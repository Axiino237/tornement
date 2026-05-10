<?php
session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';

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

$error = '';
$success = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }

    $upi_id = trim($_POST['admin_upi_id']);
    
    // Update UPI ID
    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'admin_upi_id'");
    $stmt->execute([$upi_id]);

    // Update SMTP Settings
    if (isset($_POST['smtp_host'])) {
        $smtp_fields = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from_email', 'smtp_from_name'];
        foreach ($smtp_fields as $field) {
            $val = trim($_POST[$field]);
            $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$val, $field]);
        }
        log_audit($db, $_SESSION['user_id'], 'UPDATE_SMTP_SETTINGS', "Updated SMTP email configuration");

        // Handle Test Email
        if (isset($_POST['send_test'])) {
            require_once '../includes/mailer.php';
            $test_to = $settings['smtp_user'] ?? $_SESSION['email'] ?? '';
            if (empty($test_to)) {
                $error = "Cannot send test email: No recipient found. Please save your email first.";
            } else {
                if (send_email($test_to, "Test Email - Tournament App", "<h3>SMTP Configuration Test</h3><p>If you are reading this, your SMTP settings are working correctly!</p><p>Sent at: " . date('Y-m-d H:i:s') . "</p>")) {
                    $success = "Test email sent successfully to <strong>$test_to</strong>! Please check your inbox (and spam folder).";
                } else {
                    $error = "Failed to send test email: " . ($last_mailer_error ?: "Unknown error. Check PHP error logs.");
                }
            }
        } else {
            $success = "Settings updated successfully!";
        }
    }

    // Handle QR Code upload
    if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] == 0) {
        $target_dir = "../assets/images/";
        $file_extension = pathinfo($_FILES["qr_code"]["name"], PATHINFO_EXTENSION);
        $new_filename = "admin_qr_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        $db_path = "assets/images/" . $new_filename;

        // Check if image file is a actual image or fake image
        $check = getimagesize($_FILES["qr_code"]["tmp_name"]);
        if($check !== false) {
            if (move_uploaded_file($_FILES["qr_code"]["tmp_name"], $target_file)) {
                $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'admin_qr_path'");
                $stmt->execute([$db_path]);
                log_audit($db, $_SESSION['user_id'], 'UPDATE_QR_CODE', "Uploaded new QR code: $new_filename");
                $success = "Settings and QR Code updated successfully!";
            } else {
                $error = "Failed to upload QR Code.";
            }
        } else {
            $error = "File is not an image.";
        }
    } elseif (!isset($_POST['send_test'])) {
        // success message already set if smtp was updated
    }
}

// Fetch current settings (re-fetch after update)
$stmt = $db->query("SELECT * FROM settings");
$settings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$settings = [];
foreach ($settings_raw as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}
?>
<?php require_once '../includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-12 mb-4">
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <div class="card bg-dark text-light border-secondary h-100">
            <div class="card-header bg-secondary bg-opacity-25 border-bottom border-secondary d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-cog me-2 text-info"></i>Payment Settings</h4>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    
                    <div class="mb-4">
                        <label for="admin_upi_id" class="form-label">Admin UPI ID</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="admin_upi_id" name="admin_upi_id" value="<?php echo htmlspecialchars($settings['admin_upi_id'] ?? ''); ?>" required>
                        <small class="text-muted">Users will see this UPI ID on the recharge page.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Current QR Code</label>
                        <div class="mb-2">
                            <img src="../<?php echo htmlspecialchars($settings['admin_qr_path'] ?? 'assets/images/qr_placeholder.png'); ?>" class="img-fluid rounded border border-secondary" style="max-width: 200px;" alt="Current QR">
                        </div>
                        <label for="qr_code" class="form-label">Upload New QR Code</label>
                        <input type="file" class="form-control bg-dark text-light border-secondary" id="qr_code" name="qr_code" accept="image/*">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-info fw-bold">
                            <i class="fas fa-save me-2"></i>Save Payment Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6 mt-4 mt-md-0">
        <div class="card bg-dark text-light border-secondary h-100">
            <div class="card-header bg-secondary bg-opacity-25 border-bottom border-secondary">
                <h4 class="mb-0"><i class="fas fa-envelope me-2 text-warning"></i>Email (SMTP) Settings</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="admin_upi_id" value="<?php echo htmlspecialchars($settings['admin_upi_id'] ?? ''); ?>">

                    <div class="row">
                        <div class="col-md-9 mb-3">
                            <label for="smtp_host" class="form-label">SMTP Host</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com'); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="smtp_port" class="form-label">Port</label>
                            <input type="number" class="form-control bg-dark text-light border-secondary" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="smtp_user" class="form-label">SMTP Username (Email)</label>
                        <input type="email" class="form-control bg-dark text-light border-secondary" id="smtp_user" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>" placeholder="your-email@gmail.com">
                    </div>

                    <div class="mb-3">
                        <label for="smtp_pass" class="form-label">SMTP Password / App Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control bg-dark text-light border-secondary" id="smtp_pass" name="smtp_pass" value="<?php echo htmlspecialchars($settings['smtp_pass'] ?? ''); ?>" placeholder="••••••••••••••••">
                            <button class="btn btn-outline-secondary" type="button" onclick="const p = document.getElementById('smtp_pass'); p.type = p.type === 'password' ? 'text' : 'password';"><i class="fas fa-eye"></i></button>
                        </div>
                        <small class="text-muted">Use Gmail <strong>App Password</strong> if 2FA is enabled.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="smtp_from_email" class="form-label">From Email</label>
                            <input type="email" class="form-control bg-dark text-light border-secondary" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="smtp_from_name" class="form-label">From Name</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? 'Tournament Admin'); ?>">
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-8">
                            <button type="submit" class="btn btn-warning fw-bold text-dark w-100">
                                <i class="fas fa-save me-2"></i>Save Email Settings
                            </button>
                        </div>
                        <div class="col-4">
                            <button type="submit" name="send_test" class="btn btn-outline-warning fw-bold w-100">
                                <i class="fas fa-paper-plane me-1"></i>Test
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
