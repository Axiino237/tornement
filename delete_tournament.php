<?php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Verify ownership
$stmt = $db->prepare("SELECT tournament_id, owner_id, tournament_name FROM tournaments WHERE tournament_id = ? AND owner_id = ?");
$stmt->execute([$_GET['id'], $_SESSION['user_id']]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    header("Location: my_tournaments.php?error=Unauthorized or tournament not found.");
    exit();
}

try {
    $db->beginTransaction();
    
    $tournament_id = $_GET['id'];
    
    // Delete participants
    $stmt = $db->prepare("DELETE FROM tournament_participants WHERE tournament_id = ?");
    $stmt->execute([$tournament_id]);
    
    // Delete reports
    $stmt = $db->prepare("DELETE FROM tournament_reports WHERE tournament_id = ?");
    $stmt->execute([$tournament_id]);
    
    // Delete winners
    $stmt = $db->prepare("DELETE FROM tournament_winners WHERE tournament_id = ?");
    $stmt->execute([$tournament_id]);
    
    // Delete tournament
    $stmt = $db->prepare("DELETE FROM tournaments WHERE tournament_id = ?");
    if ($stmt->execute([$tournament_id])) {
        require_once 'includes/logger.php';
        log_audit($db, $_SESSION['user_id'], 'DELETE_TOURNAMENT', "Deleted tournament: " . $tournament['tournament_name'] . " (ID: $tournament_id)");
        
        $db->commit();
        header("Location: my_tournaments.php?success=Tournament deleted successfully.");
        exit();
    } else {
        throw new Exception("Deletion failed.");
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    header("Location: my_tournaments.php?error=Failed to delete tournament: " . $e->getMessage());
    exit();
}
?>
