<?php
require_once 'config/database.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get tournament details
$stmt = $db->prepare("SELECT t.*, u.username as owner_name, 
                    (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.tournament_id) as current_participants
                    FROM tournaments t 
                    JOIN users u ON t.owner_id = u.user_id 
                    WHERE t.tournament_id = ?");
$stmt->execute([$_GET['id']]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    header("Location: index.php");
    exit();
}

// Auto-Refund & Suspension Logic
// If match started 10+ mins ago and still no Room ID, cancel and refund
if ($tournament['status'] == 'active' && empty($tournament['room_id'])) {
    $match_time = strtotime($tournament['tournament_date']);
    if (time() > ($match_time + 600)) { // 10 minutes grace period
        try {
            $db->beginTransaction();
            
            // Refund all participants
            if ($tournament['is_paid']) {
                $stmt = $db->prepare("SELECT user_id FROM tournament_participants WHERE tournament_id = ?");
                $stmt->execute([$tournament['tournament_id']]);
                $participants_to_refund = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($participants_to_refund as $p) {
                    $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?");
                    $stmt->execute([$tournament['registration_fee'], $p['user_id']]);
                }
            }
            
            // Mark tournament as cancelled
            $stmt = $db->prepare("UPDATE tournaments SET status = 'cancelled' WHERE tournament_id = ?");
            $stmt->execute([$tournament['tournament_id']]);
            
            log_audit($db, 0, 'AUTO_REFUND', "Tournament ID: " . $tournament['tournament_id'] . " cancelled & refunded due to missing room info after start time.");
            
            $db->commit();
            // Refresh to show cancelled status
            header("Location: tournament_details.php?id=" . $tournament['tournament_id'] . "&error=Tournament cancelled automatically due to missing room details.");
            exit();
        } catch (Exception $e) {
            $db->rollBack();
        }
    }
}

// Fetch game images for banners
$stmt = $db->query("SELECT game_name, image_url FROM games");
$db_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
$game_images = [];
foreach ($db_games as $g) {
    $game_images[strtolower(trim($g['game_name']))] = $g['image_url'];
}

require_once 'includes/header.php';

$error = '';
$success = '';

// Handle join request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }
    
    $team_name = isset($_POST['team_name']) ? trim($_POST['team_name']) : null;

    // Get user's current wallet balance
    $stmt = $db->prepare("SELECT wallet_balance FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_wallet = $stmt->fetch(PDO::FETCH_ASSOC)['wallet_balance'];

    // Validate paid tournament requirements
    if ($tournament['is_paid'] && $user_wallet < $tournament['registration_fee']) {
        $error = "Insufficient wallet balance. Please recharge your wallet.";
    }

    // Validate team-based requirements
    if ($tournament['is_team_based'] && empty($team_name)) {
        $error = "Team name is required for team-based tournaments";
    }

    // Check if already joined
    if (empty($error)) {
        $stmt = $db->prepare("SELECT participant_id FROM tournament_participants 
                            WHERE tournament_id = ? AND user_id = ?");
        $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $error = "You have already joined this tournament";
        } else {
            // Check if tournament is full
            if ($tournament['current_participants'] >= $tournament['max_players']) {
                $error = "This tournament is full";
            } else {
                try {
                    $db->beginTransaction();

                    if ($tournament['is_paid']) {
                        // Deduct registration fee
                        $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE user_id = ?");
                        $stmt->execute([$tournament['registration_fee'], $_SESSION['user_id']]);
                    }

                    // Insert participant
                    $stmt = $db->prepare("INSERT INTO tournament_participants 
                                        (tournament_id, user_id, team_name, is_approved) 
                                        VALUES (?, ?, ?, ?)");
                    
                    // If it's a paid tournament handled by the platform, or auto-approval is on, approve it.
                    $is_approved = ($tournament['is_paid'] || $tournament['auto_approval']) ? 1 : 0;
                    
                    if ($stmt->execute([$_GET['id'], $_SESSION['user_id'], $team_name, $is_approved])) {
                        log_audit($db, $_SESSION['user_id'], 'JOIN_TOURNAMENT', "Joined tournament ID: " . $_GET['id'] . ($tournament['is_paid'] ? " (Fee: ₹" . $tournament['registration_fee'] . ")" : " (Free)"));
                        $db->commit();
                        $success = "Successfully joined the tournament!";
                        header("Location: tournament_details.php?id=" . $_GET['id']);
                        exit();
                    } else {
                        throw new Exception("Execution failed");
                    }
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Failed to join tournament. Please try again.";
                }
            }
        }
    }
}

// Get participants
$stmt = $db->prepare("SELECT tp.*, u.username, 
                    CASE 
                        WHEN tp.is_approved = TRUE THEN 'Approved'
                        ELSE 'Pending'
                    END as status
                    FROM tournament_participants tp 
                    JOIN users u ON tp.user_id = u.user_id 
                    WHERE tp.tournament_id = ?
                    ORDER BY tp.is_approved DESC, tp.joined_at ASC");
$stmt->execute([$_GET['id']]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if user has joined the tournament
$has_joined = false;
$is_approved = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT tp.is_approved 
                        FROM tournament_participants tp
                        WHERE tp.tournament_id = ? AND tp.user_id = ?");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $participant = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($participant) {
        $has_joined = true;
        $is_approved = ($participant['is_approved'] == 1);
    }
}
?>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0"><?php echo htmlspecialchars($tournament['tournament_name']); ?></h4>
            </div>
            <div class="card-body">
                <?php 
                $game_name_key = strtolower(trim($tournament['game_name']));
                $game_image_path = isset($game_images[$game_name_key]) ? $game_images[$game_name_key] : 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=800&auto=format&fit=crop';
                ?>
                <div class="mb-4 text-center">
                    <img src="<?php echo $game_image_path; ?>" alt="<?php echo htmlspecialchars($tournament['game_name']); ?> Banner" class="img-fluid rounded shadow-sm" style="max-height: 400px; width: 100%; object-fit: cover;" onerror="this.src='https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=800&auto=format&fit=crop'">
                </div>
                <div class="tournament-info mb-4">
                    <p><i class="fas fa-gamepad me-2"></i>Game: <?php echo htmlspecialchars($tournament['game_name']); ?></p>
                    <p><i class="fas fa-calendar me-2"></i>Date: <?php echo date('M d, Y H:i', strtotime($tournament['tournament_date'])); ?></p>
                    <p><i class="fas fa-users me-2"></i>Players: <?php echo $tournament['current_participants']; ?>/<?php echo $tournament['max_players']; ?></p>
                    <?php if ($tournament['is_team_based']): ?>
                        <p><i class="fas fa-users-cog me-2"></i>Team Size: <?php echo $tournament['team_size']; ?></p>
                        <p><i class="fas fa-layer-group me-2"></i>Max Teams: <?php echo $tournament['max_teams']; ?></p>
                    <?php endif; ?>
                    <?php if ($tournament['is_paid']): ?>
                        <p><i class="fas fa-money-bill-wave me-2"></i>Registration Fee: ₹<?php echo number_format($tournament['registration_fee'], 2); ?></p>
                        <div class="prize-pool bg-dark bg-opacity-50 p-3 rounded border border-secondary mb-3">
                            <h6 class="text-warning mb-2"><i class="fas fa-trophy me-2"></i>Prize Pool Style: <?php echo ucfirst(str_replace('_', ' ', $tournament['prize_style'])); ?></h6>
                            <p class="mb-1">1st Prize: <span class="text-success fw-bold">₹<?php echo number_format($tournament['winning_prize'], 2); ?></span></p>
                            <?php if ($tournament['prize_style'] == 'top_3'): ?>
                                <p class="mb-1">2nd Prize: <span class="text-success">₹<?php echo number_format($tournament['second_prize'], 2); ?></span></p>
                                <p class="mb-1">3rd Prize: <span class="text-success">₹<?php echo number_format($tournament['third_prize'], 2); ?></span></p>
                            <?php elseif ($tournament['prize_style'] == 'per_kill'): ?>
                                <p class="mb-1">Per Kill: <span class="text-success">₹<?php echo number_format($tournament['per_kill_prize'], 2); ?></span></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <p><i class="fas fa-user me-2"></i>Organizer: <?php echo htmlspecialchars($tournament['owner_name']); ?></p>
                </div>

                <div class="contact-info mb-4">
                    <h5>Contact Information</h5>
                    <p><?php echo nl2br(htmlspecialchars($tournament['contact_info'])); ?></p>
                </div>

                <?php if ($tournament['is_paid']): ?>
                    <div class="alert alert-info border-info">
                        <i class="fas fa-wallet me-2"></i> Registration fee of <strong>₹<?php echo number_format($tournament['registration_fee'], 2); ?></strong> will be deducted from your wallet upon joining.
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $tournament['owner_id']): ?>
                    <?php if ($has_joined): ?>
                        <?php if ($is_approved): ?>
                            <?php 
                            $match_time = strtotime($tournament['tournament_date']);
                            $current_time = time();
                            $show_room_details = ($current_time >= ($match_time - 300)); // 300 seconds = 5 minutes
                            $is_owner = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $tournament['owner_id']);
                            ?>

                            <div class="room-info mb-4">
                                <h5><i class="fas fa-door-open me-2 text-info"></i>Room Details</h5>
                                <?php if ($show_room_details || $is_owner): ?>
                                    <div class="alert alert-success border-success bg-success bg-opacity-10">
                                        <div class="mb-2 d-flex justify-content-between align-items-center">
                                            <span><strong>Room ID:</strong> <span id="roomIdText"><?php echo $tournament['room_id'] ? htmlspecialchars($tournament['room_id']) : '<span class="text-muted">Not provided</span>'; ?></span></span>
                                            <?php if ($tournament['room_id']): ?>
                                                <button class="btn btn-sm btn-outline-success border-0" onclick="copyToClipboard('roomIdText', this)">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span><strong>Room Password:</strong> <span id="roomPassText"><?php echo $tournament['room_password'] ? htmlspecialchars($tournament['room_password']) : '<span class="text-muted">Not provided</span>'; ?></span></span>
                                            <?php if ($tournament['room_password']): ?>
                                                <button class="btn btn-sm btn-outline-success border-0" onclick="copyToClipboard('roomPassText', this)">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <script>
                                    function copyToClipboard(elementId, btn) {
                                        const text = document.getElementById(elementId).innerText;
                                        navigator.clipboard.writeText(text).then(() => {
                                            const icon = btn.querySelector('i');
                                            icon.classList.replace('fa-copy', 'fa-check');
                                            setTimeout(() => icon.classList.replace('fa-check', 'fa-copy'), 2000);
                                        });
                                    }
                                    </script>
                                <?php else: ?>
                                    <div class="alert alert-info border-info bg-info bg-opacity-10">
                                        <i class="fas fa-clock me-2"></i> Room details will be revealed automatically <strong>5 minutes</strong> before the match starts (<?php echo date('H:i', $match_time - 300); ?>).
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-user-clock me-2"></i> Your participation is pending approval. Room details will be visible once approved.
                            </div>
                        <?php endif; ?>
                    <?php elseif ($tournament['status'] != 'active'): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-ban me-2"></i> This tournament has been <?php echo $tournament['status']; ?>.
                        </div>
                    <?php elseif ($tournament['current_participants'] < $tournament['max_players']): ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <?php echo csrf_field(); ?>
                            <?php if ($tournament['is_team_based']): ?>
                                <div class="mb-3">
                                    <label for="team_name" class="form-label">Team Name</label>
                                    <input type="text" class="form-control" id="team_name" name="team_name" required>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($tournament['is_paid']): ?>
                                <p class="text-muted small mb-3">By joining, you agree to the deduction of ₹<?php echo number_format($tournament['registration_fee'], 2); ?> from your wallet.</p>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-primary">Join Tournament</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning">This tournament is full.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Participants</h4>
            </div>
            <div class="card-body">
                <?php if (count($participants) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-dark">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <?php if ($tournament['is_team_based']): ?>
                                        <th>Team Name</th>
                                    <?php endif; ?>
                                    <th>Status</th>
                                    <th>Joined At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $participant): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($participant['username']); ?></td>
                                        <?php if ($tournament['is_team_based']): ?>
                                            <td><?php echo htmlspecialchars($participant['team_name']); ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="badge bg-<?php echo $participant['status'] == 'Approved' ? 'success' : 'warning'; ?>">
                                                <?php echo $participant['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($participant['joined_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No participants have joined yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $tournament['owner_id']): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0">Tournament Management</h4>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if (empty($tournament['room_id']) || empty($tournament['room_password'])): ?>
                            <div class="bg-warning bg-opacity-10 p-3 rounded border border-warning mb-3">
                                <h6 class="text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Missing Room Details</h6>
                                <form method="POST" action="update_room_info.php">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="tournament_id" value="<?php echo $tournament['tournament_id']; ?>">
                                    <div class="mb-2">
                                        <input type="text" name="room_id" class="form-control form-control-sm" placeholder="Room ID" required>
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="room_password" class="form-control form-control-sm" placeholder="Room Password" required>
                                    </div>
                                    <button type="submit" class="btn btn-warning btn-sm w-100">Update Room Info</button>
                                </form>
                                <small class="text-muted d-block mt-2">Update before match start to avoid auto-refund.</small>
                            </div>
                        <?php endif; ?>

                        <?php if ($tournament['current_participants'] == 0): ?>
                            <a href="edit_tournament.php?id=<?php echo $tournament['tournament_id']; ?>" 
                               class="btn btn-info">Edit Tournament</a>
                        <?php else: ?>
                            <button class="btn btn-info" disabled title="Cannot edit after players join">Edit Tournament</button>
                        <?php endif; ?>
                        <a href="manage_participants.php?id=<?php echo $tournament['tournament_id']; ?>" 
                           class="btn btn-primary">Manage Participants</a>
                        <a href="announce_winners.php?id=<?php echo $tournament['tournament_id']; ?>" 
                           class="btn btn-success">Announce Winners</a>
                        <a href="end_tournament.php?id=<?php echo $tournament['tournament_id']; ?>" 
                           class="btn btn-danger">End Tournament</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $tournament['owner_id']): ?>
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Report Tournament</h4>
                </div>
                <div class="card-body">
                    <form action="report_tournament.php" method="POST">
                        <input type="hidden" name="tournament_id" value="<?php echo $tournament['tournament_id']; ?>">
                        <div class="mb-3">
                            <label for="report_reason" class="form-label">Reason for Report</label>
                            <textarea class="form-control" id="report_reason" name="report_reason" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger">Submit Report</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 