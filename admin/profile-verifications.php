<?php
session_start();
require_once 'config/database.php';
require_once 'includes/maintenance-check.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: index.php');
    exit;
}

// Handle approval/denial actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $verification_id = $_POST['verification_id'] ?? null;
    $action = $_POST['action'] ?? null;
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    if ($verification_id && in_array($action, ['approve', 'deny'])) {
        $status = ($action === 'approve') ? 'approved' : 'denied';
        $reviewed_by = $_SESSION['user_id'];
        
        $stmt = $pdo->prepare("UPDATE profile_verifications SET status = ?, reviewed_by = ?, reviewed_at = NOW(), admin_notes = ? WHERE id = ?");
        $stmt->execute([$status, $reviewed_by, $admin_notes, $verification_id]);
        
        // If approved, update user's verified status
        if ($status === 'approved') {
            $stmt = $pdo->prepare("SELECT user_id FROM profile_verifications WHERE id = ?");
            $stmt->execute([$verification_id]);
            $user_id = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
            $stmt->execute([$user_id]);
        }
        
        $_SESSION['admin_message'] = "Verification request " . ($action === 'approve' ? 'approved' : 'denied') . " successfully.";
        header('Location: admin-verifications.php');
        exit;
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'pending';
$allowed_filters = ['pending', 'approved', 'denied', 'all'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'pending';
}

// Build query based on filter
$query = "SELECT v.*, u.username, u.email, u.profile_photo 
          FROM profile_verifications v 
          JOIN users u ON v.user_id = u.id ";

if ($filter !== 'all') {
    $query .= "WHERE v.status = ? ";
}

$query .= "ORDER BY v.submitted_at DESC";

if ($filter !== 'all') {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$filter]);
} else {
    $stmt = $pdo->query($query);
}
$verifications = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'denied' THEN 1 ELSE 0 END) as denied
    FROM profile_verifications";
$stats = $pdo->query($stats_query)->fetch();

include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative bg-gradient-to-br from-purple-600 via-pink-500 to-red-500 text-white py-12">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center mb-2">
                    <i class="bi bi-shield-lock text-3xl mr-3"></i>
                    <h1 class="text-3xl md:text-4xl font-bold">Verification Management</h1>
                </div>
                <p class="text-white/90">Review and manage profile verification requests</p>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="max-w-7xl mx-auto px-4 py-8">
    
    <?php if (isset($_SESSION['admin_message'])): ?>
    <div class="bg-gh-success/10 border border-gh-success text-gh-success px-6 py-4 rounded-lg mb-6 flex items-center justify-between">
        <div class="flex items-center">
            <i class="bi bi-check-circle-fill text-xl mr-3"></i>
            <span><?php echo htmlspecialchars($_SESSION['admin_message']); ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-gh-success hover:text-gh-success/80">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <?php unset($_SESSION['admin_message']); endif; ?>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-gh-panel border border-gh-border rounded-lg p-6">
            <div class="flex items-center justify-between mb-2">
                <div class="text-gh-muted">Total Requests</div>
                <i class="bi bi-file-earmark-text text-2xl text-gh-muted"></i>
            </div>
            <div class="text-3xl font-bold text-gh-fg"><?php echo $stats['total']; ?></div>
        </div>
        
        <div class="bg-gh-panel border border-yellow-500/20 rounded-lg p-6">
            <div class="flex items-center justify-between mb-2">
                <div class="text-yellow-500">Pending Review</div>
                <i class="bi bi-clock-fill text-2xl text-yellow-500"></i>
            </div>
            <div class="text-3xl font-bold text-yellow-500"><?php echo $stats['pending']; ?></div>
        </div>
        
        <div class="bg-gh-panel border border-gh-success/20 rounded-lg p-6">
            <div class="flex items-center justify-between mb-2">
                <div class="text-gh-success">Approved</div>
                <i class="bi bi-check-circle-fill text-2xl text-gh-success"></i>
            </div>
            <div class="text-3xl font-bold text-gh-success"><?php echo $stats['approved']; ?></div>
        </div>
        
        <div class="bg-gh-panel border border-red-500/20 rounded-lg p-6">
            <div class="flex items-center justify-between mb-2">
                <div class="text-red-500">Denied</div>
                <i class="bi bi-x-circle-fill text-2xl text-red-500"></i>
            </div>
            <div class="text-3xl font-bold text-red-500"><?php echo $stats['denied']; ?></div>
        </div>
    </div>
    
    <!-- Filter Tabs -->
    <div class="bg-gh-panel border border-gh-border rounded-lg mb-6">
        <div class="flex flex-wrap border-b border-gh-border">
            <a href="?filter=pending" class="px-6 py-4 font-medium <?php echo $filter === 'pending' ? 'text-gh-accent border-b-2 border-gh-accent' : 'text-gh-muted hover:text-gh-fg'; ?>">
                <i class="bi bi-clock mr-2"></i>Pending (<?php echo $stats['pending']; ?>)
            </a>
            <a href="?filter=approved" class="px-6 py-4 font-medium <?php echo $filter === 'approved' ? 'text-gh-accent border-b-2 border-gh-accent' : 'text-gh-muted hover:text-gh-fg'; ?>">
                <i class="bi bi-check-circle mr-2"></i>Approved (<?php echo $stats['approved']; ?>)
            </a>
            <a href="?filter=denied" class="px-6 py-4 font-medium <?php echo $filter === 'denied' ? 'text-gh-accent border-b-2 border-gh-accent' : 'text-gh-muted hover:text-gh-fg'; ?>">
                <i class="bi bi-x-circle mr-2"></i>Denied (<?php echo $stats['denied']; ?>)
            </a>
            <a href="?filter=all" class="px-6 py-4 font-medium <?php echo $filter === 'all' ? 'text-gh-accent border-b-2 border-gh-accent' : 'text-gh-muted hover:text-gh-fg'; ?>">
                <i class="bi bi-list mr-2"></i>All (<?php echo $stats['total']; ?>)
            </a>
        </div>
    </div>
    
    <!-- Verification Requests -->
    <div class="space-y-6">
        <?php if (empty($verifications)): ?>
        <div class="bg-gh-panel border border-gh-border rounded-lg p-12 text-center">
            <i class="bi bi-inbox text-6xl text-gh-muted mb-4 block"></i>
            <div class="text-xl font-medium text-gh-fg mb-2">No Verification Requests</div>
            <div class="text-gh-muted">There are no <?php echo $filter !== 'all' ? $filter : ''; ?> verification requests at this time.</div>
        </div>
        <?php else: ?>
            <?php foreach ($verifications as $verification): ?>
            <div class="bg-gh-panel border border-gh-border rounded-lg overflow-hidden">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <!-- User Info -->
                        <div class="flex items-center">
                            <img src="<?php echo htmlspecialchars($verification['profile_photo'] ?? 'assets/default-avatar.png'); ?>" 
                                 alt="Profile" class="w-16 h-16 rounded-full object-cover mr-4">
                            <div>
                                <div class="flex items-center mb-1">
                                    <a href="profile.php?id=<?php echo $verification['user_id']; ?>" 
                                       class="text-lg font-bold text-gh-fg hover:text-gh-accent">
                                        <?php echo htmlspecialchars($verification['username']); ?>
                                    </a>
                                </div>
                                <div class="text-gh-muted text-sm"><?php echo htmlspecialchars($verification['email']); ?></div>
                                <div class="text-gh-muted text-sm mt-1">
                                    <i class="bi bi-calendar mr-1"></i>
                                    Submitted: <?php echo date('M j, Y g:i A', strtotime($verification['submitted_at'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Badge -->
                        <div>
                            <?php if ($verification['status'] === 'approved'): ?>
                                <span class="inline-flex items-center px-4 py-2 bg-gh-success/10 text-gh-success rounded-full font-medium">
                                    <i class="bi bi-check-circle-fill mr-2"></i> Approved
                                </span>
                            <?php elseif ($verification['status'] === 'pending'): ?>
                                <span class="inline-flex items-center px-4 py-2 bg-yellow-500/10 text-yellow-500 rounded-full font-medium">
                                    <i class="bi bi-clock-fill mr-2"></i> Pending
                                </span>
                            <?php elseif ($verification['status'] === 'denied'): ?>
                                <span class="inline-flex items-center px-4 py-2 bg-red-500/10 text-red-500 rounded-full font-medium">
                                    <i class="bi bi-x-circle-fill mr-2"></i> Denied
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Video Preview -->
                    <div class="mb-4">
                        <video controls class="w-full max-w-2xl rounded-lg border border-gh-border bg-black">
                            <source src="<?php echo htmlspecialchars($verification['video_path']); ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                    
                    <!-- Review Info (if reviewed) -->
                    <?php if ($verification['reviewed_at']): ?>
                    <div class="bg-gh-bg border border-gh-border rounded-lg p-4 mb-4">
                        <div class="text-sm text-gh-muted mb-2">
                            Reviewed: <?php echo date('M j, Y g:i A', strtotime($verification['reviewed_at'])); ?>
                        </div>
                        <?php if ($verification['admin_notes']): ?>
                        <div class="text-gh-fg">
                            <strong>Admin Notes:</strong> <?php echo htmlspecialchars($verification['admin_notes']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons (if pending) -->
                    <?php if ($verification['status'] === 'pending'): ?>
                    <div class="flex gap-4">
                        <button onclick="showReviewModal(<?php echo $verification['id']; ?>, 'approve')" 
                                class="flex-1 bg-gh-success hover:bg-gh-success/80 text-white font-semibold py-3 px-6 rounded-lg transition-all">
                            <i class="bi bi-check-circle mr-2"></i> Approve Verification
                        </button>
                        <button onclick="showReviewModal(<?php echo $verification['id']; ?>, 'deny')" 
                                class="flex-1 bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-6 rounded-lg transition-all">
                            <i class="bi bi-x-circle mr-2"></i> Deny Verification
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-gh-panel border border-gh-border rounded-lg max-w-lg w-full p-6">
        <h3 id="modalTitle" class="text-xl font-bold text-gh-fg mb-4"></h3>
        <form method="POST" id="reviewForm">
            <input type="hidden" name="verification_id" id="verificationId">
            <input type="hidden" name="action" id="actionType">
            
            <div class="mb-6">
                <label class="block text-gh-fg font-medium mb-2">Admin Notes (Optional)</label>
                <textarea name="admin_notes" rows="4" 
                          class="w-full px-4 py-3 bg-gh-bg border border-gh-border rounded-lg text-gh-fg focus:outline-none focus:border-gh-accent"
                          placeholder="Add any notes about this decision..."></textarea>
            </div>
            
            <div class="flex gap-4">
                <button type="button" onclick="closeReviewModal()" 
                        class="flex-1 bg-gh-bg border border-gh-border text-gh-fg hover:bg-gh-border font-semibold py-3 px-6 rounded-lg transition-all">
                    Cancel
                </button>
                <button type="submit" id="submitBtn"
                        class="flex-1 font-semibold py-3 px-6 rounded-lg transition-all">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showReviewModal(verificationId, action) {
    const modal = document.getElementById('reviewModal');
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    
    document.getElementById('verificationId').value = verificationId;
    document.getElementById('actionType').value = action;
    
    if (action === 'approve') {
        modalTitle.textContent = 'Approve Verification';
        submitBtn.textContent = 'Approve';
        submitBtn.className = 'flex-1 bg-gh-success hover:bg-gh-success/80 text-white font-semibold py-3 px-6 rounded-lg transition-all';
    } else {
        modalTitle.textContent = 'Deny Verification';
        submitBtn.textContent = 'Deny';
        submitBtn.className = 'flex-1 bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-6 rounded-lg transition-all';
    }
    
    modal.classList.remove('hidden');
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.add('hidden');
    document.getElementById('reviewForm').reset();
}

// Close modal when clicking outside
document.getElementById('reviewModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReviewModal();
    }
});
</script>

<?php include 'views/footer.php'; ?>
