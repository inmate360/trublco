<?php
/**
 * Authentication Check
 * Include this file to require user authentication
 */

if (!isset($_SESSION)) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Optional: Check if user is admin
function requireAdmin() {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        header('Location: index.php');
        exit();
    }
}

// Optional: Check if user is moderator or admin
function requireModerator() {
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'moderator'])) {
        header('Location: index.php');
        exit();
    }
}
?>
