<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

echo "<h3>Server Time: " . date('Y-m-d H:i:s') . "</h3>";

$stmt = $db->query("SELECT tournament_id, tournament_name, tournament_date, room_id, status FROM tournaments ORDER BY created_at DESC LIMIT 5");
$tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($tournaments);
echo "</pre>";

echo "<h3>Query Test:</h3>";
$query = "SELECT tournament_id FROM tournaments 
          WHERE status = 'active' 
          AND (
              tournament_date > NOW() 
              OR (room_id IS NOT NULL AND room_id != '') 
              OR tournament_date >= NOW() - INTERVAL '12 hours'
          )";
$stmt = $db->query($query);
echo "Active & Visible count: " . $stmt->rowCount();
?>
