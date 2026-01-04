<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$page_title = 'Reset Password';
$error = '';
$success = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';
$valid_token = false;

if (empty($token)) {
    $error = 'Invalid reset link';
} else {
    $db = Database::getConnection();
    
    // Verify token
    $stmt = $db->prepare("SELECT id, username, email, reset_token_expiry FROM users WHERE reset_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $expiry = strtotime($user['reset_token_expiry']);
        if ($expiry > time()) {
            $valid_token = true;
        } else {
            $error = 'This reset link has expired. Please request a new one.';
        }
    } else {
        $error = 'Invalid or expired reset link';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $error = 'Please enter a password';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Hash password and update
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
        
        if ($stmt->execute([$hashed, $token])) {
            $success = 'Your password has been reset successfully!';
            $valid_token = false;
        } else {
            $error = 'Failed to reset password. Please try again.';
        }
    }
}

include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative py-20 overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-pink-500 via-purple-500 to-indigo-600 opacity-10"></div>
    <div class="container mx-auto px-4 relative">
        <div class="max-w-md mx-auto text-center">
            <i class="bi bi-shield-lock text-5xl mb-4 text-gh-accent"></i>
            <h1 class="text-4xl font-bold mb-4 text-gh-fg">Reset Password</h1>
            <p class="text-lg text-gh-muted">Enter your new password</p>
        </div>
    </div>
</div>

<!-- Form Section -->
<div class="container mx-auto px-4 py-12 -mt-8">
    <div class="max-w-md mx-auto">
        <div class="bg-gh-panel border border-gh-border rounded-lg shadow-lg p-8">
            
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-500 bg-opacity-10 border border-red-500 rounded-lg">
                    <p class="text-red-500 flex items-center">
                        <i class="bi bi-exclamation-triangle mr-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-6 p-4 bg-gh-success bg-opacity-10 border border-gh-success rounded-lg">
                    <p class="text-gh-success flex items-center">
                        <i class="bi bi-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </p>
                </div>
                <div class="text-center mt-6">
                    <a href="login.php" class="w-full inline-block bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-600 text-white py-3 rounded-lg font-semibold hover:opacity-90 transition-opacity">
                        <i class="bi bi-box-arrow-in-right mr-2"></i>
                        Go to Login
                    </a>
                </div>
            <?php elseif ($valid_token): ?>
                <form method="POST" action="">
                    <div class="mb-6">
                        <label for="password" class="block text-sm font-medium mb-2 text-gh-fg">
                            New Password
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gh-muted">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required
                                minlength="8"
                                class="w-full pl-10 pr-4 py-3 bg-gh-bg border border-gh-border rounded-lg focus:ring-2 focus:ring-gh-accent focus:border-transparent text-gh-fg"
                                placeholder="Enter new password"
                            >
                        </div>
                        <p class="text-xs text-gh-muted mt-1">At least 8 characters</p>
                    </div>

                    <div class="mb-6">
                        <label for="confirm_password" class="block text-sm font-medium mb-2 text-gh-fg">
                            Confirm New Password
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gh-muted">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                required
                                minlength="8"
                                class="w-full pl-10 pr-4 py-3 bg-gh-bg border border-gh-border rounded-lg focus:ring-2 focus:ring-gh-accent focus:border-transparent text-gh-fg"
                                placeholder="Confirm new password"
                            >
                        </div>
                    </div>

                    <button 
                        type="submit" 
                        class="w-full bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-600 text-white py-3 rounded-lg font-semibold hover:opacity-90 transition-opacity flex items-center justify-center"
                    >
                        <i class="bi bi-check-circle mr-2"></i>
                        Reset Password
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <a href="forgot-password.php" class="text-gh-accent hover:underline">
                        Request a new reset link
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
