<?php
require_once 'config/database.php';

$content = file_get_contents("php://input");
$update = json_decode($content, true);

file_put_contents('telegram_log.txt', "Received: " . date('Y-m-d H:i:s') . "\n" . $content . "\n\n", FILE_APPEND);

if (!$update || (!isset($update["message"]) && !isset($update["my_chat_member"]))) {
    exit;
}

// Handle bot being added to a group (my_chat_member)
if (isset($update["my_chat_member"])) {
    $chat_id = $update["my_chat_member"]["chat"]["id"];
    $chat_type = $update["my_chat_member"]["chat"]["type"];
    $chat_title = isset($update["my_chat_member"]["chat"]["title"]) ? $update["my_chat_member"]["chat"]["title"] : "Group";
} else {
    $message = $update["message"];
    $chat_id = $message["chat"]["id"];
    $chat_type = $message["chat"]["type"];
    $chat_title = isset($message["chat"]["title"]) ? $message["chat"]["title"] : "Private Chat";
    $text = isset($message["text"]) ? $message["text"] : "";
}

$database = new Database();
$db = $database->getConnection();

try {
    // Store or update chat ID
    $stmt = $db->prepare("INSERT INTO telegram_chats (chat_id, chat_type, chat_title) 
                          VALUES (?, ?, ?) 
                          ON CONFLICT (chat_id) DO UPDATE SET 
                          chat_type = EXCLUDED.chat_type, 
                          chat_title = EXCLUDED.chat_title");
    $stmt->execute([$chat_id, $chat_type, $chat_title]);

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
