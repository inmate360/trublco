<?php
/**
 * SmartNotifications Class - Smart push notification system
 */
class SmartNotifications {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Send notification
    public function send($user_id, $type, $title, $body, $action_url = null, $related_user_id = null, $priority = 'medium') {
        // Check user preferences
        if(!$this->shouldSendNotification($user_id, $type)) {
            return false;
        }
        
        // Check quiet hours
        if($this->isQuietHours($user_id)) {
            return false;
        }
        
        $query = "INSERT INTO push_notifications 
                  (user_id, notification_type, title, body, action_url, related_user_id, priority)
                  VALUES (:user_id, :type, :title, :body, :url, :related_id, :priority)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':body', $body);
        $stmt->bindParam(':url', $action_url);
        $stmt->bindParam(':related_id', $related_user_id);
        $stmt->bindParam(':priority', $priority);
        
        return $stmt->execute();
    }

    // Batch send notifications
    public function sendBatch($user_ids, $type, $title, $body, $action_url = null, $priority = 'medium') {
        foreach($user_ids as $user_id) {
            $this->send($user_id, $type, $title, $body, $action_url, null, $priority);
        }
    }

    // Send message notification
    public function notifyNewMessage($user_id, $sender_id, $message_preview) {
        $query = "SELECT username FROM users WHERE id = :sender_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':sender_id', $sender_id);
        $stmt->execute();
        $sender = $stmt->fetch();
        
        if($sender) {
            $this->send(
                $user_id,
                'message',
                'New message from ' . $sender['username'],
                substr($message_preview, 0, 100),
                '/messages.php',
                $sender_id,
                'high'
            );
        }
    }

    // Send match notification
    public function notifyNewMatch($user_id, $matched_user_id, $match_score) {
        $query = "SELECT username FROM users WHERE id = :matched_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':matched_id', $matched_user_id);
        $stmt->execute();
        $matched = $stmt->fetch();
        
        if($matched) {
            $this->send(
                $user_id,
                'match',
                'New Match! ❤️',
                "You have a {$match_score}% match with " . $matched['username'],
                '/profile.php?id=' . $matched_user_id,
                $matched_user_id,
                'high'
            );
        }
    }

    // Send daily match notification
    public function notifyDailyMatches($user_id, $count) {
        $this->send(
            $user_id,
            'daily_match',
            'Your Daily Matches are Ready! 💕',
            "We found {$count} potential matches for you today",
            '/daily-matches.php',
            null,
            'medium'
        );
    }

    // Send profile view notification
    public function notifyProfileView($user_id, $viewer_id) {
        $query = "SELECT username FROM users WHERE id = :viewer_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':viewer_id', $viewer_id);
        $stmt->execute();
        $viewer = $stmt->fetch();
        
        if($viewer) {
            $this->send(
                $user_id,
                'view',
                $viewer['username'] . ' viewed your profile',
                'Someone is interested in you!',
                '/who-viewed-me.php',
                $viewer_id,
                'low'
            );
        }
    }

    // Send favorite notification
    public function notifyFavorite($user_id, $favoriter_id) {
        $query = "SELECT username FROM users WHERE id = :favoriter_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':favoriter_id', $favoriter_id);
        $stmt->execute();
        $favoriter = $stmt->fetch();
        
        if($favoriter) {
            $this->send(
                $user_id,
                'favorite',
                $favoriter['username'] . ' added you to favorites! ⭐',
                'You caught someone\'s attention!',
                '/profile.php?id=' . $favoriter_id,
                $favoriter_id,
                'medium'
            );
        }
    }

    // Get user notifications
    public function getUserNotifications($user_id, $limit = 50, $unread_only = false) {
        $query = "SELECT n.*, u.username as related_username
                  FROM push_notifications n
                  LEFT JOIN users u ON n.related_user_id = u.id
                  WHERE n.user_id = :user_id";
        
        if($unread_only) {
            $query .= " AND n.is_read = FALSE";
        }
        
        $query .= " ORDER BY n.sent_at DESC LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    // Get unread count
    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(*) as count FROM push_notifications 
                  WHERE user_id = :user_id AND is_read = FALSE";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }

    // Mark as read
    public function markAsRead($notification_id, $user_id) {
        $query = "UPDATE push_notifications 
                  SET is_read = TRUE, read_at = CURRENT_TIMESTAMP 
                  WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $notification_id);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }

    // Mark all as read
    public function markAllAsRead($user_id) {
        $query = "UPDATE push_notifications 
                  SET is_read = TRUE, read_at = CURRENT_TIMESTAMP 
                  WHERE user_id = :user_id AND is_read = FALSE";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }

    // Check if should send notification
    private function shouldSendNotification($user_id, $type) {
        $query = "SELECT * FROM notification_preferences WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $prefs = $stmt->fetch();
        
        if(!$prefs) return true; // Default allow if no preferences set
        
        if(!$prefs['push_notifications']) return false;
        
        $type_map = [
            'message' => 'message_alerts',
            'match' => 'match_alerts',
            'view' => 'view_alerts',
            'favorite' => 'favorite_alerts',
            'badge' => 'badge_alerts',
            'daily_match' => 'daily_match_alerts',
            'nearby' => 'nearby_alerts'
        ];
        
        if(isset($type_map[$type])) {
            return (bool)$prefs[$type_map[$type]];
        }
        
        return true;
    }

    // Check quiet hours
    private function isQuietHours($user_id) {
        $query = "SELECT quiet_hours_start, quiet_hours_end FROM notification_preferences WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $prefs = $stmt->fetch();
        
        if(!$prefs || !$prefs['quiet_hours_start'] || !$prefs['quiet_hours_end']) {
            return false;
        }
        
        $now = new DateTime();
        $start = DateTime::createFromFormat('H:i:s', $prefs['quiet_hours_start']);
        $end = DateTime::createFromFormat('H:i:s', $prefs['quiet_hours_end']);
        
        if($start < $end) {
            return $now >= $start && $now <= $end;
        } else {
            return $now >= $start || $now <= $end;
        }
    }

    // Get notification preferences
    public function getPreferences($user_id) {
        $query = "SELECT * FROM notification_preferences WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Save notification preferences
    public function savePreferences($user_id, $preferences) {
        $query = "INSERT INTO notification_preferences 
                  (user_id, email_notifications, push_notifications, message_alerts, match_alerts,
                   view_alerts, favorite_alerts, badge_alerts, daily_match_alerts, nearby_alerts,
                   marketing_emails, quiet_hours_start, quiet_hours_end)
                  VALUES (:user_id, :email, :push, :message, :match, :view, :favorite, :badge,
                          :daily, :nearby, :marketing, :quiet_start, :quiet_end)
                  ON DUPLICATE KEY UPDATE
                  email_notifications = :email2, push_notifications = :push2, message_alerts = :message2,
                  match_alerts = :match2, view_alerts = :view2, favorite_alerts = :favorite2,
                  badge_alerts = :badge2, daily_match_alerts = :daily2, nearby_alerts = :nearby2,
                  marketing_emails = :marketing2, quiet_hours_start = :quiet_start2, quiet_hours_end = :quiet_end2";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':email', $preferences['email_notifications'], PDO::PARAM_BOOL);
        $stmt->bindParam(':push', $preferences['push_notifications'], PDO::PARAM_BOOL);
        $stmt->bindParam(':message', $preferences['message_alerts'], PDO::PARAM_BOOL);
        $stmt->bindParam(':match', $preferences['match_alerts'], PDO::PARAM_BOOL);
        $stmt->bindParam(':view', $preferences['view_alerts'], PDO::PARAM_BOOL);
        $stmt->bindParam(':favorite', $preferences['favorite_alerts'], PDO::PARAM_BOOL);
        $stmt->bindParam(':badge', $preferences['badge_alerts'], PDO::PARAM_BOOL);
        $stmt->bindParam(':daily', $preferences['daily_match_alerts'], PDO::PARAM_BOOL);
        $stmt->bindParam(':nearby', $preferences['nearby_alerts'], PDO::PARAM_BOOL);
        $stmt->bindParam(':marketing', $preferences['marketing_emails'], PDO::PARAM_BOOL);
        $stmt->bindParam(':quiet_start', $preferences['quiet_hours_start']);
        $stmt->bindParam(':quiet_end', $preferences['quiet_hours_end']);
        
        // Duplicate key bindings
        $stmt->bindParam(':email2', $preferences['email_notifications'], PDO::PARAM_BOOL);
        $stmt->bindParam(':push2', $preferences['push_notifications'], PDO::PARAM_BOOL);
        $stmt->bindParam(':message2', $preferences['message_alerts'], PDO::PARAM_BOOL);
        $stmt->bindParam(':match2', $preferences['match_alerts'], PDO::PARAM_BOOL);
        $stmt->bindParam(':view2', $preferences['view_alerts'], PDO::PARAM_BOOL);
        $stmt->bindParam(':favorite2', $preferences['favorite_alerts'], PDO::PARAM_BOOL);
        $stmt->bindParam(':badge2', $preferences['badge_alerts'], PDO::PARAM_BOOL);
        $stmt->bindParam(':daily2', $preferences['daily_match_alerts'], PDO::PARAM_BOOL);
        $stmt->bindParam(':nearby2', $preferences['nearby_alerts'], PDO::PARAM_BOOL);
        $stmt->bindParam(':marketing2', $preferences['marketing_emails'], PDO::PARAM_BOOL);
        $stmt->bindParam(':quiet_start2', $preferences['quiet_hours_start']);
        $stmt->bindParam(':quiet_end2', $preferences['quiet_hours_end']);
        
        return $stmt->execute();
    }
}
?>