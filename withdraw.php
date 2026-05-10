<?php
require_once 'config/database.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Get current wallet balance
$stmt = $db->prepare("SELECT wallet_balance FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_wallet = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }
    
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $upi_id = trim($_POST['upi_id']);
    
    // Calculate 5% fee
    $fee_amount = $amount * 0.05;
    $net_amount = $amount - $fee_amount;
    
    if ($amount <= 0) {
        $error = "Please enter a valid amount.";
    } elseif ($amount > $user_wallet['wallet_balance']) {
        $error = "Insufficient wallet balance.";
    } elseif (empty($upi_id)) {
        $error = "UPI ID is required.";
    } else {
        try {
            $db->beginTransaction();

            // Deduct from wallet immediately to prevent double-spending
            $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE user_id = ?");
            $stmt->execute([$amount, $_SESSION['user_id']]);

            // Create withdrawal request with fee details
            $stmt = $db->prepare("INSERT INTO wallet_withdrawals (user_id, amount, fee_amount, net_amount, upi_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $amount, $fee_amount, $net_amount, $upi_id]);
            
            // Record the fee in platform earnings immediately
            $stmt = $db->prepare("INSERT INTO platform_earnings (amount, type, details) VALUES (?, 'withdrawal_fee', ?)");
            $stmt->execute([$fee_amount, "Fee from User ID: " . $_SESSION['user_id'] . " withdrawal of ₹$amount"]);

            $db->commit();
            log_audit($db, $_SESSION['user_id'], 'WITHDRAW_REQUEST', "Requested ₹$amount (Fee: ₹$fee_amount, Net: ₹$net_amount) to UPI: $upi_id");
            $success = "Withdrawal request submitted! ₹" . number_format($net_amount, 2) . " (after 5% fee) will be sent to your UPI within 48 hours.";
            
            // Update local variable so UI reflects immediately
            $user_wallet['wallet_balance'] -= $amount;
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Failed to process withdrawal request. Error: " . $e->getMessage();
        }
    }
}

// Get user's past withdrawals
$stmt = $db->prepare("SELECT * FROM wallet_withdrawals WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$past_withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row justify-content-center">
    <div class="col-md-6 mb-4">
        <div class="card bg-dark text-light border-secondary">
            <div class="card-header bg-secondary bg-opacity-25 border-bottom border-secondary d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-money-bill-wave text-warning me-2"></i>Withdraw Funds</h4>
                <span class="badge bg-success fs-6">Balance: ₹<?php echo number_format($user_wallet['wallet_balance'], 2); ?></span>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="alert alert-warning">
                    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Important Disclaimer</h5>
                    <p class="mb-0 small">Please double-check your UPI ID before submitting. <strong>We are NOT responsible if you provide an incorrect UPI ID and the payment is sent to the wrong person.</strong> Withdrawals are processed manually and may take up to 48 hours.</p>
                </div>

                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount to Withdraw (₹)</label>
                        <input type="number" class="form-control bg-dark text-light border-secondary" id="amount" name="amount" min="1" max="<?php echo $user_wallet['wallet_balance']; ?>" step="0.01" required>
                    </div>

                    <div class="mb-4">
                        <label for="upi_id" class="form-label">Your UPI ID</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="upi_id" name="upi_id" placeholder="e.g. yourname@ybl" required>
                    </div>

                    <div id="withdrawal_summary" class="bg-black bg-opacity-50 p-3 rounded mb-3" style="display: none;">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Withdrawal Amount:</span>
                            <span id="summary_amount">₹0.00</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1 text-danger">
                            <span>Admin Fee (5%):</span>
                            <span id="summary_fee">₹0.00</span>
                        </div>
                        <hr class="my-2 border-secondary">
                        <div class="d-flex justify-content-between fw-bold text-success">
                            <span>Net to Receive:</span>
                            <span id="summary_net">₹0.00</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 py-2 fw-bold" <?php echo $user_wallet['wallet_balance'] <= 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-paper-plane me-2"></i>Request Withdrawal
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card bg-dark text-light border-secondary">
            <div class="card-header border-bottom border-secondary">
                <h5 class="mb-0">Recent Withdrawal Requests</h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($past_withdrawals) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Requested</th>
                                    <th>Net (to pay)</th>
                                    <th>UPI ID</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($past_withdrawals as $withdrawal): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($withdrawal['created_at'])); ?></td>
                                        <td>₹<?php echo number_format($withdrawal['amount'], 2); ?></td>
                                        <td class="text-success fw-bold">₹<?php echo number_format($withdrawal['net_amount'] ?? $withdrawal['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($withdrawal['upi_id']); ?></td>
                                        <td>
                                            <?php if ($withdrawal['status'] === 'approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php elseif ($withdrawal['status'] === 'rejected'): ?>
                                                <span class="badge bg-danger" title="Money has been refunded to your wallet">Rejected (Refunded)</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        No withdrawal requests found.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('amount').addEventListener('input', function() {
    const amount = parseFloat(this.value) || 0;
    const summary = document.getElementById('withdrawal_summary');
    
    if (amount > 0) {
        summary.style.display = 'block';
        const fee = amount * 0.05;
        const net = amount - fee;
        
        document.getElementById('summary_amount').textContent = '₹' + amount.toFixed(2);
        document.getElementById('summary_fee').textContent = '- ₹' + fee.toFixed(2);
        document.getElementById('summary_net').textContent = '₹' + net.toFixed(2);
    } else {
        summary.style.display = 'none';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
