<?php
class ContentModerator {
    private $db;
    private $apiKey;
    private $apiEndpoint = 'https://api.perplexity.ai/chat/completions';
    
    public function __construct($db) {
        $this->db = $db;
        // Store your Perplexity API key in config
        $this->apiKey = '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';
    }
    
    /**
     * Moderate text content using Perplexity API
     */
    public function moderateText($content, $content_type, $content_id, $user_id) {
        $prompt = $this->buildModerationPrompt($content, $content_type);
        $apiResponse = $this->callPerplexityAPI($prompt);
        
        if (!$apiResponse['success']) {
            // Fallback: allow content but log error
            error_log("Moderation API Error: " . $apiResponse['error']);
            return ['risk_level' => 'unknown', 'passed' => true];
        }
        
        $analysis = $this->parseAnalysis($apiResponse['content']);
        
        // Store moderation result
        $this->storeModerationResult([
            'content_type' => $content_type,
            'content_id' => $content_id,
            'user_id' => $user_id,
            'risk_level' => $analysis['risk_level'],
            'risk_score' => $analysis['risk_score'],
            'categories_flagged' => json_encode($analysis['categories']),
            'ai_reasoning' => $analysis['reasoning'],
            'action_taken' => $analysis['action'],
            'full_response' => json_encode($apiResponse)
        ]);
        
        return $analysis;
    }
    
    /**
     * Build moderation prompt for Perplexity
     */
    private function buildModerationPrompt($content, $content_type) {
        return "Analyze the following {$content_type} content for safety and policy violations.

Content to analyze:
\"\"\"
{$content}
\"\"\"

Evaluate for:
1. Illegal content (child exploitation, human trafficking, illegal drugs)
2. Violent threats
3. Hate speech and discrimination
4. Harassment and bullying
5. Spam and scams
6. Sexual content (explicit/inappropriate)
7. Personal information exposure
8. Misinformation

Provide response in this exact JSON format:
{
  \"risk_level\": \"low|medium|high|critical\",
  \"risk_score\": 0-100,
  \"categories\": [\"list of flagged categories\"],
  \"reasoning\": \"brief explanation\",
  \"action\": \"approve|review|block\"
}";
    }
    
    /**
     * Call Perplexity API
     */
    private function callPerplexityAPI($prompt) {
        $data = [
            'model' => 'sonar',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.2,
            'max_tokens' => 500
        ];
        
        $ch = curl_init($this->apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'error' => $error];
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "API returned status $httpCode"];
        }
        
        $decoded = json_decode($response, true);
        if (!isset($decoded['choices'][0]['message']['content'])) {
            return ['success' => false, 'error' => 'Invalid API response'];
        }
        
        return [
            'success' => true,
            'content' => $decoded['choices'][0]['message']['content'],
            'usage' => $decoded['usage'] ?? null
        ];
    }
    
    /**
     * Detect romance scams and platform switching attempts
     */
    public function detectRomanceScam($text, $message_id, $sender_id, $recipient_id) {
        // Check message frequency/timing - SKIP if table doesn't exist
        $message_count = 0;
        $days_since_first = 0;
        
        try {
            // Try messages table first
            $historyQuery = "SELECT COUNT(*) as msg_count, MIN(sent_at) as first_msg
                            FROM messages 
                            WHERE user_id = :sender_id 
                            AND receiver_id = :recipient_id";
            
            $stmt = $this->db->prepare($historyQuery);
            $stmt->execute([
                ':sender_id' => $sender_id,
                ':recipient_id' => $recipient_id
            ]);
            $history = $stmt->fetch(PDO::FETCH_ASSOC);
            $message_count = $history['msg_count'] ?? 0;
            
            if($history && $history['first_msg']) {
                $days_since_first = (time() - strtotime($history['first_msg'])) / 86400;
            }
        } catch(PDOException $e) {
            // Table doesn't exist or query failed - continue with defaults
            error_log("Message history query failed: " . $e->getMessage());
        }
        
        // Build enhanced prompt
        $prompt = "Analyze this private message for romance scam indicators and platform switching attempts.

MESSAGE CONTENT:
\"$text\"

CONTEXT:
- Message count between users: $message_count
- Days since first contact: " . round($days_since_first, 1) . "

ROMANCE SCAM RED FLAGS TO CHECK:
1. Love bombing (excessive affection, \"soulmate\" language too early)
2. Sob stories (sick relative, emergency, stranded somewhere)
3. Money requests (direct or indirect - gift cards, bitcoin, wire transfer, help with bills)
4. Investment schemes (crypto, trading, business opportunities)
5. Avoiding video calls or meeting in person
6. Claims of being overseas (military, engineer, doctor abroad)
7. Inheritance or large sum stories
8. Package delivery scams

PLATFORM SWITCHING RED FLAGS:
9. Urgently asking to move to WhatsApp, Telegram, Signal, Snapchat, or other platforms
10. Claiming this platform is unsafe or being monitored
11. Asking for email, phone number, or social media within first few messages
12. Wanting to take conversation off-platform before establishing trust

Respond in JSON format:
{
  \"is_romance_scam\": true/false,
  \"is_platform_switching\": true/false,
  \"confidence_score\": 0-100,
  \"red_flags_detected\": [\"flag1\", \"flag2\"],
  \"risk_level\": \"low/medium/high/critical\",
  \"explanation\": \"Brief explanation of why this was flagged\",
  \"recommendations\": \"What the recipient should know\"
}";

        try {
            $result = $this->callPerplexityAPI($prompt);
            
            if(!$result['success']) {
                error_log("Romance scam detection API error: " . $result['error']);
                return ['is_scam' => false, 'confidence' => 0, 'patterns' => [], 'reasoning' => ''];
            }
            
            // Parse JSON response - FIX: use $result['content']
            $analysis = json_decode($result['content'], true);
            
            if(!$analysis) {
                error_log("Failed to parse romance scam detection response: " . $result['content']);
                return ['is_scam' => false, 'confidence' => 0, 'patterns' => [], 'reasoning' => ''];
            }
            
            // Log if scam detected
            if(($analysis['is_romance_scam'] ?? false) || ($analysis['is_platform_switching'] ?? false)) {
                $logQuery = "INSERT INTO romance_scam_logs 
                            (message_id, sender_id, recipient_id, is_romance_scam, is_platform_switching, 
                             confidence_score, red_flags, risk_level, ai_explanation, created_at)
                            VALUES 
                            (:message_id, :sender_id, :recipient_id, :is_romance_scam, :is_platform_switching,
                             :confidence, :red_flags, :risk_level, :explanation, NOW())";
                
                $stmt = $this->db->prepare($logQuery);
                $stmt->execute([
                    ':message_id' => $message_id,
                    ':sender_id' => $sender_id,
                    ':recipient_id' => $recipient_id,
                    ':is_romance_scam' => ($analysis['is_romance_scam'] ?? false) ? 1 : 0,
                    ':is_platform_switching' => ($analysis['is_platform_switching'] ?? false) ? 1 : 0,
                    ':confidence' => $analysis['confidence_score'] ?? 0,
                    ':red_flags' => json_encode($analysis['red_flags_detected'] ?? []),
                    ':risk_level' => $analysis['risk_level'] ?? 'low',
                    ':explanation' => $analysis['explanation'] ?? ''
                ]);
            }
            
            return [
                'is_scam' => ($analysis['is_romance_scam'] ?? false) || ($analysis['is_platform_switching'] ?? false),
                'confidence' => $analysis['confidence_score'] ?? 0,
                'patterns' => $analysis['red_flags_detected'] ?? [],
                'risk_level' => $analysis['risk_level'] ?? 'low',
                'reasoning' => $analysis['explanation'] ?? '',
                'recommendations' => $analysis['recommendations'] ?? ''
            ];
            
        } catch(Exception $e) {
            error_log("Romance scam detection error: " . $e->getMessage());
            return ['is_scam' => false, 'confidence' => 0, 'patterns' => [], 'reasoning' => ''];
        }
    }
    
    /**
     * Parse AI analysis response
     */
    private function parseAnalysis($content) {
        // Try to extract JSON from response
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return [
                    'risk_level' => $json['risk_level'] ?? 'low',
                    'risk_score' => $json['risk_score'] ?? 0,
                    'categories' => $json['categories'] ?? [],
                    'reasoning' => $json['reasoning'] ?? '',
                    'action' => $json['action'] ?? 'approve',
                    'passed' => in_array($json['action'] ?? 'approve', ['approve', 'review'])
                ];
            }
        }
        
        // Fallback parsing
        return [
            'risk_level' => 'low',
            'risk_score' => 0,
            'categories' => [],
            'reasoning' => 'Unable to parse AI response',
            'action' => 'approve',
            'passed' => true
        ];
    }
    
    /**
     * Store moderation result in database
     */
    private function storeModerationResult($data) {
        $query = "INSERT INTO ai_moderation_logs 
                  (content_type, content_id, user_id, risk_level, risk_score,
                   categories_flagged, ai_reasoning, action_taken, full_response,
                   created_at)
                  VALUES 
                  (:content_type, :content_id, :user_id, :risk_level, :risk_score,
                   :categories_flagged, :ai_reasoning, :action_taken, :full_response,
                   NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($data);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get moderation stats for admin dashboard
     */
    public function getStats($days = 30) {
        $query = "SELECT 
                    COUNT(*) as total_scans,
                    SUM(CASE WHEN risk_level = 'high' OR risk_level = 'critical' THEN 1 ELSE 0 END) as high_risk,
                    SUM(CASE WHEN action_taken = 'block' THEN 1 ELSE 0 END) as blocked,
                    SUM(CASE WHEN action_taken = 'review' THEN 1 ELSE 0 END) as pending_review,
                    AVG(risk_score) as avg_risk_score
                  FROM ai_moderation_logs 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent flagged content for review
     */
    public function getFlaggedContent($limit = 50, $offset = 0) {
        $query = "SELECT m.*, u.username, u.email,
                    CASE 
                        WHEN m.content_type = 'listing' THEN l.title
                        WHEN m.content_type = 'forum_thread' THEN ft.title
                        WHEN m.content_type = 'forum_post' THEN SUBSTRING(fp.content, 1, 100)
                        WHEN m.content_type = 'story' THEN s.title
                        WHEN m.content_type = 'message' THEN 'Private Message'
                        ELSE 'N/A'
                    END as content_title
                  FROM ai_moderation_logs m
                  LEFT JOIN users u ON m.user_id = u.id
                  LEFT JOIN listings l ON m.content_type = 'listing' AND m.content_id = l.id
                  LEFT JOIN forum_threads ft ON m.content_type = 'forum_thread' AND m.content_id = ft.id
                  LEFT JOIN forum_posts fp ON m.content_type = 'forum_post' AND m.content_id = fp.id
                  LEFT JOIN stories s ON m.content_type = 'story' AND m.content_id = s.id
                  WHERE m.risk_level IN ('high', 'critical') OR m.action_taken = 'review'
                  ORDER BY m.created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
