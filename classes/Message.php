<?php
require_once __DIR__ . '/ImageUpload.php';

class Message {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get or create a conversation between two users
     */
    public function getOrCreateConversation($user1_id, $user2_id) {
        // Check if conversation exists (in either direction)
        $query = "SELECT id FROM conversations 
                  WHERE (user1_id = :user1 AND user2_id = :user2)
                  OR (user1_id = :user2 AND user2_id = :user1)
                  LIMIT 1";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user1', $user1_id);
            $stmt->bindParam(':user2', $user2_id);
            $stmt->execute();
            
            $conversation = $stmt->fetch();
            
            if($conversation) {
                return $conversation['id'];
            }
            
            // Create new conversation if doesn't exist
            $query = "INSERT INTO conversations (user1_id, user2_id, created_at) 
                      VALUES (:user1, :user2, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user1', $user1_id);
            $stmt->bindParam(':user2', $user2_id);
            
            if($stmt->execute()) {
                return $this->db->lastInsertId();
            }
            
            return null;
        } catch(PDOException $e) {
            error_log("Error in getOrCreateConversation: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Send a message
     */
    public function send($sender_id, $receiver_id, $message, $image_url = null) {
        $query = "INSERT INTO messages (sender_id, receiver_id, message, image_url, has_image, created_at) 
                  VALUES (:sender_id, :receiver_id, :message, :image_url, :has_image, NOW())";
        
        $has_image = !empty($image_url);
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':sender_id', $sender_id);
            $stmt->bindParam(':receiver_id', $receiver_id);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':image_url', $image_url);
            $stmt->bindParam(':has_image', $has_image, PDO::PARAM_BOOL);
            
            if($stmt->execute()) {
                $message_id = $this->db->lastInsertId();
                
                // Create notification for receiver
                $this->createNotification($receiver_id, $sender_id, $message_id);
                
                return [
                    'success' => true,
                    'message_id' => $message_id
                ];
            }
            
            return ['success' => false, 'error' => 'Failed to send message'];
        } catch(PDOException $e) {
            error_log("Error sending message: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get conversation between two users
     */
    public function getConversation($user1_id, $user2_id, $limit = 50, $offset = 0) {
        $query = "SELECT m.*, 
                  sender.username as sender_username,
                  sender.is_online as sender_online,
                  receiver.username as receiver_username,
                  receiver.is_online as receiver_online
                  FROM messages m
                  LEFT JOIN users sender ON m.sender_id = sender.id
                  LEFT JOIN users receiver ON m.receiver_id = receiver.id
                  WHERE ((m.sender_id = :user1 AND m.receiver_id = :user2)
                  OR (m.sender_id = :user2 AND m.receiver_id = :user1))";
        
        // Check if deleted columns exist
        try {
            $check = $this->db->query("SHOW COLUMNS FROM messages LIKE 'deleted_by_sender'");
            if($check->rowCount() > 0) {
                $query .= " AND ((m.sender_id = :user1_check AND m.deleted_by_sender = FALSE)
                            OR (m.receiver_id = :user1_check AND m.deleted_by_receiver = FALSE))";
            }
        } catch(PDOException $e) {
            // Column doesn't exist, continue without filter
        }
        
        $query .= " ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user1', $user1_id, PDO::PARAM_INT);
            $stmt->bindParam(':user2', $user2_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            
            // Bind deleted check if needed
            try {
                $stmt->bindParam(':user1_check', $user1_id, PDO::PARAM_INT);
            } catch(PDOException $e) {
                // Parameter doesn't exist in query
            }
            
            $stmt->execute();
            
            $messages = $stmt->fetchAll();
            
            // Get reactions for each message if table exists
            if($this->tableExists('message_reactions')) {
                foreach($messages as &$msg) {
                    $msg['reactions'] = $this->getMessageReactions($msg['id']);
                }
            }
            
            return array_reverse($messages);
        } catch(PDOException $e) {
            error_log("Error getting conversation: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user's conversations list
     */
    public function getUserConversations($user_id, $limit = 50) {
        $query = "SELECT DISTINCT
                  CASE 
                    WHEN m.sender_id = :user_id THEN m.receiver_id 
                    ELSE m.sender_id 
                  END as contact_id,
                  u.username, u.is_online, u.last_seen,
                  (SELECT message FROM messages 
                   WHERE (sender_id = contact_id AND receiver_id = :user_id2) 
                   OR (sender_id = :user_id3 AND receiver_id = contact_id)
                   ORDER BY created_at DESC LIMIT 1) as last_message,
                  (SELECT created_at FROM messages 
                   WHERE (sender_id = contact_id AND receiver_id = :user_id4) 
                   OR (sender_id = :user_id5 AND receiver_id = contact_id)
                   ORDER BY created_at DESC LIMIT 1) as last_message_time,
                  (SELECT COUNT(*) FROM messages 
                   WHERE sender_id = contact_id 
                   AND receiver_id = :user_id6";
        
        // Check if is_read column exists
        if($this->columnExists('messages', 'is_read')) {
            $query .= " AND is_read = FALSE";
        }
        
        $query .= ") as unread_count
                  FROM messages m
                  LEFT JOIN users u ON u.id = CASE 
                    WHEN m.sender_id = :user_id7 THEN m.receiver_id 
                    ELSE m.sender_id 
                  END
                  WHERE (m.sender_id = :user_id8 OR m.receiver_id = :user_id9)
                  ORDER BY last_message_time DESC
                  LIMIT :limit";
        
        try {
            $stmt = $this->db->prepare($query);
            for($i = 1; $i <= 9; $i++) {
                $param = ':user_id' . ($i > 1 ? $i : '');
                $stmt->bindParam($param, $user_id, PDO::PARAM_INT);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error getting conversations: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark message as read
     */
    public function markAsRead($message_id, $user_id) {
        if(!$this->columnExists('messages', 'is_read')) {
            return true; // Column doesn't exist, skip
        }
        
        $query = "UPDATE messages 
                  SET is_read = TRUE, read_at = NOW() 
                  WHERE id = :message_id AND receiver_id = :user_id AND is_read = FALSE";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':message_id', $message_id);
            $stmt->bindParam(':user_id', $user_id);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error marking message as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark conversation as read
     */
    public function markConversationAsRead($sender_id, $receiver_id) {
        if(!$this->columnExists('messages', 'is_read')) {
            return true;
        }
        
        $query = "UPDATE messages 
                  SET is_read = TRUE, read_at = NOW() 
                  WHERE sender_id = :sender_id AND receiver_id = :receiver_id AND is_read = FALSE";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':sender_id', $sender_id);
            $stmt->bindParam(':receiver_id', $receiver_id);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error marking conversation as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread message count
     */
    public function getUnreadCount($user_id) {
        if(!$this->columnExists('messages', 'is_read')) {
            return 0;
        }
        
        $query = "SELECT COUNT(DISTINCT sender_id) as unread_count 
                  FROM messages 
                  WHERE receiver_id = :user_id AND is_read = FALSE";
        
        // Check for deleted columns
        if($this->columnExists('messages', 'deleted_by_receiver')) {
            $query .= " AND deleted_by_receiver = FALSE";
        }
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result['unread_count'] ?? 0;
        } catch(PDOException $e) {
            error_log("Error getting unread count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get total unread message count
     */
    public function getTotalUnreadCount($user_id) {
        if(!$this->columnExists('messages', 'is_read')) {
            return 0;
        }
        
        $query = "SELECT COUNT(*) as total 
                  FROM messages 
                  WHERE receiver_id = :user_id AND is_read = FALSE";
        
        if($this->columnExists('messages', 'deleted_by_receiver')) {
            $query .= " AND deleted_by_receiver = FALSE";
        }
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch(PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Add reaction to message
     */
    public function addReaction($message_id, $user_id, $reaction) {
        if(!$this->tableExists('message_reactions')) {
            return false;
        }
        
        $query = "INSERT INTO message_reactions (message_id, user_id, reaction) 
                  VALUES (:message_id, :user_id, :reaction)
                  ON DUPLICATE KEY UPDATE reaction = :reaction2";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':message_id', $message_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':reaction', $reaction);
            $stmt->bindParam(':reaction2', $reaction);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error adding reaction: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove reaction from message
     */
    public function removeReaction($message_id, $user_id) {
        if(!$this->tableExists('message_reactions')) {
            return false;
        }
        
        $query = "DELETE FROM message_reactions 
                  WHERE message_id = :message_id AND user_id = :user_id";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':message_id', $message_id);
            $stmt->bindParam(':user_id', $user_id);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error removing reaction: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get message reactions
     */
    public function getMessageReactions($message_id) {
        if(!$this->tableExists('message_reactions')) {
            return [];
        }
        
        $query = "SELECT mr.*, u.username 
                  FROM message_reactions mr
                  LEFT JOIN users u ON mr.user_id = u.id
                  WHERE mr.message_id = :message_id
                  ORDER BY mr.created_at ASC";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':message_id', $message_id);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Set typing indicator
     */
    public function setTypingIndicator($user_id, $other_user_id, $is_typing = true) {
        if(!$this->tableExists('typing_indicators')) {
            return false;
        }
        
        $conversation_id = min($user_id, $other_user_id) . '_' . max($user_id, $other_user_id);
        
        if($is_typing) {
            $query = "INSERT INTO typing_indicators (conversation_id, user_id, is_typing, last_updated) 
                      VALUES (:conversation_id, :user_id, TRUE, NOW())
                      ON DUPLICATE KEY UPDATE is_typing = TRUE, last_updated = NOW()";
        } else {
            $query = "DELETE FROM typing_indicators 
                      WHERE conversation_id = :conversation_id AND user_id = :user_id";
        }
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':conversation_id', $conversation_id);
            $stmt->bindParam(':user_id', $user_id);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error setting typing indicator: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get typing indicator
     */
    public function getTypingIndicator($user1_id, $user2_id) {
        if(!$this->tableExists('typing_indicators')) {
            return false;
        }
        
        $conversation_id = min($user1_id, $user2_id) . '_' . max($user1_id, $user2_id);
        
        // Clean old indicators (older than 10 seconds)
        try {
            $this->db->exec("DELETE FROM typing_indicators WHERE last_updated < DATE_SUB(NOW(), INTERVAL 10 SECOND)");
        } catch(PDOException $e) {
            // Ignore cleanup errors
        }
        
        $query = "SELECT user_id FROM typing_indicators 
                  WHERE conversation_id = :conversation_id 
                  AND user_id != :exclude_user 
                  AND is_typing = TRUE";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':conversation_id', $conversation_id);
            $stmt->bindParam(':exclude_user', $user1_id);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Upload image
     */
    public function uploadImage($file, $user_id) {
        $imageUpload = new ImageUpload($this->db);
        $result = $imageUpload->upload($file, 'message');
        
        if($result['success']) {
            return [
                'success' => true,
                'url' => $result['path'],
                'thumbnail' => $result['thumbnail'] ?? null
            ];
        }
        
        return ['success' => false, 'error' => $result['message']];
    }
    
    /**
     * Private helper methods
     */
    private function createNotification($user_id, $from_user_id, $message_id) {
        try {
            if(!$this->tableExists('notifications')) {
                return false;
            }
            
            $query = "INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                      VALUES (:user_id, 'message', 'You have a new message', :message_id, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':message_id', $message_id);
            $stmt->execute();
            return true;
        } catch(PDOException $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }
    
    private function tableExists($tableName) {
        try {
            $result = $this->db->query("SHOW TABLES LIKE '{$tableName}'");
            return $result->rowCount() > 0;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    private function columnExists($tableName, $columnName) {
        try {
            $result = $this->db->query("SHOW COLUMNS FROM {$tableName} LIKE '{$columnName}'");
            return $result->rowCount() > 0;
        } catch(PDOException $e) {
            return false;
        }
    }
}
?>