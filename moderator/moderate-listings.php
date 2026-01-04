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

// Handle listing actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['approve_listing'])) {
        $listing_id = $_POST['listing_id'];
        if($moderator->approveListing($listing_id, $_SESSION['user_id'])) {
            $success = 'Listing approved!';
        } else {
            $error = 'Failed to approve listing';
        }
    }
    
    if(isset($_POST['reject_listing'])) {
        $listing_id = $_POST['listing_id'];
        $reason = $_POST['rejection_reason'] ?? 'Violates community guidelines';
        if($moderator->rejectListing($listing_id, $_SESSION['user_id'], $reason)) {
            $success = 'Listing rejected';
        } else {
            $error = 'Failed to reject listing';
        }
    }
    
    if(isset($_POST['delete_listing'])) {
        $listing_id = $_POST['listing_id'];
        if($moderator->deleteListing($listing_id)) {
            $success = 'Listing deleted permanently';
        } else {
            $error = 'Failed to delete listing';
        }
    }
}

// Get pending listings
$pending_listings = $moderator->getPendingListings();

// Get flagged listings (reported)
$flagged_listings = $moderator->getFlaggedListings();

// Get recent actions
$recent_actions = $moderator->getRecentListingActions(20);

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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
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
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-blue);
}

.stat-label {
    color: var(--text-gray);
    font-size: 0.9rem;
    margin-top: 0.5rem;
}
</style>

<div class="moderator-container">
    <div class="moderator-header">
        <h1>📝 Moderate Listings</h1>
        <p style="color: var(--text-gray);">Review and approve classified ads</p>
    </div>

    <div class="moderator-nav">
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="moderate-listings.php" class="active">📝 Moderate Listings</a>
        <a href="moderate-users.php">👥 Moderate Users</a>
        <a href="moderate-reports.php">🚨 Reports</a>
    </div>

    <?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo count($pending_listings); ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($flagged_listings); ?></div>
            <div class="stat-label">Flagged by Users</div>
        </div>
    </div>

    <div class="moderator-section">
        <h2>⏳ Pending Listings (<?php echo count($pending_listings); ?>)</h2>
        
        <?php if(count($pending_listings) > 0): ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>User</th>
                        <th>Category</th>
                        <th>City</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pending_listings as $listing): ?>
                    <tr>
                        <td><?php echo $listing['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars(substr($listing['title'], 0, 50)); ?></strong>
                            <?php if(strlen($listing['title']) > 50): ?>...<?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($listing['username']); ?></td>
                        <td><?php echo htmlspecialchars($listing['category_name']); ?></td>
                        <td><?php echo htmlspecialchars($listing['city_name']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($listing['created_at'])); ?></td>
                        <td>
                            <a href="../listing.php?id=<?php echo $listing['id']; ?>" 
                               class="action-btn view" target="_blank">View</a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                <button type="submit" name="approve_listing" class="action-btn approve">
                                    ✓ Approve
                                </button>
                            </form>
                            <button class="action-btn reject" onclick="showRejectModal(<?php echo $listing['id']; ?>)">
                                ✗ Reject
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 3rem; background: rgba(66, 103, 245, 0.05); border-radius: 10px;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">✓</div>
            <h3>No Pending Listings</h3>
            <p style="color: var(--text-gray);">All listings have been reviewed</p>
        </div>
        <?php endif; ?>
    </div>

    <?php if(count($flagged_listings) > 0): ?>
    <div class="moderator-section">
        <h2>🚩 Flagged Listings (<?php echo count($flagged_listings); ?>)</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>User</th>
                        <th>Reports</th>
                        <th>Last Report</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($flagged_listings as $listing): ?>
                    <tr>
                        <td><?php echo $listing['id']; ?></td>
                        <td><?php echo htmlspecialchars(substr($listing['title'], 0, 50)); ?></td>
                        <td><?php echo htmlspecialchars($listing['username']); ?></td>
                        <td>
                            <span style="color: var(--danger-red); font-weight: bold;">
                                <?php echo $listing['report_count']; ?> reports
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($listing['last_report'])); ?></td>
                        <td>
                            <a href="../listing.php?id=<?php echo $listing['id']; ?>" 
                               class="action-btn view" target="_blank">View</a>
                            <button class="action-btn reject" onclick="showDeleteModal(<?php echo $listing['id']; ?>)">
                                Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="moderator-section">
        <h2>Recent Actions</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Listing ID</th>
                        <th>Title</th>
                        <th>Action</th>
                        <th>Moderator</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_actions as $action): ?>
                    <tr>
                        <td><?php echo $action['listing_id']; ?></td>
                        <td><?php echo htmlspecialchars(substr($action['title'], 0, 40)); ?></td>
                        <td>
                            <span style="color: <?php echo $action['action'] == 'approved' ? 'var(--success-green)' : 'var(--danger-red)'; ?>">
                                <?php echo ucfirst($action['action']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($action['moderator_name']); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($action['action_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center;">
    <div class="card" style="max-width: 500px; width: 90%;">
        <h3 style="margin-bottom: 1rem;">Reject Listing</h3>
        <form method="POST">
            <input type="hidden" name="listing_id" id="rejectListingId">
            <div class="form-group">
                <label>Rejection Reason</label>
                <textarea name="rejection_reason" rows="4" required placeholder="e.g., Violates community guidelines, inappropriate content..."></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <button type="button" class="btn-secondary btn-block" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" name="reject_listing" class="btn-danger btn-block">Reject</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center;">
    <div class="card" style="max-width: 500px; width: 90%;">
        <h3 style="margin-bottom: 1rem; color: var(--danger-red);">⚠️ Delete Listing</h3>
        <p style="color: var(--text-gray); margin-bottom: 1.5rem;">
            This will permanently delete the listing. This action cannot be undone!
        </p>
        <form method="POST">
            <input type="hidden" name="listing_id" id="deleteListingId">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <button type="button" class="btn-secondary btn-block" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" name="delete_listing" class="btn-danger btn-block">Delete Permanently</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectModal(listingId) {
    document.getElementById('rejectListingId').value = listingId;
    document.getElementById('rejectModal').style.display = 'flex';
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

function showDeleteModal(listingId) {
    document.getElementById('deleteListingId').value = listingId;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

document.getElementById('rejectModal')?.addEventListener('click', function(e) {
    if(e.target === this) closeRejectModal();
});

document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if(e.target === this) closeDeleteModal();
});
</script>

<?php include '../views/footer.php'; ?>