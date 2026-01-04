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

if(!$isCreator) {
    header('Location: become-creator.php');
    exit();
}

$listing_id = $_GET['id'] ?? 0;

// Get listing and verify ownership
$query = "SELECT * FROM creator_listings WHERE id = :id AND creator_id = :creator_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $listing_id);
$stmt->bindParam(':creator_id', $_SESSION['user_id']);
$stmt->execute();
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$listing) {
    header('Location: creator-listings.php');
    exit();
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['delete_listing'])) {
        // Delete listing
        $deleteQuery = "DELETE FROM creator_listings WHERE id = :id AND creator_id = :creator_id";
        $stmt = $db->prepare($deleteQuery);
        $stmt->bindParam(':id', $listing_id);
        $stmt->bindParam(':creator_id', $_SESSION['user_id']);
        
        if($stmt->execute()) {
            // Delete files
            if(!empty($listing['thumbnail']) && file_exists(__DIR__ . $listing['thumbnail'])) {
                @unlink(__DIR__ . $listing['thumbnail']);
            }
            if(!empty($listing['file_path']) && file_exists(__DIR__ . $listing['file_path'])) {
                @unlink(__DIR__ . $listing['file_path']);
            }
            
            header('Location: creator-listings.php?deleted=1');
            exit();
        } else {
            $error = 'Failed to delete listing';
        }
    } else {
        // Update listing
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = $_POST['category'] ?? '';
        $content_type = $_POST['content_type'] ?? '';
        $price = floatval($_POST['price'] ?? 0);
        $tags = trim($_POST['tags'] ?? '');
        $is_premium = isset($_POST['is_premium']) ? 1 : 0;
        $status = $_POST['status'] ?? 'active';
        
        if(empty($title) || empty($description) || empty($category) || empty($content_type) || $price <= 0) {
            $error = 'Please fill in all required fields';
        } else {
            // Handle file uploads
            $thumbnail_path = $listing['thumbnail'];
            $file_path = $listing['file_path'];
            
            // Upload new thumbnail if provided
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
                        // Delete old thumbnail
                        if(!empty($listing['thumbnail']) && file_exists(__DIR__ . $listing['thumbnail'])) {
                            @unlink(__DIR__ . $listing['thumbnail']);
                        }
                        $thumbnail_path = '/uploads/thumbnails/' . $filename;
                    }
                }
            }
            
            // Upload new content file if provided
            if(isset($_FILES['content_file']) && $_FILES['content_file']['error'] == 0) {
                $upload_dir = __DIR__ . '/uploads/content/';
                if(!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_ext = pathinfo($_FILES['content_file']['name'], PATHINFO_EXTENSION);
                $filename = 'content_' . uniqid() . '_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $filename;
                
                if(move_uploaded_file($_FILES['content_file']['tmp_name'], $upload_path)) {
                    // Delete old content file
                    if(!empty($listing['file_path']) && file_exists(__DIR__ . $listing['file_path'])) {
                        @unlink(__DIR__ . $listing['file_path']);
                    }
                    $file_path = '/uploads/content/' . $filename;
                }
            }
            
            // Update database
            $query = "UPDATE creator_listings SET
                      title = :title,
                      description = :description,
                      category = :category,
                      content_type = :content_type,
                      price = :price,
                      thumbnail = :thumbnail,
                      file_path = :file_path,
                      tags = :tags,
                      is_premium = :is_premium,
                      status = :status,
                      updated_at = NOW()
                      WHERE id = :id AND creator_id = :creator_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':content_type', $content_type);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':thumbnail', $thumbnail_path);
            $stmt->bindParam(':file_path', $file_path);
            $stmt->bindParam(':tags', $tags);
            $stmt->bindParam(':is_premium', $is_premium);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $listing_id);
            $stmt->bindParam(':creator_id', $_SESSION['user_id']);
            
            if($stmt->execute()) {
                $success = 'Listing updated successfully!';
                // Refresh listing data
                $stmt = $db->prepare("SELECT * FROM creator_listings WHERE id = :id");
                $stmt->bindParam(':id', $listing_id);
                $stmt->execute();
                $listing = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Failed to update listing';
            }
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

.edit-page {
    min-height: 100vh;
    padding: 2rem 0 4rem 0;
}

.edit-container {
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

.header-actions {
    display: flex;
    gap: 0.75rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    border: none;
    font-size: 0.875rem;
}

.btn-secondary {
    background: var(--color-canvas-overlay);
    color: var(--color-text-primary);
    border: 1px solid var(--color-border-default);
}

.btn-secondary:hover {
    border-color: var(--color-accent-emphasis);
    transform: translateY(-1px);
}

.btn-danger {
    background: var(--color-danger-emphasis);
    color: white;
}

.btn-danger:hover {
    background: #b52d2a;
    transform: translateY(-1px);
}

.edit-form {
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

.current-file {
    background: var(--color-canvas-overlay);
    border: 1px solid var(--color-border-default);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.current-thumb {
    width: 100px;
    height: 100px;
    border-radius: 8px;
    object-fit: cover;
}

.current-info {
    flex: 1;
}

.current-label {
    font-size: 0.75rem;
    color: var(--color-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.current-name {
    font-weight: 600;
    color: var(--color-text-primary);
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

.danger-zone {
    background: rgba(218, 54, 51, 0.05);
    border: 2px solid var(--color-danger-emphasis);
    border-radius: 12px;
    padding: 2rem;
    margin-top: 2rem;
}

.danger-zone h3 {
    color: var(--color-danger-emphasis);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .header-actions {
        width: 100%;
        flex-direction: column;
    }
}
</style>

<div class="edit-page">
    <div class="edit-container">
        <div class="page-header">
            <div class="header-content">
                <div class="header-title">
                    <div class="header-icon">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h1>Edit Listing</h1>
                        <p style="color: var(--color-text-secondary); margin: 0.25rem 0 0 0;">
                            Update your content listing
                        </p>
                    </div>
                </div>
                
                <div class="header-actions">
                    <a href="marketlisting.php?id=<?php echo $listing['id']; ?>" class="btn btn-secondary" target="_blank">
                        <i class="bi bi-eye"></i>
                        Preview
                    </a>
                    <a href="creator-listings.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i>
                        Back
                    </a>
                </div>
            </div>
        </div>

        <?php if($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="edit-form">
            <!-- Basic Information -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="bi bi-info-circle"></i>
                    Basic Information
                </h2>
                
                <div class="form-group">
                    <label class="form-label required">Title</label>
                    <input type="text" name="title" class="form-input" 
                           value="<?php echo htmlspecialchars($listing['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">Description</label>
                    <textarea name="description" class="form-textarea" required><?php echo htmlspecialchars($listing['description']); ?></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <option value="photos" <?php echo $listing['category'] == 'photos' ? 'selected' : ''; ?>>Photos</option>
                            <option value="videos" <?php echo $listing['category'] == 'videos' ? 'selected' : ''; ?>>Videos</option>
                            <option value="exclusive" <?php echo $listing['category'] == 'exclusive' ? 'selected' : ''; ?>>Exclusive Content</option>
                            <option value="premium" <?php echo $listing['category'] == 'premium' ? 'selected' : ''; ?>>Premium</option>
                            <option value="fitness" <?php echo $listing['category'] == 'fitness' ? 'selected' : ''; ?>>Fitness</option>
                            <option value="lifestyle" <?php echo $listing['category'] == 'lifestyle' ? 'selected' : ''; ?>>Lifestyle</option>
                            <option value="fashion" <?php echo $listing['category'] == 'fashion' ? 'selected' : ''; ?>>Fashion</option>
                            <option value="art" <?php echo $listing['category'] == 'art' ? 'selected' : ''; ?>>Art</option>
                            <option value="other" <?php echo $listing['category'] == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Content Type</label>
                        <select name="content_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="photo" <?php echo $listing['content_type'] == 'photo' ? 'selected' : ''; ?>>Photo/Image</option>
                            <option value="video" <?php echo $listing['content_type'] == 'video' ? 'selected' : ''; ?>>Video</option>
                            <option value="audio" <?php echo $listing['content_type'] == 'audio' ? 'selected' : ''; ?>>Audio</option>
                            <option value="document" <?php echo $listing['content_type'] == 'document' ? 'selected' : ''; ?>>Document</option>
                            <option value="set" <?php echo $listing['content_type'] == 'set' ? 'selected' : ''; ?>>Content Set</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="active" <?php echo $listing['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $listing['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="pending" <?php echo $listing['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tags</label>
                    <input type="text" name="tags" class="form-input" 
                           value="<?php echo htmlspecialchars($listing['tags'] ?? ''); ?>"
                           placeholder="sexy, hot, exclusive, custom">
                    <div class="form-help">Separate tags with commas</div>
                </div>
            </div>

            <!-- Files -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="bi bi-image"></i>
                    Files
                </h2>
                
                <div class="form-group">
                    <label class="form-label">Thumbnail</label>
                    <?php if(!empty($listing['thumbnail'])): ?>
                    <div class="current-file">
                        <img src="<?php echo htmlspecialchars($listing['thumbnail']); ?>" class="current-thumb" alt="Current thumbnail">
                        <div class="current-info">
                            <div class="current-label">Current Thumbnail</div>
                            <div class="current-name"><?php echo basename($listing['thumbnail']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <input type="file" name="thumbnail" class="form-input" accept="image/jpeg,image/png,image/jpg">
                    <div class="form-help">Leave empty to keep current thumbnail</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Content File</label>
                    <?php if(!empty($listing['file_path'])): ?>
                    <div class="current-file">
                        <div class="current-thumb" style="display: flex; align-items: center; justify-content: center; background: var(--color-canvas-default);">
                            <i class="bi bi-file-earmark" style="font-size: 2.5rem; color: var(--color-text-secondary);"></i>
                        </div>
                        <div class="current-info">
                            <div class="current-label">Current Content File</div>
                            <div class="current-name"><?php echo basename($listing['file_path']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <input type="file" name="content_file" class="form-input">
                    <div class="form-help">Leave empty to keep current file</div>
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
                           value="<?php echo $listing['price']; ?>"
                           step="0.01" min="0.99" required>
                    <div class="form-help">
                        You'll receive 85% of each sale. Platform fee: 15%
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_premium" id="isPremium" 
                           <?php echo $listing['is_premium'] ? 'checked' : ''; ?>>
                    <label for="isPremium" class="checkbox-label">
                        <strong>Mark as Premium</strong>
                        <small>Premium content gets highlighted and better visibility</small>
                    </label>
                </div>
            </div>

            <!-- Submit -->
            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <i class="bi bi-check-circle"></i>
                    Update Listing
                </button>
                <a href="creator-listings.php" class="btn btn-secondary" style="padding: 1rem 2rem;">
                    <i class="bi bi-x-circle"></i>
                    Cancel
                </a>
            </div>
        </form>

        <!-- Danger Zone -->
        <div class="danger-zone">
            <h3>
                <i class="bi bi-exclamation-triangle-fill"></i>
                Danger Zone
            </h3>
            <p style="color: var(--color-text-secondary); margin-bottom: 1.5rem;">
                Deleting this listing is permanent and cannot be undone. All files and data will be permanently removed.
            </p>
            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this listing? This action cannot be undone!');">
                <button type="submit" name="delete_listing" class="btn btn-danger">
                    <i class="bi bi-trash"></i>
                    Delete Listing Permanently
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
