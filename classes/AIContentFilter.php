<?php
class AIContentFilter {
    private $db;
    
    // Thresholds
    const NSFW_THRESHOLD = 75;
    const TOXICITY_THRESHOLD = 70;
    const SPAM_THRESHOLD = 65;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Analyze text content for violations
     */
    public function analyzeText($text, $content_type, $content_id, $user_id) {
        $results = [
            'toxicity_score' => 0,
            'profanity_score' => 0,
            'spam_score' => 0,
            'sexual_content_score' => 0,
            'flagged_words' => [],
            'ai_flags' => [],
            'should_block' => false
        ];
        
        // Check for prohibited words
        $prohibitedWords = $this->getProhibitedWords();
        $flaggedWords = [];
        
        foreach($prohibitedWords as $category => $words) {
            foreach($words as $word) {
                if(stripos($text, $word) !== false) {
                    $flaggedWords[] = [
                        'word' => $word,
                        'category' => $category
                    ];
                    
                    // Increase scores based on category
                    switch($category) {
                        case 'prostitution':
                            $results['sexual_content_score'] += 25;
                            $results['should_block'] = true;
                            break;
                        case 'drugs':
                            $results['toxicity_score'] += 20;
                            $results['should_block'] = true;
                            break;
                        case 'violence':
                            $results['toxicity_score'] += 20;
                            break;
                        case 'profanity':
                            $results['profanity_score'] += 10;
                            break;
                        case 'spam':
                            $results['spam_score'] += 15;
                            break;
                    }
                }
            }
        }
        
        $results['flagged_words'] = $flaggedWords;
        
        // Check for spam patterns
        $spamScore = $this->detectSpamPatterns($text);
        $results['spam_score'] += $spamScore;
        
        // Check for excessive caps, repetition
        if($this->hasExcessiveCaps($text)) {
            $results['spam_score'] += 10;
            $results['ai_flags'][] = 'excessive_caps';
        }
        
        if($this->hasRepetitiveContent($text)) {
            $results['spam_score'] += 15;
            $results['ai_flags'][] = 'repetitive_content';
        }
        
        // Check for contact info spam
        if($this->hasContactInfoSpam($text)) {
            $results['spam_score'] += 20;
            $results['ai_flags'][] = 'contact_spam';
        }
        
        // Determine if should block
        if($results['toxicity_score'] >= self::TOXICITY_THRESHOLD ||
           $results['spam_score'] >= self::SPAM_THRESHOLD ||
           $results['sexual_content_score'] >= 80) {
            $results['should_block'] = true;
        }
        
        // Log to database
        $this->logTextModeration($content_type, $content_id, $user_id, $text, $results);
        
        // Update user behavior score
        if($results['should_block']) {
            $this->updateUserBehaviorScore($user_id, 'content_violation', $results);
        }
        
        return $results;
    }
    
    /**
     * Analyze image for NSFW content
     * This uses a simple hash-based detection. In production, integrate with:
     * - Google Cloud Vision API
     * - Amazon Rekognition
     * - Microsoft Azure Computer Vision
     */
    public function analyzeImage($image_path, $content_type, $content_id, $user_id) {
        $results = [
            'nsfw_score' => 0,
            'violence_score' => 0,
            'drug_score' => 0,
            'weapon_score' => 0,
            'spam_score' => 0,
            'labels' => [],
            'should_block' => false
        ];
        
        // Basic image validation
        if(!file_exists($image_path)) {
            return ['error' => 'Image file not found'];
        }
        
        $imageInfo = getimagesize($image_path);
        if(!$imageInfo) {
            return ['error' => 'Invalid image file'];
        }
        
        // Simulate AI detection (in production, use actual AI service)
        // For demonstration, we'll use simple heuristics
        
        // Check image dimensions and size
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $fileSize = filesize($image_path);
        
        // Detect if image is too small (likely spam)
        if($width < 200 || $height < 200) {
            $results['spam_score'] += 30;
            $results['labels'][] = 'low_quality';
        }
        
        // Check for duplicate images using hash
        $imageHash = md5_file($image_path);
        if($this->isDuplicateImage($imageHash, $user_id)) {
            $results['spam_score'] += 40;
            $results['labels'][] = 'duplicate_image';
        }
        
        // In production, call external AI API here
        // Example with Google Cloud Vision:
        /*
        $vision = new Google\Cloud\Vision\VisionClient(['keyFilePath' => 'path/to/key.json']);
        $image = $vision->image(fopen($image_path, 'r'), ['SAFE_SEARCH_DETECTION', 'LABEL_DETECTION']);
        $annotation = $vision->annotate($image);
        
        $safeSearch = $annotation->safeSearch();
        $results['nsfw_score'] = $this->convertLikelihoodToScore($safeSearch->adult());
        $results['violence_score'] = $this->convertLikelihoodToScore($safeSearch->violence());
        */
        
        // For demo purposes, randomly assign low scores
        $results['nsfw_score'] = rand(0, 30); // Would be actual API result
        
        if($results['nsfw_score'] >= self::NSFW_THRESHOLD ||
           $results['spam_score'] >= self::SPAM_THRESHOLD) {
            $results['should_block'] = true;
        }
        
        // Log to database
        $this->logImageModeration($image_path, $content_type, $content_id, $user_id, $results);
        
        return $results;
    }
    
    /**
     * Check user behavior patterns
     */
    public function analyzeUserBehavior($user_id) {
        $patterns = [
            'spam_likelihood' => 0,
            'abuse_likelihood' => 0,
            'fake_profile_likelihood' => 0,
            'flags' => []
        ];
        
        // Get user activity
        $query = "SELECT 
                  (SELECT COUNT(*) FROM listings WHERE user_id = :uid1 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as recent_listings,
                  (SELECT COUNT(*) FROM messages WHERE sender_id = :uid2 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as recent_messages,
                  (SELECT COUNT(*) FROM reports WHERE reported_type = 'user' AND reported_id = :uid3) as total_reports,
                  (SELECT COUNT(*) FROM user_warnings WHERE user_id = :uid4) as total_warnings,
                  (SELECT created_at FROM users WHERE id = :uid5) as account_created";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':uid1', $user_id);
        $stmt->bindParam(':uid2', $user_id);
        $stmt->bindParam(':uid3', $user_id);
        $stmt->bindParam(':uid4', $user_id);
        $stmt->bindParam(':uid5', $user_id);
        $stmt->execute();
        $activity = $stmt->fetch();
        
        // Rapid posting detection
        if($activity['recent_listings'] > 10) {
            $patterns['spam_likelihood'] += 40;
            $patterns['flags'][] = 'rapid_posting';
        }
        
        // Rapid messaging
        if($activity['recent_messages'] > 50) {
            $patterns['spam_likelihood'] += 30;
            $patterns['flags'][] = 'message_spam';
        }
        
        // Multiple reports
        if($activity['total_reports'] > 5) {
            $patterns['abuse_likelihood'] += 35;
            $patterns['flags'][] = 'multiple_reports';
        }
        
        // New account activity
        $accountAge = strtotime('now') - strtotime($activity['account_created']);
        if($accountAge < 86400 && $activity['recent_listings'] > 3) { // Account less than 1 day old
            $patterns['spam_likelihood'] += 25;
            $patterns['flags'][] = 'new_account_high_activity';
        }
        
        // Check for identical content
        $query = "SELECT description, COUNT(*) as count 
                  FROM listings 
                  WHERE user_id = :user_id 
                  GROUP BY description 
                  HAVING count > 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $patterns['spam_likelihood'] += 30;
            $patterns['flags'][] = 'duplicate_content';
        }
        
        // Update behavior score in database
        $this->updateUserBehaviorScore($user_id, 'pattern_analysis', $patterns);
        
        return $patterns;
    }
    
    /**
     * Issue automated warning
     */
    public function issueAutomatedWarning($user_id, $warning_type, $severity, $message, $content_type = null, $content_id = null) {
        $query = "INSERT INTO user_warnings 
                  (user_id, warning_type, severity, content_type, content_id, message, is_automated, created_at)
                  VALUES 
                  (:user_id, :warning_type, :severity, :content_type, :content_id, :message, TRUE, NOW())";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':warning_type', $warning_type);
            $stmt->bindParam(':severity', $severity);
            $stmt->bindParam(':content_type', $content_type);
            $stmt->bindParam(':content_id', $content_id);
            $stmt->bindParam(':message', $message);
            $stmt->execute();
            
            // Update user warning count
            $query = "UPDATE users 
                      SET warning_count = warning_count + 1,
                          last_warning_at = NOW()
                      WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Check if should auto-suspend
            $this->checkAutoSuspension($user_id);
            
            return ['success' => true, 'warning_id' => $this->db->lastInsertId()];
        } catch(PDOException $e) {
            error_log("Error issuing warning: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Private helper methods
     */
    private function getProhibitedWords() {
        return [
            'prostitution' => ['escort', 'donation', 'roses', 'incall', 'outcall', 'full service', 'hhr', 'qv'],
            'drugs' => ['molly', 'coke', 'weed for sale', 'pills for sale', 'party favors'],
            'violence' => ['kill', 'murder', 'assault', 'rape'],
            'profanity' => ['fuck', 'shit', 'bitch', 'asshole'],
            'spam' => ['click here', 'buy now', 'limited time', 'act now', 'free money']
        ];
    }
    
    private function detectSpamPatterns($text) {
        $score = 0;
        
        // Multiple URLs
        preg_match_all('/https?:\/\/[^\s]+/', $text, $matches);
        if(count($matches[0]) > 3) {
            $score += 25;
        }
        
        // Multiple phone numbers
        preg_match_all('/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/', $text, $matches);
        if(count($matches[0]) > 2) {
            $score += 20;
        }
        
        // Excessive emojis
        preg_match_all('/[\x{1F600}-\x{1F64F}]/u', $text, $matches);
        if(count($matches[0]) > 10) {
            $score += 15;
        }
        
        return $score;
    }
    
    private function hasExcessiveCaps($text) {
        $upperCount = preg_match_all('/[A-Z]/', $text);
        $totalChars = strlen(preg_replace('/[^a-zA-Z]/', '', $text));
        
        if($totalChars > 0 && ($upperCount / $totalChars) > 0.6) {
            return true;
        }
        
        return false;
    }
    
    private function hasRepetitiveContent($text) {
        // Check for repeated characters
        if(preg_match('/(.)\1{10,}/', $text)) {
            return true;
        }
        
        // Check for repeated words
        $words = str_word_count(strtolower($text), 1);
        $wordCounts = array_count_values($words);
        
        foreach($wordCounts as $word => $count) {
            if($count > 5 && strlen($word) > 3) {
                return true;
            }
        }
        
        return false;
    }
    
    private function hasContactInfoSpam($text) {
        $patterns = [
            '/(?:call|text|whatsapp|telegram|kik|snapchat)\s*(?:me|@|at|:)\s*\d/i',
            '/(?:my|dm|pm)\s*(?:number|phone|cell)/i'
        ];
        
        foreach($patterns as $pattern) {
            if(preg_match($pattern, $text)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isDuplicateImage($hash, $user_id) {
        $query = "SELECT COUNT(*) as count 
                  FROM image_moderation 
                  WHERE MD5(image_url) = :hash 
                  AND user_id = :user_id 
                  AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':hash', $hash);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    private function logTextModeration($content_type, $content_id, $user_id, $text, $results) {
        $query = "INSERT INTO text_moderation 
                  (content_type, content_id, user_id, text_content, toxicity_score, profanity_score, 
                   spam_score, sexual_content_score, ai_flags, flagged_words, status)
                  VALUES 
                  (:content_type, :content_id, :user_id, :text, :toxicity, :profanity,
                   :spam, :sexual, :ai_flags, :flagged_words, :status)";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':content_type', $content_type);
            $stmt->bindParam(':content_id', $content_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':text', $text);
            $stmt->bindParam(':toxicity', $results['toxicity_score']);
            $stmt->bindParam(':profanity', $results['profanity_score']);
            $stmt->bindParam(':spam', $results['spam_score']);
            $stmt->bindParam(':sexual', $results['sexual_content_score']);
            $stmt->bindValue(':ai_flags', json_encode($results['ai_flags']));
            $stmt->bindValue(':flagged_words', json_encode($results['flagged_words']));
            $status = $results['should_block'] ? 'flagged' : 'approved';
            $stmt->bindParam(':status', $status);
            $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error logging text moderation: " . $e->getMessage());
        }
    }
    
    private function logImageModeration($image_url, $content_type, $content_id, $user_id, $results) {
        $query = "INSERT INTO image_moderation 
                  (image_url, content_type, content_id, user_id, nsfw_score, violence_score,
                   drug_score, weapon_score, spam_score, ai_labels, status)
                  VALUES 
                  (:image_url, :content_type, :content_id, :user_id, :nsfw, :violence,
                   :drug, :weapon, :spam, :labels, :status)";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':image_url', $image_url);
            $stmt->bindParam(':content_type', $content_type);
            $stmt->bindParam(':content_id', $content_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':nsfw', $results['nsfw_score']);
            $stmt->bindParam(':violence', $results['violence_score']);
            $stmt->bindParam(':drug', $results['drug_score']);
            $stmt->bindParam(':weapon', $results['weapon_score']);
            $stmt->bindParam(':spam', $results['spam_score']);
            $stmt->bindValue(':labels', json_encode($results['labels']));
            $status = $results['should_block'] ? 'flagged' : 'approved';
            $stmt->bindParam(':status', $status);
            $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error logging image moderation: " . $e->getMessage());
        }
    }
    
    private function updateUserBehaviorScore($user_id, $event_type, $data) {
        // Get or create behavior score
        $query = "INSERT INTO user_behavior_scores (user_id) 
                  VALUES (:user_id) 
                  ON DUPLICATE KEY UPDATE user_id = user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Update scores based on event
        $scoreUpdates = [];
        
        if($event_type == 'content_violation') {
            $scoreUpdates[] = "spam_score = LEAST(100, spam_score + " . ($data['spam_score'] / 2) . ")";
            $scoreUpdates[] = "abuse_score = LEAST(100, abuse_score + " . ($data['toxicity_score'] / 2) . ")";
            $scoreUpdates[] = "trust_score = GREATEST(0, trust_score - 10)";
            $scoreUpdates[] = "total_violations = total_violations + 1";
            $scoreUpdates[] = "last_violation_at = NOW()";
        } elseif($event_type == 'pattern_analysis') {
            if(isset($data['spam_likelihood'])) {
                $scoreUpdates[] = "spam_score = " . $data['spam_likelihood'];
            }
            if(isset($data['abuse_likelihood'])) {
                $scoreUpdates[] = "abuse_score = " . $data['abuse_likelihood'];
            }
        }
        
        if(!empty($scoreUpdates)) {
            $query = "UPDATE user_behavior_scores SET " . implode(', ', $scoreUpdates) . " WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
        }
    }
    
    private function checkAutoSuspension($user_id) {
        $query = "SELECT warning_count, violation_count FROM users WHERE id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        // Auto-suspend after 3 warnings or 5 violations
        if($user['warning_count'] >= 3 || $user['violation_count'] >= 5) {
            $query = "UPDATE users SET is_suspended = TRUE WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Log the suspension
            $query = "INSERT INTO moderation_logs 
                      (content_type, content_id, user_id, action, reason, is_automated)
                      VALUES ('user', :user_id, :user_id2, 'suspended', 'Auto-suspended due to multiple violations', TRUE)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':user_id2', $user_id);
            $stmt->execute();
        }
    }
}
?>