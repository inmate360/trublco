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

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'deposit') {
        $amount = floatval($_POST['amount'] ?? 0);
        if ($amount > 0) {
            $result = $bitcoin->depositBtc($user_id, $amount);
            if ($result['success']) $success = "Successfully deposited " . number_format($amount, 8) . " BTC";
            else $error = $result['error'];
        }
    } elseif ($action === 'transfer') {
        $to_username = trim($_POST['to_username'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        if (!empty($to_username) && $amount > 0) {
            $result = $bitcoin->transferBtc($user_id, $to_username, $amount);
            if ($result['success']) $success = "Successfully sent " . number_format($amount, 8) . " BTC to " . htmlspecialchars($to_username);
            else $error = $result['error'];
        }
    } elseif ($action === 'buy_premium') {
        $plan = $_POST['plan'] ?? '';
        $result = $bitcoin->buyPremiumWithBtc($user_id, $plan);
        if ($result['success']) $success = "Premium activated successfully!";
        else $error = $result['error'];
    }
}

$wallet = $bitcoin->getUserWallet($user_id);
$transactions = $bitcoin->getTransactionHistory($user_id, 20);
$btc_price = $bitcoin->getBitcoinPrice();

include 'views/header.php';
?>

<div class="bg-gh-bg min-h-screen py-12">
    <div class="mx-auto max-w-6xl px-4">
        
        <?php if($error): ?>
            <div class="mb-6 rounded-lg border border-red-500/30 bg-red-500/10 p-4 text-red-400">
                <i class="bi bi-exclamation-circle mr-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="mb-6 rounded-lg border border-green-500/30 bg-green-500/10 p-4 text-green-400">
                <i class="bi bi-check-circle mr-2"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Wallet Header -->
        <div class="mb-8 rounded-2xl bg-gradient-to-br from-yellow-600 to-orange-700 p-8 shadow-xl">
            <div class="flex flex-col justify-between gap-6 md:flex-row md:items-center">
                <div>
                    <p class="text-sm font-bold uppercase tracking-wider text-white/70">Total Balance</p>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-5xl font-black text-white"><?php echo number_format($wallet['btc_balance'], 8); ?></span>
                        <span class="text-2xl font-bold text-white/70">BTC</span>
                    </div>
                    <p class="mt-2 text-xl font-medium text-white/80">≈ $<?php echo number_format($wallet['btc_balance'] * $btc_price, 2); ?> USD</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button onclick="showModal('deposit-modal')" class="rounded-xl bg-white px-6 py-3 font-bold text-orange-700 shadow-lg transition-all hover:scale-105">
                        <i class="bi bi-plus-lg mr-2"></i> Deposit
                    </button>
                    <button onclick="showModal('send-modal')" class="rounded-xl border-2 border-white/30 bg-white/10 px-6 py-3 font-bold text-white backdrop-blur-sm transition-all hover:bg-white/20">
                        <i class="bi bi-send mr-2"></i> Send
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-8 lg:grid-cols-3">
            <!-- Transactions -->
            <div class="lg:col-span-2">
                <h2 class="mb-4 text-xl font-bold text-white">Recent Transactions</h2>
                <div class="space-y-3">
                    <?php if(empty($transactions)): ?>
                        <div class="rounded-xl border border-gh-border bg-gh-panel p-12 text-center">
                            <i class="bi bi-clock-history text-4xl text-gh-muted"></i>
                            <p class="mt-2 text-gh-muted">No transactions yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($transactions as $tx): ?>
                            <div class="flex items-center justify-between rounded-xl border border-gh-border bg-gh-panel p-4">
                                <div class="flex items-center gap-4">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full <?php echo $tx['from_user_id'] == $user_id ? 'bg-red-500/20 text-red-500' : 'bg-green-500/20 text-green-500'; ?>">
                                        <i class="bi <?php echo $tx['from_user_id'] == $user_id ? 'bi-arrow-up-right' : 'bi-arrow-down-left'; ?>"></i>
                                    </div>
                                    <div>
                                        <p class="font-bold text-white">
                                            <?php 
                                            if($tx['type'] == 'deposit') echo "Deposit";
                                            elseif($tx['type'] == 'premium_purchase') echo "Premium Purchase";
                                            elseif($tx['from_user_id'] == $user_id) echo "Sent to " . htmlspecialchars($tx['to_username']);
                                            else echo "Received from " . htmlspecialchars($tx['from_username']);
                                            ?>
                                        </p>
                                        <p class="text-xs text-gh-muted"><?php echo date('M d, Y H:i', strtotime($tx['created_at'])); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold <?php echo $tx['from_user_id'] == $user_id ? 'text-red-400' : 'text-green-400'; ?>">
                                        <?php echo $tx['from_user_id'] == $user_id ? '-' : '+'; ?><?php echo number_format($tx['amount'], 8); ?>
                                    </p>
                                    <p class="text-xs text-gh-muted">$<?php echo number_format($tx['amount'] * $btc_price, 2); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Premium Plans -->
            <div>
                <h2 class="mb-4 text-xl font-bold text-white">Upgrade to Premium</h2>
                <div class="space-y-4">
                    <?php foreach($bitcoin->config['plans'] as $key => $plan): ?>
                        <div class="rounded-xl border border-gh-border bg-gh-panel p-6 transition-all hover:border-gh-accent">
                            <h3 class="text-lg font-bold text-white"><?php echo $plan['name']; ?></h3>
                            <div class="mt-2 flex items-baseline gap-1">
                                <span class="text-2xl font-black text-white">$<?php echo $plan['price_usd']; ?></span>
                                <span class="text-sm text-gh-muted">/ <?php echo $plan['duration_days']; ?> days</span>
                            </div>
                            <p class="mt-1 text-xs text-gh-accent">≈ <?php echo number_format($bitcoin->usdToBtc($plan['price_usd']), 8); ?> BTC</p>
                            <ul class="mt-4 space-y-2 text-sm text-gh-muted">
                                <?php foreach($plan['features'] as $feature): ?>
                                    <li class="flex items-center gap-2">
                                        <i class="bi bi-check2 text-green-500"></i> <?php echo $feature; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <form method="POST" class="mt-6">
                                <input type="hidden" name="action" value="buy_premium">
                                <input type="hidden" name="plan" value="<?php echo $key; ?>">
                                <button type="submit" class="w-full rounded-lg bg-gh-accent py-2 text-sm font-bold text-white transition-all hover:brightness-110">
                                    Buy with BTC
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="deposit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4">
    <div class="w-full max-w-md rounded-2xl border border-gh-border bg-gh-panel p-6">
        <h3 class="mb-4 text-xl font-bold text-white">Deposit Bitcoin</h3>
        <form method="POST">
            <input type="hidden" name="action" value="deposit">
            <div class="mb-4">
                <label class="mb-2 block text-sm font-medium text-gh-muted">Amount (BTC)</label>
                <input type="number" name="amount" step="0.00000001" min="0.000001" class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-2 text-white focus:border-gh-accent focus:outline-none" placeholder="0.001">
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 rounded-lg bg-gh-accent py-2 font-bold text-white">Deposit</button>
                <button type="button" onclick="hideModal('deposit-modal')" class="flex-1 rounded-lg border border-gh-border bg-gh-bg py-2 font-bold text-white">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="send-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4">
    <div class="w-full max-w-md rounded-2xl border border-gh-border bg-gh-panel p-6">
        <h3 class="mb-4 text-xl font-bold text-white">Send Bitcoin</h3>
        <form method="POST">
            <input type="hidden" name="action" value="transfer">
            <div class="mb-4">
                <label class="mb-2 block text-sm font-medium text-gh-muted">Recipient Username</label>
                <input type="text" name="to_username" class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-2 text-white focus:border-gh-accent focus:outline-none" placeholder="username">
            </div>
            <div class="mb-4">
                <label class="mb-2 block text-sm font-medium text-gh-muted">Amount (BTC)</label>
                <input type="number" name="amount" step="0.00000001" min="0.000001" class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-2 text-white focus:border-gh-accent focus:outline-none" placeholder="0.001">
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 rounded-lg bg-gh-accent py-2 font-bold text-white">Send</button>
                <button type="button" onclick="hideModal('send-modal')" class="flex-1 rounded-lg border border-gh-border bg-gh-bg py-2 font-bold text-white">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('flex'); }
function hideModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }
</script>

<?php include 'views/footer.php'; ?>
