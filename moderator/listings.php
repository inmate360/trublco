<?php
session_start();
require_once '../config/database.php';

// Check if user is moderator
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check moderator status
$query = "SELECT is_moderator, is_admin FROM users WHERE id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

if(!$user || (!$user['is_moderator'] && !$user['is_admin'])) {
    header('Location: ../index.php');
    exit();
}

$success = '';
$error = '';

// Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['approve_listing'])) {
        $listing_id = $_POST['listing_id'];
        
        $query = "UPDATE listings SET status = 'active' WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $listing_id);
        
        if($stmt->execute()) {
            // Log action
            $log_query = "INSERT INTO moderator_logs (moderator_id, action_type, target_type, target_id) 
                          VALUES (:mod_id, 'listing_approve', 'listing', :target_id)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':mod_id', $_SESSION['user_id']);
            $log_stmt->bindParam(':target_id', $listing_id);
            $log_stmt->execute();
            
            $success = 'Listing approved!';
        }
    }
    
    if(isset($_POST['reject_listing'])) {
        $listing_id = $_POST['listing_id'];
        $reason = $_POST['reason'];
        
        $query = "UPDATE listings SET status = 'rejected' WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $listing_id);
        
        if($stmt->execute()) {
            // Log action
            $log_query = "INSERT INTO moderator_logs (moderator_id, action_type, target_type, target_id, reason) 
                          VALUES (:mod_id, 'listing_reject', 'listing', :target_id, :reason)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':mod_id', $_SESSION['user_id']);
            $log_stmt->bindParam(':target_id', $listing_id);
            $log_stmt->bindParam(':reason', $reason);
            $log_stmt->execute();
            
            $success = 'Listing rejected!';
        }
    }
    
    if(isset($_POST['delete_listing'])) {
        $listing_id = $_POST['listing_id'];
        $reason = $_POST['reason'];
        
        $query = "DELETE FROM listings WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $listing_id);
        
        if($stmt->execute()) {
            // Log action
            $log_query = "INSERT INTO moderator_logs (moderator_id, action_type, target_type, target_id, reason) 
                          VALUES (:mod_id, 'listing_delete', 'listing', :target_id, :reason)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':mod_id', $_SESSION['user_id']);
            $log_stmt->bindParam(':target_id', $listing_id);
            $log_stmt->bindParam(':reason', $reason);
            $log_stmt->execute();
            
            $success = 'Listing deleted!';
        }
    }
}

// Get listings
$status_filter = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$where_conditions[] = "l.status = :status";

if($search) {
    $where_conditions[] = "(l.title LIKE :search OR l.description LIKE :search OR u.username LIKE :search)";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$query = "SELECT l.*, u.username, c.name as category_name, ct.name as city_name, s.abbreviation as state_abbr
          FROM listings l
          LEFT JOIN users u ON l.user_id = u.id
          LEFT JOIN categories c ON l.category_id = c.id
          LEFT JOIN cities ct ON l.city_id = ct.id
          LEFT JOIN states s ON ct.state_id = s.id
          $where_clause
          ORDER BY l.created_at DESC
          LIMIT 50";

$stmt = $db->prepare($query);
$stmt->bindParam(':status', $status_filter);
if($search) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
}
$stmt->execute();
$listings = $stmt->fetchAll();

// Get status counts
$counts = [];
foreach(['pending', 'active', 'rejected', 'expired'] as $status) {
    $query = "SELECT COUNT(*) as count FROM listings WHERE status = :status";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->execute();
    $counts[$status] = $stmt->fetch()['count'];
}

include '../views/header.php';
?>

<link rel="stylesheet" href="../assets/css/dark-blue-theme.css">

<div class="mod-container">
    <div class="mod-header">
        <h1>📝 Listing Management</h1>
        <p style="color: var(--text-gray);">Review and moderate classified ads</p>
    </div>

    <div class="mod-nav">
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="users.php">👥 Users</a>
        <a href="listings.php" class="active">📝 Listings</a>
        <a href="reports.php">🚨 Reports</a>
    </div>

    <?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="mod-section">
        <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
            <a href="?status=pending" class="btn-secondary btn-small <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                Pending (<?php echo $counts['pending']; ?>)
            </a>
            <a href="?status=active" class="btn-secondary btn-small <?php echo $status_filter == 'active' ? 'active' : ''; ?>">
                Active (<?php echo $counts['active']; ?>)
            </a>
            <a href="?status=rejected" class="btn-secondary btn-small <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">
                Rejected (<?php echo $counts['rejected']; ?>)
            </a>
            <a href="?status=expired" class="btn-secondary btn-small <?php echo $status_filter == 'expired' ? 'active' : ''; ?>">
                Expired (<?php echo $counts['expired']; ?>)
            </a>
        </div>
        
        <form method="GET" style="display: flex; gap: 1rem; margin-bottom: 1.5rem;">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            <input type="text" name="search" placeholder="Search title, description, or username..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1;">
            <button type="submit" class="btn-primary">Search</button>
            <?php if($search): ?>
            <a href="listings.php?status=<?php echo $status_filter; ?>" class="btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
        
        <?php if(count($listings) > 0): ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>User</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Posted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($listings as $listing): ?>
                    <tr>
                        <td><?php echo $listing['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars(substr($listing['title'], 0, 40)); ?></strong>
                            <?php if(strlen($listing['title']) > 40): ?>...<?php endif; ?>
                        </td>
                        <td>
                            <a href="../profile.php?id=<?php echo $listing['user_id']; ?>" style="color: var(--primary-blue);" target="_blank">
                                <?php echo htmlspecialchars($listing['username']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($listing['category_name']); ?></td>
                        <td><?php echo htmlspecialchars($listing['city_name']); ?>, <?php echo $listing['state_abbr']; ?></td>
                        <td>
                            <span style="color: <?php 
                                echo $listing['status'] == 'active' ? 'var(--success-green)' : 
                                    ($listing['status'] == 'pending' ? 'var(--warning-orange)' : 'var(--danger-red)'); 
                            ?>">
                                <?php echo ucfirst($listing['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($listing['created_at'])); ?></td>
                        <td>
                            <a href="../listing.php?id=<?php echo $listing['id']; ?>" class="action-btn view" target="_blank">View</a>
                            
                            <?php if($listing['status'] == 'pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                <button type="submit" name="approve_listing" class="action-btn edit">
                                    Approve
                                </button>
                            </form>
                            <button class="action-btn delete" onclick="showRejectModal(<?php echo $listing['id']; ?>)">
                                Reject
                            </button>
                            <?php else: ?>
                            <button class="action-btn delete" onclick="showDeleteModal(<?php echo $listing['id']; ?>)">
                                Delete
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 3rem; background: rgba(66, 103, 245, 0.05); border-radius: 10px;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📝</div>
            <h3>No Listings Found</h3>
            <p style="color: var(--text-gray);">No listings match your current filter</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center;">
    <div class="card" style="max-width: 500px; width: 90%;">
        <h3 style="margin-bottom: 1rem;">Reject Listing</h3>
        <form method="POST">
            <input type="hidden" name="listing_id" id="rejectListingId">
            <div class="form-group">
                <label>Reason for Rejection</label>
                <textarea name="reason" rows="4" required placeholder="Explain why this listing is being rejected..."></textarea>
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
        <h3 style="margin-bottom: 1rem;">Delete Listing</h3>
        <div class="alert alert-warning" style="margin-bottom: 1rem;">
            <strong>⚠️ Warning:</strong> This action cannot be undone!
        </div>
        <form method="POST">
            <input type="hidden" name="listing_id" id="deleteListingId">
            <div class="form-group">
                <label>Reason for Deletion</label>
                <textarea name="reason" rows="4" required placeholder="Explain why this listing is being deleted..."></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <button type="button" class="btn-secondary btn-block" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" name="delete_listing" class="btn-danger btn-block">Delete</button>
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