<?php
require_once 'config/database.php';
require_once 'includes/header.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error   = '';
$success = false;
$step    = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$username = trim($_POST['username'] ?? '');

$database = new Database();
$db = $database->getConnection();

$questions = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }

    if ($step == 1) {
        // Find user and their questions
        if (empty($username)) {
            $error = "Please enter your username.";
        } else {
            $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "Username not found.";
            } else {
                $stmt_q = $db->prepare("SELECT question FROM user_security_questions WHERE user_id = ?");
                $stmt_q->execute([$user['user_id']]);
                $questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

                if (count($questions) < 3) {
                    $error = "No security questions set for this account. Please contact admin.";
                } else {
                    $step = 2; // Move to verification
                }
            }
        }
    } elseif ($step == 2) {
        // Verify answers
        $answers = $_POST['answers'] ?? [];
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $stmt_q = $db->prepare("SELECT id, question, answer FROM user_security_questions WHERE user_id = ? ORDER BY id ASC");
            $stmt_q->execute([$user['user_id']]);
            $db_questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

            $correct = true;
            foreach ($db_questions as $index => $dq) {
                $user_answer = strtolower(trim($answers[$index] ?? ''));
                if ($user_answer !== $dq['answer']) {
                    $correct = false;
                    break;
                }
            }

            if ($correct) {
                // Reset password
                $hashed_password = password_hash('Login@123', PASSWORD_DEFAULT);
                $stmt_upd = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt_upd->execute([$hashed_password, $user['user_id']]);
                
                log_audit($db, $user['user_id'], 'PASSWORD_RESET', "Password reset via security questions");
                $success = true;
            } else {
                $error = "Incorrect answers to security questions. Please try again.";
                // Reload questions for step 2
                $stmt_q = $db->prepare("SELECT question FROM user_security_questions WHERE user_id = ?");
                $stmt_q->execute([$user['user_id']]);
                $questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);
                $step = 2;
            }
        } else {
            $error = "An error occurred. Please try again.";
            $step = 1;
        }
    }
}
?>

<style>
.forgot-container {
    min-height: calc(100vh - 150px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}
.forgot-card {
    background: rgba(30, 41, 59, 0.85);
    backdrop-filter: blur(16px);
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.1);
    box-shadow: 0 25px 50px rgba(0,0,0,0.5);
    width: 100%;
    max-width: 500px;
}
.forgot-header {
    background: linear-gradient(135deg, #1e3a5f 0%, #0f2340 100%);
    padding: 2rem;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    border-radius: 20px 20px 0 0;
}
.success-box {
    text-align: center;
    padding: 2.5rem 2rem;
}
.success-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2.2rem;
    color: white;
}
.password-reveal {
    background: rgba(34,197,94,0.1);
    border: 1px solid rgba(34,197,94,0.3);
    border-radius: 10px;
    padding: 1rem 1.5rem;
    margin: 1rem 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #4ade80;
    font-family: monospace;
}
.security-question-box {
    background: rgba(255, 255, 255, 0.03);
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid rgba(13, 110, 253, 0.1);
    margin-bottom: 1.5rem;
}
.form-control {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(13, 110, 253, 0.3);
    color: #fff;
    height: 50px;
    border-radius: 8px;
}
.form-control:focus {
    background: rgba(0, 0, 0, 0.3);
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    color: #fff;
}

@media (max-width: 576px) {
    .forgot-header {
        padding: 1.5rem;
    }
    .p-4 {
        padding: 1.5rem !important;
    }
    .security-question-box {
        padding: 1rem;
    }
    h3 {
        font-size: 1.4rem;
    }
    .password-reveal {
        font-size: 1.2rem;
        padding: 0.8rem;
    }
}
</style>

<div class="forgot-container">
    <div class="forgot-card">
        <div class="forgot-header">
            <h3 class="fw-bold mb-1">Account Recovery</h3>
            <p class="text-muted mb-0">Reset your password using security questions</p>
        </div>

        <div class="p-4">
            <?php if ($success): ?>
                <div class="success-box">
                    <div class="success-icon"><i class="fas fa-check"></i></div>
                    <h4 class="fw-bold text-success">Password Reset Successful!</h4>
                    <p class="text-muted">Your password has been reset to:</p>
                    <div class="password-reveal">Login@123</div>
                    <p class="small text-warning mt-2">Please login and change your password immediately.</p>
                    <a href="login.php" class="btn btn-primary w-100 mt-3">Go to Login</a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger mb-4"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="step" value="<?php echo $step; ?>">
                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">

                    <?php if ($step == 1): ?>
                        <div class="mb-4">
                            <label class="form-label text-light">Enter Username</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary" name="username" placeholder="Username" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Find My Account</button>
                    <?php else: ?>
                        <p class="text-info mb-4"><i class="fas fa-user-check me-2"></i>Hello <strong><?php echo htmlspecialchars($username); ?></strong>, please answer your security questions:</p>
                        
                        <div class="security-questions-container">
                            <?php foreach($questions as $index => $q): ?>
                                <div class="security-question-box">
                                    <label class="text-info small fw-bold mb-2 d-block text-uppercase" style="letter-spacing: 1px;">
                                        <i class="fas fa-question-circle me-1"></i> Question <?php echo $index + 1; ?>
                                    </label>
                                    <p class="text-light mb-3" style="font-size: 1.05rem;"><?php echo htmlspecialchars($q['question']); ?></p>
                                    <div class="input-group">
                                        <span class="input-group-text bg-dark border-secondary">
                                            <i class="fas fa-comment-dots text-muted"></i>
                                        </span>
                                        <input type="text" class="form-control" name="answers[]" placeholder="Enter your answer" required>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn btn-success w-100">Verify & Reset Password</button>
                        <div class="text-center mt-3">
                            <a href="forgot_password.php" class="text-muted small">Try different username</a>
                        </div>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
