<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Fetch game images from database
$stmt = $db->query("SELECT game_name, image_url FROM games");
$db_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
$game_images = [];
foreach ($db_games as $g) {
    $game_images[strtolower(trim($g['game_name']))] = $g['image_url'];
}

// Get featured tournaments
$stmt = $db->query("SELECT t.*, u.username as owner_name, 
                    (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.tournament_id) as current_participants
                    FROM tournaments t 
                    JOIN users u ON t.owner_id = u.user_id 
                    WHERE t.status = 'active' 
                    ORDER BY t.created_at DESC LIMIT 4");
$featured_tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user info if logged in
$user_info = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

</div> <!-- Close container from header -->

<!-- Hero Section -->
<div class="py-5 text-center mb-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-bottom: 1px solid #334155; padding-top: 6rem !important; padding-bottom: 6rem !important;">
    <div class="container">
        <h1 class="display-4 fw-bold mb-3 text-white hero-title" style="letter-spacing: -1px;">The Ultimate Gaming Arena</h1>
        <p class="lead mb-4 mb-md-5 text-muted mx-auto hero-subtitle" style="max-width: 600px;">Join EpicClash to compete in thrilling tournaments, climb the leaderboards, and win epic prizes. Your journey starts here.</p>
        <div class="d-flex justify-content-center gap-3">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="create_tournament.php" class="btn btn-primary btn-lg px-4 shadow-sm">Create Tournament</a>
                <a href="my_tournaments.php" class="btn btn-outline-light btn-lg px-4 shadow-sm">My Dashboard</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-primary btn-lg px-4 shadow-sm">Start Playing Now</a>
                <a href="login.php" class="btn btn-outline-light btn-lg px-4 shadow-sm">Login to Account</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container mb-5">
    <?php
    // Get total tournaments
    $stmt = $db->query("SELECT COUNT(*) as total FROM tournaments WHERE status = 'active'");
    $total_tournaments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total users
    $stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total participants
    $stmt = $db->query("SELECT COUNT(*) as total FROM tournament_participants");
    $total_participants = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    ?>
    <div class="row text-center mb-5 justify-content-center">
        <div class="col-md-4 mb-4">
            <div class="card h-100 p-4 border-0" style="background-color: #1e293b;">
                <div class="card-body">
                    <i class="fas fa-trophy fa-3x mb-3" style="color: #38bdf8;"></i>
                    <h2 class="display-5 fw-bold text-white mb-0"><?php echo $total_tournaments; ?></h2>
                    <small class="text-uppercase text-muted" style="letter-spacing: 1px; font-weight: 600;">Active Tournaments</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100 p-4 border-0" style="background-color: #1e293b;">
                <div class="card-body">
                    <i class="fas fa-users fa-3x mb-3 text-success"></i>
                    <h2 class="display-5 fw-bold text-white mb-0"><?php echo $total_users; ?></h2>
                    <small class="text-uppercase text-muted" style="letter-spacing: 1px; font-weight: 600;">Registered Gamers</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100 p-4 border-0" style="background-color: #1e293b;">
                <div class="card-body">
                    <i class="fas fa-gamepad fa-3x mb-3 text-info"></i>
                    <h2 class="display-5 fw-bold text-white mb-0"><?php echo $total_participants; ?></h2>
                    <small class="text-uppercase text-muted" style="letter-spacing: 1px; font-weight: 600;">Total Participants</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Tournaments -->
    <div class="d-flex justify-content-between align-items-end mb-4">
        <h3 class="fw-bold mb-0 text-white">Featured Tournaments</h3>
        <a href="tournaments.php" class="btn btn-outline-primary btn-sm px-3">View All <i class="fas fa-arrow-right ms-1"></i></a>
    </div>

    <div class="row">
        <?php if (empty($featured_tournaments)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center py-5 border-0" style="background-color: #1e293b;">
                    <i class="fas fa-info-circle fa-3x mb-3 d-block text-info opacity-50"></i>
                    <h5 class="text-white">No tournaments right now</h5>
                    <p class="text-muted mb-0">Be the first to create one and invite your friends!</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($featured_tournaments as $tournament): ?>
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <?php 
                        $game_name = strtolower(trim($tournament['game_name']));
                        $image_path = isset($game_images[$game_name]) ? $game_images[$game_name] : 'https://via.placeholder.com/800x400?text=Game+Image';
                        ?>
                        <div style="position: relative;">
                            <img src="<?php echo $image_path; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($tournament['game_name']); ?>" style="height: 160px; object-fit: cover; border-top-left-radius: 12px; border-top-right-radius: 12px;">
                            <span class="badge bg-primary" style="position: absolute; top: 10px; right: 10px; font-size: 0.8rem; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?php echo htmlspecialchars($tournament['game_name']); ?></span>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title text-white mb-3" style="font-size: 1.1rem; line-height: 1.4;"><?php echo htmlspecialchars($tournament['tournament_name']); ?></h5>
                            
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between text-muted small mb-3">
                                    <span><i class="far fa-calendar-alt me-1 text-primary"></i> <?php echo date('M d', strtotime($tournament['tournament_date'])); ?></span>
                                    <span><i class="fas fa-users me-1 text-primary"></i> <?php echo $tournament['current_participants']; ?> / <?php echo $tournament['max_players']; ?></span>
                                </div>
                                
                                <?php if ($tournament['is_paid']): ?>
                                    <div class="p-2 rounded mb-3" style="background-color: rgba(56, 189, 248, 0.05); border: 1px solid rgba(56, 189, 248, 0.1);">
                                        <div class="d-flex justify-content-between text-white small fw-bold">
                                            <span><i class="fas fa-trophy text-warning me-1"></i> ₹<?php echo number_format($tournament['winning_prize'], 0); ?></span>
                                            <span class="text-info">Entry: ₹<?php echo number_format($tournament['registration_fee'], 0); ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="p-2 rounded mb-3 text-center text-success small fw-bold" style="background-color: rgba(34, 197, 94, 0.05); border: 1px solid rgba(34, 197, 94, 0.1);">
                                        <i class="fas fa-gift me-1"></i> Free Entry
                                    </div>
                                <?php endif; ?>
                                
                                <a href="tournament_details.php?id=<?php echo $tournament['tournament_id']; ?>" class="btn btn-outline-light w-100 mt-2">View Details</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<!-- Open a dummy container because footer.php will close it -->
<div>

<?php require_once 'includes/footer.php'; ?> 