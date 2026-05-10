<?php
require_once 'config/database.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch all reports on tournaments owned by this user
$stmt = $db->prepare("
    SELECT tr.report_id, tr.report_reason, tr.reported_at, tr.status,
           t.tournament_id, t.tournament_name,
           u.username AS reporter_username
    FROM tournament_reports tr
    INNER JOIN tournaments t ON tr.tournament_id = t.tournament_id
    LEFT JOIN users u ON tr.reporter_id = u.user_id
    WHERE t.owner_id = ?
    ORDER BY tr.reported_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pending_count = count(array_filter($reports, fn($r) => $r['status'] === 'pending'));
?>

<style>
.report-card {
    background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
    border: 1px solid rgba(220, 53, 69, 0.3);
    border-radius: 12px;
    transition: all 0.3s ease;
}
.report-card:hover {
    border-color: rgba(220, 53, 69, 0.7);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(220, 53, 69, 0.15);
}
.report-card.resolved {
    border-color: rgba(25, 135, 84, 0.4);
    opacity: 0.8;
}
.report-card.dismissed {
    border-color: rgba(108, 117, 125, 0.3);
    opacity: 0.6;
}
.badge-pending { background: linear-gradient(135deg, #dc3545, #c82333); }
.badge-resolved { background: linear-gradient(135deg, #198754, #146c43); }
.badge-dismissed { background: linear-gradient(135deg, #6c757d, #565e64); }
.stat-card {
    background: rgba(0,0,0,0.3);
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.08);
    padding: 20px;
    text-align: center;
}
@media (max-width: 768px) {
    .report-card {
        padding: 1.5rem !important;
    }
    .stat-card {
        padding: 15px;
    }
    h2 {
        font-size: 1.5rem;
    }
}
</style>

<h2><i class="fas fa-flag text-danger me-2"></i>Reports on My Tournaments</h2>
<p class="text-muted">These are complaints/reports submitted by players about your tournaments.</p>
<hr class="border-secondary mb-4">

<!-- Stats -->
<div class="row mb-4 g-3">
    <div class="col-md-4 col-12">
        <div class="stat-card">
            <h3 class="text-danger fw-bold"><?php echo count($reports); ?></h3>
            <small class="text-muted text-uppercase">Total Reports</small>
        </div>
    </div>
    <div class="col-md-4 col-12">
        <div class="stat-card">
            <h3 class="text-warning fw-bold"><?php echo $pending_count; ?></h3>
            <small class="text-muted text-uppercase">Pending</small>
        </div>
    </div>
    <div class="col-md-4 col-12">
        <div class="stat-card">
            <h3 class="text-success fw-bold"><?php echo count(array_filter($reports, fn($r) => $r['status'] === 'resolved')); ?></h3>
            <small class="text-muted text-uppercase">Resolved</small>
        </div>
    </div>
</div>

<?php if ($pending_count > 0): ?>
<div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
    <i class="fas fa-exclamation-triangle me-2 fs-5"></i>
    <div>
        You have <strong><?php echo $pending_count; ?> pending report(s)</strong> that need your attention. Please review and address them.
    </div>
</div>
<?php endif; ?>

<?php if (empty($reports)): ?>
<div class="text-center py-5">
    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
    <h5 class="text-muted">No reports yet!</h5>
    <p class="text-muted">Your tournaments have no complaints. Keep it up! 🎉</p>
</div>
<?php else: ?>

<!-- Filter Tabs -->
<ul class="nav nav-pills mb-4" id="reportTabs">
    <li class="nav-item">
        <a class="nav-link active" href="#" data-filter="all">All (<?php echo count($reports); ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#" data-filter="pending">Pending (<?php echo $pending_count; ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#" data-filter="resolved">Resolved</a>
    </li>
</ul>

<div id="reportsContainer">
<?php foreach ($reports as $r): ?>
    <div class="report-card <?php echo $r['status']; ?> mb-3 p-4" data-status="<?php echo $r['status']; ?>">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h6 class="mb-1">
                    <i class="fas fa-trophy text-warning me-2"></i>
                    <a href="tournament_details.php?id=<?php echo $r['tournament_id']; ?>" class="text-warning text-decoration-none">
                        <?php echo htmlspecialchars($r['tournament_name']); ?>
                    </a>
                </h6>
                <small class="text-muted">
                    <i class="fas fa-user me-1"></i>
                    Reported by: <strong><?php echo htmlspecialchars($r['reporter_username'] ?? 'Anonymous'); ?></strong>
                    &nbsp;|&nbsp;
                    <i class="fas fa-clock me-1"></i>
                    <?php echo date('d M Y, h:i A', strtotime($r['reported_at'])); ?>
                </small>
            </div>
            <span class="badge badge-<?php echo $r['status']; ?> px-3 py-2">
                <?php echo ucfirst($r['status']); ?>
            </span>
        </div>

        <div class="mt-3 p-3 rounded" style="background: rgba(0,0,0,0.3); border-left: 3px solid #dc3545;">
            <small class="text-danger fw-bold text-uppercase d-block mb-1">
                <i class="fas fa-comment-alt me-1"></i>Report Reason
            </small>
            <p class="mb-0 text-light"><?php echo nl2br(htmlspecialchars($r['report_reason'])); ?></p>
        </div>

        <?php if ($r['status'] === 'pending'): ?>
        <div class="mt-3 p-2 rounded" style="background: rgba(255,193,7,0.08); border: 1px solid rgba(255,193,7,0.2);">
            <small class="text-warning">
                <i class="fas fa-info-circle me-1"></i>
                <strong>Action needed:</strong> Review this complaint and fix the issue in your tournament if valid. Admin has been notified.
            </small>
        </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<script>
document.querySelectorAll('#reportTabs .nav-link').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('#reportTabs .nav-link').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        const filter = this.dataset.filter;
        document.querySelectorAll('#reportsContainer .report-card').forEach(card => {
            if (filter === 'all' || card.dataset.status === filter) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
