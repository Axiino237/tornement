<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h3>Telegram Debug Status</h3>";

try {
    // Auto-create table if missing
    $db->exec("CREATE TABLE IF NOT EXISTS telegram_chats (
        id SERIAL PRIMARY KEY,
        chat_id VARCHAR(50) UNIQUE,
        chat_type VARCHAR(20),
        chat_title VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Check Table
    $stmt = $db->query("SELECT * FROM telegram_chats");
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<b>Registered Chats:</b> " . count($chats) . "<br>";
    if (count($chats) > 0) {
        echo "<pre>";
        print_r($chats);
        echo "</pre>";
    } else {
        echo "No chats registered yet. Webhook might not be working.<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

echo "<h3>Webhook Logs</h3>";
if (file_exists('telegram_log.txt')) {
    echo "<pre>" . htmlspecialchars(file_get_contents('telegram_log.txt')) . "</pre>";
} else {
    echo "No log file found. Webhook has not been called yet.";
}
?>
