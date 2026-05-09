<?php
require_once 'config/database.php';

echo "Starting migration to Supabase...\n";

// Load .env manually since we don't have a library
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . "=" . trim($value));
    }
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Connection failed!\n");
}

echo "Connected to Supabase. Reading schema...\n";

$sql = file_get_contents('supabase_schema.sql');

try {
    // Split SQL by semicolon, but be careful with enums/functions
    // For simplicity, we'll run it in one go if PDO supports it, 
    // or try to execute the whole thing.
    $db->exec($sql);
    echo "Schema executed successfully!\n";
} catch (PDOException $e) {
    echo "Error executing schema: " . $e->getMessage() . "\n";
}
