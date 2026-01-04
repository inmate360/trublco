<?php
session_start();
require_once 'config/database.php';
require_once 'classes/MediaContent.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$mediaContent = new MediaContent($db);

// Check if user is a creator
$query = "SELECT is_creator FROM creator_settings WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$creator_settings = $stmt->fetch();

if(!$creator_settings || !$creator_settings['is_creator']) {
    header('Location: become-creator.php');
    exit();
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description']),
        'content_type' => $_POST['content_type'],
        'price' => floatval($_POST['price']),
        'is_free' => isset($_POST['is_free']),
        'is_exclusive' => isset($_POST['is_exclusive']),
        'blur_preview' => isset($_POST['blur_preview']),
        'status' => $_POST['status'] ?? 'published'
    ];
    
    $files = [];
    if(!empty($_FILES['media_files']['tmp_name'][0])) {
        foreach($_FILES['media_files']['tmp_name'] as $key => $tmp_name) {
            $files[] = [
                'tmp_name' => $_FILES['media_files']['tmp_name'][$key],
                'name' => $_FILES['media_files']['name'][$key],
                'type' => $_FILES['media_files']['type'][$key],
                'size' => $_FILES['media_files']['size'][$key]
            ];
        }
    }
    
    $result = $mediaContent->createContent($_SESSION['user_id'], $data, $files);
    
    if($result['success']) {
        header('Location: creator-dashboard.php?uploaded=1');
        exit();
    } else {
        $error = $result['error'];
    }
}

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/light-theme.css">

<style>
.upload-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 20px;
}

.upload-header {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    margin-bottom: 2rem;
}

.upload-zone {
    border: 3px dashed var(--border-color);
    border-radius: 20px;
    padding: 3rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    background: rgba(66, 103, 245, 0.05);
    margin-bottom: 2rem;
}

.upload-zone:hover {
    border-color: var(--primary-blue);
    background: rgba(66, 103, 245, 0.1);
}

.upload-zone.dragover {
    border-color: var(--primary-blue);
    background: rgba(66, 103, 245, 0.15);
    transform: scale(1.02);
}

.file-preview {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.file-item {
    position: relative;
    border-radius: 10px;
    overflow: hidden;
    border: 2px solid var(--border-color);
}

.file-item img {
    width: 100%;
    height: 150px;
    object-fit: cover;
}

.remove-file {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: rgba(239, 68, 68, 0.9);
    color: white;
    border: none;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pricing-presets {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.price-preset {
    padding: 0.75rem;
    background: rgba(66, 103, 245, 0.1);
    border: 2px solid var(--border-color);
    border-radius: 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.price-preset:hover {
    border-color: var(--primary-blue);
    background: rgba(66, 103, 245, 0.2);
}
</style>

<div class="page-content">
    <div class="upload-container">
        
        <div class="upload-header">
            <div style="font-size: 4rem; margin-bottom: 1rem;">⬆️</div>
            <h1 style="margin: 0 0 0.5rem;">Upload Content</h1>
            <p style="opacity: 0.9; margin: 0;">Share your exclusive content with your subscribers</p>
        </div>
        
        <?php if($error): ?>
        <div class="alert alert-error" style="margin-bottom: 2rem;">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                
                <!-- Upload Zone -->
                <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📸</div>
                    <h3 style="margin-bottom: 0.5rem;">Click or Drag Files Here</h3>
                    <p style="color: var(--text-gray);">
                        Upload photos or videos (Max 10 files, 50MB each)
                    </p>
                    <input type="file" 
                           id="fileInput" 
                           name="media_files[]" 
                           multiple 
                           accept="image/*,video/*"
                           style="display: none;"
                           onchange="handleFiles(this.files)">
                </div>
                
                <div id="filePreview" class="file-preview"></div>
                
                <!-- Title -->
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" 
                           name="title" 
                           class="form-control" 
                           placeholder="Give your content a catchy title..."
                           required
                           maxlength="255">
                </div>
                
                <!-- Description -->
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" 
                              class="form-control" 
                              rows="4"
                              placeholder="Describe your content..."></textarea>
                </div>
                
                <!-- Content Type -->
                <div class="form-group">
                    <label>Content Type *</label>
                    <select name="content_type" class="form-control" required>
                        <option value="photo">Single Photo</option>
                        <option value="photo_set">Photo Set</option>
                        <option value="video">Single Video</option>
                        <option value="video_set">Video Set</option>
                    </select>
                </div>
                
                <!-- Pricing -->
                <div class="form-group">
                    <label>Price (in coins) *</label>
                    <input type="number" 
                           name="price" 
                           id="priceInput"
                           class="form-control" 
                           min="0"
                           step="1"
                           value="50"
                           required>
                    
                    <div class="pricing-presets">
                        <div class="price-preset" onclick="setPrice(0)">
                            <strong>Free</strong><br>
                            <small style="color: var(--text-gray);">0 coins</small>
                        </div>
                        <div class="price-preset" onclick="setPrice(25)">
                            <strong>Low</strong><br>
                            <small style="color: var(--text-gray);">25 coins</small>
                        </div>
                        <div class="price-preset" onclick="setPrice(50)">
                            <strong>Medium</strong><br>
                            <small style="color: var(--text-gray);">50 coins</small>
                        </div>
                        <div class="price-preset" onclick="setPrice(100)">
                            <strong>High</strong><br>
                            <small style="color: var(--text-gray);">100 coins</small>
                        </div>
                        <div class="price-preset" onclick="setPrice(200)">
                            <strong>Premium</strong><br>
                            <small style="color: var(--text-gray);">200 coins</small>
                        </div>
                    </div>
                </div>
                
                <!-- Options -->
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_free" id="isFree" onchange="toggleFree()">
                        <span>Make this free for all subscribers</span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="blur_preview" checked>
                        <span>Blur preview image (Recommended for paid content)</span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_exclusive">
                        <span>Mark as exclusive/limited content</span>
                    </label>
                </div>
                
                <!-- Status -->
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="published">Publish Now</option>
                        <option value="draft">Save as Draft</option>
                    </select>
                </div>
                
                <!-- Submit -->
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <a href="creator-dashboard.php" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-primary">
                        🚀 Upload Content
                    </button>
                </div>
                
            </form>
        </div>
        
    </div>
</div>

<script>
let selectedFiles = [];

// Drag and drop
const uploadZone = document.getElementById('uploadZone');

uploadZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadZone.classList.add('dragover');
});

uploadZone.addEventListener('dragleave', () => {
    uploadZone.classList.remove('dragover');
});

uploadZone.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    handleFiles(e.dataTransfer.files);
});

function handleFiles(files) {
    selectedFiles = Array.from(files);
    displayPreviews();
}

function displayPreviews() {
    const preview = document.getElementById('filePreview');
    preview.innerHTML = '';
    
    selectedFiles.forEach((file, index) => {
        const reader = new FileReader();
        
        reader.onload = (e) => {
            const div = document.createElement('div');
            div.className = 'file-item';
            
            if(file.type.startsWith('image/')) {
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-file" onclick="removeFile(${index})">×</button>
                `;
            } else {
                div.innerHTML = `
                    <div style="height: 150px; display: flex; align-items: center; justify-content: center; background: rgba(66, 103, 245, 0.1); font-size: 3rem;">
                        🎥
                    </div>
                    <button type="button" class="remove-file" onclick="removeFile(${index})">×</button>
                `;
            }
            
            preview.appendChild(div);
        };
        
        reader.readAsDataURL(file);
    });
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    displayPreviews();
    
    // Update file input
    const dt = new DataTransfer();
    selectedFiles.forEach(file => dt.items.add(file));
    document.getElementById('fileInput').files = dt.files;
}

function setPrice(price) {
    document.getElementById('priceInput').value = price;
    if(price === 0) {
        document.getElementById('isFree').checked = true;
    }
}

function toggleFree() {
    if(document.getElementById('isFree').checked) {
        document.getElementById('priceInput').value = 0;
        document.getElementById('priceInput').disabled = true;
    } else {
        document.getElementById('priceInput').disabled = false;
        document.getElementById('priceInput').value = 50;
    }
}
</script>

<?php include 'views/footer.php'; ?>