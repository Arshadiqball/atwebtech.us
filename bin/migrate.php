<?php

declare(strict_types=1);

// Applies pending SQL migrations for the configured driver. Usage: php bin/migrate.php
// The web app also runs these on startup. This command prints what was applied.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

$applied = Migrator::applied();
foreach ($applied as $name) {
    echo "Applied {$name}\n";
}

$count = count($applied);
echo $count === 0 ? "Nothing to migrate.\n" : "Done. {$count} migration(s) applied.\n";
