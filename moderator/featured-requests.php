<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Moderator.php';
require_once '../classes/FeaturedAd.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$moderator = new Moderator($db);
$featuredAd = new FeaturedAd($db);

$mod_data = $moderator->isModerator($_SESSION['user_id']);

if(!$mod_data) {
    $_SESSION['error'] = 'Access denied';
    header('Location: ../index.php');
    exit();
}

// Handle approval/rejection
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $notes = $_POST['notes'] ?? '';
    
    if($action == 'approve') {
        $result = $featuredAd->approveRequest($request_id, $mod_data['id'], $notes);
        if($result['success']) {
            $moderator->logAction($mod_data['id'], 'approve_report', 'featured_request', $request_id, $notes);
            $_SESSION['success'] = 'Featured request approved';
        }
    } elseif($action == 'reject') {
        $result = $featuredAd->rejectRequest($request_id, $mod_data['id'], $notes);
        if($result['success']) {
            $moderator->logAction($mod_data['id'], 'reject_report', 'featured_request', $request_id, $notes);
            $_SESSION['success'] = 'Featured request rejected';
        }
    }
    
    header('Location: featured-requests.php');
    exit();
}

$pending_requests = $featuredAd->getPendingRequests();

include '../views/header.php';
?>

<div class="mod-dashboard">
    <div class="container">
        <h2>🌟 Featured Ad Requests</h2>
        
        <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <a href="dashboard.php" class="btn-secondary">← Back to Dashboard</a>
        
        <?php if(count($pending_requests) > 0): ?>
        <div class="featured-requests-list">
            <?php foreach($pending_requests as $req): ?>
            <div class="featured-request-card">
                <div class="request-header">
                    <h3>Request #<?php echo $req['id']; ?></h3>
                    <span class="price-badge">$<?php echo number_format($req['price'], 2); ?></span>
                </div>
                
                <div class="request-content">
                    <div class="request-info">
                        <p><strong>User:</strong> <?php echo htmlspecialchars($req['username']); ?> (<?php echo htmlspecialchars($req['email']); ?>)</p>
                        <p><strong>Duration:</strong> <?php echo $req['duration_days']; ?> days</p>
                        <p><strong>Requested:</strong> <?php echo date('M j, Y g:i A', strtotime($req['requested_at'])); ?></p>
                    </div>
                    
                    <div class="listing-preview">
                        <h4>Listing Preview:</h4>
                        <p><strong><?php echo htmlspecialchars($req['title']); ?></strong></p>
                        <p><?php echo htmlspecialchars(substr($req['description'], 0, 200)); ?>...</p>
                        <a href="../listing.php?id=<?php echo $req['listing_id']; ?>" target="_blank" class="btn-secondary">View Full Listing</a>
                    </div>
                </div>
                
                <form method="POST" class="review-form">
                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                    <div class="form-group">
                        <label>Review Notes (optional):</label>
                        <textarea name="notes" rows="3" placeholder="Add any notes or reasons for your decision..."></textarea>
                    </div>
                    <div class="review-actions">
                        <button type="submit" name="action" value="approve" class="btn-success">✓ Approve</button>
                        <button type="submit" name="action" value="reject" class="btn-danger" 
                                onclick="return confirm('Are you sure you want to reject this request?')">✗ Reject</button>
                    </div>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-data">
            <p>No pending featured ad requests at this time.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.featured-requests-list {
    margin-top: 2rem;
}

.featured-request-card {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f0f0f0;
    margin-bottom: 1.5rem;
}

.price-badge {
    background: #3498db;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: bold;
    font-size: 1.1rem;
}

.request-content {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 2rem;
    margin-bottom: 1.5rem;
}

.request-info p {
    margin-bottom: 0.8rem;
}

.listing-preview {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
}

.listing-preview h4 {
    margin-bottom: 1rem;
}

.listing-preview p {
    margin-bottom: 1rem;
}

.review-form {
    border-top: 2px solid #f0f0f0;
    padding-top: 1.5rem;
}

.review-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.btn-success {
    background: #27ae60;
    color: white;
    padding: 0.8rem 2rem;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1rem;
    transition: background 0.3s;
}

.btn-success:hover {
    background: #229954;
}

@media (max-width: 768px) {
    .request-content {
        grid-template-columns: 1fr;
    }
    
    .review-actions {
        flex-direction: column;
    }
}
</style>

<?php include '../views/footer.php'; ?>