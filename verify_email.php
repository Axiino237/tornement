<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$message = '';
$messageType = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if token exists and user is not already verified
    $stmt = $db->prepare("SELECT user_id, is_email_verified FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        if ($user['is_email_verified']) {
            $message = "Email is already verified. You can log in.";
            $messageType = "info";
        } else {
            // Verify email
            $updateStmt = $db->prepare("UPDATE users SET is_email_verified = TRUE, verification_token = NULL WHERE user_id = ?");
            if ($updateStmt->execute([$user['user_id']])) {
                $message = "Email successfully verified! You can now log in.";
                $messageType = "success";
            } else {
                $message = "Failed to verify email. Please try again later.";
                $messageType = "danger";
            }
        }
    } else {
        $message = "Invalid or expired verification token.";
        $messageType = "danger";
    }
} else {
    $message = "No verification token provided.";
    $messageType = "warning";
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <div class="card bg-dark text-light border-<?php echo $messageType; ?>">
                <div class="card-header bg-dark border-bottom border-<?php echo $messageType; ?>">
                    <h4>Email Verification</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
