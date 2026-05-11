<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check how many tournaments the user has successfully completed
$stmt = $db->prepare("SELECT COUNT(*) as completed_count FROM tournaments WHERE owner_id = ? AND status = 'completed'");
$stmt->execute([$_SESSION['user_id']]);
$completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['completed_count'];

// Check user status
$stmt = $db->prepare("SELECT is_unlocked, wallet_balance FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_status = $stmt->fetch(PDO::FETCH_ASSOC);
$is_unlocked = $user_status['is_unlocked'];

// Get active games
$stmt = $db->query("SELECT game_name FROM games WHERE status = 'active' ORDER BY game_name ASC");
$active_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }

    // Handle Unlock Action
    if (isset($_POST['action']) && $_POST['action'] === 'unlock_paid_feature') {
        if ($user_status['wallet_balance'] >= 20) {
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance - 20, is_unlocked = 1 WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                
                // Record in platform earnings
                $stmt = $db->prepare("INSERT INTO platform_earnings (amount, type, details) VALUES (20.00, 'unlock_fee', ?)");
                $stmt->execute(["User ID: " . $_SESSION['user_id'] . " unlocked paid features"]);
                
                log_audit($db, $_SESSION['user_id'], 'UNLOCK_PAID_FEATURE', "Paid ₹20 to unlock paid tournament hosting.");
                $db->commit();
                $success = "Paid features unlocked successfully! You can now host paid tournaments.";
                $is_unlocked = 1;
                $user_status['wallet_balance'] -= 20;
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Failed to unlock features.";
            }
        } else {
            $error = "Insufficient balance. You need ₹20 to unlock.";
        }
    }
    
    if (isset($_POST['tournament_name'])) {
        $tournament_name = trim($_POST['tournament_name']);
        $game_name = $_POST['game_name'];
        $tournament_date = $_POST['tournament_date'];
        $max_players = (int)$_POST['max_players'];
        $is_team_based = isset($_POST['is_team_based']) ? 1 : 0;
        $team_size = $is_team_based ? (int)$_POST['team_size'] : null;
        $max_teams = $is_team_based ? (int)$_POST['max_teams'] : null;
        $room_id = trim($_POST['room_id']);
        $room_password = trim($_POST['room_password']);
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
            if ($completed_count < 2 && !$is_admin && !$is_unlocked) {
                $error = "You must successfully conduct at least 2 free tournaments OR pay ₹20 to unlock paid match hosting.";
            } elseif (empty($registration_fee) || empty($winning_prize) || ($prize_style == 'top_3' && (empty($second_prize) || empty($third_prize))) || ($prize_style == 'per_kill' && empty($per_kill_prize))) {
                $error = "All prize fields are required based on the selected prize style.";
        } else {
            // Insert tournament
            $stmt = $db->prepare("INSERT INTO tournaments (owner_id, tournament_name, game_name, tournament_date, 
                max_players, is_team_based, team_size, max_teams, room_id, room_password, is_paid, 
                registration_fee, prize_style, winning_prize, second_prize, third_prize, per_kill_prize, contact_info, auto_approval) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$_SESSION['user_id'], $tournament_name, $game_name, $tournament_date, 
                $max_players, $is_team_based, $team_size, $max_teams, $room_id, $room_password, 
                $is_paid, $registration_fee, $prize_style, $winning_prize, $second_prize, $third_prize, $per_kill_prize, $contact_info, $auto_approval])) {
                $new_id = $db->lastInsertId();
                log_audit($db, $_SESSION['user_id'], 'CREATE_TOURNAMENT', "Created paid tournament: $tournament_name (ID: $new_id)");
                $success = "Tournament created successfully!";
            } else {
                $error = "Failed to create tournament. Please try again.";
            }
        }
    } else {
        // Insert tournament for free tournaments
        $stmt = $db->prepare("INSERT INTO tournaments (owner_id, tournament_name, game_name, tournament_date, 
            max_players, is_team_based, team_size, max_teams, room_id, room_password, is_paid, 
            registration_fee, prize_style, winning_prize, second_prize, third_prize, per_kill_prize, contact_info, auto_approval) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$_SESSION['user_id'], $tournament_name, $game_name, $tournament_date, 
            $max_players, $is_team_based, $team_size, $max_teams, $room_id, $room_password, 
            $is_paid, $registration_fee, $prize_style, $winning_prize, $second_prize, $third_prize, $per_kill_prize, $contact_info, $auto_approval])) {
            $new_id = $db->lastInsertId();
            log_audit($db, $_SESSION['user_id'], 'CREATE_TOURNAMENT', "Created free tournament: $tournament_name (ID: $new_id)");
            $success = "Tournament created successfully!";
        } else {
            $error = "Failed to create tournament. Please try again.";
        }
    }
}
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Create New Tournament</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if ($completed_count < 2 && !$is_unlocked && $_SESSION['role'] !== 'admin'): ?>
                    <div class="alert alert-info border-info bg-info bg-opacity-10 mb-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div class="mb-2 mb-md-0">
                                <h5 class="alert-heading h6 mb-1"><i class="fas fa-lock me-2"></i>Paid Tournaments Locked</h5>
                                <p class="mb-0 small">You need 2 completed free matches to host paid ones. <strong>OR</strong> unlock now.</p>
                            </div>
                            <form method="POST" action="">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="unlock_paid_feature">
                                <button type="submit" class="btn btn-primary btn-sm fw-bold px-3" onclick="return confirm('Pay ₹20 from your wallet to unlock paid tournaments?')">
                                    Unlock for ₹20
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label for="tournament_name" class="form-label">Tournament Name</label>
                        <input type="text" class="form-control" id="tournament_name" name="tournament_name" required>
                    </div>

                    <div class="mb-3">
                        <label for="game_name" class="form-label">Game Name</label>
                        <select class="form-select" id="game_name" name="game_name" required>
                            <option value="">Select Game</option>
                            <?php foreach ($active_games as $game): ?>
                                <option value="<?php echo htmlspecialchars($game['game_name']); ?>"><?php echo htmlspecialchars($game['game_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="tournament_date" class="form-label">Tournament Date</label>
                        <input type="datetime-local" class="form-control" id="tournament_date" name="tournament_date" required>
                    </div>

                    <div class="mb-3">
                        <label for="max_players" class="form-label">Maximum Players</label>
                        <input type="number" class="form-control" id="max_players" name="max_players" min="2" required>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_team_based" name="is_team_based">
                            <label class="form-check-label" for="is_team_based">Team-based Tournament</label>
                        </div>
                    </div>

                    <div id="team_fields" style="display: none;">
                        <div class="mb-3">
                            <label for="team_size" class="form-label">Team Size</label>
                            <input type="number" class="form-control" id="team_size" name="team_size" min="2">
                        </div>
                        <div class="mb-3">
                            <label for="max_teams" class="form-label">Maximum Teams</label>
                            <input type="number" class="form-control" id="max_teams" name="max_teams" min="2">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="room_id" class="form-label">Room ID (Optional)</label>
                        <input type="text" class="form-control" id="room_id" name="room_id">
                    </div>

                    <div class="mb-3">
                        <label for="room_password" class="form-label">Room Password (Optional)</label>
                        <input type="text" class="form-control" id="room_password" name="room_password">
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <?php $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'); ?>
                            <input class="form-check-input" type="checkbox" id="is_paid" name="is_paid" <?php echo ($completed_count < 2 && !$is_admin && !$is_unlocked) ? 'disabled' : ''; ?>>
                            <label class="form-check-label" for="is_paid">Paid Tournament</label>
                            <small class="text-muted d-block">(Requires 2 completed tournaments)</small>
                            <?php if ($completed_count < 2 && !$is_admin && !$is_unlocked): ?>
                                <div class="alert alert-warning mt-2">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    You have completed <?php echo $completed_count; ?> tournaments. You must successfully conduct at least 2 free tournaments before creating a paid match.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="paid_fields" style="display: none;">
                        <div class="mb-3">
                            <label for="registration_fee" class="form-label">Registration Fee (₹)</label>
                            <input type="number" class="form-control" id="registration_fee" name="registration_fee" min="0" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label for="prize_style" class="form-label">Prize Style</label>
                            <select class="form-select" id="prize_style" name="prize_style">
                                <option value="single">Single Winner (Booyah)</option>
                                <option value="top_3">Top 3 Players</option>
                                <option value="per_kill">Per Kill Prize</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="winning_prize" class="form-label" id="first_prize_label">Winning Prize (₹)</label>
                            <input type="number" class="form-control" id="winning_prize" name="winning_prize" min="0" step="0.01">
                        </div>
                        <div id="top_3_fields" style="display: none;">
                            <div class="mb-3">
                                <label for="second_prize" class="form-label">Second Prize (₹)</label>
                                <input type="number" class="form-control" id="second_prize" name="second_prize" min="0" step="0.01">
                            </div>
                            <div class="mb-3">
                                <label for="third_prize" class="form-label">Third Prize (₹)</label>
                                <input type="number" class="form-control" id="third_prize" name="third_prize" min="0" step="0.01">
                            </div>
                        </div>
                        <div id="per_kill_fields" style="display: none;">
                            <div class="mb-3">
                                <label for="per_kill_prize" class="form-label">Amount Per Kill (₹)</label>
                                <input type="number" class="form-control" id="per_kill_prize" name="per_kill_prize" min="0" step="0.01">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="contact_info" class="form-label">Contact Information</label>
                        <textarea class="form-control" id="contact_info" name="contact_info" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="auto_approval" name="auto_approval">
                            <label class="form-check-label" for="auto_approval">Auto-approve Participants</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Create Tournament</button>
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
const isUnlocked = <?php echo $is_unlocked ? 'true' : 'false'; ?>;

if (completedCount < 2 && !isAdmin && !isUnlocked) {
    isPaidCheckbox.addEventListener('click', function(e) {
        if (!this.disabled) {
            e.preventDefault();
            alert('You have completed ' + completedCount + ' tournaments. You must successfully conduct at least 2 free tournaments before creating a paid match.');
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?> 