<?php
session_start();
require_once '../config/database.php';
require_once '../classes/SecurityLogger.php';
require_once '../classes/SpamProtection.php';
require_once '../classes/RateLimiter.php';

// Check if user is admin
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check admin status
$query = "SELECT is_admin FROM users WHERE id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

if(!$user || !$user['is_admin']) {
    header('Location: ../index.php');
    exit();
}

$securityLogger = new SecurityLogger($db);
$spamProtection = new SpamProtection($db);

// Get recent security logs
$recentLogs = $securityLogger->getRecentLogs(50);
$criticalLogs = $securityLogger->getRecentLogs(20, 'critical');

// Get blocked IPs
$query = "SELECT * FROM blocked_ips ORDER BY blocked_at DESC LIMIT 50";
$stmt = $db->prepare($query);
$stmt->execute();
$blockedIPs = $stmt->fetchAll();

// Get rate limit stats
$query = "SELECT action, COUNT(*) as count 
          FROM rate_limits 
          WHERE blocked_until > NOW() 
          GROUP BY action";
$stmt = $db->prepare($query);
$stmt->execute();
$rateLimitStats = $stmt->fetchAll();

// Handle unblock IP
if(isset($_POST['unblock_ip'])) {
    $ip = $_POST['ip_address'];
    $spamProtection->unblockIP($ip);
    header('Location: security-dashboard.php?unblocked=1');
    exit();
}

include '../views/header.php';
?>

<link rel="stylesheet" href="../assets/css/dark-blue-theme.css">

<style>
.admin-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-gray);
    font-size: 0.9rem;
}

.admin-section {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.log-entry {
    padding: 1rem;
    margin: 0.5rem 0;
    background: rgba(66, 103, 245, 0.05);
    border-left: 3px solid var(--primary-blue);
    border-radius: 8px;
}

.log-entry.critical {
    border-left-color: var(--danger-red);
    background: rgba(239, 68, 68, 0.1);
}

.log-entry.high {
    border-left-color: var(--warning-orange);
    background: rgba(245, 158, 11, 0.1);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: rgba(66, 103, 245, 0.1);
    padding: 0.75rem;
    text-align: left;
    color: var(--text-white);
    font-weight: 600;
    border-bottom: 2px solid var(--border-color);
}

.data-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-gray);
}
</style>

<div class="admin-container">
    <div style="margin-bottom: 2rem;">
        <h1>🔒 Security Dashboard</h1>
        <p style="color: var(--text-gray);">Monitor security events and manage blocked IPs</p>
    </div>

    <?php if(isset($_GET['unblocked'])): ?>
    <div class="alert alert-success">IP address unblocked successfully!</div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value" style="color: var(--danger-red);">
                <?php echo count($criticalLogs); ?>
            </div>
            <div class="stat-label">Critical Events (24h)</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value" style="color: var(--warning-orange);">
                <?php echo count($blockedIPs); ?>
            </div>
            <div class="stat-label">Blocked IPs</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value" style="color: var(--info-cyan);">
                <?php echo array_sum(array_column($rateLimitStats, 'count')); ?>
            </div>
            <div class="stat-label">Active Rate Limits</div>
        </div>
    </div>

    <?php if(count($criticalLogs) > 0): ?>
    <div class="admin-section">
        <h2 style="color: var(--danger-red); margin-bottom: 1rem;">⚠️ Critical Security Events</h2>
        <?php foreach($criticalLogs as $log): ?>
        <div class="log-entry critical">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <strong style="color: var(--danger-red);"><?php echo htmlspecialchars($log['action']); ?></strong>
                <span style="color: var(--text-gray); font-size: 0.85rem;">
                    <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                </span>
            </div>
            <div style="color: var(--text-gray); font-size: 0.9rem;">
                IP: <code><?php echo htmlspecialchars($log['ip_address']); ?></code>
                <?php if($log['username']): ?>
                | User: <?php echo htmlspecialchars($log['username']); ?>
                <?php endif; ?>
            </div>
            <?php if($log['details']): ?>
            <div style="color: var(--text-gray); margin-top: 0.5rem; font-size: 0.85rem;">
                <?php echo htmlspecialchars($log['details']); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="admin-section">
        <h2 style="margin-bottom: 1rem;">🚫 Blocked IP Addresses</h2>
        <?php if(count($blockedIPs) > 0): ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Reason</th>
                        <th>Blocked At</th>
                        <th>Expires</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($blockedIPs as $block): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($block['ip_address']); ?></code></td>
                        <td><?php echo htmlspecialchars($block['reason']); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($block['blocked_at'])); ?></td>
                        <td>
                            <?php if($block['expires_at']): ?>
                                <?php echo date('M j, Y g:i A', strtotime($block['expires_at'])); ?>
                            <?php else: ?>
                                <span style="color: var(--danger-red);">Permanent</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="ip_address" value="<?php echo htmlspecialchars($block['ip_address']); ?>">
                                <button type="submit" name="unblock_ip" class="btn-secondary btn-small">
                                    Unblock
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="text-align: center; color: var(--text-gray); padding: 2rem;">No blocked IPs</p>
        <?php endif; ?>
    </div>

    <div class="admin-section">
        <h2 style="margin-bottom: 1rem;">📊 Recent Security Logs</h2>
        <?php foreach(array_slice($recentLogs, 0, 20) as $log): ?>
        <div class="log-entry <?php echo strtolower($log['severity']); ?>">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <strong><?php echo htmlspecialchars($log['action']); ?></strong>
                <span style="color: var(--text-gray); font-size: 0.85rem;">
                    <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                </span>
            </div>
            <div style="color: var(--text-gray); font-size: 0.9rem;">
                IP: <code><?php echo htmlspecialchars($log['ip_address']); ?></code>
                <?php if($log['username']): ?>
                | User: <?php echo htmlspecialchars($log['username']); ?>
                <?php endif; ?>
                | Severity: <span style="color: var(--warning-orange);"><?php echo ucfirst($log['severity']); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="margin-top: 2rem; text-align: center;">
        <a href="dashboard.php" class="btn-secondary">← Back to Dashboard</a>
    </div>
</div>

<?php include '../views/footer.php'; ?>