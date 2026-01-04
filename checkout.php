<?php
session_start();
require_once 'config/database.php';
require_once 'classes/MembershipPlan.php';
require_once 'classes/Subscription.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if(!isset($_GET['plan'])) {
    header('Location: membership.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$planManager = new MembershipPlan($db);
$subscription = new Subscription($db);

$plan = $planManager->getBySlug($_GET['plan']);

if(!$plan || $plan['price'] == 0) {
    header('Location: membership.php');
    exit();
}

$current_subscription = $subscription->getUserSubscription($_SESSION['user_id']);

include 'views/header.php';
?>

<div class="container">
    <div class="checkout-container">
        <h2>Complete Your Purchase</h2>
        
        <div class="checkout-summary">
            <h3><?php echo htmlspecialchars($plan['name']); ?> Plan</h3>
            <p class="price">$<?php echo number_format($plan['price'], 2); ?> / <?php echo $plan['billing_cycle']; ?></p>
            
            <div class="features">
                <h4>What's included:</h4>
                <ul>
                    <?php 
                    $features = json_decode($plan['features'], true);
                    foreach($features as $feature): 
                    ?>
                    <li>✓ <?php echo htmlspecialchars($feature); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="payment-form">
            <h3>Payment Information</h3>
            <p class="payment-note">⚠️ <strong>Demo Mode:</strong> This is a demonstration. Real Stripe integration requires:</p>
            <ol>
                <li>Install Stripe PHP SDK: <code>composer require stripe/stripe-php</code></li>
                <li>Add your Stripe keys to <code>config/stripe.php</code></li>
                <li>Implement the Stripe Checkout or Payment Intent flow</li>
            </ol>

            <form id="payment-form" method="POST" action="process-payment.php">
                <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                <input type="hidden" name="plan_slug" value="<?php echo $plan['slug']; ?>">
                
                <div class="form-group">
                    <label>Card Holder Name</label>
                    <input type="text" name="card_name" required placeholder="John Doe">
                </div>
                
                <div class="form-group">
                    <label>Card Number (Demo)</label>
                    <input type="text" name="card_number" placeholder="4242 4242 4242 4242" maxlength="19">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="text" name="expiry" placeholder="MM/YY" maxlength="5">
                    </div>
                    <div class="form-group">
                        <label>CVV</label>
                        <input type="text" name="cvv" placeholder="123" maxlength="4">
                    </div>
                </div>

                <div class="alert alert-info">
                    <strong>Note:</strong> In demo mode, any card information will work. In production, this will process a real payment through Stripe.
                </div>
                
                <button type="submit" class="btn-primary btn-block">
                    Subscribe Now - $<?php echo number_format($plan['price'], 2); ?>
                </button>
            </form>
        </div>

        <div class="security-badges">
            <p>🔒 Secure payment processing powered by Stripe</p>
            <p>✓ Cancel anytime • ✓ No hidden fees • ✓ Instant activation</p>
        </div>
    </div>
</div>

<style>
.checkout-container {
    max-width: 800px;
    margin: 2rem auto;
}

.checkout-container h2 {
    text-align: center;
    margin-bottom: 2rem;
}

.checkout-summary {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
    text-align: center;
}

.checkout-summary .price {
    font-size: 2rem;
    font-weight: bold;
    color: #e74c3c;
    margin: 1rem 0;
}

.checkout-summary .features {
    text-align: left;
    margin-top: 1.5rem;
}

.checkout-summary .features ul {
    list-style: none;
    padding: 0;
}

.checkout-summary .features li {
    padding: 0.5rem 0;
}

.payment-form {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.payment-note {
    background: #fff3cd;
    padding: 1rem;
    border-radius: 5px;
    margin-bottom: 1rem;
}

.payment-note ol {
    margin-top: 0.5rem;
    padding-left: 1.5rem;
}

.payment-note code {
    background: #f5f5f5;
    padding: 0.2rem 0.5rem;
    border-radius: 3px;
    font-family: monospace;
}

.security-badges {
    text-align: center;
    color: #666;
    padding: 1rem;
}
</style>

<?php include 'views/footer.php'; ?>