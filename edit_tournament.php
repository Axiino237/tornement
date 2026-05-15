<?php
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/telegram.php';

// Check if user is logged in and is an admin
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

// Fetch tournament and participant count
$stmt = $db->prepare("SELECT t.*, 
    (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.tournament_id) as current_participants 
    FROM tournaments t WHERE t.tournament_id = ? AND t.owner_id = ?");
$stmt->execute([$_GET['id'], $_SESSION['user_id']]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    header("Location: index.php");
    exit();
}

if ($tournament['current_participants'] > 0) {
    echo "<div class='container mt-5'><div class='alert alert-danger'><h3><i class='fas fa-exclamation-circle me-2'></i>Cannot Edit Tournament</h3><p>This tournament already has participants. Editing is disabled to ensure fairness. Please contact support if you need to make critical changes.</p><a href='my_tournaments.php' class='btn btn-primary mt-3'>Back to My Tournaments</a></div></div>";
    require_once 'includes/footer.php';
    exit();
}

// Check how many tournaments the user has successfully completed
$stmt = $db->prepare("SELECT COUNT(*) as completed_count FROM tournaments WHERE owner_id = ? AND status = 'completed'");
$stmt->execute([$_SESSION['user_id']]);
$completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['completed_count'];

// Get active games
$stmt = $db->query("SELECT game_name FROM games WHERE status = 'active' ORDER BY game_name ASC");
$active_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }
    
    $tournament_name = trim($_POST['tournament_name']);
    $game_name = $_POST['game_name'];
    $tournament_date = $_POST['tournament_date'];
    $max_players = (int)$_POST['max_players'];
    $is_team_based = isset($_POST['is_team_based']) ? 1 : 0;
    $team_size = $is_team_based ? (int)$_POST['team_size'] : null;
    $max_teams = $is_team_based ? (int)$_POST['max_teams'] : null;
    $room_id = trim($_POST['room_id']);
    $room_password = trim($_POST['room_password']);
    
    // Validation for changing to paid
    // If it was already paid, they can edit it.
    // but if they are CHANGING it to paid, they need 2 completed tournaments.
    $is_paid = isset($_POST['is_paid']) ? 1 : 0;
    $registration_fee = $is_paid ? (float)$_POST['registration_fee'] : null;
    $prize_style = $is_paid ? $_POST['prize_style'] : 'single';
    $winning_prize = $is_paid ? (float)$_POST['winning_prize'] : null;
    $second_prize = ($is_paid && $prize_style == 'top_3') ? (float)$_POST['second_prize'] : null;
    $third_prize = ($is_paid && $prize_style == 'top_3') ? (float)$_POST['third_prize'] : null;
    $per_kill_prize = ($is_paid && $prize_style == 'per_kill') ? (float)$_POST['per_kill_prize'] : null;
    $contact_info = trim($_POST['contact_info']);
    $auto_approval = isset($_POST['auto_approval']) ? 1 : 0;

    // Validate input
    if (empty($tournament_name) || empty($game_name) || empty($tournament_date) || empty($max_players)) {
        $error = "Required fields cannot be empty";
    } elseif ($is_team_based && (empty($team_size) || empty($max_teams))) {
        $error = "Team size and max teams are required for team-based tournaments";
    } elseif ($is_paid) {
        $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
        if ($completed_count < 2 && !$tournament['is_paid'] && !$is_admin) {
            $error = "You must successfully conduct at least 2 free tournaments before creating a paid match.";
        } elseif (empty($registration_fee) || empty($winning_prize) || ($prize_style == 'top_3' && (empty($second_prize) || empty($third_prize))) || ($prize_style == 'per_kill' && empty($per_kill_prize))) {
            $error = "All prize fields are required based on the selected prize style.";
        } else {
            // Update tournament
            $stmt = $db->prepare("UPDATE tournaments SET tournament_name=?, game_name=?, tournament_date=?, 
                max_players=?, is_team_based=?, team_size=?, max_teams=?, room_id=?, room_password=?, is_paid=?, 
                registration_fee=?, prize_style=?, winning_prize=?, second_prize=?, third_prize=?, per_kill_prize=?, contact_info=?, auto_approval=? 
                WHERE tournament_id=? AND owner_id=?");
            
            if ($stmt->execute([$tournament_name, $game_name, $tournament_date, 
                $max_players, $is_team_based, $team_size, $max_teams, $room_id, $room_password, 
                $is_paid, $registration_fee, $prize_style, $winning_prize, $second_prize, $third_prize, $per_kill_prize, $contact_info, $auto_approval, $_GET['id'], $_SESSION['user_id']])) {
                $success = "Tournament updated successfully!";
                
                // Refresh tournament data properly
                $stmt_ref = $db->prepare("SELECT * FROM tournaments WHERE tournament_id = ?");
                $stmt_ref->execute([$_GET['id']]);
                $tournament = $stmt_ref->fetch(PDO::FETCH_ASSOC);

                // Send Telegram Notification
                $msg = "<b>✏️ Tournament Updated!</b>\n\n";
                $msg .= "<b>Name:</b> $tournament_name\n";
                $msg .= "<b>Game:</b> $game_name\n";
                $msg .= "<b>Date:</b> " . date('M d, Y H:i', strtotime($tournament_date)) . "\n";
                $msg .= "<b>Players:</b> $max_players\n";
                if ($is_team_based) {
                    $msg .= "<b>Team:</b> Yes ($team_size players/team, $max_teams teams)\n";
                }
                $msg .= "<b>Fee:</b> ₹" . number_format($registration_fee, 2) . "\n";
                $msg .= "<b>Prize Style:</b> $prize_style\n";
                $msg .= "<b>Winner Prize:</b> ₹" . number_format($winning_prize, 2) . "\n";
                if ($prize_style == 'top_3') {
                    $msg .= "<b>2nd:</b> ₹$second_prize | <b>3rd:</b> ₹$third_prize\n";
                } elseif ($prize_style == 'per_kill') {
                    $msg .= "<b>Per Kill:</b> ₹$per_kill_prize\n";
                }
                if (!empty($room_id)) {
                    $msg .= "<b>Room ID:</b> $room_id\n";
                    $msg .= "<b>Pass:</b> $room_password\n";
                }
                $msg .= "<b>Auto Approval:</b> " . ($auto_approval ? "Yes" : "No") . "\n";
                $msg .= "<b>Contact:</b> $contact_info\n";
                $msg .= "\n<a href='https://tornement.onrender.com/tournament_details.php?id=" . $_GET['id'] . "'>View Changes</a>";
                sendTelegramNotification($msg);
            } else {
                $error = "Failed to update tournament. Please try again.";
            }
        }
    } else {
        // Update free tournament
        $stmt = $db->prepare("UPDATE tournaments SET tournament_name=?, game_name=?, tournament_date=?, 
            max_players=?, is_team_based=?, team_size=?, max_teams=?, room_id=?, room_password=?, is_paid=?, 
            registration_fee=?, prize_style=?, winning_prize=?, second_prize=?, third_prize=?, per_kill_prize=?, contact_info=?, auto_approval=? 
            WHERE tournament_id=? AND owner_id=?");
        
        if ($stmt->execute([$tournament_name, $game_name, $tournament_date, 
            $max_players, $is_team_based, $team_size, $max_teams, $room_id, $room_password, 
            $is_paid, $registration_fee, $prize_style, $winning_prize, $second_prize, $third_prize, $per_kill_prize, $contact_info, $auto_approval, $_GET['id'], $_SESSION['user_id']])) {
            $success = "Tournament updated successfully!";
            
            // Refresh tournament data properly
            $stmt_ref = $db->prepare("SELECT * FROM tournaments WHERE tournament_id = ?");
            $stmt_ref->execute([$_GET['id']]);
            $tournament = $stmt_ref->fetch(PDO::FETCH_ASSOC);

            // Send Telegram Notification
            $msg = "<b>✏️ Tournament Updated (Free)!</b>\n\n";
            $msg .= "<b>Name:</b> $tournament_name\n";
            $msg .= "<b>Game:</b> $game_name\n";
            $msg .= "<b>Date:</b> " . date('M d, Y H:i', strtotime($tournament_date)) . "\n";
            $msg .= "<b>Players:</b> $max_players\n";
            if ($is_team_based) {
                $msg .= "<b>Team:</b> Yes ($team_size players/team, $max_teams teams)\n";
            }
            if (!empty($room_id)) {
                $msg .= "<b>Room ID:</b> $room_id\n";
                $msg .= "<b>Pass:</b> $room_password\n";
            }
            $msg .= "<b>Auto Approval:</b> " . ($auto_approval ? "Yes" : "No") . "\n";
            $msg .= "<b>Contact:</b> $contact_info\n";
            $msg .= "\n<a href='https://tornement.onrender.com/tournament_details.php?id=" . $_GET['id'] . "'>View Changes</a>";
            sendTelegramNotification($msg);
        } else {
            $error = "Failed to update tournament. Please try again.";
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Edit Tournament</h4>
                <a href="my_tournaments.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label for="tournament_name" class="form-label">Tournament Name</label>
                        <input type="text" class="form-control" id="tournament_name" name="tournament_name" value="<?php echo htmlspecialchars($tournament['tournament_name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="game_name" class="form-label">Game Name</label>
                        <select class="form-select" id="game_name" name="game_name" required>
                            <option value="">Select Game</option>
                            <?php foreach ($active_games as $game): ?>
                                <option value="<?php echo htmlspecialchars($game['game_name']); ?>" <?php echo $game['game_name'] == $tournament['game_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($game['game_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="tournament_date" class="form-label">Tournament Date</label>
                        <input type="datetime-local" class="form-control" id="tournament_date" name="tournament_date" value="<?php echo date('Y-m-d\TH:i', strtotime($tournament['tournament_date'])); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="max_players" class="form-label">Maximum Players</label>
                        <input type="number" class="form-control" id="max_players" name="max_players" min="2" value="<?php echo htmlspecialchars($tournament['max_players']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_team_based" name="is_team_based" <?php echo $tournament['is_team_based'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_team_based">Team-based Tournament</label>
                        </div>
                    </div>

                    <div id="team_fields" style="display: <?php echo $tournament['is_team_based'] ? 'block' : 'none'; ?>;">
                        <div class="mb-3">
                            <label for="team_size" class="form-label">Team Size</label>
                            <input type="number" class="form-control" id="team_size" name="team_size" min="2" value="<?php echo htmlspecialchars($tournament['team_size']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="max_teams" class="form-label">Maximum Teams</label>
                            <input type="number" class="form-control" id="max_teams" name="max_teams" min="2" value="<?php echo htmlspecialchars($tournament['max_teams']); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="room_id" class="form-label">Room ID (Optional)</label>
                        <input type="text" class="form-control" id="room_id" name="room_id" value="<?php echo htmlspecialchars($tournament['room_id']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="room_password" class="form-label">Room Password (Optional)</label>
                        <input type="text" class="form-control" id="room_password" name="room_password" value="<?php echo htmlspecialchars($tournament['room_password']); ?>">
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <?php 
                            $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
                            $can_make_paid = ($completed_count >= 2 || $tournament['is_paid'] || $is_admin); 
                            ?>
                            <input class="form-check-input" type="checkbox" id="is_paid" name="is_paid" <?php echo $tournament['is_paid'] ? 'checked' : ''; ?> <?php echo !$can_make_paid ? 'disabled' : ''; ?>>
                            <label class="form-check-label" for="is_paid">Paid Tournament</label>
                            <small class="text-muted d-block">(Requires 2 completed tournaments to enable)</small>
                            <?php if (!$can_make_paid): ?>
                                <div class="alert alert-warning mt-2">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    You have completed <?php echo $completed_count; ?> tournaments. You must successfully conduct at least 2 free tournaments before creating a paid match.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="paid_fields" style="display: <?php echo $tournament['is_paid'] ? 'block' : 'none'; ?>;">
                        <div class="mb-3">
                            <label for="registration_fee" class="form-label">Registration Fee (₹)</label>
                            <input type="number" class="form-control" id="registration_fee" name="registration_fee" min="0" step="0.01" value="<?php echo htmlspecialchars($tournament['registration_fee']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="prize_style" class="form-label">Prize Style</label>
                            <select class="form-select" id="prize_style" name="prize_style">
                                <option value="single" <?php echo $tournament['prize_style'] == 'single' ? 'selected' : ''; ?>>Single Winner (Booyah)</option>
                                <option value="top_3" <?php echo $tournament['prize_style'] == 'top_3' ? 'selected' : ''; ?>>Top 3 Players</option>
                                <option value="per_kill" <?php echo $tournament['prize_style'] == 'per_kill' ? 'selected' : ''; ?>>Per Kill Prize</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="winning_prize" class="form-label" id="first_prize_label">
                                <?php echo $tournament['prize_style'] == 'top_3' ? 'First Prize (₹)' : ($tournament['prize_style'] == 'per_kill' ? 'Booyah Bonus (₹)' : 'Winning Prize (₹)'); ?>
                            </label>
                            <input type="number" class="form-control" id="winning_prize" name="winning_prize" min="0" step="0.01" value="<?php echo htmlspecialchars($tournament['winning_prize']); ?>">
                        </div>
                        <div id="top_3_fields" style="display: <?php echo $tournament['prize_style'] == 'top_3' ? 'block' : 'none'; ?>;">
                            <div class="mb-3">
                                <label for="second_prize" class="form-label">Second Prize (₹)</label>
                                <input type="number" class="form-control" id="second_prize" name="second_prize" min="0" step="0.01" value="<?php echo htmlspecialchars($tournament['second_prize']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="third_prize" class="form-label">Third Prize (₹)</label>
                                <input type="number" class="form-control" id="third_prize" name="third_prize" min="0" step="0.01" value="<?php echo htmlspecialchars($tournament['third_prize']); ?>">
                            </div>
                        </div>
                        <div id="per_kill_fields" style="display: <?php echo $tournament['prize_style'] == 'per_kill' ? 'block' : 'none'; ?>;">
                            <div class="mb-3">
                                <label for="per_kill_prize" class="form-label">Amount Per Kill (₹)</label>
                                <input type="number" class="form-control" id="per_kill_prize" name="per_kill_prize" min="0" step="0.01" value="<?php echo htmlspecialchars($tournament['per_kill_prize']); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="contact_info" class="form-label">Contact Information</label>
                        <textarea class="form-control" id="contact_info" name="contact_info" rows="3" required><?php echo htmlspecialchars($tournament['contact_info']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="auto_approval" name="auto_approval" <?php echo $tournament['auto_approval'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="auto_approval">Auto-approve Participants</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('is_team_based').addEventListener('change', function() {
    document.getElementById('team_fields').style.display = this.checked ? 'block' : 'none';
});

const completedCount = <?php echo $completed_count; ?>;
const isOriginallyPaid = <?php echo $tournament['is_paid'] ? 'true' : 'false'; ?>;
const isPaidCheckbox = document.getElementById('is_paid');

isPaidCheckbox.addEventListener('change', function() {
    document.getElementById('paid_fields').style.display = this.checked ? 'block' : 'none';
});

const prizeStyleSelect = document.getElementById('prize_style');
const top3Fields = document.getElementById('top_3_fields');
const perKillFields = document.getElementById('per_kill_fields');
const firstPrizeLabel = document.getElementById('first_prize_label');

prizeStyleSelect.addEventListener('change', function() {
    top3Fields.style.display = 'none';
    perKillFields.style.display = 'none';
    firstPrizeLabel.textContent = 'Winning Prize (₹)';
    
    if (this.value === 'top_3') {
        top3Fields.style.display = 'block';
        firstPrizeLabel.textContent = 'First Prize (₹)';
    } else if (this.value === 'per_kill') {
        perKillFields.style.display = 'block';
        firstPrizeLabel.textContent = 'Booyah/Winner Bonus (₹) - Optional';
    }
});

const isAdmin = <?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'true' : 'false'; ?>;

if (completedCount < 2 && !isOriginallyPaid && !isAdmin) {
    isPaidCheckbox.addEventListener('click', function(e) {
        if (!this.disabled) {
            e.preventDefault();
            alert('You have completed ' + completedCount + ' tournaments. You must successfully conduct at least 2 free tournaments before creating a paid match.');
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
