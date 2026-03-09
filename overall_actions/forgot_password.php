<?php
session_start();

// Fix: Use correct path to db.php
if (file_exists(__DIR__ . '/../dont_touch_kinda_stuff/db.php')) {
    require_once __DIR__ . '/../dont_touch_kinda_stuff/db.php';
} elseif (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
} elseif (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} else {
    die('Database connection file not found.');
}

require_once __DIR__ . '/../dont_touch_kinda_stuff/CSRFToken.php';

// Check if PHPMailer is available
$phpmailer_available = false;
$email_error = null;
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    $phpmailer_available = true;
} else {
    $email_error = 'PHPMailer not installed. Install via: composer require phpmailer/phpmailer';
}

$error = '';
$success = '';
$email_sent = false;

// Step 1: Handle email submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] === 'email') {
    // Validate CSRF token
    if (!CSRFToken::validate('forgot_password_csrf')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Search for user in all user tables
            $user_found = false;
            $user_role = '';
            $user_id = 0;
            $user_name = '';

            $roles = ['student' => 'students', 'supervisor' => 'supervisors', 'coordinator' => 'coordinators'];

            foreach ($roles as $role => $table) {
                $stmt = $conn->prepare("SELECT id, name FROM $table WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $user_found = true;
                    $user_role = $role;
                    $user_id = $user['id'];
                    $user_name = $user['name'] ?? 'User';
                    break;
                }
            }

            if (!$user_found) {
                // Don't reveal if email exists (security best practice)
                $success = 'If an account with that email exists, a reset link will be sent shortly. Check your inbox.';
                $email_sent = true;
            } else {
                // Check rate limiting (max 3 attempts per hour)
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count FROM password_reset_tokens
                    WHERE user_id = ? AND user_role = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ");
                $stmt->execute([$user_id, $user_role]);
                $rate_check = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($rate_check['count'] >= 3) {
                    $success = 'Too many reset requests. Please try again in an hour.';
                    $email_sent = true;
                } else {
                    // Generate 6-digit reset code
                    $reset_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $token_hash = password_hash($reset_code, PASSWORD_DEFAULT);
                    $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

                    try {
                        // Insert reset token into database
                        $stmt = $conn->prepare("
                            INSERT INTO password_reset_tokens 
                            (user_id, user_role, reset_code, token_hash, expires_at) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$user_id, $user_role, $reset_code, $token_hash, $expires_at]);
                        $token_id = $conn->lastInsertId();

                        // Try to send email if PHPMailer is available
                        if ($phpmailer_available) {
                            try {
                                require_once __DIR__ . '/../dont_touch_kinda_stuff/EmailService.php';
                                $emailService = new \InternHub\EmailService();
                                
                                $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] 
                                    . dirname($_SERVER['REQUEST_URI']) . '/forgot_password.php?token=' . urlencode($reset_code);
                                
                                $emailSent = $emailService->sendPasswordResetEmail(
                                    $email,
                                    $user_name,
                                    $reset_code,
                                    $resetLink
                                );
                                
                                if ($emailSent) {
                                    $success = 'Password reset link has been sent to your email. Check your inbox for the reset code.';
                                    $_SESSION['reset_email'] = $email;
                                    $_SESSION['reset_role'] = $user_role;
                                    $_SESSION['reset_user_id'] = $user_id;
                                    $email_sent = true;
                                } else {
                                    error_log("Failed to send reset email to {$email}");
                                    $success = 'Password reset requested. Check your email for instructions.';
                                    $_SESSION['reset_email'] = $email;
                                    $_SESSION['reset_role'] = $user_role;
                                    $_SESSION['reset_user_id'] = $user_id;
                                    $email_sent = true;
                                }
                            } catch (Exception $e) {
                                error_log("Email service error: " . $e->getMessage());
                                $success = 'Password reset requested. Check your email for instructions.';
                                $_SESSION['reset_email'] = $email;
                                $_SESSION['reset_role'] = $user_role;
                                $_SESSION['reset_user_id'] = $user_id;
                                $email_sent = true;
                            }
                        } else {
                            // Fallback: store in session for demo
                            $_SESSION['reset_email'] = $email;
                            $_SESSION['reset_role'] = $user_role;
                            $_SESSION['reset_user_id'] = $user_id;
                            $success = 'If an account with that email exists, a reset link will be sent shortly. Check your inbox.';
                            $email_sent = true;
                        }
                    } catch (PDOException $e) {
                        error_log("Password reset database error: " . $e->getMessage());
                        $success = 'If an account with that email exists, a reset link will be sent shortly.';
                        $email_sent = true;
                    }
                }
            }
    }
}
}

// Step 2: Verify code and reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] === 'reset') {
    // Validate CSRF token
    if (!CSRFToken::validate('forgot_password_csrf')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $reset_code = trim($_POST['reset_code'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_role'])) {
            $error = 'Session expired. Please start over.';
        } elseif (empty($reset_code) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all fields.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $user_role = $_SESSION['reset_role'];
            $user_id = $_SESSION['reset_user_id'];

            $stmt = $conn->prepare("
                SELECT id, token_hash FROM password_reset_tokens 
                WHERE user_id = ? AND user_role = ? AND is_used = 0 AND expires_at > NOW()
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$user_id, $user_role]);
            $token_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$token_record || !password_verify($reset_code, $token_record['token_hash'])) {
                $error = 'Invalid or expired reset code.';
            } else {
                try {
                    $user_table = [
                        'student' => 'students',
                        'supervisor' => 'supervisors',
                        'coordinator' => 'coordinators'
                    ][$user_role];

                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("UPDATE $user_table SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$password_hash, $user_id]);

                    $stmt = $conn->prepare("UPDATE password_reset_tokens SET is_used = 1 WHERE id = ?");
                    $stmt->execute([$token_record['id']]);

                    // Get user email and name for confirmation email
                    $user_email = $_SESSION['reset_email'];
                    $stmt = $conn->prepare("SELECT name FROM $user_table WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user_name = $stmt->fetchColumn() ?: 'User';

                    // Send confirmation email if available
                    if ($phpmailer_available) {
                        try {
                            require_once __DIR__ . '/../dont_touch_kinda_stuff/EmailService.php';
                            $emailService = new \InternHub\EmailService();
                            $emailService->sendPasswordChangedEmail($user_email, $user_name);
                        } catch (Exception $e) {
                            error_log("Failed to send confirmation email: " . $e->getMessage());
                        }
                    }

                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_role']);
                    unset($_SESSION['reset_user_id']);

                    $success = 'Password reset successful! You can now login with your new password.';
                } catch (PDOException $e) {
                    $error = 'An error occurred. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — InternHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            500: '#2563eb',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .form-container { transition: opacity 0.3s ease; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-blue-700 text-white hidden md:flex flex-col items-center justify-center p-8 text-center">
            <h1 class="text-3xl font-bold mb-4">InternHub</h1>
            <p class="text-blue-200">Track your internship hours, reports, and progress.</p>
        </aside>
        <main class="flex-1 flex items-center justify-center p-4">
            <div class="w-full max-w-md">
                <div id="step1" class="form-container bg-white p-8 rounded-xl shadow-lg border border-gray-200">
                    <div class="text-center mb-6">
                        <div class="mx-auto bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-key text-blue-600 text-2xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800">Forgot your password?</h2>
                        <p class="text-gray-600 mt-2">Enter your email and we’ll send you a <strong>reset code</strong>.</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="step" value="email">
                        <?php echo CSRFToken::field('forgot_password_csrf'); ?>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email address</label>
                            <input type="email" name="email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="you@domain.com" required>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg transition">
                            Send Reset Code
                        </button>
                    </form>
                    <div class="mt-6 text-center">
                        <a href="auth.php" class="text-sm text-blue-600 hover:underline font-medium">
                            ← Back to login
                        </a>
                    </div>
                </div>
                <div id="step2" class="form-container bg-white p-8 rounded-xl shadow-lg border border-gray-200 <?php echo !$email_sent && empty($success) ? 'hidden' : ''; ?>">
                    <div class="text-center mb-6">
                        <div class="mx-auto bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-lock text-blue-600 text-2xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800">Reset your password</h2>
                        <p class="text-gray-600 mt-2">Check your email for the <strong>6-digit code</strong> we sent you.</p>
                    </div>
                    
                    <?php if (!empty($success)): ?>
                        <div class="mb-4 p-3 bg-green-100 text-green-700 border border-green-300 rounded-lg text-sm">
                            <?= htmlspecialchars($success) ?>
                        </div>
                        <div class="text-center mt-6">
                            <a href="auth.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-lg transition">
                                Return to Login
                            </a>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($error)): ?>
                            <div class="mb-4 p-3 bg-red-100 text-red-700 border border-red-300 rounded-lg text-sm">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="step" value="reset">
                            <?php echo CSRFToken::field('forgot_password_csrf'); ?>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Reset Code</label>
                                <input type="text" name="reset_code" inputmode="numeric" pattern="[0-9]*" placeholder="123456" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" maxlength="6" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                <input type="password" name="new_password" placeholder="••••••••" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                <input type="password" name="confirm_password" placeholder="••••••••" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg transition">
                                Reset Password
                            </button>
                        </form>
                        <div class="mt-6 text-center">
                            <button type="button" id="backBtn" class="text-sm text-blue-600 hover:underline font-medium">
                                ← Back to email step
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        const backBtn = document.getElementById('backBtn');
        if (backBtn) {
            backBtn.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('step2').classList.add('hidden');
                document.getElementById('step1').classList.remove('hidden');
            });
        }
    </script>
</body>
</html>