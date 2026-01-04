<?php
session_start();
require_once 'config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $verificationId = $_POST['verification_id'] ?? 0;
    $action = $_POST['action'];
    $notes = trim($_POST['admin_notes'] ?? '');
    $rejectionReason = trim($_POST['rejection_reason'] ?? '');
    
    if ($action == 'approve') {
        // Approve verification
        $updateQuery = "UPDATE user_verifications 
                        SET status = 'approved', 
                            reviewed_at = NOW(), 
                            reviewed_by = :admin_id,
                            admin_notes = :notes
                        WHERE id = :vid";
        $stmt = $db->prepare($updateQuery);
        $stmt->bindParam(':admin_id', $_SESSION['user_id']);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':vid', $verificationId);
        
        if ($stmt->execute()) {
            // Update user table
            $getUserQuery = "SELECT user_id, verification_type FROM user_verifications WHERE id = :vid";
            $getUserStmt = $db->prepare($getUserQuery);
            $getUserStmt->bindParam(':vid', $verificationId);
            $getUserStmt->execute();
            $verification = $getUserStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($verification) {
                $updateUserQuery = "UPDATE users 
                                    SET id_verified = 1, 
                                        age_verified = 1,
                                        id_verified_at = NOW(),
                                        verification_level = 'id_verified'
                                    WHERE id = :uid";
                $updateUserStmt = $db->prepare($updateUserQuery);
                $updateUserStmt->bindParam(':uid', $verification['user_id']);
                $updateUserStmt->execute();
                
                // If creator verification, update creator status
                if ($verification['verification_type'] == 'creator') {
                    $creatorQuery = "UPDATE users SET creator = 1 WHERE id = :uid";
                    $creatorStmt = $db->prepare($creatorQuery);
                    $creatorStmt->bindParam(':uid', $verification['user_id']);
                    $creatorStmt->execute();
                }
            }
            
            // Audit log
            $auditQuery = "INSERT INTO verification_audit_log 
                (verification_id, user_id, admin_id, action, old_status, new_status, notes, ip_address) 
                VALUES (:vid, :uid, :aid, 'approved', 'pending', 'approved', :notes, :ip)";
            $auditStmt = $db->prepare($auditQuery);
            $auditStmt->bindParam(':vid', $verificationId);
            $auditStmt->bindParam(':uid', $verification['user_id']);
            $auditStmt->bindParam(':aid', $_SESSION['user_id']);
            $auditStmt->bindParam(':notes', $notes);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $auditStmt->bindParam(':ip', $ip);
            $auditStmt->execute();
            
            $_SESSION['success_message'] = 'Verification approved successfully';
        }
    } elseif ($action == 'reject') {
        // Reject verification
        $updateQuery = "UPDATE user_verifications 
                        SET status = 'rejected', 
                            reviewed_at = NOW(), 
                            reviewed_by = :admin_id,
                            rejection_reason = :reason,
                            admin_notes = :notes
                        WHERE id = :vid";
        $stmt = $db->prepare($updateQuery);
        $stmt->bindParam(':admin_id', $_SESSION['user_id']);
        $stmt->bindParam(':reason', $rejectionReason);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':vid', $verificationId);
        $stmt->execute();
        
        // Audit log
        $getUserQuery = "SELECT user_id FROM user_verifications WHERE id = :vid";
        $getUserStmt = $db->prepare($getUserQuery);
        $getUserStmt->bindParam(':vid', $verificationId);
        $getUserStmt->execute();
        $userId = $getUserStmt->fetchColumn();
        
        $auditQuery = "INSERT INTO verification_audit_log 
            (verification_id, user_id, admin_id, action, old_status, new_status, notes, ip_address) 
            VALUES (:vid, :uid, :aid, 'rejected', 'pending', 'rejected', :reason, :ip)";
        $auditStmt = $db->prepare($auditQuery);
        $auditStmt->bindParam(':vid', $verificationId);
        $auditStmt->bindParam(':uid', $userId);
        $auditStmt->bindParam(':aid', $_SESSION['user_id']);
        $auditStmt->bindParam(':reason', $rejectionReason);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $auditStmt->bindParam(':ip', $ip);
        $auditStmt->execute();
        
        $_SESSION['error_message'] = 'Verification rejected';
    }
    
    header('Location: admin-verify-review.php');
    exit;
}

// Get pending verifications
$query = "SELECT v.*, u.username, u.email 
          FROM user_verifications v 
          LEFT JOIN users u ON v.user_id = u.id 
          WHERE v.status = 'pending' 
          ORDER BY v.submitted_at ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$pendingVerifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM user_verifications";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

include 'views/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
/* Similar styling as before - keeping it consistent with your existing design */
:root {
    --color-canvas-default: #0d1117;
    --color-canvas-subtle: #161b22;
    --color-border-default: #30363d;
    --color-text-primary: #e6edf3;
    --color-text-secondary: #7d8590;
    --color-accent-emphasis: #1f6feb;
    --color-success-emphasis: #238636;
    --color-danger-emphasis: #da3633;
}

body {
    background: var(--color-canvas-default);
    color: var(--color-text-primary);
}

.admin-container {
    max-width: 1400px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 1.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--color-text-primary);
}

.stat-label {
    color: var(--color-text-secondary);
    font-size: 0.875rem;
}

.verification-card {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.verification-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
}

.verification-images {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin: 1.5rem 0;
}

.verification-image {
    border: 1px solid var(--color-border-default);
    border-radius: 8px;
    overflow: hidden;
}

.verification-image img {
    width: 100%;
    height: auto;
    display: block;
    cursor: pointer;
}

.verification-image label {
    display: block;
    padding: 0.5rem;
    background: var(--color-canvas-default);
    color: var(--color-text-secondary);
    font-size: 0.75rem;
    text-align: center;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.btn-approve {
    background: var(--color-success-emphasis);
    color: white;
}

.btn-reject {
    background: var(--color-danger-emphasis);
    color: white;
}

.form-textarea {
    width: 100%;
    padding: 0.75rem;
    background: var(--color-canvas-default);
    border: 1px solid var(--color-border-default);
    border-radius: 8px;
    color: var(--color-text-primary);
    font-family: inherit;
    min-height: 80px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}
</style>

<div class="admin-container">
    <h1><i class="bi bi-shield-check"></i> ID Verification Review</h1>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['approved']; ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['rejected']; ?></div>
            <div class="stat-label">Rejected</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total Submissions</div>
        </div>
    </div>

    <?php foreach ($pendingVerifications as $verification): ?>
    <div class="verification-card">
        <div class="verification-header">
            <div>
                <h3><?php echo htmlspecialchars($verification['full_name']); ?></h3>
                <p style="color: var(--color-text-secondary); margin: 0.25rem 0;">
                    Username: <?php echo htmlspecialchars($verification['username']); ?> | 
                    Email: <?php echo htmlspecialchars($verification['email']); ?>
                </p>
                <p style="color: var(--color-text-secondary); margin: 0.25rem 0; font-size: 0.875rem;">
                    Type: <strong><?php echo ucfirst($verification['verification_type']); ?></strong> |
                    Submitted: <?php echo date('M j, Y g:i A', strtotime($verification['submitted_at'])); ?>
                </p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 1rem 0;">
            <div>
                <strong>DOB:</strong> <?php echo date('M j, Y', strtotime($verification['date_of_birth'])); ?>
                (Age: <?php 
                    $dob = new DateTime($verification['date_of_birth']);
                    $now = new DateTime();
                    echo $now->diff($dob)->y;
                ?>)
            </div>
            <div><strong>Country:</strong> <?php echo htmlspecialchars($verification['country']); ?></div>
            <div><strong>Document:</strong> <?php echo ucwords(str_replace('_', ' ', $verification['document_type'])); ?></div>
            <?php if ($verification['document_number']): ?>
            <div><strong>Doc #:</strong> <?php echo htmlspecialchars($verification['document_number']); ?></div>
            <?php endif; ?>
        </div>

        <div class="verification-images">
            <div class="verification-image">
                <img src="<?php echo htmlspecialchars($verification['document_front_path']); ?>" 
                     alt="Document Front" onclick="openModal(this.src)">
                <label>Document Front</label>
            </div>
            
            <?php if ($verification['document_back_path']): ?>
            <div class="verification-image">
                <img src="<?php echo htmlspecialchars($verification['document_back_path']); ?>" 
                     alt="Document Back" onclick="openModal(this.src)">
                <label>Document Back</label>
            </div>
            <?php endif; ?>
            
            <div class="verification-image">
                <img src="<?php echo htmlspecialchars($verification['selfie_path']); ?>" 
                     alt="Selfie" onclick="openModal(this.src)">
                <label>Selfie with ID</label>
            </div>
        </div>

        <form method="POST" style="margin-top: 1.5rem;">
            <input type="hidden" name="verification_id" value="<?php echo $verification['id']; ?>">
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Admin Notes (optional)</label>
                <textarea name="admin_notes" class="form-textarea" placeholder="Internal notes about this verification..."></textarea>
            </div>
            
            <div id="rejectReason_<?php echo $verification['id']; ?>" style="display: none; margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Rejection Reason (required)</label>
                <textarea name="rejection_reason" class="form-textarea" placeholder="Explain why this verification is being rejected..."></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button type="submit" name="action" value="approve" class="btn btn-approve">
                    <i class="bi bi-check-circle"></i> Approve
                </button>
                <button type="button" class="btn btn-reject" onclick="showRejectReason(<?php echo $verification['id']; ?>)">
                    <i class="bi bi-x-circle"></i> Reject
                </button>
            </div>
        </form>
    </div>
    <?php endforeach; ?>

    <?php if (empty($pendingVerifications)): ?>
    <div style="text-align: center; padding: 4rem; color: var(--color-text-secondary);">
        <i class="bi bi-inbox" style="font-size: 4rem; opacity: 0.3; display: block; margin-bottom: 1rem;"></i>
        <h3>No Pending Verifications</h3>
        <p>All verification requests have been processed</p>
    </div>
    <?php endif; ?>
</div>

<!-- Image Modal -->
<div id="imageModal" class="modal" onclick="closeModal()">
    <img id="modalImage" src="" alt="Verification Document">
</div>

<script>
function showRejectReason(verificationId) {
    const rejectDiv = document.getElementById('rejectReason_' + verificationId);
    rejectDiv.style.display = 'block';
    
    // Change reject button to submit
    const form = rejectDiv.closest('form');
    const rejectBtn = form.querySelector('.btn-reject');
    rejectBtn.type = 'submit';
    rejectBtn.name = 'action';
    rejectBtn.value = 'reject';
    rejectBtn.textContent = 'Confirm Rejection';
}

function openModal(src) {
    document.getElementById('imageModal').classList.add('active');
    document.getElementById('modalImage').src = src;
}

function closeModal() {
    document.getElementById('imageModal').classList.remove('active');
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php include 'views/footer.php'; ?>
