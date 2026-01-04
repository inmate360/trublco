<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
$amount = isset($_GET['amount']) ? (int)$_GET['amount'] : 0;
$price = isset($_GET['price']) ? (float)$_GET['price'] : 0.0;

$allowedTypes = ['coins', 'subscription'];
if (!in_array($type, $allowedTypes, true) || $amount <= 0 || $price <= 0) {
    $error = 'Invalid payment request. Please check your parameters.';
}

if (isset($error)) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Bitcoin Payment Error</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{background:#020617;color:#e5e7eb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem}
            .box{background:#0b1120;padding:2.5rem;border-radius:1rem;max-width:480px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.8);border:2px solid #1e293b}
            h1{margin:0 0 1rem;font-size:1.75rem;color:#f97316}
            p{margin:.5rem 0;color:#9ca3af;line-height:1.6}
            a{color:#3b82f6;text-decoration:none;font-weight:600}
            a:hover{text-decoration:underline;color:#60a5fa}
            .icon{font-size:4rem;text-align:center;margin-bottom:1rem}
        </style>
    </head>
    <body>
        <div class="box">
            <div class="icon">⚠️</div>
            <h1>Payment Error</h1>
            <p><?php echo htmlspecialchars($error); ?></p>
            <p style="margin-top:1.5rem"><a href="buy-coins.php">← Go back to coin packages</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$database = new Database();
$db = $database->getConnection();
$userId = (int)$_SESSION['user_id'];

try {
    $stmt = $db->prepare("INSERT INTO bitcoin_payments (user_id, payment_type, amount_coins, amount_usd, status, created_at) VALUES (:user_id, :type, :coins, :usd, 'pending', NOW())");
    $stmt->execute([
        ':user_id' => $userId,
        ':type' => $type,
        ':coins' => $amount,
        ':usd' => $price
    ]);
    $paymentId = $db->lastInsertId();
} catch(Exception $e) {
    error_log('Bitcoin payment creation error: ' . $e->getMessage());
    $paymentId = 0;
}

$btcAddress = 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh';
$btcAmount = number_format($price / 50000, 8);

include 'views/header.php';
?>

<style>
:root{--bg-dark:#0a0a0f;--bg-card:#1a1a2e;--border:#2d2d44;--blue:#4267f5;--text:#fff;--gray:#a0a0b0;--orange:#f59e0b}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg-dark);color:var(--text)}
.container{max-width:800px;margin:0 auto;padding:2rem 1.5rem}
.card{background:var(--bg-card);border:2px solid var(--border);border-radius:20px;padding:2.5rem;margin-bottom:2rem}
.hero{background:linear-gradient(135deg,rgba(255,193,7,.1),rgba(255,152,0,.1));border:2px solid var(--orange);border-radius:20px;padding:2rem;text-align:center;margin-bottom:2rem}
.detail-row{display:flex;justify-content:space-between;align-items:center;padding:1rem 0;border-bottom:1px solid var(--border)}
.detail-row:last-child{border-bottom:none}
.detail-label{color:var(--gray);font-size:.9rem}
.detail-value{font-weight:700;font-size:1.1rem}
.qr-box{background:rgba(255,255,255,.05);padding:2rem;border-radius:15px;text-align:center;margin:2rem 0}
.address-box{background:rgba(255,152,0,.1);border:2px solid var(--orange);padding:1rem 1.5rem;border-radius:10px;margin:1rem 0;word-break:break-all;font-family:monospace;font-size:.95rem;position:relative}
.copy-btn{background:var(--orange);color:white;border:none;padding:.75rem 1.5rem;border-radius:10px;cursor:pointer;font-weight:600;transition:all .3s;margin-top:1rem}
.copy-btn:hover{background:#e68900;transform:translateY(-2px)}
.warning{background:rgba(239,68,68,.1);border:2px solid #ef4444;padding:1.5rem;border-radius:10px;margin:2rem 0}
.warning h3{color:#ef4444;margin-bottom:.5rem}
.step{background:rgba(66,103,245,.05);border:2px solid var(--blue);padding:1.5rem;border-radius:10px;margin:1rem 0}
.step h3{color:var(--blue);margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem}
.step-num{background:var(--blue);color:white;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700}
</style>

<div class="container">
    <div class="hero">
        <h1 style="font-size:2.5rem;margin-bottom:.5rem">₿ Bitcoin Payment</h1>
        <p style="color:var(--gray);font-size:1.1rem">Complete your purchase with Bitcoin</p>
    </div>

    <div class="card">
        <h2 style="margin-bottom:1.5rem">Payment Details</h2>
        <div class="detail-row">
            <span class="detail-label">Payment Type</span>
            <span class="detail-value" style="text-transform:capitalize"><?php echo htmlspecialchars($type);?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Amount</span>
            <span class="detail-value"><?php echo number_format($amount);?> coins</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Price (USD)</span>
            <span class="detail-value">$<?php echo number_format($price, 2);?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Bitcoin Amount</span>
            <span class="detail-value"><?php echo $btcAmount;?> BTC</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Payment ID</span>
            <span class="detail-value">#<?php echo $paymentId;?></span>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-bottom:1.5rem">How to Pay</h2>
        
        <div class="step">
            <h3><span class="step-num">1</span> Copy Bitcoin Address</h3>
            <p style="color:var(--gray);margin-bottom:1rem">Copy the address below to your Bitcoin wallet</p>
            <div class="address-box"><?php echo $btcAddress;?></div>
            <button class="copy-btn" onclick="copyAddress()" id="copyBtn">📋 Copy Address</button>
        </div>

        <div class="step">
            <h3><span class="step-num">2</span> Send Exact Amount</h3>
            <p style="color:var(--gray)">Send exactly <strong style="color:var(--text)"><?php echo $btcAmount;?> BTC</strong> to the address above</p>
        </div>

        <div class="step">
            <h3><span class="step-num">3</span> Wait for Confirmation</h3>
            <p style="color:var(--gray)">Your coins will be credited after 3 blockchain confirmations (typically 30-60 minutes)</p>
        </div>
    </div>

    <div class="warning">
        <h3>⚠️ Important Notes</h3>
        <ul style="list-style:none;padding:0;margin:.5rem 0">
            <li style="margin:.5rem 0">• Send only Bitcoin (BTC) to this address</li>
            <li style="margin:.5rem 0">• Sending any other cryptocurrency will result in permanent loss</li>
            <li style="margin:.5rem 0">• Make sure to send the exact amount shown</li>
            <li style="margin:.5rem 0">• This address is valid for 24 hours</li>
        </ul>
    </div>

    <div style="text-align:center;margin-top:2rem">
        <a href="account.php" style="background:var(--bg-card);color:var(--text);padding:1rem 2rem;border:2px solid var(--border);border-radius:10px;text-decoration:none;display:inline-block;font-weight:600">View Payment History</a>
    </div>
</div>

<script>
function copyAddress() {
    const address = '<?php echo $btcAddress;?>';
    navigator.clipboard.writeText(address).then(() => {
        const btn = document.getElementById('copyBtn');
        btn.textContent = '✓ Copied!';
        btn.style.background = '#10b981';
        setTimeout(() => {
            btn.textContent = '📋 Copy Address';
            btn.style.background = '';
        }, 2000);
    }).catch(() => {
        alert('Failed to copy. Please copy manually.');
    });
}
</script>

<?php include 'views/footer.php';?>