<?php
session_start();
require_once 'config/database.php';
if (isset($_SESSION['user_id'])) {
    $database = new Database();
    $db = $database->getConnection();
    log_audit($db, $_SESSION['user_id'], 'LOGOUT', "User logged out");
}
session_destroy();
header("Location: login.php");
exit();
?> 