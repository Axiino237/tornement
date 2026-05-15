<?php
require_once 'config/database.php';
require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card bg-dark border-secondary p-4 p-md-5">
                <div class="text-center mb-5">
                    <h1 class="display-4 fw-bold text-white">About FireCrown</h1>
                    <div class="bg-primary mx-auto" style="height: 4px; width: 60px;"></div>
                </div>

                <div class="row g-4 align-items-center mb-5">
                    <div class="col-md-6">
                        <h3 class="text-primary mb-3">Who We Are</h3>
                        <p class="text-light">FireCrown is a premier eSports tournament platform designed for passionate gamers. We provide a fair, secure, and competitive environment where players can showcase their skills in their favorite games like Free Fire, BGMI, and more.</p>
                        <p class="text-light">Our mission is to bridge the gap between amateur gaming and professional eSports by offering regular tournaments with exciting prize pools.</p>
                    </div>
                    <div class="col-md-6">
                        <div class="p-4 rounded shadow-sm" style="background-color: #1e293b;">
                            <img src="/assets/images/games/WhatsApp Image 2026-05-11 at 10.06.26 PM.jpeg" alt="FireCrown Logo" class="img-fluid rounded mb-3" style="width: 100px;">
                            <h5 class="text-white">Professional Gaming Infrastructure</h5>
                            <p class="text-muted small">We use state-of-the-art technology to ensure seamless tournament management and fast prize distributions.</p>
                        </div>
                    </div>
                </div>

                <div class="row g-4 text-center">
                    <div class="col-md-4">
                        <div class="p-3">
                            <i class="fas fa-shield-alt fa-3x text-success mb-3"></i>
                            <h5 class="text-white">Fair Play</h5>
                            <p class="text-muted small">Advanced monitoring to ensure zero cheating and a fair competitive environment.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3">
                            <i class="fas fa-bolt fa-3x text-warning mb-3"></i>
                            <h5 class="text-white">Instant Withdrawals</h5>
                            <p class="text-muted small">No more waiting. Get your winnings transferred quickly via UPI.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3">
                            <i class="fas fa-users fa-3x text-info mb-3"></i>
                            <h5 class="text-white">Strong Community</h5>
                            <p class="text-muted small">Join thousands of like-minded gamers and build your team.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
