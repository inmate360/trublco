<?php
// Admin Header - Dedicated header for admin pages
// This prevents redirect issues caused by using the regular user header

// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
//if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
   // header('Location: ../login.php');
  //  exit;
//}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
  <meta name="description" content="Admin Dashboard - Content Moderation" />
  <meta name="theme-color" content="#0d1117" />
  
  <title>Admin Dashboard - trubl</title>
  
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            gh: {
              bg: '#0d1117',
              panel: '#161b22',
              panel2: '#0b1220',
              border: '#30363d',
              fg: '#c9d1d9',
              muted: '#8b949e',
              accent: '#2f81f7',
              danger: '#da3633',
              warning: '#d29922',
              success: '#238636'
            }
          }
        }
      }
    }
  </script>
  
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
  </style>
</head>

<body class="bg-gh-bg text-gh-fg">
  
  <header class="sticky top-0 z-50 border-b border-gh-border bg-gh-bg/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3">
      
      <a class="flex items-center gap-3 font-extrabold tracking-tight text-gh-fg hover:no-underline" href="dashboard.php">
        <span class="text-2xl sm:text-3xl font-bold" style="font-family: 'Brush Script MT', cursive; color: #FFD700;">Admin Panel</span>
      </a>

      <nav class="flex items-center gap-2">
        <a class="rounded-md px-3 py-2 text-sm font-semibold transition-colors <?php echo $current_page === 'dashboard' ? 'border border-gh-border bg-gh-panel text-gh-fg' : 'text-gh-muted hover:bg-white/5 hover:text-gh-fg'; ?>"
           href="dashboard.php">
          <i class="bi bi-speedometer2 mr-1.5"></i>Dashboard
        </a>
        
        <a class="rounded-md px-3 py-2 text-sm font-semibold transition-colors <?php echo $current_page === 'moderation' ? 'border border-gh-border bg-gh-panel text-gh-fg' : 'text-gh-muted hover:bg-white/5 hover:text-gh-fg'; ?>"
           href="moderation.php">
          <i class="bi bi-shield-check mr-1.5"></i>Moderation
        </a>
        
        <a class="rounded-md px-3 py-2 text-sm font-semibold transition-colors <?php echo $current_page === 'users' ? 'border border-gh-border bg-gh-panel text-gh-fg' : 'text-gh-muted hover:bg-white/5 hover:text-gh-fg'; ?>"
           href="users.php">
          <i class="bi bi-people-fill mr-1.5"></i>Users
        </a>
        
        <a class="rounded-md px-3 py-2 text-sm font-semibold transition-colors <?php echo $current_page === 'news' ? 'border border-gh-border bg-gh-panel text-gh-fg' : 'text-gh-muted hover:bg-white/5 hover:text-gh-fg'; ?>"
           href="news.php">
          <i class="bi bi-megaphone-fill mr-1.5"></i>News
        </a>
        
        <a class="rounded-md px-3 py-2 text-sm font-semibold transition-colors text-gh-muted hover:bg-white/5 hover:text-gh-fg"
           href="../index.php">
          <i class="bi bi-house-fill mr-1.5"></i>Main Site
        </a>
        
        <a class="rounded-md px-3 py-2 text-sm font-semibold transition-colors text-gh-muted hover:bg-white/5 hover:text-gh-fg"
           href="../logout.php">
          <i class="bi bi-box-arrow-right mr-1.5"></i>Logout
        </a>
      </nav>
    </div>
  </header>
