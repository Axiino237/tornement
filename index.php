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
                    AND (
                        t.tournament_date > NOW() -- Future matches
                        OR (t.room_id IS NOT NULL AND t.room_id != '') -- Past but has Room ID
                        OR t.tournament_date >= NOW() - INTERVAL '12 hours' -- Past, no Room ID, but within grace period
                    )
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
<div class="py-5 text-center mb-5"
    style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-bottom: 1px solid #334155; padding-top: 6rem !important; padding-bottom: 6rem !important;">
    <div class="container">
        <!-- Floating Logo -->
        <div class="mb-5">
            <div class="logo-container mx-auto">
                <img src="/assets/images/games/WhatsApp Image 2026-05-11 at 10.06.26 PM.jpeg" alt="FireCrown Logo"
                    class="hero-logo shadow-lg">
            </div>
            <h4 class="mt-3 fw-bold text-uppercase tracking-wider"
                style="color: #38bdf8; letter-spacing: 2px; text-shadow: 0 0 15px rgba(56, 189, 248, 0.5);">FF KOLARU
                GAMING</h4>
        </div>
        <h1 class="display-4 fw-bold mb-3 text-white hero-title" style="letter-spacing: -1px;">The Ultimate Gaming Arena
        </h1>
        <p class="lead mb-4 mb-md-5 text-muted mx-auto hero-subtitle" style="max-width: 600px;">Join FireCrown to
            compete in thrilling tournaments, climb the leaderboards, and win epic prizes. Your journey starts here.</p>

        <style>
            .logo-container {
                width: 180px;
                height: 180px;
                border-radius: 50%;
                padding: 6px;
                background: linear-gradient(135deg, #0284c7 0%, #38bdf8 100%);
                box-shadow: 0 0 35px rgba(56, 189, 248, 0.6);
                animation: float 4s ease-in-out infinite;
                position: relative;
            }

            .logo-container::after {
                content: '';
                position: absolute;
                top: -5px;
                left: -5px;
                right: -5px;
                bottom: -5px;
                border-radius: 50%;
                background: linear-gradient(135deg, #0284c7 0%, #38bdf8 100%);
                z-index: -1;
                filter: blur(15px);
                opacity: 0.5;
                animation: pulse 2s infinite;
            }

            .hero-logo {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 50%;
                border: 4px solid #0f172a;
            }

            @keyframes float {
                0% {
                    transform: translateY(0px) rotate(0deg);
                }

                50% {
                    transform: translateY(-20px) rotate(2deg);
                }

                100% {
                    transform: translateY(0px) rotate(0deg);
                }
            }

            @keyframes pulse {
                0% {
                    transform: scale(1);
                    opacity: 0.5;
                }

                50% {
                    transform: scale(1.1);
                    opacity: 0.8;
                }

                100% {
                    transform: scale(1);
                    opacity: 0.5;
                }
            }

            .hero-title {
                text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            }
        </style>
        <div class="d-flex justify-content-center gap-3">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="create_tournament.php" class="btn btn-primary btn-lg px-4 shadow-sm">Create Tournament</a>
                    <a href="my_tournaments.php" class="btn btn-outline-light btn-lg px-4 shadow-sm">My Dashboard</a>
                <?php else: ?>
                    <a href="tournaments.php" class="btn btn-primary btn-lg px-4 shadow-sm">Join Tournaments</a>
                    <a href="profile.php" class="btn btn-outline-light btn-lg px-4 shadow-sm">My Profile</a>
                <?php endif; ?>
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
                    <small class="text-uppercase text-muted" style="letter-spacing: 1px; font-weight: 600;">Active
                        Tournaments</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100 p-4 border-0" style="background-color: #1e293b;">
                <div class="card-body">
                    <i class="fas fa-users fa-3x mb-3 text-success"></i>
                    <h2 class="display-5 fw-bold text-white mb-0"><?php echo $total_users; ?></h2>
                    <small class="text-uppercase text-muted" style="letter-spacing: 1px; font-weight: 600;">Registered
                        Gamers</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100 p-4 border-0" style="background-color: #1e293b;">
                <div class="card-body">
                    <i class="fas fa-gamepad fa-3x mb-3 text-info"></i>
                    <h2 class="display-5 fw-bold text-white mb-0"><?php echo $total_participants; ?></h2>
                    <small class="text-uppercase text-muted" style="letter-spacing: 1px; font-weight: 600;">Total
                        Participants</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Tournaments -->
    <div class="d-flex justify-content-between align-items-end mb-4">
        <h3 class="fw-bold mb-0 text-white">Featured Tournaments</h3>
        <a href="tournaments.php" class="btn btn-outline-primary btn-sm px-3">View All <i
                class="fas fa-arrow-right ms-1"></i></a>
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
                        // Add leading slash if it's a relative path
                        if (!filter_var($image_path, FILTER_VALIDATE_URL)) {
                            $image_path = '/' . ltrim($image_path, '/');
                        }
                        ?>
                        <div style="position: relative;">
                            <img src="<?php echo $image_path; ?>" class="card-img-top"
                                alt="<?php echo htmlspecialchars($tournament['game_name']); ?>"
                                style="height: 160px; object-fit: cover; border-top-left-radius: 12px; border-top-right-radius: 12px;"
                                onerror="this.src='https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=800&auto=format&fit=crop'">
                            <span class="badge bg-primary"
                                style="position: absolute; top: 10px; right: 10px; font-size: 0.8rem; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?php echo htmlspecialchars($tournament['game_name']); ?></span>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title text-white mb-3" style="font-size: 1.1rem; line-height: 1.4;">
                                <?php echo htmlspecialchars($tournament['tournament_name']); ?></h5>

                            <div class="mt-auto">
                                <div class="d-flex justify-content-between text-muted small mb-3">
                                    <span><i class="far fa-calendar-alt me-1 text-primary"></i>
                                        <?php echo date('M d', strtotime($tournament['tournament_date'])); ?></span>
                                    <span><i class="fas fa-users me-1 text-primary"></i>
                                        <?php echo $tournament['current_participants']; ?> /
                                        <?php echo $tournament['max_players']; ?></span>
                                </div>

                                <?php if ($tournament['is_paid']): ?>
                                    <div class="p-2 rounded mb-3"
                                        style="background-color: rgba(56, 189, 248, 0.05); border: 1px solid rgba(56, 189, 248, 0.1);">
                                        <div class="d-flex justify-content-between text-white small fw-bold">
                                            <span><i class="fas fa-trophy text-warning me-1"></i>
                                                ₹<?php echo number_format($tournament['winning_prize'], 0); ?></span>
                                            <span class="text-info">Entry:
                                                ₹<?php echo number_format($tournament['registration_fee'], 0); ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="p-2 rounded mb-3 text-center text-success small fw-bold"
                                        style="background-color: rgba(34, 197, 94, 0.05); border: 1px solid rgba(34, 197, 94, 0.1);">
                                        <i class="fas fa-gift me-1"></i> Free Entry
                                    </div>
                                <?php endif; ?>

                                <a href="tournament_details.php?id=<?php echo $tournament['tournament_id']; ?>"
                                    class="btn btn-outline-light w-100 mt-2">View Details</a>
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