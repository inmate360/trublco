<?php
// Get unread messages count if user is logged in
$unread_messages = 0;
if(isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../classes/Message.php';
    
    $db_header = new Database();
    $conn_header = $db_header->getConnection();
    $msg_header = new Message($conn_header);
    $unread_messages = $msg_header->getTotalUnreadCount($_SESSION['user_id']);
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DoubleList Clone - Local Personals</title>
    <link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
</head>
<body>
    <nav class="navbar-blue">
        <div class="container">
            <div class="nav-brand">
                <div class="brand-icon-small">📋</div>
                <a href="<?php echo isset($_SESSION['current_city']) ? '/city.php?location=' . $_SESSION['current_city'] : '/choose-location.php'; ?>">DoubleList</a>
            </div>
            <button class="menu-toggle" id="menuToggle">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="nav-menu" id="navMenu">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="/my-listings.php" class="<?php echo $current_page == 'my-listings' ? 'active' : ''; ?>">My Listings</a>
                    <a href="/messages.php" class="<?php echo $current_page == 'messages' ? 'active' : ''; ?>">
                        Messages 
                        <?php if($unread_messages > 0): ?>
                            <span class="notification-badge"><?php echo $unread_messages; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/membership.php" class="<?php echo $current_page == 'membership' ? 'active' : ''; ?>">Membership</a>
                    <?php 
                    // Check if moderator
                    if(isset($conn_header)) {
                        require_once __DIR__ . '/../classes/Moderator.php';
                        $mod_check = new Moderator($conn_header);
                        if($mod_check->isModerator($_SESSION['user_id'])): 
                    ?>
                    <a href="/moderator/dashboard.php" style="color: var(--primary-blue); font-weight: bold;">🛡️ Moderator</a>
                    <?php 
                        endif;
                    }
                    ?>
                    <a href="/logout.php">Logout</a>
                <?php else: ?>
                    <a href="/membership.php" class="<?php echo $current_page == 'membership' ? 'active' : ''; ?>">Plans</a>
                    <a href="/login.php" class="<?php echo $current_page == 'login' ? 'active' : ''; ?>">Login</a>
                    <a href="/register.php" class="btn-primary btn-small">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
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