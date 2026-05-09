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
    } else {
        $success = "Settings updated successfully!";
    }
}

// Fetch current settings
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
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <div class="card bg-dark text-light border-secondary">
            <div class="card-header bg-secondary bg-opacity-25 border-bottom border-secondary">
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
        <div class="card bg-dark text-light border-secondary">
            <div class="card-header bg-secondary bg-opacity-25 border-bottom border-secondary">
                <h4 class="mb-0"><i class="fas fa-envelope me-2 text-warning"></i>Email (SMTP) Settings</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <!-- Hidden UPI ID to satisfy the form requirement -->
                    <input type="hidden" name="admin_upi_id" value="<?php echo htmlspecialchars($settings['admin_upi_id'] ?? ''); ?>">

                    <div class="row">
                        <div class="col-md-9 mb-3">
                            <label for="smtp_host" class="form-label">SMTP Host</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary opacity-75" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com'); ?>" readonly>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="smtp_port" class="form-label">Port</label>
                            <input type="number" class="form-control bg-dark text-light border-secondary opacity-75" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="smtp_user" class="form-label">SMTP Username (Email)</label>
                        <input type="email" class="form-control bg-dark text-light border-secondary" id="smtp_user" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>" placeholder="your-email@gmail.com" oninput="document.getElementById('smtp_from_email').value = this.value">
                    </div>

                    <div class="mb-3">
                        <label for="smtp_pass" class="form-label">SMTP Password / App Password</label>
                        <input type="password" class="form-control bg-dark text-light border-secondary" id="smtp_pass" name="smtp_pass" value="<?php echo htmlspecialchars($settings['smtp_pass'] ?? ''); ?>" placeholder="••••••••••••••••">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="smtp_from_email" class="form-label">From Email</label>
                            <input type="email" class="form-control bg-dark text-light border-secondary opacity-75" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="smtp_from_name" class="form-label">From Name</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary opacity-75" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? 'Tournament Admin'); ?>" readonly>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning fw-bold text-dark">
                            <i class="fas fa-paper-plane me-2"></i>Save Email Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
