<?php
// Safe CLI/Browse script to drop leave_requests table. Run from project root.
require_once __DIR__ . '/../includes/db.php';
$mysqli = getDB();

if (!$mysqli) {
    echo "✗ Failed to connect to DB\n";
    exit(1);
}

// Confirm intent when run via browser
if (php_sapi_name() !== 'cli') {
    echo "This script drops the 'leave_requests' table.\n";
    echo "Run from CLI or provide ?confirm=1 to proceed.\n";
    if (empty($_GET['confirm'])) {
        echo "Add ?confirm=1 to the URL to proceed.\n";
        exit;
    }
}

// Execute drop
if ($mysqli->query("DROP TABLE IF EXISTS leave_requests")) {
    echo "✓ 'leave_requests' table dropped (if existed).\n";
    exit(0);
} else {
    echo "✗ Failed to drop 'leave_requests': " . $mysqli->error . "\n";
    exit(2);
}
