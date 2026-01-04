<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Moderator.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$moderator = new Moderator($db);

// Check if user is moderator
if(!$moderator->isModerator($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$success = '';
$error = '';

// Handle report actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['resolve_report'])) {
        $report_id = $_POST['report_id'];
        $action = $_POST['action_taken'] ?? 'Reviewed and resolved';
        
        if($moderator->resolveReport($report_id, $_SESSION['user_id'], $action)) {
            $success = 'Report resolved successfully!';
        } else {
            $error = 'Failed to resolve report';
        }
    }
    
    if(isset($_POST['dismiss_report'])) {
        $report_id = $_POST['report_id'];
        
        if($moderator->dismissReport($report_id, $_SESSION['user_id'])) {
            $success = 'Report dismissed';
        } else {
            $error = 'Failed to dismiss report';
        }
    }
}

// Get pending reports
$pending_reports = $moderator->getPendingReports();

// Get recent resolved reports
$recent_reports = $moderator->getRecentResolvedReports(20);

include '../views/header.php';
?>

<link rel="stylesheet" href="../assets/css/dark-blue-theme.css">

<style>
.moderator-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 20px;
}

.moderator-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.moderator-nav {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.moderator-nav a {
    padding: 0.75rem 1.5rem;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-white);
    transition: all 0.3s;
}

.moderator-nav a:hover, .moderator-nav a.active {
    background: rgba(66, 103, 245, 0.1);
    border-color: var(--primary-blue);
    color: var(--primary-blue);
}

.moderator-section {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.moderator-section h2 {
    margin-bottom: 1.5rem;
    color: var(--primary-blue);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: rgba(66, 103, 245, 0.1);
    padding: 1rem;
    text-align: left;
    color: var(--text-white);
    font-weight: 600;
    border-bottom: 2px solid var(--border-color);
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-gray);
}

.data-table tr:hover {
    background: rgba(66, 103, 245, 0.05);
}

.action-btn {
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.85rem;
    display: inline-block;
    margin-right: 0.5rem;
    border: none;
    cursor: pointer;
}

.action-btn.view {
    background: rgba(6, 182, 212, 0.2);
    color: var(--info-cyan);
}

.action-btn.approve {
    background: rgba(16, 185, 129, 0.2);
    color: var(--success-green);
}

.action-btn.reject {
    background: rgba(239, 68, 68, 0.2);
    color: var(--danger-red);
}
</style>

<div class="moderator-container">
    <div class="moderator-header">
        <h1>🚨 Moderate Reports</h1>
        <p style="color: var(--text-gray);">Review and resolve user reports</p>
    </div>

    <div class="moderator-nav">
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="moderate-listings.php">📝 Moderate Listings</a>
        <a href="moderate-users.php">👥 Moderate Users</a>
        <a href="moderate-reports.php" class="active">🚨 Reports</a>
    </div>

    <?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="moderator-section">
        <h2>⏳ Pending Reports (<?php echo count($pending_reports); ?>)</h2>
        
        <?php if(count($pending_reports) > 0): ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reporter</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pending_reports as $report): ?>
                    <tr>
                        <td><?php echo $report['id']; ?></td>
                        <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                        <td>
                            <span style="padding: 0.25rem 0.6rem; background: rgba(66, 103, 245, 0.2); border-radius: 8px; font-size: 0.85rem;">
                                <?php echo ucfirst($report['reported_type']); ?>
                            </span>
                        </td>
                        <td style="color: var(--danger-red);">
                            <?php echo htmlspecialchars($report['reason']); ?>
                        </td>
                        <td>
                            <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars($report['description']); ?>
                            </div>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($report['created_at'])); ?></td>
                        <td>
                            <?php if($report['reported_type'] == 'listing'): ?>
                            <a href="../listing.php?id=<?php echo $report['reported_id']; ?>" 
                               class="action-btn view" target="_blank">View</a>
                            <?php elseif($report['reported_type'] == 'user'): ?>
                            <a href="../profile.php?id=<?php echo $report['reported_id']; ?>" 
                               class="action-btn view" target="_blank">View</a>
                            <?php endif; ?>
                            
                            <button class="action-btn approve" onclick="showResolveModal(<?php echo $report['id']; ?>)">
                                Resolve
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                <button type="submit" name="dismiss_report" class="action-btn reject" 
                                        onclick="return confirm('Dismiss this report?');">
                                    Dismiss
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 3rem; background: rgba(66, 103, 245, 0.05); border-radius: 10px;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">✓</div>
            <h3>No Pending Reports</h3>
            <p style="color: var(--text-gray);">All reports have been reviewed</p>
        </div>
        <?php endif; ?>
    </div>

    <div class="moderator-section">
        <h2>Recent Actions</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reporter</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Action</th>
                        <th>Moderator</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_reports as $report): ?>
                    <tr>
                        <td><?php echo $report['id']; ?></td>
                        <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                        <td><?php echo ucfirst($report['reported_type']); ?></td>
                        <td><?php echo htmlspecialchars($report['reason']); ?></td>
                        <td>
                            <span style="color: <?php echo $report['status'] == 'resolved' ? 'var(--success-green)' : 'var(--text-gray)'; ?>">
                                <?php echo ucfirst($report['status']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($report['action_taken'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($report['moderator_name'] ?? 'System'); ?></td>
                        <td><?php echo date('M j, Y', strtotime($report['resolved_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Resolve Modal -->
<div id="resolveModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center;">
    <div class="card" style="max-width: 500px; width: 90%;">
        <h3 style="margin-bottom: 1rem;">Resolve Report</h3>
        <form method="POST">
            <input type="hidden" name="report_id" id="resolveReportId">
            <div class="form-group">
                <label>Action Taken</label>
                <select name="action_taken" required>
                    <option value="">Select action...</option>
                    <option value="Content removed">Content removed</option>
                    <option value="User warned">User warned</option>
                    <option value="User suspended">User suspended</option>
                    <option value="User banned">User banned</option>
                    <option value="No violation found">No violation found</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <button type="button" class="btn-secondary btn-block" onclick="closeResolveModal()">Cancel</button>
                <button type="submit" name="resolve_report" class="btn-success btn-block">Resolve</button>
            </div>
        </form>
    </div>
</div>

<script>
function showResolveModal(reportId) {
    document.getElementById('resolveReportId').value = reportId;
    document.getElementById('resolveModal').style.display = 'flex';
}

function closeResolveModal() {
    document.getElementById('resolveModal').style.display = 'none';
}

document.getElementById('resolveModal')?.addEventListener('click', function(e) {
    if(e.target === this) closeResolveModal();
});
</script>

<?php include '../views/footer.php'; ?>