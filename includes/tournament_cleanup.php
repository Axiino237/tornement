<?php
/**
 * Clean up stale tournaments and refund participants if room info is missing.
 */
function cleanupStaleTournaments($db) {
    try {
        // Find tournaments that have passed their start time but have no room info
        // and are still marked as 'active'
        $stmt = $db->query("SELECT tournament_id, tournament_name, registration_fee, is_paid 
                            FROM tournaments 
                            WHERE status = 'active' 
                            AND tournament_date < NOW() 
                            AND (room_id IS NULL OR room_id = '')");
        $stale_tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($stale_tournaments)) return;

        require_once 'includes/telegram.php';

        foreach ($stale_tournaments as $t) {
            $db->beginTransaction();
            
            $tid = $t['tournament_id'];
            $fee = (float)$t['registration_fee'];
            $name = $t['tournament_name'];

            // 1. Get participants to refund
            $stmt_p = $db->prepare("SELECT user_id FROM tournament_participants WHERE tournament_id = ?");
            $stmt_p->execute([$tid]);
            $participants = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

            if ($t['is_paid'] && $fee > 0) {
                foreach ($participants as $p) {
                    $uid = $p['user_id'];
                    
                    // Refund to wallet
                    $stmt_refund = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?");
                    $stmt_refund->execute([$fee, $uid]);
                    
                    // Log the refund
                    $stmt_log = $db->prepare("INSERT INTO wallet_logs (user_id, amount, type, details) VALUES (?, ?, 'refund', ?)");
                    $stmt_log->execute([$uid, $fee, "Auto-refund for tournament: $name (ID: $tid) - No Room Info"]);
                }
            }

            // 2. Mark tournament as canceled
            $stmt_cancel = $db->prepare("UPDATE tournaments SET status = 'canceled' WHERE tournament_id = ?");
            $stmt_cancel->execute([$tid]);

            // 3. Log Audit
            if (function_exists('log_audit')) {
                log_audit($db, 0, 'AUTO_CLEANUP', "Canceled tournament $name (ID: $tid) and refunded " . count($participants) . " users due to missing room info.");
            }

            $db->commit();

            // 4. Send Telegram Notification
            $msg = "<b>⚠️ Tournament Auto-Canceled!</b>\n\n";
            $msg .= "<b>Name:</b> $name\n";
            $msg .= "<b>Reason:</b> No Room ID/Password provided after start time.\n";
            $msg .= "<b>Action:</b> " . count($participants) . " users refunded automatically.";
            sendTelegramNotification($msg);
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("Tournament Cleanup Error: " . $e->getMessage());
    }
}
?>
