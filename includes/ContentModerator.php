<?php
/**
 * ContentModerator - AI-powered content moderation using Perplexity API
 * For trubl.co platform
 */

class ContentModerator {
    private $apiKey;
    private $apiUrl = 'https://api.perplexity.ai/chat/completions';
    private $model = 'llama-3.1-sonar-small-128k-online';
    private $db;
    
    // Moderation categories
    const CATEGORY_SAFE = 'safe';
    const CATEGORY_INAPPROPRIATE = 'inappropriate';
    const CATEGORY_SPAM = 'spam';
    const CATEGORY_ILLEGAL = 'illegal';
    const CATEGORY_HARASSMENT = 'harassment';
    const CATEGORY_EXPLICIT = 'explicit';
    
    /**
     * Constructor
     * @param string $apiKey Perplexity API key
     * @param PDO $db Database connection
     */
    public function __construct($apiKey, $db = null) {
        $this->apiKey = $apiKey;
        $this->db = $db;
    }
    
    /**
     * Moderate content using Perplexity AI
     * @param string $content The content to moderate
     * @param string $contentType Type: listing, forum, story, message, profile
     * @param int $userId User ID who posted the content
     * @param int $contentId ID of the content being moderated
     * @return array Moderation result
     */
    public function moderateContent($content, $contentType = 'general', $userId = null, $contentId = null) {
        try {
            // Prepare the moderation prompt
            $prompt = $this->buildModerationPrompt($content, $contentType);
            
            // Call Perplexity API
            $response = $this->callPerplexityAPI($prompt);
            
            // Parse the response
            $result = $this->parseModeration($response);
            
            // Log the moderation result
            if ($this->db && $result) {
                $this->logModeration($content, $contentType, $userId, $contentId, $result);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("ContentModerator Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'approved' => true, // Fail-safe: approve on error
                'confidence' => 0
            ];
        }
    }
    
    /**
     * Build moderation prompt based on content type
     */
    private function buildModerationPrompt($content, $contentType) {
        $basePrompt = "Analyze the following {$contentType} content for a personals/dating website and determine if it's appropriate. ";
        $basePrompt .= "Check for: illegal content, harassment, spam, scams, explicit non-consensual content, hate speech, or violations of adult dating platform policies. ";
        $basePrompt .= "Respond ONLY in valid JSON format with these exact fields:\n";
        $basePrompt .= "{\n";
        $basePrompt .= '  "approved": true or false,' . "\n";
        $basePrompt .= '  "category": "safe", "spam", "inappropriate", "illegal", "harassment", or "explicit",' . "\n";
        $basePrompt .= '  "confidence": 0.0 to 1.0,' . "\n";
        $basePrompt .= '  "reason": "brief explanation",' . "\n";
        $basePrompt .= '  "suggested_action": "approve", "review", or "reject"' . "\n";
        $basePrompt .= "}\n\n";
        $basePrompt .= "Content to analyze:\n" . substr($content, 0, 3000); // Limit content length
        
        return $basePrompt;
    }
    
    /**
     * Call Perplexity API
     */
    private function callPerplexityAPI($prompt) {
        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a content moderation AI for an adult personals website. Be precise, concise, and respond only in valid JSON format.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.2,
            'max_tokens' => 500
        ];
        
        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('API returned status code: ' . $httpCode);
        }
        
        $decoded = json_decode($response, true);
        
        if (!$decoded || !isset($decoded['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response format');
        }
        
        return $decoded['choices'][0]['message']['content'];
    }
    
    /**
     * Parse moderation response
     */
    private function parseModeration($response) {
        // Extract JSON from response (handle markdown code blocks)
        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');
        
        if ($jsonStart === false || $jsonEnd === false) {
            throw new Exception('No valid JSON found in response');
        }
        
        $jsonStr = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
        $parsed = json_decode($jsonStr, true);
        
        if (!$parsed) {
            throw new Exception('Failed to parse JSON response');
        }
        
        return [
            'success' => true,
            'approved' => $parsed['approved'] ?? true,
            'category' => $parsed['category'] ?? self::CATEGORY_SAFE,
            'confidence' => floatval($parsed['confidence'] ?? 0.5),
            'reason' => $parsed['reason'] ?? '',
            'suggested_action' => $parsed['suggested_action'] ?? 'review'
        ];
    }
    
    /**
     * Log moderation result to database
     */
    private function logModeration($content, $contentType, $userId, $contentId, $result) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO moderation_logs 
                (content_type, content_id, user_id, content_preview, approved, category, confidence, reason, suggested_action, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $contentPreview = substr($content, 0, 200);
            
            $stmt->execute([
                $contentType,
                $contentId,
                $userId,
                $contentPreview,
                $result['approved'] ? 1 : 0,
                $result['category'],
                $result['confidence'],
                $result['reason'],
                $result['suggested_action']
            ]);
        } catch (PDOException $e) {
            error_log("Failed to log moderation: " . $e->getMessage());
        }
    }
    
    /**
     * Moderate listing content
     */
    public function moderateListing($title, $description, $userId = null, $listingId = null) {
        $combinedContent = "Title: {$title}\n\nDescription: {$description}";
        return $this->moderateContent($combinedContent, 'listing', $userId, $listingId);
    }
    
    /**
     * Moderate forum post
     */
    public function moderateForumPost($title, $body, $userId = null, $postId = null) {
        $combinedContent = "Title: {$title}\n\nPost: {$body}";
        return $this->moderateContent($combinedContent, 'forum', $userId, $postId);
    }
    
    /**
     * Moderate story
     */
    public function moderateStory($title, $story, $userId = null, $storyId = null) {
        $combinedContent = "Title: {$title}\n\nStory: {$story}";
        return $this->moderateContent($combinedContent, 'story', $userId, $storyId);
    }
    
    /**
     * Moderate private message
     */
    public function moderateMessage($messageBody, $userId = null, $messageId = null) {
        return $this->moderateContent($messageBody, 'message', $userId, $messageId);
    }
    
    /**
     * Moderate profile content
     */
    public function moderateProfile($bio, $aboutMe, $userId = null) {
        $combinedContent = "Bio: {$bio}\n\nAbout: {$aboutMe}";
        return $this->moderateContent($combinedContent, 'profile', $userId, $userId);
    }
    
    /**
     * Check if content should be auto-rejected
     */
    public function shouldReject($moderationResult) {
        if (!$moderationResult['success']) {
            return false;
        }
        
        return !$moderationResult['approved'] && 
               $moderationResult['confidence'] >= 0.8 &&
               in_array($moderationResult['category'], [
                   self::CATEGORY_ILLEGAL,
                   self::CATEGORY_HARASSMENT,
                   self::CATEGORY_SPAM
               ]);
    }
    
    /**
     * Check if content needs human review
     */
    public function needsReview($moderationResult) {
        if (!$moderationResult['success']) {
            return false;
        }
        
        return $moderationResult['suggested_action'] === 'review' ||
               (!$moderationResult['approved'] && $moderationResult['confidence'] < 0.8);
    }
}
