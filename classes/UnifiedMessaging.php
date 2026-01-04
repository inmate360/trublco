<?php
require_once __DIR__ . '/ImageUpload.php';
require_once __DIR__ . '/ScamDetector.php';

class UnifiedMessaging {
    private $db;
    
    // Message limits
    const FREE_DAILY_LIMIT = 25;
    const PLUS_DAILY_LIMIT = 100;
    const PREMIUM_DAILY_LIMIT = 999999; // Unlimited
    const VIP_DAILY_LIMIT = 999999; // Unlimited
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Check if user can send message (daily limit check)
     */
    public function canSendMessage($user_id) {
        // Get user's membership status
        $query = "SELECT is_premium, daily_message_limit FROM users WHERE id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if(!$user) {
            return ['can_send' => false, 'error' => 'User not found'];
        }
        
        // Premium users have unlimited messages
        if($user['is_premium']) {
            return [
                'can_send' => true,
                'remaining' => 'Unlimited',
                'is_premium' => true
            ];
        }
        
        // Check daily limit for free users
        $limit = self::FREE_DAILY_LIMIT;
        $today = date('Y-m-d');
        
        // Get or create limit record
        $query = "INSERT INTO message_limits (user_id, messages_sent_today, last_reset_date) 
                  VALUES (:user_id, 0, :today)
                  ON DUPLICATE KEY UPDATE 
                  messages_sent_today = IF(last_reset_date < :today2, 0, messages_sent_today),
                  last_reset_date = IF(last_reset_date < :today3, :today4, last_reset_date)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':today', $today);
        $stmt->bindParam(':today2', $today);
        $stmt->bindParam(':today3', $today);
        $stmt->bindParam(':today4', $today);
        $stmt->execute();
        
        // Get current count
        $query = "SELECT messages_sent_today FROM message_limits WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $limitData = $stmt->fetch();
        
        $sent_today = $limitData['messages_sent_today'] ?? 0;
        $remaining = $limit - $sent_today;
        
        if($sent_today >= $limit) {
            return [
                'can_send' => false,
                'error' => 'Daily message limit reached (25 messages). Upgrade to Premium for unlimited messaging!',
                'limit' => $limit,
                'sent_today' => $sent_today,
                'remaining' => 0,
                'is_premium' => false
            ];
        }
        
        return [
            'can_send' => true,
            'limit' => $limit,
            'sent_today' => $sent_today,
            'remaining' => $remaining,
            'is_premium' => false
        ];
    }
    
    /**
     * Censor message content for free users
     */
    private function censorMessage($message, $is_premium) {
        if($is_premium) {
            return ['censored' => false, 'message' => $message, 'violations' => []];
        }
        
        $violations = [];
        $censored = false;
        $original = $message;
        
        // Phone number patterns to detect
        $phonePatterns = [
            // Standard formats
            '/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/',  // 555-123-4567, 555.123.4567, 555 123 4567
            '/\b\(\d{3}\)\s?\d{3}[-.\s]?\d{4}\b/',  // (555) 123-4567
            '/\b\d{10}\b/',                          // 5551234567
            '/\b1[-.\s]?\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/', // 1-555-123-4567
            
            // International formats
            '/\b\+\d{1,3}[-.\s]?\d{1,14}\b/',      // +1 555 123 4567
            '/\b00\d{1,3}[-.\s]?\d{1,14}\b/',      // 001 555 123 4567
            
            // Written out numbers
            '/\b(?:zero|one|two|three|four|five|six|seven|eight|nine)(?:\s?(?:zero|one|two|three|four|five|six|seven|eight|nine)){9,}\b/i',
            
            // Obfuscated formats
            '/\b\d{3}\s?[a-z]+\s?\d{3}\s?[a-z]+\s?\d{4}\b/i', // 555 dash 123 dash 4567
            '/\b\d[\s\-\.]?\d[\s\-\.]?\d[\s\-\.]?\d[\s\-\.]?\d[\s\-\.]?\d[\s\-\.]?\d[\s\-\.]?\d[\s\-\.]?\d[\s\-\.]?\d\b/', // Spaced out digits
        ];
        
        foreach($phonePatterns as $pattern) {
            if(preg_match($pattern, $message, $matches)) {
                $censored = true;
                $violations[] = 'phone_number';
                // Replace phone number with censored text
                $message = preg_replace($pattern, '[PHONE NUMBER BLOCKED]', $message);
            }
        }
        
        // Check for digit sequences that might be phone numbers
        if(preg_match_all('/\d/', $message, $matches)) {
            if(count($matches[0]) >= 10) {
                // Remove spaces, dashes, dots between potential phone number
                $digitsOnly = preg_replace('/\D/', '', $original);
                if(strlen($digitsOnly) >= 10) {
                    $censored = true;
                    $violations[] = 'digit_sequence';
                }
            }
        }
        
        // Check for contact-related keywords with numbers
        $contactPatterns = [
            '/(?:call|text|phone|cell|mobile|number|contact|reach|dial)(?:\s+(?:me|at|on))?\s*:?\s*\d+/i',
            '/\d+\s*(?:is|@)?\s*(?:my|the)?\s*(?:number|phone|cell|mobile|contact)/i',
        ];
        
        foreach($contactPatterns as $pattern) {
            if(preg_match($pattern, $message)) {
                $censored = true;
                $violations[] = 'contact_info';
                $message = preg_replace($pattern, '[CONTACT INFO BLOCKED]', $message);
            }
        }
        
        return [
            'censored' => $censored,
            'message' => $message,
            'violations' => array_unique($violations)
        ];
    }
    
    /**
     * Send a message
     */
    public function sendMessage($sender_id, $receiver_id, $message, $image_url = null) {
        // Check if can send
        $canSend = $this->canSendMessage($sender_id);
        
        if(!$canSend['can_send']) {
            return [
                'success' => false,
                'error' => $canSend['error'],
                'limit_info' => $canSend,
                'upgrade_required' => true
            ];
        }
        
        // Check if blocked
        if($this->isBlocked($sender_id, $receiver_id)) {
            return ['success' => false, 'error' => 'You cannot message this user'];
        }
        
        // Censor message for free users
        $censorResult = $this->censorMessage($message, $canSend['is_premium']);
        
        if($censorResult['censored']) {
            return [
                'success' => false,
                'error' => 'Your message contains phone numbers or contact information. Upgrade to Premium to share contact details!',
                'censored' => true,
                'violations' => $censorResult['violations'],
                'upgrade_required' => true,
                'censored_message' => $censorResult['message']
            ];
        }
        
        $has_image = !empty($image_url);
        
        $query = "INSERT INTO messages (sender_id, receiver_id, message, image_url, has_image, created_at) 
                  VALUES (:sender_id, :receiver_id, :message, :image_url, :has_image, NOW())";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':sender_id', $sender_id);
            $stmt->bindParam(':receiver_id', $receiver_id);
            $stmt->bindParam(':message', $censorResult['message']);
            $stmt->bindParam(':image_url', $image_url);
            $stmt->bindParam(':has_image', $has_image, PDO::PARAM_BOOL);
            
            if($stmt->execute()) {
                $message_id = $this->db->lastInsertId();
                
                // Increment message count for free users
                if(!$canSend['is_premium']) {
                    $query = "UPDATE message_limits SET messages_sent_today = messages_sent_today + 1 WHERE user_id = :user_id";
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':user_id', $sender_id);
                    $stmt->execute();
                }
                
                // Update conversation
                $this->updateConversation($sender_id, $receiver_id, $message_id);
                
                // Create notification
                $this->createNotification($receiver_id, $sender_id, $message_id);
                
                // Get updated limit info
                $limitInfo = $this->canSendMessage($sender_id);
                
                // AI Scam Detection
                $scamDetector = new ScamDetector($this->db);
                $recentMessages = $this->getConversation($sender_id, $receiver_id, 10);
                $scamResult = $scamDetector->analyzeConversation($recentMessages, $sender_id, $receiver_id);
                
                if ($scamResult['is_scam'] && $scamResult['risk_score'] > 0.8) {
                    // High risk scam detected, flag for admin
                    $query = "UPDATE users SET status = 'flagged', moderation_status = 'pending' WHERE id = :id";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute(['id' => $sender_id]);
                }
                
                // Warn if approaching limit
                $warning = null;
                if(!$limitInfo['is_premium'] && $limitInfo['remaining'] <= 5) {
                    $warning = "You have {$limitInfo['remaining']} messages remaining today. Upgrade to Premium for unlimited messaging!";
                }
                
                return [
                    'success' => true,
                    'message_id' => $message_id,
                    'limit_info' => $limitInfo,
                    'warning' => $warning
                ];
            }
            
            return ['success' => false, 'error' => 'Failed to send message'];
        } catch(PDOException $e) {
            error_log("Error sending message: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
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
                  OR (m.sender_id = :user2 AND m.receiver_id = :user1))
                  AND ((m.sender_id = :user1_check AND m.deleted_by_sender = FALSE)
                  OR (m.receiver_id = :user1_check2 AND m.deleted_by_receiver = FALSE))
                  ORDER BY m.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user1', $user1_id, PDO::PARAM_INT);
            $stmt->bindParam(':user2', $user2_id, PDO::PARAM_INT);
            $stmt->bindParam(':user1_check', $user1_id, PDO::PARAM_INT);
            $stmt->bindParam(':user1_check2', $user1_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $messages = $stmt->fetchAll();
            return array_reverse($messages);
        } catch(PDOException $e) {
            error_log("Error getting conversation: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user's conversations list
     */
    public function getConversationsList($user_id, $limit = 50) {
        $query = "SELECT DISTINCT
                  CASE 
                    WHEN m.sender_id = :user_id THEN m.receiver_id 
                    ELSE m.sender_id 
                  END as contact_id,
                  u.username, u.is_online, u.last_seen,
                  (SELECT message FROM messages 
                   WHERE ((sender_id = contact_id AND receiver_id = :user_id2) 
                   OR (sender_id = :user_id3 AND receiver_id = contact_id))
                   AND ((sender_id = contact_id AND deleted_by_sender = FALSE)
                   OR (receiver_id = :user_id4 AND deleted_by_receiver = FALSE))
                   ORDER BY created_at DESC LIMIT 1) as last_message,
                  (SELECT created_at FROM messages 
                   WHERE ((sender_id = contact_id AND receiver_id = :user_id5) 
                   OR (sender_id = :user_id6 AND receiver_id = contact_id))
                   ORDER BY created_at DESC LIMIT 1) as last_message_time,
                  (SELECT COUNT(*) FROM messages 
                   WHERE sender_id = contact_id 
                   AND receiver_id = :user_id7
                   AND is_read = FALSE
                   AND deleted_by_receiver = FALSE) as unread_count
                  FROM messages m
                  LEFT JOIN users u ON u.id = CASE 
                    WHEN m.sender_id = :user_id8 THEN m.receiver_id 
                    ELSE m.sender_id 
                  END
                  WHERE (m.sender_id = :user_id9 OR m.receiver_id = :user_id10)
                  AND u.id IS NOT NULL
                  ORDER BY last_message_time DESC
                  LIMIT :limit";
        
        try {
            $stmt = $this->db->prepare($query);
            for($i = 1; $i <= 10; $i++) {
                $param = ':user_id' . ($i > 1 ? $i : '');
                $stmt->bindParam($param, $user_id, PDO::PARAM_INT);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error getting conversations list: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark messages as read
     */
    public function markAsRead($sender_id, $receiver_id) {
        $query = "UPDATE messages 
                  SET is_read = TRUE, read_at = NOW() 
                  WHERE sender_id = :sender_id 
                  AND receiver_id = :receiver_id 
                  AND is_read = FALSE";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':sender_id', $sender_id);
            $stmt->bindParam(':receiver_id', $receiver_id);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error marking as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread message count
     */
    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(DISTINCT sender_id) as unread_count 
                  FROM messages 
                  WHERE receiver_id = :user_id 
                  AND is_read = FALSE 
                  AND deleted_by_receiver = FALSE";
        
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
     * Upload image for message
     */
    public function uploadImage($file, $user_id) {
        // Check if user can send images
        $query = "SELECT is_premium, can_send_images FROM users WHERE id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if(!$user['is_premium'] && !$user['can_send_images']) {
            return [
                'success' => false,
                'error' => 'Image sharing is only available for Premium members',
                'upgrade_required' => true
            ];
        }
        
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
     * Check if user is blocked
     */
    private function isBlocked($user_id, $other_user_id) {
        try {
            $query = "SELECT id FROM blocked_users 
                      WHERE (blocker_id = :user1 AND blocked_id = :user2)
                      OR (blocker_id = :user2 AND blocked_id = :user1)
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user1', $user_id);
            $stmt->bindParam(':user2', $other_user_id);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Update conversation record
     */
    private function updateConversation($user1_id, $user2_id, $message_id) {
        try {
            $query = "INSERT INTO conversations (user1_id, user2_id, last_message_id, last_message_at)
                      VALUES (:user1, :user2, :msg_id, NOW())
                      ON DUPLICATE KEY UPDATE 
                      last_message_id = :msg_id2,
                      last_message_at = NOW()";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user1', $user1_id);
            $stmt->bindParam(':user2', $user2_id);
            $stmt->bindParam(':msg_id', $message_id);
            $stmt->bindParam(':msg_id2', $message_id);
            $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error updating conversation: " . $e->getMessage());
        }
    }
    
    /**
     * Create notification
     */
    private function createNotification($user_id, $from_user_id, $message_id) {
        try {
            $query = "INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                      VALUES (:user_id, 'message', 'You have a new message', :message_id, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':message_id', $message_id);
            $stmt->execute();
        } catch(PDOException $e) {
            // Notifications table might not exist, ignore
        }
    }
}
?>