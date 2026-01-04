<?php
session_start();
require_once 'config/database.php';
require_once 'classes/ImageUpload.php';

if(!isset($_SESSION['user_id']) || !isset($_GET['listing_id'])) {
    header('Location: my-listings.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$imageUpload = new ImageUpload($db);

$listing_id = $_GET['listing_id'];

// Verify ownership
$query = "SELECT * FROM listings WHERE id = :id AND user_id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $listing_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$listing = $stmt->fetch();

if(!$listing) {
    header('Location: my-listings.php');
    exit();
}

$images = $imageUpload->getListingImages($listing_id);

include 'views/header.php';
?>

<div class="container" style="margin: 2rem auto;">
    <a href="listing.php?id=<?php echo $listing['id']; ?>" class="btn-secondary">← Back to Listing</a>
    
    <div class="image-manager">
        <h2>Manage Images for: <?php echo htmlspecialchars($listing['title']); ?></h2>
        
        <div class="upload-section">
            <h3>Upload New Image</h3>
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">
                <div class="file-input-wrapper">
                    <input type="file" name="image" id="imageInput" accept="image/*" required>
                    <label for="imageInput" class="file-label">Choose Image</label>
                    <span id="fileName">No file chosen</span>
                </div>
                <button type="submit" class="btn-primary">Upload</button>
            </form>
            <div id="uploadStatus"></div>
            <p class="help-text">Max 10 images per listing. Max file size: 5MB. Formats: JPG, PNG, GIF, WebP</p>
        </div>

        <div class="images-grid">
            <?php if(count($images) > 0): ?>
                <?php foreach($images as $img): ?>
                <div class="image-card" data-image-id="<?php echo $img['id']; ?>">
                    <img src="<?php echo htmlspecialchars($img['file_path']); ?>" alt="Listing image">
                    <?php if($img['is_primary']): ?>
                        <div class="primary-badge">Primary</div>
                    <?php endif; ?>
                    <div class="image-actions">
                        <?php if(!$img['is_primary']): ?>
                        <button onclick="setPrimary(<?php echo $img['id']; ?>)" class="btn-small btn-secondary">Set as Primary</button>
                        <?php endif; ?>
                        <button onclick="deleteImage(<?php echo $img['id']; ?>)" class="btn-small btn-danger">Delete</button>
                    </div>
                    <div class="image-info">
                        <?php echo round($img['file_size'] / 1024); ?>KB • <?php echo $img['width']; ?>x<?php echo $img['height']; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-images">No images uploaded yet</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.image-manager {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin: 2rem 0;
}

.upload-section {
    background: #f8f9fa;
    padding: 2rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.upload-section h3 {
    margin-bottom: 1rem;
}

#uploadForm {
    display: flex;
    gap: 1rem;
    align-items: center;
    margin-bottom: 1rem;
}

.file-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.file-input-wrapper input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.file-label {
    padding: 0.8rem 1.5rem;
    background: #3498db;
    color: white;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.3s;
}

.file-label:hover {
    background: #2980b9;
}

#fileName {
    color: #666;
}

.help-text {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
}

#uploadStatus {
    margin-top: 1rem;
    padding: 0.8rem;
    border-radius: 5px;
    display: none;
}

#uploadStatus.success {
    background: #d4edda;
    color: #155724;
    display: block;
}

#uploadStatus.error {
    background: #f8d7da;
    color: #721c24;
    display: block;
}

.images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.5rem;
}

.image-card {
    position: relative;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.3s;
}

.image-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.image-card img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.primary-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #27ae60;
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: bold;
}

.image-actions {
    padding: 1rem;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-small {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
    flex: 1;
}

.image-info {
    padding: 0 1rem 1rem;
    font-size: 0.85rem;
    color: #666;
}

.no-images {
    text-align: center;
    padding: 3rem;
    color: #999;
}
</style>

<script>
// Show selected file name
document.getElementById('imageInput').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || 'No file chosen';
    document.getElementById('fileName').textContent = fileName;
});

// Handle upload
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const statusDiv = document.getElementById('uploadStatus');
    
    fetch('upload-images.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            statusDiv.className = 'success';
            statusDiv.textContent = 'Image uploaded successfully!';
            setTimeout(() => location.reload(), 1500);
        } else {
            statusDiv.className = 'error';
            statusDiv.textContent = 'Error: ' + data.error;
        }
    })
    .catch(error => {
        statusDiv.className = 'error';
        statusDiv.textContent = 'Upload failed: ' + error;
    });
});

// Set primary image
function setPrimary(imageId) {
    if(!confirm('Set this as the primary image?')) return;
    
    fetch('set-primary-image.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `image_id=${imageId}&listing_id=<?php echo $listing_id; ?>`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert('Failed to set primary image');
        }
    });
}

// Delete image
function deleteImage(imageId) {
    if(!confirm('Are you sure you want to delete this image?')) return;
    
    fetch('delete-image.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `image_id=${imageId}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            document.querySelector(`[data-image-id="${imageId}"]`).remove();
            if(document.querySelectorAll('.image-card').length === 0) {
                location.reload();
            }
        } else {
            alert('Failed to delete image');
        }
    });
}
</script>

<?php include 'views/footer.php'; ?>