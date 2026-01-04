<?php
class MessageLimits {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function canSendMessage($user_id) {
        // Check if user is premium
        if($this->isPremium($user_id)) {
            return [
                'can_send' => true,
                'messages_left' => 'unlimited',
                'is_premium' => true
            ];
        }
        
        // Reset counter if it's a new day
        $this->resetIfNewDay($user_id);
        
        // Get user's message count
        $query = "SELECT messages_sent_today FROM users WHERE id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        $messages_sent = $user['messages_sent_today'] ?? 0;
        $limit = $this->getMessageLimit();
        $messages_left = max(0, $limit - $messages_sent);
        
        return [
            'can_send' => $messages_sent < $limit,
            'messages_left' => $messages_left,
            'messages_sent' => $messages_sent,
            'limit' => $limit,
            'is_premium' => false
        ];
    }
    
    public function incrementMessageCount($user_id) {
        $query = "UPDATE users 
                  SET messages_sent_today = messages_sent_today + 1 
                  WHERE id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }
    
    private function resetIfNewDay($user_id) {
        $query = "SELECT last_message_reset FROM users WHERE id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        $today = date('Y-m-d');
        $last_reset = $user['last_message_reset'] ?? null;
        
        if($last_reset !== $today) {
            $query = "UPDATE users 
                      SET messages_sent_today = 0, last_message_reset = :today 
                      WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':today', $today);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
        }
    }
    
    private function isPremium($user_id) {
        try {
            $query = "SELECT id FROM user_subscriptions 
                      WHERE user_id = :user_id 
                      AND status = 'active' 
                      AND (end_date IS NULL OR end_date > NOW())
                      LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    private function getMessageLimit() {
        try {
            $query = "SELECT setting_value FROM site_settings WHERE setting_key = 'free_message_limit' LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return (int)($result['setting_value'] ?? 5);
        } catch(PDOException $e) {
            return 5; // Default limit
        }
    }
}
?>