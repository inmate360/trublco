<?php
class RateLimiter {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->createTable();
    }
    
    private function createTable() {
        $query = "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            action VARCHAR(50) NOT NULL,
            attempts INT DEFAULT 1,
            last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            blocked_until TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_identifier_action (identifier, action),
            INDEX idx_blocked (blocked_until)
        )";
        
        try {
            $this->db->exec($query);
        } catch(PDOException $e) {
            error_log("Error creating rate_limits table: " . $e->getMessage());
        }
    }
    
    public function checkLimit($identifier, $action, $maxAttempts = 5, $timeWindow = 300) {
        // Clean old records
        $this->cleanup();
        
        // Get current attempts
        $query = "SELECT attempts, blocked_until, last_attempt 
                  FROM rate_limits 
                  WHERE identifier = :identifier AND action = :action 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':identifier', $identifier);
        $stmt->bindParam(':action', $action);
        $stmt->execute();
        
        $record = $stmt->fetch();
        
        // Check if blocked
        if($record && $record['blocked_until']) {
            if(strtotime($record['blocked_until']) > time()) {
                $remaining = strtotime($record['blocked_until']) - time();
                return [
                    'allowed' => false,
                    'reason' => 'Too many attempts',
                    'retry_after' => $remaining
                ];
            } else {
                // Block expired, reset
                $this->reset($identifier, $action);
                $record = null;
            }
        }
        
        // Check if within time window
        if($record) {
            $timeSinceLastAttempt = time() - strtotime($record['last_attempt']);
            
            if($timeSinceLastAttempt > $timeWindow) {
                // Reset counter if outside time window
                $this->reset($identifier, $action);
                return ['allowed' => true, 'remaining' => $maxAttempts - 1];
            }
            
            // Check if exceeded max attempts
            if($record['attempts'] >= $maxAttempts) {
                // Block for exponential backoff
                $blockDuration = min(3600, $timeWindow * pow(2, $record['attempts'] - $maxAttempts));
                $this->block($identifier, $action, $blockDuration);
                
                return [
                    'allowed' => false,
                    'reason' => 'Rate limit exceeded',
                    'retry_after' => $blockDuration
                ];
            }
        }
        
        // Increment or create record
        $this->increment($identifier, $action);
        
        $remaining = $maxAttempts - ($record ? $record['attempts'] + 1 : 1);
        return ['allowed' => true, 'remaining' => $remaining];
    }
    
    private function increment($identifier, $action) {
        $query = "INSERT INTO rate_limits (identifier, action, attempts, last_attempt) 
                  VALUES (:identifier, :action, 1, NOW())
                  ON DUPLICATE KEY UPDATE 
                  attempts = attempts + 1, 
                  last_attempt = NOW()";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':identifier', $identifier);
        $stmt->bindParam(':action', $action);
        $stmt->execute();
    }
    
    private function block($identifier, $action, $duration) {
        $blockedUntil = date('Y-m-d H:i:s', time() + $duration);
        
        $query = "UPDATE rate_limits 
                  SET blocked_until = :blocked_until 
                  WHERE identifier = :identifier AND action = :action";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':blocked_until', $blockedUntil);
        $stmt->bindParam(':identifier', $identifier);
        $stmt->bindParam(':action', $action);
        $stmt->execute();
    }
    
    private function reset($identifier, $action) {
        $query = "DELETE FROM rate_limits 
                  WHERE identifier = :identifier AND action = :action";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':identifier', $identifier);
        $stmt->bindParam(':action', $action);
        $stmt->execute();
    }
    
    private function cleanup() {
        // Clean records older than 24 hours
        $query = "DELETE FROM rate_limits 
                  WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        try {
            $this->db->exec($query);
        } catch(PDOException $e) {
            error_log("Rate limit cleanup error: " . $e->getMessage());
        }
    }
    
    public static function getIdentifier() {
        // Use IP + User Agent for anonymous users
        $ip = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return hash('sha256', $ip . $userAgent);
    }
    
    public static function getClientIP() {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        ];
        
        foreach($headers as $header) {
            if(isset($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Get first IP if comma-separated
                if(strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if(filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
}
?>