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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }

    if (isset($_POST['action']) && isset($_POST['target_user_id'])) {
        $target_id = $_POST['target_user_id'];
        
        if ($_POST['action'] === 'activate') {
            $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
            $stmt->execute([$target_id]);
            log_audit($db, $_SESSION['user_id'], 'ACTIVATE_USER', "Activated user ID: $target_id");
            $success = "User activated successfully!";
        } elseif ($_POST['action'] === 'deactivate') {
            // Don't allow deactivating themselves
            if ($target_id != $_SESSION['user_id']) {
                $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
                $stmt->execute([$target_id]);
                log_audit($db, $_SESSION['user_id'], 'DEACTIVATE_USER', "Deactivated user ID: $target_id");
                $success = "User deactivated successfully!";
            } else {
                $error = "You cannot deactivate your own admin account!";
            }
        }
    }
}

$stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once '../includes/header.php'; ?>

        <h2><i class="fas fa-users-cog text-warning me-2"></i>Manage Users</h2>
        <hr class="border-secondary mb-4">

        <?php if (isset($success) && $success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error) && $error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card bg-dark text-light border-secondary">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead class="border-bottom border-secondary">
                            <tr>
                                <th class="d-none d-md-table-cell">ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Wallet Balance (₹)</th>
                                <th class="d-none d-md-table-cell">Verified</th>
                                <th class="d-none d-md-table-cell">Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_users as $u): ?>
                                <tr>
                                    <td class="d-none d-md-table-cell"><?php echo $u['user_id']; ?></td>
                                    <td>
                                        <div class="fw-bold small"><?php echo htmlspecialchars($u['username']); ?></div>
                                        <div class="text-muted extra-small d-md-none"><?php echo htmlspecialchars($u['email']); ?></div>
                                    </td>
                                    <td class="d-none d-md-table-cell small"><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><span class="badge bg-info text-dark">₹<?php echo number_format($u['wallet_balance'], 2); ?></span></td>
                                    <td class="d-none d-md-table-cell"><?php echo $u['is_email_verified'] ? '<span class="badge bg-success"><i class="fas fa-check"></i></span>' : '<span class="badge bg-danger"><i class="fas fa-times"></i></span>'; ?></td>
                                    <td class="d-none d-md-table-cell">
                                        <?php if($u['role'] === 'admin'): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-shield-alt me-1"></i>Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="fas fa-user me-1"></i>User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($u['status'] === 'active'): ?>
                                            <span class="badge bg-success small-badge">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger small-badge">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" style="display:inline;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="target_user_id" value="<?php echo $u['user_id']; ?>">
                                                <?php if ($u['status'] === 'inactive'): ?>
                                                    <button type="submit" name="action" value="activate" class="btn btn-sm btn-success px-2 py-1" style="font-size: 0.7rem;"><i class="fas fa-check"></i></button>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="deactivate" class="btn btn-sm btn-danger px-2 py-1" style="font-size: 0.7rem;" onclick="return confirm('Deactivate?')"><i class="fas fa-ban"></i></button>
                                                <?php endif; ?>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

<?php require_once '../includes/footer.php'; ?>
