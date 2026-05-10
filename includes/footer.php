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

    <footer class="bg-transparent text-light py-4 mt-5 border-top border-secondary">
        <div class="container text-center">
            <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> EpicClash. Designed with a modern UI.</p>
        </div>
    </footer>

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