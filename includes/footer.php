            </div> <!-- /container -->
            
            <!-- Bottom Banner Ad -->
            <div class="container mt-4 mb-3 text-center ad-container">
                <ins class="adsbygoogle"
                     style="display:block"
                     data-ad-client="ca-pub-4776704024331789"
                     data-ad-slot="BOTTOM_BANNER_SLOT_ID"
                     data-ad-format="auto"
                     data-full-width-responsive="true"></ins>
                <script>
                     (adsbygoogle = window.adsbygoogle || []).push({});
                </script>
            </div>
            
        </div> <!-- /main-content -->
    </div> <!-- /wrapper -->

    <footer class="bg-dark text-light py-5 mt-5 border-top border-secondary">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <h5 class="text-white fw-bold mb-4"><i class="fas fa-gamepad text-primary me-2"></i>FireCrown</h5>
                    <p class="text-muted small">The ultimate platform for competitive gaming. Join tournaments, climb the leaderboard, and win amazing prizes.</p>
                    <div class="d-flex gap-3 mt-4">
                        <a href="https://www.instagram.com/ff_kolaru_gaming._" target="_blank" class="text-muted hover-primary"><i class="fab fa-instagram"></i></a>
                        <a href="https://wa.me/917200752528" target="_blank" class="text-muted hover-primary"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6">
                    <h6 class="text-white fw-bold mb-4">Quick Links</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="/index.php" class="text-muted text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="/tournaments.php" class="text-muted text-decoration-none">Tournaments</a></li>
                        <li class="mb-2"><a href="/guides.php" class="text-muted text-decoration-none">Gaming Guides</a></li>
                        <li class="mb-2"><a href="/about-us.php" class="text-muted text-decoration-none">About Us</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <h6 class="text-white fw-bold mb-4">Support & Legal</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="/contact-us.php" class="text-muted text-decoration-none">Contact Us</a></li>
                        <li class="mb-2"><a href="/privacy-policy.php" class="text-muted text-decoration-none">Privacy Policy</a></li>
                        <li class="mb-2"><a href="/terms-conditions.php" class="text-muted text-decoration-none">Terms & Conditions</a></li>
                        <li class="mb-2"><a href="/index.php" class="text-muted text-decoration-none">FAQ</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <h6 class="text-white fw-bold mb-4">Contact Info</h6>
                    <ul class="list-unstyled small text-muted">
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i> ffkolarugaming52528@gmail.com</li>
                        <li class="mb-2"><i class="fas fa-location-dot me-2"></i> Chennai, Tamil Nadu, India</li>
                        <li class="mb-2"><i class="fas fa-phone me-2"></i> +91 72007 52528</li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4 border-secondary opacity-25">
            
            <div class="text-center">
                <p class="text-muted small mb-0">&copy; <?php echo date('Y'); ?> FireCrown. All Rights Reserved.</p>
                <p class="text-muted extra-small">Developed with <i class="fas fa-heart text-danger"></i> by <a href="http://www.Axiino.com" target="_blank" class="text-decoration-none text-primary fw-bold">Axiino</a></p>
            </div>
        </div>
    </footer>

    <style>
        .hover-primary:hover { color: #38bdf8 !important; }
        .extra-small { font-size: 0.75rem; }
    </style>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Sidebar Toggle Logic for Mobile
        document.addEventListener("DOMContentLoaded", function() {
            const toggleBtn = document.getElementById('mobileNavToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            function toggleSidebar() {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            }
            
            if (toggleBtn && sidebar && overlay) {
                toggleBtn.addEventListener('click', toggleSidebar);
                overlay.addEventListener('click', toggleSidebar);
            }
        });
    </script>
</body>
</html> 