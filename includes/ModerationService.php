<?php
// includes/ModerationService.php

class ModerationService {
    private $db;
    private $apiKey;
    private $apiEndpoint = 'https://api.openai.com/v1/moderations';
    
    public function __construct($database, $openaiKey) {
        $this->db = $database;
        $this->apiKey = $openaiKey;
    }
    
    /**
     * Moderate content using OpenAI API
     * @param string $contentType - listing, story, forum_post, etc.
     * @param int $contentId - ID of the content
     * @param int $userId - ID of user who created content
     * @param string $text - Text content to moderate
     * @param array $images - Optional array of image URLs
     * @return array - Moderation result
     */
    public function moderateContent($contentType, $contentId, $userId, $text, $images = []) {
        // Get settings for this content type
        $settings = $this->getSettings($contentType);
        
        if (!$settings['enabled']) {
            return ['status' => 'approved', 'message' => 'Moderation disabled'];
        }
        
        // Call OpenAI Moderation API
        $moderationResult = $this->callModerationAPI($text, $images);
        
        if (!$moderationResult['success']) {
            // If API fails, add to queue for manual review
            return $this->queueForReview($contentType, $contentId, $userId, $text, $images);
        }
        
        // Process results
        $result = $moderationResult['data']['results'][0];
        $flagged = $result['flagged'];
        $categories = $result['categories'];
        $categoryScores = $result['category_scores'];
        
        // Calculate highest score
        $maxScore = max($categoryScores);
        $flaggedCategories = array_keys(array_filter($categories));
        
        // Check against blocked categories
        $blockedCats = json_decode($settings['blocked_categories'], true);
        $hasBlockedContent = !empty(array_intersect($flaggedCategories, $blockedCats));
        
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
            $flaggedCategories,
            $maxScore,
            $autoAction
        );
        
        return [
            'status' => $status,
            'flagged' => $flagged,
            'categories' => $flaggedCategories,
            'max_score' => $maxScore,
            'queue_id' => $queueId,
            'requires_review' => !$autoAction
        ];
    }
    
    /**
     * Call OpenAI Moderation API
     */
    private function callModerationAPI($text, $images = []) {
        $input = ['type' => 'text', 'text' => $text];
        
        // For multi-modal (text + images), format accordingly
        if (!empty($images)) {
            $input = [
                ['type' => 'text', 'text' => $text]
            ];
            foreach ($images as $img) {
                $input[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $img]
                ];
            }
        }
        
        $data = [
            'model' => 'omni-moderation-latest',
            'input' => $input
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
            return [
                'success' => true,
                'data' => json_decode($response, true)
            ];
        }
        
        return ['success' => false, 'error' => $response];
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
     * Approve content
     */
    public function approveContent($queueId, $adminId) {
        $stmt = $this->db->prepare("
            UPDATE moderation_queue 
            SET status = 'approved', reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('ii', $adminId, $queueId);
        return $stmt->execute();
    }
    
    /**
     * Reject content
     */
    public function rejectContent($queueId, $adminId) {
        $stmt = $this->db->prepare("
            UPDATE moderation_queue 
            SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('ii', $adminId, $queueId);
        return $stmt->execute();
    }
    
    /**
     * Get moderation queue with filters
     */
    public function getQueue($status = null, $limit = 50, $offset = 0) {
        if ($status) {
            $stmt = $this->db->prepare("
                SELECT mq.*, u.username, u.email 
                FROM moderation_queue mq
                LEFT JOIN users u ON mq.user_id = u.id
                WHERE mq.status = ?
                ORDER BY mq.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param('sii', $status, $limit, $offset);
        } else {
            $stmt = $this->db->prepare("
                SELECT mq.*, u.username, u.email 
                FROM moderation_queue mq
                LEFT JOIN users u ON mq.user_id = u.id
                ORDER BY mq.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param('ii', $limit, $offset);
        }
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get moderation statistics
     */
    public function getStats($days = 7) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN auto_action = 1 THEN 1 ELSE 0 END) as auto_actions
            FROM moderation_queue
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->bind_param('i', $days);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
