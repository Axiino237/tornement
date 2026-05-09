<?php
function log_audit($db, $user_id, $action, $details = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$user_id, $action, $details, $ip]);
    } catch (Exception $e) {
        // Fail silently or log to file
        error_log("Audit Log Failed: " . $e->getMessage());
        return false;
    }
}
?>
