<?php
/**
 * Gamification Class - Handles points, badges, and leveling system
 */
class Gamification {
    private $conn;
    private $pointsPerLevel = 100;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Initialize user points
    public function initializeUser($user_id) {
        $query = "INSERT INTO user_points (user_id, total_points, level, experience) 
                  VALUES (:user_id, 0, 1, 0)
                  ON DUPLICATE KEY UPDATE user_id = user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }

    // Award points
    public function awardPoints($user_id, $points, $action, $description = null) {
        // Add points to total
        $query = "UPDATE user_points 
                  SET total_points = total_points + :points,
                      experience = experience + :points2
                  WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':points', $points);
        $stmt->bindParam(':points2', $points);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Log point history
        $query = "INSERT INTO point_history (user_id, points, action, description) 
                  VALUES (:user_id, :points, :action, :description)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':points', $points);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':description', $description);
        $stmt->execute();
        
        // Check for level up
        $this->checkLevelUp($user_id);
        
        // Check for badges
        $this->checkBadges($user_id);
        
        return true;
    }

    // Check and update level
    private function checkLevelUp($user_id) {
        $query = "SELECT level, experience FROM user_points WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if(!$user) return;
        
        $requiredXP = $user['level'] * $this->pointsPerLevel;
        
        if($user['experience'] >= $requiredXP) {
            $newLevel = $user['level'] + 1;
            $remainingXP = $user['experience'] - $requiredXP;
            
            $query = "UPDATE user_points 
                      SET level = :level, experience = :experience 
                      WHERE user_id = :user_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':level', $newLevel);
            $stmt->bindParam(':experience', $remainingXP);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Create notification
            $this->createLevelUpNotification($user_id, $newLevel);
        }
    }

    // Check and award badges
    private function checkBadges($user_id) {
        // Get all active badges user doesn't have
        $query = "SELECT b.* FROM badges b
                  WHERE b.is_active = TRUE
                  AND b.id NOT IN (SELECT badge_id FROM user_badges WHERE user_id = :user_id)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $badges = $stmt->fetchAll();
        
        foreach($badges as $badge) {
            if($this->checkBadgeRequirement($user_id, $badge)) {
                $this->awardBadge($user_id, $badge['id']);
            }
        }
    }

    // Check if user meets badge requirement
    private function checkBadgeRequirement($user_id, $badge) {
        $query = "SELECT * FROM user_points WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $stats = $stmt->fetch();
        
        if(!$stats) return false;
        
        switch($badge['requirement_type']) {
            case 'profile_completion':
                $query = "SELECT profile_completion FROM user_profiles WHERE user_id = :user_id LIMIT 1";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                $profile = $stmt->fetch();
                return $profile && $profile['profile_completion'] >= $badge['requirement_value'];
                
            case 'message_count':
                return $stats['messages_sent'] >= $badge['requirement_value'];
                
            case 'login_streak':
                return $stats['login_streak'] >= $badge['requirement_value'];
                
            case 'photo_upload':
                $query = "SELECT COUNT(*) as count FROM user_photos WHERE user_id = :user_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                $result = $stmt->fetch();
                return $result['count'] >= $badge['requirement_value'];
                
            case 'listing_post':
                return $stats['listings_posted'] >= $badge['requirement_value'];
                
            case 'favorites_received':
                return $stats['favorites_received'] >= $badge['requirement_value'];
                
            case 'profile_views':
                $query = "SELECT COUNT(*) as count FROM profile_views WHERE viewed_id = :user_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                $result = $stmt->fetch();
                return $result['count'] >= $badge['requirement_value'];
                
            default:
                return false;
        }
    }

    // Award badge
    private function awardBadge($user_id, $badge_id) {
        $query = "INSERT INTO user_badges (user_id, badge_id) VALUES (:user_id, :badge_id)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':badge_id', $badge_id);
        
        try {
            $stmt->execute();
            
            // Award badge points
            $query = "SELECT points, name FROM badges WHERE id = :badge_id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':badge_id', $badge_id);
            $stmt->execute();
            $badge = $stmt->fetch();
            
            if($badge) {
                $this->awardPoints($user_id, $badge['points'], 'badge_earned', 'Earned badge: ' . $badge['name']);
                $this->createBadgeNotification($user_id, $badge['name']);
            }
            
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }

    // Get user stats
    public function getUserStats($user_id) {
        $query = "SELECT * FROM user_points WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $stats = $stmt->fetch();
        
        if($stats) {
            $stats['next_level_xp'] = $stats['level'] * $this->pointsPerLevel;
            $stats['progress_percent'] = round(($stats['experience'] / $stats['next_level_xp']) * 100);
        }
        
        return $stats;
    }

    // Get user badges
    public function getUserBadges($user_id) {
        $query = "SELECT b.*, ub.earned_at, ub.is_displayed 
                  FROM user_badges ub
                  LEFT JOIN badges b ON ub.badge_id = b.id
                  WHERE ub.user_id = :user_id
                  ORDER BY ub.earned_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    // Get leaderboard
    public function getLeaderboard($limit = 50, $period = 'all_time') {
        if($period == 'weekly') {
            $query = "SELECT u.id, u.username, SUM(ph.points) as points
                      FROM users u
                      LEFT JOIN point_history ph ON u.id = ph.user_id
                      WHERE ph.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                      GROUP BY u.id
                      ORDER BY points DESC
                      LIMIT :limit";
        } else {
            $query = "SELECT u.id, u.username, up.total_points as points, up.level,
                             (SELECT file_path FROM user_photos WHERE user_id = u.id AND is_primary = TRUE LIMIT 1) as photo
                      FROM users u
                      LEFT JOIN user_points up ON u.id = up.user_id
                      ORDER BY up.total_points DESC
                      LIMIT :limit";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    // Update login streak
    public function updateLoginStreak($user_id) {
        $query = "SELECT login_streak, last_login_date FROM user_points WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        $today = date('Y-m-d');
        $streak = 1;
        
        if($user && $user['last_login_date']) {
            $lastLogin = new DateTime($user['last_login_date']);
            $todayDate = new DateTime($today);
            $diff = $lastLogin->diff($todayDate)->days;
            
            if($diff == 1) {
                // Consecutive day
                $streak = $user['login_streak'] + 1;
            } elseif($diff == 0) {
                // Same day, keep streak
                return;
            }
        }
        
        $query = "UPDATE user_points 
                  SET login_streak = :streak, last_login_date = :date 
                  WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':streak', $streak);
        $stmt->bindParam(':date', $today);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Award points for login
        if($streak == 1) {
            $this->awardPoints($user_id, 5, 'daily_login', 'Daily login');
        } else {
            $this->awardPoints($user_id, 5 + ($streak * 2), 'login_streak', "Login streak: {$streak} days");
        }
    }

    // Track activity
    public function trackActivity($user_id, $activity_type) {
        $points_map = [
            'message_sent' => ['points' => 2, 'column' => 'messages_sent'],
            'listing_posted' => ['points' => 10, 'column' => 'listings_posted'],
            'profile_view' => ['points' => 1, 'column' => 'profile_views_given']
        ];
        
        if(isset($points_map[$activity_type])) {
            $config = $points_map[$activity_type];
            
            // Update counter
            $query = "UPDATE user_points SET {$config['column']} = {$config['column']} + 1 WHERE user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Award points
            $this->awardPoints($user_id, $config['points'], $activity_type);
        }
    }

    // Notification helpers
    private function createLevelUpNotification($user_id, $level) {
        require_once __DIR__ . '/SmartNotifications.php';
        $notifications = new SmartNotifications($this->conn);
        $notifications->send($user_id, 'system', 'Level Up! 🎉', "Congratulations! You've reached level {$level}!", '/gamification.php');
    }

    private function createBadgeNotification($user_id, $badge_name) {
        require_once __DIR__ . '/SmartNotifications.php';
        $notifications = new SmartNotifications($this->conn);
        $notifications->send($user_id, 'badge', 'New Badge Earned! 🏆', "You've earned the '{$badge_name}' badge!", '/gamification.php');
    }
}
?>