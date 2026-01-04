<?php
session_start();
require_once 'config/database.php';
require_once 'classes/EmailVerification.php';
require_once 'includes/security_headers.php';

SecurityHeaders::set();

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$emailVerification = new EmailVerification($db);

// Check if already verified
if($emailVerification->isVerified($_SESSION['user_id'])) {
    header('Location: choose-location.php');
    exit();
}

$resent = false;
if(isset($_POST['resend'])) {
    if($emailVerification->sendVerification($_SESSION['user_id'], $_SESSION['email'])) {
        $resent = true;
    }
}

include 'views/header.php';
?>

<div class="page-content">
    <div class="container-narrow">
        <div class="card" style="max-width: 600px; margin: 0 auto; text-align: center;">
            <div style="font-size: 5rem; margin-bottom: 1rem;">📧</div>
            <h2 style="color: var(--primary-blue); margin-bottom: 1rem;">Verify Your Email</h2>
            
            <?php if($resent): ?>
            <div class="alert alert-success">
                ✓ Verification email sent! Check your inbox.
            </div>
            <?php endif; ?>
            
            <p style="color: var(--text-gray); font-size: 1.1rem; line-height: 1.8; margin-bottom: 2rem;">
                We've sent a verification email to:<br>
                <strong style="color: var(--primary-blue);"><?php echo htmlspecialchars($_SESSION['email']); ?></strong>
            </p>
            
            <div style="background: rgba(66, 103, 245, 0.1); padding: 2rem; border-radius: 12px; margin-bottom: 2rem; text-align: left;">
                <h3 style="color: var(--primary-blue); margin-bottom: 1rem;">📝 Next Steps:</h3>
                <ol style="color: var(--text-gray); line-height: 2; margin-left: 1.5rem;">
                    <li>Check your email inbox (and spam/junk folder)</li>
                    <li>Click the verification link in the email</li>
                    <li>Your account will be fully activated</li>
                </ol>
            </div>
            
            <form method="POST" action="" style="margin-bottom: 2rem;">
                <button type="submit" name="resend" class="btn-secondary">
                    📨 Resend Verification Email
                </button>
            </form>
            
            <p style="color: var(--text-gray); font-size: 0.9rem;">
                Didn't receive the email? Check your spam folder or click resend above.
            </p>
            
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                <a href="choose-location.php" class="btn-primary">
                    Continue to Site (Limited Access)
                </a>
                <p style="color: var(--text-gray); font-size: 0.85rem; margin-top: 1rem;">
                    You can browse, but some features require email verification
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>