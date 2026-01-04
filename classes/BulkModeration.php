<?php
class BulkModeration {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Process bulk action on multiple items
     */
    public function processBulkAction($action_type, $content_type, $content_ids, $reason, $moderator_id) {
        if(empty($content_ids) || !is_array($content_ids)) {
            return ['success' => false, 'error' => 'No items selected'];
        }
        
        $results = [
            'success' => 0,
            'failed' => 0,
            'items' => []
        ];
        
        try {
            $this->db->beginTransaction();
            
            foreach($content_ids as $content_id) {
                $result = $this->processAction($action_type, $content_type, $content_id, $reason, $moderator_id);
                
                if($result['success']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
                
                $results['items'][$content_id] = $result;
            }
            
            // Log bulk action
            $query = "INSERT INTO bulk_moderation_queue 
                      (action_type, content_type, content_ids, reason, created_by, processed, processed_at, results)
                      VALUES (:action_type, :content_type, :content_ids, :reason, :moderator_id, TRUE, NOW(), :results)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':action_type', $action_type);
            $stmt->bindParam(':content_type', $content_type);
            $stmt->bindValue(':content_ids', json_encode($content_ids));
            $stmt->bindParam(':reason', $reason);
            $stmt->bindParam(':moderator_id', $moderator_id);
            $stmt->bindValue(':results', json_encode($results));
            $stmt->execute();
            
            $this->db->commit();
            
            return [
                'success' => true,
                'processed' => $results['success'],
                'failed' => $results['failed'],
                'details' => $results['items']
            ];
        } catch(PDOException $e) {
            $this->db->rollBack();
            error_log("Bulk moderation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Process single action
     */
    private function processAction($action_type, $content_type, $content_id, $reason, $moderator_id) {
        switch($action_type) {
            case 'approve':
                return $this->approveContent($content_type, $content_id, $moderator_id);
                
            case 'reject':
                return $this->rejectContent($content_type, $content_id, $reason, $moderator_id);
                
            case 'delete':
                return $this->deleteContent($content_type, $content_id, $reason, $moderator_id);
                
            case 'warn':
                return $this->warnUser($content_type, $content_id, $reason, $moderator_id);
                
            case 'suspend':
                return $this->suspendUser($content_id, $reason, $moderator_id);
                
            default:
                return ['success' => false, 'error' => 'Invalid action type'];
        }
    }
    
    private function approveContent($content_type, $content_id, $moderator_id) {
        try {
            if($content_type == 'listing') {
                $query = "UPDATE listings SET status = 'active', moderation_status = 'approved' WHERE id = :id";
            } elseif($content_type == 'image') {
                $query = "UPDATE image_moderation SET status = 'approved', reviewed_by = :mod_id, reviewed_at = NOW() WHERE id = :id";
            } else {
                return ['success' => false, 'error' => 'Unsupported content type'];
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $content_id);
            if($content_type == 'image') {
                $stmt->bindParam(':mod_id', $moderator_id);
            }
            $stmt->execute();
            
            // Log action
            $this->logModerationAction($content_type, $content_id, $moderator_id, 'approved', null);
            
            return ['success' => true];
        } catch(PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function rejectContent($content_type, $content_id, $reason, $moderator_id) {
        try {
            if($content_type == 'listing') {
                $query = "UPDATE listings SET status = 'rejected', moderation_status = 'rejected', moderation_notes = :reason WHERE id = :id";
            } elseif($content_type == 'image') {
                $query = "UPDATE image_moderation SET status = 'rejected', reviewed_by = :mod_id, reviewed_at = NOW() WHERE id = :id";
            } else {
                return ['success' => false, 'error' => 'Unsupported content type'];
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $content_id);
            $stmt->bindParam(':reason', $reason);
            if($content_type == 'image') {
                $stmt->bindParam(':mod_id', $moderator_id);
            }
            $stmt->execute();
            
            // Log action
            $this->logModerationAction($content_type, $content_id, $moderator_id, 'rejected', $reason);
            
            return ['success' => true];
        } catch(PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function deleteContent($content_type, $content_id, $reason, $moderator_id) {
        try {
            if($content_type == 'listing') {
                $query = "DELETE FROM listings WHERE id = :id";
            } elseif($content_type == 'message') {
                $query = "DELETE FROM messages WHERE id = :id";
            } else {
                return ['success' => false, 'error' => 'Unsupported content type'];
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $content_id);
            $stmt->execute();
            
            // Log action
            $this->logModerationAction($content_type, $content_id, $moderator_id, 'deleted', $reason);
            
            return ['success' => true];
        } catch(PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function warnUser($content_type, $content_id, $reason, $moderator_id) {
        try {
            // Get user_id from content
            $user_id = $this->getUserIdFromContent($content_type, $content_id);
            
            if(!$user_id) {
                return ['success' => false, 'error' => 'User not found'];
            }
            
            $query = "INSERT INTO user_warnings 
                      (user_id, warning_type, severity, content_type, content_id, message, issued_by)
                      VALUES (:user_id, 'terms_violation', 'medium', :content_type, :content_id, :message, :moderator_id)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':content_type', $content_type);
            $stmt->bindParam(':content_id', $content_id);
            $stmt->bindParam(':message', $reason);
            $stmt->bindParam(':moderator_id', $moderator_id);
            $stmt->execute();
            
            // Update user warning count
            $query = "UPDATE users SET warning_count = warning_count + 1, last_warning_at = NOW() WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Log action
            $this->logModerationAction($content_type, $content_id, $moderator_id, 'warned', $reason);
            
            return ['success' => true];
        } catch(PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function suspendUser($user_id, $reason, $moderator_id) {
        try {
            $query = "UPDATE users SET is_suspended = TRUE WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Log action
            $this->logModerationAction('user', $user_id, $moderator_id, 'suspended', $reason);
            
            return ['success' => true];
        } catch(PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function getUserIdFromContent($content_type, $content_id) {
        $query = "";
        
        switch($content_type) {
            case 'listing':
                $query = "SELECT user_id FROM listings WHERE id = :id LIMIT 1";
                break;
            case 'message':
                $query = "SELECT sender_id as user_id FROM messages WHERE id = :id LIMIT 1";
                break;
            default:
                return null;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $content_id);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result ? $result['user_id'] : null;
    }
    
    private function logModerationAction($content_type, $content_id, $moderator_id, $action, $reason) {
        $query = "INSERT INTO moderation_logs 
                  (content_type, content_id, moderator_id, action, reason, is_automated)
                  VALUES (:content_type, :content_id, :moderator_id, :action, :reason, FALSE)";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':content_type', $content_type);
            $stmt->bindParam(':content_id', $content_id);
            $stmt->bindParam(':moderator_id', $moderator_id);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':reason', $reason);
            $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error logging moderation action: " . $e->getMessage());
        }
    }
}
?>