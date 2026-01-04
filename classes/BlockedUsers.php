<?php
class BlockedUsers {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function blockUser($blocker_id, $blocked_id, $reason = null) {
        if($blocker_id == $blocked_id) {
            return ['success' => false, 'error' => 'Cannot block yourself'];
        }
        
        $query = "INSERT INTO blocked_users (blocker_id, blocked_id, reason, created_at) 
                  VALUES (:blocker_id, :blocked_id, :reason, NOW())
                  ON DUPLICATE KEY UPDATE reason = :reason2, created_at = NOW()";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':blocker_id', $blocker_id);
            $stmt->bindParam(':blocked_id', $blocked_id);
            $stmt->bindParam(':reason', $reason);
            $stmt->bindParam(':reason2', $reason);
            
            if($stmt->execute()) {
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Failed to block user'];
        } catch(PDOException $e) {
            error_log("Error blocking user: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    public function unblockUser($blocker_id, $blocked_id) {
        $query = "DELETE FROM blocked_users 
                  WHERE blocker_id = :blocker_id AND blocked_id = :blocked_id";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':blocker_id', $blocker_id);
            $stmt->bindParam(':blocked_id', $blocked_id);
            
            if($stmt->execute()) {
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Failed to unblock user'];
        } catch(PDOException $e) {
            error_log("Error unblocking user: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    public function isBlocked($blocker_id, $blocked_id) {
        $query = "SELECT id FROM blocked_users 
                  WHERE blocker_id = :blocker_id AND blocked_id = :blocked_id 
                  LIMIT 1";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':blocker_id', $blocker_id);
            $stmt->bindParam(':blocked_id', $blocked_id);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function isBlockedByEither($user1_id, $user2_id) {
        return $this->isBlocked($user1_id, $user2_id) || $this->isBlocked($user2_id, $user1_id);
    }
    
    public function getBlockedUsers($user_id) {
        $query = "SELECT bu.*, u.username, u.created_at as user_created 
                  FROM blocked_users bu
                  LEFT JOIN users u ON bu.blocked_id = u.id
                  WHERE bu.blocker_id = :user_id
                  ORDER BY bu.created_at DESC";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error getting blocked users: " . $e->getMessage());
            return [];
        }
    }
}
?>