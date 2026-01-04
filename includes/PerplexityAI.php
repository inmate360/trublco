<?php
/**
 * Perplexity AI API Wrapper
 * Simple PHP wrapper for Perplexity Sonar API
 */

class PerplexityAI {
    private $api_key;
    private $base_url = 'https://api.perplexity.ai';
    private $timeout = 30;

    /**
     * Initialize Perplexity API client
     * @param string $api_key Your Perplexity API key
     */
    public function __construct($api_key = null) {
        $this->api_key = $api_key;
    }

    /**
     * Set API key
     * @param string $api_key
     */
    public function setApiKey($api_key) {
        $this->api_key = $api_key;
    }

    /**
     * Make a chat completion request
     * 
     * @param array $messages Array of message objects [{"role": "user", "content": "..."}]
     * @param string $model Model to use (default: sonar-pro)
     * @param array $options Additional options (temperature, max_tokens, etc.)
     * @return array API response
     */
    public function chat($messages, $model = 'sonar-pro', $options = []) {
        $data = [
            'model' => $model,
            'messages' => $messages
        ];

        // Add optional parameters
        if (isset($options['temperature'])) {
            $data['temperature'] = $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $data['max_tokens'] = $options['max_tokens'];
        }
        if (isset($options['top_p'])) {
            $data['top_p'] = $options['top_p'];
        }
        if (isset($options['stream'])) {
            $data['stream'] = $options['stream'];
        }

        return $this->makeRequest('/chat/completions', $data);
    }

    /**
     * Quick search query
     * 
     * @param string $query Search query
     * @param string $model Model to use
     * @param array $options Additional options
     * @return array API response
     */
    public function search($query, $model = 'sonar-pro', $options = []) {
        $messages = [
            [
                'role' => 'user',
                'content' => $query
            ]
        ];

        return $this->chat($messages, $model, $options);
    }

    /**
     * Ask a question with context
     * 
     * @param string $question User question
     * @param string $system_context System context/instructions
     * @param string $model Model to use
     * @param array $options Additional options
     * @return array API response
     */
    public function ask($question, $system_context = '', $model = 'sonar-pro', $options = []) {
        $messages = [];

        if (!empty($system_context)) {
            $messages[] = [
                'role' => 'system',
                'content' => $system_context
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $question
        ];

        return $this->chat($messages, $model, $options);
    }

    /**
     * Multi-turn conversation
     * 
     * @param array $conversation_history Array of previous messages
     * @param string $new_message New user message
     * @param string $model Model to use
     * @param array $options Additional options
     * @return array API response
     */
    public function continue_conversation($conversation_history, $new_message, $model = 'sonar-pro', $options = []) {
        $messages = $conversation_history;
        $messages[] = [
            'role' => 'user',
            'content' => $new_message
        ];

        return $this->chat($messages, $model, $options);
    }

    /**
     * Get available models
     * @return array List of available models
     */
    public function getModels() {
        return [
            'sonar' => [
                'name' => 'Sonar',
                'description' => 'Fast, affordable, real-time web search',
                'pricing' => 'Lower cost'
            ],
            'sonar-pro' => [
                'name' => 'Sonar Pro',
                'description' => 'Advanced model for complex queries',
                'pricing' => 'Higher quality, more expensive'
            ],
            'sonar-reasoning' => [
                'name' => 'Sonar Reasoning',
                'description' => 'Deep reasoning capabilities',
                'pricing' => 'Premium pricing'
            ]
        ];
    }

    /**
     * Test API connection
     * @return bool True if connection successful
     */
    public function testConnection() {
        try {
            $response = $this->search('Test connection', 'sonar', ['max_tokens' => 10]);
            return isset($response['choices']) && !isset($response['error']);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get usage statistics from response
     * @param array $response API response
     * @return array Usage statistics
     */
    public function getUsage($response) {
        if (isset($response['usage'])) {
            return $response['usage'];
        }
        return null;
    }

    /**
     * Extract content from response
     * @param array $response API response
     * @return string Response content
     */
    public function getContent($response) {
        if (isset($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }
        return '';
    }

    /**
     * Extract citations from response
     * @param array $response API response
     * @return array Citations
     */
    public function getCitations($response) {
        if (isset($response['citations'])) {
            return $response['citations'];
        }
        return [];
    }

    /**
     * Make API request
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array API response
     */
    private function makeRequest($endpoint, $data) {
        if (empty($this->api_key)) {
            throw new Exception('API key not set');
        }

        $url = $this->base_url . $endpoint;

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }

        $decoded = json_decode($response, true);

        if ($http_code !== 200) {
            $error_message = isset($decoded['error']['message']) 
                ? $decoded['error']['message'] 
                : 'API request failed with status ' . $http_code;
            throw new Exception($error_message);
        }

        return $decoded;
    }

    /**
     * Format response for display
     * @param array $response API response
     * @return array Formatted response
     */
    public function formatResponse($response) {
        return [
            'content' => $this->getContent($response),
            'citations' => $this->getCitations($response),
            'usage' => $this->getUsage($response),
            'model' => $response['model'] ?? null,
            'created' => $response['created'] ?? null
        ];
    }
}
