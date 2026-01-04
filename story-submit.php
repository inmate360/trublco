<?php
session_start();
require_once 'config/database.php';
require_once 'AwardsManager.php';
require_once 'classes/ContentModerator.php';

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';
$warning_message = '';

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $author_name = trim($_POST['author_name'] ?? 'Anonymous');
    $age = isset($_POST['age']) ? (int)$_POST['age'] : null;
    $gender = $_POST['gender'] ?? '';
    
    // Validation
    if(empty($title) || empty($category) || empty($content)) {
        $error_message = 'Please fill in all required fields.';
    } elseif(strlen($content) < 100) {
        $error_message = 'Story must be at least 100 characters long.';
    } elseif(strlen($content) > 10000) {
        $error_message = 'Story must be less than 10,000 characters.';
    } else {
        try {
            // Get user_id if logged in, otherwise null for anonymous
            $user_id = $_SESSION['user_id'] ?? null;
            
            // Insert with 'pending' status initially for moderation
            $query = "INSERT INTO stories 
                      (user_id, title, category, location, content, author_name, age, gender, status, ip_address, created_at) 
                      VALUES 
                      (:user_id, :title, :category, :location, :content, :author_name, :age, :gender, 'pending', :ip, NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':author_name', $author_name);
            $stmt->bindParam(':age', $age);
            $stmt->bindParam(':gender', $gender);
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bindParam(':ip', $ip);
            
            if($stmt->execute()) {
                $story_id = $db->lastInsertId();
                
                // ═══════════════════════════════════════════════════
                // 🔒 AI CONTENT MODERATION - Perplexity API
                // ═══════════════════════════════════════════════════
                $moderator = new ContentModerator($db);
                $moderationResult = $moderator->moderateText(
                    $title . " " . $content, 
                    'story', 
                    $story_id, 
                    $user_id
                );
                
                // Handle moderation results
                if ($moderationResult['risk_level'] === 'critical') {
                    // CRITICAL: Block and delete story
                    $updateQuery = "UPDATE stories SET status = 'blocked' WHERE id = :id";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->execute([':id' => $story_id]);
                    
                    $error_message = 'Your story could not be published due to policy violations. If you believe this is an error, please contact support.';
                    
                } elseif ($moderationResult['risk_level'] === 'high') {
                    // HIGH RISK: Hold for human review
                    $updateQuery = "UPDATE stories SET status = 'pending_review' WHERE id = :id";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->execute([':id' => $story_id]);
                    
                    $warning_message = 'Thank you for submitting your story! It has been flagged for review by our moderation team and will be published once approved (usually within 24 hours).';
                    
                } else {
                    // LOW/MEDIUM RISK: Auto-approve
                    $updateQuery = "UPDATE stories SET status = 'approved' WHERE id = :id";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->execute([':id' => $story_id]);
                    
                    $success_message = 'Thank you! Your story has been published successfully and is now live!';
                    
                    // ═══════════════════════════════════════════════
                    // 🏆 AWARDS SYSTEM - Check and grant awards
                    // ═══════════════════════════════════════════════
                    if($user_id) {
                        $awardsManager = new AwardsManager($db);
                        $newly_earned = $awardsManager->checkAndGrantAwards($user_id);
                        
                        if(count($newly_earned) > 0) {
                            $_SESSION['new_awards'] = $newly_earned;
                        }
                    }
                }
                // ═══════════════════════════════════════════════════
                
                // Clear form only on success
                if($success_message) {
                    $_POST = [];
                }
                
            } else {
                $error_message = 'Failed to submit story. Please try again.';
            }
            
        } catch(PDOException $e) {
            error_log("Error submitting story: " . $e->getMessage());
            $error_message = 'An error occurred. Please try again later.';
        }
    }
}

$categories = [
    'hookup' => 'Hookup Stories',
    'first-time' => 'First Time',
    'encounter' => 'Random Encounter',
    'dating' => 'Dating Experience',
    'threesome' => 'Group Experience',
    'casual' => 'Casual Meet',
    'app' => 'App Hookup',
    'other' => 'Other'
];

include 'views/header.php';
?>

<div class="min-h-screen bg-gh-bg py-8">
  <div class="container mx-auto px-4 max-w-4xl">

    <!-- Header -->
    <div class="text-center mb-8">
      <h1 class="text-4xl font-bold text-white mb-2">Share Your Story</h1>
      <p class="text-gh-muted">Anonymous submissions welcome. Stories are published after AI review.</p>
    </div>

    <!-- Success Message -->
    <?php if($success_message): ?>
    <div class="mb-6 rounded-lg border border-green-500/30 bg-green-500/10 p-4">
      <div class="flex items-start gap-3">
        <i class="bi bi-check-circle-fill text-2xl text-green-400"></i>
        <div class="flex-1">
          <p class="text-sm font-semibold text-green-400 mb-1">Success!</p>
          <p class="text-sm text-gh-muted"><?php echo htmlspecialchars($success_message); ?></p>
          <a href="story.php" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-green-400 hover:underline">
            <i class="bi bi-arrow-right"></i>
            View all stories
          </a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Warning Message -->
    <?php if($warning_message): ?>
    <div class="mb-6 rounded-lg border border-orange-500/30 bg-orange-500/10 p-4">
      <div class="flex items-start gap-3">
        <i class="bi bi-clock-history text-2xl text-orange-400"></i>
        <div class="flex-1">
          <p class="text-sm font-semibold text-orange-400 mb-1">Under Review</p>
          <p class="text-sm text-gh-muted"><?php echo htmlspecialchars($warning_message); ?></p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if($error_message): ?>
    <div class="mb-6 rounded-lg border border-red-500/30 bg-red-500/10 p-4">
      <div class="flex items-start gap-3">
        <i class="bi bi-exclamation-triangle-fill text-2xl text-red-400"></i>
        <div class="flex-1">
          <p class="text-sm font-semibold text-red-400 mb-1">Error</p>
          <p class="text-sm text-gh-muted"><?php echo htmlspecialchars($error_message); ?></p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- AI Moderation Notice -->
    <div class="mb-6 rounded-xl border border-purple-500/30 bg-gradient-to-r from-purple-500/10 to-pink-500/10 p-4">
      <div class="flex items-start gap-3">
        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-pink-600 to-purple-600">
          <i class="bi bi-shield-shaded text-lg text-white"></i>
        </div>
        <div class="flex-1">
          <p class="text-sm font-bold text-white mb-1">Protected by Perplexity Sonar Pro</p>
          <p class="text-xs text-gh-muted">
            All stories are automatically scanned by AI for safety and policy compliance. 
            Inappropriate content will be blocked or flagged for review.
          </p>
        </div>
      </div>
    </div>

    <!-- Story Submission Form -->
    <form method="POST" class="rounded-xl border border-gh-border bg-gh-panel p-6 space-y-6">

      <!-- Title -->
      <div>
        <label class="block text-sm font-semibold text-white mb-2">
          Story Title <span class="text-red-500">*</span>
        </label>
        <input type="text" name="title" required maxlength="200"
               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
               class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none"
               placeholder="Give your story a catchy title...">
      </div>

      <!-- Category -->
      <div>
        <label class="block text-sm font-semibold text-white mb-2">
          Category <span class="text-red-500">*</span>
        </label>
        <select name="category" required
                class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm text-gh-fg transition-all focus:border-gh-accent focus:outline-none">
          <option value="">Select a category...</option>
          <?php foreach($categories as $key => $label): ?>
            <option value="<?php echo $key; ?>" <?php echo (($_POST['category'] ?? '') === $key) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Location -->
      <div>
        <label class="block text-sm font-semibold text-white mb-2">
          Location (Optional)
        </label>
        <input type="text" name="location" maxlength="100"
               value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
               class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none"
               placeholder="e.g., New York, Miami Beach, etc.">
      </div>

      <!-- Story Content -->
      <div>
        <label class="block text-sm font-semibold text-white mb-2">
          Your Story <span class="text-red-500">*</span>
        </label>
        <textarea name="content" required rows="12" minlength="100" maxlength="10000"
                  class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none resize-y"
                  placeholder="Share your experience... (minimum 100 characters, maximum 10,000)"><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
        <div class="flex items-center justify-between mt-2">
          <p class="text-xs text-gh-muted">Minimum 100 characters required</p>
          <div class="text-10px text-gh-muted font-medium flex items-center gap-1">
            <i class="bi bi-shield-check text-gh-accent"></i>
            AI Moderated Content
          </div>
        </div>
      </div>

      <!-- Author Info -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-semibold text-white mb-2">
            Author Name
          </label>
          <input type="text" name="author_name" maxlength="50"
                 value="<?php echo htmlspecialchars($_POST['author_name'] ?? 'Anonymous'); ?>"
                 class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none"
                 placeholder="Anonymous">
        </div>

        <div>
          <label class="block text-sm font-semibold text-white mb-2">
            Age (Optional)
          </label>
          <input type="number" name="age" min="18" max="99"
                 value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>"
                 class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none"
                 placeholder="18+">
        </div>

        <div>
          <label class="block text-sm font-semibold text-white mb-2">
            Gender (Optional)
          </label>
          <select name="gender"
                  class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm text-gh-fg transition-all focus:border-gh-accent focus:outline-none">
            <option value="">Prefer not to say</option>
            <option value="male" <?php echo (($_POST['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
            <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
            <option value="other" <?php echo (($_POST['gender'] ?? '') === 'other') ? 'selected' : ''; ?>>Other</option>
          </select>
        </div>
      </div>

      <!-- Guidelines -->
      <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
        <p class="text-xs font-semibold text-white mb-2">Community Guidelines</p>
        <ul class="space-y-1 text-xs text-gh-muted">
          <li>• Stories must be your own experiences</li>
          <li>• All participants must be 18+ years old</li>
          <li>• Be respectful and avoid hate speech</li>
          <li>• No personal information or contact details</li>
          <li>• Content will be reviewed by AI before publishing</li>
        </ul>
      </div>

      <!-- Submit Button -->
      <div class="flex gap-3">
        <button type="submit"
                class="flex-1 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-3 text-sm font-bold text-white transition-all hover:brightness-110">
          <i class="bi bi-send-fill mr-2"></i>
          Submit Story
        </button>
        <a href="story.php"
           class="rounded-lg border border-gh-border bg-gh-panel2 px-6 py-3 text-sm font-semibold text-gh-fg transition-all hover:border-gh-accent">
          Cancel
        </a>
      </div>

      <p class="text-center text-xs text-gh-muted">
        By submitting, you agree to our Terms of Service and Community Guidelines
      </p>

    </form>

  </div>
</div>

<?php include 'views/footer.php'; ?>
