<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$redirect = $_GET['redirect'] ?? 'dashboard.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username_or_email = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if(empty($username_or_email) || empty($password)) {
        $error = 'Please enter your username/email and password';
    } else {
        // Find user by username or email
        $query = "SELECT * FROM users WHERE username = :identifier OR email = :identifier LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([':identifier' => $username_or_email]);
        $user = $stmt->fetch();
        
        if($user && password_verify($password, $user['password'])) {
            // Check if account is active
            if($user['is_banned']) {
                $error = 'Your account has been suspended. Please contact support.';
            } else {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['is_admin'] = $user['is_admin'] ?? 0; // For admin features
                
                // Update last login
                $update = "UPDATE users SET last_login = NOW() WHERE id = :id";
                $stmt2 = $db->prepare($update);
                $stmt2->execute([':id' => $user['id']]);
                
                // Remember me functionality
                if($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                    // Store token in database (implement later)
                }
                
                // Redirect
                header('Location: ' . $redirect);
                exit();
            }
        } else {
            $error = 'Invalid username/email or password';
        }
    }
}

include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-pink-900 to-red-900 py-12">
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDEzNGg3djdjMCAxLjEwNS0uODk1IDItMiAyaC0zYy0xLjEwNSAwLTItLjg5NS0yLTJ2LTd6bTAtMTRoN3Y3YzAgMS4xMDUtLjg5NSAyLTIgMmgtM2MtMS4xMDUgMC0yLS44OTUtMi0ydi03eiIvPjwvZz48L2c+PC9zdmc+')] opacity-10"></div>
    
    <div class="relative mx-auto max-w-6xl px-4 text-center">
        <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-white/10 text-3xl backdrop-blur-sm">
            <i class="bi bi-box-arrow-in-right text-pink-300"></i>
        </div>
        <h1 class="mb-3 bg-gradient-to-r from-white via-pink-200 to-white bg-clip-text text-4xl font-bold text-transparent md:text-5xl">
            Welcome Back
        </h1>
        <p class="text-base text-pink-200">Login to your trubl account</p>
    </div>
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-12">
    <div class="mx-auto max-w-md px-4">

        <?php if($error): ?>
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/10 p-4">
            <i class="bi bi-exclamation-circle-fill mt-0.5 text-red-500"></i>
            <div>
                <p class="font-semibold text-red-400">Login Failed</p>
                <p class="text-sm text-red-300"><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if(isset($_GET['verified'])): ?>
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-green-500/30 bg-green-500/10 p-4">
            <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
            <div>
                <p class="font-semibold text-green-400">Email Verified!</p>
                <p class="text-sm text-green-300">Your account has been verified. You can now login.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Login Form -->
        <div class="rounded-lg border border-gh-border bg-gh-panel p-8">
            <h2 class="mb-6 text-center text-2xl font-bold text-white">Login to Account</h2>

            <form method="POST" class="space-y-4">
                
                <!-- Username or Email -->
                <div>
                    <label class="mb-2 block text-sm font-semibold text-white">
                        <i class="bi bi-person-fill mr-1 text-gh-accent"></i>
                        Username or Email
                    </label>
                    <input 
                        type="text" 
                        name="username_or_email" 
                        required
                        value="<?php echo isset($_POST['username_or_email']) ? htmlspecialchars($_POST['username_or_email']) : ''; ?>"
                        class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                        placeholder="Enter your username or email"
                        autocomplete="username"
                    >
                </div>

                <!-- Password -->
                <div>
                    <label class="mb-2 flex items-center justify-between text-sm font-semibold text-white">
                        <span>
                            <i class="bi bi-lock-fill mr-1 text-gh-accent"></i>
                            Password
                        </span>
                        <a href="forgot-password.php" class="text-xs font-normal text-gh-accent hover:underline">
                            Forgot password?
                        </a>
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            name="password" 
                            id="password"
                            required
                            class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 pr-12 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                        >
                        <button 
                            type="button" 
                            onclick="togglePassword()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gh-muted hover:text-white"
                        >
                            <i class="bi bi-eye-fill" id="password-icon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center gap-3">
                    <input 
                        type="checkbox" 
                        name="remember" 
                        id="remember"
                        class="h-4 w-4 rounded border-gh-border bg-gh-panel text-gh-accent focus:ring-2 focus:ring-gh-accent/20"
                    >
                    <label for="remember" class="text-sm text-gh-muted">
                        Keep me logged in
                    </label>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit"
                    class="group flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-3 font-bold text-white shadow-lg transition-all hover:brightness-110"
                >
                    <i class="bi bi-box-arrow-in-right"></i>
                    Login to Account
                    <i class="bi bi-arrow-right transition-transform group-hover:translate-x-1"></i>
                </button>
            </form>

            <!-- Divider -->
            <div class="my-6 flex items-center gap-3">
                <div class="h-px flex-1 bg-gh-border"></div>
                <span class="text-xs text-gh-muted">OR</span>
                <div class="h-px flex-1 bg-gh-border"></div>
            </div>

            <!-- Social Login Buttons -->
            <div class="space-y-3">
                <button class="flex w-full items-center justify-center gap-3 rounded-lg border border-gh-border bg-gh-bg px-6 py-3 font-semibold text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-google text-xl"></i>
                    Continue with Google
                </button>
                <button class="flex w-full items-center justify-center gap-3 rounded-lg border border-gh-border bg-gh-bg px-6 py-3 font-semibold text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-facebook text-xl"></i>
                    Continue with Facebook
                </button>
            </div>

            <!-- Register Link -->
            <div class="mt-6 text-center">
                <p class="text-sm text-gh-muted">
                    Don't have an account? 
                    <a href="register.php" class="font-semibold text-gh-accent hover:text-gh-success">
                        Sign up free
                    </a>
                </p>
            </div>
        </div>

        <!-- Security Notice -->
        <div class="mt-6 rounded-lg border border-blue-500/30 bg-blue-500/10 p-4">
            <div class="flex items-start gap-3">
                <i class="bi bi-shield-lock-fill mt-0.5 text-blue-400"></i>
                <div class="text-sm text-blue-300">
                    <p class="mb-1 font-semibold">Your Security Matters</p>
                    <p class="text-xs text-blue-200">We use industry-standard encryption to protect your data. Never share your password with anyone.</p>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="mt-8 grid gap-3 sm:grid-cols-2">
            <a href="how-it-works.php" class="group flex items-center gap-3 rounded-lg border border-gh-border bg-gh-panel p-4 transition-all hover:border-gh-accent">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-green-500 to-emerald-600 text-white">
                    <i class="bi bi-question-circle-fill"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-white">How It Works</h3>
                    <p class="text-xs text-gh-muted">Learn the basics</p>
                </div>
                <i class="bi bi-arrow-right text-gh-muted transition-transform group-hover:translate-x-1"></i>
            </a>

            <a href="contact.php" class="group flex items-center gap-3 rounded-lg border border-gh-border bg-gh-panel p-4 transition-all hover:border-gh-accent">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-yellow-500 to-orange-600 text-white">
                    <i class="bi bi-headset"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-white">Need Help?</h3>
                    <p class="text-xs text-gh-muted">Contact support</p>
                </div>
                <i class="bi bi-arrow-right text-gh-muted transition-transform group-hover:translate-x-1"></i>
            </a>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const field = document.getElementById('password');
    const icon = document.getElementById('password-icon');
    
    if(field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('bi-eye-fill');
        icon.classList.add('bi-eye-slash-fill');
    } else {
        field.type = 'password';
        icon.classList.remove('bi-eye-slash-fill');
        icon.classList.add('bi-eye-fill');
    }
}
</script>

<?php include 'views/footer.php'; ?>
