<?php
session_start();
require_once 'config/database.php';
require_once 'classes/UserProfile.php';
require_once 'classes/Location.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$userProfile = new UserProfile($db);
$location = new Location($db);

$profile = $userProfile->getProfile($_SESSION['user_id']);
$userLocation = $userProfile->getUserLocation($_SESSION['user_id']);
$states = $location->getAllStates();

$error = '';
$success = '';

// Handle avatar upload with cropped image
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_cropped_avatar'])) {
    if (isset($_POST['cropped_image_data'])) {
        $imageData = $_POST['cropped_image_data'];
        $imageData = str_replace('data:image/png;base64,', '', $imageData);
        $imageData = str_replace(' ', '+', $imageData);
        $decodedImage = base64_decode($imageData);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'avatar_');
        file_put_contents($tempFile, $decodedImage);
        
        $fileArray = [
            'name' => 'avatar_' . time() . '.png',
            'type' => 'image/png',
            'tmp_name' => $tempFile,
            'error' => 0,
            'size' => strlen($decodedImage)
        ];
        
        $result = $userProfile->uploadAvatar($_SESSION['user_id'], $fileArray);
        unlink($tempFile);
        
        if($result === true) {
            $success = 'Profile photo updated successfully!';
        } else {
            $error = $result;
        }
    }
    $profile = $userProfile->getProfile($_SESSION['user_id']);
}

// Handle avatar removal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_avatar'])) {
    $userProfile->removeAvatar($_SESSION['user_id']);
    $success = 'Profile photo removed.';
    $profile = $userProfile->getProfile($_SESSION['user_id']);
}

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['upload_cropped_avatar']) && !isset($_POST['remove_avatar'])) {
    $data = [
        'bio' => $_POST['bio'] ?? '',
        'height' => !empty($_POST['height']) ? $_POST['height'] : null,
        'body_type' => $_POST['body_type'] ?? null,
        'ethnicity' => $_POST['ethnicity'] ?? '',
        'relationship_status' => $_POST['relationship_status'] ?? null,
        'looking_for' => isset($_POST['looking_for']) ? $_POST['looking_for'] : [],
        'interests' => isset($_POST['interests']) ? explode(',', $_POST['interests']) : [],
        'occupation' => $_POST['occupation'] ?? '',
        'education' => $_POST['education'] ?? null,
        'smoking' => $_POST['smoking'] ?? null,
        'drinking' => $_POST['drinking'] ?? null,
        'has_kids' => isset($_POST['has_kids']) ? (bool)$_POST['has_kids'] : false,
        'wants_kids' => $_POST['wants_kids'] ?? null,
        'languages' => isset($_POST['languages']) ? explode(',', $_POST['languages']) : [],
        'display_distance' => isset($_POST['display_distance']) ? true : false,
        'show_age' => isset($_POST['show_age']) ? true : false,
        'show_online_status' => isset($_POST['show_online_status']) ? true : false
    ];
    
    if($userProfile->saveProfile($_SESSION['user_id'], $data)) {
        if(!empty($_POST['city_id'])) {
            $city = $location->getCityById($_POST['city_id']);
            if($city) {
                $userProfile->saveLocation(
                    $_SESSION['user_id'],
                    $_POST['city_id'],
                    null,
                    null,
                    $_POST['postal_code'] ?? null,
                    $_POST['max_distance'] ?? 50
                );
            }
        }
        $success = 'Profile updated successfully!';
        $profile = $userProfile->getProfile($_SESSION['user_id']);
    } else {
        $error = 'Failed to update profile';
    }
}

include 'views/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Pacifico&display=swap');

.avatar-upload-zone {
  position: relative;
  width: 200px;
  height: 200px;
  border: 3px dashed var(--gh-border);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s;
  margin: 0 auto;
  background: var(--gh-panel);
}

.avatar-upload-zone:hover {
  border-color: var(--gh-accent);
  background: var(--gh-bg);
}

.avatar-upload-zone img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

.avatar-upload-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.7);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.3s;
}

.avatar-upload-zone:hover .avatar-upload-overlay {
  opacity: 1;
}
</style>

<div class="min-h-screen bg-gh-bg py-8">
  <div class="mx-auto max-w-4xl px-4">
    
    <!-- Header -->
    <div class="mb-8">
      <div class="mb-3">
        <a href="index.php" class="inline-block">
          <h1 class="bg-gradient-to-r from-gh-accent via-gh-success to-gh-accent bg-clip-text text-4xl font-bold text-transparent" style="font-family: 'Pacifico', cursive;">
            trubl
          </h1>
        </a>
      </div>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="mb-2 text-2xl font-bold text-gh-fg">Edit Profile</h2>
          <p class="text-gh-muted">Keep your profile up to date to get better matches</p>
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

    <form method="POST" id="profileForm" class="space-y-6">
      
      <!-- Profile Photo Section -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <h3 class="mb-4 flex items-center gap-2 text-lg font-bold text-gh-fg">
          <i class="bi bi-camera-fill text-pink-500"></i>
          Profile Photo
        </h3>
        
        <div class="flex flex-col items-center gap-4">
          <div class="avatar-upload-zone" onclick="document.getElementById('avatarInput').click()">
            <img id="avatarPreview" 
                 src="<?php echo htmlspecialchars($profile['avatar'] ?? '/assets/images/default-avatar.png'); ?>" 
                 alt="Avatar">
            <div class="avatar-upload-overlay">
              <div class="text-center text-white">
                <i class="bi bi-camera-fill text-3xl"></i>
                <p class="mt-2 text-sm font-semibold">Change Photo</p>
              </div>
            </div>
          </div>
          
          <input type="file" id="avatarInput" accept="image/*" class="hidden" onchange="previewAvatar(this)">
          
          <div class="flex gap-2">
            <button type="button" onclick="document.getElementById('avatarInput').click()" 
                    class="rounded-lg border border-gh-border bg-gh-bg px-4 py-2 text-sm font-semibold text-gh-fg transition-all hover:border-gh-accent">
              <i class="bi bi-upload"></i>
              Upload New
            </button>
            <?php if(!empty($profile['avatar'])): ?>
            <button type="submit" name="remove_avatar" 
                    class="rounded-lg border border-red-500 bg-red-500/10 px-4 py-2 text-sm font-semibold text-red-500 transition-all hover:bg-red-500/20">
              <i class="bi bi-trash"></i>
              Remove
            </button>
            <?php endif; ?>
          </div>
          <p class="text-xs text-gh-muted">Click or drag and drop to upload<br>JPEG or PNG (max 2MB)</p>
        </div>
      </div>

      <!-- Basic Information -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <h3 class="mb-4 flex items-center gap-2 text-lg font-bold text-gh-fg">
          <i class="bi bi-person-fill text-purple-500"></i>
          Basic Information
        </h3>
        
        <div class="space-y-4">
          <!-- Bio -->
          <div>
            <label class="mb-2 block font-semibold text-gh-fg">
              About Me
            </label>
            <textarea name="bio" rows="4" 
                      class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-gh-fg focus:border-gh-accent focus:outline-none"
                      placeholder="Tell others about yourself..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
          </div>

          <!-- Height -->
          <div>
            <label class="mb-2 block font-semibold text-gh-fg">
              <i class="bi bi-rulers mr-1"></i>
              Height (cm)
            </label>
            <input type="number" name="height" 
                   value="<?php echo htmlspecialchars($profile['height'] ?? ''); ?>"
                   class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-gh-fg focus:border-gh-accent focus:outline-none"
                   placeholder="170">
          </div>

          <!-- Body Type -->
          <div>
            <label class="mb-2 block font-semibold text-gh-fg">
              <i class="bi bi-person mr-1"></i>
              Body Type
            </label>
            <select name="body_type" 
                    class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-gh-fg focus:border-gh-accent focus:outline-none">
              <option value="">Select body type</option>
              <option value="Slim" <?php echo ($profile['body_type'] ?? '') == 'Slim' ? 'selected' : ''; ?>>Slim</option>
              <option value="Athletic" <?php echo ($profile['body_type'] ?? '') == 'Athletic' ? 'selected' : ''; ?>>Athletic</option>
              <option value="Average" <?php echo ($profile['body_type'] ?? '') == 'Average' ? 'selected' : ''; ?>>Average</option>
              <option value="Curvy" <?php echo ($profile['body_type'] ?? '') == 'Curvy' ? 'selected' : ''; ?>>Curvy</option>
              <option value="Plus Size" <?php echo ($profile['body_type'] ?? '') == 'Plus Size' ? 'selected' : ''; ?>>Plus Size</option>
            </select>
          </div>

          <!-- Ethnicity -->
          <div>
            <label class="mb-2 block font-semibold text-gh-fg">
              <i class="bi bi-globe mr-1"></i>
              Ethnicity
            </label>
            <input type="text" name="ethnicity" 
                   value="<?php echo htmlspecialchars($profile['ethnicity'] ?? ''); ?>"
                   class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-gh-fg focus:border-gh-accent focus:outline-none"
                   placeholder="e.g., Asian, Hispanic, etc.">
          </div>

          <!-- Relationship Status -->
          <div>
            <label class="mb-2 block font-semibold text-gh-fg">
              <i class="bi bi-heart mr-1"></i>
              Relationship Status
            </label>
            <select name="relationship_status" 
                    class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-gh-fg focus:border-gh-accent focus:outline-none">
              <option value="">Select status</option>
              <option value="Single" <?php echo ($profile['relationship_status'] ?? '') == 'Single' ? 'selected' : ''; ?>>Single</option>
              <option value="In a Relationship" <?php echo ($profile['relationship_status'] ?? '') == 'In a Relationship' ? 'selected' : ''; ?>>In a Relationship</option>
              <option value="Married" <?php echo ($profile['relationship_status'] ?? '') == 'Married' ? 'selected' : ''; ?>>Married</option>
              <option value="Divorced" <?php echo ($profile['relationship_status'] ?? '') == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
              <option value="Widowed" <?php echo ($profile['relationship_status'] ?? '') == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
              <option value="It's Complicated" <?php echo ($profile['relationship_status'] ?? '') == "It's Complicated" ? 'selected' : ''; ?>>It's Complicated</option>
            </select>
          </div>

          <!-- Occupation -->
          <div>
            <label class="mb-2 block font-semibold text-gh-fg">
              <i class="bi bi-briefcase mr-1"></i>
              Occupation
            </label>
            <input type="text" name="occupation" 
                   value="<?php echo htmlspecialchars($profile['occupation'] ?? ''); ?>"
                   class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-gh-fg focus:border-gh-accent focus:outline-none"
                   placeholder="Your job title or profession">
          </div>

          <!-- Education -->
          <div>
            <label class="mb-2 block font-semibold text-gh-fg">
              <i class="bi bi-mortarboard mr-1"></i>
              Education
            </label>
            <select name="education" 
                    class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-gh-fg focus:border-gh-accent focus:outline-none">
              <option value="">Select education level</option>
              <option value="High School" <?php echo ($profile['education'] ?? '') == 'High School' ? 'selected' : ''; ?>>High School</option>
              <option value="Some College" <?php echo ($profile['education'] ?? '') == 'Some College' ? 'selected' : ''; ?>>Some College</option>
              <option value="Associate Degree" <?php echo ($profile['education'] ?? '') == 'Associate Degree' ? 'selected' : ''; ?>>Associate Degree</option>
              <option value="Bachelor's Degree" <?php echo ($profile['education'] ?? '') == "Bachelor's Degree" ? 'selected' : ''; ?>>Bachelor's Degree</option>
              <option value="Master's Degree" <?php echo ($profile['education'] ?? '') == "Master's Degree" ? 'selected' : ''; ?>>Master's Degree</option>
              <option value="Doctorate" <?php echo ($profile['education'] ?? '') == 'Doctorate' ? 'selected' : ''; ?>>Doctorate</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Lifestyle -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <h3 class="mb-4 flex items-center gap-2 text-lg font-bold text-gh-fg">
          <i class="bi bi-star-fill text-yellow-500"></i>
          Lifestyle
        </h3>
        
        <div class="grid gap-4 sm:grid-cols-2">
          <!-- Smoking -->
          <div>
            <label class="mb-2 block font-semibold text-gh-fg">Smoking</label>
            <select name="smoking" 
                    class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-gh-fg focus:border-gh-accent focus:outline-none">
              <option value="">Prefer not to say</option>
              <option value="Never" <?php echo ($profile['smoking'] ?? '') == 'Never' ? 'selected' : ''; ?>>Never</option>
              <option value="Occasionally" <?php echo ($profile['smoking'] ?? '') == 'Occasionally' ? 'selected' : ''; ?>>Occasionally</option>
              <option value="Regularly" <?php echo ($profile['smoking'] ?? '') == 'Regularly' ? 'selected' : ''; ?>>Regularly</option>
              <option value="Trying to Quit" <?php echo ($profile['smoking'] ?? '') == 'Trying to Quit' ? 'selected' : ''; ?>>Trying to Quit</option>
            </select>
          </div>

          <!-- Drinking -->
          <div>
            <label class="mb-2 block font-semibold text-gh-fg">Drinking</label>
            <select name="drinking" 
                    class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-gh-fg focus:border-gh-accent focus:outline-none">
              <option value="">Prefer not to say</option>
              <option value="Never" <?php echo ($profile['drinking'] ?? '') == 'Never' ? 'selected' : ''; ?>>Never</option>
              <option value="Socially" <?php echo ($profile['drinking'] ?? '') == 'Socially' ? 'selected' : ''; ?>>Socially</option>
              <option value="Regularly" <?php echo ($profile['drinking'] ?? '') == 'Regularly' ? 'selected' : ''; ?>>Regularly</option>
            </select>
          </div>
        </div>

        <!-- Interests -->
        <div class="mt-4">
          <label class="mb-2 block font-semibold text-gh-fg">
            <i class="bi bi-tags-fill mr-1"></i>
            Interests (comma-separated)
          </label>
          <input type="text" name="interests" 
                 value="<?php echo htmlspecialchars(is_array($profile['interests'] ?? '') ? implode(', ', $profile['interests']) : ($profile['interests'] ?? '')); ?>"
                 class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-gh-fg focus:border-gh-accent focus:outline-none"
                 placeholder="e.g., Travel, Music, Sports, Cooking">
        </div>
      </div>

      <!-- Privacy Settings -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <h3 class="mb-4 flex items-center gap-2 text-lg font-bold text-gh-fg">
          <i class="bi bi-shield-lock-fill text-blue-500"></i>
          Privacy Settings
        </h3>
        
        <div class="space-y-3">
          <label class="flex cursor-pointer items-center justify-between rounded-lg border border-gh-border bg-gh-bg p-4 transition-all hover:border-gh-accent">
            <span class="flex items-center gap-2 font-semibold text-gh-fg">
              <i class="bi bi-eye"></i>
              Show my age on profile
            </span>
            <input type="checkbox" name="show_age" <?php echo ($profile['show_age'] ?? true) ? 'checked' : ''; ?>
                   class="h-5 w-5 rounded border-gh-border bg-gh-bg text-gh-accent focus:ring-2 focus:ring-gh-accent">
          </label>

          <label class="flex cursor-pointer items-center justify-between rounded-lg border border-gh-border bg-gh-bg p-4 transition-all hover:border-gh-accent">
            <span class="flex items-center gap-2 font-semibold text-gh-fg">
              <i class="bi bi-circle-fill"></i>
              Show online status
            </span>
            <input type="checkbox" name="show_online_status" <?php echo ($profile['show_online_status'] ?? true) ? 'checked' : ''; ?>
                   class="h-5 w-5 rounded border-gh-border bg-gh-bg text-gh-accent focus:ring-2 focus:ring-gh-accent">
          </label>

          <label class="flex cursor-pointer items-center justify-between rounded-lg border border-gh-border bg-gh-bg p-4 transition-all hover:border-gh-accent">
            <span class="flex items-center gap-2 font-semibold text-gh-fg">
              <i class="bi bi-geo-alt"></i>
              Display distance to others
            </span>
            <input type="checkbox" name="display_distance" <?php echo ($profile['display_distance'] ?? false) ? 'checked' : ''; ?>
                   class="h-5 w-5 rounded border-gh-border bg-gh-bg text-gh-accent focus:ring-2 focus:ring-gh-accent">
          </label>
        </div>
      </div>

      <!-- Submit Button -->
      <div class="flex gap-3">
        <button type="submit" 
                class="flex-1 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-8 py-4 text-lg font-bold text-white shadow-lg transition-all hover:brightness-110 hover:shadow-xl">
          <i class="bi bi-check-circle-fill mr-2"></i>
          Save Changes
        </button>
        <a href="profile.php" 
           class="rounded-lg border border-gh-border bg-gh-panel px-8 py-4 text-center font-bold text-gh-fg transition-all hover:border-gh-accent">
          Cancel
        </a>
      </div>
    </form>

  </div>
</div>

<script>
function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('avatarPreview').src = e.target.result;
    }
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php include 'views/footer.php'; ?>
