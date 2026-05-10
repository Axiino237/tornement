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

    if (isset($_POST['action']) && isset($_POST['recharge_id'])) {
        $recharge_id = $_POST['recharge_id'];
        
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM wallet_recharges WHERE recharge_id = ? AND status = 'pending' FOR UPDATE");
            $stmt->execute([$recharge_id]);
            $recharge = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($recharge) {
                if ($_POST['action'] === 'approve') {
                    // Update status
                    $stmt = $db->prepare("UPDATE wallet_recharges SET status = 'approved' WHERE recharge_id = ?");
                    $stmt->execute([$recharge_id]);

                    // Add funds to wallet
                    $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?");
                    $stmt->execute([$recharge['amount'], $recharge['user_id']]);
                    
                    log_audit($db, $_SESSION['user_id'], 'APPROVE_RECHARGE', "Approved ₹" . $recharge['amount'] . " for User ID: " . $recharge['user_id'] . " (UTR: " . $recharge['transaction_id'] . ")");
                    $success = "Recharge request approved successfully!";
                } elseif ($_POST['action'] === 'reject') {
                    // Update status
                    $stmt = $db->prepare("UPDATE wallet_recharges SET status = 'rejected' WHERE recharge_id = ?");
                    $stmt->execute([$recharge_id]);
                    
                    log_audit($db, $_SESSION['user_id'], 'REJECT_RECHARGE', "Rejected recharge request #$recharge_id for User ID: " . $recharge['user_id'] . " (UTR: " . $recharge['transaction_id'] . ")");
                    $success = "Recharge request rejected.";
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
$stmt = $db->query("SELECT r.*, u.username, u.email FROM wallet_recharges r 
                    JOIN users u ON r.user_id = u.user_id 
                    WHERE r.status = 'pending' 
                    ORDER BY r.created_at ASC");
$pending_recharges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recently processed requests
$stmt = $db->query("SELECT r.*, u.username FROM wallet_recharges r 
                    JOIN users u ON r.user_id = u.user_id 
                    WHERE r.status != 'pending' 
                    ORDER BY r.created_at DESC LIMIT 10");
$processed_recharges = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once '../includes/header.php'; ?>

        <h2 class="mb-4"><i class="fas fa-file-invoice-dollar text-info me-2"></i>Manage Recharges</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="card bg-dark text-light border-secondary mb-5">
            <div class="card-header bg-secondary bg-opacity-25 border-bottom border-secondary">
                <h5 class="mb-0">Pending Recharge Requests</h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($pending_recharges) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead class="border-bottom border-secondary">
                                <tr>
                                    <th class="d-none d-md-table-cell">ID</th>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Transaction ID</th>
                                    <th class="d-none d-md-table-cell">Requested At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_recharges as $r): ?>
                                    <tr>
                                        <td class="d-none d-md-table-cell">#<?php echo $r['recharge_id']; ?></td>
                                        <td>
                                            <div class="fw-bold small"><?php echo htmlspecialchars($r['username']); ?></div>
                                            <div class="small text-muted d-none d-md-block"><?php echo htmlspecialchars($r['email']); ?></div>
                                        </td>
                                        <td class="fw-bold text-success small">₹<?php echo number_format($r['amount'], 2); ?></td>
                                        <td class="font-monospace user-select-all small"><?php echo htmlspecialchars($r['transaction_id']); ?></td>
                                        <td class="d-none d-md-table-cell"><?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Approve?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="recharge_id" value="<?php echo $r['recharge_id']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success px-2 py-1 small-badge"><i class="fas fa-check"></i><span class="d-none d-md-inline ms-1">Approve</span></button>
                                            </form>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Reject?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="recharge_id" value="<?php echo $r['recharge_id']; ?>">
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
                        <i class="fas fa-check-circle fa-3x mb-3 text-success opacity-50"></i>
                        <h5>All Caught Up!</h5>
                        <p>There are no pending recharge requests.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card bg-dark text-light border-secondary">
            <div class="card-header border-bottom border-secondary">
                <h5 class="mb-0">Recently Processed</h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($processed_recharges) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead class="border-bottom border-secondary">
                                <tr>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th class="d-none d-md-table-cell">Transaction ID</th>
                                    <th>Status</th>
                                    <th class="d-none d-md-table-cell">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($processed_recharges as $r): ?>
                                    <tr>
                                        <td><span class="small"><?php echo htmlspecialchars($r['username']); ?></span></td>
                                        <td><span class="small">₹<?php echo number_format($r['amount'], 2); ?></span></td>
                                        <td class="d-none d-md-table-cell"><span class="text-muted font-monospace small"><?php echo htmlspecialchars($r['transaction_id']); ?></span></td>
                                        <td>
                                            <?php if ($r['status'] === 'approved'): ?>
                                                <span class="badge bg-success small">Approved</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger small">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="d-none d-md-table-cell"><small class="text-muted"><?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?></small></td>
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
