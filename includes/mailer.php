<?php
/**
 * Standalone SMTP Mailer Class
 * No external libraries (PHPMailer/SwiftMailer) required.
 */
class SimpleSMTP {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $debug = false;

    public function __construct($host, $port, $user, $pass) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function send($from_email, $from_name, $to, $subject, $body) {
        $timeout = 10;
        $socket = stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, $timeout);

        if (!$socket) return false;

        $this->getResponse($socket); // 220

        fwrite($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
        $this->getResponse($socket);

        fwrite($socket, "STARTTLS\r\n");
        $this->getResponse($socket);

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }

        fwrite($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
        $this->getResponse($socket);

        fwrite($socket, "AUTH LOGIN\r\n");
        $this->getResponse($socket);

        fwrite($socket, base64_encode($this->user) . "\r\n");
        $this->getResponse($socket);

        fwrite($socket, base64_encode($this->pass) . "\r\n");
        $this->getResponse($socket);

        fwrite($socket, "MAIL FROM: <{$this->user}>\r\n");
        $this->getResponse($socket);

        fwrite($socket, "RCPT TO: <{$to}>\r\n");
        $this->getResponse($socket);

        fwrite($socket, "DATA\r\n");
        $this->getResponse($socket);

        $headers = [
            "MIME-Version: 1.0",
            "Content-type: text/html; charset=utf-8",
            "To: <{$to}>",
            "From: {$from_name} <{$from_email}>",
            "Subject: {$subject}",
            "Date: " . date("r")
        ];

        fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
        $this->getResponse($socket);

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return true;
    }

    private function getResponse($socket) {
        $response = "";
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == " ") break;
        }
        return $response;
    }
}

function send_email($to, $subject, $body) {
    global $db;
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    $smtp = new SimpleSMTP(
        $settings['smtp_host'] ?? 'smtp.gmail.com',
        $settings['smtp_port'] ?? 587,
        $settings['smtp_user'] ?? '',
        $settings['smtp_pass'] ?? ''
    );

    return $smtp->send(
        $settings['smtp_from_email'] ?? $settings['smtp_user'],
        $settings['smtp_from_name'] ?? 'Tournament Admin',
        $to,
        $subject,
        $body
    );
}

function send_verification_email($email, $username, $token) {
    $base_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $verify_link = $base_url . "/verify_email.php?token=" . $token;
    
    $subject = "Verify Your Account - Tournament App";
    $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #0d6efd; text-align: center;'>Welcome to Tournament App!</h2>
            <p>Hi <strong>$username</strong>,</p>
            <p>Thank you for registering. Please click the button below to verify your email address and activate your account:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$verify_link' style='background-color: #0d6efd; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Verify Email Address</a>
            </div>
            <p>If the button doesn't work, copy and paste this link into your browser:</p>
            <p style='word-break: break-all; color: #666;'>$verify_link</p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='font-size: 12px; color: #888; text-align: center;'>If you did not create an account, no further action is required.</p>
        </div>
    ";
    
    return send_email($email, $subject, $body);
}
?>
