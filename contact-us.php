<?php
require_once 'config/database.php';
require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card bg-dark border-secondary p-4 p-md-5">
                <div class="row g-5">
                    <div class="col-md-5">
                        <h1 class="display-5 fw-bold text-white mb-4">Contact Us</h1>
                        <p class="text-muted mb-5">Have questions or need assistance? Our support team is here to help you 24/7.</p>

                        <div class="d-flex mb-4">
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; min-width: 50px;">
                                <i class="fas fa-envelope text-white"></i>
                            </div>
                            <div>
                                <h6 class="text-white mb-1">Email Support</h6>
                                <p class="text-muted mb-0">info@axiino.com</p>
                            </div>
                        </div>

                        <div class="d-flex mb-4">
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; min-width: 50px;">
                                <i class="fas fa-location-dot text-white"></i>
                            </div>
                            <div>
                                <h6 class="text-white mb-1">Our Location</h6>
                                <p class="text-muted mb-0">Erode, Tamil Nadu, India</p>
                            </div>
                        </div>

                        <div class="mt-5">
                            <h6 class="text-white mb-3">Follow Us</h6>
                            <div class="d-flex gap-3">
                                <a href="#" class="btn btn-outline-secondary btn-sm"><i class="fab fa-facebook-f"></i></a>
                                <a href="#" class="btn btn-outline-secondary btn-sm"><i class="fab fa-twitter"></i></a>
                                <a href="#" class="btn btn-outline-secondary btn-sm"><i class="fab fa-instagram"></i></a>
                                <a href="#" class="btn btn-outline-secondary btn-sm"><i class="fab fa-youtube"></i></a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-7">
                        <div class="p-4 rounded shadow-sm" style="background-color: #1e293b;">
                            <h4 class="text-white mb-4">Send a Message</h4>
                            <form action="#" method="POST">
                                <div class="mb-3">
                                    <label class="form-label text-muted">Your Name</label>
                                    <input type="text" class="form-control bg-dark border-secondary text-white" placeholder="John Doe">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted">Email Address</label>
                                    <input type="email" class="form-control bg-dark border-secondary text-white" placeholder="john@example.com">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted">Subject</label>
                                    <input type="text" class="form-control bg-dark border-secondary text-white" placeholder="Support Request">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label text-muted">Message</label>
                                    <textarea class="form-control bg-dark border-secondary text-white" rows="5" placeholder="How can we help you?"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2">Send Message</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
