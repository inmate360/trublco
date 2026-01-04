<?php
/**
 * Romance Scam Detection System for trubl.co
 * Uses Perplexity API to analyze conversation patterns
 */
class ScamDetector {
    private $pdo;
    private $perplexityApiKey;
    private $apiUrl = 'https://api.perplexity.ai/chat/completions';

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $config = require __DIR__ . '/../config/moderation.php';
        $this->perplexityApiKey = $config['api_key'] ?? '';
    }

    public function analyzeConversation($messages, $senderId, $receiverId) {
        try {
            // Check if scam detection is enabled
            $stmt = $this->pdo->query("SELECT setting_value FROM moderation_settings WHERE setting_key = 'enable_scam_detection'");
            if (($stmt->fetchColumn() ?: 'true') === 'false') {
                return ['is_scam' => false];
            }

            $prompt = $this->buildScamDetectionPrompt($messages);
            $response = $this->callPerplexityAPI($prompt);
            $analysis = $this->parseAIResponse($response);
            
            if ($analysis['is_scam']) {
                $this->logScamAttempt($senderId, $receiverId, $messages, $analysis);
            }

            return $analysis;
        } catch (Exception $e) {
            error_log("Scam Detection Error: " . $e->getMessage());
            return ['is_scam' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildScamDetectionPrompt($messages) {
        $conversationText = "";
        foreach ($messages as $msg) {
            $role = $msg['sender_id'] == $messages[0]['sender_id'] ? "User A" : "User B";
            $conversationText .= "{$role}: {$msg['message']}\n";
        }

        return "You are a romance scam detection expert for a dating platform. 
Analyze the following conversation between User A and User B for red flags.

CONVERSATION:
{$conversationText}

RED FLAGS TO LOOK FOR:
1. Love Bombing: Excessive, premature declarations of love or intense affection.
2. Financial Solicitation: Requests for money, gift cards, or help with 'emergencies'.
3. Off-Platform Movement: Urging the user to move to WhatsApp, Telegram, or encrypted apps very quickly.
4. Tragic Backstory: Elaborate stories about being stuck abroad, military service, or medical crises.
5. Inconsistent Language: Sudden changes in tone or poor grammar that suggests a script.

RESPONSE FORMAT (JSON only):
{
  \"is_scam\": true/false,
  \"risk_score\": 0.0-1.0,
  \"detected_flags\": [\"flag1\", \"flag2\"],
  \"reasoning\": \"brief explanation\",
  \"suggested_action\": \"none/warn_user/flag_admin/block_sender\",
  \"safety_tip\": \"A helpful, non-accusatory safety tip for the user if risk is detected\"
}

Respond ONLY with valid JSON.";
    }

    private function callPerplexityAPI($prompt) {
        $ch = curl_init($this->apiUrl);
        $data = [
            'model' => 'sonar-pro',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a security expert. Respond only with valid JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.1
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->perplexityApiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    private function parseAIResponse($response) {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}');
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonStr = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
            return json_decode($jsonStr, true);
        }
        return ['is_scam' => false];
    }

    private function logScamAttempt($senderId, $receiverId, $messages, $analysis) {
        $query = "INSERT INTO scam_logs (sender_id, receiver_id, conversation_snippet, risk_score, flags, reasoning, created_at) 
                  VALUES (:sender, :receiver, :snippet, :score, :flags, :reasoning, NOW())";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            'sender' => $senderId,
            'receiver' => $receiverId,
            'snippet' => substr(json_encode($messages), 0, 5000),
            'score' => $analysis['risk_score'],
            'flags' => json_encode($analysis['detected_flags']),
            'reasoning' => $analysis['reasoning']
        ]);
    }
}
