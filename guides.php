<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Fetch guides
$stmt = $db->query("SELECT * FROM guides ORDER BY created_at DESC");
$guides = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
    <div class="container text-center">
        <h1 class="display-4 fw-bold text-white mb-3">Gaming Guides</h1>
        <p class="lead text-muted mx-auto" style="max-width: 700px;">Master your favorite games with our expert tips, strategies, and tournament guides.</p>
    </div>
</div>

<div class="container py-5">
    <div class="row">
        <?php if (empty($guides)): ?>
            <div class="col-12 text-center py-5">
                <div class="alert alert-dark border-secondary">
                    <h5 class="text-white">No guides available yet.</h5>
                    <p class="text-muted">Stay tuned for expert gaming tips!</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($guides as $guide): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 bg-dark border-secondary shadow-sm hover-transform">
                        <img src="<?php echo htmlspecialchars($guide['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($guide['title']); ?>" style="height: 200px; object-fit: cover;">
                        <div class="card-body">
                            <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($guide['category']); ?></span>
                            <h5 class="card-title text-white mb-3"><?php echo htmlspecialchars($guide['title']); ?></h5>
                            <p class="card-text text-muted small"><?php echo substr(strip_tags($guide['content']), 0, 120); ?>...</p>
                        </div>
                        <div class="card-footer bg-transparent border-secondary">
                            <a href="guide_details.php?slug=<?php echo $guide['slug']; ?>" class="btn btn-outline-light btn-sm w-100">Read More</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.hover-transform {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.hover-transform:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.5) !important;
}
</style>

<?php require_once 'includes/footer.php'; ?>
