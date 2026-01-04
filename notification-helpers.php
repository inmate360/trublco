<?php
/**
 * Notification Helper Functions for trubl.co
 * Include this file in your other pages to easily create notifications
 */

/**
 * Create a new notification
 * 
 * @param PDO $db Database connection
 * @param int $user_id User receiving the notification
 * @param int $from_user_id User who triggered the notification
 * @param string $type Type: message, story_reply, forum_reply, system
 * @param string $content Preview text for the notification
 * @param int $reference_id ID of the related item
 * @param string $link_url Optional direct link
 * @return bool Success status
 */
function createNotification($db, $user_id, $from_user_id, $type, $content = '', $reference_id = null, $link_url = null) {
    try {
        // Don't notify yourself
        if ($user_id == $from_user_id) {
            return false;
        }

        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, from_user_id, type, content, reference_id, link_url)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $user_id,
            $from_user_id,
            $type,
            substr($content, 0, 200), // Limit content to 200 chars
            $reference_id,
            $link_url
        ]);
    } catch (Exception $e) {
        error_log("Notification creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count for a user
 * 
 * @param PDO $db Database connection
 * @param int $user_id User ID
 * @return int Number of unread notifications
 */
function getUnreadCount($db, $user_id) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)$result['count'];
}

/**
 * Mark notification as read
 * 
 * @param PDO $db Database connection
 * @param int $notification_id Notification ID
 * @param int $user_id User ID (for security)
 * @return bool Success status
 */
function markNotificationRead($db, $notification_id, $user_id) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    return $stmt->execute([$notification_id, $user_id]);
}

/**
 * Delete old notifications (run this in a cron job)
 * Deletes read notifications older than 30 days
 * 
 * @param PDO $db Database connection
 * @return int Number of deleted notifications
 */
function cleanupOldNotifications($db) {
    $stmt = $db->prepare("
        DELETE FROM notifications 
        WHERE is_read = 1 
        AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    return $stmt->rowCount();
}

// Example usage in your other files:
/*
// In messages.php when sending a message:
require_once 'includes/notification-helpers.php';
createNotification($db, $recipient_id, $sender_id, 'message', $message_preview, $message_id);

// In story-view.php when replying to a story:
createNotification($db, $story_author_id, $current_user_id, 'story_reply', $reply_text, $story_id);

// In forum.php when replying to a post:
createNotification($db, $post_author_id, $current_user_id, 'forum_reply', $reply_text, $forum_post_id);
*/
?>
