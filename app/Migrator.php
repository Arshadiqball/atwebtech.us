<?php

declare(strict_types=1);

// Applies pending SQL migrations. The web app runs this on startup so a deploy
// picks up new files in database/migrations without a manual import.

final class Migrator
{
    /** @var list<string> */
    private static array $applied = [];

    private static bool $ran = false;

    /** @return list<string> Migration filenames applied during this process. */
    public static function applied(): array
    {
        return self::$applied;
    }

    /** @return list<string> */
    public static function run(): array
    {
        if (self::$ran) {
            return self::$applied;
        }
        self::$ran = true;

        $driver = Database::driver();
        $files = glob(BASE_PATH . "/database/migrations/*.{$driver}.sql") ?: [];
        sort($files);
        $stamp = self::stamp($files);
        $stampFile = BASE_PATH . '/storage/migrations.stamp';

        if (self::stampMatches($stampFile, $stamp)) {
            return self::$applied;
        }

        $lockFile = BASE_PATH . '/storage/migrations.lock';
        $lock = fopen($lockFile, 'c');
        if ($lock === false) {
            throw new RuntimeException('Could not open the migration lock.');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Could not lock migrations.');
            }
            if (self::stampMatches($stampFile, $stamp)) {
                return self::$applied;
            }

            self::$applied = self::apply($driver, $files);
            file_put_contents($stampFile, $stamp);
            return self::$applied;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param list<string> $files */
    private static function stamp(array $files): string
    {
        $parts = [];
        foreach ($files as $file) {
            $parts[] = basename($file) . ':' . hash_file('sha256', $file);
        }
        return hash('sha256', implode("\n", $parts));
    }

    private static function stampMatches(string $stampFile, string $stamp): bool
    {
        return is_file($stampFile) && hash_equals($stamp, trim((string) file_get_contents($stampFile)));
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    private static function apply(string $driver, array $files): array
    {
        $pdo = Database::connection();
        $pdo->exec($driver === 'sqlite'
            ? 'CREATE TABLE IF NOT EXISTS schema_migrations (name TEXT PRIMARY KEY, applied_at TEXT NOT NULL)'
            : 'CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $applied = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $appliedNow = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }
            $statements = array_filter(array_map('trim', explode(';', (string) file_get_contents($file))));
            foreach ($statements as $sql) {
                $pdo->exec($sql);
            }
            $pdo->prepare('INSERT INTO schema_migrations (name, applied_at) VALUES (:name, :at)')
                ->execute(['name' => $name, 'at' => Database::now()]);
            $appliedNow[] = $name;
        }

        return $appliedNow;
    }
}
