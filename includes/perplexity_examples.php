<?php
/**
 * Example Implementations - Perplexity AI Integration
 * Various use cases for integrating Perplexity AI into your application
 */

// ============================================================================
// EXAMPLE 1: Simple Search in Listing Pages
// ============================================================================

/**
 * Add AI-powered search suggestions to listing search
 */
function getAISearchSuggestions($search_query, $db) {
    try {
        // Get API key
        $query = "SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();

        if(!$result) {
            return null;
        }

        require_once 'includes/PerplexityAI.php';
        $pplx = new PerplexityAI($result['setting_value']);

        // Ask for search suggestions
        $prompt = "Based on the search query '{$search_query}', suggest 3-5 related search terms for a classified ads platform. Format as a simple comma-separated list.";

        $response = $pplx->search($prompt, 'sonar', ['max_tokens' => 100]);
        $suggestions = $pplx->getContent($response);

        // Parse suggestions
        $suggestions_array = array_map('trim', explode(',', $suggestions));
        return $suggestions_array;

    } catch(Exception $e) {
        error_log('AI Search Suggestions Error: ' . $e->getMessage());
        return null;
    }
}


// ============================================================================
// EXAMPLE 2: AI-Powered Listing Description Generator
// ============================================================================

/**
 * Generate professional listing description using AI
 */
function generateListingDescription($title, $category, $price, $key_features, $db) {
    try {
        $query = "SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();

        if(!$result) {
            return null;
        }

        require_once 'includes/PerplexityAI.php';
        $pplx = new PerplexityAI($result['setting_value']);

        $prompt = "Write a professional, engaging classified ad description for:\n" .
                  "Title: {$title}\n" .
                  "Category: {$category}\n" .
                  "Price: \${$price}\n" .
                  "Key Features: {$key_features}\n\n" .
                  "Keep it concise (3-4 sentences), persuasive, and highlight the value proposition.";

        $response = $pplx->ask(
            $prompt,
            "You are a professional copywriter specializing in classified ads.",
            'sonar',
            ['max_tokens' => 200]
        );

        return $pplx->getContent($response);

    } catch(Exception $e) {
        error_log('AI Description Generator Error: ' . $e->getMessage());
        return null;
    }
}


// ============================================================================
// EXAMPLE 3: Smart Content Moderation
// ============================================================================

/**
 * Check if listing content is appropriate using AI
 */
function moderateListingContent($title, $description, $db) {
    try {
        $query = "SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();

        if(!$result) {
            return ['approved' => true, 'reason' => 'Moderation not available'];
        }

        require_once 'includes/PerplexityAI.php';
        $pplx = new PerplexityAI($result['setting_value']);

        $prompt = "Review this classified ad for inappropriate content:\n\n" .
                  "Title: {$title}\n" .
                  "Description: {$description}\n\n" .
                  "Check for: spam, scams, prohibited items, offensive language.\n" .
                  "Respond with ONLY:\n" .
                  "APPROVED or REJECTED: [brief reason if rejected]";

        $response = $pplx->search($prompt, 'sonar', ['max_tokens' => 50]);
        $result_text = $pplx->getContent($response);

        $approved = stripos($result_text, 'APPROVED') !== false;
        $reason = $approved ? '' : $result_text;

        return [
            'approved' => $approved,
            'reason' => $reason
        ];

    } catch(Exception $e) {
        error_log('AI Moderation Error: ' . $e->getMessage());
        return ['approved' => true, 'reason' => 'Error in moderation'];
    }
}


// ============================================================================
// EXAMPLE 4: AI Chatbot for Support
// ============================================================================

/**
 * Handle user support questions with AI
 * Stores conversation history in session
 */
function aiSupportChat($user_message, $db) {
    try {
        $query = "SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();

        if(!$result) {
            return "I'm sorry, AI support is currently unavailable.";
        }

        require_once 'includes/PerplexityAI.php';
        $pplx = new PerplexityAI($result['setting_value']);

        // Get conversation history from session
        if(!isset($_SESSION['ai_support_history'])) {
            $_SESSION['ai_support_history'] = [];
        }

        $system_context = "You are a helpful customer support agent for a classified ads platform called trubl. " .
                         "Help users with questions about posting listings, account issues, safety tips, and general platform usage. " .
                         "Be friendly, concise, and professional.";

        $response = $pplx->ask(
            $user_message,
            $system_context,
            'sonar-pro',
            ['max_tokens' => 300]
        );

        $ai_response = $pplx->getContent($response);

        // Store in conversation history
        $_SESSION['ai_support_history'][] = ['role' => 'user', 'content' => $user_message];
        $_SESSION['ai_support_history'][] = ['role' => 'assistant', 'content' => $ai_response];

        // Keep only last 10 messages
        if(count($_SESSION['ai_support_history']) > 20) {
            $_SESSION['ai_support_history'] = array_slice($_SESSION['ai_support_history'], -20);
        }

        return $ai_response;

    } catch(Exception $e) {
        error_log('AI Support Chat Error: ' . $e->getMessage());
        return "I apologize, but I'm having trouble connecting right now. Please try again or contact our support team.";
    }
}


// ============================================================================
// EXAMPLE 5: Price Suggestion Based on Market Data
// ============================================================================

/**
 * Get AI-powered price suggestions for listings
 */
function suggestListingPrice($title, $category, $condition, $location, $db) {
    try {
        $query = "SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();

        if(!$result) {
            return null;
        }

        require_once 'includes/PerplexityAI.php';
        $pplx = new PerplexityAI($result['setting_value']);

        $prompt = "What is the typical market price range for:\n" .
                  "Item: {$title}\n" .
                  "Category: {$category}\n" .
                  "Condition: {$condition}\n" .
                  "Location: {$location}\n\n" .
                  "Provide a brief price range estimate in USD and reasoning.";

        $response = $pplx->search($prompt, 'sonar-pro', ['max_tokens' => 150]);

        return [
            'suggestion' => $pplx->getContent($response),
            'citations' => $pplx->getCitations($response)
        ];

    } catch(Exception $e) {
        error_log('AI Price Suggestion Error: ' . $e->getMessage());
        return null;
    }
}


// ============================================================================
// EXAMPLE 6: Automated FAQ Responses
// ============================================================================

/**
 * Get AI-generated answers to FAQ questions
 */
function getFAQAnswer($question, $db) {
    try {
        $query = "SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();

        if(!$result) {
            return null;
        }

        require_once 'includes/PerplexityAI.php';
        $pplx = new PerplexityAI($result['setting_value']);

        $system_context = "You are an FAQ assistant for trubl, a classified ads platform. " .
                         "Answer questions clearly and concisely. If you don't know, suggest contacting support.";

        $response = $pplx->ask(
            $question,
            $system_context,
            'sonar',
            ['max_tokens' => 200]
        );

        return $pplx->getContent($response);

    } catch(Exception $e) {
        error_log('AI FAQ Error: ' . $e->getMessage());
        return null;
    }
}


// ============================================================================
// EXAMPLE 7: Content Translation
// ============================================================================

/**
 * Translate listing content to different languages
 */
function translateListing($text, $target_language, $db) {
    try {
        $query = "SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();

        if(!$result) {
            return null;
        }

        require_once 'includes/PerplexityAI.php';
        $pplx = new PerplexityAI($result['setting_value']);

        $prompt = "Translate the following text to {$target_language}. Maintain the tone and style:\n\n{$text}";

        $response = $pplx->search($prompt, 'sonar', ['max_tokens' => 500]);

        return $pplx->getContent($response);

    } catch(Exception $e) {
        error_log('AI Translation Error: ' . $e->getMessage());
        return null;
    }
}


// ============================================================================
// EXAMPLE 8: Trend Analysis for Admin Dashboard
// ============================================================================

/**
 * Get market trends and insights for admin dashboard
 */
function getMarketTrends($category, $timeframe, $db) {
    try {
        $query = "SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();

        if(!$result) {
            return null;
        }

        require_once 'includes/PerplexityAI.php';
        $pplx = new PerplexityAI($result['setting_value']);

        $prompt = "What are the current market trends for {$category} in {$timeframe}? " .
                  "Include: popularity, pricing trends, and buyer preferences. Keep it concise (3-4 sentences).";

        $response = $pplx->search($prompt, 'sonar-pro', ['max_tokens' => 200]);

        return [
            'analysis' => $pplx->getContent($response),
            'sources' => $pplx->getCitations($response)
        ];

    } catch(Exception $e) {
        error_log('AI Trend Analysis Error: ' . $e->getMessage());
        return null;
    }
}


// ============================================================================
// EXAMPLE 9: SEO Optimization Suggestions
// ============================================================================

/**
 * Get SEO suggestions for listing titles and descriptions
 */
function getSEOSuggestions($title, $description, $category, $db) {
    try {
        $query = "SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();

        if(!$result) {
            return null;
        }

        require_once 'includes/PerplexityAI.php';
        $pplx = new PerplexityAI($result['setting_value']);

        $prompt = "Suggest SEO improvements for this classified ad:\n" .
                  "Title: {$title}\n" .
                  "Description: {$description}\n" .
                  "Category: {$category}\n\n" .
                  "Provide: 1) Improved title with keywords, 2) Top 5 relevant keywords";

        $response = $pplx->search($prompt, 'sonar', ['max_tokens' => 150]);

        return $pplx->getContent($response);

    } catch(Exception $e) {
        error_log('AI SEO Suggestions Error: ' . $e->getMessage());
        return null;
    }
}


// ============================================================================
// EXAMPLE 10: Smart Recommendations
// ============================================================================

/**
 * Get personalized listing recommendations for users
 */
function getSmartRecommendations($user_interests, $recent_views, $db) {
    try {
        $query = "SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();

        if(!$result) {
            return null;
        }

        require_once 'includes/PerplexityAI.php';
        $pplx = new PerplexityAI($result['setting_value']);

        $interests_str = implode(', ', $user_interests);
        $views_str = implode(', ', $recent_views);

        $prompt = "Based on user interests ({$interests_str}) and recent views ({$views_str}), " .
                  "suggest 5 related product categories or search terms they might be interested in. " .
                  "Format as a simple comma-separated list.";

        $response = $pplx->search($prompt, 'sonar', ['max_tokens' => 80]);
        $recommendations = $pplx->getContent($response);

        return array_map('trim', explode(',', $recommendations));

    } catch(Exception $e) {
        error_log('AI Recommendations Error: ' . $e->getMessage());
        return null;
    }
}

?>
