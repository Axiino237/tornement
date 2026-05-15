<?php
require_once 'config/database.php';

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update || !isset($update["message"])) {
    exit;
}

$message = $update["message"];
$chat_id = $message["chat"]["id"];
$chat_type = $message["chat"]["type"];
$chat_title = isset($message["chat"]["title"]) ? $message["chat"]["title"] : "Private Chat";
$text = isset($message["text"]) ? $message["text"] : "";

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

    // Respond to /start
    if ($text === "/start") {
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
    error_log("Telegram Webhook Error: " . $e->getMessage());
}
?>
