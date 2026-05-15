<?php
require_once 'config/database.php';

$content = file_get_contents("php://input");
$update = json_decode($content, true);

// Debug: Log everything
file_put_contents('telegram_log.txt', "Update Received: " . date('Y-m-d H:i:s') . "\n" . json_encode($update, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

if (!$update || (!isset($update["message"]) && !isset($update["my_chat_member"]))) {
    exit;
}

// Extract chat info from different update types
if (isset($update["message"])) {
    $chat_id = $update["message"]["chat"]["id"];
    $chat_type = $update["message"]["chat"]["type"];
    $chat_title = isset($update["message"]["chat"]["title"]) ? $update["message"]["chat"]["title"] : "Private Chat";
    $text = isset($update["message"]["text"]) ? $update["message"]["text"] : "";
} elseif (isset($update["channel_post"])) {
    $chat_id = $update["channel_post"]["chat"]["id"];
    $chat_type = $update["channel_post"]["chat"]["type"];
    $chat_title = isset($update["channel_post"]["chat"]["title"]) ? $update["channel_post"]["chat"]["title"] : "Channel";
    $text = isset($update["channel_post"]["text"]) ? $update["channel_post"]["text"] : "";
} elseif (isset($update["my_chat_member"])) {
    $chat_id = $update["my_chat_member"]["chat"]["id"];
    $chat_type = $update["my_chat_member"]["chat"]["type"];
    $chat_title = isset($update["my_chat_member"]["chat"]["title"]) ? $update["my_chat_member"]["chat"]["title"] : "Group";
    $text = "";
} else {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Auto-create table if missing (safety check)
    $db->exec("CREATE TABLE IF NOT EXISTS telegram_chats (
        id SERIAL PRIMARY KEY,
        chat_id VARCHAR(50) UNIQUE,
        chat_type VARCHAR(20),
        chat_title VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Store or update chat ID
    $stmt = $db->prepare("INSERT INTO telegram_chats (chat_id, chat_type, chat_title) 
                          VALUES (?, ?, ?) 
                          ON CONFLICT (chat_id) DO UPDATE SET 
                          chat_type = EXCLUDED.chat_type, 
                          chat_title = EXCLUDED.chat_title");
    $result = $stmt->execute([$chat_id, $chat_type, $chat_title]);
    
    file_put_contents('telegram_log.txt', "DB Insert Result: " . ($result ? "Success" : "Fail") . "\n", FILE_APPEND);
    if (!$result) {
        file_put_contents('telegram_log.txt', "DB Error Info: " . print_r($stmt->errorInfo(), true) . "\n", FILE_APPEND);
    }

    // Respond to /start or if bot is added to group
    if (strpos($text, "/start") === 0 || isset($update["my_chat_member"])) {
        $botToken = '8984171269:AAFNk303IeY_wYC9VzPJU213r4kT45M63YM';
        $response = "🚀 <b>FireCrown Bot Connected!</b>\n\nThis chat (ID: $chat_id) has been registered. You will now receive tournament updates here.";
        
        $url = "https://api.telegram.org/bot$botToken/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $response,
            'parse_mode' => 'HTML'
        ];
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
            ],
        ];
        $context = stream_context_create($options);
        file_get_contents($url, false, $context);
    }
} catch (Exception $e) {
    file_put_contents('telegram_log.txt', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>
