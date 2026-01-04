<?php
// includes/ModerationPublicStats.php

class ModerationPublicStats {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get overall statistics (7, 30, 90 days)
     */
    public function getOverallStats($days = 30) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_scanned,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged,
                SUM(CASE WHEN auto_action = 1 THEN 1 ELSE 0 END) as auto_actions,
                AVG(confidence_score) as avg_confidence
            FROM moderation_queue
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->bind_param('i', $days);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get category breakdown
     */
    public function getCategoryBreakdown($days = 30) {
        $stmt = $this->db->prepare("
            SELECT flagged_categories
            FROM moderation_queue
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND flagged_categories IS NOT NULL
            AND flagged_categories != '[]'
        ");
        $stmt->bind_param('i', $days);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Count categories
        $categoryCounts = [];
        foreach ($results as $row) {
            $categories = json_decode($row['flagged_categories'], true);
            if ($categories) {
                foreach ($categories as $cat) {
                    $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
                }
            }
        }
        
        arsort($categoryCounts);
        return $categoryCounts;
    }
    
    /**
     * Get content type breakdown
     */
    public function getContentTypeStats($days = 30) {
        $stmt = $this->db->prepare("
            SELECT 
                content_type,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged
            FROM moderation_queue
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY content_type
            ORDER BY total DESC
        ");
        $stmt->bind_param('i', $days);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get recent activity (anonymized)
     */
    public function getRecentActivity($limit = 20) {
        $stmt = $this->db->prepare("
            SELECT 
                content_type,
                status,
                flagged_categories,
                confidence_score,
                auto_action,
                created_at,
                SUBSTRING(content_text, 1, 100) as preview
            FROM moderation_queue
            WHERE status IN ('rejected', 'flagged')
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get hourly activity chart data (last 24 hours)
     */
    public function getHourlyActivity() {
        $stmt = $this->db->query("
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM moderation_queue
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY HOUR(created_at)
            ORDER BY hour
        ");
        return $stmt->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get response time stats
     */
    public function getResponseTimeStats($days = 30) {
        $stmt = $this->db->prepare("
            SELECT 
                AVG(TIMESTAMPDIFF(SECOND, created_at, reviewed_at)) as avg_review_time,
                MIN(TIMESTAMPDIFF(SECOND, created_at, reviewed_at)) as min_review_time,
                MAX(TIMESTAMPDIFF(SECOND, created_at, reviewed_at)) as max_review_time
            FROM moderation_queue
            WHERE reviewed_at IS NOT NULL
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->bind_param('i', $days);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get accuracy stats (successful appeals)
     */
    public function getAccuracyStats($days = 30) {
        // This assumes you have an appeals table
        // For now, we'll estimate based on auto_action accuracy
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_auto,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as correct_auto
            FROM moderation_queue
            WHERE auto_action = 1
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->bind_param('i', $days);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $accuracy = $result['total_auto'] > 0 
            ? ($result['correct_auto'] / $result['total_auto']) * 100 
            : 0;
            
        return [
            'total_auto_decisions' => $result['total_auto'],
            'accuracy_percentage' => round($accuracy, 1)
        ];
    }
}
