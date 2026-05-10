<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/logger.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EpicClash Gaming Tournaments</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <!-- Google AdSense -->
    <meta name="google-adsense-account" content="ca-pub-4776704024331789">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4776704024331789"
     crossorigin="anonymous"></script>
</head>
<body class="bg-dark text-light">
    
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <button class="mobile-nav-toggle" id="mobileNavToggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php $is_admin_dir = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false; ?>

    <div class="wrapper">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a class="sidebar-brand" href="/index.php">
                    <i class="fas fa-gamepad me-2"></i>EpicClash
                </a>
            </div>
            
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && !$is_admin_dir ? 'active' : ''; ?>" href="/index.php">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tournaments.php' ? 'active' : ''; ?>" href="/tournaments.php">
                        <i class="fas fa-trophy"></i> Tournaments
                    </a>
                </li>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'create_tournament.php' ? 'active' : ''; ?>" href="/create_tournament.php">
                        <i class="fas fa-plus-circle"></i> Create Tournament
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my_tournaments.php' ? 'active' : ''; ?>" href="/my_tournaments.php">
                        <i class="fas fa-list"></i> My Tournaments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my_reports.php' ? 'active' : ''; ?>" href="/my_reports.php">
                        <i class="fas fa-flag text-danger"></i> My Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'recharge.php' ? 'active' : ''; ?>" href="/recharge.php">
                        <i class="fas fa-wallet text-info"></i> Recharge Wallet
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'withdraw.php' ? 'active' : ''; ?>" href="/withdraw.php">
                        <i class="fas fa-money-bill-wave text-warning"></i> Withdraw Funds
                    </a>
                </li>
                <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li class="nav-item mt-3 mb-2 px-4 text-uppercase text-muted" style="font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 600;">Admin Area</li>
                <li class="nav-item">
                    <a class="nav-link text-warning <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && $is_admin_dir ? 'active' : ''; ?>" href="/admin/index.php">
                        <i class="fas fa-shield-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-warning <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' && $is_admin_dir ? 'active' : ''; ?>" href="/admin/users.php">
                        <i class="fas fa-users-cog"></i> Manage Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-warning <?php echo basename($_SERVER['PHP_SELF']) == 'games.php' && $is_admin_dir ? 'active' : ''; ?>" href="/admin/games.php">
                        <i class="fas fa-gamepad"></i> Manage Games
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-warning <?php echo basename($_SERVER['PHP_SELF']) == 'recharges.php' && $is_admin_dir ? 'active' : ''; ?>" href="/admin/recharges.php">
                        <i class="fas fa-file-invoice-dollar"></i> Manage Recharges
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-warning <?php echo basename($_SERVER['PHP_SELF']) == 'withdrawals.php' && $is_admin_dir ? 'active' : ''; ?>" href="/admin/withdrawals.php">
                        <i class="fas fa-hand-holding-usd"></i> Manage Withdrawals
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-warning <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' && $is_admin_dir ? 'active' : ''; ?>" href="/admin/settings.php">
                        <i class="fas fa-cog"></i> Payment Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-warning <?php echo basename($_SERVER['PHP_SELF']) == 'audit_logs.php' && $is_admin_dir ? 'active' : ''; ?>" href="/admin/audit_logs.php">
                        <i class="fas fa-history"></i> Audit & Service Logs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-warning <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' && $is_admin_dir ? 'active' : ''; ?>" href="/admin/reports.php">
                        <i class="fas fa-flag"></i> Reports
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>
            </ul>
            
            <div class="sidebar-footer p-3" style="border-top: 1px solid rgba(255,255,255,0.05);">
                <ul class="sidebar-nav p-0 m-0">
                    <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="/profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>" href="/login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-success <?php echo basename($_SERVER['PHP_SELF']) == 'register.php' ? 'active' : ''; ?>" href="/register.php">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
        
        <div class="main-content">
            <!-- Top Banner Ad -->
            <div class="container mt-3 mb-3 text-center ad-container">
                <ins class="adsbygoogle"
                     style="display:block"
                     data-ad-client="ca-pub-4776704024331789"
                     data-ad-slot="TOP_BANNER_SLOT_ID"
                     data-ad-format="auto"
                     data-full-width-responsive="true"></ins>
                <script>
                     (adsbygoogle = window.adsbygoogle || []).push({});
                </script>
            </div>
            
            <div class="container fade-in-up"> 