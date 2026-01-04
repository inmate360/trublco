<?php
require_once '../config/database.php';
require_once '../config/bitcoin.php';
require_once '../classes/BitcoinService.php';

// Get webhook payload
$payload = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE'] ?? '';

$config = require '../config/bitcoin.php';
$webhook_secret = $config['coinbase']['webhook_secret'];

// Verify signature
$computed_sig = hash_hmac('sha256', $payload, $webhook_secret);

if(!hash_equals($computed_sig, $sig_header)) {
    http_response_code(400);
    error_log("Coinbase webhook signature verification failed");
    exit('Invalid signature');
}

// Parse event
$event = json_decode($payload, true);

if(!$event) {
    http_response_code(400);
    exit('Invalid JSON');
}

// Log event
error_log("Coinbase webhook event: " . $event['event']['type']);

$database = new Database();
$db = $database->getConnection();
$bitcoin = new BitcoinService($db);

// Handle event
switch($event['event']['type']) {
    case 'charge:confirmed':
        $charge_code = $event['event']['data']['code'];
        $result = $bitcoin->activateSubscription($charge_code);
        
        if($result['success']) {
            error_log("Subscription activated for charge: " . $charge_code);
        } else {
            error_log("Failed to activate subscription: " . $result['error']);
        }
        break;
        
    case 'charge:failed':
        $charge_code = $event['event']['data']['code'];
        
        // Update charge status
        $query = "UPDATE coinbase_charges SET status = 'failed' WHERE charge_id = :charge_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['charge_id' => $charge_code]);
        
        error_log("Charge failed: " . $charge_code);
        break;
        
    case 'charge:pending':
        error_log("Charge pending: " . $event['event']['data']['code']);
        break;
}

http_response_code(200);
echo json_encode(['success' => true]);
?>