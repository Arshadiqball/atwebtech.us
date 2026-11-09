<?php

declare(strict_types=1);

final class BillingHandler
{
    /** GET /upgrade: a pricing-page "Upgrade" button. Guests sign up first, then land on Billing. */
    public static function upgrade(): never
    {
        $intent = self::intent($_GET);
        if (Auth::check()) {
            redirect('/app/billing?' . http_build_query($intent));
        }
        $_SESSION['_upgrade'] = $intent;
        redirect('/signup');
    }

    public static function pageState(array $user): array
    {
        $uid = (int) $user['id'];
        $current = Billing::enabled() ? Billing::current($uid) : null;
        $connected = count(Channels::forUser($uid));
        $defaults = [
            'plan' => $current['plan'] ?? 'essentials',
            'billing' => $current['billing'] ?? 'yearly',
            'channels' => (string) max(1, (int) ($current['channels'] ?? $connected)),
        ];
        $selected = self::intent($_GET + $defaults);
        $selected['channels'] = max($selected['channels'], $connected, 1);
        return [
            'billingEnabled' => Billing::enabled(),
            'subscription' => $current,
            'latest' => Billing::enabled() ? Billing::latest($uid) : null,
            'connectedCount' => $connected,
            'selected' => $selected,
            'canceled' => isset($_GET['canceled']),
            'hasCustomer' => self::customerId($uid) !== '',
        ];
    }

    /** POST /app/billing/checkout: new subscription through Stripe Checkout, or a change to a live one. */
    public static function checkout(): never
    {
        $user = Auth::member();
        $intent = self::intent($_POST);
        $back = '/app/billing?' . http_build_query($intent);
        if (!Billing::enabled()) {
            self::notice($back, 'Paid plans are not open yet. We will email you when they are.');
        }
        $connected = count(Channels::forUser((int) $user['id']));
        if ($intent['channels'] < $connected) {
            self::notice($back, sprintf('You have %d channels connected. Choose at least %d, or disconnect a channel first.', $connected, $connected));
        }
        try {
            $current = Billing::current((int) $user['id']);
            if ($current !== null) {
                Billing::change($user, $current, $intent['plan'], $intent['billing'], $intent['channels']);
                self::notice('/app/billing', sprintf('Your plan is now %s for %d %s. Any difference is prorated on your next invoice.',
                    data('pricing')['plans'][$intent['plan']]['name'], $intent['channels'], $intent['channels'] === 1 ? 'channel' : 'channels'));
            }
            redirect(Billing::checkoutUrl($user, $intent['plan'], $intent['billing'], $intent['channels']));
        } catch (BillingException $e) {
            error_log(sprintf('[%s] billing checkout for user %d failed: %s', gmdate('c'), $user['id'], $e->getMessage()));
            self::notice($back, 'We could not reach our payment provider. Please try again in a minute.');
        }
    }

    /** GET /app/billing/success: Stripe Checkout sends the customer back here. */
    public static function success(): never
    {
        $user = Auth::require();
        $sub = null;
        try {
            $sub = Billing::completeCheckout($user, (string) ($_GET['session_id'] ?? ''));
        } catch (BillingException $e) {
            error_log(sprintf('[%s] billing success for user %d: %s', gmdate('c'), $user['id'], $e->getMessage()));
        }
        if ($sub !== null && in_array($sub['status'], Billing::LIVE_STATUSES, true)) {
            self::notice('/app/billing', sprintf('Welcome to %s. Your paid features are on.', data('pricing')['plans'][$sub['plan']]['name'] ?? 'your new plan'));
        }
        self::notice('/app/billing', 'Thanks! Your payment is processing. Your plan will update here in a moment.');
    }

    /** POST /app/billing/portal: card, invoices and cancellation are handled by Stripe. */
    public static function portal(): never
    {
        $user = Auth::member();
        if (!Billing::enabled() || self::customerId((int) $user['id']) === '') {
            self::notice('/app/billing', 'There is no billing account to manage yet.');
        }
        try {
            redirect(Billing::portalUrl($user));
        } catch (BillingException $e) {
            error_log(sprintf('[%s] billing portal for user %d failed: %s', gmdate('c'), $user['id'], $e->getMessage()));
            self::notice('/app/billing', 'We could not open billing settings. Please try again in a minute.');
        }
    }

    /** @return array{plan: string, billing: string, channels: int} */
    private static function intent(array $input): array
    {
        $plan = (string) ($input['plan'] ?? '');
        return [
            'plan' => in_array($plan, Billing::paidPlans(), true) ? $plan : 'essentials',
            'billing' => Pricing::billing($input['billing'] ?? 'yearly'),
            'channels' => Pricing::clampChannels($input['channels'] ?? 1),
        ];
    }

    private static function customerId(int $uid): string
    {
        $stmt = Database::connection()->prepare('SELECT stripe_customer_id FROM users WHERE id = :id');
        $stmt->execute(['id' => $uid]);
        return (string) $stmt->fetchColumn();
    }

    private static function notice(string $location, string $message): never
    {
        flash('notice', $message);
        redirect($location);
    }
}
