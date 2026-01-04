<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get user's latest verification status
$query = "SELECT v.*, u.username, u.email 
          FROM user_verifications v 
          LEFT JOIN users u ON v.user_id = u.id 
          WHERE v.user_id = :user_id 
          ORDER BY v.submitted_at DESC 
          LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$verification = $stmt->fetch(PDO::FETCH_ASSOC);

// If no verification found, redirect to submit
if (!$verification) {
    header('Location: verify-identity.php');
    exit;
}

// If already approved, redirect to marketplace
if ($verification['status'] == 'approved') {
    header('Location: marketplace.php');
    exit;
}

include 'views/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root {
    --color-canvas-default: #0d1117;
    --color-canvas-subtle: #161b22;
    --color-canvas-overlay: #1c2128;
    --color-border-default: #30363d;
    --color-border-muted: #21262d;
    --color-text-primary: #e6edf3;
    --color-text-secondary: #7d8590;
    --color-text-tertiary: #636e7b;
    --color-accent-emphasis: #1f6feb;
    --color-success-emphasis: #238636;
    --color-warning-emphasis: #9e6a03;
    --color-danger-emphasis: #da3633;
    --color-done-emphasis: #8957e5;
}

body {
    background: var(--color-canvas-default);
    color: var(--color-text-primary);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    min-height: 100vh;
}

.pending-container {
    max-width: 700px;
    margin: 4rem auto;
    padding: 0 1.5rem;
}

.pending-card {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: 0 8px 24px rgba(1, 4, 9, 0.85);
}

.status-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 2rem;
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 50px;
    animation: pulse 2s ease-in-out infinite;
}

.status-icon.rejected {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.9; }
}

.pending-card h1 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--color-text-primary);
    margin-bottom: 1rem;
}

.pending-card p {
    color: var(--color-text-secondary);
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 2rem;
}

.info-box {
    background: var(--color-canvas-overlay);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 1.5rem;
    margin: 2rem 0;
    text-align: left;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--color-border-muted);
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    color: var(--color-text-secondary);
    font-weight: 600;
}

.info-value {
    color: var(--color-text-primary);
    font-weight: 600;
}

.status-badge {
    display: inline-block;
    padding: 0.5rem 1.25rem;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.875rem;
}

.status-badge.pending {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.status-badge.rejected {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.timeline {
    background: var(--color-canvas-overlay);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 1.5rem;
    margin: 2rem 0;
    text-align: left;
}

.timeline-item {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-icon {
    width: 40px;
    height: 40px;
    background: var(--color-accent-emphasis);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.timeline-icon.completed {
    background: var(--color-success-emphasis);
}

.timeline-icon.current {
    background: #f59e0b;
    animation: pulse 2s ease-in-out infinite;
}

.timeline-content h4 {
    color: var(--color-text-primary);
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.timeline-content p {
    color: var(--color-text-secondary);
    font-size: 0.875rem;
    margin: 0;
}

.rejection-box {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid var(--color-danger-emphasis);
    border-radius: 12px;
    padding: 1.5rem;
    margin: 2rem 0;
    text-align: left;
}

.rejection-box h3 {
    color: var(--color-danger-emphasis);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.rejection-box p {
    color: var(--color-text-primary);
    margin: 0;
}

.btn {
    padding: 1rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    border: none;
    font-size: 1rem;
}

.btn-primary {
    background: linear-gradient(135deg, #8957e5, #1f6feb);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(137, 87, 229, 0.4);
}

.btn-secondary {
    background: var(--color-canvas-overlay);
    color: var(--color-text-primary);
    border: 1px solid var(--color-border-default);
}

.btn-secondary:hover {
    border-color: var(--color-accent-emphasis);
}

.btn-group {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
}

@media (max-width: 768px) {
    .pending-card {
        padding: 2rem 1.5rem;
    }

    .btn-group {
        flex-direction: column;
    }

    .info-item {
        flex-direction: column;
        gap: 0.25rem;
    }
}
</style>

<div class="pending-container">
    <div class="pending-card">
        <?php if ($verification['status'] == 'pending'): ?>
            <div class="status-icon">
                <i class="bi bi-clock-history"></i>
            </div>

            <h1>Verification Under Review</h1>
            <p>Thank you for submitting your verification! Our team is currently reviewing your documents.</p>

            <span class="status-badge pending">Pending Review</span>

            <div class="info-box">
                <div class="info-item">
                    <span class="info-label">Submitted</span>
                    <span class="info-value"><?php echo date('M j, Y g:i A', strtotime($verification['submitted_at'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Verification Type</span>
                    <span class="info-value"><?php echo ucfirst($verification['verification_type']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($verification['full_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Document Type</span>
                    <span class="info-value"><?php echo ucwords(str_replace('_', ' ', $verification['document_type'])); ?></span>
                </div>
            </div>

            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-icon completed">
                        <i class="bi bi-check"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>Documents Submitted</h4>
                        <p>Your ID and selfie have been uploaded successfully</p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-icon current">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>Under Review</h4>
                        <p>Our verification team is reviewing your documents</p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>Verification Complete</h4>
                        <p>You'll receive an email once verified</p>
                    </div>
                </div>
            </div>

            <div style="background: var(--color-canvas-overlay); border: 1px solid var(--color-border-default); border-radius: 12px; padding: 1.5rem; margin-top: 2rem;">
                <i class="bi bi-info-circle" style="color: var(--color-accent-emphasis); font-size: 1.5rem;"></i>
                <h3 style="color: var(--color-text-primary); margin: 1rem 0 0.5rem;">What Happens Next?</h3>
                <p style="color: var(--color-text-secondary); margin: 0; text-align: left;">
                    • Our team will verify your documents within <strong>24-48 hours</strong><br>
                    • You'll receive an email notification when the review is complete<br>
                    • Once approved, you'll have full access to the platform<br>
                    • If we need additional information, we'll contact you via email
                </p>
            </div>

        <?php elseif ($verification['status'] == 'rejected'): ?>
            <div class="status-icon rejected">
                <i class="bi bi-x-circle"></i>
            </div>

            <h1>Verification Rejected</h1>
            <p>Unfortunately, we were unable to verify your identity with the provided documents.</p>

            <span class="status-badge rejected">Rejected</span>

            <?php if ($verification['rejection_reason']): ?>
            <div class="rejection-box">
                <h3>
                    <i class="bi bi-exclamation-triangle"></i>
                    Reason for Rejection
                </h3>
                <p><?php echo htmlspecialchars($verification['rejection_reason']); ?></p>
            </div>
            <?php endif; ?>

            <div style="background: var(--color-canvas-overlay); border: 1px solid var(--color-border-default); border-radius: 12px; padding: 1.5rem; margin: 2rem 0; text-align: left;">
                <h3 style="color: var(--color-text-primary); margin: 0 0 1rem;">Common Issues:</h3>
                <ul style="color: var(--color-text-secondary); margin: 0; padding-left: 1.5rem;">
                    <li>Document photo is blurry or unclear</li>
                    <li>Document is expired</li>
                    <li>Selfie doesn't clearly show face and ID</li>
                    <li>Name on document doesn't match provided information</li>
                    <li>Document appears to be altered or fake</li>
                </ul>
            </div>

            <div class="btn-group">
                <a href="verify-identity.php" class="btn btn-primary">
                    <i class="bi bi-arrow-repeat"></i>
                    Resubmit Verification
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-house"></i>
                    Back to Home
                </a>
            </div>

        <?php endif; ?>

        <div class="btn-group">
            <a href="marketplace.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i>
                Back to Marketplace
            </a>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
