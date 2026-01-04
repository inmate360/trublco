<?php
/**
 * Content Moderation Configuration
 * Store your Perplexity API key here (keep this file secure!)
 */

return [
    'api_key' => getenv('PERPLEXITY_API_KEY') ?: 'your-api-key-here', // Set PERPLEXITY_API_KEY environment variable
    'enabled' => true,
    'auto_reject_threshold' => 0.85,
    'review_threshold' => 0.60,
    'timeout' => 30,
    
    // Content types to moderate
    'moderate_types' => [
        'listing' => true,
        'forum' => true,
        'story' => true,
        'message' => true,
        'profile' => true
    ]
];
