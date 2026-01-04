<?php
/**
 * Frontend AI Content Scanner
 * Lightweight wrapper for user-facing content submissions
 */

require_once __DIR__ . '/admin/PerplexityClient.php';

class FrontendContentScanner {
    private $db;
    private $apiKey;
    private $enabled = false;

    public function __construct($db) {
        $this->db = $db;
        $this->loadSettings();
    }

    private function loadSettings() {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'");
            $stmt->execute();
            $result = $stmt->fetch();

            if ($result && !empty($result['setting_value'])) {
                $this->apiKey = $result['setting_value'];
                $this->enabled = true;
            }
        } catch (PDOException $e) {
            error_log("Failed to load AI scanner settings: " . $e->getMessage());
        }
    }

    /**
     * Scan content and return status decision
     * Returns: ['approved' => bool, 'risk_level' => string, 'reason' => string]
     */
    public function scanAndDecide($content, $contentType = 'general', $context = '') {
        // If AI scanning is disabled, auto-approve
        if (!$this->enabled) {
            return [
                'approved' => true,
                'status' => 'active',
                'risk_level' => 'not_scanned',
                'reason' => 'AI scanning not configured'
            ];
        }

        try {
            $client = new PerplexityClient($this->apiKey);
            $result = $client->scanContent($content, $contentType, $context);

            if (isset($result['error'])) {
                // If API fails, log and auto-approve to not block users
                error_log("AI Scanner API Error: " . ($result['error'] ?? 'Unknown'));
                return [
                    'approved' => true,
                    'status' => 'active',
                    'risk_level' => 'error',
                    'reason' => 'Scanner temporarily unavailable'
                ];
            }

            // Decision logic based on risk level
            $approved = true;
            $status = 'active';

            if ($result['risk_level'] === 'critical') {
                $approved = false;
                $status = 'rejected';
            } elseif ($result['risk_level'] === 'high') {
                $approved = false;
                $status = 'pending'; // Requires manual review
            } elseif ($result['risk_level'] === 'medium') {
                $approved = true;
                $status = 'active'; // Auto-approve but log for review
            }

            return [
                'approved' => $approved,
                'status' => $status,
                'risk_level' => $result['risk_level'],
                'confidence' => $result['confidence_score'] ?? 0,
                'violations' => $result['violations'] ?? [],
                'reason' => $result['reason'] ?? '',
                'scan_result' => $result
            ];

        } catch (Exception $e) {
            error_log("AI Scanner Exception: " . $e->getMessage());
            return [
                'approved' => true,
                'status' => 'active',
                'risk_level' => 'error',
                'reason' => 'Scanner error'
            ];
        }
    }

    /**
     * Save scan result to database for admin review
     */
    public function logScan($contentType, $contentId, $scanResult, $contentPreview = '') {
        if (!$this->enabled || !isset($scanResult['scan_result'])) {
            return;
        }

        try {
            $result = $scanResult['scan_result'];

            $query = "INSERT INTO ai_content_scans 
                      (content_type, content_id, content_preview, is_safe, risk_level, 
                       violations, confidence_score, reason, recommended_action, created_at) 
                      VALUES (:content_type, :content_id, :preview, :is_safe, :risk_level, 
                              :violations, :confidence, :reason, :action, NOW())";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':content_type', $contentType);
            $stmt->bindParam(':content_id', $contentId);
            $stmt->bindParam(':preview', $contentPreview);
            $stmt->bindParam(':is_safe', $result['is_safe'], PDO::PARAM_BOOL);
            $stmt->bindParam(':risk_level', $result['risk_level']);
            $violations = json_encode($result['violations']);
            $stmt->bindParam(':violations', $violations);
            $stmt->bindParam(':confidence', $result['confidence_score']);
            $stmt->bindParam(':reason', $result['reason']);
            $stmt->bindParam(':action', $result['recommended_action']);
            $stmt->execute();

        } catch (PDOException $e) {
            error_log("Failed to log AI scan: " . $e->getMessage());
        }
    }

    /**
     * Get user-friendly message based on scan result
     */
    public function getUserMessage($scanResult) {
        if (!$scanResult['approved']) {
            if ($scanResult['status'] === 'rejected') {
                return 'Your content was flagged by our automated system and cannot be published. Please review our community guidelines.';
            } elseif ($scanResult['status'] === 'pending') {
                return 'Your content has been submitted for review and will be published after admin approval.';
            }
        }
        return '';
    }
}
?>