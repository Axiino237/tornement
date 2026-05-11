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

// Handle dismiss/resolve action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['report_id'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }
    $report_id = (int)$_POST['report_id'];
    if ($_POST['action'] === 'resolve') {
        $stmt = $db->prepare("UPDATE tournament_reports SET status = 'resolved' WHERE report_id = ?");
        $stmt->execute([$report_id]);
        $success = "Report marked as resolved.";
    } elseif ($_POST['action'] === 'dismiss') {
        $stmt = $db->prepare("UPDATE tournament_reports SET status = 'dismissed' WHERE report_id = ?");
        $stmt->execute([$report_id]);
        $success = "Report dismissed.";
    }
}

// Fetch all reports with tournament and reporter info
$stmt = $db->query("
    SELECT tr.report_id, tr.report_reason, tr.reported_at, tr.status,
           t.tournament_name AS tournament_title, t.tournament_id,
           u.username AS reporter_username, u.email AS reporter_email
    FROM tournament_reports tr
    LEFT JOIN tournaments t ON tr.tournament_id = t.tournament_id
    LEFT JOIN users u ON tr.reporter_id = u.user_id
    ORDER BY tr.reported_at DESC
");
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once '../includes/header.php'; ?>

        <h2><i class="fas fa-flag text-danger me-2"></i>Tournament Reports</h2>
        <hr class="border-secondary mb-4">

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (empty($reports)): ?>
            <div class="text-center py-5">
                <i class="fas fa-flag fa-4x text-muted mb-3"></i>
                <p class="text-muted fs-5">No reports submitted yet.</p>
            </div>
        <?php else: ?>
        <!-- Filter Tabs -->
        <ul class="nav nav-pills mb-4" id="reportTabs">
            <li class="nav-item">
                <a class="nav-link active" href="#" data-filter="all">All (<?php echo count($reports); ?>)</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-filter="pending">Pending (<?php echo count(array_filter($reports, fn($r) => $r['status'] === 'pending')); ?>)</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-filter="resolved">Resolved</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-filter="dismissed">Dismissed</a>
            </li>
        </ul>

        <div class="table-responsive" id="reportsContainer">
            <table class="table table-dark table-hover align-middle">
                <thead class="table-secondary text-dark">
                    <tr>
                        <th>#</th>
                        <th>Tournament</th>
                        <th>Reported By</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $i => $r): ?>
                    <tr data-status="<?php echo $r['status']; ?>">
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <a href="/tournament_details.php?id=<?php echo $r['tournament_id']; ?>" class="text-warning text-decoration-none">
                                <?php echo htmlspecialchars($r['tournament_title'] ?? 'N/A'); ?>
                            </a>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($r['reporter_username'] ?? 'Unknown'); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($r['reporter_email'] ?? ''); ?></small>
                        </td>
                        <td style="max-width: 250px;">
                            <span class="d-inline-block text-truncate" style="max-width: 230px;" title="<?php echo htmlspecialchars($r['report_reason']); ?>">
                                <?php echo htmlspecialchars($r['report_reason']); ?>
                            </span>
                        </td>
                        <td><small><?php echo date('d M Y, h:i A', strtotime($r['reported_at'])); ?></small></td>
                        <td>
                            <?php if ($r['status'] === 'resolved'): ?>
                                <span class="badge bg-success">Resolved</span>
                            <?php elseif ($r['status'] === 'dismissed'): ?>
                                <span class="badge bg-secondary">Dismissed</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!in_array($r['status'], ['resolved', 'dismissed'])): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="report_id" value="<?php echo $r['report_id']; ?>">
                                <button type="submit" name="action" value="resolve" class="btn btn-sm btn-success me-1" title="Mark Resolved">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button type="submit" name="action" value="dismiss" class="btn btn-sm btn-secondary" title="Dismiss">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                            <?php else: ?>
                                <span class="text-muted small">No action needed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

<script>
document.querySelectorAll('#reportTabs .nav-link').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('#reportTabs .nav-link').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        const filter = this.dataset.filter;
        document.querySelectorAll('#reportsContainer tbody tr').forEach(row => {
            if (filter === 'all' || row.dataset.status === filter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
