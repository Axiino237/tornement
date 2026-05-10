<?php
require_once 'config/database.php';

echo "--- Database Migration: Adding device_id column ---\n";

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed!\n");
}

try {
    // Check if column exists
    $stmt = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name='users' AND column_name='device_id'");
    if ($stmt->fetch()) {
        echo "Column 'device_id' already exists.\n";
    } else {
        echo "Adding 'device_id' column to 'users' table...\n";
        $db->exec("ALTER TABLE users ADD COLUMN device_id VARCHAR(255) DEFAULT 'unknown'");
        echo "Column 'device_id' added successfully!\n";
    }
    
    // Also ensure index for performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_device_id ON users(device_id)");
    echo "Index created successfully!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nMigration Complete.\n";
?>
