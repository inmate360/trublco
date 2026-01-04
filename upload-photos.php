<?php
session_start();
require_once 'config/database.php';
require_once 'classes/UserProfile.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$userProfile = new UserProfile($db);

$error = '';
$success = '';

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_photos'])) {
    if (isset($_POST['photos_data']) && is_array($_POST['photos_data'])) {
        $uploaded_count = 0;
        $failed_count = 0;
        
        foreach ($_POST['photos_data'] as $photo_data) {
            $result = $userProfile->uploadGalleryPhoto($_SESSION['user_id'], $photo_data);
            if ($result === true) {
                $uploaded_count++;
            } else {
                $failed_count++;
            }
        }
        
        if ($uploaded_count > 0) {
            $success = "Successfully uploaded {$uploaded_count} photo(s)";
        }
        if ($failed_count > 0) {
            $error = "Failed to upload {$failed_count} photo(s)";
        }
    } else {
        $error = 'No photos to upload';
    }
}

// Handle photo deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_photo'])) {
    $photo_id = $_POST['photo_id'];
    if ($userProfile->deleteGalleryPhoto($_SESSION['user_id'], $photo_id)) {
        $success = 'Photo deleted successfully';
    } else {
        $error = 'Failed to delete photo';
    }
}

// Handle set primary photo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['set_primary'])) {
    $photo_id = $_POST['photo_id'];
    if ($userProfile->setPrimaryPhoto($_SESSION['user_id'], $photo_id)) {
        $success = 'Primary photo updated';
    } else {
        $error = 'Failed to update primary photo';
    }
}

// Get user's existing photos
$query = "SELECT * FROM user_photos WHERE user_id = :user_id ORDER BY is_primary DESC, display_order ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$existing_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'views/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Pacifico&display=swap');

.upload-zone {
  border: 3px dashed var(--gh-border);
  border-radius: 12px;
  padding: 3rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s;
  background: var(--gh-panel);
}

.upload-zone:hover {
  border-color: var(--gh-accent);
  background: var(--gh-bg);
}

.upload-zone.dragover {
  border-color: var(--gh-accent);
  background: var(--gh-accent);
  background-opacity: 0.1;
}

.photo-item {
  position: relative;
  aspect-ratio: 1;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.3s;
}

.photo-item:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.3);
}

.photo-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 50%);
  opacity: 0;
  transition: opacity 0.3s;
  display: flex;
  align-items: flex-end;
  padding: 1rem;
}

.photo-item:hover .photo-overlay {
  opacity: 1;
}
</style>

<div class="min-h-screen bg-gh-bg py-8">
  <div class="mx-auto max-w-6xl px-4">
    
    <!-- Header -->
    <div class="mb-8">
      <div class="mb-3">
        <a href="index.php" class="inline-block">
          <h1 class="bg-gradient-to-r from-gh-accent via-gh-success to-gh-accent bg-clip-text text-4xl font-bold text-transparent" style="font-family: 'Pacifico', cursive;">
            Lustifieds
          </h1>
        </a>
      </div>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="mb-2 text-2xl font-bold text-gh-fg">Manage Photos</h2>
          <p class="text-gh-muted">Upload and manage your profile gallery</p>
        </div>
        <a href="profile.php" class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-4 py-2 font-semibold text-gh-fg transition-all hover:border-gh-accent">
          <i class="bi bi-arrow-left"></i>
          Back to Profile
        </a>
      </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if($success): ?>
    <div class="mb-6 rounded-lg border border-green-500 bg-green-500/10 p-4">
      <div class="flex items-center gap-2 text-green-500">
        <i class="bi bi-check-circle-fill text-xl"></i>
        <span class="font-semibold"><?php echo htmlspecialchars($success); ?></span>
      </div>
    </div>
    <?php endif; ?>

    <?php if($error): ?>
    <div class="mb-6 rounded-lg border border-red-500 bg-red-500/10 p-4">
      <div class="flex items-center gap-2 text-red-500">
        <i class="bi bi-exclamation-triangle-fill text-xl"></i>
        <span class="font-semibold"><?php echo htmlspecialchars($error); ?></span>
      </div>
    </div>
    <?php endif; ?>

    <!-- Upload Section -->
    <div class="mb-8 rounded-xl border border-gh-border bg-gh-panel p-6">
      <h3 class="mb-4 flex items-center gap-2 text-lg font-bold text-gh-fg">
        <i class="bi bi-cloud-upload-fill text-pink-500"></i>
        Upload New Photos
      </h3>
      
      <form method="POST" id="uploadForm">
        <div class="upload-zone" id="uploadZone" onclick="document.getElementById('photoInput').click()">
          <i class="bi bi-images mb-3 text-6xl text-gh-muted"></i>
          <h4 class="mb-2 text-xl font-bold text-gh-fg">Click or drag photos here</h4>
          <p class="text-sm text-gh-muted">Upload up to 10 photos at once (max 5MB each)</p>
          <p class="mt-2 text-xs text-gh-muted">Supported formats: JPEG, PNG, WebP</p>
        </div>
        
        <input type="file" id="photoInput" accept="image/*" multiple class="hidden">
        
        <!-- Preview Area -->
        <div id="previewArea" class="mt-6 hidden">
          <h4 class="mb-3 font-bold text-gh-fg">Selected Photos</h4>
          <div id="previewGrid" class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"></div>
          
          <div class="mt-6 flex gap-3">
            <button type="button" onclick="uploadPhotos()" 
                    class="flex-1 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-3 font-bold text-white shadow-lg transition-all hover:brightness-110">
              <i class="bi bi-cloud-upload-fill mr-2"></i>
              Upload Photos
            </button>
            <button type="button" onclick="clearSelection()" 
                    class="rounded-lg border border-gh-border bg-gh-panel px-6 py-3 font-bold text-gh-fg transition-all hover:border-red-500 hover:text-red-500">
              Cancel
            </button>
          </div>
        </div>
        
        <input type="hidden" name="upload_photos" value="1">
        <div id="photosDataContainer"></div>
      </form>
    </div>

    <!-- Existing Photos -->
    <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
      <h3 class="mb-4 flex items-center gap-2 text-lg font-bold text-gh-fg">
        <i class="bi bi-images text-purple-500"></i>
        My Photos (<?php echo count($existing_photos); ?>)
      </h3>
      
      <?php if(count($existing_photos) > 0): ?>
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
        <?php foreach($existing_photos as $photo): ?>
        <div class="photo-item group border border-gh-border bg-gh-bg">
          <img src="<?php echo htmlspecialchars($photo['file_path']); ?>" 
               alt="Gallery photo"
               class="h-full w-full object-cover">
          
          <div class="photo-overlay">
            <div class="flex w-full items-end justify-between">
              <?php if($photo['is_primary']): ?>
                <span class="rounded-full bg-gh-accent px-3 py-1 text-xs font-bold text-white">
                  <i class="bi bi-star-fill"></i>
                  Primary
                </span>
              <?php else: ?>
                <form method="POST" class="inline">
                  <input type="hidden" name="photo_id" value="<?php echo $photo['id']; ?>">
                  <button type="submit" name="set_primary" 
                          class="rounded-full border border-white/50 bg-white/10 px-3 py-1 text-xs font-bold text-white backdrop-blur transition-all hover:bg-white/20">
                    <i class="bi bi-star"></i>
                    Set Primary
                  </button>
                </form>
              <?php endif; ?>
              
              <form method="POST" class="inline" onsubmit="return confirm('Delete this photo?')">
                <input type="hidden" name="photo_id" value="<?php echo $photo['id']; ?>">
                <button type="submit" name="delete_photo" 
                        class="rounded-full bg-red-500 p-2 text-white transition-all hover:bg-red-600">
                  <i class="bi bi-trash-fill"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="rounded-lg border border-gh-border bg-gh-bg p-12 text-center">
        <i class="bi bi-images mb-3 text-6xl text-gh-muted opacity-20"></i>
        <h4 class="mb-2 text-lg font-bold text-gh-fg">No Photos Yet</h4>
        <p class="text-sm text-gh-muted">Upload your first photo to get started</p>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
let selectedFiles = [];

// File input change handler
document.getElementById('photoInput').addEventListener('change', function(e) {
  handleFiles(e.target.files);
});

// Drag and drop handlers
const uploadZone = document.getElementById('uploadZone');

uploadZone.addEventListener('dragover', function(e) {
  e.preventDefault();
  e.stopPropagation();
  this.classList.add('dragover');
});

uploadZone.addEventListener('dragleave', function(e) {
  e.preventDefault();
  e.stopPropagation();
  this.classList.remove('dragover');
});

uploadZone.addEventListener('drop', function(e) {
  e.preventDefault();
  e.stopPropagation();
  this.classList.remove('dragover');
  handleFiles(e.dataTransfer.files);
});

function handleFiles(files) {
  selectedFiles = Array.from(files);
  displayPreviews();
}

function displayPreviews() {
  const previewArea = document.getElementById('previewArea');
  const previewGrid = document.getElementById('previewGrid');
  
  if(selectedFiles.length === 0) {
    previewArea.classList.add('hidden');
    return;
  }
  
  previewArea.classList.remove('hidden');
  previewGrid.innerHTML = '';
  
  selectedFiles.forEach((file, index) => {
    const reader = new FileReader();
    reader.onload = function(e) {
      const div = document.createElement('div');
      div.className = 'relative aspect-square overflow-hidden rounded-lg border border-gh-border';
      div.innerHTML = `
        <img src="${e.target.result}" class="h-full w-full object-cover">
        <button type="button" onclick="removeFile(${index})" 
                class="absolute right-2 top-2 rounded-full bg-red-500 p-2 text-white hover:bg-red-600">
          <i class="bi bi-x-lg"></i>
        </button>
      `;
      previewGrid.appendChild(div);
    };
    reader.readAsDataURL(file);
  });
}

function removeFile(index) {
  selectedFiles.splice(index, 1);
  displayPreviews();
}

function clearSelection() {
  selectedFiles = [];
  document.getElementById('photoInput').value = '';
  displayPreviews();
}

function uploadPhotos() {
  if(selectedFiles.length === 0) {
    alert('Please select photos to upload');
    return;
  }
  
  const photosDataContainer = document.getElementById('photosDataContainer');
  photosDataContainer.innerHTML = '';
  
  let uploadedCount = 0;
  
  selectedFiles.forEach((file, index) => {
    const reader = new FileReader();
    reader.onload = function(e) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = `photos_data[${index}]`;
      input.value = e.target.result;
      photosDataContainer.appendChild(input);
      
      uploadedCount++;
      if(uploadedCount === selectedFiles.length) {
        document.getElementById('uploadForm').submit();
      }
    };
    reader.readAsDataURL(file);
  });
}
</script>

<?php include 'views/footer.php'; ?>
