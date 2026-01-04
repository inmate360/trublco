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

// Check if user is a creator
$query = "SELECT * FROM creator_settings WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$creator_settings = $stmt->fetch();

if(!$creator_settings || !$creator_settings['is_creator']) {
    header('Location: become-creator.php');
    exit();
}

$coin_balance = $coinsSystem->getBalance($_SESSION['user_id']);
$stats = $coinsSystem->getStats($_SESSION['user_id']);

$success = '';
$error = '';

// Minimum withdrawal: 1000 coins
$min_withdrawal = 1000;

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['withdraw'])) {
    $amount = floatval($_POST['amount']);
    $bitcoin_address = trim($_POST['bitcoin_address']);
    
    if($amount < $min_withdrawal) {
        $error = "Minimum withdrawal is {$min_withdrawal} coins";
    } elseif($amount > $coin_balance) {
        $error = "Insufficient balance";
    } elseif(empty($bitcoin_address)) {
        $error = "Bitcoin address is required";
    } else {
        try {
            $db->beginTransaction();
            
            // Deduct coins
            $result = $coinsSystem->deductCoins(
                $_SESSION['user_id'],
                $amount,
                'withdrawal',
                'Withdrawal to Bitcoin: ' . $bitcoin_address,
                'withdrawal',
                null
            );
            
            if(!$result['success']) {
                throw new Exception($result['error']);
            }
            
            // Record withdrawal request
            $query = "INSERT INTO withdrawal_requests 
                      (user_id, amount, bitcoin_address, status, requested_at) 
                      VALUES (:user_id, :amount, :bitcoin_address, 'pending', NOW())";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':bitcoin_address', $bitcoin_address);
            $stmt->execute();
            
            $db->commit();
            
            $success = "Withdrawal request submitted! You'll receive Bitcoin within 24-48 hours.";
            $coin_balance = $coinsSystem->getBalance($_SESSION['user_id']);
            
        } catch(Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Get withdrawal history
$query = "SELECT * FROM withdrawal_requests 
          WHERE user_id = :user_id 
          ORDER BY requested_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$withdrawal_history = $stmt->fetchAll();

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/light-theme.css">

<style>
.withdraw-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 20px;
}

.balance-card {
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    color: white;
    margin-bottom: 2rem;
}

.balance-amount {
    font-size: 4rem;
    font-weight: 800;
    margin: 1rem 0;
}

.withdrawal-table {
    width: 100%;
    border-collapse: collapse;
}

.withdrawal-table th,
.withdrawal-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-pending {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

.status-completed {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.status-failed {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}
</style>

<div class="page-content">
    <div class="withdraw-container">
        
        <div class="card" style="background: linear-gradient(135deg, #4267F5, #1D9BF0); color: white; margin-bottom: 2rem;">
            <div style="padding: 2rem;">
                <h1 style="margin: 0 0 0.5rem;">💸 Withdraw Earnings</h1>
                <p style="opacity: 0.9; margin: 0;">Convert your coins to Bitcoin</p>
            </div>
        </div>
        
        <!-- Balance Display -->
        <div class="balance-card">
            <div style="font-size: 3rem; margin-bottom: 1rem;">💰</div>
            <div style="opacity: 0.9;">Available Balance</div>
            <div class="balance-amount"><?php echo number_format($coin_balance, 0); ?></div>
            <div style="opacity: 0.9;">coins</div>
            <div style="margin-top: 1rem; font-size: 1.1rem;">
                ≈ $<?php echo number_format($coin_balance * 0.05, 2); ?> USD
            </div>
        </div>
        
        <?php if($success): ?>
        <div class="alert alert-success" style="margin-bottom: 2rem;">
            ✅ <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error" style="margin-bottom: 2rem;">
            ❌ <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <!-- Withdrawal Form -->
        <div class="card" style="margin-bottom: 2rem;">
            <h3 style="margin-bottom: 2rem;">💸 Request Withdrawal</h3>
            
            <form method="POST">
                <div class="form-group">
                    <label>Amount (coins) *</label>
                    <input type="number" 
                           name="amount" 
                           class="form-control" 
                           min="<?php echo $min_withdrawal; ?>"
                           max="<?php echo $coin_balance; ?>"
                           step="1"
                           placeholder="Enter amount..."
                           required>
                    <small style="color: var(--text-gray);">
                        Minimum: <?php echo number_format($min_withdrawal); ?> coins 
                        (≈ $<?php echo number_format($min_withdrawal * 0.05, 2); ?>)
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Bitcoin Address *</label>
                    <input type="text" 
                           name="bitcoin_address" 
                           class="form-control" 
                           placeholder="Enter your Bitcoin wallet address..."
                           required>
                    <small style="color: var(--text-gray);">
                        Make sure this is correct! We cannot reverse Bitcoin transactions.
                    </small>
                </div>
                
                <div style="background: rgba(251, 191, 36, 0.1); border: 2px solid #fbbf24; padding: 1.5rem; border-radius: 15px; margin: 2rem 0;">
                    <h4 style="color: #fbbf24; margin-bottom: 1rem;">⚠️ Important Information</h4>
                    <ul style="color: var(--text-gray); line-height: 2; margin: 0;">
                        <li>Processing time: 24-48 hours</li>
                        <li>Network fees may apply</li>
                        <li>Minimum withdrawal: <?php echo number_format($min_withdrawal); ?> coins</li>
                        <li>Withdrawals are final and cannot be cancelled</li>
                    </ul>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <a href="/creator-dashboard.php" class="btn-secondary">Cancel</a>
                    <button type="submit" name="withdraw" class="btn-primary" <?php echo $coin_balance < $min_withdrawal ? 'disabled' : ''; ?>>
                        💸 Request Withdrawal
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Withdrawal History -->
        <?php if(!empty($withdrawal_history)): ?>
        <div class="card">
            <h3 style="margin-bottom: 2rem;">📜 Withdrawal History</h3>
            <div style="overflow-x: auto;">
                <table class="withdrawal-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Bitcoin Address</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($withdrawal_history as $withdrawal): ?>
                        <tr>
                            <td><?php echo date('M j, Y g:i A', strtotime($withdrawal['requested_at'])); ?></td>
                            <td style="font-weight: 700;">
                                <?php echo number_format($withdrawal['amount']); ?> coins
                            </td>
                            <td style="font-family: monospace; font-size: 0.85rem;">
                                <?php echo substr($withdrawal['bitcoin_address'], 0, 10); ?>...<?php echo substr($withdrawal['bitcoin_address'], -6); ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $withdrawal['status']; ?>">
                                    <?php echo ucfirst($withdrawal['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<?php include 'views/footer.php'; ?>