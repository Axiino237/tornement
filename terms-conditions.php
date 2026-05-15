<?php
require_once 'config/database.php';
require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card bg-dark border-secondary p-4 p-md-5">
                <h1 class="display-5 fw-bold text-white mb-4">Terms & Conditions</h1>
                <p class="text-muted mb-4">Last Updated: <?php echo date('F d, Y'); ?></p>

                <div class="terms-content text-light">
                    <section class="mb-5">
                        <h3 class="text-primary mb-3">1. Acceptance of Terms</h3>
                        <p>By accessing or using FireCrown, you agree to be bound by these Terms and Conditions. If you do not agree to all of these terms, you should not use our services.</p>
                    </section>

                    <section class="mb-5">
                        <h3 class="text-primary mb-3">2. Eligibility</h3>
                        <p>You must be at least 13 years old to use this site. If you are under 18, you may only use this site with the involvement and consent of a parent or guardian.</p>
                    </section>

                    <section class="mb-5">
                        <h3 class="text-primary mb-3">3. User Accounts</h3>
                        <p>You are responsible for maintaining the confidentiality of your account and password. You agree to accept responsibility for all activities that occur under your account.</p>
                    </section>

                    <section class="mb-5">
                        <h3 class="text-primary mb-3">4. Tournament Rules</h3>
                        <p>All participants must follow the specific rules defined for each tournament. Cheating, hacking, or any form of unsportsmanlike conduct will lead to immediate disqualification and potential account suspension.</p>
                    </section>

                    <section class="mb-5">
                        <h3 class="text-primary mb-3">5. Financial Transactions</h3>
                        <p>All wallet recharges and prize distributions are final. We reserve the right to withhold payments if there is suspicion of fraudulent activity or violation of tournament rules.</p>
                    </section>

                    <section class="mb-5">
                        <h3 class="text-primary mb-3">6. Limitation of Liability</h3>
                        <p>FireCrown shall not be liable for any direct, indirect, incidental, or consequential damages resulting from the use or inability to use our services.</p>
                    </section>

                    <section class="mb-5">
                        <h3 class="text-primary mb-3">7. Modifications</h3>
                        <p>We reserve the right to modify these terms at any time. Your continued use of the site after any such changes constitutes your acceptance of the new Terms and Conditions.</p>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
