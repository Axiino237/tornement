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

// Pagination
$page = isset($_GET['page']) ? (int)$GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Fetch logs with user info
$stmt = $db->query("SELECT a.*, u.username 
                    FROM audit_logs a 
                    LEFT JOIN users u ON a.user_id = u.user_id 
                    ORDER BY a.created_at DESC 
                    LIMIT $per_page OFFSET $offset");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total for pagination
$stmt = $db->query("SELECT COUNT(*) FROM audit_logs");
$total_logs = $stmt->fetchColumn();
$total_pages = ceil($total_logs / $per_page);
?>
<?php require_once '../includes/header.php'; ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="fas fa-history text-info me-2"></i>Audit & Service Logs</h2>
            <span class="badge bg-secondary">Total Logs: <?php echo $total_logs; ?></span>
        </div>

        <div class="card bg-dark text-light border-secondary">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead class="border-bottom border-secondary">
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="small text-muted"><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <span class="fw-bold"><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></span>
                                        <br><small class="text-muted">ID: <?php echo $log['user_id'] ?? '0'; ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $badge_class = 'bg-secondary';
                                        if (strpos($log['action'], 'JOIN') !== false) $badge_class = 'bg-info text-dark';
                                        if (strpos($log['action'], 'COMPLETE') !== false) $badge_class = 'bg-success';
                                        if (strpos($log['action'], 'RECHARGE') !== false) $badge_class = 'bg-primary';
                                        if (strpos($log['action'], 'WITHDRAWAL') !== false) $badge_class = 'bg-warning text-dark';
                                        if (strpos($log['action'], 'DEACTIVATE') !== false) $badge_class = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($log['action']); ?></span>
                                    </td>
                                    <td class="small"><?php echo htmlspecialchars($log['details']); ?></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">No audit logs found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link bg-dark border-secondary text-light" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
