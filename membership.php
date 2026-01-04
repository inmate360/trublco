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

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-pink-900 to-red-900 py-12">
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDEzNGg3djdjMCAxLjEwNS0uODk1IDItMiAyaC0zYy0xLjEwNSAwLTItLjg5NS0yLTJ2LTd6bTAtMTRoN3Y3YzAgMS4xMDUtLjg5NSAyLTIgMmgtM2MtMS4xMDUgMC0yLS44OTUtMi0ydi03eiIvPjwvZz48L2c+PC9zdmc+')] opacity-10"></div>
    
    <div class="relative mx-auto max-w-6xl px-4 text-center">
        <!-- Bitcoin Badge -->
        <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-yellow-500 to-orange-500 px-4 py-2 text-sm font-bold text-white shadow-lg">
            <i class="bi bi-currency-bitcoin"></i>
            Pay with Bitcoin
        </div>
        
        <h1 class="mb-3 bg-gradient-to-r from-white via-pink-200 to-white bg-clip-text text-4xl font-bold text-transparent md:text-5xl">
            <i class="bi bi-gem mr-2 text-purple-300"></i>
            Choose Your Premium Plan
        </h1>
        
        <p class="mb-2 text-base text-pink-200">Unlock exclusive features with secure Bitcoin payment</p>
        
        <div class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-4 py-2 backdrop-blur-sm">
            <span class="text-sm text-pink-200">Current BTC Price:</span>
            <span class="text-lg font-bold text-white">$<?php echo number_format($btc_price, 2); ?></span>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-8">
    <div class="mx-auto max-w-6xl px-4">
        
        <!-- Plans Grid -->
        <div class="mb-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach($plans as $key => $plan): ?>
            <div class="group relative overflow-hidden rounded-lg border transition-all hover:shadow-2xl
                <?php echo $key === 'premium' ? 'border-yellow-500 bg-gradient-to-br from-yellow-600/20 to-orange-600/20' : 'border-gh-border bg-gh-panel hover:border-gh-accent'; ?>">
                
                <!-- Featured Badge -->
                <?php if($key === 'premium'): ?>
                <div class="absolute right-0 top-4 z-10">
                    <div class="flex items-center gap-1 rounded-l-full bg-gradient-to-r from-yellow-500 to-orange-500 px-3 py-1 text-xs font-bold text-black shadow-lg">
                        <i class="bi bi-star-fill"></i>
                        MOST POPULAR
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="p-6">
                    <!-- Plan Icon & Name -->
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg text-2xl shadow-lg
                            <?php if($key === 'plus'): ?>
                                bg-gradient-to-br from-blue-500 to-cyan-600
                            <?php elseif($key === 'premium'): ?>
                                bg-gradient-to-br from-yellow-500 to-orange-600
                            <?php else: ?>
                                bg-gradient-to-br from-purple-500 to-pink-600
                            <?php endif; ?>">
                            <?php if($key === 'plus'): ?>
                                <i class="bi bi-plus-circle-fill text-white"></i>
                            <?php elseif($key === 'premium'): ?>
                                <i class="bi bi-gem text-white"></i>
                            <?php else: ?>
                                <i class="bi bi-star-fill text-white"></i>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-2xl font-bold text-white"><?php echo $plan['name']; ?></h3>
                    </div>
                    
                    <!-- Pricing -->
                    <div class="mb-4">
                        <div class="mb-2 text-4xl font-bold
                            <?php echo $key === 'premium' ? 'text-yellow-400' : 'text-gh-accent'; ?>">
                            $<?php echo number_format($plan['price_usd'], 2); ?>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gh-muted">
                            <i class="bi bi-currency-bitcoin text-yellow-500"></i>
                            <span><?php echo number_format($bitcoin->usdToBtc($plan['price_usd']), 8); ?> BTC</span>
                        </div>
                        <div class="mt-2 flex items-center gap-2 text-xs text-gh-muted">
                            <i class="bi bi-calendar-check"></i>
                            <span><?php echo $plan['duration_days']; ?> Days</span>
                        </div>
                    </div>
                    
                    <!-- Features -->
                    <ul class="mb-6 space-y-3">
                        <?php foreach($plan['features'] as $feature): ?>
                        <li class="flex items-start gap-2 text-sm text-white">
                            <i class="bi bi-check-circle-fill mt-0.5 shrink-0 text-green-500"></i>
                            <span><?php echo $feature; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <!-- Subscribe Button -->
                    <button 
                        class="group/btn w-full rounded-lg py-3 text-sm font-bold text-white shadow-lg transition-all hover:brightness-110
                            <?php echo $key === 'premium' 
                                ? 'bg-gradient-to-r from-yellow-500 to-orange-500' 
                                : 'bg-gradient-to-r from-pink-600 to-purple-600'; ?>"
                        onclick="subscribePlan('<?php echo $key; ?>')">
                        <i class="bi bi-currency-bitcoin"></i>
                        Subscribe with Bitcoin
                        <i class="bi bi-arrow-right ml-1 transition-transform group-hover/btn:translate-x-1"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Bitcoin Info Section -->
        <div class="rounded-lg border border-yellow-500/30 bg-gradient-to-br from-yellow-600/10 to-orange-600/10 p-6">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-yellow-500 text-2xl text-white shadow-lg">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div>
                    <h3 class="mb-2 text-xl font-bold text-white">
                        <i class="bi bi-currency-bitcoin text-yellow-500"></i>
                        Secure Bitcoin Payments
                    </h3>
                    <p class="text-sm leading-relaxed text-gh-muted">
                        All Bitcoin payments are processed securely through <strong class="text-yellow-400">Coinbase Commerce</strong>. 
                        Your subscription activates automatically once payment is confirmed (typically 3-6 confirmations). 
                        Your privacy and security are our top priority.
                    </p>
                    
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="flex items-center gap-2 text-xs text-gh-muted">
                            <i class="bi bi-lock-fill text-green-500"></i>
                            <span>Encrypted</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gh-muted">
                            <i class="bi bi-lightning-charge-fill text-yellow-500"></i>
                            <span>Fast Processing</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gh-muted">
                            <i class="bi bi-check-circle-fill text-green-500"></i>
                            <span>Auto-Activation</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Benefits Overview -->
        <div class="mt-8 rounded-lg border border-gh-border bg-gh-panel p-6">
            <h3 class="mb-4 text-center text-xl font-bold text-white">
                <i class="bi bi-star-fill mr-2 text-yellow-500"></i>
                Why Go Premium?
            </h3>
            <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-4">
                <div class="text-center">
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-pink-500 to-purple-600 text-lg text-white">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                    <p class="text-sm text-gh-muted">Increased Visibility</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-cyan-600 text-lg text-white">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>
                    <p class="text-sm text-gh-muted">Unlimited Messages</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-green-500 to-emerald-600 text-lg text-white">
                        <i class="bi bi-patch-check-fill"></i>
                    </div>
                    <p class="text-sm text-gh-muted">Verified Badge</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-yellow-500 to-orange-600 text-lg text-white">
                        <i class="bi bi-megaphone-fill"></i>
                    </div>
                    <p class="text-sm text-gh-muted">Ad Priority</p>
                </div>
            </div>
        </div>
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
            window.location.href = data.hosted_url;
        } else {
            alert(data.error || 'Failed to create payment');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-currency-bitcoin"></i> Subscribe with Bitcoin <i class="bi bi-arrow-right ml-1"></i>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-currency-bitcoin"></i> Subscribe with Bitcoin <i class="bi bi-arrow-right ml-1"></i>';
    });
}
</script>

<?php include 'views/footer.php'; ?>
