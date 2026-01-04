<?php
session_start();
require_once 'config/database.php';
require_once 'classes/FeaturedAd.php';

if(!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: my-listings.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$featuredAd = new FeaturedAd($db);

$listing_id = $_POST['listing_id'];
$duration_days = $_POST['duration_days'];

// Verify ownership
$query = "SELECT * FROM listings WHERE id = :id AND user_id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $listing_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

if($stmt->rowCount() == 0) {
    $_SESSION['error'] = 'Listing not found';
    header('Location: my-listings.php');
    exit();
}

// DEMO MODE - In production, integrate with Stripe
// Real implementation would be:
/*
require_once 'config/stripe.php';

$pricing = $featuredAd->getPricingByDuration($duration_days);

try {
    $payment_intent = \Stripe\PaymentIntent::create([
        'amount' => $pricing['price'] * 100,
        'currency' => 'usd',
        'description' => "Featured ad for {$duration_days} days",
        'metadata' => [
            'listing_id' => $listing_id,
            'user_id' => $_SESSION['user_id'],
            'duration_days' => $duration_days
        ]
    ]);
    
    $payment_intent_id = $payment_intent->id;
} catch(\Stripe\Exception\CardException $e) {
    $_SESSION['error'] = 'Payment failed: ' . $e->getError()->message;
    header('Location: feature-ad.php?listing_id=' . $listing_id);
    exit();
}
*/

// DEMO MODE: Simulate payment
$payment_intent_id = 'pi_demo_' . uniqid();

// Create featured request
$result = $featuredAd->createRequest($listing_id, $_SESSION['user_id'], $duration_days, $payment_intent_id);

if($result['success']) {
    // Log payment
    $pricing = $featuredAd->getPricingByDuration($duration_days);
    $query = "INSERT INTO payment_history 
              (user_id, stripe_payment_id, amount, status, description, payment_type, related_id) 
              VALUES (:user_id, :payment_id, :amount, 'succeeded', :description, 'featured_ad', :request_id)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':payment_id', $payment_intent_id);
    $stmt->bindParam(':amount', $pricing['price']);
    $description = "Featured ad for {$duration_days} days";
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':request_id', $result['request_id']);
    $stmt->execute();
    
    $_SESSION['success'] = 'Featured ad request submitted! It will be reviewed shortly.';
    header('Location: featured-requests-status.php');
} else {
    $_SESSION['error'] = $result['error'];
    header('Location: feature-ad.php?listing_id=' . $listing_id);
}
exit();
?>