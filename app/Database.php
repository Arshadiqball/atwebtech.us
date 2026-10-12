<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if (self::driver() === 'sqlite') {
            $path = (string) config('db.sqlite_path');
            if (!str_starts_with($path, '/')) {
                $path = BASE_PATH . '/' . $path;
            }
            self::$pdo = new PDO('sqlite:' . $path, null, null, $options);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            config('db.host'),
            config('db.port'),
            config('db.name')
        );
        self::$pdo = new PDO($dsn, (string) config('db.user'), (string) config('db.pass'), $options);
        return self::$pdo;
    }

    public static function driver(): string
    {
        return config('db.driver') === 'sqlite' ? 'sqlite' : 'mysql';
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
