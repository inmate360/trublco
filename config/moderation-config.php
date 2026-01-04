<?php
// config/moderation-config.php

// Perplexity API Key - Get at https://www.perplexity.ai/settings/api
define('PERPLEXITY_API_KEY', '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');

// Model selection
define('MODERATION_MODEL', 'sonar-pro'); // or 'sonar' for faster/cheaper

// Moderation settings
define('MODERATION_ENABLED', true);
define('AUTO_MODERATE_ON_SUBMIT', true);

// Email notifications
define('MODERATION_EMAIL', 'ai@trubl.co');
define('NOTIFY_ON_FLAG', true);

// Rate limiting (Perplexity: 60 requests/min for Sonar Pro)
define('MODERATION_RATE_LIMIT', 5);
