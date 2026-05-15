<?php
/**
 * Send a message to a Telegram chat.
 * 
 * @param string $message The message to send.
 * @return bool True if successful, false otherwise.
 */
function sendTelegramNotification($message) {
    // These should ideally be in .env but we can hardcode for now as per user request
    $botToken = '8984171269:AAFNk303IeY_wYC9VzPJU213r4kT45M63YM';
    $chatId = '2013184540';
    
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    return $result !== false;
}
?>
