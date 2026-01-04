<?php
/**
 * Enhanced Private Messaging System for Lustifieds
 * (Updated: Uses 'avatar' column for user images)
 */

require_once __DIR__ . '/ScamDetector.php';

class PrivateMessaging {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getInbox($user_id, $page = 1, $per_page = 20) {
        $offset = ($page - 1) * $per_page;

        $query = "SELECT 
                    t.*,
                    starter.username as starter_name,
                    starter.is_verified as starter_verified,
                    starter.avatar as starter_image,
                    recipient.username as recipient_name,
                    recipient.is_verified as recipient_verified,
                    recipient.avatar as recipient_image,
                    (SELECT COUNT(*) FROM messages WHERE thread_id = t.id AND recipient_id = :user_id AND is_read = 0) as unread_count,
                    (SELECT message FROM messages WHERE thread_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message
                  FROM message_threads t
                  LEFT JOIN users starter ON t.starter_id = starter.id
                  LEFT JOIN users recipient ON t.recipient_id = recipient.id
                  WHERE (t.starter_id = :user_id OR t.recipient_id = :user_id)
                    AND t.is_deleted_by_starter = 0 AND t.is_deleted_by_recipient = 0
                  ORDER BY t.last_activity DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getThread($thread_id, $user_id) {
        $query = "SELECT t.*,
                    starter.username as starter_name,
                    starter.is_verified as starter_verified,
                    starter.avatar as starter_image,
                    recipient.username as recipient_name,
                    recipient.is_verified as recipient_verified,
                    recipient.avatar as recipient_image
                  FROM message_threads t
                  LEFT JOIN users starter ON t.starter_id = starter.id
                  LEFT JOIN users recipient ON t.recipient_id = recipient.id
                  WHERE t.id = :thread_id 
                    AND (t.starter_id = :user_id OR t.recipient_id = :user_id)";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $thread = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$thread) return null;

        // Uses 'avatar' column here too
        $query = "SELECT m.*, u.username, u.is_verified, u.avatar as profile_image
                  FROM messages m
                  LEFT JOIN users u ON m.sender_id = u.id
                  WHERE m.thread_id = :thread_id
                  ORDER BY m.created_at ASC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id, PDO::PARAM_INT);
        $stmt->execute();

        $thread['messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $thread;
    }

    public function createThread($sender_id, $recipient_id, $subject, $message) {
        try {
            $this->db->beginTransaction();

            $query = "SELECT id FROM message_threads 
                      WHERE ((starter_id = :sender AND recipient_id = :recipient)
                         OR (starter_id = :recipient AND recipient_id = :sender))
                      AND is_deleted_by_starter = 0 AND is_deleted_by_recipient = 0
                      LIMIT 1";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':sender', $sender_id, PDO::PARAM_INT);
            $stmt->bindParam(':recipient', $recipient_id, PDO::PARAM_INT);
            $stmt->execute();

            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if($existing) {
                $thread_id = (int)$existing['id'];
            } else {
                $query = "INSERT INTO message_threads 
                          (starter_id, recipient_id, subject, created_at, last_activity) 
                          VALUES (:starter, :recipient, :subject, NOW(), NOW())";

                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':starter', $sender_id, PDO::PARAM_INT);
                $stmt->bindParam(':recipient', $recipient_id, PDO::PARAM_INT);
                $stmt->bindParam(':subject', $subject);
                $stmt->execute();

                $thread_id = (int)$this->db->lastInsertId();
            }

            $query = "INSERT INTO messages 
                      (thread_id, sender_id, recipient_id, message, created_at) 
                      VALUES (:thread, :sender, :recipient, :message, NOW())";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':thread', $thread_id, PDO::PARAM_INT);
            $stmt->bindParam(':sender', $sender_id, PDO::PARAM_INT);
            $stmt->bindParam(':recipient', $recipient_id, PDO::PARAM_INT);
            $stmt->bindParam(':message', $message);
            $stmt->execute();

            $this->updateThreadActivity($thread_id);

            $this->db->commit();
            return ['success' => true, 'thread_id' => $thread_id];

        } catch(PDOException $e) {
            $this->db->rollBack();
            error_log("Error creating thread: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to send message: ' . $e->getMessage()];
        }
    }

    public function sendReply($thread_id, $sender_id, $message) {
        try {
            $query = "SELECT starter_id, recipient_id FROM message_threads WHERE id = :thread_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':thread_id', $thread_id, PDO::PARAM_INT);
            $stmt->execute();
            $thread = $stmt->fetch(PDO::FETCH_ASSOC);

            if(!$thread) {
                return ['success' => false, 'error' => 'Thread not found'];
            }

            $recipient_id = ((int)$thread['starter_id'] === (int)$sender_id)
                ? (int)$thread['recipient_id']
                : (int)$thread['starter_id'];

            $query = "INSERT INTO messages 
                      (thread_id, sender_id, recipient_id, message, created_at) 
                      VALUES (:thread, :sender, :recipient, :message, NOW())";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':thread', $thread_id, PDO::PARAM_INT);
            $stmt->bindParam(':sender', $sender_id, PDO::PARAM_INT);
            $stmt->bindParam(':recipient', $recipient_id, PDO::PARAM_INT);
            $stmt->bindParam(':message', $message);
            $stmt->execute();

            $this->updateThreadActivity($thread_id);
            $message_id = (int)$this->db->lastInsertId();

            // AI Scam Detection & Safety Tips
            $scamDetector = new ScamDetector($this->db);
            $recentMessages = $this->getNewMessages($thread_id, 0, $sender_id);
            $scamResult = $scamDetector->analyzeConversation($recentMessages, $sender_id, $recipient_id);

            return [
                'success' => true, 
                'message_id' => $message_id,
                'safety_tip' => $scamResult['safety_tip'] ?? null
            ];

        } catch(PDOException $e) {
            error_log("Error sending reply: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to send message'];
        }
    }

    public function markAsRead($thread_id, $user_id) {
        $query = "UPDATE messages 
                  SET is_read = 1 
                  WHERE thread_id = :thread_id 
                    AND recipient_id = :user_id 
                    AND is_read = 0";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(*) FROM messages WHERE recipient_id = :user_id AND is_read = 0";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function deleteThread($thread_id, $user_id) {
        $query = "SELECT starter_id, recipient_id FROM message_threads WHERE id = :thread_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id, PDO::PARAM_INT);
        $stmt->execute();
        $thread = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$thread) return false;

        if((int)$thread['starter_id'] === (int)$user_id) {
            $query = "UPDATE message_threads SET is_deleted_by_starter = 1 WHERE id = :thread_id";
        } else {
            $query = "UPDATE message_threads SET is_deleted_by_recipient = 1 WHERE id = :thread_id";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function searchUsers($query, $limit = 10) {
        $search = "%{$query}%";

        // Uses 'avatar' column here too
        $stmt = $this->db->prepare("SELECT id, username, is_verified, is_premium, avatar
                                     FROM users
                                     WHERE username LIKE :query
                                     ORDER BY is_verified DESC, username ASC
                                     LIMIT :limit");

        $stmt->bindParam(':query', $search);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function updateThreadActivity($thread_id) {
        $query = "UPDATE message_threads SET last_activity = NOW() WHERE id = :thread_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getNewMessages($thread_id, $since_id, $user_id) {
        // Uses 'avatar' column here too
        $query = "SELECT m.*, u.username, u.is_verified, u.avatar as profile_image
                  FROM messages m
                  LEFT JOIN users u ON m.sender_id = u.id
                  WHERE m.thread_id = :thread_id
                    AND m.id > :since_id
                  ORDER BY m.created_at ASC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id, PDO::PARAM_INT);
        $stmt->bindParam(':since_id', $since_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
