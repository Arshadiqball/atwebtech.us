<?php

declare(strict_types=1);

/** Remembers the ad or email link that brought someone here, for this browser session. */
final class Campaign
{
    /** @var array<string, int> */
    private const FIELDS = [
        'utm_source' => 80,
        'utm_medium' => 80,
        'utm_campaign' => 120,
        'utm_content' => 120,
        'utm_term' => 120,
    ];

    /** @var array<string, int> */
    private const CLICKS = [
        'gclid' => 180,
        'fbclid' => 180,
        'msclkid' => 180,
    ];

    public static function capture(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $current = is_array($_SESSION['campaign'] ?? null) ? $_SESSION['campaign'] : [];
        $changed = false;
        foreach (self::FIELDS as $key => $max) {
            $value = self::clean($_GET[$key] ?? null, $max);
            if ($value !== null && empty($current[$key])) {
                $current[$key] = $value;
                $changed = true;
            }
        }
        foreach (self::CLICKS as $key => $max) {
            $value = self::clean($_GET[$key] ?? null, $max, true);
            if ($value !== null && empty($current['click_id'])) {
                $current['click_kind'] = $key;
                $current['click_id'] = $value;
                $changed = true;
                break;
            }
        }
        if ($changed) {
            $_SESSION['campaign'] = $current;
        }
    }

    /** @return array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, utm_content: ?string, utm_term: ?string, click_kind: ?string, click_id: ?string} */
    public static function get(): array
    {
        $current = is_array($_SESSION['campaign'] ?? null) ? $_SESSION['campaign'] : [];
        $out = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $out[$key] = isset($current[$key]) ? (string) $current[$key] : null;
        }
        $out['click_kind'] = isset($current['click_kind']) ? (string) $current['click_kind'] : null;
        $out['click_id'] = isset($current['click_id']) ? (string) $current['click_id'] : null;
        return $out;
    }

    public static function label(): string
    {
        $c = self::get();
        $parts = array_filter([
            $c['utm_source'],
            $c['utm_medium'],
            $c['utm_campaign'],
            $c['click_kind'],
        ]);
        return $parts === [] ? 'Direct' : implode(' / ', $parts);
    }

    private static function clean(mixed $value, int $max, bool $click = false): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            return null;
        }
        $pattern = $click ? '/^[A-Za-z0-9_-]+$/' : '/^[\p{L}\p{N} ._+|\-]+$/u';
        return preg_match($pattern, $value) === 1 ? $value : null;
    }
}
