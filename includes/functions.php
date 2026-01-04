<?php
/**
 * Helper Functions for Turnpage/Hookup Application
 */

/**
 * Calculate time ago from timestamp
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

/**
 * Check if user is online (last seen within 15 minutes)
 * @param string $last_seen
 * @return bool
 */
function isUserOnline($last_seen) {
    if (!$last_seen) return false;
    $timestamp = strtotime($last_seen);
    $diff = time() - $timestamp;
    return $diff < 900; // 15 minutes
}

/**
 * Get user's display name or username
 * @param array $user
 * @return string
 */
function getUserDisplayName($user) {
    return !empty($user['display_name']) ? $user['display_name'] : $user['username'];
}

/**
 * Get user avatar with fallback
 * @param string $avatar
 * @return string
 */
function getUserAvatar($avatar) {
    return !empty($avatar) ? $avatar : '/assets/images/default-avatar.png';
}

/**
 * Get user cover image with fallback
 * @param string $cover
 * @return string
 */
function getUserCover($cover) {
    return !empty($cover) ? $cover : '/assets/images/default-cover.jpg';
}

/**
 * Sanitize output
 * @param string $str
 * @return string
 */
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Format number for display
 * @param int $num
 * @return string
 */
function formatNumber($num) {
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    } elseif ($num >= 1000) {
        return round($num / 1000, 1) . 'K';
    }
    return $num;
}
?>