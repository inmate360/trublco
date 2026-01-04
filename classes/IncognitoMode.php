<?php
/**
 * IncognitoMode Class - Anonymous browsing functionality
 */
class IncognitoMode {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Enable incognito mode
    public function enable($user_id, $duration_hours = 24) {
        // Check if user has active subscription (premium feature)
        $query = "SELECT us.plan_id, mp.features 
                  FROM user_subscriptions us
                  LEFT JOIN membership_plans mp ON us.plan_id = mp.id
                  WHERE us.user_id = :user_id AND us.status = 'active'
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $subscription = $stmt->fetch();
        
        // Check if user has incognito access (premium/VIP only)
        if(!$subscription || $subscription['plan_id'] < 3) {
            return ['success' => false, 'error' => 'Incognito mode requires Premium or VIP membership'];
        }
        
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_hours} hours"));
        
        // Create incognito session
        $query = "INSERT INTO incognito_sessions (user_id, expires_at, is_active)
                  VALUES (:user_id, :expires_at, TRUE)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':expires_at', $expires_at);
        
        if($stmt->execute()) {
            // Update user settings
            $query = "UPDATE user_settings SET incognito_mode = TRUE WHERE user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            return ['success' => true, 'expires_at' => $expires_at];
        }
        
        return ['success' => false, 'error' => 'Failed to enable incognito mode'];
    }

    // Disable incognito mode
    public function disable($user_id) {
        $query = "UPDATE incognito_sessions SET is_active = FALSE 
                  WHERE user_id = :user_id AND is_active = TRUE";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Update user settings
        $query = "UPDATE user_settings SET incognito_mode = FALSE WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        
        return $stmt->execute();
    }

    // Check if user is in incognito mode
    public function isActive($user_id) {
        $query = "SELECT * FROM incognito_sessions 
                  WHERE user_id = :user_id 
                  AND is_active = TRUE 
                  AND expires_at > NOW()
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    // Get incognito session details
    public function getSession($user_id) {
        $query = "SELECT * FROM incognito_sessions 
                  WHERE user_id = :user_id 
                  AND is_active = TRUE 
                  AND expires_at > NOW()
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetch();
    }

    // Should hide profile view (if viewer is in incognito)
    public function shouldHideView($viewer_id) {
        return $this->isActive($viewer_id);
    }

    // Clean up expired sessions
    public function cleanupExpiredSessions() {
        $query = "UPDATE incognito_sessions SET is_active = FALSE 
                  WHERE is_active = TRUE AND expires_at < NOW()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        // Also update user settings
        $query = "UPDATE user_settings us
                  INNER JOIN incognito_sessions ins ON us.user_id = ins.user_id
                  SET us.incognito_mode = FALSE
                  WHERE ins.is_active = FALSE AND us.incognito_mode = TRUE";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
    }
}
?>