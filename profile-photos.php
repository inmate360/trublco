<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user's photos
$query = "SELECT * FROM user_photos WHERE user_id = :user_id ORDER BY is_primary DESC, display_order ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$photos = $stmt->fetchAll();

include 'views/header.php';
?>

<div class="page-content">
    <div class="container-narrow">
        <div class="card">
            <h2>Manage Profile Photos</h2>
            
            <div class="alert alert-info">
                <strong>Photo Guidelines:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <li>Upload clear, recent photos of yourself</li>
                    <li>Maximum 10 photos per profile</li>
                    <li>Max file size: 5MB per photo</li>
                    <li>Accepted formats: JPG, PNG, GIF, WebP</li>
                    <li>No nudity or inappropriate content</li>
                </ul>
            </div>
            
            <?php if(count($photos) < 10): ?>
            <div class="upload-section">
                <h3>Upload New Photo</h3>
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="file-input-wrapper">
                        <input type="file" name="photo" id="photoInput" accept="image/*" required>
                        <label for="photoInput" class="btn-primary">Choose Photo</label>
                        <span id="fileName" style="margin-left: 1rem; color: var(--text-gray);">No file chosen</span>
                    </div>
                    <button type="submit" class="btn-primary" style="margin-top: 1rem;">Upload Photo</button>
                </form>
                <div id="uploadStatus"></div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">
                You have reached the maximum of 10 photos. Delete a photo to upload a new one.
            </div>
            <?php endif; ?>
            
            <?php if(count($photos) > 0): ?>
            <div style="margin-top: 2rem;">
                <h3>Your Photos</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <?php foreach($photos as $photo): ?>
                    <div class="photo-card" data-photo-id="<?php echo $photo['id']; ?>">
                        <div style="position: relative;">
                            <img src="<?php echo htmlspecialchars($photo['file_path']); ?>" 
                                 alt="Profile photo" 
                                 style="width: 100%; height: 200px; object-fit: cover; border-radius: 10px;">
                            
                            <?php if($photo['is_primary']): ?>
                            <div style="position: absolute; top: 10px; right: 10px; background: var(--primary-purple); color: white; padding: 0.3rem 0.8rem; border-radius: 8px; font-size: 0.75rem; font-weight: bold;">
                                PRIMARY
                            </div>
                            <?php endif; ?>
                            
                            <?php if($photo['is_verified']): ?>
                            <div style="position: absolute; top: 10px; left: 10px; background: var(--success-green); color: white; padding: 0.3rem 0.8rem; border-radius: 8px; font-size: 0.75rem; font-weight: bold;">
                                ✓ VERIFIED
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-top: 0.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                            <?php if(!$photo['is_primary']): ?>
                            <button onclick="setPrimary(<?php echo $photo['id']; ?>)" class="btn-secondary btn-small">Set Primary</button>
                            <?php else: ?>
                            <button class="btn-secondary btn-small" disabled>Primary</button>
                            <?php endif; ?>
                            <button onclick="deletePhoto(<?php echo $photo['id']; ?>)" class="btn-danger btn-small">Delete</button>
                        </div>
                        
                        <p style="font-size: 0.8rem; color: var(--text-gray); margin-top: 0.5rem; text-align: center;">
                            <?php echo round($photo['file_size'] / 1024); ?>KB
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 3rem; background: var(--border-color); border-radius: 10px; margin-top: 2rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">📷</div>
                <h3>No photos yet</h3>
                <p style="color: var(--text-gray);">Upload your first photo to make your profile stand out!</p>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                <a href="profile.php?id=<?php echo $_SESSION['user_id']; ?>" class="btn-secondary">Back to Profile</a>
            </div>
        </div>
    </div>
</div>

<style>
.file-input-wrapper {
    display: flex;
    align-items: center;
}

.file-input-wrapper input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

#uploadStatus {
    margin-top: 1rem;
    padding: 1rem;
    border-radius: 8px;
    display: none;
}

#uploadStatus.success {
    background: var(--success-green);
    color: white;
    display: block;
}

#uploadStatus.error {
    background: var(--danger-red);
    color: white;
    display: block;
}

.photo-card {
    background: var(--card-bg);
    padding: 1rem;
    border-radius: 12px;
    border: 1px solid var(--border-color);
}
</style>

<script>
// Show selected file name
document.getElementById('photoInput').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || 'No file chosen';
    document.getElementById('fileName').textContent = fileName;
});

// Handle upload
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData();
    const fileInput = document.getElementById('photoInput');
    formData.append('photo', fileInput.files[0]);
    
    const statusDiv = document.getElementById('uploadStatus');
    statusDiv.textContent = 'Uploading...';
    statusDiv.className = '';
    statusDiv.style.display = 'block';
    
    fetch('upload-profile-photo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            statusDiv.className = 'success';
            statusDiv.textContent = 'Photo uploaded successfully!';
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

// Set primary photo
function setPrimary(photoId) {
    if(!confirm('Set this as your primary photo?')) return;
    
    fetch('set-primary-photo.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `photo_id=${photoId}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert('Failed to set primary photo');
        }
    });
}

// Delete photo
function deletePhoto(photoId) {
    if(!confirm('Are you sure you want to delete this photo?')) return;
    
    fetch('delete-profile-photo.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `photo_id=${photoId}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            document.querySelector(`[data-photo-id="${photoId}"]`).remove();
            if(document.querySelectorAll('.photo-card').length === 0) {
                location.reload();
            }
        } else {
            alert('Failed to delete photo');
        }
    });
}
</script>

<?php include 'views/footer.php'; ?>