<?php
session_start();
require_once 'config/database.php';
require_once 'classes/CSRF.php';
require_once 'classes/ContentModerator.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Get current user data
$query = "SELECT * FROM users WHERE id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

$is_required = isset($_GET['required']);

// Calculate completion
$completion = 0;
if(!empty($user['age']) && $user['age'] >= 18) $completion += 25;
if(!empty($user['gender'])) $completion += 25;
if(!empty($user['location'])) $completion += 25;
if(!empty($user['bio']) && strlen($user['bio']) >= 20) $completion += 25;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $age = (int)$_POST['age'];
        $gender = $_POST['gender'];
        $location = trim($_POST['location']);
        $bio = trim($_POST['bio']);
        
        // Validation
        if($age < 18) {
            $error = 'You must be at least 18 years old';
        } elseif(empty($gender)) {
            $error = 'Please select your gender';
        } elseif(empty($location)) {
            $error = 'Please enter your location';
        } elseif(strlen($bio) < 20) {
            $error = 'Bio must be at least 20 characters';
        } else {
            try {
                $query = "UPDATE users SET 
                          age = :age,
                          gender = :gender,
                          location = :location,
                          bio = :bio
                          WHERE id = :user_id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':age', $age);
                $stmt->bindParam(':gender', $gender);
                $stmt->bindParam(':location', $location);
                $stmt->bindParam(':bio', $bio);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if($stmt->execute()) {
                    // AI Content Moderation
                    $moderator = new ContentModerator($db);
                    $moderationResult = $moderator->moderateText($bio, 'profile', $_SESSION['user_id'], $_SESSION['user_id']);
                    
                    if ($moderationResult['risk_level'] === 'high') {
                        header('Location: /profile.php?flagged=1');
                    } else {
                        header('Location: /choose-location.php?setup=complete');
                    }
                    exit();
                } else {
                    $error = 'Failed to update profile';
                }
            } catch(PDOException $e) {
                error_log("Profile setup error: " . $e->getMessage());
                $error = 'Database error occurred';
            }
        }
    }
}

$csrf_token = CSRF::getToken();

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/light-theme.css">

<style>
.setup-container {
    max-width: 700px;
    margin: 2rem auto;
    padding: 0 20px;
}

.setup-header {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    padding: 3rem 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    text-align: center;
    color: white;
}

.setup-header h1 {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

<?php if($is_required): ?>
.required-notice {
    background: rgba(239, 68, 68, 0.1);
    border: 2px solid var(--danger-red);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    text-align: center;
}
<?php endif; ?>

.completion-bar {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    height: 8px;
    margin-top: 1rem;
    overflow: hidden;
}

.completion-progress {
    background: linear-gradient(90deg, #10b981, #3b82f6);
    height: 100%;
    border-radius: 20px;
    transition: width 0.3s;
}

.setup-form {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
}

.form-step {
    margin-bottom: 2rem;
}

.step-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 1rem;
}

.step-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.gender-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.gender-option {
    position: relative;
}

.gender-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.gender-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1.5rem;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
}

.gender-label:hover {
    border-color: var(--primary-blue);
    background: rgba(66, 103, 245, 0.05);
}

.gender-option input:checked + .gender-label {
    border-color: var(--success-green);
    background: rgba(16, 185, 129, 0.1);
}

.gender-icon {
    font-size: 2.5rem;
}

.bio-counter {
    text-align: right;
    font-size: 0.85rem;
    color: var(--text-gray);
    margin-top: 0.5rem;
}

.help-text {
    font-size: 0.9rem;
    color: var(--text-gray);
    margin-top: 0.5rem;
}
</style>

<div class="page-content">
    <div class="setup-container">
        
        <div class="setup-header">
            <div style="font-size: 4rem; margin-bottom: 1rem;">👤</div>
            <h1>Complete Your Profile</h1>
            <p style="font-size: 1.1rem; opacity: 0.95;">
                Let's set up your profile to get started
            </p>
            <div class="completion-bar">
                <div class="completion-progress" style="width: <?php echo $completion; ?>%"></div>
            </div>
            <p style="margin-top: 0.5rem; font-size: 0.9rem; opacity: 0.9;">
                <?php echo $completion; ?>% Complete
            </p>
        </div>

        <?php if($is_required): ?>
        <div class="required-notice">
            <strong style="color: var(--danger-red); font-size: 1.1rem;">⚠️ Profile Setup Required</strong>
            <p style="margin: 0.5rem 0 0; color: var(--text-gray);">
                Please complete your profile to access all features including posting ads and messaging.
            </p>
        </div>
        <?php endif; ?>

        <?php if($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="setup-form">
            <form method="POST">
                <?php echo CSRF::getHiddenInput(); ?>
                
                <!-- Step 1: Age -->
                <div class="form-step">
                    <div class="step-label">
                        <span class="step-number">1</span>
                        <span>Your Age</span>
                    </div>
                    <div class="form-group">
                        <label>Age *</label>
                        <input type="number" 
                               name="age" 
                               min="18" 
                               max="99" 
                               value="<?php echo $user['age'] ?? ''; ?>"
                               required
                               placeholder="Enter your age">
                        <p class="help-text">You must be 18 or older to use this site</p>
                    </div>
                </div>

                <!-- Step 2: Gender -->
                <div class="form-step">
                    <div class="step-label">
                        <span class="step-number">2</span>
                        <span>Your Gender</span>
                    </div>
                    <div class="gender-options">
                        <div class="gender-option">
                            <input type="radio" 
                                   name="gender" 
                                   value="male" 
                                   id="male"
                                   <?php echo ($user['gender'] ?? '') == 'male' ? 'checked' : ''; ?>
                                   required>
                            <label for="male" class="gender-label">
                                <span class="gender-icon">👨</span>
                                <span>Male</span>
                            </label>
                        </div>
                        <div class="gender-option">
                            <input type="radio" 
                                   name="gender" 
                                   value="female" 
                                   id="female"
                                   <?php echo ($user['gender'] ?? '') == 'female' ? 'checked' : ''; ?>
                                   required>
                            <label for="female" class="gender-label">
                                <span class="gender-icon">👩</span>
                                <span>Female</span>
                            </label>
                        </div>
                        <div class="gender-option">
                            <input type="radio" 
                                   name="gender" 
                                   value="other" 
                                   id="other"
                                   <?php echo ($user['gender'] ?? '') == 'other' ? 'checked' : ''; ?>
                                   required>
                            <label for="other" class="gender-label">
                                <span class="gender-icon">⚧️</span>
                                <span>Other</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Location -->
                <div class="form-step">
                    <div class="step-label">
                        <span class="step-number">3</span>
                        <span>Your Location</span>
                    </div>
                    <div class="form-group">
                        <label>Location *</label>
                        <input type="text" 
                               name="location" 
                               value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>"
                               required
                               placeholder="City, State">
                        <p class="help-text">e.g., Los Angeles, CA</p>
                    </div>
                </div>

                <!-- Step 4: Bio -->
                <div class="form-step">
                    <div class="step-label">
                        <span class="step-number">4</span>
                        <span>About You</span>
                    </div>
                    <div class="form-group">
                        <label>Bio *</label>
                        <textarea name="bio" 
                                  rows="6" 
                                  required
                                  minlength="20"
                                  maxlength="500"
                                  id="bioInput"
                                  placeholder="Tell others about yourself... (minimum 20 characters)"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        <div class="bio-counter">
                            <span id="bioCount">0</span> / 500 characters
                        </div>
                        <p class="help-text">Minimum 20 characters required</p>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; font-size: 1.1rem; padding: 1rem;">
                    ✅ Complete Profile
                </button>
                
                <?php if(!$is_required): ?>
                <a href="/settings.php" class="btn-secondary" style="width: 100%; text-align: center; padding: 1rem; margin-top: 1rem; display: block;">
                    Skip for Now
                </a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
// Bio character counter
const bioInput = document.getElementById('bioInput');
const bioCount = document.getElementById('bioCount');

if(bioInput && bioCount) {
    function updateCount() {
        bioCount.textContent = bioInput.value.length;
        if(bioInput.value.length >= 20) {
            bioCount.style.color = 'var(--success-green)';
        } else {
            bioCount.style.color = 'var(--danger-red)';
        }
    }
    
    bioInput.addEventListener('input', updateCount);
    updateCount();
}

// Radio button styling
document.querySelectorAll('.gender-option').forEach(option => {
    option.addEventListener('click', function() {
        const radio = this.querySelector('input[type="radio"]');
        if(radio) radio.checked = true;
    });
});
</script>

<?php include 'views/footer.php'; ?>