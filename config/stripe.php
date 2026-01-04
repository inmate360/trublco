<?php
/**
 * Stripe Configuration
 */

// Stripe API Keys (Get these from https://dashboard.stripe.com/apikeys)
define('STRIPE_SECRET_KEY', 'sk_test_your_secret_key_here'); // Replace with your actual key
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_your_publishable_key_here'); // Replace with your actual key

// For production, use live keys:
// define('STRIPE_SECRET_KEY', 'sk_live_your_secret_key_here');
// define('STRIPE_PUBLISHABLE_KEY', 'pk_live_your_publishable_key_here');

// Webhook Secret (for verifying webhook events)
define('STRIPE_WEBHOOK_SECRET', 'whsec_your_webhook_secret_here');

// Set Stripe API Key
require_once 'vendor/autoload.php'; // Make sure to install Stripe PHP library
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
?>