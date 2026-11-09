<?php

declare(strict_types=1);

/**
 * Paid subscriptions through Stripe Checkout, the Stripe customer portal and webhooks.
 *
 * Plans are priced per channel (see app/data/pricing.php). Each subscription has up to two
 * line items: channels 1 to (volume_from - 1) at the plan rate, and every channel after that
 * at the volume rate. Prices are created inline (price_data), so the only Stripe setup is a
 * secret key and a webhook secret. One Stripe product per plan is created on first use.
 */
final class Billing
{
    private const API = 'https://api.stripe.com';
    private const API_VERSION = '2024-06-20';
    /** Statuses that keep paid limits switched on. past_due keeps access while Stripe retries the card. */
    public const LIVE_STATUSES = ['active', 'trialing', 'past_due'];
    private const WEBHOOK_TOLERANCE = 300;

    public static function enabled(): bool
    {
        return (string) config('stripe.secret') !== '';
    }

    /** @return list<string> */
    public static function paidPlans(): array
    {
        return array_values(array_filter(array_keys(data('pricing')['plans']), static fn (string $k): bool => data('pricing')['plans'][$k]['rate'] > 0));
    }

    /**
     * Stripe line items for a plan, billing interval and channel count.
     *
     * @return list<array{label: string, unit_amount: int, quantity: int, interval: string}>
     */
    public static function lines(string $plan, string $billing, int $channels): array
    {
        $cfg = data('pricing');
        $rate = (float) $cfg['plans'][$plan]['rate'];
        $months = $billing === 'yearly' ? (int) $cfg['yearly_months'] : 1;
        $interval = $billing === 'yearly' ? 'year' : 'month';
        $included = (int) $cfg['volume_from'] - 1;
        $base = min($channels, $included);
        $extra = max(0, $channels - $included);
        $lines = [[
            'label' => 'channels 1 to ' . $included,
            'unit_amount' => (int) round($rate * $months * 100),
            'quantity' => $base,
            'interval' => $interval,
        ]];
        if ($extra > 0) {
            $lines[] = [
                'label' => 'channels ' . ($included + 1) . '+',
                'unit_amount' => (int) round((float) $cfg['volume_rate'] * $months * 100),
                'quantity' => $extra,
                'interval' => $interval,
            ];
        }
        return $lines;
    }

    /** The live paid subscription for a user, if any. */
    public static function current(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        $in = implode(',', array_map(static fn (string $s): string => "'" . $s . "'", self::LIVE_STATUSES));
        $stmt = Database::connection()->prepare("SELECT * FROM subscriptions WHERE user_id = :id AND status IN ($in) ORDER BY updated_at DESC, id DESC LIMIT 1");
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Most recent subscription of any status, for showing "canceled" or "unpaid" on the Billing page. */
    public static function latest(int $userId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM subscriptions WHERE user_id = :id ORDER BY updated_at DESC, id DESC LIMIT 1');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Starts Stripe Checkout for a new subscription and returns the hosted page URL. */
    public static function checkoutUrl(array $user, string $plan, string $billing, int $channels): string
    {
        $params = [
            'mode' => 'subscription',
            'customer' => self::customer($user),
            'client_reference_id' => (string) $user['id'],
            'success_url' => absolute_url('/app/billing/success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => absolute_url('/app/billing?canceled=1'),
            'allow_promotion_codes' => 'true',
            'billing_address_collection' => 'auto',
            'customer_update' => ['address' => 'auto', 'name' => 'auto'],
            'subscription_data' => ['metadata' => self::meta($user, $plan, $billing)],
            'metadata' => self::meta($user, $plan, $billing),
            'line_items' => [],
        ];
        if (config('stripe.automatic_tax')) {
            $params['automatic_tax'] = ['enabled' => 'true'];
        }
        $product = self::product($plan);
        foreach (self::lines($plan, $billing, $channels) as $line) {
            $params['line_items'][] = [
                'quantity' => $line['quantity'],
                'price_data' => [
                    'currency' => 'usd',
                    'product' => $product,
                    'unit_amount' => $line['unit_amount'],
                    'recurring' => ['interval' => $line['interval']],
                ],
            ];
        }
        $session = self::api('POST', '/v1/checkout/sessions', $params);
        if (!is_string($session['url'] ?? null)) {
            throw new BillingException('Stripe did not return a checkout page.');
        }
        return $session['url'];
    }

    /** Changes plan, billing interval or channel count on a live subscription, with proration. */
    public static function change(array $user, array $sub, string $plan, string $billing, int $channels): void
    {
        $remote = self::api('GET', '/v1/subscriptions/' . rawurlencode($sub['stripe_subscription_id']));
        $items = [];
        foreach ($remote['items']['data'] ?? [] as $item) {
            $items[] = ['id' => $item['id'], 'deleted' => 'true'];
        }
        $product = self::product($plan);
        foreach (self::lines($plan, $billing, $channels) as $line) {
            $items[] = [
                'quantity' => $line['quantity'],
                'price_data' => [
                    'currency' => 'usd',
                    'product' => $product,
                    'unit_amount' => $line['unit_amount'],
                    'recurring' => ['interval' => $line['interval']],
                ],
            ];
        }
        $updated = self::api('POST', '/v1/subscriptions/' . rawurlencode($sub['stripe_subscription_id']), [
            'items' => $items,
            'proration_behavior' => 'create_prorations',
            'cancel_at_period_end' => 'false',
            'metadata' => self::meta($user, $plan, $billing),
        ]);
        self::store($updated);
    }

    /** Stripe customer portal: card, invoices, cancel. */
    public static function portalUrl(array $user): string
    {
        $session = self::api('POST', '/v1/billing_portal/sessions', [
            'customer' => self::customer($user),
            'return_url' => absolute_url('/app/billing'),
        ]);
        if (!is_string($session['url'] ?? null)) {
            throw new BillingException('Stripe did not return a portal page.');
        }
        return $session['url'];
    }

    /** After Checkout returns: confirm the session belongs to this user and record the subscription now. */
    public static function completeCheckout(array $user, string $sessionId): ?array
    {
        if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
            return null;
        }
        $session = self::api('GET', '/v1/checkout/sessions/' . rawurlencode($sessionId));
        if ((string) ($session['client_reference_id'] ?? '') !== (string) $user['id'] || !is_string($session['subscription'] ?? null)) {
            return null;
        }
        return self::sync($session['subscription']);
    }

    /** Fetches a subscription from Stripe and saves it. */
    public static function sync(string $subscriptionId): ?array
    {
        $remote = self::api('GET', '/v1/subscriptions/' . rawurlencode($subscriptionId));
        return self::store($remote);
    }

    /** Verifies the Stripe-Signature header. */
    public static function verifySignature(string $payload, string $header, string $secret, ?int $now = null): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't' && ctype_digit($v)) {
                $timestamp = (int) $v;
            } elseif ($k === 'v1' && $v !== '') {
                $signatures[] = $v;
            }
        }
        if ($timestamp === null || $signatures === [] || abs(($now ?? time()) - $timestamp) > self::WEBHOOK_TOLERANCE) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }
        return false;
    }

    /** POST /stripe/webhook */
    public static function webhook(): never
    {
        $payload = (string) file_get_contents('php://input');
        if (!self::verifySignature($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), (string) config('stripe.webhook_secret'))) {
            json_response(['error' => 'invalid signature'], 400);
        }
        $event = json_decode($payload, true);
        if (!is_array($event) || !is_string($event['id'] ?? null) || !is_string($event['type'] ?? null)) {
            json_response(['error' => 'invalid payload'], 400);
        }
        $pdo = Database::connection();
        $seen = $pdo->prepare('SELECT 1 FROM stripe_events WHERE id = :id');
        $seen->execute(['id' => $event['id']]);
        if ($seen->fetchColumn()) {
            json_response(['received' => true, 'duplicate' => true]);
        }

        $object = $event['data']['object'] ?? [];
        try {
            switch ($event['type']) {
                case 'checkout.session.completed':
                    if (is_string($object['subscription'] ?? null)) {
                        self::sync($object['subscription']);
                    }
                    break;
                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                case 'customer.subscription.paused':
                case 'customer.subscription.resumed':
                    if (is_string($object['id'] ?? null)) {
                        self::sync($object['id']);
                    }
                    break;
                case 'invoice.paid':
                case 'invoice.payment_failed':
                    $sub = $object['subscription'] ?? ($object['parent']['subscription_details']['subscription'] ?? null);
                    if (is_string($sub)) {
                        self::sync($sub);
                    }
                    break;
            }
        } catch (Throwable $e) {
            error_log(sprintf('[%s] stripe webhook %s failed: %s', gmdate('c'), $event['type'], $e->getMessage()));
            json_response(['error' => 'processing failed'], 500);
        }

        $pdo->prepare('INSERT INTO stripe_events (id, type, received_at) VALUES (:id, :type, :at)')
            ->execute(['id' => $event['id'], 'type' => mb_substr($event['type'], 0, 80), 'at' => Database::now()]);
        json_response(['received' => true]);
    }

    /** Saves a Stripe subscription object. Returns the stored row, or null when it can't be tied to a user. */
    private static function store(array $remote): ?array
    {
        $id = (string) ($remote['id'] ?? '');
        $customer = is_array($remote['customer'] ?? null) ? (string) ($remote['customer']['id'] ?? '') : (string) ($remote['customer'] ?? '');
        if ($id === '' || $customer === '') {
            return null;
        }
        $pdo = Database::connection();
        $meta = is_array($remote['metadata'] ?? null) ? $remote['metadata'] : [];
        $userId = (int) ($meta['user_id'] ?? 0);
        if ($userId <= 0) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE stripe_customer_id = :c');
            $stmt->execute(['c' => $customer]);
            $userId = (int) $stmt->fetchColumn();
        }
        if ($userId <= 0) {
            error_log(sprintf('[%s] stripe subscription %s has no matching user', gmdate('c'), $id));
            return null;
        }

        $items = $remote['items']['data'] ?? [];
        $channels = 0;
        $interval = 'month';
        $periodEnd = $remote['current_period_end'] ?? null;
        foreach ($items as $item) {
            $channels += (int) ($item['quantity'] ?? 0);
            $interval = (string) ($item['price']['recurring']['interval'] ?? $interval);
            $periodEnd ??= $item['current_period_end'] ?? null;
        }
        $plan = in_array($meta['plan'] ?? '', self::paidPlans(), true) ? $meta['plan'] : 'essentials';
        $row = [
            'user_id' => $userId,
            'sub' => $id,
            'customer' => $customer,
            'plan' => $plan,
            'billing' => $interval === 'year' ? 'yearly' : 'monthly',
            'channels' => max(1, $channels),
            'status' => mb_substr((string) ($remote['status'] ?? 'incomplete'), 0, 24),
            'cancel' => !empty($remote['cancel_at_period_end']) ? 1 : 0,
            'period_end' => is_numeric($periodEnd) ? gmdate('Y-m-d H:i:s', (int) $periodEnd) : null,
            'now' => Database::now(),
        ];

        $exists = $pdo->prepare('SELECT id FROM subscriptions WHERE stripe_subscription_id = :sub');
        $exists->execute(['sub' => $id]);
        if ($exists->fetchColumn()) {
            unset($row['now']);
            $pdo->prepare('UPDATE subscriptions SET user_id = :user_id, stripe_customer_id = :customer, plan = :plan, billing = :billing, channels = :channels,
                status = :status, cancel_at_period_end = :cancel, current_period_end = :period_end, updated_at = :now WHERE stripe_subscription_id = :sub')
                ->execute($row + ['now' => Database::now()]);
        } else {
            $pdo->prepare('INSERT INTO subscriptions (user_id, stripe_subscription_id, stripe_customer_id, plan, billing, channels, status, cancel_at_period_end, current_period_end, created_at, updated_at)
                VALUES (:user_id, :sub, :customer, :plan, :billing, :channels, :status, :cancel, :period_end, :now, :now2)')
                ->execute($row + ['now2' => $row['now']]);
        }
        $pdo->prepare('UPDATE users SET stripe_customer_id = :c WHERE id = :id AND (stripe_customer_id IS NULL OR stripe_customer_id = \'\')')
            ->execute(['c' => $customer, 'id' => $userId]);

        $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE stripe_subscription_id = :sub');
        $stmt->execute(['sub' => $id]);
        return $stmt->fetch() ?: null;
    }

    private static function meta(array $user, string $plan, string $billing): array
    {
        return ['user_id' => (string) $user['id'], 'plan' => $plan, 'billing' => $billing];
    }

    /** The user's Stripe customer, created on first use. */
    private static function customer(array $user): string
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT stripe_customer_id FROM users WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);
        $existing = (string) $stmt->fetchColumn();
        if ($existing !== '') {
            return $existing;
        }
        $customer = self::api('POST', '/v1/customers', [
            'email' => (string) $user['email'],
            'name' => (string) ($user['display_name'] ?? ''),
            'metadata' => ['user_id' => (string) $user['id']],
        ], 'customer-' . $user['id']);
        $id = (string) ($customer['id'] ?? '');
        if ($id === '') {
            throw new BillingException('Stripe did not create a customer.');
        }
        $pdo->prepare('UPDATE users SET stripe_customer_id = :c WHERE id = :id')->execute(['c' => $id, 'id' => $user['id']]);
        return $id;
    }

    /** One Stripe product per plan, created on first use and remembered. */
    private static function product(string $plan): string
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT stripe_product_id FROM billing_products WHERE plan = :plan');
        $stmt->execute(['plan' => $plan]);
        $existing = (string) $stmt->fetchColumn();
        if ($existing !== '') {
            return $existing;
        }
        $name = (string) data('pricing')['plans'][$plan]['name'];
        $product = self::api('POST', '/v1/products', [
            'name' => 'atwebtech ' . $name,
            'description' => 'atwebtech ' . $name . ' plan, billed per connected channel.',
            'metadata' => ['plan' => $plan],
        ], 'product-' . $plan . '-' . substr(hash('sha256', (string) config('stripe.secret')), 0, 12));
        $id = (string) ($product['id'] ?? '');
        if ($id === '') {
            throw new BillingException('Stripe did not create a product.');
        }
        $pdo->prepare('INSERT INTO billing_products (plan, stripe_product_id, created_at) VALUES (:plan, :id, :at)')
            ->execute(['plan' => $plan, 'id' => $id, 'at' => Database::now()]);
        return $id;
    }

    private static function api(string $method, string $path, array $params = [], ?string $idempotencyKey = null): array
    {
        $secret = (string) config('stripe.secret');
        if ($secret === '') {
            throw new BillingException('Billing is not set up yet.');
        }
        $base = (string) config('stripe.test_base');
        $url = ($base !== '' && !config('app.is_production') ? rtrim($base, '/') : self::API) . $path;
        $headers = ['Authorization: Bearer ' . $secret, 'Stripe-Version: ' . self::API_VERSION, 'Accept: application/json'];
        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | (config('app.is_production') ? 0 : CURLPROTO_HTTP),
        ]);
        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_URL, $url . ($params ? '?' . http_build_query($params) : ''));
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false) {
            throw new BillingException('Could not reach Stripe: ' . curl_error($ch));
        }
        $data = json_decode((string) $body, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($data) ? (string) ($data['error']['message'] ?? '') : '';
            throw new BillingException(sprintf('Stripe returned HTTP %d. %s', $status, mb_substr($message, 0, 200)));
        }
        return is_array($data) ? $data : [];
    }
}

final class BillingException extends RuntimeException
{
}
