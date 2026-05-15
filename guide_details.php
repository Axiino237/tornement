<?php
require_once 'config/database.php';
require_once 'includes/header.php';

if (!isset($_GET['slug'])) {
    header("Location: guides.php");
    exit();
}

$slug = $_GET['slug'];
$database = new Database();
$db = $database->getConnection();

$stmt = $db->prepare("SELECT * FROM guides WHERE slug = ?");
$stmt->execute([$slug]);
$guide = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guide) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Guide not found.</div></div>";
    require_once 'includes/footer.php';
    exit();
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
                    <li class="breadcrumb-item"><a href="guides.php" class="text-decoration-none">Guides</a></li>
                    <li class="breadcrumb-item active text-muted" aria-current="page"><?php echo htmlspecialchars($guide['category']); ?></li>
                </ol>
            </nav>

            <img src="<?php echo htmlspecialchars($guide['image_url']); ?>" class="img-fluid rounded shadow-lg mb-5 w-100" style="max-height: 400px; object-fit: cover;" alt="<?php echo htmlspecialchars($guide['title']); ?>">

            <h1 class="display-5 fw-bold text-white mb-3"><?php echo htmlspecialchars($guide['title']); ?></h1>
            <div class="d-flex align-items-center mb-5 text-muted small">
                <span class="me-3"><i class="far fa-calendar-alt me-1"></i> <?php echo date('M d, Y', strtotime($guide['created_at'])); ?></span>
                <span class="badge bg-primary"><?php echo htmlspecialchars($guide['category']); ?></span>
            </div>

            <div class="guide-content text-light" style="font-size: 1.1rem; line-height: 1.8;">
                <?php echo nl2br(htmlspecialchars($guide['content'])); ?>
                
                <hr class="my-5 border-secondary">
                
                <div class="p-4 rounded" style="background-color: #1e293b;">
                    <h5 class="text-white mb-3">Ready to test your skills?</h5>
                    <p class="text-muted">Join our upcoming tournaments and win exciting prizes!</p>
                    <a href="tournaments.php" class="btn btn-primary">Browse Tournaments</a>
                </div>
            </div>
            
            <!-- Sidebar/Related Section (Mobile friendly) -->
            <div class="mt-5 pt-5 border-top border-secondary">
                <h4 class="text-white mb-4">Related Guides</h4>
                <div class="row">
                    <?php
                    $stmt = $db->prepare("SELECT * FROM guides WHERE category = ? AND slug != ? LIMIT 2");
                    $stmt->execute([$guide['category'], $slug]);
                    $related = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($related as $rel):
                    ?>
                    <div class="col-md-6 mb-3">
                        <a href="guide_details.php?slug=<?php echo $rel['slug']; ?>" class="text-decoration-none">
                            <div class="card bg-dark border-secondary h-100">
                                <div class="card-body p-3">
                                    <h6 class="text-white mb-0"><?php echo htmlspecialchars($rel['title']); ?></h6>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
