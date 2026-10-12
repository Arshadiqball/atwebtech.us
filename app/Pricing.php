<?php

declare(strict_types=1);

/** Price calculations for app/data/pricing.php. public/assets/js/app.js mirrors this formula. */
final class Pricing
{
    public const BILLING = ['monthly', 'yearly'];

    public static function clampChannels(mixed $value): int
    {
        $max = (int) data('pricing')['max_channels'];
        $n = filter_var($value, FILTER_VALIDATE_INT);
        return max(1, min($max, $n === false ? 1 : $n));
    }

    public static function billing(mixed $value): string
    {
        return in_array($value, self::BILLING, true) ? $value : 'yearly';
    }

    /** Monthly list price for a plan and channel count. */
    public static function monthlyTotal(string $plan, int $channels): float
    {
        $cfg = data('pricing');
        $rate = (float) $cfg['plans'][$plan]['rate'];
        $standard = min($channels, $cfg['volume_from'] - 1);
        $extra = max(0, $channels - ($cfg['volume_from'] - 1));
        return $standard * $rate + $extra * (float) $cfg['volume_rate'];
    }

    /** @return array{per_month: string, note: string} */
    public static function quote(string $plan, int $channels, string $billing): array
    {
        $cfg = data('pricing');
        $monthly = self::monthlyTotal($plan, $channels);
        $label = $channels . ' ' . ($channels === 1 ? 'channel' : 'channels');
        if ($billing === 'monthly') {
            return ['per_month' => self::money($monthly), 'note' => $label . ' · Billed monthly'];
        }
        $yearly = $monthly * $cfg['yearly_months'];
        $saved = 12 - $cfg['yearly_months'];
        return [
            'per_month' => self::money($yearly / 12),
            'note' => $label . ' · ' . self::money($yearly) . ' billed yearly (save ' . $saved . ' months)',
        ];
    }

    public static function money(float $amount): string
    {
        $amount = round($amount, 2);
        $decimals = fmod($amount, 1.0) === 0.0 ? 0 : 2;
        return data('pricing')['currency'] . number_format($amount, $decimals);
    }
}
