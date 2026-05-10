<?php
/**
 * Database Verification Script
 * Tests database connection and verifies all tables exist
 */

echo "=== DTI Database Verification ===\n\n";

try {
    require_once __DIR__ . '/includes/init.php';
    
    // Get database connection
    $dbName = 'dti';
    
    // Test 1: Connection
    echo "[1/4] Testing database connection... ";
    if ($mysqli && !$mysqli->connect_errno) {
        echo "✓ Connected\n";
    } else {
        echo "✗ Failed\n";
        exit(1);
    }
    
    // Test 2: Database exists
    echo "[2/4] Testing database '$dbName'... ";
    $result = $mysqli->query("SELECT DATABASE()");
    $row = $result->fetch_row();
    if ($row[0] === $dbName) {
        echo "✓ Database selected\n";
    } else {
        echo "✗ Database not selected\n";
        exit(1);
    }
    
    // Test 3: Tables exist
    echo "[3/4] Checking tables...\n";
    $expectedTables = [
        'users',
        'activities',
        'supply_requests',
        'ob_slips',
        'vehicle_requests',
        'passengers',
        'leave_requests',
        'notifications',
        'employee_events',
        'user_login_logs'
    ];
    
    $allTablesExist = true;
    foreach ($expectedTables as $table) {
        $check = $mysqli->query("SHOW TABLES LIKE '$table'");
        if ($check && $check->num_rows > 0) {
            echo "     ✓ $table\n";
        } else {
            echo "     ✗ $table (MISSING)\n";
            $allTablesExist = false;
        }
    }
    
    if (!$allTablesExist) {
        echo "\n[WARNING] Some tables are missing. Re-run includes/init.php\n";
    }
    
    // Test 4: Get statistics
    echo "[4/4] Database statistics:\n";
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT table_name) as table_count,
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
        FROM information_schema.tables 
        WHERE table_schema = '$dbName'
    ";
    $result = $mysqli->query($statsQuery);
    $stats = $result->fetch_assoc();
    echo "     Tables: " . $stats['table_count'] . "\n";
    echo "     Size: " . $stats['size_mb'] . " MB\n";
    
    // Check record counts
    echo "\n=== Record Counts ===\n";
    foreach ($expectedTables as $table) {
        $countResult = $mysqli->query("SELECT COUNT(*) as cnt FROM $table");
        if ($countResult) {
            $countRow = $countResult->fetch_assoc();
            echo "$table: " . $countRow['cnt'] . " records\n";
        }
    }
    
    echo "\n✓ Database verification complete!\n";
    echo "\nConnection Details:\n";
    echo "  Host: " . $mysqli->get_host_info() . "\n";
    echo "  Charset: " . $mysqli->get_charset()->charset . "\n";
    echo "  Server: " . $mysqli->server_info . "\n";
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
