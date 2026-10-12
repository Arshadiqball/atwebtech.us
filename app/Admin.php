<?php

declare(strict_types=1);

final class Admin
{
    private const LIMIT = 200;

    public static function messages(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, name, email, topic, message, created_at
             FROM enquiries
             ORDER BY created_at DESC, id DESC
             LIMIT ' . self::LIMIT
        );
        return $stmt->fetchAll();
    }

    public static function message(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, email, topic, message, created_at FROM enquiries WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function subscribers(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, email, role, plan, billing, channels, source, utm_source, utm_medium, utm_campaign, utm_content, utm_term, click_kind, created_at, updated_at
             FROM waitlist_signups
             ORDER BY updated_at DESC, id DESC
             LIMIT ' . self::LIMIT
        );
        return $stmt->fetchAll();
    }

    public static function subscriber(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, email, role, plan, billing, channels, source, utm_source, utm_medium, utm_campaign, utm_content, utm_term, click_kind, created_at, updated_at
             FROM waitlist_signups WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function topic(string $topic): string
    {
        return FormHandler::CONTACT_TOPICS[$topic] ?? $topic;
    }

    public static function role(string $role): string
    {
        return FormHandler::WAITLIST_ROLES[$role] ?? $role;
    }

    public static function source(string $source): string
    {
        return [
            'hero' => 'Homepage',
            'final-cta' => 'Page ending',
            'pricing' => 'Pricing',
            'feature' => 'Feature page',
            'solution' => 'Solution page',
            'resources' => 'Resources',
            'demo' => 'Demo',
            'contact' => 'Contact page',
            'join' => 'Waitlist page',
            'page' => 'Website',
        ][$source] ?? $source;
    }

    public static function plan(string $plan): string
    {
        $plans = data('pricing')['plans'] ?? [];
        return $plans[$plan]['name'] ?? $plan;
    }

    public static function excerpt(string $message, int $length = 120): string
    {
        $flat = preg_replace('/\s+/u', ' ', trim($message)) ?? '';
        if (mb_strlen($flat) <= $length) {
            return $flat;
        }
        return rtrim(mb_substr($flat, 0, $length - 1)) . '…';
    }

    public static function when(string $utc): DateTimeImmutable
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $utc, new DateTimeZone('UTC'));
        return $dt instanceof DateTimeImmutable ? $dt : new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
