<?php
session_start();
require_once 'config/database.php';
require_once 'classes/CSRF.php';
require_once 'classes/RateLimiter.php';
require_once 'classes/SpamProtection.php';
require_once 'classes/InputSanitizer.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

// Check for maintenance mode
if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

// Check if user already logged in
if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Check if TOS was accepted
if(!isset($_SESSION['tos_accepted']) || !$_SESSION['tos_accepted']) {
    header('Location: register-tos.php');
    exit();
}

// Initialize security classes
$rateLimiter = new RateLimiter($db);
$spamProtection = new SpamProtection($db);

$error = '';
$success = '';

// Generate Math CAPTCHA
function generateMathCaptcha() {
    $num1 = rand(1, 20);
    $num2 = rand(1, 20);
    $operators = ['+', '-', '×'];
    $operator = $operators[array_rand($operators)];
    
    switch($operator) {
        case '+':
            $answer = $num1 + $num2;
            break;
        case '-':
            // Make sure result is positive
            if($num1 < $num2) {
                $temp = $num1;
                $num1 = $num2;
                $num2 = $temp;
            }
            $answer = $num1 - $num2;
            break;
        case '×':
            // Keep numbers smaller for multiplication
            $num1 = rand(2, 12);
            $num2 = rand(2, 12);
            $answer = $num1 * $num2;
            break;
    }
    
    return [
        'question' => "$num1 $operator $num2",
        'answer' => $answer
    ];
}

// Generate new captcha if not exists
if(!isset($_SESSION['math_captcha'])) {
    $_SESSION['math_captcha'] = generateMathCaptcha();
}

// Check if IP is blocked
$ipCheck = $spamProtection->isBlocked();
if($ipCheck) {
    $error = 'Access denied. ' . ($ipCheck['reason'] ?? 'Please contact support.');
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && !$ipCheck) {
    // Check CSRF token
    if(!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
        $_SESSION['math_captcha'] = generateMathCaptcha(); // Regenerate captcha
    } else {
        // Check rate limit
        $identifier = RateLimiter::getIdentifier();
        $rateCheck = $rateLimiter->checkLimit($identifier, 'register', 5, 3600); // 5 attempts per hour
        
        if(!$rateCheck['allowed']) {
            $minutes = ceil($rateCheck['retry_after'] / 60);
            $error = "Too many registration attempts. Please try again in {$minutes} minutes.";
            $_SESSION['math_captcha'] = generateMathCaptcha(); // Regenerate captcha
        } else {
            // Validate Math CAPTCHA
            $captcha_answer = trim($_POST['captcha_answer'] ?? '');
            $correct_answer = $_SESSION['math_captcha']['answer'] ?? '';
            
            if(empty($captcha_answer) || $captcha_answer != $correct_answer) {
                $error = 'Incorrect answer to the math question. Please try again.';
                $_SESSION['math_captcha'] = generateMathCaptcha(); // Regenerate captcha
            } else {
                // Sanitize inputs
                $username = InputSanitizer::cleanUsername($_POST['username']);
                $email = InputSanitizer::cleanEmail($_POST['email']);
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                $age_verification = isset($_POST['age_verification']);
                
                // Validation
                if(empty($username) || empty($email) || empty($password)) {
                    $error = 'All fields are required';
                } elseif($username === false) {
                    $error = 'Invalid username. Only letters, numbers, underscore and hyphen allowed.';
                } elseif($email === false) {
                    $error = 'Invalid email address';
                } elseif(!$age_verification) {
                    $error = 'You must confirm you are 18 years or older';
                } elseif(strlen($username) < 3 || strlen($username) > 30) {
                    $error = 'Username must be between 3 and 30 characters';
                } elseif(strlen($password) < 8) {
                    $error = 'Password must be at least 8 characters';
                } elseif(!preg_match('/[A-Z]/', $password)) {
                    $error = 'Password must contain at least one uppercase letter';
                } elseif(!preg_match('/[a-z]/', $password)) {
                    $error = 'Password must contain at least one lowercase letter';
                } elseif(!preg_match('/[0-9]/', $password)) {
                    $error = 'Password must contain at least one number';
                } elseif($password !== $confirm_password) {
                    $error = 'Passwords do not match';
                } elseif(InputSanitizer::detectXSS($username . $email)) {
                    $error = 'Invalid characters detected';
                    $spamProtection->blockIP(RateLimiter::getClientIP(), 'XSS attempt', 86400);
                } else {
                    // Check if username already exists
                    $query = "SELECT id FROM users WHERE username = :username LIMIT 1";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $username);
                    $stmt->execute();
                    
                    if($stmt->rowCount() > 0) {
                        $error = 'Username already taken';
                        $_SESSION['math_captcha'] = generateMathCaptcha(); // Regenerate captcha
                    } else {
                        // Check if email already exists
                        $query = "SELECT id FROM users WHERE email = :email LIMIT 1";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':email', $email);
                        $stmt->execute();
                        
                        if($stmt->rowCount() > 0) {
                            $error = 'Email already registered';
                            $_SESSION['math_captcha'] = generateMathCaptcha(); // Regenerate captcha
                        } else {
                            // Create account
                            $hashed_password = password_hash($password, PASSWORD_ARGON2ID);
                            $tos_accepted_at = $_SESSION['tos_accepted_at'] ?? date('Y-m-d H:i:s');
                            $ip = RateLimiter::getClientIP();
                            
                            $query = "INSERT INTO users (username, email, password, tos_accepted_at, registration_ip, is_verified, created_at) 
                                      VALUES (:username, :email, :password, :tos_accepted_at, :ip, 1, NOW())";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':username', $username);
                            $stmt->bindParam(':email', $email);
                            $stmt->bindParam(':password', $hashed_password);
                            $stmt->bindParam(':tos_accepted_at', $tos_accepted_at);
                            $stmt->bindParam(':ip', $ip);
                            
                                if($stmt->execute()) {
	                                $user_id = $db->lastInsertId();

                                    // Create Bitcoin Wallet
                                    try {
                                        require_once 'classes/BitcoinService.php';
                                        $bitcoin = new BitcoinService($db);
                                        $bitcoin->getUserWallet($user_id);
                                    } catch(Exception $e) {
                                        error_log("Wallet creation error: " . $e->getMessage());
                                    }
	                                
	                                // Log in the user
                                $_SESSION['user_id'] = $user_id;
                                $_SESSION['username'] = $username;
                                $_SESSION['email'] = $email;
                                
                                // Clear session data
                                unset($_SESSION['tos_accepted']);
                                unset($_SESSION['tos_accepted_at']);
                                unset($_SESSION['math_captcha']);
                                
                                // Destroy CSRF token
                                CSRF::destroyToken();
                                
                                // Redirect to dashboard
                                header('Location: dashboard.php');
                                exit();
                            } else {
                                $error = 'Registration failed. Please try again.';
                                $_SESSION['math_captcha'] = generateMathCaptcha(); // Regenerate captcha
                            }
                        }
                    }
                }
                
                // Regenerate captcha on any error
                if($error) {
                    $_SESSION['math_captcha'] = generateMathCaptcha();
                }
            }
        }
    }
}

// Generate new CSRF token
$csrf_token = CSRF::getToken();

include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-pink-900 to-red-900 py-12">
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDEzNGg3djdjMCAxLjEwNS0uODk1IDItMiAyaC0zYy0xLjEwNSAwLTItLjg5NS0yLTJ2LTd6bTAtMTRoN3Y3YzAgMS4xMDUtLjg5NSAyLTIgMmgtM2MtMS4xMDUgMC0yLS44OTUtMi0ydi03eiIvPjwvZz48L2c+PC9zdmc+')] opacity-10"></div>
    
    <div class="relative mx-auto max-w-6xl px-4 text-center">
        <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-white/10 text-3xl backdrop-blur-sm">
            <i class="bi bi-person-plus text-pink-300"></i>
        </div>
        <h1 class="mb-3 bg-gradient-to-r from-white via-pink-200 to-white bg-clip-text text-4xl font-bold text-transparent md:text-5xl">
            Join trubl Today
        </h1>
        <p class="text-base text-pink-200">Create your account and start connecting locally</p>
    </div>
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-12">
    <div class="mx-auto max-w-md px-4">

        <!-- TOS Accepted Badge -->
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-green-500/30 bg-green-500/10 p-4">
            <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
            <div>
                <p class="font-semibold text-green-400">Terms Accepted</p>
                <p class="text-sm text-green-300">Thank you for reviewing and accepting our Terms of Service.</p>
            </div>
        </div>

        <?php if($error): ?>
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/10 p-4">
            <i class="bi bi-exclamation-circle-fill mt-0.5 text-red-500"></i>
            <div>
                <p class="font-semibold text-red-400">Registration Error</p>
                <p class="text-sm text-red-300"><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <div class="rounded-lg border border-gh-border bg-gh-panel p-8">
            <h2 class="mb-6 text-center text-2xl font-bold text-white">Create Your Account</h2>

            <form method="POST" action="register.php" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <!-- Username -->
                <div>
                    <label class="mb-2 block text-sm font-semibold text-white">
                        <i class="bi bi-person-fill mr-1 text-gh-accent"></i>
                        Username
                    </label>
                    <input 
                        type="text" 
                        name="username" 
                        required
                        autofocus
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                        placeholder="Choose a username"
                        autocomplete="username"
                    >
                    <p class="mt-1.5 text-xs text-gh-muted">3-30 characters, letters, numbers, underscore and hyphen</p>
                </div>

                <!-- Email -->
                <div>
                    <label class="mb-2 block text-sm font-semibold text-white">
                        <i class="bi bi-envelope-fill mr-1 text-gh-accent"></i>
                        Email Address
                    </label>
                    <input 
                        type="email" 
                        name="email" 
                        required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                        placeholder="you@example.com"
                        autocomplete="email"
                    >
                </div>

                <!-- Password -->
                <div>
                    <label class="mb-2 block text-sm font-semibold text-white">
                        <i class="bi bi-lock-fill mr-1 text-gh-accent"></i>
                        Password
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            name="password" 
                            id="password"
                            required
                            class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 pr-12 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                            placeholder="Create a strong password"
                            autocomplete="new-password"
                        >
                        <button 
                            type="button" 
                            onclick="togglePassword('password', 'password-icon')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gh-muted hover:text-white"
                        >
                            <i class="bi bi-eye-fill" id="password-icon"></i>
                        </button>
                    </div>
                    <div class="mt-1.5 space-y-1 text-xs text-gh-muted">
                        <div>• At least 8 characters</div>
                        <div>• One uppercase, one lowercase letter</div>
                        <div>• At least one number</div>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label class="mb-2 block text-sm font-semibold text-white">
                        <i class="bi bi-lock-fill mr-1 text-gh-accent"></i>
                        Confirm Password
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            name="confirm_password" 
                            id="confirm_password"
                            required
                            class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 pr-12 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                            placeholder="Re-enter your password"
                            autocomplete="new-password"
                        >
                        <button 
                            type="button" 
                            onclick="togglePassword('confirm_password', 'confirm-icon')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gh-muted hover:text-white"
                        >
                            <i class="bi bi-eye-fill" id="confirm-icon"></i>
                        </button>
                    </div>
                </div>

                <!-- Math CAPTCHA -->
                <div class="rounded-lg border-2 border-purple-500/50 bg-purple-500/10 p-4">
                    <label class="mb-2 block text-sm font-semibold text-white">
                        <i class="bi bi-calculator-fill mr-1 text-purple-400"></i>
                        Anti-Bot Verification
                    </label>
                    <div class="mb-3 rounded-lg bg-gh-bg p-3 text-center">
                        <p class="text-sm text-gh-muted">What is:</p>
                        <p class="text-2xl font-bold text-white"><?php echo $_SESSION['math_captcha']['question']; ?> = ?</p>
                    </div>
                    <input 
                        type="number" 
                        name="captcha_answer" 
                        required
                        class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-center text-white transition-colors focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20"
                        placeholder="Enter your answer"
                        autocomplete="off"
                    >
                    <p class="mt-1.5 text-xs text-purple-300">Solve this simple math problem to prove you're human</p>
                </div>

                <!-- Age Verification -->
                <div class="rounded-lg border border-gh-border bg-gh-bg p-4">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input 
                            type="checkbox" 
                            name="age_verification" 
                            required
                            class="mt-0.5 h-4 w-4 rounded border-gh-border bg-gh-panel text-gh-accent focus:ring-2 focus:ring-gh-accent/20"
                        >
                        <span class="flex-1 text-sm">
                            <span class="font-semibold text-white">I confirm that I am 18 years or older</span>
                            <span class="block text-gh-muted">This site is for adults only</span>
                        </span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit"
                    class="group flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-3 font-bold text-white shadow-lg transition-all hover:brightness-110"
                >
                    <i class="bi bi-person-plus"></i>
                    Create Account
                    <i class="bi bi-arrow-right transition-transform group-hover:translate-x-1"></i>
                </button>
            </form>

            <!-- Login Link -->
            <div class="mt-6 text-center">
                <p class="text-sm text-gh-muted">
                    Already have an account? 
                    <a href="login.php" class="font-semibold text-gh-accent hover:text-gh-success">
                        Sign in
                    </a>
                </p>
            </div>
        </div>

        <!-- Security Notice -->
        <div class="mt-6 rounded-lg border border-blue-500/30 bg-blue-500/10 p-4">
            <div class="flex items-start gap-3">
                <i class="bi bi-shield-lock-fill mt-0.5 text-blue-400"></i>
                <div class="text-sm text-blue-300">
                    <p class="mb-1 font-semibold">Your Privacy is Protected</p>
                    <p class="text-xs text-blue-200">We use advanced encryption and never share your personal information. Your data is safe with us.</p>
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
function togglePassword(fieldId, iconId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(iconId);
    
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
