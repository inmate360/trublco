<?php
class SpamProtection {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->createTable();
    }
    
    private function createTable() {
        $query = "CREATE TABLE IF NOT EXISTS blocked_ips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            reason TEXT,
            blocked_by INT NULL,
            blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            UNIQUE KEY unique_ip (ip_address),
            INDEX idx_ip (ip_address),
            INDEX idx_expires (expires_at),
            FOREIGN KEY (blocked_by) REFERENCES users(id) ON DELETE SET NULL
        )";
        
        try {
            $this->db->exec($query);
        } catch(PDOException $e) {
            error_log("Error creating blocked_ips table: " . $e->getMessage());
        }
    }
    
    public function isBlocked($ip = null) {
        if($ip === null) {
            $ip = RateLimiter::getClientIP();
        }
        
        // Clean expired blocks
        $this->cleanExpired();
        
        $query = "SELECT id, reason, expires_at 
                  FROM blocked_ips 
                  WHERE ip_address = :ip 
                  AND (expires_at IS NULL OR expires_at > NOW())
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ip', $ip);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function blockIP($ip, $reason, $duration = null, $blockedBy = null) {
        $expiresAt = null;
        if($duration !== null) {
            $expiresAt = date('Y-m-d H:i:s', time() + $duration);
        }
        
        $query = "INSERT INTO blocked_ips (ip_address, reason, blocked_by, expires_at) 
                  VALUES (:ip, :reason, :blocked_by, :expires_at)
                  ON DUPLICATE KEY UPDATE 
                  reason = :reason2, 
                  blocked_by = :blocked_by2,
                  expires_at = :expires_at2,
                  blocked_at = NOW()";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':blocked_by', $blockedBy);
        $stmt->bindParam(':expires_at', $expiresAt);
        $stmt->bindParam(':reason2', $reason);
        $stmt->bindParam(':blocked_by2', $blockedBy);
        $stmt->bindParam(':expires_at2', $expiresAt);
        
        return $stmt->execute();
    }
    
    public function unblockIP($ip) {
        $query = "DELETE FROM blocked_ips WHERE ip_address = :ip";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ip', $ip);
        return $stmt->execute();
    }
    
    public function detectSpamPatterns($content) {
        $spamPatterns = [
            '/viagra|cialis|pharmacy/i',
            '/\b(cash|money|lottery|winner|prize)\b.*\b(click|claim|call)\b/i',
            '/(buy|cheap|discount).*\b(now|today)\b/i',
            '/\b(weight loss|diet pills)\b/i',
            '/(http[s]?:\/\/[^\s]+){3,}/i', // Multiple URLs
            '/(.)\1{10,}/', // Repeated characters
            '/[A-Z]{20,}/' // All caps
        ];
        
        foreach($spamPatterns as $pattern) {
            if(preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    public function checkUserBehavior($user_id) {
        // Check rapid posting
        $query = "SELECT COUNT(*) as count 
                  FROM listings 
                  WHERE user_id = :user_id 
                  AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if($result['count'] > 10) {
            return ['suspicious' => true, 'reason' => 'Rapid posting detected'];
        }
        
        // Check identical content
        $query = "SELECT description 
                  FROM listings 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC 
                  LIMIT 5";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $listings = $stmt->fetchAll();
        
        $descriptions = array_column($listings, 'description');
        if(count($descriptions) > 1 && count(array_unique($descriptions)) === 1) {
            return ['suspicious' => true, 'reason' => 'Duplicate content detected'];
        }
        
        return ['suspicious' => false];
    }
    
    private function cleanExpired() {
        $query = "DELETE FROM blocked_ips 
                  WHERE expires_at IS NOT NULL 
                  AND expires_at < NOW()";
        
        try {
            $this->db->exec($query);
        } catch(PDOException $e) {
            error_log("Error cleaning expired blocks: " . $e->getMessage());
        }
    }
}
?>