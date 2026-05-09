<?php
require_once 'config/database.php';
require_once 'includes/header.php';

if (!isset($_GET['id']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Verify tournament ownership and get details
$stmt = $db->prepare("SELECT t.*, 
                    (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.tournament_id) as current_participants 
                    FROM tournaments t 
                    WHERE t.tournament_id = ? AND t.owner_id = ?");
$stmt->execute([$_GET['id'], $_SESSION['user_id']]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament || $tournament['status'] == 'completed') {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

// Get participants
$stmt = $db->prepare("SELECT tp.*, u.username 
                    FROM tournament_participants tp 
                    JOIN users u ON tp.user_id = u.user_id 
                    WHERE tp.tournament_id = ? AND (tp.is_approved = 1 OR tp.status = 'approved')");
$stmt->execute([$_GET['id']]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get announced winners
$stmt = $db->prepare("SELECT tw.*, u.username 
                    FROM tournament_winners tw 
                    JOIN users u ON tw.user_id = u.user_id 
                    WHERE tw.tournament_id = ? 
                    ORDER BY tw.position ASC");
$stmt->execute([$_GET['id']]);
$winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle tournament completion and prize distribution
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalize_tournament'])) {
    try {
        $db->beginTransaction();

        $total_prize_given = 0;
        $platform_fee_percent = 0.05;
        $total_collection = $tournament['current_participants'] * $tournament['registration_fee'];
        $platform_fee = $total_collection * $platform_fee_percent;

        if ($tournament['prize_style'] == 'per_kill') {
            $kills_data = $_POST['kills'] ?? [];
            foreach ($participants as $p) {
                $p_id = $p['user_id'];
                $p_kills = isset($kills_data[$p_id]) ? (int)$kills_data[$p_id] : 0;
                $p_prize = $p_kills * $tournament['per_kill_prize'];
                
                // Check if this player is also a Booyah winner (1st place)
                $is_booyah = false;
                foreach ($winners as $w) {
                    if ($w['user_id'] == $p_id && $w['position'] == 1) {
                        $p_prize += $tournament['winning_prize'];
                        $is_booyah = true;
                        break;
                    }
                }

                if ($p_prize > 0) {
                    $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?");
                    $stmt->execute([$p_prize, $p_id]);
                    $total_prize_given += $p_prize;
                }

                // Update participant stats
                $stmt = $db->prepare("UPDATE tournament_participants SET kills = ?, prize_awarded = ? WHERE tournament_id = ? AND user_id = ?");
                $stmt->execute([$p_kills, $p_prize, $_GET['id'], $p_id]);
            }
        } else {
            // Single or Top 3 style
            if (count($winners) == 0) {
                throw new Exception("Please announce winners before finalizing.");
            }
            if ($tournament['prize_style'] == 'top_3' && count($winners) < 3 && $tournament['current_participants'] >= 3) {
                throw new Exception("Please announce at least 3 winners for Top 3 style.");
            }

            foreach ($winners as $w) {
                $w_prize = 0;
                if ($w['position'] == 1) $w_prize = $tournament['winning_prize'];
                elseif ($w['position'] == 2) $w_prize = $tournament['second_prize'];
                elseif ($w['position'] == 3) $w_prize = $tournament['third_prize'];

                if ($w_prize > 0) {
                    $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?");
                    $stmt->execute([$w_prize, $w['user_id']]);
                    $total_prize_given += $w_prize;

                    $stmt = $db->prepare("UPDATE tournament_winners SET prize_awarded = ? WHERE tournament_id = ? AND user_id = ?");
                    $stmt->execute([$w_prize, $_GET['id'], $w['user_id']]);
                }
            }
        }

        // Calculate Organizer Profit/Loss
        // Collection - Platform Fee (5%) - Prizes Distributed
        $organizer_share = $total_collection - $platform_fee - $total_prize_given;
        
        if ($organizer_share != 0) {
            $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?");
            $stmt->execute([$organizer_share, $tournament['owner_id']]);
        }

        // Record platform earnings
        if ($platform_fee > 0) {
            $stmt = $db->prepare("INSERT INTO platform_earnings (tournament_id, amount, type) VALUES (?, ?, 'tournament_fee')");
            $stmt->execute([$_GET['id'], $platform_fee]);
        }

        // Finalize tournament
        $stmt = $db->prepare("UPDATE tournaments SET status = 'completed' WHERE tournament_id = ?");
        $stmt->execute([$_GET['id']]);

        log_audit($db, $_SESSION['user_id'], 'COMPLETE_TOURNAMENT', "Finalized tournament ID: " . $_GET['id'] . ". Total Collection: ₹$total_collection, Prizes: ₹$total_prize_given, Platform Fee: ₹$platform_fee, Organizer Share: ₹$organizer_share");
        
        if ($platform_fee > 0) {
            log_audit($db, 1, 'PLATFORM_EARNING', "Collected ₹$platform_fee from tournament ID: " . $_GET['id']);
        }

        $db->commit();
        $success = "Tournament finalized! Prizes distributed and platform fee (5%) deducted.";
        header("Location: tournament_details.php?id=" . $_GET['id']);
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card bg-dark text-light border-secondary">
            <div class="card-header bg-secondary bg-opacity-25 border-bottom border-secondary">
                <h4 class="mb-0"><i class="fas fa-flag-checkered text-danger me-2"></i>Finalize Tournament</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Tournament Info</h5>
                        <p class="mb-1">Name: <strong><?php echo htmlspecialchars($tournament['tournament_name']); ?></strong></p>
                        <p class="mb-1">Style: <span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $tournament['prize_style'])); ?></span></p>
                        <p class="mb-1">Total Players: <strong><?php echo $tournament['current_participants']; ?></strong></p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h5>Financial Estimate</h5>
                        <p class="mb-1">Total Collection: <strong>₹<?php echo number_format($tournament['current_participants'] * $tournament['registration_fee'], 2); ?></strong></p>
                        <p class="mb-1 text-warning">Platform Fee (5%): <strong>-₹<?php echo number_format(($tournament['current_participants'] * $tournament['registration_fee']) * 0.05, 2); ?></strong></p>
                    </div>
                </div>

                <form method="POST" action="">
                    <?php if ($tournament['prize_style'] == 'per_kill'): ?>
                        <div class="mb-4">
                            <h5><i class="fas fa-crosshairs me-2"></i>Enter Kills for Participants</h5>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover">
                                    <thead>
                                        <tr>
                                            <th>Player</th>
                                            <th>Kills</th>
                                            <th>Prize Estimate (Kills * ₹<?php echo number_format($tournament['per_kill_prize'], 2); ?>)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($participants as $p): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($p['username']); ?>
                                                    <?php 
                                                    $is_winner = false;
                                                    foreach($winners as $w) if($w['user_id'] == $p['user_id'] && $w['position'] == 1) $is_winner = true;
                                                    if($is_winner) echo ' <span class="badge bg-warning text-dark"><i class="fas fa-crown"></i> Booyah</span>';
                                                    ?>
                                                </td>
                                                <td>
                                                    <input type="number" name="kills[<?php echo $p['user_id']; ?>]" class="form-control form-control-sm bg-dark text-light border-secondary kill-input" data-prize="<?php echo $tournament['per_kill_prize']; ?>" value="0" min="0">
                                                </td>
                                                <td class="prize-calc">₹0.00</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mb-4">
                            <h5><i class="fas fa-trophy me-2"></i>Winners Summary</h5>
                            <ul class="list-group list-group-flush bg-transparent">
                                <?php if (count($winners) > 0): ?>
                                    <?php foreach ($winners as $w): ?>
                                        <li class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between align-items-center">
                                            <span><strong>#<?php echo $w['position']; ?></strong> - <?php echo htmlspecialchars($w['username']); ?></span>
                                            <span class="text-success">₹<?php 
                                                if($w['position'] == 1) echo number_format($tournament['winning_prize'], 2);
                                                elseif($w['position'] == 2) echo number_format($tournament['second_prize'], 2);
                                                elseif($w['position'] == 3) echo number_format($tournament['third_prize'], 2);
                                            ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="list-group-item bg-transparent text-danger border-secondary">No winners announced yet. <a href="announce_winners.php?id=<?php echo $_GET['id']; ?>" class="text-info">Go to Announce Winners</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-danger mt-4">
                        <i class="fas fa-exclamation-triangle me-2"></i> <strong>Warning:</strong> This action will automatically deduct/add funds to users' wallets and finalize the tournament. It cannot be undone.
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="tournament_details.php?id=<?php echo $_GET['id']; ?>" class="btn btn-outline-light">Cancel</a>
                        <button type="submit" name="finalize_tournament" class="btn btn-danger btn-lg px-5" <?php echo ($tournament['prize_style'] != 'per_kill' && count($winners) == 0) ? 'disabled' : ''; ?>>
                            <i class="fas fa-check-double me-2"></i>Finalize & Pay Winners
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.kill-input').forEach(input => {
    input.addEventListener('input', function() {
        const kills = parseInt(this.value) || 0;
        const perKill = parseFloat(this.getAttribute('data-prize'));
        const total = kills * perKill;
        this.closest('tr').querySelector('.prize-calc').textContent = '₹' + total.toFixed(2);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>