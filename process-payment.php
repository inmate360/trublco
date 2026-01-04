<?php
session_start();
require_once 'config/database.php';
require_once 'classes/MembershipPlan.php';
require_once 'classes/Subscription.php';

if(!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: membership.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$planManager = new MembershipPlan($db);
$subscription = new Subscription($db);

$plan_id = $_POST['plan_id'];
$plan = $planManager->getById($plan_id);

if(!$plan) {
    $_SESSION['error'] = 'Invalid plan selected';
    header('Location: membership.php');
    exit();
}

// DEMO MODE - In production, this would integrate with Stripe
// Here's what the real implementation would look like:

/*
require_once 'config/stripe.php';

try {
    // Create or retrieve Stripe customer
    $customer = \Stripe\Customer::create([
        'email' => $_SESSION['email'],
        'name' => $_SESSION['username'],
    ]);
    
    // Create price based on plan
    $price_data = [
        'currency' => 'usd',
        'unit_amount' => $plan['price'] * 100, // Stripe uses cents
        'recurring' => ['interval' => $plan['billing_cycle']],
        'product_data' => ['name' => $plan['name']],
    ];
    
    // Create subscription
    $stripe_subscription = \Stripe\Subscription::create([
        'customer' => $customer->id,
        'items' => [['price_data' => $price_data]],
        'payment_behavior' => 'default_incomplete',
        'expand' => ['latest_invoice.payment_intent'],
    ]);
    
    // Save subscription to database
    $stripe_data = [
        'customer_id' => $customer->id,
        'subscription_id' => $stripe_subscription->id,
        'status' => $stripe_subscription->status,
        'period_start' => date('Y-m-d H:i:s', $stripe_subscription->current_period_start),
        'period_end' => date('Y-m-d H:i:s', $stripe_subscription->current_period_end),
    ];
    
    $subscription->create($_SESSION['user_id'], $plan_id, $stripe_data);
    
    // Return client secret for frontend confirmation
    $client_secret = $stripe_subscription->latest_invoice->payment_intent->client_secret;
    
} catch(\Stripe\Exception\CardException $e) {
    $_SESSION['error'] = 'Payment failed: ' . $e->getError()->message;
    header('Location: checkout.php?plan=' . $plan['slug']);
    exit();
}
*/

// DEMO MODE: Simulate successful payment
$stripe_data = [
    'customer_id' => 'cus_demo_' . uniqid(),
    'subscription_id' => 'sub_demo_' . uniqid(),
    'status' => 'active',
    'period_start' => date('Y-m-d H:i:s'),
    'period_end' => date('Y-m-d H:i:s', strtotime('+1 month')),
];

$result = $subscription->create($_SESSION['user_id'], $plan_id, $stripe_data);

if($result) {
    // Log payment in payment history
    $query = "INSERT INTO payment_history 
              (user_id, subscription_id, stripe_payment_id, amount, status, description) 
              VALUES (:user_id, :sub_id, :payment_id, :amount, 'succeeded', :description)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':sub_id', $result);
    $payment_id = 'pi_demo_' . uniqid();
    $stmt->bindParam(':payment_id', $payment_id);
    $stmt->bindParam(':amount', $plan['price']);
    $description = "Subscription to {$plan['name']} plan";
    $stmt->bindParam(':description', $description);
    $stmt->execute();
    
    $_SESSION['success'] = 'Subscription activated successfully!';
    header('Location: subscription-success.php');
} else {
    $_SESSION['error'] = 'Failed to create subscription';
    header('Location: checkout.php?plan=' . $plan['slug']);
}
exit();
?>