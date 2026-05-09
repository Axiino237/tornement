<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$stmt = $db->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get stats
$stmt = $db->query("SELECT COUNT(*) as total FROM users");
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM tournaments");
$total_tournaments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM tournament_participants");
$total_participants = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM tournament_reports");
$total_reports = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM wallet_recharges WHERE status = 'pending'");
$pending_recharges = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM wallet_withdrawals WHERE status = 'pending'");
$pending_withdrawals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $db->query("SELECT SUM(amount) as total FROM platform_earnings");
$platform_earnings = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>
<?php require_once '../includes/header.php'; ?>

        <h2><i class="fas fa-shield-alt text-warning me-2"></i>Admin Dashboard</h2>
        <hr class="border-secondary mb-4">
        
        <div class="row text-center mt-4">
            <div class="col-md-3 mb-4">
                <div class="card bg-secondary text-light glow-effect">
                    <div class="card-body py-4">
                        <i class="fas fa-users fa-3x mb-3 text-info"></i>
                        <h3 class="display-5 fw-bold"><?php echo $total_users; ?></h3>
                        <p class="text-uppercase text-muted fw-bold mb-0" style="letter-spacing: 1px; font-size: 0.8rem;">Total Users</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card bg-primary text-light glow-effect">
                    <div class="card-body py-4">
                        <i class="fas fa-trophy fa-3x mb-3 text-warning"></i>
                        <h3 class="display-5 fw-bold"><?php echo $total_tournaments; ?></h3>
                        <p class="text-uppercase text-muted fw-bold mb-0" style="letter-spacing: 1px; font-size: 0.8rem;">Total Tournaments</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card bg-success text-light glow-effect">
                    <div class="card-body py-4">
                        <i class="fas fa-gamepad fa-3x mb-3 text-light"></i>
                        <h3 class="display-5 fw-bold"><?php echo $total_participants; ?></h3>
                        <p class="text-uppercase text-muted fw-bold mb-0" style="letter-spacing: 1px; font-size: 0.8rem;">Participants</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card bg-danger text-light glow-effect">
                    <div class="card-body py-4">
                        <i class="fas fa-flag fa-3x mb-3 text-light"></i>
                        <h3 class="display-5 fw-bold"><?php echo $total_reports; ?></h3>
                        <p class="text-uppercase text-muted fw-bold mb-0" style="letter-spacing: 1px; font-size: 0.8rem;">Reports</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row text-center">
            <div class="col-md-6 mb-4">
                <a href="recharges.php" class="text-decoration-none">
                    <div class="card bg-dark border-info text-light glow-effect">
                        <div class="card-body py-4">
                            <i class="fas fa-file-invoice-dollar fa-3x mb-3 text-info"></i>
                            <h3 class="display-5 fw-bold text-info"><?php echo $pending_recharges; ?></h3>
                            <p class="text-uppercase text-muted fw-bold mb-0" style="letter-spacing: 1px; font-size: 0.8rem;">Pending Recharges</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 mb-4">
                <a href="withdrawals.php" class="text-decoration-none">
                    <div class="card bg-dark border-warning text-light glow-effect">
                        <div class="card-body py-4">
                            <i class="fas fa-hand-holding-usd fa-3x mb-3 text-warning"></i>
                            <h3 class="display-5 fw-bold text-warning"><?php echo $pending_withdrawals; ?></h3>
                            <p class="text-uppercase text-muted fw-bold mb-0" style="letter-spacing: 1px; font-size: 0.8rem;">Pending Withdrawals</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-12 mb-4">
                <div class="card bg-dark border-success text-light glow-effect">
                    <div class="card-body py-4">
                        <i class="fas fa-wallet fa-3x mb-3 text-success"></i>
                        <h3 class="display-5 fw-bold text-success">₹<?php echo number_format($platform_earnings, 2); ?></h3>
                        <p class="text-uppercase text-muted fw-bold mb-0" style="letter-spacing: 1px; font-size: 0.8rem;">Total Platform Earnings (Fees + Unlocks)</p>
                    </div>
                </div>
            </div>
        </div>

<?php require_once '../includes/footer.php'; ?>
