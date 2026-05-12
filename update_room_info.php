<?php
require_once 'config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: index.php");
        exit();
    }
    require_once 'includes/csrf.php';
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }

    $database = new Database();
    $db = $database->getConnection();

    $tournament_id = $_POST['tournament_id'];
    $room_id = trim($_POST['room_id']);
    $room_password = trim($_POST['room_password']);

    // Verify ownership
    $stmt = $db->prepare("SELECT owner_id FROM tournaments WHERE tournament_id = ?");
    $stmt->execute([$tournament_id]);
    $tournament = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tournament && $tournament['owner_id'] == $_SESSION['user_id']) {
        $stmt = $db->prepare("UPDATE tournaments SET room_id = ?, room_password = ? WHERE tournament_id = ?");
        if ($stmt->execute([$room_id, $room_password, $tournament_id])) {
            log_audit($db, $_SESSION['user_id'], 'UPDATE_ROOM_INFO', "Updated Room ID/Pass for tournament ID: $tournament_id");
            header("Location: tournament_details.php?id=$tournament_id&success=Room details updated!");
            exit();
        }
    }
}
header("Location: index.php");
exit();
?>
