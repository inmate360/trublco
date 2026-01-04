<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Listing.php';
require_once 'classes/LocationService.php';
require_once 'classes/ImageUpload.php';
require_once 'classes/CSRF.php';
require_once 'includes/maintenance_check.php';
require_once 'includes/profile_required.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$database = new Database();
$db = $database->getConnection();

if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

requireCompleteProfile($db, $_SESSION['user_id']);


$listing = new Listing($db);
$locationService = new LocationService($db);
$imageUpload = new ImageUpload($db);

$error = '';
$success = '';

// Get categories
$categories = $listing->getCategories();

// Get states and cities
$query = "SELECT s.id as state_id, s.name as state_name, s.abbreviation,
          c.id as city_id, c.name as city_name, c.slug as city_slug
          FROM states s
          LEFT JOIN cities c ON s.id = c.state_id
          ORDER BY s.name ASC, c.name ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$locations = $stmt->fetchAll();

// Organize by state
$states_cities = [];
foreach($locations as $loc) {
    if(!isset($states_cities[$loc['state_id']])) {
        $states_cities[$loc['state_id']] = [
            'name' => $loc['state_name'],
            'abbreviation' => $loc['abbreviation'],
            'cities' => []
        ];
    }
    if($loc['city_id']) {
        $states_cities[$loc['state_id']]['cities'][] = [
            'id' => $loc['city_id'],
            'name' => $loc['city_name'],
            'slug' => $loc['city_slug']
        ];
    }
}

// Get user's current location
$query = "SELECT current_latitude, current_longitude FROM users WHERE id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user_location = $stmt->fetch();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $category_id = (int)$_POST['category_id'];
        $city_id = (int)$_POST['city_id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $neighborhood = trim($_POST['neighborhood'] ?? '');
        $zip_code = trim($_POST['zip_code'] ?? '');
        $use_current_location = isset($_POST['use_current_location']);
        
        // Validation
        if(empty($category_id) || empty($city_id) || empty($title) || empty($description)) {
            $error = 'All required fields must be filled';
        } elseif(strlen($title) < 10 || strlen($title) > 100) {
            $error = 'Title must be between 10 and 100 characters';
        } elseif(strlen($description) < 50) {
            $error = 'Description must be at least 50 characters';
        } else {
            // Use current location if requested
            if($use_current_location && $user_location && $user_location['current_latitude']) {
                $latitude = $user_location['current_latitude'];
                $longitude = $user_location['current_longitude'];
                
                // Get neighborhood from coordinates
                $location_info = $locationService->reverseGeocode($latitude, $longitude);
                if($location_info['success']) {
                    if(empty($neighborhood)) {
                        $neighborhood = $location_info['neighborhood'] ?? '';
                    }
                    if(empty($zip_code)) {
                        $zip_code = $location_info['zip_code'] ?? '';
                    }
                }
            }
            
            // Handle photo upload
            $photo_url = null;
            if(isset($_FILES['photo']) && $_FILES['photo']['size'] > 0) {
                $upload_result = $imageUpload->upload($_FILES['photo'], 'listing');
                if($upload_result['success']) {
                    $photo_url = $upload_result['path'];
                } else {
                    $error = $upload_result['message'];
                }
            }
            
            if(empty($error)) {
                // Create listing data array
                $listing_data = [
                    'user_id' => $_SESSION['user_id'],
                    'city_id' => $city_id,
                    'category_id' => $category_id,
                    'title' => $title,
                    'description' => $description,
                    'photo_url' => $photo_url,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'neighborhood' => $neighborhood,
                    'zip_code' => $zip_code,
                    'status' => 'active' // or 'pending' if you have moderation
                ];
                
                $result = $listing->create($listing_data);
                
                if($result['success']) {
                    CSRF::destroyToken();
                    $_SESSION['success'] = 'Listing created successfully!';
                    header('Location: listing.php?id=' . $result['listing_id']);
                    exit();
                } else {
                    $error = $result['error'] ?? 'Failed to create listing. Please try again.';
                }
            }
        }
    }
}

$csrf_token = CSRF::getToken();

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">

<style>
.create-listing-container {
    max-width: 800px;
    margin: 2rem auto;
    padding: 0 20px;
}

.form-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
}

.form-section {
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--border-color);
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.form-section h3 {
    color: var(--primary-blue);
    margin-bottom: 1rem;
}

.char-counter {
    text-align: right;
    font-size: 0.85rem;
    color: var(--text-gray);
    margin-top: 0.25rem;
}

.location-info {
    background: rgba(66, 103, 245, 0.1);
    border: 2px solid var(--primary-blue);
    border-radius: 12px;
    padding: 1rem;
    margin-top: 1rem;
}

.photo-preview {
    max-width: 300px;
    max-height: 300px;
    margin-top: 1rem;
    border-radius: 12px;
    display: none;
}

.location-options {
    background: rgba(66, 103, 245, 0.05);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 1rem;
}

.location-btn-group {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

@media (max-width: 768px) {
    .location-btn-group {
        flex-direction: column;
    }
}
</style>

<div class="page-content">
    <div class="create-listing-container">
        <h1 style="margin-bottom: 0.5rem;">📝 Create New Listing</h1>
        <p style="color: var(--text-gray); margin-bottom: 2rem;">
            Post your personal ad and connect with people in your area
        </p>

        <?php if($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" action="" enctype="multipart/form-data" id="createListingForm">
                <?php echo CSRF::getHiddenInput(); ?>
                
                <!-- Basic Information -->
                <div class="form-section">
                    <h3>📋 Basic Information</h3>
                    
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category_id" required>
                            <option value="">Select a category...</option>
                            <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Title * (10-100 characters)</label>
                        <input type="text" 
                               name="title" 
                               required 
                               minlength="10" 
                               maxlength="100"
                               placeholder="Create an attention-grabbing title..."
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                               oninput="updateCharCount('title', 100)">
                        <div class="char-counter" id="titleCounter">0 / 100</div>
                    </div>

                    <div class="form-group">
                        <label>Description * (minimum 50 characters)</label>
                        <textarea name="description" 
                                  required 
                                  minlength="50"
                                  rows="8"
                                  placeholder="Describe what you're looking for. Be specific and genuine..."
                                  oninput="updateCharCount('description', null)"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <div class="char-counter" id="descriptionCounter">0 characters</div>
                    </div>
                </div>

                <!-- Location -->
                <div class="form-section">
                    <h3>📍 Location</h3>
                    
                    <div class="form-group">
                        <label>City *</label>
                        <select name="city_id" required id="citySelect">
                            <option value="">Select a city...</option>
                            <?php foreach($states_cities as $state): ?>
                            <optgroup label="<?php echo htmlspecialchars($state['name']); ?>">
                                <?php foreach($state['cities'] as $city): ?>
                                <option value="<?php echo $city['id']; ?>"
                                        <?php echo (isset($_POST['city_id']) && $_POST['city_id'] == $city['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="location-options">
                        <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-white); margin-bottom: 1rem;">
                            <input type="checkbox" 
                                   name="use_current_location" 
                                   id="useCurrentLocation"
                                   <?php echo $user_location && $user_location['current_latitude'] ? '' : 'disabled'; ?>
                                   onchange="toggleLocationInputs(this.checked)">
                            Use my current location
                            <?php if(!$user_location || !$user_location['current_latitude']): ?>
                            <span style="color: var(--warning-orange); font-size: 0.85rem;">(Enable location first)</span>
                            <?php endif; ?>
                        </label>

                        <div class="location-btn-group">
                            <button type="button" 
                                    class="btn-secondary" 
                                    onclick="detectLocation()"
                                    <?php echo $user_location && $user_location['current_latitude'] ? '' : 'disabled'; ?>>
                                📍 Detect My Location
                            </button>
                            <button type="button" class="btn-secondary" onclick="showManualLocation()">
                                ✏️ Enter Manually
                            </button>
                        </div>

                        <div id="manualLocationInputs" style="display: none; margin-top: 1rem;">
                            <div class="form-group">
                                <label>Neighborhood (Optional)</label>
                                <input type="text" 
                                       name="neighborhood" 
                                       placeholder="e.g., Downtown, Mission District"
                                       value="<?php echo isset($_POST['neighborhood']) ? htmlspecialchars($_POST['neighborhood']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label>Zip Code (Optional)</label>
                                <input type="text" 
                                       name="zip_code" 
                                       placeholder="e.g., 94103"
                                       pattern="[0-9]{5}"
                                       value="<?php echo isset($_POST['zip_code']) ? htmlspecialchars($_POST['zip_code']) : ''; ?>">
                            </div>
                        </div>

                        <input type="hidden" name="latitude" id="latitude">
                        <input type="hidden" name="longitude" id="longitude">

                        <div id="locationDetected" class="location-info" style="display: none;">
                            <strong style="color: var(--primary-blue);">✓ Location Detected</strong>
                            <p style="color: var(--text-gray); margin-top: 0.5rem; font-size: 0.9rem;" id="locationDetails"></p>
                        </div>
                    </div>
                </div>

                <!-- Photo Upload -->
                <div class="form-section">
                    <h3>📷 Photo (Optional)</h3>
                    
                    <div class="form-group">
                        <label>Upload Photo</label>
                        <input type="file" 
                               name="photo" 
                               accept="image/*"
                               onchange="previewPhoto(this)">
                        <small style="color: var(--text-gray); display: block; margin-top: 0.5rem;">
                            Supported formats: JPG, PNG, GIF, WebP (Max 5MB)
                        </small>
                    </div>

                    <img id="photoPreview" class="photo-preview" alt="Photo preview">
                </div>

                <!-- Guidelines -->
                <div class="alert alert-warning">
                    <strong>⚠️ Important Guidelines</strong>
                    <ul style="margin: 0.5rem 0 0 1.5rem; line-height: 1.8;">
                        <li>Be respectful and genuine in your listing</li>
                        <li>No prostitution, escort services, or illegal activities</li>
                        <li>Must be 18+ to post</li>
                        <li>Keep content appropriate and consensual</li>
                        <li>No spam or duplicate postings</li>
                    </ul>
                </div>

                <button type="submit" class="btn-primary btn-block" style="margin-top: 2rem;">
                    ✨ Create Listing
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Character counters
function updateCharCount(field, max) {
    const input = document.querySelector(`[name="${field}"]`);
    const counter = document.getElementById(`${field}Counter`);
    const length = input.value.length;
    
    if(max) {
        counter.textContent = `${length} / ${max}`;
        if(length >= max) {
            counter.style.color = 'var(--danger-red)';
        } else {
            counter.style.color = 'var(--text-gray)';
        }
    } else {
        counter.textContent = `${length} characters`;
        if(length < 50) {
            counter.style.color = 'var(--warning-orange)';
        } else {
            counter.style.color = 'var(--success-green)';
        }
    }
}

// Initialize counters
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.querySelector('[name="title"]');
    const descInput = document.querySelector('[name="description"]');
    
    if(titleInput && titleInput.value) {
        updateCharCount('title', 100);
    }
    if(descInput && descInput.value) {
        updateCharCount('description', null);
    }
});

// Photo preview
function previewPhoto(input) {
    const preview = document.getElementById('photoPreview');
    
    if(input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Toggle location inputs
function toggleLocationInputs(useCurrentLocation) {
    const manualInputs = document.getElementById('manualLocationInputs');
    
    if(useCurrentLocation) {
        manualInputs.style.display = 'none';
        detectLocation();
    } else {
        manualInputs.style.display = 'block';
    }
}

// Show manual location inputs
function showManualLocation() {
    document.getElementById('useCurrentLocation').checked = false;
    document.getElementById('manualLocationInputs').style.display = 'block';
}

// Detect location
function detectLocation() {
    if(!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }
    
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = '📍 Detecting...';
    
    navigator.geolocation.getCurrentPosition(
        (position) => {
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
            
            // Reverse geocode to get address
            fetch(`/api/location.php?action=reverse_geocode&latitude=${position.coords.latitude}&longitude=${position.coords.longitude}`)
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        const locationDetected = document.getElementById('locationDetected');
                        const locationDetails = document.getElementById('locationDetails');
                        
                        locationDetails.textContent = data.display_name;
                        locationDetected.style.display = 'block';
                        
                        // Auto-fill neighborhood and zip if available
                        if(data.address.neighbourhood) {
                            document.querySelector('[name="neighborhood"]').value = data.address.neighbourhood;
                        }
                        if(data.address.postcode) {
                            document.querySelector('[name="zip_code"]').value = data.address.postcode;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error reverse geocoding:', error);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.textContent = '📍 Detect My Location';
                });
        },
        (error) => {
            console.error('Geolocation error:', error);
            alert('Unable to get your location. Please enable location access or enter manually.');
            btn.disabled = false;
            btn.textContent = '📍 Detect My Location';
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}
</script>

<?php include 'views/footer.php'; ?>