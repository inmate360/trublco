<?php
class SecurityLogger {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->createTable();
    }
    
    private function createTable() {
        $query = "CREATE TABLE IF NOT EXISTS security_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            ip_address VARCHAR(45) NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user (user_id),
            INDEX idx_ip (ip_address),
            INDEX idx_action (action),
            INDEX idx_severity (severity),
            INDEX idx_created (created_at DESC)
        )";
        
        try {
            $this->db->exec($query);
        } catch(PDOException $e) {
            error_log("Error creating security_logs table: " . $e->getMessage());
        }
    }
    
    public function log($action, $details = null, $severity = 'low', $user_id = null) {
        $ip = RateLimiter::getClientIP();
        
        if($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        $query = "INSERT INTO security_logs (user_id, ip_address, action, details, severity, created_at) 
                  VALUES (:user_id, :ip, :action, :details, :severity, NOW())";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':ip', $ip);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':details', $details);
            $stmt->bindParam(':severity', $severity);
            $stmt->execute();
            
            // Also log to file for critical events
            if($severity === 'critical') {
                error_log("SECURITY CRITICAL: {$action} - IP: {$ip} - User: {$user_id} - {$details}");
            }
            
            return true;
        } catch(PDOException $e) {
            error_log("Error logging security event: " . $e->getMessage());
            return false;
        }
    }
    
    public function getRecentLogs($limit = 100, $severity = null) {
        $query = "SELECT sl.*, u.username 
                  FROM security_logs sl
                  LEFT JOIN users u ON sl.user_id = u.id";
        
        if($severity) {
            $query .= " WHERE sl.severity = :severity";
        }
        
        $query .= " ORDER BY sl.created_at DESC LIMIT :limit";
        
        try {
            $stmt = $this->db->prepare($query);
            if($severity) {
                $stmt->bindParam(':severity', $severity);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error fetching security logs: " . $e->getMessage());
            return [];
        }
    }
    
    public function getUserLogs($user_id, $limit = 50) {
        $query = "SELECT * FROM security_logs 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC 
                  LIMIT :limit";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error fetching user logs: " . $e->getMessage());
            return [];
        }
    }
}
?>