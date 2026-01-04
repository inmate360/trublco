<?php
// Get unread messages count if user is logged in
$unread_messages = 0;
$unread_notifications = 0;
$incognito_active = false;
$user_stats = null;

if(isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../classes/Message.php';
    require_once __DIR__ . '/../classes/SmartNotifications.php';
    require_once __DIR__ . '/../classes/IncognitoMode.php';
    require_once __DIR__ . '/../classes/Gamification.php';
    
    $db_header = new Database();
    $conn_header = $db_header->getConnection();
    
    $msg_header = new Message($conn_header);
    $unread_messages = $msg_header->getTotalUnreadCount($_SESSION['user_id']);
    
    $notif_header = new SmartNotifications($conn_header);
    $unread_notifications = $notif_header->getUnreadCount($_SESSION['user_id']);
    
    $incognito_header = new IncognitoMode($conn_header);
    $incognito_active = $incognito_header->isActive($_SESSION['user_id']);
    
    $gamification_header = new Gamification($conn_header);
    $user_stats = $gamification_header->getUserStats($_SESSION['user_id']);
    
    // Update login streak
    $gamification_header->updateLoginStreak($_SESSION['user_id']);
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DoubleList Clone - Find Your Perfect Match 💕</title>
    <link rel="stylesheet" href="/assets/css/pink-theme.css">
    <style>
        /* Additional inline styles for special effects */
        .sparkle {
            position: relative;
            overflow: hidden;
        }
        
        .sparkle::after {
            content: '✨';
            position: absolute;
            top: -20px;
            right: -20px;
            font-size: 2rem;
            opacity: 0;
            animation: sparkle 3s infinite;
        }
        
        @keyframes sparkle {
            0%, 100% { opacity: 0; transform: rotate(0deg) scale(0); }
            50% { opacity: 1; transform: rotate(180deg) scale(1); }
        }
    </style>
</head>
<body>
    <nav class="navbar-blue">
        <div class="container">
            <div class="nav-brand">
                <div class="brand-icon-small">💕</div>
                <a href="<?php echo isset($_SESSION['current_city']) ? '/city.php?location=' . $_SESSION['current_city'] : '/choose-location.php'; ?>">DoubleList</a>
            </div>
            <button class="menu-toggle" id="menuToggle">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="nav-menu" id="navMenu">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php if($user_stats): ?>
                    <a href="/gamification.php" class="<?php echo $current_page == 'gamification' ? 'active' : ''; ?>" title="Level <?php echo $user_stats['level']; ?>">
                        <span class="level-badge" style="font-size: 0.8rem; padding: 0.3rem 0.6rem;">
                            Lv<?php echo $user_stats['level']; ?>
                        </span>
                    </a>
                    <?php endif; ?>
                    
                    <a href="/daily-matches.php" class="<?php echo $current_page == 'daily-matches' ? 'active' : ''; ?>">
                        💖 Daily Matches
                    </a>
                    
                    <a href="/browse-members.php" class="<?php echo $current_page == 'browse-members' ? 'active' : ''; ?>">
                        🔍 Browse
                    </a>
                    
                    <a href="/my-listings.php" class="<?php echo $current_page == 'my-listings' ? 'active' : ''; ?>">
                        My Listings
                    </a>
                    
                    <a href="/messages.php" class="<?php echo $current_page == 'messages' ? 'active' : ''; ?>">
                        💬 Messages 
                        <?php if($unread_messages > 0): ?>
                            <span class="notification-badge"><?php echo $unread_messages; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="/notifications.php" class="<?php echo $current_page == 'notifications' ? 'active' : ''; ?>">
                        🔔
                        <?php if($unread_notifications > 0): ?>
                            <span class="notification-badge"><?php echo $unread_notifications; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="/profile.php?id=<?php echo $_SESSION['user_id']; ?>" class="<?php echo $current_page == 'profile' ? 'active' : ''; ?>">
                        👤 Profile
                    </a>
                    
                    <a href="/settings.php" class="<?php echo $current_page == 'settings' ? 'active' : ''; ?>">
                        ⚙️
                    </a>
                    
                    <?php 
                    // Check if moderator
                    if(isset($conn_header)) {
                        require_once __DIR__ . '/../classes/Moderator.php';
                        $mod_check = new Moderator($conn_header);
                        if($mod_check->isModerator($_SESSION['user_id'])): 
                    ?>
                    <a href="/moderator/dashboard.php" style="color: var(--gold-accent); font-weight: bold;">
                        🛡️ Moderator
                    </a>
                    <?php 
                        endif;
                    }
                    ?>
                    
                    <a href="/logout.php">Logout</a>
                <?php else: ?>
                    <a href="/browse-members.php">Browse</a>
                    <a href="/membership.php" class="<?php echo $current_page == 'membership' ? 'active' : ''; ?>">
                        💎 Premium
                    </a>
                    <a href="/login.php" class="<?php echo $current_page == 'login' ? 'active' : ''; ?>">
                        Login
                    </a>
                    <a href="/register.php" class="btn-primary btn-small">
                        Sign Up Free
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <?php if($incognito_active && isset($_SESSION['user_id'])): ?>
    <div class="incognito-indicator">
        🕶️ Incognito Mode Active
    </div>
    <?php endif; ?>
    
    <main>

<script>
// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const navMenu = document.getElementById('navMenu');
    
    if(menuToggle && navMenu) {
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if(!navMenu.contains(e.target) && !menuToggle.contains(e.target)) {
                menuToggle.classList.remove('active');
                navMenu.classList.remove('active');
            }
        });
        
        // Close menu when clicking a link
        navMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                menuToggle.classList.remove('active');
                navMenu.classList.remove('active');
            });
        });
    }
});
</script>