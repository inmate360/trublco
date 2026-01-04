<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if user is a creator
$query = "SELECT creator FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$isCreator = $stmt->fetchColumn();


// Check if creator is ID verified
$verifyQuery = "SELECT id_verified FROM users WHERE id = :uid";
$verifyStmt = $db->prepare($verifyQuery);
$verifyStmt->bindParam(':uid', $_SESSION['user_id']);
$verifyStmt->execute();
$verificationStatus = $verifyStmt->fetch(PDO::FETCH_ASSOC);

if (!$verificationStatus['id_verified']) {
    header('Location: verify-identity.php');
    exit;
}


if(!$isCreator) {
    header('Location: become-creator.php');
    exit();
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $content_type = $_POST['content_type'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $tags = trim($_POST['tags'] ?? '');
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    
    // Validate
    if(empty($title) || empty($description) || empty($category) || empty($content_type) || $price <= 0) {
        $error = 'Please fill in all required fields';
    } else {
        // Handle file uploads
        $thumbnail_path = null;
        $file_path = null;
        
        // Upload thumbnail
        if(isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            if(in_array($_FILES['thumbnail']['type'], $allowed_types)) {
                $upload_dir = __DIR__ . '/uploads/thumbnails/';
                if(!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
                $filename = 'thumb_' . uniqid() . '_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $filename;
                
                if(move_uploaded_file($_FILES['thumbnail']['tmp_name'], $upload_path)) {
                    $thumbnail_path = '/uploads/thumbnails/' . $filename;
                }
            }
        }
        
        // Upload main content file
        if(isset($_FILES['content_file']) && $_FILES['content_file']['error'] == 0) {
            $upload_dir = __DIR__ . '/uploads/content/';
            if(!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = pathinfo($_FILES['content_file']['name'], PATHINFO_EXTENSION);
            $filename = 'content_' . uniqid() . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $filename;
            
            if(move_uploaded_file($_FILES['content_file']['tmp_name'], $upload_path)) {
                $file_path = '/uploads/content/' . $filename;
            }
        }
        
        // Insert into database
        $query = "INSERT INTO creator_listings 
                  (creator_id, title, description, category, content_type, price, thumbnail, file_path, tags, is_premium, status, created_at)
                  VALUES (:creator_id, :title, :description, :category, :content_type, :price, :thumbnail, :file_path, :tags, :is_premium, 'active', NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':creator_id', $_SESSION['user_id']);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':content_type', $content_type);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':thumbnail', $thumbnail_path);
        $stmt->bindParam(':file_path', $file_path);
        $stmt->bindParam(':tags', $tags);
        $stmt->bindParam(':is_premium', $is_premium);
        
        if($stmt->execute()) {
            header('Location: creator-listings.php?success=1');
            exit();
        } else {
            $error = 'Failed to create listing. Please try again.';
        }
    }
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
    --color-accent-muted: rgba(31, 111, 235, 0.15);
    --color-success-emphasis: #238636;
    --color-attention-emphasis: #9e6a03;
    --color-danger-emphasis: #da3633;
    --color-done-emphasis: #8957e5;
    --color-shadow: rgba(1, 4, 9, 0.85);
}

body {
    background: var(--color-canvas-default);
    color: var(--color-text-primary);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', Helvetica, Arial, sans-serif;
}

.upload-page {
    min-height: 100vh;
    padding: 2rem 0 4rem 0;
}

.upload-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.page-header {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px var(--color-shadow);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.header-title {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.header-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--color-done-emphasis), var(--color-accent-emphasis));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.header-title h1 {
    font-size: 2rem;
    font-weight: 600;
    color: var(--color-text-primary);
    margin: 0;
}

.btn-secondary {
    padding: 0.75rem 1.5rem;
    background: var(--color-canvas-overlay);
    color: var(--color-text-primary);
    border: 1px solid var(--color-border-default);
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    font-size: 0.875rem;
}

.btn-secondary:hover {
    border-color: var(--color-accent-emphasis);
    transform: translateY(-1px);
}

.upload-form {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 8px 24px var(--color-shadow);
}

.form-section {
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--color-border-default);
}

.form-section:last-of-type {
    border-bottom: none;
    margin-bottom: 0;
}

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--color-text-primary);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-title i {
    color: var(--color-accent-emphasis);
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

.form-input,
.form-textarea,
.form-select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: var(--color-canvas-default);
    border: 1px solid var(--color-border-default);
    border-radius: 8px;
    color: var(--color-text-primary);
    font-size: 0.875rem;
    transition: all 0.2s;
    font-family: inherit;
}

.form-input:focus,
.form-textarea:focus,
.form-select:focus {
    outline: none;
    border-color: var(--color-accent-emphasis);
    box-shadow: 0 0 0 3px var(--color-accent-muted);
}

.form-textarea {
    resize: vertical;
    min-height: 120px;
}

.form-help {
    font-size: 0.8rem;
    color: var(--color-text-tertiary);
    margin-top: 0.375rem;
}

.upload-zone {
    border: 2px dashed var(--color-border-default);
    border-radius: 12px;
    padding: 3rem 2rem;
    text-align: center;
    transition: all 0.3s;
    cursor: pointer;
    background: var(--color-canvas-overlay);
}

.upload-zone:hover {
    border-color: var(--color-accent-emphasis);
    background: var(--color-canvas-subtle);
}

.upload-zone.dragover {
    border-color: var(--color-success-emphasis);
    background: rgba(35, 134, 54, 0.1);
    border-style: solid;
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

.preview-container {
    margin-top: 1rem;
    display: none;
}

.preview-container.active {
    display: block;
}

.preview-item {
    background: var(--color-canvas-overlay);
    border: 1px solid var(--color-border-default);
    border-radius: 8px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.preview-thumb {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    object-fit: cover;
    background: var(--color-canvas-default);
}

.preview-info {
    flex: 1;
    min-width: 0;
}

.preview-name {
    font-weight: 600;
    color: var(--color-text-primary);
    margin-bottom: 0.25rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.preview-meta {
    font-size: 0.8rem;
    color: var(--color-text-secondary);
}

.preview-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-remove {
    padding: 0.5rem 1rem;
    background: var(--color-danger-emphasis);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-remove:hover {
    background: #b52d2a;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--color-canvas-overlay);
    border: 1px solid var(--color-border-default);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.checkbox-group:hover {
    border-color: var(--color-accent-emphasis);
}

.checkbox-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.checkbox-label {
    flex: 1;
}

.checkbox-label strong {
    color: var(--color-text-primary);
    display: block;
    margin-bottom: 0.25rem;
}

.checkbox-label small {
    color: var(--color-text-secondary);
    font-size: 0.8rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    padding-top: 2rem;
}

.btn-submit {
    flex: 1;
    padding: 1rem 2rem;
    background: linear-gradient(135deg, var(--color-done-emphasis), var(--color-accent-emphasis));
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

.btn-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: rgba(35, 134, 54, 0.15);
    border: 1px solid var(--color-success-emphasis);
    color: var(--color-success-emphasis);
}

.alert-danger {
    background: rgba(218, 54, 51, 0.15);
    border: 1px solid var(--color-danger-emphasis);
    color: var(--color-danger-emphasis);
}

.info-box {
    background: var(--color-canvas-overlay);
    border: 1px solid var(--color-border-default);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    display: flex;
    gap: 1rem;
}

.info-box i {
    color: var(--color-accent-emphasis);
    font-size: 1.5rem;
}

.info-box-content {
    flex: 1;
}

.info-box-title {
    font-weight: 600;
    color: var(--color-text-primary);
    margin-bottom: 0.25rem;
}

.info-box-text {
    font-size: 0.875rem;
    color: var(--color-text-secondary);
    margin: 0;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<div class="upload-page">
    <div class="upload-container">
        <div class="page-header">
            <div class="header-content">
                <div class="header-title">
                    <div class="header-icon">
                        <i class="bi bi-cloud-upload"></i>
                    </div>
                    <div>
                        <h1>Upload New Content</h1>
                        <p style="color: var(--color-text-secondary); margin: 0.25rem 0 0 0;">
                            Create a new listing for your content
                        </p>
                    </div>
                </div>
                
                <a href="creator-listings.php" class="btn-secondary">
                    <i class="bi bi-arrow-left"></i>
                    Back to Listings
                </a>
            </div>
        </div>

        <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <div class="info-box">
            <i class="bi bi-lightbulb"></i>
            <div class="info-box-content">
                <div class="info-box-title">Tips for Success</div>
                <p class="info-box-text">
                    Use clear titles, detailed descriptions, and high-quality thumbnails. 
                    Price competitively and tag appropriately to reach your target audience.
                </p>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" class="upload-form" id="uploadForm">
            <!-- Basic Information -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="bi bi-info-circle"></i>
                    Basic Information
                </h2>
                
                <div class="form-group">
                    <label class="form-label required">Title</label>
                    <input type="text" name="title" class="form-input" 
                           placeholder="Enter a catchy title for your content" required>
                    <div class="form-help">Make it clear and descriptive</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">Description</label>
                    <textarea name="description" class="form-textarea" 
                              placeholder="Describe your content in detail..." required></textarea>
                    <div class="form-help">Minimum 50 characters. Tell buyers what they'll get.</div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <option value="photos">Photos</option>
                            <option value="videos">Videos</option>
                            <option value="exclusive">Exclusive Content</option>
                            <option value="premium">Premium</option>
                            <option value="fitness">Fitness</option>
                            <option value="lifestyle">Lifestyle</option>
                            <option value="fashion">Fashion</option>
                            <option value="art">Art</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Content Type</label>
                        <select name="content_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="photo">Photo/Image</option>
                            <option value="video">Video</option>
                            <option value="audio">Audio</option>
                            <option value="document">Document</option>
                            <option value="set">Content Set</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tags</label>
                    <input type="text" name="tags" class="form-input" 
                           placeholder="sexy, hot, exclusive, custom">
                    <div class="form-help">Separate tags with commas. Max 10 tags.</div>
                </div>
            </div>

            <!-- File Uploads -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="bi bi-image"></i>
                    Files
                </h2>
                
                <div class="form-group">
                    <label class="form-label required">Thumbnail</label>
                    <div class="upload-zone" id="thumbnailZone" onclick="document.getElementById('thumbnailInput').click()">
                        <i class="bi bi-image upload-icon"></i>
                        <div class="upload-text">Click or drag to upload thumbnail</div>
                        <div class="upload-subtext">JPEG or PNG, max 5MB. Recommended: 1200x800px</div>
                    </div>
                    <input type="file" id="thumbnailInput" name="thumbnail" class="file-input" 
                           accept="image/jpeg,image/png,image/jpg" required>
                    <div class="preview-container" id="thumbnailPreview"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">Content File</label>
                    <div class="upload-zone" id="contentZone" onclick="document.getElementById('contentInput').click()">
                        <i class="bi bi-file-earmark-arrow-up upload-icon"></i>
                        <div class="upload-text">Click or drag to upload content file</div>
                        <div class="upload-subtext">Any file type, max 500MB</div>
                    </div>
                    <input type="file" id="contentInput" name="content_file" class="file-input" required>
                    <div class="preview-container" id="contentPreview"></div>
                </div>
            </div>

            <!-- Pricing -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="bi bi-currency-dollar"></i>
                    Pricing
                </h2>
                
                <div class="form-group">
                    <label class="form-label required">Price (USD)</label>
                    <input type="number" name="price" class="form-input" 
                           placeholder="9.99" step="0.01" min="0.99" required>
                    <div class="form-help">
                        You'll receive 85% of each sale. Platform fee: 15%
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_premium" id="isPremium">
                    <label for="isPremium" class="checkbox-label">
                        <strong>Mark as Premium</strong>
                        <small>Premium content gets highlighted and better visibility</small>
                    </label>
                </div>
            </div>

            <!-- Submit -->
            <div class="form-actions">
                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="bi bi-check-circle"></i>
                    Publish Listing
                </button>
                <a href="creator-listings.php" class="btn-secondary" style="padding: 1rem 2rem;">
                    <i class="bi bi-x-circle"></i>
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Drag and drop functionality
function setupUploadZone(zoneId, inputId, previewId) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    
    zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.classList.add('dragover');
    });
    
    zone.addEventListener('dragleave', () => {
        zone.classList.remove('dragover');
    });
    
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            input.files = files;
            handleFilePreview(files[0], preview, zoneId === 'thumbnailZone');
        }
    });
    
    input.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleFilePreview(e.target.files[0], preview, zoneId === 'thumbnailZone');
        }
    });
}

function handleFilePreview(file, previewContainer, isImage) {
    previewContainer.classList.add('active');
    
    const fileSize = (file.size / (1024 * 1024)).toFixed(2);
    const fileName = file.name;
    
    let previewHTML = `
        <div class="preview-item">
            ${isImage ? '<img src="" class="preview-thumb" id="previewImg">' : '<div class="preview-thumb" style="display: flex; align-items: center; justify-content: center;"><i class="bi bi-file-earmark" style="font-size: 2rem; color: var(--color-text-secondary);"></i></div>'}
            <div class="preview-info">
                <div class="preview-name">${fileName}</div>
                <div class="preview-meta">${fileSize} MB</div>
            </div>
            <div class="preview-actions">
                <button type="button" class="btn-remove" onclick="removeFile('${previewContainer.id}', '${isImage ? 'thumbnailInput' : 'contentInput'}')">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>
        </div>
    `;
    
    previewContainer.innerHTML = previewHTML;
    
    // Load image preview
    if (isImage && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

function removeFile(previewId, inputId) {
    document.getElementById(previewId).classList.remove('active');
    document.getElementById(previewId).innerHTML = '';
    document.getElementById(inputId).value = '';
}

// Initialize upload zones
setupUploadZone('thumbnailZone', 'thumbnailInput', 'thumbnailPreview');
setupUploadZone('contentZone', 'contentInput', 'contentPreview');

// Form validation
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    const title = document.querySelector('[name="title"]').value.trim();
    const description = document.querySelector('[name="description"]').value.trim();
    const price = parseFloat(document.querySelector('[name="price"]').value);
    
    if (title.length < 5) {
        e.preventDefault();
        alert('Title must be at least 5 characters long');
        return;
    }
    
    if (description.length < 50) {
        e.preventDefault();
        alert('Description must be at least 50 characters long');
        return;
    }
    
    if (price < 0.99) {
        e.preventDefault();
        alert('Minimum price is $0.99');
        return;
    }
    
    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Uploading...';
});

// Price calculator
document.querySelector('[name="price"]').addEventListener('input', function(e) {
    const price = parseFloat(e.target.value) || 0;
    const yourEarnings = (price * 0.85).toFixed(2);
    const platformFee = (price * 0.15).toFixed(2);
    
    const helpText = e.target.nextElementSibling;
    helpText.innerHTML = `You'll receive <strong style="color: var(--color-success-emphasis);">$${yourEarnings}</strong> per sale. Platform fee: $${platformFee}`;
});

// Character counter for description
document.querySelector('[name="description"]').addEventListener('input', function(e) {
    const chars = e.target.value.length;
    const helpText = e.target.nextElementSibling;
    
    if (chars < 50) {
        helpText.innerHTML = `${chars}/50 characters (${50 - chars} more needed)`;
        helpText.style.color = 'var(--color-danger-emphasis)';
    } else {
        helpText.innerHTML = `${chars} characters - Looking good!`;
        helpText.style.color = 'var(--color-success-emphasis)';
    }
});

// Tag counter
document.querySelector('[name="tags"]').addEventListener('input', function(e) {
    const tags = e.target.value.split(',').filter(tag => tag.trim() !== '');
    const helpText = e.target.nextElementSibling;
    
    if (tags.length > 10) {
        helpText.innerHTML = `Too many tags! Please use max 10 tags. (Currently: ${tags.length})`;
        helpText.style.color = 'var(--color-danger-emphasis)';
    } else {
        helpText.innerHTML = `Separate tags with commas. Max 10 tags. (Currently: ${tags.length})`;
        helpText.style.color = 'var(--color-text-tertiary)';
    }
});
</script>

<?php include 'views/footer.php'; ?>
