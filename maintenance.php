<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check if user is admin
$is_admin = false;
if(isset($_SESSION['user_id'])) {
    $query = "SELECT is_admin FROM users WHERE id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch();
    $is_admin = $user && $user['is_admin'];
}

// Handle signature submission
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signature_data'])) {
    header('Content-Type: application/json');
    
    try {
        // Create table if it doesn't exist
        $createTable = "CREATE TABLE IF NOT EXISTS signing_wall (
            id INT AUTO_INCREMENT PRIMARY KEY,
            signature_data LONGTEXT NOT NULL,
            name VARCHAR(100) DEFAULT 'Anonymous',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45)
        )";
        $db->exec($createTable);
        
        $signature_data = $_POST['signature_data'];
        $name = $_POST['name'] ?? 'Anonymous';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $insert = "INSERT INTO signing_wall (signature_data, name, ip_address) VALUES (:data, :name, :ip)";
        $stmt = $db->prepare($insert);
        $stmt->bindParam(':data', $signature_data);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':ip', $ip);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Signature saved!']);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Get existing signatures
$signatures = [];
try {
    $query = "SELECT * FROM signing_wall ORDER BY created_at DESC LIMIT 50";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $signatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    // Table might not exist yet
}

// Get maintenance settings
$settings = [];
try {
    $query = "SELECT setting_key, setting_value FROM site_settings 
              WHERE setting_key IN ('maintenance_mode', 'maintenance_title', 'maintenance_message', 
              'coming_soon_mode', 'coming_soon_message', 'coming_soon_launch_date', 'allow_admin_access')";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch(PDOException $e) {
    error_log("Error fetching maintenance settings: " . $e->getMessage());
}

$maintenance_mode = ($settings['maintenance_mode'] ?? '0') == '1';
$coming_soon_mode = ($settings['coming_soon_mode'] ?? '0') == '1';
$allow_admin = ($settings['allow_admin_access'] ?? '1') == '1';
$launch_date = $settings['coming_soon_launch_date'] ?? null;

// Determine which mode to show
$show_coming_soon = $coming_soon_mode;
$show_maintenance = $maintenance_mode && !$coming_soon_mode;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coming Soon - Lustifieds | Adult Classifieds & Hookups</title>
    <meta name="description" content="Lustifieds is launching soon! The ultimate platform for adult classifieds, hookups, and connections.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'gh-bg': '#0d1117',
                        'gh-panel': '#161b22',
                        'gh-border': '#30363d',
                        'gh-fg': '#e6edf3',
                        'gh-muted': '#7d8590',
                        'gh-accent': '#1f6feb',
                        'gh-success': '#238636',
                    }
                }
            }
        }
    </script>
    
    <style>
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .animated-gradient {
            background: linear-gradient(-45deg, #667eea, #764ba2, #f093fb, #f5576c);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }
        
        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .pulse-glow {
            animation: pulse-glow 2s ease-in-out infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(102, 126, 234, 0.5); }
            50% { box-shadow: 0 0 40px rgba(102, 126, 234, 0.8), 0 0 60px rgba(118, 75, 162, 0.6); }
        }
        
        .feature-card {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-8px);
        }
        
        /* Shimmer effect */
        .feature-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.05),
                transparent
            );
            transform: rotate(45deg);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% {
                transform: translateX(-100%) translateY(-100%) rotate(45deg);
            }
            100% {
                transform: translateX(100%) translateY(100%) rotate(45deg);
            }
        }
        
        .feature-card:hover::before {
            animation: shimmer 1.5s infinite;
        }
        
        .countdown-item {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .countdown-item::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.08),
                transparent
            );
            animation: shimmer 4s infinite;
        }
        
        /* Icon pulse animation */
        .icon-pulse {
            animation: icon-pulse 2s ease-in-out infinite;
        }
        
        @keyframes icon-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        /* Signing canvas */
        #signingCanvas {
            cursor: crosshair;
            touch-action: none;
            border-radius: 12px;
        }
        
        .signature-item {
            transition: all 0.3s ease;
        }
        
        .signature-item:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body class="bg-gh-bg text-gh-fg antialiased">

    <!-- Admin Notice (if admin) -->
    <?php if($is_admin): ?>
    <div class="fixed left-4 right-4 top-4 z-50 mx-auto max-w-2xl rounded-lg border border-yellow-500 bg-yellow-500/10 p-4 backdrop-blur-lg sm:left-auto sm:right-4 sm:w-auto">
        <div class="flex items-center gap-3">
            <i class="bi bi-shield-fill-check text-2xl text-yellow-500"></i>
            <div>
                <p class="font-bold text-yellow-500">Admin View</p>
                <p class="text-sm text-yellow-400">You're viewing the coming soon page. Regular users will see this.</p>
            </div>
            <a href="index.php" class="ml-auto rounded-lg bg-yellow-500 px-4 py-2 font-semibold text-white transition hover:bg-yellow-600">
                Go to Dashboard
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="relative min-h-screen overflow-hidden">
        
        <!-- Animated Background -->
        <div class="animated-gradient absolute inset-0 opacity-10"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_120%,rgba(102,126,234,0.1),transparent)]"></div>
        
        <!-- Content Container -->
        <div class="relative z-10 flex min-h-screen flex-col">
            
            <!-- Header -->
            <header class="px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <div class="flex items-center justify-between">
                        <h1 class="text-3xl font-bold sm:text-4xl lg:text-5xl" style="font-family: 'Pacifico', cursive;">
                            <span class="gradient-text">Lustifieds</span>
                        </h1>
                        <div class="flex items-center gap-3">
                            <a href="https://status.lustifieds.com" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel/50 px-3 py-2 text-sm font-semibold text-gh-fg backdrop-blur transition-all hover:border-green-500 hover:text-green-500 sm:px-4">
                                <i class="bi bi-activity"></i>
                                <span class="hidden sm:inline">Status</span>
                            </a>
                            <a href="https://x.com/Lustifieds" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel/50 px-3 py-2 text-sm font-semibold text-gh-fg backdrop-blur transition-all hover:border-blue-500 hover:text-blue-500 sm:px-4">
                                <i class="bi bi-twitter-x"></i>
                                <span class="hidden sm:inline">Follow Us</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Hero Section -->
            <main class="flex flex-1 items-center px-4 py-12 sm:px-6 lg:px-8">
                <div class="mx-auto w-full max-w-7xl">
                    
                    <!-- Main Message -->
                    <div class="mb-12 text-center">
                        <div class="floating mb-8 inline-block">
                            <div class="pulse-glow rounded-full bg-gradient-to-r from-purple-600 to-pink-600 p-6">
                                <i class="bi bi-rocket-takeoff-fill text-6xl text-white sm:text-7xl"></i>
                            </div>
                        </div>
                        
                        <h2 class="mb-4 text-4xl font-bold sm:text-5xl lg:text-6xl">
                            Something <span class="gradient-text">Amazing</span> is Coming
                        </h2>
                        
                        <p class="mx-auto mb-8 max-w-2xl text-lg text-gh-muted sm:text-xl">
                            <?php echo htmlspecialchars($settings['coming_soon_message'] ?? 
                                'The ultimate adult classifieds platform is launching soon. Connect, explore, and find exactly what you\'re looking for.'); ?>
                        </p>

                        <!-- Countdown Timer (if launch date set) -->
                        <?php if($launch_date): ?>
                        <div class="mb-12">
                            <div class="mx-auto grid max-w-3xl grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                                <div class="countdown-item rounded-xl p-4 sm:p-6">
                                    <div class="relative z-10 text-3xl font-bold text-gh-fg sm:text-4xl" id="days">00</div>
                                    <div class="relative z-10 text-sm text-gh-muted sm:text-base">Days</div>
                                </div>
                                <div class="countdown-item rounded-xl p-4 sm:p-6">
                                    <div class="relative z-10 text-3xl font-bold text-gh-fg sm:text-4xl" id="hours">00</div>
                                    <div class="relative z-10 text-sm text-gh-muted sm:text-base">Hours</div>
                                </div>
                                <div class="countdown-item rounded-xl p-4 sm:p-6">
                                    <div class="relative z-10 text-3xl font-bold text-gh-fg sm:text-4xl" id="minutes">00</div>
                                    <div class="relative z-10 text-sm text-gh-muted sm:text-base">Minutes</div>
                                </div>
                                <div class="countdown-item rounded-xl p-4 sm:p-6">
                                    <div class="relative z-10 text-3xl font-bold text-gh-fg sm:text-4xl" id="seconds">00</div>
                                    <div class="relative z-10 text-sm text-gh-muted sm:text-base">Seconds</div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Email Notify Form -->
                        <div class="mx-auto max-w-md">
                            <form class="flex flex-col gap-3 sm:flex-row" onsubmit="return subscribeEmail(event)">
                                <input type="email" id="notify-email" required
                                       placeholder="Enter your email for updates"
                                       class="flex-1 rounded-lg border border-gh-border bg-gh-panel/50 px-4 py-3 text-gh-fg backdrop-blur placeholder:text-gh-muted focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/50">
                                <button type="submit"
                                        class="rounded-lg bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-3 font-bold text-white shadow-lg transition-all hover:scale-105 hover:shadow-xl">
                                    Notify Me
                                </button>
                            </form>
                            <p class="mt-2 text-xs text-gh-muted">We'll notify you when we launch. No spam, promise!</p>
                        </div>
                    </div>

                    <!-- Signing Wall Section -->
                    <div class="mb-12">
                        <div class="mx-auto max-w-4xl">
                            <div class="rounded-xl border border-gh-border bg-gh-panel/50 p-6 backdrop-blur sm:p-8">
                                <div class="mb-6 text-center">
                                    <h3 class="mb-2 flex items-center justify-center gap-2 text-2xl font-bold sm:text-3xl">
                                        <i class="bi bi-pencil-fill text-pink-500"></i>
                                        <span class="gradient-text">Sign Our Wall</span>
                                    </h3>
                                    <p class="text-gh-muted">Leave your mark! Draw your signature and be part of our launch story.</p>
                                </div>

                                <!-- Drawing Canvas -->
                                <div class="mb-6">
                                    <div class="mb-4 overflow-hidden rounded-xl border-2 border-gh-border bg-white">
                                        <canvas id="signingCanvas" width="800" height="300"></canvas>
                                    </div>
                                    
                                    <!-- Drawing Controls -->
                                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <label class="text-sm font-semibold text-gh-fg">Color:</label>
                                            <button onclick="changeColor('#000000')" class="h-8 w-8 rounded-full bg-black ring-2 ring-gh-border transition hover:scale-110"></button>
                                            <button onclick="changeColor('#1f6feb')" class="h-8 w-8 rounded-full bg-blue-500 ring-2 ring-gh-border transition hover:scale-110"></button>
                                            <button onclick="changeColor('#f5576c')" class="h-8 w-8 rounded-full bg-red-500 ring-2 ring-gh-border transition hover:scale-110"></button>
                                            <button onclick="changeColor('#238636')" class="h-8 w-8 rounded-full bg-green-500 ring-2 ring-gh-border transition hover:scale-110"></button>
                                            <button onclick="changeColor('#8957e5')" class="h-8 w-8 rounded-full bg-purple-500 ring-2 ring-gh-border transition hover:scale-110"></button>
                                            <button onclick="changeColor('#f093fb')" class="h-8 w-8 rounded-full bg-pink-500 ring-2 ring-gh-border transition hover:scale-110"></button>
                                        </div>
                                        
                                        <div class="flex gap-2">
                                            <button onclick="clearCanvas()" 
                                                    class="rounded-lg border border-gh-border bg-gh-bg px-4 py-2 text-sm font-semibold text-gh-fg transition hover:border-red-500 hover:text-red-500">
                                                <i class="bi bi-trash"></i> Clear
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Name Input & Submit -->
                                    <div class="flex flex-col gap-3 sm:flex-row">
                                        <input type="text" id="signerName" placeholder="Your name (optional)" maxlength="50"
                                               class="flex-1 rounded-lg border border-gh-border bg-gh-panel px-4 py-3 text-gh-fg placeholder:text-gh-muted focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/50">
                                        <button onclick="saveSignature()" 
                                                class="rounded-lg bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-3 font-bold text-white shadow-lg transition-all hover:scale-105 hover:shadow-xl">
                                            <i class="bi bi-check-circle"></i> Sign the Wall
                                        </button>
                                    </div>
                                </div>

                                <!-- Existing Signatures -->
                                <?php if(count($signatures) > 0): ?>
                                <div>
                                    <h4 class="mb-4 text-lg font-bold text-gh-fg">
                                        <i class="bi bi-people-fill text-purple-500"></i>
                                        Recent Signatures (<?php echo count($signatures); ?>)
                                    </h4>
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                                        <?php foreach($signatures as $sig): ?>
                                        <div class="signature-item rounded-lg border border-gh-border bg-gh-bg p-2">
                                            <img src="<?php echo htmlspecialchars($sig['signature_data']); ?>" 
                                                 alt="Signature" 
                                                 class="mb-2 h-24 w-full rounded object-contain bg-white">
                                            <div class="text-center">
                                                <p class="truncate text-xs font-semibold text-gh-fg">
                                                    <?php echo htmlspecialchars($sig['name']); ?>
                                                </p>
                                                <p class="text-xs text-gh-muted">
                                                    <?php echo date('M j', strtotime($sig['created_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Features Section -->
                    <div class="mb-12">
                        <h3 class="mb-8 text-center text-2xl font-bold sm:text-3xl">
                            Powerful Features Coming Your Way
                        </h3>
                        
                        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            
                            <!-- Feature 1: Personals/Classifieds -->
                            <div class="feature-card group rounded-xl border border-gh-border bg-gh-panel/50 p-6 backdrop-blur">
                                <div class="relative z-10 mb-4 inline-flex rounded-lg bg-gradient-to-br from-pink-600/20 to-rose-600/20 p-3 ring-1 ring-pink-500/30">
                                    <i class="icon-pulse bi bi-heart-fill text-3xl text-pink-400"></i>
                                </div>
                                <h4 class="relative z-10 mb-2 text-xl font-bold text-gh-fg">Personals & Classifieds</h4>
                                <p class="relative z-10 text-gh-muted">Post and browse local hookup ads with advanced filtering by location, preferences, and interests.</p>
                            </div>

                            <!-- Feature 2: Stories -->
                            <div class="feature-card group rounded-xl border border-gh-border bg-gh-panel/50 p-6 backdrop-blur">
                                <div class="relative z-10 mb-4 inline-flex rounded-lg bg-gradient-to-br from-purple-600/20 to-violet-600/20 p-3 ring-1 ring-purple-500/30">
                                    <i class="icon-pulse bi bi-book-fill text-3xl text-purple-400"></i>
                                </div>
                                <h4 class="relative z-10 mb-2 text-xl font-bold text-gh-fg">Lusterotic Stories</h4>
                                <p class="relative z-10 text-gh-muted">Share and read real hookup experiences. Browse stories by category, trending, and most popular.</p>
                            </div>

                            <!-- Feature 3: Marketplace -->
                            <div class="feature-card group rounded-xl border border-gh-border bg-gh-panel/50 p-6 backdrop-blur">
                                <div class="relative z-10 mb-4 inline-flex rounded-lg bg-gradient-to-br from-orange-600/20 to-amber-600/20 p-3 ring-1 ring-orange-500/30">
                                    <i class="icon-pulse bi bi-shop text-3xl text-orange-400"></i>
                                </div>
                                <h4 class="relative z-10 mb-2 text-xl font-bold text-gh-fg">Creator Marketplace</h4>
                                <p class="relative z-10 text-gh-muted">Buy and sell exclusive content from verified creators. Monetize your following with custom content.</p>
                            </div>

                            <!-- Feature 4: Messaging -->
                            <div class="feature-card group rounded-xl border border-gh-border bg-gh-panel/50 p-6 backdrop-blur">
                                <div class="relative z-10 mb-4 inline-flex rounded-lg bg-gradient-to-br from-blue-600/20 to-cyan-600/20 p-3 ring-1 ring-blue-500/30">
                                    <i class="icon-pulse bi bi-chat-dots-fill text-3xl text-blue-400"></i>
                                </div>
                                <h4 class="relative z-10 mb-2 text-xl font-bold text-gh-fg">Real-Time Messaging</h4>
                                <p class="relative z-10 text-gh-muted">Instant messaging with photo sharing, emojis, and read receipts. Connect privately and securely.</p>
                            </div>

                            <!-- Feature 5: Profiles -->
                            <div class="feature-card group rounded-xl border border-gh-border bg-gh-panel/50 p-6 backdrop-blur">
                                <div class="relative z-10 mb-4 inline-flex rounded-lg bg-gradient-to-br from-green-600/20 to-emerald-600/20 p-3 ring-1 ring-green-500/30">
                                    <i class="icon-pulse bi bi-person-circle text-3xl text-green-400"></i>
                                </div>
                                <h4 class="relative z-10 mb-2 text-xl font-bold text-gh-fg">Rich Multimedia Profiles</h4>
                                <p class="relative z-10 text-gh-muted">Create detailed profiles with photo galleries, videos, voice notes, and comprehensive preferences.</p>
                            </div>

                            <!-- Feature 6: Privacy & Security -->
                            <div class="feature-card group rounded-xl border border-gh-border bg-gh-panel/50 p-6 backdrop-blur">
                                <div class="relative z-10 mb-4 inline-flex rounded-lg bg-gradient-to-br from-indigo-600/20 to-purple-600/20 p-3 ring-1 ring-indigo-500/30">
                                    <i class="icon-pulse bi bi-shield-check text-3xl text-indigo-400"></i>
                                </div>
                                <h4 class="relative z-10 mb-2 text-xl font-bold text-gh-fg">Privacy & Verification</h4>
                                <p class="relative z-10 text-gh-muted">Photo verification, incognito mode, and granular privacy controls to keep you safe and anonymous.</p>
                            </div>

                        </div>
                    </div>

                    <!-- Social Links -->
                    <div class="text-center">
                        <p class="mb-4 text-lg font-semibold text-gh-fg">Follow Our Journey</p>
                        <div class="flex flex-wrap items-center justify-center gap-4">
                            <a href="https://x.com/Lustifieds" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel/50 px-6 py-3 font-semibold text-gh-fg backdrop-blur transition-all hover:scale-105 hover:border-blue-500 hover:text-blue-500">
                                <i class="bi bi-twitter-x text-xl"></i>
                                <span>@Lustifieds</span>
                            </a>
                            <a href="https://status.lustifieds.com" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel/50 px-6 py-3 font-semibold text-gh-fg backdrop-blur transition-all hover:scale-105 hover:border-green-500 hover:text-green-500">
                                <i class="bi bi-activity text-xl"></i>
                                <span>Status Page</span>
                            </a>
                        </div>
                    </div>

                </div>
            </main>

            <!-- Footer -->
            <footer class="px-4 py-6 text-center sm:px-6 lg:px-8">
                <p class="text-sm text-gh-muted">
                    &copy; <?php echo date('Y'); ?> Lustifieds. All rights reserved.
                </p>
            </footer>

        </div>
    </div>

    <script>
        // Canvas setup
        const canvas = document.getElementById('signingCanvas');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let currentColor = '#000000';
        
        // Make canvas responsive
        function resizeCanvas() {
            const container = canvas.parentElement;
            const rect = container.getBoundingClientRect();
            canvas.style.width = '100%';
            canvas.style.height = 'auto';
        }
        
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
        
        // Set up canvas
        ctx.strokeStyle = currentColor;
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        function changeColor(color) {
            currentColor = color;
            ctx.strokeStyle = color;
        }
        
        function clearCanvas() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
        
        function getCoordinates(e) {
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            
            if (e.touches && e.touches[0]) {
                return {
                    x: (e.touches[0].clientX - rect.left) * scaleX,
                    y: (e.touches[0].clientY - rect.top) * scaleY
                };
            }
            return {
                x: (e.clientX - rect.left) * scaleX,
                y: (e.clientY - rect.top) * scaleY
            };
        }
        
        function startDrawing(e) {
            e.preventDefault();
            isDrawing = true;
            const coords = getCoordinates(e);
            ctx.beginPath();
            ctx.moveTo(coords.x, coords.y);
        }
        
        function draw(e) {
            if (!isDrawing) return;
            e.preventDefault();
            const coords = getCoordinates(e);
            ctx.lineTo(coords.x, coords.y);
            ctx.stroke();
        }
        
        function stopDrawing() {
            isDrawing = false;
        }
        
        // Mouse events
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        
        // Touch events
        canvas.addEventListener('touchstart', startDrawing);
        canvas.addEventListener('touchmove', draw);
        canvas.addEventListener('touchend', stopDrawing);
        
        // Save signature
        function saveSignature() {
            const name = document.getElementById('signerName').value.trim() || 'Anonymous';
            const imageData = canvas.toDataURL('image/png');
            
            // Check if canvas is blank
            const blankCanvas = document.createElement('canvas');
            blankCanvas.width = canvas.width;
            blankCanvas.height = canvas.height;
            
            if (canvas.toDataURL() === blankCanvas.toDataURL()) {
                alert('Please draw your signature first!');
                return;
            }
            
            // Send to server
            const formData = new FormData();
            formData.append('signature_data', imageData);
            formData.append('name', name);
            
            fetch('maintenance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Thanks for signing! 🎉');
                    clearCanvas();
                    document.getElementById('signerName').value = '';
                    location.reload();
                } else {
                    alert('Error saving signature. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving signature. Please try again.');
            });
        }
        
        // Countdown Timer
        <?php if($launch_date): ?>
        const launchDate = new Date('<?php echo $launch_date; ?>').getTime();
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = launchDate - now;
            
            if (distance < 0) {
                document.getElementById('days').textContent = '00';
                document.getElementById('hours').textContent = '00';
                document.getElementById('minutes').textContent = '00';
                document.getElementById('seconds').textContent = '00';
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById('days').textContent = String(days).padStart(2, '0');
            document.getElementById('hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
            document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php endif; ?>
        
        // Email Subscription
        function subscribeEmail(event) {
            event.preventDefault();
            const email = document.getElementById('notify-email').value;
            
            fetch('subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Thanks for subscribing! We\'ll notify you when we launch. 🚀');
                    document.getElementById('notify-email').value = '';
                } else {
                    alert('Something went wrong. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Thanks for your interest! We\'ll keep you updated.');
                document.getElementById('notify-email').value = '';
            });
            
            return false;
        }
    </script>

</body>
</html>
