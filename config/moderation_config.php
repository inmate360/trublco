<?php
// Add to your existing config.php or create separate file

// Perplexity API Configuration
define('PERPLEXITY_API_KEY', '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'); // Get from https://docs.perplexity.ai
define('PERPLEXITY_MODEL', 'sonar'); // or 'sonar-pro' for better accuracy

// Moderation Settings
define('AUTO_BLOCK_SCORE', 90); // Automatically block content above this score
define('REVIEW_THRESHOLD_SCORE', 70); // Send for human review above this score
define('MODERATE_MESSAGES', true); // Enable message moderation
define('MODERATE_STORIES', true); // Enable story moderation
define('MODERATE_LISTINGS', true); // Enable listing moderation
define('MODERATE_FORUM', true); // Enable forum moderation
