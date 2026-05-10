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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }
    
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $transaction_id = trim($_POST['transaction_id']);
    
    $valid_amounts = [20, 50, 100, 500];
    
    if (!in_array($amount, $valid_amounts)) {
        $error = "Please select a valid recharge amount.";
    } elseif (empty($transaction_id)) {
        $error = "Transaction ID is required.";
    } else {
        $stmt = $db->prepare("INSERT INTO wallet_recharges (user_id, amount, transaction_id) VALUES (?, ?, ?)");
        if ($stmt->execute([$_SESSION['user_id'], $amount, $transaction_id])) {
            $success = "Recharge request submitted successfully! It will be credited after admin verification.";
        } else {
            $error = "Failed to submit recharge request.";
        }
    }
}

// Get user's past recharges
$stmt = $db->prepare("SELECT * FROM wallet_recharges WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$past_recharges = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT wallet_balance FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_wallet = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch admin payment settings
$stmt = $db->query("SELECT * FROM settings");
$settings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$settings = [];
foreach ($settings_raw as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 mb-4">
        <div class="card bg-dark text-light border-secondary">
            <div class="card-header bg-secondary bg-opacity-25 border-bottom border-secondary d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-wallet text-info me-2"></i>Recharge Wallet</h4>
                <span class="badge bg-success fs-6">Balance: ₹<?php echo number_format($user_wallet['wallet_balance'], 2); ?></span>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="alert alert-warning border-warning bg-warning bg-opacity-10 text-warning mb-4">
                    <i class="fas fa-info-circle me-2"></i> <strong>Note:</strong> Recharges are verified manually by our team. Your balance will be updated within <strong>48 hours</strong> after successful verification of the Transaction ID.
                </div>

                <div class="qr-container mb-4 text-center">
                    <img src="<?php echo htmlspecialchars($settings['admin_qr_path'] ?? 'assets/images/qr_placeholder.png'); ?>" class="img-fluid rounded border border-secondary mb-2" style="max-width: 200px;" alt="UPI QR Code">
                    <p class="text-info mb-1 fw-bold"><?php echo htmlspecialchars($settings['admin_upi_id'] ?? ''); ?></p>
                    <p class="text-muted small">Scan the QR code or pay to the UPI ID above using any UPI App</p>
                </div>

                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    
                    <div class="mb-4">
                        <label class="form-label d-block text-center mb-3">Select Amount to Recharge</label>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <div class="form-check p-0 m-0">
                                <input class="form-check-input d-none amount-radio" type="radio" name="amount" id="amt20" value="20" required>
                                <label class="btn btn-outline-info px-3 py-2" for="amt20">₹20</label>
                            </div>
                            <div class="form-check p-0 m-0">
                                <input class="form-check-input d-none amount-radio" type="radio" name="amount" id="amt50" value="50">
                                <label class="btn btn-outline-info px-3 py-2" for="amt50">₹50</label>
                            </div>
                            <div class="form-check p-0 m-0">
                                <input class="form-check-input d-none amount-radio" type="radio" name="amount" id="amt100" value="100">
                                <label class="btn btn-outline-info px-3 py-2" for="amt100">₹100</label>
                            </div>
                            <div class="form-check p-0 m-0">
                                <input class="form-check-input d-none amount-radio" type="radio" name="amount" id="amt500" value="500">
                                <label class="btn btn-outline-info px-3 py-2" for="amt500">₹500</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="transaction_id" class="form-label">UPI Transaction ID / UTR Number</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="transaction_id" name="transaction_id" placeholder="e.g. 301234567890" required>
                        <div class="form-text text-muted">Enter the 12-digit transaction ID after successful payment.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2"><i class="fas fa-paper-plane me-2"></i>Submit Recharge Request</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card bg-dark text-light border-secondary">
            <div class="card-header border-bottom border-secondary">
                <h5 class="mb-0">Recent Recharge Requests</h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($past_recharges) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($past_recharges as $recharge): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($recharge['created_at'])); ?></td>
                                        <td>₹<?php echo number_format($recharge['amount'], 2); ?></td>
                                        <td>
                                            <?php if ($recharge['status'] === 'approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php elseif ($recharge['status'] === 'rejected'): ?>
                                                <span class="badge bg-danger">Rejected</span>
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
                        No recharge requests found.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styling for radio button labels to look like selectable buttons */
.amount-radio:checked + label {
    background-color: #0dcaf0;
    color: #000;
    border-color: #0dcaf0;
    box-shadow: 0 0 15px rgba(13, 202, 240, 0.4);
}
</style>

<?php require_once 'includes/footer.php'; ?>
