<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Forum.php';
require_once 'AwardsManager.php';
require_once 'classes/ContentModerator.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=forum-create-thread.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$forum = new Forum($db);

$categories = $forum->getCategories();
$preselected_category = (int)($_GET['category'] ?? 0);

$error = '';
$success = '';
$warning = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    
    if(empty($title) || empty($content) || empty($category_id)) {
        $error = 'Please fill in all fields';
    } elseif(strlen($title) < 5) {
        $error = 'Title must be at least 5 characters';
    } elseif(strlen($content) < 20) {
        $error = 'Content must be at least 20 characters';
    } else {
        $result = $forum->createThread($_SESSION['user_id'], $category_id, $title, $content);
        
        if($result['success']) {
            // ═══════════════════════════════════════════════════
            // 🔒 AI CONTENT MODERATION - Perplexity API
            // ═══════════════════════════════════════════════════
            $moderator = new ContentModerator($db);
            $moderationResult = $moderator->moderateText(
                $title . " " . $content, 
                'forum_thread', 
                $result['id'], 
                $_SESSION['user_id']
            );
            
            // Handle moderation results
            if ($moderationResult['risk_level'] === 'critical') {
                // CRITICAL: Delete thread and block
                $deleteQuery = "DELETE FROM forum_threads WHERE id = :id";
                $deleteStmt = $db->prepare($deleteQuery);
                $deleteStmt->execute([':id' => $result['id']]);
                
                $error = 'Your thread could not be created due to policy violations. If you believe this is an error, please contact support.';
                
            } elseif ($moderationResult['risk_level'] === 'high') {
                // HIGH RISK: Mark for review
                $updateQuery = "UPDATE forum_threads SET status = 'pending' WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->execute([':id' => $result['id']]);
                
                header('Location: forum.php?flagged=1');
                exit();
                
            } else {
                // LOW/MEDIUM RISK: Allow normally
                // ═══════════════════════════════════════════════
                // 🏆 AWARDS SYSTEM - Check and grant awards
                // ═══════════════════════════════════════════════
                $awardsManager = new AwardsManager($db);
                $newly_earned = $awardsManager->checkAndGrantAwards($_SESSION['user_id']);
                
                if(count($newly_earned) > 0) {
                    $_SESSION['new_awards'] = $newly_earned;
                }
                // ═══════════════════════════════════════════════
                
                header('Location: forum-thread.php?slug=' . $result['slug']);
                exit();
            }
            // ═══════════════════════════════════════════════════
            
        } else {
            $error = $result['error'] ?? 'Failed to create thread';
        }
    }
}

include 'views/header.php';
?>

<div class="min-h-screen bg-gh-bg py-8">
  <div class="container mx-auto px-4 max-w-4xl">

    <!-- Header -->
    <div class="mb-6 flex items-center gap-3">
      <a href="forum.php" class="flex h-9 w-9 items-center justify-center rounded-lg text-gh-muted transition-all hover:bg-gh-panel hover:text-gh-fg">
        <i class="bi bi-arrow-left text-lg"></i>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-white">Create New Thread</h1>
        <p class="text-sm text-gh-muted">Start a new discussion</p>
      </div>
    </div>

    <!-- Error Message -->
    <?php if($error): ?>
    <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 p-4">
      <div class="flex items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill text-red-400 mt-0.5"></i>
        <div class="flex-1">
          <p class="text-sm font-semibold text-red-400 mb-1">Error</p>
          <p class="text-sm text-gh-muted"><?php echo htmlspecialchars($error); ?></p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- AI Moderation Notice -->
    <div class="mb-6 rounded-lg border border-purple-500/30 bg-purple-500/10 p-3">
      <div class="flex items-start gap-2">
        <i class="bi bi-shield-check text-purple-400 mt-0.5"></i>
        <div class="flex-1">
          <p class="text-xs font-semibold text-purple-400 mb-1">Protected by Perplexity Sonar Pro</p>
          <p class="text-xs text-gh-muted">
            All forum posts are automatically scanned for safety. Inappropriate content will be blocked or flagged for review.
          </p>
        </div>
      </div>
    </div>

    <!-- Create Thread Form -->
    <form method="POST" class="rounded-xl border border-gh-border bg-gh-panel p-6 space-y-5">

      <!-- Category Selection -->
      <div>
        <label class="block text-sm font-semibold text-white mb-2">
          Category <span class="text-red-500">*</span>
        </label>
        <select name="category_id" required
                class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm text-gh-fg transition-all focus:border-gh-accent focus:outline-none">
          <option value="">Select a category...</option>
          <?php foreach($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" 
                    <?php echo ($preselected_category == $cat['id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($cat['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Thread Title -->
      <div>
        <label class="block text-sm font-semibold text-white mb-2">
          Thread Title <span class="text-red-500">*</span>
        </label>
        <input type="text" name="title" required maxlength="200" minlength="5"
               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
               class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none"
               placeholder="Enter a descriptive title...">
        <p class="mt-1 text-xs text-gh-muted">Minimum 5 characters</p>
      </div>

      <!-- Thread Content -->
      <div>
        <label class="block text-sm font-semibold text-white mb-2">
          Content <span class="text-red-500">*</span>
        </label>
        <textarea name="content" required rows="10" minlength="20" maxlength="10000"
                  class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none resize-y"
                  placeholder="Share your thoughts, ask a question, or start a discussion..."><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
        <div class="flex items-center justify-between mt-2">
          <p class="text-xs text-gh-muted">Minimum 20 characters</p>
          <div class="text-10px text-gh-muted font-medium flex items-center gap-1">
            <i class="bi bi-shield-check text-gh-accent"></i>
            AI Moderated Content
          </div>
        </div>
      </div>

      <!-- Forum Guidelines -->
      <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
        <p class="text-xs font-semibold text-white mb-2">Forum Guidelines</p>
        <ul class="space-y-1 text-xs text-gh-muted">
          <li>• Be respectful and civil in discussions</li>
          <li>• No spam, harassment, or hate speech</li>
          <li>• Stay on topic for the category</li>
          <li>• No personal attacks or trolling</li>
          <li>• Content is reviewed by AI before publishing</li>
        </ul>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-3">
        <button type="submit"
                class="flex-1 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-2.5 text-sm font-bold text-white transition-all hover:brightness-110">
          <i class="bi bi-send-fill mr-2"></i>
          Create Thread
        </button>
        <a href="forum.php"
           class="rounded-lg border border-gh-border bg-gh-panel2 px-6 py-2.5 text-sm font-semibold text-gh-fg transition-all hover:border-gh-accent">
          Cancel
        </a>
      </div>

    </form>

  </div>
</div>

<?php include 'views/footer.php'; ?>
