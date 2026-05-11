<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();
$stmt = $db->query("SELECT * FROM tournaments");
$tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($tournaments, JSON_PRETTY_PRINT);
?>
