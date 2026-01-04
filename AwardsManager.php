<?php
/**
 * Awards Manager - FIXED VERSION
 * Handles automatic award checking and granting
 * Fixed: Handles forum table column names and missing tables gracefully
 */

class AwardsManager {
    private $db;

    public function __construct($database_connection) {
        $this->db = $database_connection;
    }

    /**
     * Check and grant awards for a user based on their activity
     * Call this after any action (post story, create listing, etc.)
     */
    public function checkAndGrantAwards($user_id) {
        if(!$user_id) return false;

        $newly_earned = [];

        // Get user stats
        $stats = $this->getUserStats($user_id);

        // Get all active awards
        $query = "SELECT * FROM awards WHERE is_active = 1";
        $stmt = $this->db->query($query);
        $awards = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach($awards as $award) {
            // Check if user already has this award
            if($this->userHasAward($user_id, $award['id'])) {
                continue;
            }

            // Check if user qualifies for this award
            if($this->checkAwardRequirement($stats, $award)) {
                $this->grantAward($user_id, $award['id'], $award['points']);
                $newly_earned[] = $award;
            }
        }

        return $newly_earned;
    }

    /**
     * Get user statistics
     */
    private function getUserStats($user_id) {
        $stats = [
            'user_id' => $user_id,
            'story_count' => 0,
            'listing_count' => 0,
            'forum_posts' => 0,
            'likes_received' => 0,
            'likes_given' => 0,
            'is_verified' => 0,
            'profile_complete' => 0
        ];

        // Get user info
        try {
            $stmt = $this->db->prepare("SELECT id, is_verified FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if($user) {
                $stats['user_id'] = $user['id'];
                $stats['is_verified'] = $user['is_verified'] ?? 0;
            }
        } catch(PDOException $e) {
            error_log("Error getting user info: " . $e->getMessage());
        }

        // Count stories
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM stories WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats['story_count'] = $stmt->fetchColumn();
        } catch(PDOException $e) {
            error_log("Error counting stories: " . $e->getMessage());
        }

        // Count listings
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM listings WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats['listing_count'] = $stmt->fetchColumn();
        } catch(PDOException $e) {
            error_log("Error counting listings: " . $e->getMessage());
        }

        // Count forum posts - TRY MULTIPLE COLUMN NAMES
        $forum_count = 0;
        $forum_column_names = ['user_id', 'author_id', 'created_by', 'poster_id'];

        foreach($forum_column_names as $column) {
            try {
                // First check if table exists
                $check = $this->db->query("SHOW TABLES LIKE 'forum_threads'");
                if($check->rowCount() == 0) {
                    break; // Table doesn't exist
                }

                // Try to count with this column name
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM forum_threads WHERE {$column} = ?");
                $stmt->execute([$user_id]);
                $forum_count = $stmt->fetchColumn();
                break; // Success! Use this count
            } catch(PDOException $e) {
                // This column doesn't exist, try next one
                continue;
            }
        }

        $stats['forum_posts'] = $forum_count;

        // Count likes received (story likes)
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM story_likes sl
                INNER JOIN stories s ON sl.story_id = s.id
                WHERE s.user_id = ?
            ");
            $stmt->execute([$user_id]);
            $stats['likes_received'] = $stmt->fetchColumn();
        } catch(PDOException $e) {
            error_log("Error counting likes received: " . $e->getMessage());
        }

        // Count likes given
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM story_likes WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats['likes_given'] = $stmt->fetchColumn();
        } catch(PDOException $e) {
            error_log("Error counting likes given: " . $e->getMessage());
        }

        // Check profile completeness (simple version - can be enhanced)
        $stats['profile_complete'] = $stats['is_verified'] ? 100 : 50;

        return $stats;
    }

    /**
     * Check if user meets award requirement
     */
    private function checkAwardRequirement($stats, $award) {
        $type = $award['requirement_type'];
        $value = $award['requirement_value'];

        switch($type) {
            case 'story_count':
                return $stats['story_count'] >= $value;

            case 'listing_count':
                return $stats['listing_count'] >= $value;

            case 'forum_posts':
                return $stats['forum_posts'] >= $value;

            case 'likes_received':
                return $stats['likes_received'] >= $value;

            case 'likes_given':
                return $stats['likes_given'] >= $value;

            case 'is_verified':
                return $stats['is_verified'] == $value;

            case 'user_id':
                // For "early adopter" - user ID must be <= value
                return $stats['user_id'] <= $value;

            case 'profile_complete':
                return $stats['profile_complete'] >= $value;

            default:
                return false;
        }
    }

    /**
     * Check if user already has an award
     */
    private function userHasAward($user_id, $award_id) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM user_awards WHERE user_id = ? AND award_id = ?");
            $stmt->execute([$user_id, $award_id]);
            return $stmt->fetch() !== false;
        } catch(PDOException $e) {
            error_log("Error checking user award: " . $e->getMessage());
            return true; // Assume they have it to prevent errors
        }
    }

    /**
     * Grant an award to a user
     */
    private function grantAward($user_id, $award_id, $points) {
        try {
            // Insert user award
            $stmt = $this->db->prepare("
                INSERT INTO user_awards (user_id, award_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$user_id, $award_id]);

            // Update user points and award count
            $stmt = $this->db->prepare("
                UPDATE users 
                SET total_points = total_points + ?,
                    award_count = award_count + 1
                WHERE id = ?
            ");
            $stmt->execute([$points, $user_id]);

            return true;
        } catch(PDOException $e) {
            error_log("Error granting award: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all awards for a user
     */
    public function getUserAwards($user_id, $displayed_only = false) {
        try {
            $query = "
                SELECT a.*, ua.earned_at, ua.is_displayed
                FROM user_awards ua
                INNER JOIN awards a ON ua.award_id = a.id
                WHERE ua.user_id = ?
            ";

            if($displayed_only) {
                $query .= " AND ua.is_displayed = 1";
            }

            $query .= " ORDER BY ua.earned_at DESC";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting user awards: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's progress towards next awards
     */
    public function getUserProgress($user_id) {
        $stats = $this->getUserStats($user_id);
        $progress = [];

        try {
            // Get awards user doesn't have yet
            $query = "
                SELECT a.* FROM awards a
                WHERE a.is_active = 1
                AND a.id NOT IN (
                    SELECT award_id FROM user_awards WHERE user_id = ?
                )
                ORDER BY a.requirement_value ASC
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$user_id]);
            $available_awards = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach($available_awards as $award) {
                $current = 0;
                $required = $award['requirement_value'];

                switch($award['requirement_type']) {
                    case 'story_count':
                        $current = $stats['story_count'];
                        break;
                    case 'listing_count':
                        $current = $stats['listing_count'];
                        break;
                    case 'forum_posts':
                        $current = $stats['forum_posts'];
                        break;
                    case 'likes_received':
                        $current = $stats['likes_received'];
                        break;
                    case 'likes_given':
                        $current = $stats['likes_given'];
                        break;
                }

                $percent = $required > 0 ? min(100, ($current / $required) * 100) : 0;

                $progress[] = [
                    'award' => $award,
                    'current' => $current,
                    'required' => $required,
                    'percent' => round($percent, 1)
                ];
            }
        } catch(PDOException $e) {
            error_log("Error getting user progress: " . $e->getMessage());
        }

        return $progress;
    }

    /**
     * Get leaderboard (top users by points)
     */
    public function getLeaderboard($limit = 10) {
        try {
            $query = "
                SELECT u.id, u.username, u.total_points, u.award_count,
                       (SELECT COUNT(*) FROM stories WHERE user_id = u.id) as story_count,
                       (SELECT COUNT(*) FROM listings WHERE user_id = u.id) as listing_count
                FROM users u
                WHERE u.total_points > 0
                ORDER BY u.total_points DESC, u.award_count DESC
                LIMIT ?
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting leaderboard: " . $e->getMessage());
            return [];
        }
    }
}
?>
