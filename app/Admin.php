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

    public static function topic(string $topic): string
    {
        return FormHandler::CONTACT_TOPICS[$topic] ?? $topic;
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
