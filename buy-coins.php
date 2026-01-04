<?php
session_start();
require_once 'config/database.php';
require_once 'classes/CoinsSystem.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$coinsSystem = new CoinsSystem($db);

$balance = $coinsSystem->getBalance($_SESSION['user_id']);
$stats = $coinsSystem->getStats($_SESSION['user_id']);

include 'views/header.php';
?>

<style>
:root {
  /* GitHub Dark Theme Colors */
  --gh-canvas-default: #0d1117;
  --gh-canvas-overlay: #161b22;
  --gh-canvas-inset: #010409;
  --gh-canvas-subtle: #161b22;

  --gh-fg-default: #e6edf3;
  --gh-fg-muted: #7d8590;
  --gh-fg-subtle: #6e7681;
  --gh-fg-on-emphasis: #ffffff;

  --gh-border-default: #30363d;
  --gh-border-muted: #21262d;
  --gh-border-subtle: #21262d;

  --gh-accent-fg: #2f81f7;
  --gh-accent-emphasis: #1f6feb;
  --gh-accent-muted: rgba(56,139,253,0.4);
  --gh-accent-subtle: rgba(56,139,253,0.15);

  --gh-success-fg: #3fb950;
  --gh-success-emphasis: #238636;
  --gh-success-muted: rgba(46,160,67,0.4);
  --gh-success-subtle: rgba(46,160,67,0.15);

  --gh-attention-fg: #d29922;
  --gh-attention-emphasis: #9e6a03;
  --gh-attention-muted: rgba(187,128,9,0.4);
  --gh-attention-subtle: rgba(187,128,9,0.15);

  --gh-danger-fg: #f85149;
  --gh-danger-emphasis: #da3633;
  --gh-danger-muted: rgba(248,81,73,0.4);
  --gh-danger-subtle: rgba(248,81,73,0.15);

  --gh-done-fg: #a371f7;
  --gh-done-emphasis: #8957e5;

  --gh-sponsors-fg: #db61a2;
  --gh-sponsors-emphasis: #bf4b8a;

  /* Gold Colors */
  --gold-light: #ffd700;
  --gold-medium: #ffb700;
  --gold-dark: #ff8c00;
}

body {
  background: var(--gh-canvas-default);
  color: var(--gh-fg-default);
}

/* Apply GitHub theme colors to existing classes */
.bg-gh-panel { background-color: var(--gh-canvas-overlay); }
.bg-gh-panel2 { background-color: var(--gh-canvas-subtle); }
.border-gh-border { border-color: var(--gh-border-default); }
.text-gh-fg { color: var(--gh-fg-default); }
.text-gh-muted { color: var(--gh-fg-muted); }
.text-gh-accent { color: var(--gh-accent-fg); }
.text-gh-success { color: var(--gh-success-fg); }
.text-gh-danger { color: var(--gh-danger-fg); }
.text-gh-warning { color: var(--gh-attention-fg); }
.bg-gh-accent { background-color: var(--gh-accent-emphasis); }
.bg-gh-success { background-color: var(--gh-success-emphasis); }
.bg-gh-danger { background-color: var(--gh-danger-emphasis); }
.hover\:text-gh-accent:hover { color: var(--gh-accent-fg); }
.hover\:bg-gh-panel2:hover { background-color: var(--gh-canvas-subtle); }
.divide-gh-border > :not([hidden]) ~ :not([hidden]) { border-color: var(--gh-border-default); }

/* Coin packages styling */
.coin-package {
  background: var(--gh-canvas-overlay);
  border-color: var(--gh-border-default);
}

.coin-package:hover {
  border-color: var(--gh-accent-fg);
  background: var(--gh-canvas-subtle);
}

.popular-badge {
  background: linear-gradient(135deg, var(--gold-medium), var(--gold-dark));
}

.save-badge {
  background-color: var(--gh-success-emphasis);
}
</style>



<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/light-theme.css">

<style>
.coins-page {
    padding: 2rem 0;
}

.balance-card {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    border-radius: 20px;
    padding: 2.5rem;
    text-align: center;
    color: white;
    margin-bottom: 2rem;
    box-shadow: 0 10px 40px rgba(66, 103, 245, 0.3);
}

.balance-amount {
    font-size: 4rem;
    font-weight: 800;
    margin: 1rem 0;
}

.packages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
    margin: 2rem 0;
}

.package-card {
    background: var(--card-bg);
    border: 3px solid var(--border-color);
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}

.package-card:hover {
    transform: translateY(-8px);
    border-color: var(--primary-blue);
    box-shadow: 0 15px 50px rgba(66, 103, 245, 0.3);
}

.package-card.popular {
    border-color: #fbbf24;
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.1), rgba(245, 158, 11, 0.05));
}

.popular-badge {
    position: absolute;
    top: 1rem;
    right: -2rem;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: white;
    padding: 0.5rem 3rem;
    transform: rotate(45deg);
    font-weight: 700;
    font-size: 0.8rem;
}

.coin-amount {
    font-size: 3rem;
    font-weight: 800;
    color: var(--primary-blue);
    margin: 1rem 0;
}

.package-card.popular .coin-amount {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.price-tag {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 1rem;
}

.bonus-badge {
    background: var(--success-green);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: inline-block;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.stat-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-blue);
}

.stat-label {
    color: var(--text-gray);
    font-size: 0.9rem;
    margin-top: 0.5rem;
}
</style>

<div class="page-content">
    <div class="container">
        <div class="coins-page">
            
            <!-- Balance Card -->
            <div class="balance-card">
                <div style="font-size: 4rem; margin-bottom: 1rem;">💰</div>
                <h2 style="margin: 0; font-size: 1.2rem; opacity: 0.9;">Your Coin Balance</h2>
                <div class="balance-amount">
                    <?php echo number_format($balance, 0); ?>
                    <span style="font-size: 2rem; opacity: 0.8;">coins</span>
                </div>
                <p style="opacity: 0.9; font-size: 1.1rem;">Use coins to unlock exclusive content</p>
            </div>
            
            <!-- Coin Packages -->
            <div class="card">
                <h2 style="text-align: center; margin-bottom: 0.5rem;">💎 Buy Coins</h2>
                <p style="text-align: center; color: var(--text-gray); margin-bottom: 2rem;">
                    Purchase with Bitcoin - Instant delivery
                </p>
                
                <div class="packages-grid">
                    
                    <!-- 100 Coins -->
                    <div class="package-card">
                        <div class="coin-amount">100</div>
                        <div style="color: var(--text-gray); margin-bottom: 1rem;">coins</div>
                        <div class="price-tag">$4.99</div>
                        <button onclick="buyCoins(100, 4.99)" class="btn-primary btn-block">
                            ⚡ Buy with Bitcoin
                        </button>
                    </div>
                    
                    <!-- 500 Coins -->
                    <div class="package-card">
                        <div class="coin-amount">500</div>
                        <div style="color: var(--text-gray); margin-bottom: 1rem;">coins</div>
                        <div class="price-tag">$19.99</div>
                        <div class="bonus-badge">Save 20%</div>
                        <button onclick="buyCoins(500, 19.99)" class="btn-primary btn-block">
                            ⚡ Buy with Bitcoin
                        </button>
                    </div>
                    
                    <!-- 1000 Coins - Popular -->
                    <div class="package-card popular">
                        <div class="popular-badge">POPULAR</div>
                        <div class="coin-amount">1,000</div>
                        <div style="color: var(--text-gray); margin-bottom: 1rem;">coins</div>
                        <div class="price-tag">$34.99</div>
                        <div class="bonus-badge">Save 30%</div>
                        <button onclick="buyCoins(1000, 34.99)" class="btn-primary btn-block">
                            ⚡ Buy with Bitcoin
                        </button>
                    </div>
                    
                    <!-- 5000 Coins -->
                    <div class="package-card">
                        <div class="coin-amount">5,000</div>
                        <div style="color: var(--text-gray); margin-bottom: 1rem;">coins</div>
                        <div class="price-tag">$149.99</div>
                        <div class="bonus-badge">Save 40%</div>
                        <button onclick="buyCoins(5000, 149.99)" class="btn-primary btn-block">
                            ⚡ Buy with Bitcoin
                        </button>
                    </div>
                    
                    <!-- 10000 Coins -->
                    <div class="package-card">
                        <div class="coin-amount">10,000</div>
                        <div style="color: var(--text-gray); margin-bottom: 1rem;">coins</div>
                        <div class="price-tag">$249.99</div>
                        <div class="bonus-badge" style="background: #ef4444;">Save 50%</div>
                        <button onclick="buyCoins(10000, 249.99)" class="btn-primary btn-block">
                            ⚡ Buy with Bitcoin
                        </button>
                    </div>
                    
                </div>
            </div>
            
            <!-- Stats -->
            <div class="card">
                <h2 style="text-align: center; margin-bottom: 2rem;">📊 Your Stats</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['lifetime_purchased'] ?? 0, 0); ?></div>
                        <div class="stat-label">Total Purchased</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['lifetime_spent'] ?? 0, 0); ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['lifetime_earned'] ?? 0, 0); ?></div>
                        <div class="stat-label">Total Earned</div>
                    </div>
                </div>
            </div>
            
            <!-- How it works -->
            <div class="card" style="background: rgba(66, 103, 245, 0.05);">
                <h3 style="text-align: center; margin-bottom: 1.5rem;">❓ How It Works</h3>
                <div style="max-width: 600px; margin: 0 auto;">
                    <ol style="line-height: 2.5; color: var(--text-gray);">
                        <li><strong style="color: var(--text-white);">Select a package</strong> - Choose the amount of coins you want</li>
                        <li><strong style="color: var(--text-white);">Pay with Bitcoin</strong> - Complete payment via our secure Bitcoin gateway</li>
                        <li><strong style="color: var(--text-white);">Instant delivery</strong> - Coins are added to your account immediately</li>
                        <li><strong style="color: var(--text-white);">Unlock content</strong> - Use coins to purchase exclusive media from creators</li>
                    </ol>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
function buyCoins(amount, price) {
    // Redirect to Bitcoin payment integration
    window.location.href = `/bitcoin-payment.php?type=coins&amount=${amount}&price=${price}`;
}
</script>

<?php include 'views/footer.php'; ?>