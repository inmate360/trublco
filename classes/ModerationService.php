<?php
// includes/PerplexityModerationService.php

class PerplexityModerationService {
    private $db;
    private $apiKey;
    private $apiEndpoint = 'https://api.perplexity.ai/chat/completions';
    private $model = 'sonar-pro'; // or 'sonar' for faster/cheaper
    
    public function __construct($database, $perplexityKey) {
        $this->db = $database;
        $this->apiKey = $perplexityKey;
    }
    
    /**
     * Moderate content using Perplexity Sonar Pro API
     */
    public function moderateContent($contentType, $contentId, $userId, $text, $images = []) {
        $settings = $this->getSettings($contentType);
        
        if (!$settings['enabled']) {
            return ['status' => 'approved', 'message' => 'Moderation disabled'];
        }
        
        // Call Perplexity Sonar Pro for content analysis
        $moderationResult = $this->callPerplexityAPI($text, $contentType);
        
        if (!$moderationResult['success']) {
            return $this->queueForReview($contentType, $contentId, $userId, $text, $images);
        }
        
        // Process AI analysis
        $analysis = $moderationResult['analysis'];
        $flagged = $analysis['is_inappropriate'];
        $categories = $analysis['violation_categories'];
        $categoryScores = $analysis['category_scores'];
        $maxScore = $analysis['confidence_score'];
        
        // Check against blocked categories
        $blockedCats = json_decode($settings['blocked_categories'], true);
        $hasBlockedContent = !empty(array_intersect($categories, $blockedCats));
        
        // Determine action
        $status = 'approved';
        $autoAction = true;
        
        if ($hasBlockedContent && $maxScore >= $settings['auto_reject_threshold']) {
            $status = 'rejected';
        } elseif ($flagged || $maxScore >= $settings['auto_flag_threshold']) {
            $status = 'flagged';
            $autoAction = false;
        }
        
        // Save to moderation queue
        $queueId = $this->saveToQueue(
            $contentType,
            $contentId,
            $userId,
            $text,
            $images,
            $status,
            $categoryScores,
            $categories,
            $maxScore,
            $autoAction
        );
        
        return [
            'status' => $status,
            'flagged' => $flagged,
            'categories' => $categories,
            'max_score' => $maxScore,
            'queue_id' => $queueId,
            'requires_review' => !$autoAction,
            'reasoning' => $analysis['reasoning']
        ];
    }
    
    /**
     * Call Perplexity Sonar Pro API with specialized prompt
     */
    private function callPerplexityAPI($text, $contentType) {
        // Craft specialized moderation prompt
        $systemPrompt = $this->getModerationPrompt($contentType);
        
        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $text
                ]
            ],
            'temperature' => 0.1, // Low temperature for consistent analysis
            'max_tokens' => 500,
            'return_citations' => false, // We don't need citations for moderation
            'search_recency_filter' => 'month' // Use recent policy knowledge
        ];
        
        $ch = curl_init($this->apiEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $aiResponse = $result['choices'][0]['message']['content'];
            
            // Parse AI response
            $analysis = $this->parseAIResponse($aiResponse);
            
            return [
                'success' => true,
                'analysis' => $analysis,
                'raw_response' => $aiResponse
            ];
        }
        
        error_log("Perplexity API Error: " . $response);
        return ['success' => false, 'error' => $response];
    }
    
    /**
     * Generate specialized moderation prompt
     */
    private function getModerationPrompt($contentType) {
        return <<<PROMPT
You are an expert content moderation AI for a personals/classifieds platform called Basehit. Your task is to analyze user-submitted content and determine if it violates safety policies.

CONTENT TYPE: {$contentType}

ANALYZE FOR THESE VIOLATIONS:
1. **sexual_minors** - Any content involving or sexualizing minors (instant reject)
2. **hate_speech** - Discriminatory content targeting protected groups (race, religion, gender, etc.)
3. **violence** - Graphic violence, threats, or promotion of harm
4. **illegal_activity** - Drug sales, weapons, human trafficking, fraud
5. **harassment** - Bullying, doxxing, stalking content
6. **spam** - Excessive promotional content, scams, phishing
7. **self_harm** - Promotion of suicide or self-injury
8. **impersonation** - Pretending to be someone else

IMPORTANT CONTEXT:
- This is an adult personals platform (18+), so adult content between consenting adults is ALLOWED
- Casual dating and hookup content is NORMAL and ACCEPTABLE
- Only flag sexual content if it involves minors, non-consent, or illegal activities
- Be culturally sensitive but prioritize user safety

RESPOND IN THIS EXACT JSON FORMAT:
{
  "is_inappropriate": true/false,
  "confidence_score": 0.0-1.0,
  "violation_categories": ["category1", "category2"],
  "category_scores": {
    "sexual_minors": 0.0-1.0,
    "hate_speech": 0.0-1.0,
    "violence": 0.0-1.0,
    "illegal_activity": 0.0-1.0,
    "harassment": 0.0-1.0,
    "spam": 0.0-1.0,
    "self_harm": 0.0-1.0,
    "impersonation": 0.0-1.0
  },
  "reasoning": "Brief explanation of your decision"
}

Be thorough but fair. Err on the side of safety for serious violations.
PROMPT;
    }
    
    /**
     * Parse AI response into structured data
     */
    private function parseAIResponse($response) {
        // Try to extract JSON from response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $jsonData = json_decode($matches[0], true);
            
            if ($jsonData && isset($jsonData['is_inappropriate'])) {
                return $jsonData;
            }
        }
        
        // Fallback parsing if JSON is malformed
        $isInappropriate = stripos($response, '"is_inappropriate": true') !== false;
        
        return [
            'is_inappropriate' => $isInappropriate,
            'confidence_score' => $isInappropriate ? 0.7 : 0.1,
            'violation_categories' => [],
            'category_scores' => [],
            'reasoning' => $response
        ];
    }
    
    /**
     * Save moderation result to queue
     */
    private function saveToQueue($contentType, $contentId, $userId, $text, $images, $status, $scores, $categories, $maxScore, $autoAction) {
        $stmt = $this->db->prepare("
            INSERT INTO moderation_queue 
            (content_type, content_id, user_id, content_text, content_images, status, 
             moderation_score, flagged_categories, confidence_score, auto_action)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $imagesJson = json_encode($images);
        $scoresJson = json_encode($scores);
        $categoriesJson = json_encode($categories);
        
        $stmt->bind_param(
            'siisssssdi',
            $contentType,
            $contentId,
            $userId,
            $text,
            $imagesJson,
            $status,
            $scoresJson,
            $categoriesJson,
            $maxScore,
            $autoAction
        );
        
        $stmt->execute();
        return $this->db->insert_id;
    }
    
    /**
     * Queue for manual review when API fails
     */
    private function queueForReview($contentType, $contentId, $userId, $text, $images) {
        $stmt = $this->db->prepare("
            INSERT INTO moderation_queue 
            (content_type, content_id, user_id, content_text, content_images, status, auto_action)
            VALUES (?, ?, ?, ?, ?, 'pending', 0)
        ");
        
        $imagesJson = json_encode($images);
        $stmt->bind_param('siiss', $contentType, $contentId, $userId, $text, $imagesJson);
        $stmt->execute();
        
        return ['status' => 'pending', 'requires_review' => true];
    }
    
    /**
     * Get settings for content type
     */
    private function getSettings($contentType) {
        $stmt = $this->db->prepare("SELECT * FROM moderation_settings WHERE content_type = ?");
        $stmt->bind_param('s', $contentType);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result ?: [
            'enabled' => true,
            'auto_reject_threshold' => 0.80,
            'auto_flag_threshold' => 0.50,
            'blocked_categories' => '[]'
        ];
    }
    
    /**
     * Batch moderate multiple items (efficient for bulk processing)
     */
    public function moderateBatch($items) {
        $results = [];
        foreach ($items as $item) {
            $results[] = $this->moderateContent(
                $item['content_type'],
                $item['content_id'],
                $item['user_id'],
                $item['text'],
                $item['images'] ?? []
            );
            
            // Rate limiting: 60 requests per minute for Sonar Pro
            usleep(1000000); // 1 second delay between requests
        }
        return $results;
    }
    
    // ... (Include approve, reject, getQueue, getStats methods from previous version)
}
