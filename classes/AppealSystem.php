<?php
class AppealSystem {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Submit an appeal
     */
    public function submitAppeal($user_id, $appeal_type, $related_id, $reason, $evidence = null) {
        // Check if user already has pending appeal for same item
        $query = "SELECT id FROM moderation_appeals 
                  WHERE user_id = :user_id 
                  AND appeal_type = :appeal_type 
                  AND related_id = :related_id 
                  AND status IN ('pending', 'reviewing')
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':appeal_type', $appeal_type);
        $stmt->bindParam(':related_id', $related_id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return ['success' => false, 'error' => 'You already have a pending appeal for this item'];
        }
        
        // Create appeal
        $query = "INSERT INTO moderation_appeals 
                  (user_id, appeal_type, related_id, reason, supporting_evidence, status, created_at)
                  VALUES (:user_id, :appeal_type, :related_id, :reason, :evidence, 'pending', NOW())";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':appeal_type', $appeal_type);
            $stmt->bindParam(':related_id', $related_id);
            $stmt->bindParam(':reason', $reason);
            $stmt->bindParam(':evidence', $evidence);
            
            if($stmt->execute()) {
                return [
                    'success' => true,
                    'appeal_id' => $this->db->lastInsertId(),
                    'message' => 'Appeal submitted successfully. We will review it within 48 hours.'
                ];
            }
            
            return ['success' => false, 'error' => 'Failed to submit appeal'];
        } catch(PDOException $e) {
            error_log("Error submitting appeal: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get user's appeals
     */
    public function getUserAppeals($user_id) {
        $query = "SELECT a.*, 
                  CASE 
                    WHEN a.appeal_type = 'warning' THEN (SELECT message FROM user_warnings WHERE id = a.related_id)
                    WHEN a.appeal_type = 'content_removal' THEN (SELECT title FROM listings WHERE id = a.related_id)
                    ELSE NULL
                  END as related_details,
                  m.username as reviewer_name
                  FROM moderation_appeals a
                  LEFT JOIN users m ON a.reviewed_by = m.id
                  WHERE a.user_id = :user_id
                  ORDER BY a.created_at DESC";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            return ['success' => true, 'appeals' => $stmt->fetchAll()];
        } catch(PDOException $e) {
            error_log("Error getting user appeals: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get pending appeals for review
     */
    public function getPendingAppeals($limit = 50) {
        $query = "SELECT a.*, u.username, u.email,
                  CASE 
                    WHEN a.appeal_type = 'warning' THEN (SELECT message FROM user_warnings WHERE id = a.related_id)
                    WHEN a.appeal_type = 'suspension' THEN 'Account suspended'
                    WHEN a.appeal_type = 'ban' THEN 'Account banned'
                    WHEN a.appeal_type = 'content_removal' THEN (SELECT title FROM listings WHERE id = a.related_id)
                  END as issue_details
                  FROM moderation_appeals a
                  LEFT JOIN users u ON a.user_id = u.id
                  WHERE a.status IN ('pending', 'reviewing')
                  ORDER BY a.created_at ASC
                  LIMIT :limit";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return ['success' => true, 'appeals' => $stmt->fetchAll()];
        } catch(PDOException $e) {
            error_log("Error getting pending appeals: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Review and resolve appeal
     */
    public function resolveAppeal($appeal_id, $moderator_id, $decision, $notes = null) {
        if(!in_array($decision, ['approved', 'rejected'])) {
            return ['success' => false, 'error' => 'Invalid decision'];
        }
        
        try {
            $this->db->beginTransaction();
            
            // Get appeal details
            $query = "SELECT * FROM moderation_appeals WHERE id = :appeal_id LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':appeal_id', $appeal_id);
            $stmt->execute();
            $appeal = $stmt->fetch();
            
            if(!$appeal) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Appeal not found'];
            }
            
            // Update appeal status
            $query = "UPDATE moderation_appeals 
                      SET status = :decision,
                          reviewed_by = :moderator_id,
                          reviewer_notes = :notes,
                          resolved_at = NOW()
                      WHERE id = :appeal_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':decision', $decision);
            $stmt->bindParam(':moderator_id', $moderator_id);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':appeal_id', $appeal_id);
            $stmt->execute();
            
            // If approved, reverse the action
            if($decision == 'approved') {
                $this->reverseAction($appeal);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "Appeal {$decision}",
                'action_reversed' => $decision == 'approved'
            ];
        } catch(PDOException $e) {
            $this->db->rollBack();
            error_log("Error resolving appeal: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Reverse moderation action after appeal approval
     */
    private function reverseAction($appeal) {
        switch($appeal['appeal_type']) {
            case 'warning':
                // Remove warning
                $query = "DELETE FROM user_warnings WHERE id = :related_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':related_id', $appeal['related_id']);
                $stmt->execute();
                
                // Decrease warning count
                $query = "UPDATE users SET warning_count = GREATEST(0, warning_count - 1) WHERE id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':user_id', $appeal['user_id']);
                $stmt->execute();
                break;
                
            case 'suspension':
                // Lift suspension
                $query = "UPDATE users SET is_suspended = FALSE WHERE id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':user_id', $appeal['user_id']);
                $stmt->execute();
                break;
                
            case 'ban':
                // Lift ban
                $query = "UPDATE users SET is_banned = FALSE WHERE id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':user_id', $appeal['user_id']);
                $stmt->execute();
                break;
                
            case 'content_removal':
                // Restore content
                $query = "UPDATE listings SET status = 'active', moderation_status = 'approved' WHERE id = :related_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':related_id', $appeal['related_id']);
                $stmt->execute();
                break;
        }
    }
}
?>