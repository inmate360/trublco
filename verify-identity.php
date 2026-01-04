<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Check existing verification
$checkQuery = "SELECT * FROM user_verifications WHERE user_id = :user_id ORDER BY submitted_at DESC LIMIT 1";
$stmt = $db->prepare($checkQuery);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$existingVerification = $stmt->fetch(PDO::FETCH_ASSOC);

// If already verified, redirect
if ($existingVerification && $existingVerification['status'] == 'approved') {
    header('Location: marketplace.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $dob = $_POST['date_of_birth'] ?? '';
        $country = $_POST['country'] ?? '';
        $document_type = $_POST['document_type'] ?? '';
        $document_number = trim($_POST['document_number'] ?? '');
        $document_expiry = $_POST['document_expiry'] ?? '';
        $verification_type = $_POST['verification_type'] ?? 'buyer';
        
        if (empty($full_name) || empty($dob) || empty($country) || empty($document_type)) {
            $error = 'Please fill in all required fields';
        } else {
            // Calculate age
            $birthDate = new DateTime($dob);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            
            if ($age < 18) {
                $error = 'You must be at least 18 years old to use this platform';
            } else {
                // Handle file uploads
                $uploadDir = __DIR__ . '/uploads/verifications/' . $_SESSION['user_id'] . '/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $documentFrontPath = null;
                $documentBackPath = null;
                $selfiePath = null;
                $uploadSuccess = true;
                
                // Upload document front
                if (isset($_FILES['document_front']) && $_FILES['document_front']['error'] == 0) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
                    if (in_array($_FILES['document_front']['type'], $allowedTypes) && $_FILES['document_front']['size'] <= 10485760) {
                        $ext = pathinfo($_FILES['document_front']['name'], PATHINFO_EXTENSION);
                        $filename = 'doc_front_' . uniqid() . '_' . time() . '.' . $ext;
                        $uploadPath = $uploadDir . $filename;
                        if (move_uploaded_file($_FILES['document_front']['tmp_name'], $uploadPath)) {
                            $documentFrontPath = 'uploads/verifications/' . $_SESSION['user_id'] . '/' . $filename;
                        } else {
                            $uploadSuccess = false;
                            $error = 'Failed to upload document front';
                        }
                    } else {
                        $error = 'Invalid document file (max 10MB, JPEG/PNG/PDF only)';
                        $uploadSuccess = false;
                    }
                } else {
                    $error = 'Document front is required';
                    $uploadSuccess = false;
                }
                
                // Upload document back (optional)
                if ($uploadSuccess && isset($_FILES['document_back']) && $_FILES['document_back']['error'] == 0) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
                    if (in_array($_FILES['document_back']['type'], $allowedTypes) && $_FILES['document_back']['size'] <= 10485760) {
                        $ext = pathinfo($_FILES['document_back']['name'], PATHINFO_EXTENSION);
                        $filename = 'doc_back_' . uniqid() . '_' . time() . '.' . $ext;
                        $uploadPath = $uploadDir . $filename;
                        if (move_uploaded_file($_FILES['document_back']['tmp_name'], $uploadPath)) {
                            $documentBackPath = 'uploads/verifications/' . $_SESSION['user_id'] . '/' . $filename;
                        }
                    }
                }
                
                // Upload selfie (required)
                if ($uploadSuccess && isset($_FILES['selfie']) && $_FILES['selfie']['error'] == 0) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                    if (in_array($_FILES['selfie']['type'], $allowedTypes) && $_FILES['selfie']['size'] <= 10485760) {
                        $ext = pathinfo($_FILES['selfie']['name'], PATHINFO_EXTENSION);
                        $filename = 'selfie_' . uniqid() . '_' . time() . '.' . $ext;
                        $uploadPath = $uploadDir . $filename;
                        if (move_uploaded_file($_FILES['selfie']['tmp_name'], $uploadPath)) {
                            $selfiePath = 'uploads/verifications/' . $_SESSION['user_id'] . '/' . $filename;
                        } else {
                            $uploadSuccess = false;
                            $error = 'Failed to upload selfie';
                        }
                    } else {
                        $error = 'Invalid selfie file (max 10MB, JPEG/PNG only)';
                        $uploadSuccess = false;
                    }
                } else if ($uploadSuccess) {
                    $error = 'Selfie with ID is required';
                    $uploadSuccess = false;
                }
                
                // Insert into database
                if ($uploadSuccess) {
                    $insertQuery = "INSERT INTO user_verifications 
                        (user_id, verification_type, full_name, date_of_birth, country, 
                         document_type, document_number, document_expiry, 
                         document_front_path, document_back_path, selfie_path, 
                         ip_address, user_agent, age_verified) 
                        VALUES 
                        (:user_id, :verification_type, :full_name, :dob, :country, 
                         :document_type, :document_number, :document_expiry, 
                         :doc_front, :doc_back, :selfie, 
                         :ip, :user_agent, :age_verified)";
                    
                    $stmt = $db->prepare($insertQuery);
                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $stmt->bindParam(':verification_type', $verification_type);
                    $stmt->bindParam(':full_name', $full_name);
                    $stmt->bindParam(':dob', $dob);
                    $stmt->bindParam(':country', $country);
                    $stmt->bindParam(':document_type', $document_type);
                    $stmt->bindParam(':document_number', $document_number);
                    $stmt->bindParam(':document_expiry', $document_expiry);
                    $stmt->bindParam(':doc_front', $documentFrontPath);
                    $stmt->bindParam(':doc_back', $documentBackPath);
                    $stmt->bindParam(':selfie', $selfiePath);
                    
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $ageVerified = 1;
                    
                    $stmt->bindParam(':ip', $ip);
                    $stmt->bindParam(':user_agent', $userAgent);
                    $stmt->bindParam(':age_verified', $ageVerified);
                    
                    if ($stmt->execute()) {
                        $verificationId = $db->lastInsertId();
                        
                        // Audit trail
                        $auditQuery = "INSERT INTO verification_audit_log 
                            (verification_id, user_id, action, new_status, ip_address) 
                            VALUES (:vid, :uid, 'submitted', 'pending', :ip)";
                        $auditStmt = $db->prepare($auditQuery);
                        $auditStmt->bindParam(':vid', $verificationId);
                        $auditStmt->bindParam(':uid', $_SESSION['user_id']);
                        $auditStmt->bindParam(':ip', $ip);
                        $auditStmt->execute();
                        
                        header('Location: verification-pending.php');
                        exit;
                    } else {
                        $error = 'Failed to submit verification. Please try again.';
                    }
                }
            }
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'views/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root {
    --color-canvas-default: #0d1117;
    --color-canvas-subtle: #161b22;
    --color-border-default: #30363d;
    --color-text-primary: #e6edf3;
    --color-text-secondary: #7d8590;
    --color-accent-emphasis: #1f6feb;
    --color-danger-emphasis: #da3633;
    --color-success-emphasis: #238636;
}

body {
    background: var(--color-canvas-default);
    color: var(--color-text-primary);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.verification-container {
    max-width: 800px;
    margin: 3rem auto;
    padding: 0 1.5rem;
}

.verification-card {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 8px 24px rgba(1, 4, 9, 0.85);
}

.verification-header {
    text-align: center;
    margin-bottom: 2rem;
}

.verification-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, #8957e5, #1f6feb);
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    margin-bottom: 1rem;
}

.verification-header h1 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--color-text-primary);
    margin-bottom: 0.5rem;
}

.verification-header p {
    color: var(--color-text-secondary);
    font-size: 1rem;
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-danger {
    background: rgba(218, 54, 51, 0.15);
    border: 1px solid var(--color-danger-emphasis);
    color: var(--color-danger-emphasis);
}

.alert-warning {
    background: rgba(158, 106, 3, 0.15);
    border: 1px solid #9e6a03;
    color: #9e6a03;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    color: var(--color-text-secondary);
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.form-label.required::after {
    content: '*';
    color: var(--color-danger-emphasis);
    margin-left: 0.25rem;
}

.form-input, .form-select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: var(--color-canvas-default);
    border: 1px solid var(--color-border-default);
    border-radius: 8px;
    color: var(--color-text-primary);
    font-size: 0.875rem;
    transition: all 0.2s;
}

.form-input:focus, .form-select:focus {
    outline: none;
    border-color: var(--color-accent-emphasis);
    box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.15);
}

.upload-zone {
    border: 2px dashed var(--color-border-default);
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s;
    cursor: pointer;
    background: var(--color-canvas-default);
}

.upload-zone:hover {
    border-color: var(--color-accent-emphasis);
    background: var(--color-canvas-subtle);
}

.upload-icon {
    font-size: 3rem;
    color: var(--color-text-secondary);
    margin-bottom: 1rem;
}

.upload-text {
    color: var(--color-text-primary);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.upload-subtext {
    color: var(--color-text-secondary);
    font-size: 0.875rem;
}

.file-input {
    display: none;
}

.btn-submit {
    width: 100%;
    padding: 1rem 2rem;
    background: linear-gradient(135deg, #8957e5, #1f6feb);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(137, 87, 229, 0.4);
}

.security-notice {
    background: var(--color-canvas-default);
    border: 1px solid var(--color-border-default);
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1.5rem;
    font-size: 0.875rem;
    color: var(--color-text-secondary);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.preview-container {
    margin-top: 1rem;
    display: none;
}

.preview-container.active {
    display: block;
}

.preview-item {
    background: var(--color-canvas-default);
    border: 1px solid var(--color-border-default);
    border-radius: 8px;
    padding: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.preview-thumb {
    width: 60px;
    height: 60px;
    border-radius: 6px;
    object-fit: cover;
}

.preview-info {
    flex: 1;
}

.preview-name {
    font-weight: 600;
    color: var(--color-text-primary);
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.preview-size {
    font-size: 0.75rem;
    color: var(--color-text-secondary);
}
</style>

<div class="verification-container">
    <div class="verification-card">
        <div class="verification-header">
            <div class="verification-icon">
                <i class="bi bi-shield-check"></i>
            </div>
            <h1>ID Verification</h1>
            <p>Verify your identity to access the platform</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($existingVerification && $existingVerification['status'] == 'pending'): ?>
            <div class="alert alert-warning">
                <i class="bi bi-clock-history"></i>
                Your verification is currently under review. This typically takes 24-48 hours.
            </div>
        <?php elseif ($existingVerification && $existingVerification['status'] == 'rejected'): ?>
            <div class="alert alert-danger">
                <i class="bi bi-x-circle-fill"></i>
                <div>
                    <strong>Verification Rejected:</strong><br>
                    <?php echo htmlspecialchars($existingVerification['rejection_reason'] ?? 'Please resubmit with valid documents'); ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="verificationForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <!-- Verification Type -->
            <div class="form-group">
                <label class="form-label required">Verification Type</label>
                <select name="verification_type" class="form-select" required>
                    <option value="buyer">Buyer (Age Verification)</option>
                    <option value="creator">Creator (Full ID Verification)</option>
                </select>
            </div>

            <!-- Personal Information -->
            <div class="form-group">
                <label class="form-label required">Full Name (as shown on ID)</label>
                <input type="text" name="full_name" class="form-input" placeholder="John Doe" required>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-input" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label required">Country</label>
                    <select name="country" class="form-select" required>
                        <option value="">Select Country</option>
                        <option value="US">United States</option>
                        <option value="GB">United Kingdom</option>
                        <option value="CA">Canada</option>
                        <option value="AU">Australia</option>
                        <option value="DE">Germany</option>
                        <option value="FR">France</option>
                        <option value="ES">Spain</option>
                        <option value="IT">Italy</option>
                        <!-- Add more countries as needed -->
                    </select>
                </div>
            </div>

            <!-- Document Information -->
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">Document Type</label>
                    <select name="document_type" class="form-select" required>
                        <option value="">Select Type</option>
                        <option value="passport">Passport</option>
                        <option value="drivers_license">Driver's License</option>
                        <option value="national_id">National ID Card</option>
                        <option value="state_id">State ID</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Document Number (optional)</label>
                    <input type="text" name="document_number" class="form-input" placeholder="Optional">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Document Expiry Date (optional)</label>
                <input type="date" name="document_expiry" class="form-input" min="<?php echo date('Y-m-d'); ?>">
            </div>

            <!-- File Uploads -->
            <div class="form-group">
                <label class="form-label required">Document Front/Photo Page</label>
                <div class="upload-zone" id="docFrontZone" onclick="document.getElementById('docFrontInput').click()">
                    <i class="bi bi-file-earmark-image upload-icon"></i>
                    <div class="upload-text">Click or drag to upload document front</div>
                    <div class="upload-subtext">JPEG, PNG, or PDF • Max 10MB</div>
                </div>
                <input type="file" id="docFrontInput" name="document_front" class="file-input" accept="image/jpeg,image/png,image/jpg,application/pdf" required>
                <div class="preview-container" id="docFrontPreview"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Document Back (if applicable)</label>
                <div class="upload-zone" id="docBackZone" onclick="document.getElementById('docBackInput').click()">
                    <i class="bi bi-file-earmark-image upload-icon"></i>
                    <div class="upload-text">Click or drag to upload document back</div>
                    <div class="upload-subtext">JPEG, PNG, or PDF • Max 10MB</div>
                </div>
                <input type="file" id="docBackInput" name="document_back" class="file-input" accept="image/jpeg,image/png,image/jpg,application/pdf">
                <div class="preview-container" id="docBackPreview"></div>
            </div>

            <div class="form-group">
                <label class="form-label required">Selfie Holding ID</label>
                <div class="upload-zone" id="selfieZone" onclick="document.getElementById('selfieInput').click()">
                    <i class="bi bi-camera upload-icon"></i>
                    <div class="upload-text">Click or drag to upload selfie</div>
                    <div class="upload-subtext">Take a photo holding your ID next to your face • JPEG, PNG • Max 10MB</div>
                </div>
                <input type="file" id="selfieInput" name="selfie" class="file-input" accept="image/jpeg,image/png,image/jpg" required>
                <div class="preview-container" id="selfiePreview"></div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="bi bi-check-circle"></i>
                Submit Verification
            </button>

            <div class="security-notice">
                <i class="bi bi-lock-fill"></i>
                <strong>Your privacy matters:</strong> All documents are encrypted and stored securely. We only use this information to verify your age and identity as required by law [web:13].
            </div>
        </form>
    </div>
</div>

<script>
// File preview functionality
function setupFilePreview(inputId, zoneId, previewId) {
    const input = document.getElementById(inputId);
    const zone = document.getElementById(zoneId);
    const preview = document.getElementById(previewId);
    
    // Drag and drop
    zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.style.borderColor = '#1f6feb';
    });
    
    zone.addEventListener('dragleave', () => {
        zone.style.borderColor = '#30363d';
    });
    
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.style.borderColor = '#30363d';
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            input.files = files;
            showPreview(files[0], preview);
        }
    });
    
    // File input change
    input.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            showPreview(e.target.files[0], preview);
        }
    });
}

function showPreview(file, previewContainer) {
    previewContainer.classList.add('active');
    const fileSize = (file.size / 1024 / 1024).toFixed(2);
    
    let previewHTML = `
        <div class="preview-item">
            <div class="preview-info">
                <div class="preview-name">${file.name}</div>
                <div class="preview-size">${fileSize} MB</div>
            </div>
        </div>
    `;
    
    // Show image preview if it's an image
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewHTML = `
                <div class="preview-item">
                    <img src="${e.target.result}" class="preview-thumb" alt="Preview">
                    <div class="preview-info">
                        <div class="preview-name">${file.name}</div>
                        <div class="preview-size">${fileSize} MB</div>
                    </div>
                </div>
            `;
            previewContainer.innerHTML = previewHTML;
        };
        reader.readAsDataURL(file);
    } else {
        previewContainer.innerHTML = previewHTML;
    }
}

// Initialize file previews
setupFilePreview('docFrontInput', 'docFrontZone', 'docFrontPreview');
setupFilePreview('docBackInput', 'docBackZone', 'docBackPreview');
setupFilePreview('selfieInput', 'selfieZone', 'selfiePreview');

// Form validation
document.getElementById('verificationForm').addEventListener('submit', function(e) {
    const dob = new Date(document.querySelector('[name="date_of_birth"]').value);
    const today = new Date();
    const age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age--;
    }
    
    if (age < 18) {
        e.preventDefault();
        alert('You must be at least 18 years old to use this platform');
        return false;
    }
});
</script>

<?php include 'views/footer.php'; ?>
