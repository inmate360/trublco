<?php
// Authentication & User Data
$unread_messages = 0;
$unread_notifications = 0;
$incognito_active = false;
$user_location_set = false;
$current_theme = 'dark';
$profile_incomplete = false;
$is_premium_user = false;
$current_username = 'User';

if(isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../classes/Message.php';
    require_once __DIR__ . '/../classes/SmartNotifications.php';
    require_once __DIR__ . '/../classes/IncognitoMode.php';
    require_once __DIR__ . '/../includes/award-notification.php';

	
    
    $db_header = new Database();
    $conn_header = $db_header->getConnection();
    
    try {
        $msg_header = new Message($conn_header);
        $unread_messages = $msg_header->getTotalUnreadCount($_SESSION['user_id']);
    } catch(Exception $e) {
        $unread_messages = 0;
    }
    
    try {
        $notif_header = new SmartNotifications($conn_header);
        $unread_notifications = $notif_header->getUnreadCount($_SESSION['user_id']);
    } catch(Exception $e) {
        $unread_notifications = 0;
    }
    
    try {
        $incognito_header = new IncognitoMode($conn_header);
        $incognito_active = $incognito_header->isActive($_SESSION['user_id']);
    } catch(Exception $e) {
        $incognito_active = false;
    }
    
    try {
        $columns_query = "SHOW COLUMNS FROM users";
        $columns_stmt = $conn_header->query($columns_query);
        $existing_columns = [];
        while($col = $columns_stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing_columns[] = $col['Field'];
        }
        
        $select_fields = ['id', 'username', 'email', 'created_at'];
        if(in_array('current_latitude', $existing_columns)) $select_fields[] = 'current_latitude';
        if(in_array('auto_location', $existing_columns)) $select_fields[] = 'auto_location';
        if(in_array('theme_preference', $existing_columns)) $select_fields[] = 'theme_preference';
        if(in_array('age', $existing_columns)) $select_fields[] = 'age';
        if(in_array('gender', $existing_columns)) $select_fields[] = 'gender';
        if(in_array('location', $existing_columns)) $select_fields[] = 'location';
        if(in_array('bio', $existing_columns)) $select_fields[] = 'bio';
        if(in_array('is_premium', $existing_columns)) $select_fields[] = 'is_premium';
        
        $query = "SELECT " . implode(', ', $select_fields) . " FROM users WHERE id = :user_id LIMIT 1";
        $stmt = $conn_header->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $user_data = $stmt->fetch();
        
        if($user_data) {
            $user_location_set = isset($user_data['current_latitude']) && !empty($user_data['current_latitude']);
            $current_theme = $user_data['theme_preference'] ?? 'dark';
            $is_premium_user = $user_data['is_premium'] ?? false;
            $current_username = $user_data['username'] ?? 'User';
            
            if(in_array('age', $existing_columns) && in_array('gender', $existing_columns) && 
               in_array('location', $existing_columns) && in_array('bio', $existing_columns)) {
                $account_age = time() - strtotime($user_data['created_at']);
                $is_new = $account_age < 86400;
                if($is_new) {
                    $profile_incomplete = empty($user_data['age']) || empty($user_data['gender']) || 
                                        empty($user_data['location']) || empty($user_data['bio']) || 
                                        strlen($user_data['bio']) < 20;
                }
            }
        }
    } catch(PDOException $e) {
        error_log("Header query error: " . $e->getMessage());
    }
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $current_theme === 'light' ? 'light' : 'dark'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>trubl - Adult Personals & Creator Marketplace</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Tailwind Config -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'gh-bg': '#0a0a0a',
                        'gh-panel': '#151515',
                        'gh-panel2': '#1a1a1a',
                        'gh-border': '#2a2a2a',
                        'gh-accent': '#ec4899',
                        'gh-muted': '#9ca3af',
                        'gh-success': '#10b981',
                        'gh-fg': '#f3f4f6'
                    }
                }
            }
        }
    </script>
    
    <style>
        /* RGB Orb Animation */
        @keyframes rgbOrb {
            0% {
                background: linear-gradient(135deg, #ec4899, #8b5cf6);
                box-shadow: 0 0 20px rgba(236, 72, 153, 0.6), 0 0 40px rgba(139, 92, 246, 0.4);
            }
            33% {
                background: linear-gradient(135deg, #8b5cf6, #06b6d4);
                box-shadow: 0 0 20px rgba(139, 92, 246, 0.6), 0 0 40px rgba(6, 182, 212, 0.4);
            }
            66% {
                background: linear-gradient(135deg, #06b6d4, #10b981);
                box-shadow: 0 0 20px rgba(6, 182, 212, 0.6), 0 0 40px rgba(16, 185, 129, 0.4);
            }
            100% {
                background: linear-gradient(135deg, #10b981, #ec4899);
                box-shadow: 0 0 20px rgba(16, 185, 129, 0.6), 0 0 40px rgba(236, 72, 153, 0.4);
            }
        }
        
        .rgb-orb {
            animation: rgbOrb 4s ease-in-out infinite;
        }
        
        /* Dropdown styles */
        .dropdown-menu {
            display: none;
        }
        
        .dropdown-menu.active {
            display: block;
        }
        
        /* Post Drawer styles */
        .post-drawer {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 60;
            transform: translateY(100%);
            transition: transform 0.3s ease-in-out;
        }
        
        .post-drawer.active {
            display: block;
            transform: translateY(0);
        }
        
        .drawer-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 55;
        }
        
        .drawer-backdrop.active {
            display: block;
        }
    </style>
</head>
<body class="bg-gh-bg text-gh-fg min-h-screen">

<!-- Header -->
<header class="sticky top-0 z-50 border-b border-gh-border bg-gh-panel/95 backdrop-blur-sm">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            
            <!-- Logo + Brand -->
            <a href="index.php" class="flex items-center gap-3 group">
                <!-- Animated RGB Orb -->
                <div class="relative">
                    <div class="h-10 w-10 rounded-full rgb-orb"></div>
                    <div class="absolute inset-0 rounded-full bg-gradient-to-br from-pink-500/20 to-purple-500/20 blur-xl"></div>
                </div>
                
                <!-- trubl Text -->
                <span class="text-xl font-bold text-white group-hover:text-gh-accent transition-colors">
                    trubl
                </span>
            </a>
            
            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center gap-1">
                <a href="browse.php" class="px-3 py-2 rounded-lg text-sm font-medium <?php echo $current_page === 'browse' ? 'bg-gh-panel2 text-white' : 'text-gh-muted hover:text-white hover:bg-gh-panel2'; ?> transition-colors">
                    <i class="bi bi-grid mr-1.5"></i>Browse
                </a>
                <a href="story.php" class="px-3 py-2 rounded-lg text-sm font-medium <?php echo $current_page === 'story' ? 'bg-gh-panel2 text-white' : 'text-gh-muted hover:text-white hover:bg-gh-panel2'; ?> transition-colors">
                    <i class="bi bi-book mr-1.5"></i>Stories
                </a>
                <a href="marketplace.php" class="px-3 py-2 rounded-lg text-sm font-medium <?php echo $current_page === 'marketplace' ? 'bg-gh-panel2 text-white' : 'text-gh-muted hover:text-white hover:bg-gh-panel2'; ?> transition-colors">
                    <i class="bi bi-bag mr-1.5"></i>Marketplace
                </a>
                <a href="forum.php" class="px-3 py-2 rounded-lg text-sm font-medium <?php echo $current_page === 'forum' ? 'bg-gh-panel2 text-white' : 'text-gh-muted hover:text-white hover:bg-gh-panel2'; ?> transition-colors">
                    <i class="bi bi-chat-dots mr-1.5"></i>Forum
                </a>
            </div>
            
            <!-- Right Side -->
            <div class="flex items-center gap-2">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Post Button -->
                    <a href="post-ad.php" class="hidden sm:inline-flex items-center gap-1.5 rounded-lg bg-gh-accent px-3 py-2 text-sm font-semibold text-white hover:opacity-90 transition-opacity">
                        <i class="bi bi-plus-lg"></i>
                        <span>Post</span>
                    </a>
                    
                    <!-- Messages -->
                    <a href="messages-inbox.php" class="relative p-2 rounded-lg text-gh-muted hover:text-white hover:bg-gh-panel2 transition-colors">
                        <i class="bi bi-envelope text-lg"></i>
                        <?php if ($unread_messages > 0): ?>
                            <span class="absolute -top-0.5 -right-0.5 h-4 w-4 rounded-full bg-gh-accent text-[10px] font-bold text-white flex items-center justify-center">
                                <?php echo $unread_messages > 9 ? '9+' : $unread_messages; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    
         
                    
                    <!-- Square Dropdown Button -->
                    <div class="relative">
                        <button 
                            id="user-menu-btn"
                            class="h-9 w-9 rounded-lg border border-gh-border bg-gh-panel2 hover:bg-gh-bg transition-colors flex items-center justify-center text-gh-muted hover:text-white"
                            onclick="toggleDropdown()"
                        >
                            <i class="bi bi-grid-3x3-gap text-lg"></i>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div id="user-menu" class="dropdown-menu absolute right-0 mt-2 w-56 rounded-xl border border-gh-border bg-gh-panel shadow-xl">
                            <div class="p-3 border-b border-gh-border">
                                <p class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($current_username); ?></p>
                                <p class="text-xs text-gh-muted">
                                    <?php if ($is_premium_user): ?>
                                        <i class="bi bi-star-fill text-gh-accent"></i> Premium Member
                                    <?php else: ?>
                                        Free Account
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div class="p-1.5">
                                <a href="profile.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-gh-muted hover:text-white hover:bg-gh-panel2 transition-colors">
                                    <i class="bi bi-person"></i>
                                    <span>My Profile</span>
                                </a>
                                <a href="edit-profile.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-gh-muted hover:text-white hover:bg-gh-panel2 transition-colors">
                                    <i class="bi bi-gear"></i>
                                    <span>Settings</span>
                                </a>
                                <?php if (!$is_premium_user): ?>
                                    <a href="membership.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-gh-accent hover:bg-gh-accent/10 transition-colors">
                                        <i class="bi bi-star-fill"></i>
                                        <span>Go Premium</span>
                                    </a>
                                <?php endif; ?>
                                
                                <div class="my-1.5 border-t border-gh-border"></div>
                                
                                <a href="my-listings.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-gh-muted hover:text-white hover:bg-gh-panel2 transition-colors">
                                    <i class="bi bi-list-ul"></i>
                                    <span>My Listings</span>
                                </a>
                                
                                <a href="marketplace.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-gh-muted hover:text-white hover:bg-gh-panel2 transition-colors">
                                    <i class="bi bi-bag"></i>
                                    <span>Marketplace</span>
                                </a>
                                
                                <a href="story.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-gh-muted hover:text-white hover:bg-gh-panel2 transition-colors">
                                    <i class="bi bi-book"></i>
                                    <span>Hookup Stories</span>
                                </a>
                                
                                <a href="forum.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-gh-muted hover:text-white hover:bg-gh-panel2 transition-colors">
                                    <i class="bi bi-chat-dots"></i>
                                    <span>Community Forums</span>
                                </a>
                                
                                <?php if ($incognito_active): ?>
                                    <a href="incognito-toggle.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-gh-success hover:bg-gh-success/10 transition-colors">
                                        <i class="bi bi-incognito"></i>
                                        <span>Incognito: ON</span>
                                    </a>
                                <?php endif; ?>
                                
                                <div class="my-1.5 border-t border-gh-border"></div>
                                
                                <a href="contact.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-gh-muted hover:text-white hover:bg-gh-panel2 transition-colors">
                                    <i class="bi bi-envelope"></i>
                                    <span>Contact Us</span>
                                </a>
                                
                                <a href="report.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-red-400 hover:bg-red-500/10 transition-colors">
                                    <i class="bi bi-flag"></i>
                                    <span>Report</span>
                                </a>
                                
                                <div class="my-1.5 border-t border-gh-border"></div>
                                
                                <a href="logout.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-red-400 hover:bg-red-500/10 transition-colors">
                                    <i class="bi bi-box-arrow-right"></i>
                                    <span>Sign Out</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Guest Buttons -->
                    <a href="login.php" class="px-3 py-2 rounded-lg text-sm font-medium text-gh-muted hover:text-white hover:bg-gh-panel2 transition-colors">
                        Sign In
                    </a>
                    <a href="register.php" class="px-3 py-2 rounded-lg bg-gh-accent text-sm font-semibold text-white hover:opacity-90 transition-opacity">
                        Sign Up
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>

<!-- Mobile Bottom Nav -->
<div class="md:hidden fixed bottom-0 inset-x-0 z-40 border-t border-gh-border bg-gh-panel/95 backdrop-blur-sm">
    <div class="flex items-center justify-around h-16 px-2">
        <a href="index.php" class="flex flex-col items-center gap-0.5 px-3 py-1 <?php echo $current_page === 'index' ? 'text-gh-accent' : 'text-gh-muted'; ?>">
            <i class="bi bi-house text-xl"></i>
            <span class="text-[10px]">Home</span>
        </a>
        <a href="browse.php" class="flex flex-col items-center gap-0.5 px-3 py-1 <?php echo $current_page === 'browse' ? 'text-gh-accent' : 'text-gh-muted'; ?>">
            <i class="bi bi-grid text-xl"></i>
            <span class="text-[10px]">Browse</span>
        </a>
        <button onclick="togglePostDrawer()" class="flex flex-col items-center gap-0.5 px-3 py-1 text-gh-accent">
            <i class="bi bi-plus-circle-fill text-2xl"></i>
            <span class="text-[10px]">Post</span>
        </button>
        <a href="messages-inbox.php" class="relative flex flex-col items-center gap-0.5 px-3 py-1 <?php echo $current_page === 'messages-inbox' ? 'text-gh-accent' : 'text-gh-muted'; ?>">
            <i class="bi bi-envelope text-xl"></i>
            <span class="text-[10px]">Messages</span>
            <?php if ($unread_messages > 0): ?>
                <span class="absolute top-0 right-1 h-3 w-3 rounded-full bg-gh-accent"></span>
            <?php endif; ?>
        </a>
        <a href="profile.php" class="flex flex-col items-center gap-0.5 px-3 py-1 <?php echo $current_page === 'profile' ? 'text-gh-accent' : 'text-gh-muted'; ?>">
            <i class="bi bi-person text-xl"></i>
            <span class="text-[10px]">Profile</span>
        </a>
    </div>
</div>

<!-- Post Drawer -->
<div id="drawer-backdrop" class="drawer-backdrop" onclick="togglePostDrawer()"></div>
<div id="post-drawer" class="post-drawer">
    <div class="bg-gh-panel border-t border-gh-border rounded-t-2xl shadow-2xl">
        <div class="p-4 border-b border-gh-border flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">Create New Post</h3>
            <button onclick="togglePostDrawer()" class="p-2 rounded-lg text-gh-muted hover:text-white hover:bg-gh-panel2">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <div class="p-4 space-y-3">
            <a href="post-ad.php" class="flex items-center gap-3 p-4 rounded-xl border border-gh-border bg-gh-panel2 hover:bg-gh-bg transition-colors">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gh-accent/20 text-gh-accent">
                    <i class="bi bi-megaphone text-xl"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-white">Post to Listings</p>
                    <p class="text-xs text-gh-muted">Create a personal ad or listing</p>
                </div>
                <i class="bi bi-chevron-right text-gh-muted"></i>
            </a>
            
            <a href="story-submit.php" class="flex items-center gap-3 p-4 rounded-xl border border-gh-border bg-gh-panel2 hover:bg-gh-bg transition-colors">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-500/20 text-purple-400">
                    <i class="bi bi-book text-xl"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-white">Share a Story</p>
                    <p class="text-xs text-gh-muted">Tell your hookup experience</p>
                </div>
                <i class="bi bi-chevron-right text-gh-muted"></i>
            </a>
            
            <a href="forum.php" class="flex items-center gap-3 p-4 rounded-xl border border-gh-border bg-gh-panel2 hover:bg-gh-bg transition-colors">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-500/20 text-cyan-400">
                    <i class="bi bi-chat-dots text-xl"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-white">Post to Forums</p>
                    <p class="text-xs text-gh-muted">Start a community discussion</p>
                </div>
                <i class="bi bi-chevron-right text-gh-muted"></i>
            </a>
        </div>
    </div>
</div>

<script>
    function toggleDropdown() {
        const menu = document.getElementById('user-menu');
        menu.classList.toggle('active');
    }
    
    function togglePostDrawer() {
        const drawer = document.getElementById('post-drawer');
        const backdrop = document.getElementById('drawer-backdrop');
        drawer.classList.toggle('active');
        backdrop.classList.toggle('active');
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const btn = document.getElementById('user-menu-btn');
        const menu = document.getElementById('user-menu');
        
        if (btn && menu && !btn.contains(event.target) && !menu.contains(event.target)) {
            menu.classList.remove('active');
        }
    });
</script>
