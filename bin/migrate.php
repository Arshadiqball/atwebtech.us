<?php

declare(strict_types=1);

// Applies pending SQL migrations for the configured driver. Usage: php bin/migrate.php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

$driver = Database::driver();
$pdo = Database::connection();

$pdo->exec($driver === 'sqlite'
    ? 'CREATE TABLE IF NOT EXISTS schema_migrations (name TEXT PRIMARY KEY, applied_at TEXT NOT NULL)'
    : 'CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$applied = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(BASE_PATH . "/database/migrations/*.{$driver}.sql") ?: [];
sort($files);

$count = 0;
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
    echo "Applied {$name}\n";
    $count++;
}

echo $count === 0 ? "Nothing to migrate.\n" : "Done. {$count} migration(s) applied.\n";
