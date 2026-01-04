<?php
session_start();
require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'classes/CSRF.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);
        
        $email = trim($_POST['email']);
        
        if(empty($email)) {
            $error = 'Please enter your email address';
        } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address';
        } else {
            // Check if email exists
            $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($user_data) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) 
                                     VALUES (?, ?, ?, NOW()) 
                                     ON DUPLICATE KEY UPDATE token = ?, expires_at = ?, created_at = NOW()");
                $stmt->execute([$email, $token, $expires, $token, $expires]);
                
                // Create reset link
                $reset_link = 'https://' . $_SERVER['HTTP_HOST'] . '/reset-password.php?token=' . $token;
                
                // Send email
                $to = $email;
                $subject = 'Password Reset Request - Basehit.io';
                $message = "Hello " . htmlspecialchars($user_data['username']) . ",\n\n";
                $message .= "You requested to reset your password. Click the link below to reset it:\n\n";
                $message .= $reset_link . "\n\n";
                $message .= "This link will expire in 1 hour.\n\n";
                $message .= "If you didn't request this, please ignore this email.\n\n";
                $message .= "Best regards,\nBasehit.io Team";
                
                $headers = "From: noreply@basehit.io\r\n";
                $headers .= "Reply-To: support@basehit.io\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();
                
                if(mail($to, $subject, $message, $headers)) {
                    $success = 'Password reset instructions have been sent to your email';
                } else {
                    $error = 'Failed to send email. Please try again later.';
                }
            } else {
                // Don't reveal if email exists or not (security best practice)
                $success = 'If an account exists with that email, password reset instructions have been sent';
            }
        }
    }
}

include 'views/header.php';
?>

<div class="flex min-h-[70vh] items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="mb-8 text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl border-2 border-gh-border bg-gradient-to-br from-gh-panel to-gh-panel2 text-xl font-black shadow-lg">
                <i class="bi bi-key-fill text-gh-accent"></i>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight">Reset your password</h1>
            <p class="mt-2 text-sm text-gh-muted">Enter your email to receive reset instructions</p>
        </div>

        <!-- Reset Card -->
        <div class="rounded-xl border border-gh-border bg-gh-panel shadow-lg">
            <div class="p-6">
                <?php if(!empty($success)): ?>
                <div class="mb-5 rounded-lg border border-gh-border bg-gh-panel2 p-4">
                    <div class="flex items-start gap-3">
                        <i class="bi bi-check-circle-fill text-lg text-gh-success"></i>
                        <div class="flex-1 text-sm">
                            <span class="font-semibold text-gh-success">Success!</span>
                            <span class="text-gh-fg"><?php echo htmlspecialchars($success); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if(!empty($error)): ?>
                <div class="mb-5 rounded-lg border border-gh-border bg-gh-panel2 p-4">
                    <div class="flex items-start gap-3">
                        <i class="bi bi-exclamation-triangle-fill text-lg text-red-500"></i>
                        <div class="flex-1 text-sm">
                            <span class="font-semibold text-red-500">Error</span>
                            <span class="text-gh-fg"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if(empty($success)): ?>
                <form method="POST" action="forgot-password.php" class="space-y-4">
                    <?php echo CSRF::getHiddenInput(); ?>
                    
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gh-fg">Email address</label>
                        <input type="email" name="email" required autofocus 
                               placeholder="you@example.com"
                               class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50">
                    </div>

                    <button type="submit" 
                            class="w-full rounded-lg bg-gh-accent px-4 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110">
                        Send reset instructions
                    </button>
                </form>
                <?php else: ?>
                <a href="login.php" 
                   class="block w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-center text-sm font-semibold transition-colors hover:bg-white/5">
                    Back to login
                </a>
                <?php endif; ?>
            </div>

            <div class="border-t border-gh-border bg-gh-panel2 px-6 py-4 text-center text-sm">
                <span class="text-gh-muted">Remember your password?</span>
                <a href="login.php" class="ml-1 font-semibold text-gh-accent hover:underline">Sign in</a>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
