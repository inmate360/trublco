<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Location.php';
require_once 'classes/ContentModerator.php';
require_once 'AwardsManager.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$database = new Database();
$db = $database->getConnection();
$location = new Location($db);

$error_message = '';

// Get categories
$categories = [];
try {
    $query = "SELECT * FROM categories ORDER BY name ASC";
    $stmt = $db->query($query);
    $categories = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

$states = $location->getAllStates();

// Get ALL cities and group by state (for inline JavaScript)
$all_cities = [];
try {
    $query = "SELECT id, name, state_id FROM cities ORDER BY name ASC";
    $stmt = $db->query($query);
    $cities_result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($cities_result as $city) {
        $state_id = $city['state_id'];
        if(!isset($all_cities[$state_id])) {
            $all_cities[$state_id] = [];
        }
        $all_cities[$state_id][] = [
            'id' => $city['id'],
            'name' => $city['name']
        ];
    }
} catch(PDOException $e) {
    error_log("Error loading cities: " . $e->getMessage());
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $city_id = (int)($_POST['city_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $age = isset($_POST['age']) ? (int)$_POST['age'] : null;
    $contact_method = $_POST['contact_method'] ?? 'message';
    $contact_info = trim($_POST['contact_info'] ?? '');

    if(empty($title) || !$category_id || !$city_id || empty($description)) {
        $error_message = 'Please fill in all required fields.';
    } elseif(strlen($description) < 50) {
        $error_message = 'Description must be at least 50 characters.';
    } else {
        try {
            $query = "INSERT INTO listings (user_id, title, category_id, city_id, description, age, contact_method, contact_info, status, created_at) 
                      VALUES (:user_id, :title, :category_id, :city_id, :description, :age, :contact_method, :contact_info, 'active', NOW())";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':city_id', $city_id);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':age', $age);
            $stmt->bindParam(':contact_method', $contact_method);
            $stmt->bindParam(':contact_info', $contact_info);

            if($stmt->execute()) {
                $listing_id = $db->lastInsertId();
                
                // Awards system
                $awardsManager = new AwardsManager($db);
                $newly_earned = $awardsManager->checkAndGrantAwards($_SESSION['user_id']);

                if(count($newly_earned) > 0) {
                    $_SESSION['new_awards'] = $newly_earned;
                }
                
                // AI moderation
                $moderator = new ContentModerator($db);
                $moderationResult = $moderator->moderateText($title . " " . $description, 'listing', $listing_id, $_SESSION['user_id']);
                
                // Enhanced risk-based handling
                if ($moderationResult['risk_level'] === 'critical') {
                    // Block listing and update status
                    $blockQuery = "UPDATE listings SET status = 'blocked' WHERE id = :id";
                    $blockStmt = $db->prepare($blockQuery);
                    $blockStmt->execute([':id' => $listing_id]);
                    header('Location: my-listings.php?flagged=1&blocked=1');
                    exit();
                } elseif ($moderationResult['risk_level'] === 'high') {
                    // Hold for review
                    $reviewQuery = "UPDATE listings SET status = 'pending_review' WHERE id = :id";
                    $reviewStmt = $db->prepare($reviewQuery);
                    $reviewStmt->execute([':id' => $listing_id]);
                    header('Location: my-listings.php?flagged=1&review=1');
                    exit();
                } else {
                    // Medium or low risk - approve as normal
                    header('Location: listing.php?id=' . $listing_id . '&posted=1');
                    exit();
                }
            } else {
                $error_message = 'Failed to post ad. Please try again.';
            }
        } catch(PDOException $e) {
            error_log("Error posting ad: " . $e->getMessage());
            $error_message = 'An error occurred. Please try again later.';
        }
    }
}

include 'views/header.php';
?>

<div class="min-h-screen bg-gh-bg py-8">
  <div class="container mx-auto px-4 max-w-3xl">

    <div class="text-center mb-8">
      <h1 class="text-3xl font-bold text-gh-fg mb-2">Post a Personal Ad</h1>
      <p class="text-gh-muted">Free and takes less than 2 minutes</p>
      <!-- Status indicator style -->
<div class="flex items-center gap-3 rounded-lg border border-gh-border bg-gh-panel2 px-4 py-3">
    <div class="flex items-center gap-2">
        <div class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></div>
        <span class="text-sm font-medium text-white">AI Moderation Active</span>
    </div>
    <div class="h-4 w-px bg-gh-border"></div>
    <span class="text-xs text-gh-muted">Powered by Perplexity Sonar Pro</span>
</div>

    </div>
 
    <?php if($error_message): ?>
    <div class="bg-red-500/10 border border-red-500 text-red-500 rounded-lg p-4 mb-6">
      <i class="bi bi-exclamation-triangle-fill mr-2"></i>
      <?php echo $error_message; ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="bg-gh-panel border border-gh-border rounded-xl p-6 space-y-6">

      <div>
        <label class="block text-sm font-medium text-gh-fg mb-2">
          Ad Title <span class="text-red-500">*</span>
        </label>
        <input type="text" name="title" required
               class="w-full px-4 py-2 bg-gh-bg border border-gh-border rounded-lg text-gh-fg focus:outline-none focus:border-gh-accent"
               placeholder="e.g., Looking for casual fun this weekend"
               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
      </div>

      <div>
        <label class="block text-sm font-medium text-gh-fg mb-2">
          Category <span class="text-red-500">*</span>
        </label>
        <select name="category_id" required
                class="w-full px-4 py-2 bg-gh-bg border border-gh-border rounded-lg text-gh-fg focus:outline-none focus:border-gh-accent">
          <option value="">Select a category...</option>
          <?php foreach($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($cat['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gh-fg mb-2">
          Location <span class="text-red-500">*</span>
        </label>
        <div class="grid grid-cols-2 gap-4">
          <select id="state-select" name="state_id"
                  class="px-4 py-2 bg-gh-bg border border-gh-border rounded-lg text-gh-fg focus:outline-none focus:border-gh-accent">
            <option value="">Select State...</option>
            <?php foreach($states as $state): ?>
              <option value="<?php echo $state['id']; ?>"><?php echo htmlspecialchars($state['name']); ?></option>
            <?php endforeach; ?>
          </select>

          <select id="city-select" name="city_id" required
                  class="px-4 py-2 bg-gh-bg border border-gh-border rounded-lg text-gh-fg focus:outline-none focus:border-gh-accent">
            <option value="">Select City...</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gh-fg mb-2">
          Description <span class="text-red-500">*</span>
        </label>
        <textarea name="description" required rows="8"
                  class="w-full px-4 py-2 bg-gh-bg border border-gh-border rounded-lg text-gh-fg focus:outline-none focus:border-gh-accent resize-y"
                  placeholder="Describe what you're looking for... (minimum 50 characters)"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium text-gh-fg mb-2">Age</label>
        <input type="number" name="age" min="18" max="99"
               class="w-full px-4 py-2 bg-gh-bg border border-gh-border rounded-lg text-gh-fg focus:outline-none focus:border-gh-accent"
               placeholder="Optional"
               value="<?php echo $_POST['age'] ?? ''; ?>">
      </div>

      <div>
        <label class="block text-sm font-medium text-gh-fg mb-2">Contact Method</label>
        <select name="contact_method"
                class="w-full px-4 py-2 bg-gh-bg border border-gh-border rounded-lg text-gh-fg focus:outline-none focus:border-gh-accent">
          <option value="message" <?php echo ($_POST['contact_method'] ?? 'message') === 'message' ? 'selected' : ''; ?>>
            Site Message
          </option>
          <option value="email" <?php echo ($_POST['contact_method'] ?? '') === 'email' ? 'selected' : ''; ?>>
            Email
          </option>
          <option value="phone" <?php echo ($_POST['contact_method'] ?? '') === 'phone' ? 'selected' : ''; ?>>
            Phone
          </option>
          <option value="other" <?php echo ($_POST['contact_method'] ?? '') === 'other' ? 'selected' : ''; ?>>
            Other
          </option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gh-fg mb-2">Contact Info</label>
        <input type="text" name="contact_info"
               class="w-full px-4 py-2 bg-gh-bg border border-gh-border rounded-lg text-gh-fg focus:outline-none focus:border-gh-accent"
               placeholder="Optional - only if not using site messages"
               value="<?php echo htmlspecialchars($_POST['contact_info'] ?? ''); ?>">
      </div>

      <button type="submit"
              class="w-full bg-gh-accent text-white font-semibold py-3 rounded-lg hover:bg-gh-accent/90 transition-colors">
        Post Ad
      </button>

      <p class="text-xs text-gh-muted text-center">
        By posting, you agree to our Terms of Service and Community Guidelines
         
      </p>
    </form>
<!-- Animated version -->
<div class="inline-flex items-center gap-2 rounded-lg border border-green-500/30 bg-green-500/10 px-3 py-1.5">
    <div class="relative flex h-2 w-2">
        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
        <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
    </div>
    <span class="text-xs font-semibold text-green-400">
        Live AI Monitoring by Perplexity Sonar Pro
    </span>
</div>

  </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════════
// INLINE CITY LOADING - No API required!
// Cities are loaded directly from PHP, no external API calls needed
// ═══════════════════════════════════════════════════════════════════

const citiesByState = <?php echo json_encode($all_cities); ?>;

console.log('Cities loaded:', Object.keys(citiesByState).length, 'states');

document.getElementById('state-select').addEventListener('change', function() {
    const stateId = this.value;
    const citySelect = document.getElementById('city-select');

    console.log('State selected:', stateId);

    // Clear current options
    citySelect.innerHTML = '<option value="">Select City...</option>';

    if(!stateId) {
        return;
    }

    // Load cities for this state
    if(citiesByState[stateId]) {
        console.log('Loading', citiesByState[stateId].length, 'cities');

        citiesByState[stateId].forEach(city => {
            const option = document.createElement('option');
            option.value = city.id;
            option.textContent = city.name;
            citySelect.appendChild(option);
        });
    } else {
        console.warn('No cities found for state', stateId);
        citySelect.innerHTML = '<option value="">No cities available</option>';
    }
});
</script>

<?php include 'views/footer.php'; ?>
