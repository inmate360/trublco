<?php
class Moderator {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function isModerator($user_id) {
        try {
            $query = "SELECT is_admin, is_moderator FROM users WHERE id = :user_id LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $user = $stmt->fetch();
            return $user && ($user['is_admin'] || ($user['is_moderator'] ?? false));
        } catch(PDOException $e) {
            error_log("Error checking moderator status: " . $e->getMessage());
            return false;
        }
    }
    
    // ==================== LISTING MODERATION ====================
    
    public function getPendingListings() {
        try {
            $query = "SELECT l.*, u.username, c.name as category_name, ct.name as city_name, s.abbreviation as state_abbr
                      FROM listings l
                      LEFT JOIN users u ON l.user_id = u.id
                      LEFT JOIN categories c ON l.category_id = c.id
                      LEFT JOIN cities ct ON l.city_id = ct.id
                      LEFT JOIN states s ON ct.state_id = s.id
                      WHERE l.status = 'pending'
                      ORDER BY l.created_at ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error getting pending listings: " . $e->getMessage());
            return [];
        }
    }
    
    public function getFlaggedListings() {
        try {
            $query = "SELECT l.*, u.username, 
                      COUNT(r.id) as report_count,
                      MAX(r.created_at) as last_report
                      FROM listings l
                      LEFT JOIN users u ON l.user_id = u.id
                      LEFT JOIN reports r ON r.reported_type = 'listing' AND r.reported_id = l.id AND r.status = 'pending'
                      WHERE l.status = 'active'
                      GROUP BY l.id
                      HAVING report_count > 0
                      ORDER BY report_count DESC, last_report DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error getting flagged listings: " . $e->getMessage());
            return [];
        }
    }
    
    public function approveListing($listing_id, $moderator_id) {
        try {
            $query = "UPDATE listings SET status = 'active' WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $listing_id);
            
            if($stmt->execute()) {
                $this->logAction($moderator_id, 'listing', $listing_id, 'approved');
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Error approving listing: " . $e->getMessage());
            return false;
        }
    }
    
    public function rejectListing($listing_id, $moderator_id, $reason) {
        try {
            $query = "UPDATE listings SET status = 'rejected' WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $listing_id);
            
            if($stmt->execute()) {
                $this->logAction($moderator_id, 'listing', $listing_id, 'rejected', $reason);
                // TODO: Send notification to user
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Error rejecting listing: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteListing($listing_id) {
        try {
            $query = "DELETE FROM listings WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $listing_id);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error deleting listing: " . $e->getMessage());
            return false;
        }
    }
    
    public function getRecentListingActions($limit = 20) {
        try {
            $query = "SELECT ma.*, l.title, u.username as moderator_name
                      FROM moderation_actions ma
                      LEFT JOIN listings l ON ma.target_id = l.id
                      LEFT JOIN users u ON ma.moderator_id = u.id
                      WHERE ma.target_type = 'listing'
                      ORDER BY ma.action_date DESC
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error getting listing actions: " . $e->getMessage());
            return [];
        }
    }
    
    // ==================== USER MODERATION ====================
    
    public function getFlaggedUsers() {
        try {
            $query = "SELECT u.*, 
                      COUNT(r.id) as report_count,
                      MAX(r.created_at) as last_report
                      FROM users u
                      LEFT JOIN reports r ON r.reported_type = 'user' AND r.reported_id = u.id AND r.status = 'pending'
                      WHERE u.is_suspended = FALSE OR u.is_suspended IS NULL
                      GROUP BY u.id
                      HAVING report_count > 0
                      ORDER BY report_count DESC, last_report DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error getting flagged users: " . $e->getMessage());
            return [];
        }
    }
    
    public function getSuspendedUsers() {
        try {
            $query = "SELECT u.*, mu.suspension_reason, mu.suspension_end, 
                      mod.username as moderator_name
                      FROM users u
                      LEFT JOIN moderated_users mu ON u.id = mu.user_id
                      LEFT JOIN users mod ON mu.moderated_by = mod.id
                      WHERE u.is_suspended = TRUE AND mu.suspension_end > NOW()
                      ORDER BY mu.suspension_end ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error getting suspended users: " . $e->getMessage());
            return [];
        }
    }
    
    public function suspendUser($user_id, $moderator_id, $reason, $duration_days) {
        try {
            // Ensure is_suspended column exists
            $this->ensureUserModerationColumns();
            
            // Update user status
            $query = "UPDATE users SET is_suspended = TRUE WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Create moderated_users table if needed
            $this->ensureModeratedUsersTable();
            
            // Insert/Update suspension record
            $suspension_end = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
            $query = "INSERT INTO moderated_users (user_id, moderated_by, suspension_reason, suspension_end, created_at)
                      VALUES (:user_id, :moderator_id, :reason, :suspension_end, NOW())
                      ON DUPLICATE KEY UPDATE 
                      suspension_reason = :reason2, 
                      suspension_end = :suspension_end2,
                      moderated_by = :moderator_id2";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':moderator_id', $moderator_id);
            $stmt->bindParam(':reason', $reason);
            $stmt->bindParam(':suspension_end', $suspension_end);
            $stmt->bindParam(':reason2', $reason);
            $stmt->bindParam(':suspension_end2', $suspension_end);
            $stmt->bindParam(':moderator_id2', $moderator_id);
            
            if($stmt->execute()) {
                $this->logAction($moderator_id, 'user', $user_id, 'suspended', $reason);
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Error suspending user: " . $e->getMessage());
            return false;
        }
    }
    
    public function unsuspendUser($user_id, $moderator_id) {
        try {
            $query = "UPDATE users SET is_suspended = FALSE WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            
            if($stmt->execute()) {
                $this->logAction($moderator_id, 'user', $user_id, 'unsuspended');
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Error unsuspending user: " . $e->getMessage());
            return false;
        }
    }
    
    public function banUser($user_id, $moderator_id, $reason) {
        try {
            $query = "UPDATE users SET is_suspended = TRUE, is_banned = TRUE WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            
            if($stmt->execute()) {
                $this->logAction($moderator_id, 'user', $user_id, 'banned', $reason);
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Error banning user: " . $e->getMessage());
            return false;
        }
    }
    
    public function warnUser($user_id, $moderator_id, $warning_message) {
        try {
            $this->logAction($moderator_id, 'user', $user_id, 'warned', $warning_message);
            // TODO: Send warning notification to user
            return true;
        } catch(PDOException $e) {
            error_log("Error warning user: " . $e->getMessage());
            return false;
        }
    }
    
    public function getRecentUserActions($limit = 20) {
        try {
            $query = "SELECT ma.*, u.username, mod.username as moderator_name
                      FROM moderation_actions ma
                      LEFT JOIN users u ON ma.target_id = u.id
                      LEFT JOIN users mod ON ma.moderator_id = mod.id
                      WHERE ma.target_type = 'user'
                      ORDER BY ma.action_date DESC
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error getting user actions: " . $e->getMessage());
            return [];
        }
    }
    
    // ==================== REPORT MODERATION ====================
    
    public function getPendingReports() {
        try {
            $query = "SELECT r.*, u.username as reporter_name
                      FROM reports r
                      LEFT JOIN users u ON r.reporter_id = u.id
                      WHERE r.status = 'pending'
                      ORDER BY r.created_at ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error getting pending reports: " . $e->getMessage());
            return [];
        }
    }
    
    public function getRecentResolvedReports($limit = 20) {
        try {
            $query = "SELECT r.*, 
                      reporter.username as reporter_name,
                      mod.username as moderator_name
                      FROM reports r
                      LEFT JOIN users reporter ON r.reporter_id = reporter.id
                      LEFT JOIN users mod ON r.resolved_by = mod.id
                      WHERE r.status IN ('resolved', 'dismissed')
                      ORDER BY r.resolved_at DESC
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error getting resolved reports: " . $e->getMessage());
            return [];
        }
    }
    
    public function resolveReport($report_id, $moderator_id, $action_taken) {
        try {
            $query = "UPDATE reports 
                      SET status = 'resolved', 
                          action_taken = :action, 
                          resolved_by = :moderator_id,
                          resolved_at = NOW()
                      WHERE id = :report_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':action', $action_taken);
            $stmt->bindParam(':moderator_id', $moderator_id);
            $stmt->bindParam(':report_id', $report_id);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error resolving report: " . $e->getMessage());
            return false;
        }
    }
    
    public function dismissReport($report_id, $moderator_id) {
        try {
            $query = "UPDATE reports 
                      SET status = 'dismissed', 
                          resolved_by = :moderator_id,
                          resolved_at = NOW()
                      WHERE id = :report_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':moderator_id', $moderator_id);
            $stmt->bindParam(':report_id', $report_id);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error dismissing report: " . $e->getMessage());
            return false;
        }
    }
    
    // ==================== HELPER METHODS ====================
    
    private function logAction($moderator_id, $target_type, $target_id, $action, $reason = null) {
        try {
            // Create moderation_actions table if needed
            $create_table = "CREATE TABLE IF NOT EXISTS moderation_actions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                moderator_id INT NOT NULL,
                target_type ENUM('listing', 'user', 'report') NOT NULL,
                target_id INT NOT NULL,
                action VARCHAR(50) NOT NULL,
                reason TEXT,
                action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_moderator (moderator_id),
                INDEX idx_target (target_type, target_id),
                INDEX idx_date (action_date DESC)
            )";
            $this->db->exec($create_table);
            
            $query = "INSERT INTO moderation_actions (moderator_id, target_type, target_id, action, reason, action_date)
                      VALUES (:moderator_id, :target_type, :target_id, :action, :reason, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':moderator_id', $moderator_id);
            $stmt->bindParam(':target_type', $target_type);
            $stmt->bindParam(':target_id', $target_id);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':reason', $reason);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error logging action: " . $e->getMessage());
            return false;
        }
    }
    
    private function ensureUserModerationColumns() {
        try {
            $this->db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_suspended BOOLEAN DEFAULT FALSE AFTER is_admin");
            $this->db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_banned BOOLEAN DEFAULT FALSE AFTER is_suspended");
            $this->db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_moderator BOOLEAN DEFAULT FALSE AFTER is_admin");
        } catch(PDOException $e) {
            error_log("Error ensuring user moderation columns: " . $e->getMessage());
        }
    }
    
    private function ensureModeratedUsersTable() {
        try {
            $create_table = "CREATE TABLE IF NOT EXISTS moderated_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL UNIQUE,
                moderated_by INT NOT NULL,
                suspension_reason TEXT,
                suspension_end DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user (user_id),
                INDEX idx_suspension_end (suspension_end)
            )";
            $this->db->exec($create_table);
        } catch(PDOException $e) {
            error_log("Error creating moderated_users table: " . $e->getMessage());
        }
    }
}
?>