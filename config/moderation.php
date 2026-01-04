<?php
/**
 * Content Moderation Configuration
 * Store your Perplexity API key here (keep this file secure!)
 */

return [
    'api_key' => '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', // Replace with your actual key
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
