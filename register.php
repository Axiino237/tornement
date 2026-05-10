<?php
require_once 'config/database.php';
require_once 'includes/header.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }
    
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $device_id = $_POST['device_id'] ?? 'unknown';
    
    // Security Questions
    $questions = $_POST['security_questions'] ?? [];
    $answers = $_POST['security_answers'] ?? [];
    
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all basic fields";
    } elseif (count($questions) < 3 || count($answers) < 3 || empty($answers[0]) || empty($answers[1]) || empty($answers[2])) {
        $error = "Please select and answer 3 security questions";
    } elseif (count(array_unique($questions)) < 3) {
        $error = "Please select 3 DIFFERENT security questions";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if username exists
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already exists";
        } else {
            // Check if email exists
            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Email already exists";
            } else {
                // Check if device already has an ACTIVE account
                $stmt = $db->prepare("SELECT user_id FROM users WHERE device_id = ? AND status = 'active'");
                $stmt->execute([$device_id]);
                if ($stmt->fetch() && $device_id !== 'unknown') {
                    $error = "This device is already linked to an active account. You cannot create multiple accounts.";
                } else {
                    // Insert new user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, email, password, device_id, is_email_verified, status) VALUES (?, ?, ?, ?, TRUE, 'active')");
                    if ($stmt->execute([$username, $email, $hashed_password, $device_id])) {
                        $new_user_id = $db->lastInsertId();
                        
                        // Save Security Questions
                        $stmt_q = $db->prepare("INSERT INTO user_security_questions (user_id, question, answer) VALUES (?, ?, ?)");
                        for ($i = 0; $i < 3; $i++) {
                            $stmt_q->execute([$new_user_id, $questions[$i], strtolower(trim($answers[$i]))]);
                        }
                        
                        log_audit($db, $new_user_id, 'REGISTER', "New user registered with device_id: $device_id");
                        
                        $success = "Registration successful! You can now <a href='login.php' class='text-info'>Sign in</a> to your account.";
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                }
            }
        }
    }
}
?>

<style>
.register-container {
    min-height: calc(100vh - 200px);
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    padding: 2rem;
}

.register-card {
    background: linear-gradient(145deg, #2d2d2d 0%, #1a1a1a 100%);
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    border: 2px solid #0d6efd;
    overflow: hidden;
    width: 100%;
    max-width: 500px;
    position: relative;
}

.register-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, #0d6efd, #0a58ca);
}

.register-header {
    background: rgba(0, 0, 0, 0.3);
    padding: 2rem;
    text-align: center;
    border-bottom: 1px solid rgba(13, 110, 253, 0.2);
}

.register-header h2 {
    color: #fff;
    margin: 0;
    font-size: 2rem;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
}

.register-body {
    padding: 2rem;
}

.form-control {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(13, 110, 253, 0.3);
    color: #fff;
    height: 50px;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    transition: all 0.3s;
}

.form-control:focus {
    background: rgba(0, 0, 0, 0.3);
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.btn-register {
    background: linear-gradient(145deg, #0d6efd 0%, #0a58ca 100%);
    border: none;
    height: 50px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    transition: all 0.3s;
}

.btn-register:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
}

.alert {
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid rgba(220, 53, 69, 0.3);
    color: #fff;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.alert-success {
    background: rgba(25, 135, 84, 0.1);
    border-color: rgba(25, 135, 84, 0.3);
}

.login-link {
    text-align: center;
    margin-top: 1.5rem;
    color: rgba(255, 255, 255, 0.7);
}

.login-link a {
    color: #0d6efd;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.login-link a:hover {
    color: #0a58ca;
    text-decoration: underline;
}

.input-group-text {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(13, 110, 253, 0.3);
    color: #fff;
    border-radius: 8px 0 0 8px;
    padding: 0 1rem;
}

.input-group .form-control {
    border-radius: 0 8px 8px 0;
}

.form-select {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(13, 110, 253, 0.3);
    color: #fff;
    height: 50px;
    border-radius: 8px;
    transition: all 0.3s;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%230d6efd' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 16px 12px;
    padding-right: 2.5rem;
}

.form-select:focus {
    background-color: rgba(0, 0, 0, 0.3);
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    color: #fff;
}

.form-select option {
    background-color: #1a1a1a;
    color: #fff;
}

.security-question-box {
    background: rgba(255, 255, 255, 0.03);
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid rgba(13, 110, 253, 0.1);
    margin-bottom: 1.5rem;
}

@media (max-width: 576px) {
    .register-body {
        padding: 1.5rem;
    }
    .register-header {
        padding: 1.5rem;
    }
    .security-question-box {
        padding: 1rem;
    }
    .register-header h2 {
        font-size: 1.5rem;
    }
    .form-select {
        font-size: 0.9rem;
    }
}

.password-requirements {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 0.5rem;
    padding-left: 1.5rem;
}

.password-requirements li {
    margin-bottom: 0.25rem;
}

.password-requirements li i {
    margin-right: 0.5rem;
    color: #0d6efd;
}
</style>

<div class="register-container">
    <div class="register-card">
        <div class="register-header">
            <h2><i class="fas fa-user-plus me-2"></i>Create Account</h2>
            <p class="text-light mb-0">Join the gaming community today</p>
        </div>
        <div class="register-body">
            <?php if ($error): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="device_id" id="device_id_input">
                
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
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" class="form-control" name="email" placeholder="Email" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" name="password" id="regPassword" placeholder="Password" required>
                        <button type="button" class="input-group-text" id="toggleRegPassword" style="cursor:pointer; border-left:0;">
                            <i class="fas fa-eye" id="regEyeIcon"></i>
                        </button>
                    </div>
                    <ul class="password-requirements list-unstyled">
                        <li><i class="fas fa-check-circle"></i>At least 6 characters long</li>
                        <li><i class="fas fa-check-circle"></i>Include numbers and letters</li>
                    </ul>
                </div>
                
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" name="confirm_password" id="regConfirmPassword" placeholder="Confirm Password" required>
                        <button type="button" class="input-group-text" id="toggleConfirmPassword" style="cursor:pointer; border-left:0;">
                            <i class="fas fa-eye" id="confirmEyeIcon"></i>
                        </button>
                    </div>
                </div>

                <hr class="border-secondary my-4">
                <div class="d-flex align-items-center mb-3">
                    <div style="width: 35px; height: 35px; background: rgba(13, 110, 253, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                        <i class="fas fa-shield-alt text-info"></i>
                    </div>
                    <h5 class="text-light mb-0">Security Questions</h5>
                </div>
                <p class="text-muted small mb-4">Choose 3 questions. These will be used to recover your account if you forget your password.</p>
                
                <?php 
                $sec_questions = [
                    "What is your favorite game?",
                    "What was the name of your first pet?",
                    "What city were you born in?",
                    "What is your mother's maiden name?",
                    "What was the name of your first school?"
                ];
                ?>

                <div class="security-questions-container">
                    <?php for($i = 0; $i < 3; $i++): ?>
                    <div class="security-question-box">
                        <label class="text-info small fw-bold mb-2 d-block text-uppercase" style="letter-spacing: 1px;">
                            <i class="fas fa-question-circle me-1"></i> Question <?php echo $i+1; ?>
                        </label>
                        <select class="form-select mb-3" name="security_questions[]" required>
                            <option value="">Select a security question</option>
                            <?php foreach($sec_questions as $q): ?>
                                <option value="<?php echo htmlspecialchars($q); ?>"><?php echo htmlspecialchars($q); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary">
                                <i class="fas fa-comment-dots text-muted"></i>
                            </span>
                            <input type="text" class="form-control" name="security_answers[]" placeholder="Enter your answer" required>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
                
                <button type="submit" class="btn btn-primary btn-register w-100">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
            </form>
            
            <div class="login-link">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Device ID
    let deviceId = localStorage.getItem('device_id');
    if (!deviceId) {
        deviceId = 'DEV-' + Math.random().toString(36).substring(2, 15) + '-' + Date.now();
        localStorage.setItem('device_id', deviceId);
    }
    document.getElementById('device_id_input').value = deviceId;

    // Password toggle helper
    function togglePassword(inputId, iconId) {
        const pwd = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            pwd.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    document.getElementById('toggleRegPassword').addEventListener('click', () => togglePassword('regPassword', 'regEyeIcon'));
    document.getElementById('toggleConfirmPassword').addEventListener('click', () => togglePassword('regConfirmPassword', 'confirmEyeIcon'));
});
</script>

<?php require_once 'includes/footer.php'; ?> 