<?php
session_start();
require_once 'config/database.php';
require_once 'classes/BitcoinService.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$bitcoin = new BitcoinService($db);

$config = require 'config/bitcoin.php';
$plans = $config['plans'];
$btc_price = $bitcoin->getBitcoinPrice();

include 'views/header.php';
?>

<style>
.subscription-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.subscription-header {
    text-align: center;
    margin-bottom: 3rem;
}

.subscription-header h1 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.bitcoin-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    padding: 0.5rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
    margin-bottom: 1rem;
}

.plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.plan-card {
    background: var(--card-bg);
    border: 3px solid var(--border-color);
    border-radius: 20px;
    padding: 2rem;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}

.plan-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.plan-card.featured {
    border-color: var(--featured-gold);
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.05), rgba(245, 158, 11, 0.05));
}

.plan-card.featured::before {
    content: '⭐ MOST POPULAR';
    position: absolute;
    top: 1rem;
    right: -2rem;
    background: var(--featured-gold);
    color: white;
    padding: 0.25rem 3rem;
    transform: rotate(45deg);
    font-size: 0.75rem;
    font-weight: 700;
}

.plan-name {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: var(--text-white);
}

.plan-price {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.price-usd {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary-blue);
}

.price-btc {
    font-size: 1.2rem;
    color: var(--text-gray);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.plan-duration {
    color: var(--text-gray);
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
}

.plan-features {
    list-style: none;
    padding: 0;
    margin: 0 0 2rem;
}

.plan-features li {
    padding: 0.75rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--text-white);
}

.plan-features li i {
    color: var(--success-green);
    font-size: 1.2rem;
}

.subscribe-btn {
    width: 100%;
    padding: 1rem;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
}

.subscribe-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(66, 103, 245, 0.4);
}

.subscribe-btn.featured {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.subscribe-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btc-info {
    background: rgba(245, 158, 11, 0.1);
    border: 2px solid rgba(245, 158, 11, 0.3);
    border-radius: 15px;
    padding: 2rem;
    text-align: center;
}
</style>

<div class="subscription-container">
    
    <div class="subscription-header">
        <div class="bitcoin-badge">
            <i class="bi bi-currency-bitcoin"></i>
            Pay with Bitcoin
        </div>
        <h1>Choose Your Premium Plan</h1>
        <p class="text-muted">Unlock exclusive features with Bitcoin payment</p>
        <p class="text-muted">Current BTC Price: <strong>$<?php echo number_format($btc_price, 2); ?></strong></p>
    </div>

    <div class="plans-grid">
        <?php foreach($plans as $key => $plan): ?>
        <div class="plan-card <?php echo $key === 'premium' ? 'featured' : ''; ?>">
            <div class="plan-name">
                <?php if($key === 'plus'): ?>
                    <i class="bi bi-plus-circle-fill text-primary"></i>
                <?php elseif($key === 'premium'): ?>
                    <i class="bi bi-gem text-warning"></i>
                <?php else: ?>
                    <i class="bi bi-star-fill text-warning"></i>
                <?php endif; ?>
                <?php echo $plan['name']; ?>
            </div>
            
            <div class="plan-price">
                <div class="price-usd">
                    $<?php echo number_format($plan['price_usd'], 2); ?>
                </div>
                <div class="price-btc">
                    <i class="bi bi-currency-bitcoin"></i>
                    <?php echo number_format($bitcoin->usdToBtc($plan['price_usd']), 8); ?> BTC
                </div>
            </div>
            
            <div class="plan-duration">
                <i class="bi bi-calendar-check"></i> <?php echo $plan['duration_days']; ?> Days
            </div>
            
            <ul class="plan-features">
                <?php foreach($plan['features'] as $feature): ?>
                <li>
                    <i class="bi bi-check-circle-fill"></i>
                    <?php echo $feature; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <button class="subscribe-btn <?php echo $key === 'premium' ? 'featured' : ''; ?>" 
                    onclick="subscribePlan('<?php echo $key; ?>')">
                <i class="bi bi-currency-bitcoin"></i>
                Subscribe with Bitcoin
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="btc-info">
        <h3><i class="bi bi-shield-check"></i> Secure Bitcoin Payments</h3>
        <p class="mb-0">
            All Bitcoin payments are processed securely through Coinbase Commerce. 
            Your subscription activates automatically once payment is confirmed (typically 3-6 confirmations).
        </p>
    </div>
</div>

<script>
function subscribePlan(planType) {
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Creating Payment...';
    
    fetch('/api/bitcoin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=create_subscription_charge&plan_type=' + planType
    })
    .then(response => response.json())
    .then(data => {
        if(data.success && data.hosted_url) {
            // Redirect to Coinbase Commerce payment page
            window.location.href = data.hosted_url;
        } else {
            alert(data.error || 'Failed to create payment');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-currency-bitcoin"></i> Subscribe with Bitcoin';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-currency-bitcoin"></i> Subscribe with Bitcoin';
    });
}
</script>

<?php include 'views/footer.php'; ?>