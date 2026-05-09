<?php
require_once 'config/database.php';
require_once 'includes/header.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("SELECT user_id, username, password, is_email_verified, role, status FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = "Your account has been deactivated. Please contact support.";
            } elseif (!$user['is_email_verified']) {
                $stmt = $db->prepare("SELECT verification_token FROM users WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
                $vtoken = $stmt->fetchColumn();
                $error = "Please verify your email address before logging in. <br><a href='verify_email.php?token=" . $vtoken . "' class='alert-link'>Click here to verify</a>";
            } else {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                log_audit($db, $user['user_id'], 'LOGIN', "User logged in successfully");
                header("Location: index.php");
                exit();
            }
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>

<style>
.login-container {
    min-height: calc(100vh - 150px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.login-card {
    background: rgba(30, 41, 59, 0.7) !important;
    backdrop-filter: blur(15px);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    width: 100%;
    max-width: 420px;
    overflow: hidden;
}

.login-header {
    padding: 2.5rem 2rem 1.5rem;
    text-align: center;
}

.login-header .icon-box {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #38bdf8 0%, #0284c7 100%);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 1.8rem;
    color: white;
    box-shadow: 0 10px 20px rgba(2, 132, 199, 0.3);
}

.login-body {
    padding: 0 2rem 2.5rem;
}

.btn-login {
    height: 52px;
    font-size: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}
</style>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div class="icon-box">
                <i class="fas fa-gamepad"></i>
            </div>
            <h2 class="fw-bold">Welcome Back</h2>
            <p class="text-muted mb-0">Sign in to continue your gaming journey</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" class="form-control" name="username" placeholder="Username" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" name="password" placeholder="Password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>
            
            <div class="register-link">
                Don't have an account? <a href="register.php">Register now</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 