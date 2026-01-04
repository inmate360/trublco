<?php
/**
 * Perplexity API Client
 * Handles content moderation using Perplexity Sonar API
 */
class PerplexityClient {
    private $apiKey;
    private $baseUrl = 'https://api.perplexity.ai/chat/completions';
    private $model = 'sonar-pro'; // or 'sonar' for faster, lighter tasks

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Scan content for policy violations, harmful content, spam, etc.
     */
    public function scanContent($content, $contentType = 'general', $context = '') {
        $systemPrompt = $this->getSystemPrompt($contentType);

        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $this->buildUserPrompt($content, $context)
                ]
            ],
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object']
        ];

        return $this->makeRequest($data);
    }

    /**
     * Batch scan multiple content items
     */
    public function batchScan($contentItems, $contentType = 'general') {
        $results = [];
        foreach ($contentItems as $item) {
            $results[] = $this->scanContent($item['content'], $contentType, $item['context'] ?? '');
        }
        return $results;
    }

    private function getSystemPrompt($contentType) {
        $prompts = [
            'listing' => 'You are an AI content moderator for a classified ads platform. Analyze listings for: spam, scams, prohibited items (weapons, drugs, illegal services), inappropriate content, misleading information, and policy violations. Return JSON with: {"is_safe": boolean, "risk_level": "low|medium|high|critical", "violations": [], "confidence_score": 0-100, "reason": "detailed explanation", "recommended_action": "approve|flag|reject"}',

            'profile' => 'You are an AI content moderator for user profiles. Check for: fake profiles, impersonation, inappropriate usernames, spam in bio, suspicious behavior indicators. Return JSON with: {"is_safe": boolean, "risk_level": "low|medium|high|critical", "violations": [], "confidence_score": 0-100, "reason": "detailed explanation", "recommended_action": "approve|flag|reject"}',

            'message' => 'You are an AI content moderator for user messages. Detect: harassment, threats, hate speech, spam, phishing attempts, scams, explicit content. Return JSON with: {"is_safe": boolean, "risk_level": "low|medium|high|critical", "violations": [], "confidence_score": 0-100, "reason": "detailed explanation", "recommended_action": "approve|flag|reject"}',

            'general' => 'You are an AI content moderator. Analyze content for: inappropriate material, spam, scams, policy violations, harmful content. Return JSON with: {"is_safe": boolean, "risk_level": "low|medium|high|critical", "violations": [], "confidence_score": 0-100, "reason": "detailed explanation", "recommended_action": "approve|flag|reject"}'
        ];

        return $prompts[$contentType] ?? $prompts['general'];
    }

    private function buildUserPrompt($content, $context) {
        $prompt = "Analyze this content:

" . $content;
        if ($context) {
            $prompt .= "

Context: " . $context;
        }
        return $prompt;
    }

    private function makeRequest($data) {
        $ch = curl_init($this->baseUrl);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => 'cURL Error: ' . $error];
        }

        if ($httpCode !== 200) {
            return ['error' => 'API Error: HTTP ' . $httpCode, 'response' => $response];
        }

        $result = json_decode($response, true);

        if (isset($result['choices'][0]['message']['content'])) {
            $contentData = json_decode($result['choices'][0]['message']['content'], true);
            return $contentData ?? ['error' => 'Invalid JSON response'];
        }

        return ['error' => 'Unexpected API response format'];
    }
}
?>