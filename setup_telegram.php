<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $sql = "CREATE TABLE IF NOT EXISTS telegram_chats (
        id SERIAL PRIMARY KEY,
        chat_id VARCHAR(50) UNIQUE,
        chat_type VARCHAR(20),
        chat_title VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $db->exec($sql);
    echo "Table 'telegram_chats' created or already exists.<br>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
