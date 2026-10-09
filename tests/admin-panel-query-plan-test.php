<?php

declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../includes/core/class-flacso-admin-panel.php');
if ($source === false) {
    throw new RuntimeException('Could not read admin panel source');
}

foreach (['admin_posts_by_type', 'integrity_alerts'] as $required) {
    if (strpos($source, $required) === false) {
        throw new RuntimeException("Missing admin panel query-plan contract: {$required}");
    }
}

if (substr_count($source, "'posts_per_page' => -1") > 3) {
    throw new RuntimeException('Admin panel still performs too many unbounded scans');
}

echo "OK admin-panel-query-plan-test\n";
