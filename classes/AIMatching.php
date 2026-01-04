<?php
/**
 * AIMatching Class - AI-powered matching algorithm
 */
class AIMatching {
    private $conn;
    private $userProfile;

    public function __construct($db) {
        $this->conn = $db;
        require_once __DIR__ . '/UserProfile.php';
        $this->userProfile = new UserProfile($db);
    }

    // Calculate AI match score
    public function calculateMatchScore($user_id, $target_user_id) {
        $user1Profile = $this->userProfile->getProfile($user_id);
        $user2Profile = $this->userProfile->getProfile($target_user_id);
        
        if(!$user1Profile || !$user2Profile) return 0;
        
        $scores = [];
        
        // Location compatibility (30%)
        $scores['distance'] = $this->calculateDistanceScore($user_id, $target_user_id) * 0.30;
        
        // Interest overlap (25%)
        $scores['interests'] = $this->calculateInterestScore($user1Profile, $user2Profile) * 0.25;
        
        // Personality compatibility (20%)
        $scores['personality'] = $this->calculatePersonalityScore($user_id, $target_user_id) * 0.20;
        
        // Activity compatibility (15%)
        $scores['activity'] = $this->calculateActivityScore($user_id, $target_user_id) * 0.15;
        
        // Preference match (10%)
        $scores['preferences'] = $this->calculatePreferenceScore($user_id, $target_user_id) * 0.10;
        
        $totalScore = array_sum($scores);
        
        // Save match score
        $this->saveMatchScore($user_id, $target_user_id, $totalScore, $scores);
        
        return round($totalScore);
    }

    // Calculate distance compatibility score
    private function calculateDistanceScore($user_id, $target_user_id) {
        $loc1 = $this->userProfile->getUserLocation($user_id);
        $loc2 = $this->userProfile->getUserLocation($target_user_id);
        
        if(!$loc1 || !$loc2 || !$loc1['latitude'] || !$loc2['latitude']) {
            return 50; // Neutral score if location unknown
        }
        
        $distance = $this->userProfile->calculateDistance(
            $loc1['latitude'], $loc1['longitude'],
            $loc2['latitude'], $loc2['longitude']
        );
        
        // Score decreases with distance
        if($distance < 5) return 100;
        if($distance < 10) return 90;
        if($distance < 25) return 75;
        if($distance < 50) return 60;
        if($distance < 100) return 40;
        return 20;
    }

    // Calculate interest overlap score
    private function calculateInterestScore($profile1, $profile2) {
        $interests1 = $profile1['interests'] ?? [];
        $interests2 = $profile2['interests'] ?? [];
        
        if(empty($interests1) || empty($interests2)) return 50;
        
        $common = array_intersect($interests1, $interests2);
        $total = array_unique(array_merge($interests1, $interests2));
        
        if(empty($total)) return 50;
        
        return round((count($common) / count($total)) * 100);
    }

    // Calculate personality compatibility
    private function calculatePersonalityScore($user_id, $target_user_id) {
        // Get quiz results
        $query = "SELECT quiz_id, answers FROM user_quiz_results WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user1Quizzes = $stmt->fetchAll();
        
        $stmt->bindParam(':user_id', $target_user_id);
        $stmt->execute();
        $user2Quizzes = $stmt->fetchAll();
        
        if(empty($user1Quizzes) || empty($user2Quizzes)) return 50;
        
        $compatibility = 0;
        $count = 0;
        
        foreach($user1Quizzes as $quiz1) {
            foreach($user2Quizzes as $quiz2) {
                if($quiz1['quiz_id'] == $quiz2['quiz_id']) {
                    $answers1 = json_decode($quiz1['answers'], true);
                    $answers2 = json_decode($quiz2['answers'], true);
                    
                    $matching = 0;
                    $total = count($answers1);
                    
                    foreach($answers1 as $key => $answer1) {
                        if(isset($answers2[$key]) && $answers2[$key] == $answer1) {
                            $matching++;
                        }
                    }
                    
                    if($total > 0) {
                        $compatibility += ($matching / $total) * 100;
                        $count++;
                    }
                }
            }
        }
        
        return $count > 0 ? round($compatibility / $count) : 50;
    }

    // Calculate activity compatibility
    private function calculateActivityScore($user_id, $target_user_id) {
        $query = "SELECT is_online, last_activity FROM users WHERE id = :user_id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user1 = $stmt->fetch();
        
        $stmt->bindParam(':user_id', $target_user_id);
        $stmt->execute();
        $user2 = $stmt->fetch();
        
        if(!$user1 || !$user2) return 50;
        
        $score = 50;
        
        // Both online = high score
        if($user1['is_online'] && $user2['is_online']) {
            $score += 30;
        }
        
        // Similar activity times
        if($user1['last_activity'] && $user2['last_activity']) {
            $time1 = strtotime($user1['last_activity']);
            $time2 = strtotime($user2['last_activity']);
            $diff = abs($time1 - $time2) / 3600; // Hours difference
            
            if($diff < 2) $score += 20;
            elseif($diff < 6) $score += 10;
        }
        
        return min($score, 100);
    }

    // Calculate preference match score
    private function calculatePreferenceScore($user_id, $target_user_id) {
        $prefs = $this->userProfile->getPreferences($user_id);
        $targetProfile = $this->userProfile->getProfile($target_user_id);
        
        if(!$prefs || !$targetProfile) return 50;
        
        $score = 100;
        
        // Check body type preference
        if(!empty($prefs['preferred_body_types']) && $targetProfile['body_type']) {
            if(!in_array($targetProfile['body_type'], $prefs['preferred_body_types'])) {
                $score -= 20;
            }
        }
        
        // Check relationship status preference
        if(!empty($prefs['preferred_relationship_status']) && $targetProfile['relationship_status']) {
            if(!in_array($targetProfile['relationship_status'], $prefs['preferred_relationship_status'])) {
                $score -= 15;
            }
        }
        
        return max($score, 0);
    }

    // Save match score to database
    private function saveMatchScore($user_id, $matched_user_id, $total_score, $scores) {
        $query = "INSERT INTO match_scores 
                  (user_id, matched_user_id, score, distance, compatibility_score, 
                   personality_match, interest_overlap, activity_compatibility)
                  VALUES (:user_id, :matched_id, :score, :distance, :total,
                          :personality, :interests, :activity)
                  ON DUPLICATE KEY UPDATE
                  score = :score2, compatibility_score = :total2,
                  personality_match = :personality2, interest_overlap = :interests2,
                  activity_compatibility = :activity2, calculated_at = CURRENT_TIMESTAMP";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':matched_id', $matched_user_id);
        $stmt->bindParam(':score', $total_score);
        $stmt->bindParam(':distance', $scores['distance']);
        $stmt->bindParam(':total', $total_score);
        $stmt->bindParam(':personality', $scores['personality']);
        $stmt->bindParam(':interests', $scores['interests']);
        $stmt->bindParam(':activity', $scores['activity']);
        $stmt->bindParam(':score2', $total_score);
        $stmt->bindParam(':total2', $total_score);
        $stmt->bindParam(':personality2', $scores['personality']);
        $stmt->bindParam(':interests2', $scores['interests']);
        $stmt->bindParam(':activity2', $scores['activity']);
        
        $stmt->execute();
    }

    // Get daily matches for user
    public function getDailyMatches($user_id, $count = 10) {
        // Check if we have matches for today
        $today = date('Y-m-d');
        
        $query = "SELECT dm.*, u.username, up.bio,
                         (SELECT file_path FROM user_photos WHERE user_id = dm.matched_user_id AND is_primary = TRUE LIMIT 1) as photo
                  FROM daily_matches dm
                  LEFT JOIN users u ON dm.matched_user_id = u.id
                  LEFT JOIN user_profiles up ON dm.matched_user_id = up.user_id
                  WHERE dm.user_id = :user_id AND dm.match_date = :date
                  ORDER BY dm.match_score DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':date', $today);
        $stmt->execute();
        $existing = $stmt->fetchAll();
        
        if(count($existing) >= $count) {
            return $existing;
        }
        
        // Generate new matches
        $this->generateDailyMatches($user_id, $count);
        
        // Fetch again
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Generate daily matches
    private function generateDailyMatches($user_id, $count = 10) {
        require_once __DIR__ . '/AdvancedSearch.php';
        $search = new AdvancedSearch($this->conn);
        
        // Get potential matches
        $filters = ['limit' => 50];
        $candidates = $search->searchUsers($user_id, $filters);
        
        $matches = [];
        foreach($candidates as $candidate) {
            $score = $this->calculateMatchScore($user_id, $candidate['id']);
            $matches[] = [
                'user_id' => $candidate['id'],
                'score' => $score
            ];
        }
        
        // Sort by score
        usort($matches, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Take top matches
        $topMatches = array_slice($matches, 0, $count);
        $today = date('Y-m-d');
        
        foreach($topMatches as $match) {
            $query = "INSERT INTO daily_matches (user_id, matched_user_id, match_score, match_date)
                      VALUES (:user_id, :matched_id, :score, :date)
                      ON DUPLICATE KEY UPDATE match_score = :score2";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':matched_id', $match['user_id']);
            $stmt->bindParam(':score', $match['score']);
            $stmt->bindParam(':date', $today);
            $stmt->bindParam(':score2', $match['score']);
            $stmt->execute();
        }
    }
}
?>