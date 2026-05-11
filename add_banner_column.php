<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Connection failed!\n");
}

try {
    $db->exec("ALTER TABLE tournaments ADD COLUMN IF NOT EXISTS banner_url VARCHAR(255)");
    echo "Column 'banner_url' added successfully (or already exists)!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
