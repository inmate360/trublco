<?php
session_start();
require_once 'config/database.php';
require_once 'classes/EmailVerification.php';
require_once 'includes/security_headers.php';

SecurityHeaders::set();

$database = new Database();
$db = $database->getConnection();
$emailVerification = new EmailVerification($db);

$token = $_GET['token'] ?? '';
$success = false;
$error = '';

if(empty($token)) {
    $error = 'Invalid verification link';
} else {
    $result = $emailVerification->verifyToken($token);
    
    if($result['success']) {
        $success = true;
        
        // Update session if user is logged in
        if(isset($_SESSION['user_id']) && $_SESSION['user_id'] == $result['user_id']) {
            $_SESSION['email_verified'] = true;
        }
    } else {
        $error = $result['message'];
    }
}

include 'views/header.php';
?>

<div class="page-content">
    <div class="container-narrow">
        <div class="card" style="max-width: 600px; margin: 0 auto; text-align: center;">
            <?php if($success): ?>
                <div style="font-size: 5rem; margin-bottom: 1rem;">✅</div>
                <h2 style="color: var(--success-green); margin-bottom: 1rem;">Email Verified!</h2>
                
                <p style="color: var(--text-gray); font-size: 1.1rem; line-height: 1.8; margin-bottom: 2rem;">
                    Your email has been successfully verified. You now have full access to all Turnpage features!
                </p>
                
                <div style="background: rgba(16, 185, 129, 0.1); padding: 2rem; border-radius: 12px; margin-bottom: 2rem; text-align: left;">
                    <h3 style="color: var(--success-green); margin-bottom: 1rem;">🎉 What's Next?</h3>
                    <ul style="color: var(--text-gray); line-height: 2; margin-left: 1.5rem;">
                        <li>Post unlimited personal ads</li>
                        <li>Send and receive messages</li>
                        <li>Upload photos to your profile</li>
                        <li>Save favorite listings</li>
                        <li>Get notifications for responses</li>
                    </ul>
                </div>
                
                <a href="choose-location.php" class="btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                    Start Browsing →
                </a>
            <?php else: ?>
                <div style="font-size: 5rem; margin-bottom: 1rem;">❌</div>
                <h2 style="color: var(--danger-red); margin-bottom: 1rem;">Verification Failed</h2>
                
                <p style="color: var(--text-gray); font-size: 1.1rem; line-height: 1.8; margin-bottom: 2rem;">
                    <?php echo htmlspecialchars($error); ?>
                </p>
                
                <div style="background: rgba(239, 68, 68, 0.1); padding: 2rem; border-radius: 12px; margin-bottom: 2rem;">
                    <h3 style="color: var(--danger-red); margin-bottom: 1rem;">Possible Reasons:</h3>
                    <ul style="color: var(--text-gray); line-height: 2; margin-left: 1.5rem; text-align: left;">
                        <li>The verification link has expired (24 hours)</li>
                        <li>The link has already been used</li>
                        <li>The link is invalid or corrupted</li>
                    </ul>
                </div>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="verify-email-notice.php" class="btn-primary">
                        Request New Verification Email
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn-primary">
                        Login to Your Account
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>