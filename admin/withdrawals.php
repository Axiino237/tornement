<?php
session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';

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

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }

    if (isset($_POST['action']) && isset($_POST['withdrawal_id'])) {
        $withdrawal_id = $_POST['withdrawal_id'];
        
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM wallet_withdrawals WHERE withdrawal_id = ? AND status = 'pending' FOR UPDATE");
            $stmt->execute([$withdrawal_id]);
            $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($withdrawal) {
                if ($_POST['action'] === 'approve') {
                    // Update status
                    $stmt = $db->prepare("UPDATE wallet_withdrawals SET status = 'approved' WHERE withdrawal_id = ?");
                    $stmt->execute([$withdrawal_id]);

                    log_audit($db, $_SESSION['user_id'], 'APPROVE_WITHDRAWAL', "Completed withdrawal #$withdrawal_id for User ID: " . $withdrawal['user_id'] . " (Amount: ₹" . $withdrawal['amount'] . ")");
                    $success = "Withdrawal request marked as completed!";
                } elseif ($_POST['action'] === 'reject') {
                    // Update status
                    $stmt = $db->prepare("UPDATE wallet_withdrawals SET status = 'rejected' WHERE withdrawal_id = ?");
                    $stmt->execute([$withdrawal_id]);
                    
                    // Refund funds back to user's wallet
                    $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?");
                    $stmt->execute([$withdrawal['amount'], $withdrawal['user_id']]);
                    
                    log_audit($db, $_SESSION['user_id'], 'REJECT_WITHDRAWAL', "Rejected withdrawal request #$withdrawal_id for User ID: " . $withdrawal['user_id'] . " (Amount: ₹" . $withdrawal['amount'] . ")");
                    $success = "Withdrawal rejected. Funds have been refunded to the user's wallet.";
                }
            } else {
                $error = "Invalid or already processed request.";
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $error = "An error occurred while processing the request.";
        }
    }
}

// Fetch all pending requests
$stmt = $db->query("SELECT w.*, u.username, u.email FROM wallet_withdrawals w 
                    JOIN users u ON w.user_id = u.user_id 
                    WHERE w.status = 'pending' 
                    ORDER BY w.created_at ASC");
$pending_withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recently processed requests
$stmt = $db->query("SELECT w.*, u.username FROM wallet_withdrawals w 
                    JOIN users u ON w.user_id = u.user_id 
                    WHERE w.status != 'pending' 
                    ORDER BY w.created_at DESC LIMIT 10");
$processed_withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once '../includes/header.php'; ?>

        <h2 class="mb-4"><i class="fas fa-hand-holding-usd text-warning me-2"></i>Manage Withdrawals</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="alert alert-info border-info mb-4 text-start">
            <h5 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Action Required</h5>
            <p class="mb-0">For pending requests, you must manually transfer the funds to the user's UPI ID using your payment app before clicking <strong>Approve</strong>. If you reject a request, the funds will automatically be refunded to the user's platform wallet.</p>
        </div>

        <div class="card bg-dark text-light border-secondary mb-5">
            <div class="card-header bg-secondary bg-opacity-25 border-bottom border-secondary">
                <h5 class="mb-0">Pending Withdrawal Requests</h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($pending_withdrawals) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead class="border-bottom border-secondary">
                                <tr>
                                    <th class="d-none d-md-table-cell">ID</th>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>UPI ID to Pay</th>
                                    <th class="d-none d-md-table-cell">Requested At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_withdrawals as $w): ?>
                                    <tr>
                                        <td class="d-none d-md-table-cell">#<?php echo $w['withdrawal_id']; ?></td>
                                        <td>
                                            <div class="fw-bold small"><?php echo htmlspecialchars($w['username']); ?></div>
                                            <div class="small text-muted d-none d-md-block"><?php echo htmlspecialchars($w['email']); ?></div>
                                        </td>
                                        <td class="fw-bold text-warning small">₹<?php echo number_format($w['amount'], 2); ?></td>
                                        <td class="font-monospace user-select-all text-info small"><?php echo htmlspecialchars($w['upi_id']); ?></td>
                                        <td class="d-none d-md-table-cell"><?php echo date('M d, Y H:i', strtotime($w['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Already Paid?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="withdrawal_id" value="<?php echo $w['withdrawal_id']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success px-2 py-1 small-badge"><i class="fas fa-check"></i><span class="d-none d-md-inline ms-1">Approve</span></button>
                                            </form>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Reject?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="withdrawal_id" value="<?php echo $w['withdrawal_id']; ?>">
                                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger px-2 py-1 small-badge"><i class="fas fa-times"></i><span class="d-none d-md-inline ms-1">Reject</span></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        <i class="fas fa-glass-cheers fa-3x mb-3 text-warning opacity-50"></i>
                        <h5>All Clear!</h5>
                        <p>There are no pending withdrawal requests.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card bg-dark text-light border-secondary">
            <div class="card-header border-bottom border-secondary">
                <h5 class="mb-0">Recently Processed</h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($processed_withdrawals) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead class="border-bottom border-secondary">
                                <tr>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th class="d-none d-md-table-cell">UPI ID</th>
                                    <th>Status</th>
                                    <th class="d-none d-md-table-cell">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($processed_withdrawals as $w): ?>
                                    <tr>
                                        <td><span class="small"><?php echo htmlspecialchars($w['username']); ?></span></td>
                                        <td><span class="small">₹<?php echo number_format($w['amount'], 2); ?></span></td>
                                        <td class="d-none d-md-table-cell"><span class="text-muted font-monospace small"><?php echo htmlspecialchars($w['upi_id']); ?></span></td>
                                        <td>
                                            <?php if ($w['status'] === 'approved'): ?>
                                                <span class="badge bg-success small">Approved</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger small">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="d-none d-md-table-cell"><small class="text-muted"><?php echo date('M d, Y H:i', strtotime($w['created_at'])); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-3 text-center text-muted">No processed requests found.</div>
                <?php endif; ?>
            </div>
        </div>

<?php require_once '../includes/footer.php'; ?>
