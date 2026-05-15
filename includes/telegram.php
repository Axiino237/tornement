<?php
/**
 * Send a message to a Telegram chat.
 * 
 * @param string $message The message to send.
 * @return bool True if successful, false otherwise.
 */
function sendTelegramNotification($message) {
    require_once 'config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    $botToken = '8984171269:AAFNk303IeY_wYC9VzPJU213r4kT45M63YM';
    
    try {
        // Fetch all registered chats
        $stmt = $db->query("SELECT chat_id FROM telegram_chats");
        $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($chats)) {
            // Fallback to the original chat ID if none registered in DB yet
            $chats = [['chat_id' => '2013184540']];
        }

        foreach ($chats as $chat) {
            $chatId = $chat['chat_id'];
            $url = "https://api.telegram.org/bot$botToken/sendMessage";
            
            $data = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => false
            ];
            
            $options = [
                'http' => [
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($data),
                ],
            ];
            
            $context  = stream_context_create($options);
            @file_get_contents($url, false, $context);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Telegram broadcast error: " . $e->getMessage());
        return false;
    }
}
?>
