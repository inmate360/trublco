l<?php
session_start();
require_once 'config/database.php';
require_once 'classes/PrivateMessaging.php';
require_once 'classes/ContentModerator.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$pm = new PrivateMessaging($db);

$error = '';
$recipient_id = isset($_GET['to']) ? (int)$_GET['to'] : null;
$recipient_name = '';
$listing_id = isset($_GET['listing']) ? (int)$_GET['listing'] : null;

// Get recipient info if specified
if($recipient_id) {
    $query = "SELECT username FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $recipient_id);
    $stmt->execute();
    $recipient = $stmt->fetch();
    $recipient_name = $recipient['username'] ?? '';
}

// Get listing info if specified
$listing_subject = '';
if($listing_id) {
    $query = "SELECT title FROM listings WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $listing_id);
    $stmt->execute();
    $listing = $stmt->fetch();
    if($listing) {
        $listing_subject = 'Re: ' . $listing['title'];
    }
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_id = (int)($_POST['recipient_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if(empty($recipient_id) || empty($subject) || empty($message)) {
        $error = 'All fields are required (make sure you selected a recipient).';
    } elseif($recipient_id == $_SESSION['user_id']) {
        $error = 'You cannot send a message to yourself.';
    } else {
        $result = $pm->createThread($_SESSION['user_id'], $recipient_id, $subject, $message);
        
        if(!empty($result['success'])) {
            // ═══════════════════════════════════════════════════
            // 🔒 AI CONTENT MODERATION - Perplexity API
            // ═══════════════════════════════════════════════════
            $moderator = new ContentModerator($db);
            $moderationResult = $moderator->moderateText(
                $subject . " " . $message, 
                'message', 
                $result['message_id'] ?? $result['thread_id'], 
                $_SESSION['user_id']
            );
            
            // Handle moderation results
            if ($moderationResult['risk_level'] === 'critical') {
                // CRITICAL: Block message and notify sender
                // Delete the message/thread
                $deleteQuery = "DELETE FROM pm_threads WHERE id = :thread_id";
                $deleteStmt = $db->prepare($deleteQuery);
                $deleteStmt->execute([':thread_id' => $result['thread_id']]);
                
                $error = 'Your message could not be sent due to policy violations. If you believe this is an error, please contact support.';
                
            } elseif ($moderationResult['risk_level'] === 'high') {
                // HIGH RISK: Flag for review but allow delivery with warning
                header('Location: messages-view.php?thread=' . $result['thread_id'] . '&flagged=1');
                exit();
                
            } else {
                // LOW/MEDIUM RISK: Allow normally
                header('Location: messages-view.php?thread=' . $result['thread_id']);
                exit();
            }
            // ═══════════════════════════════════════════════════
        } else {
            $error = $result['error'] ?? 'Failed to send message.';
        }
    }
}

include 'views/header.php';
?>

<div class="min-h-screen bg-gh-bg py-6">
  <div class="mx-auto max-w-3xl px-4">

    <div class="mb-6 flex items-center gap-3">
      <a href="messages-inbox.php" class="flex h-9 w-9 items-center justify-center rounded-lg text-gh-muted transition-all hover:bg-gh-panel hover:text-gh-fg">
        <i class="bi bi-arrow-left"></i>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-white">New Message</h1>
        <p class="text-sm text-gh-muted">Send a private message</p>
      </div>
    </div>

    <?php if($error): ?>
    <div class="mb-4 rounded-lg border border-red-500 bg-red-500/10 p-3">
      <p class="text-sm text-red-500">
        <i class="bi bi-exclamation-triangle mr-1"></i>
        <?php echo htmlspecialchars($error); ?>
      </p>
    </div>
    <?php endif; ?>

    <!-- AI Moderation Notice -->
    <div class="mb-4 rounded-lg border border-purple-500/30 bg-purple-500/10 p-3">
      <div class="flex items-start gap-2">
        <i class="bi bi-shield-check text-purple-400 mt-0.5"></i>
        <div class="flex-1">
          <p class="text-xs font-semibold text-purple-400 mb-1">Protected by Perplexity Sonar Pro</p>
          <p class="text-xs text-gh-muted">
            All messages are automatically scanned for safety. Inappropriate content will be blocked.
          </p>
        </div>
      </div>
    </div>

    <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
      <form method="POST" id="composeForm" novalidate>

        <div class="mb-4">
          <label class="mb-2 block text-sm font-semibold text-white">
            To <span class="text-red-500">*</span>
          </label>
          
          <?php if($recipient_id): ?>
          <div class="flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel2 px-3 py-2">
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-pink-600 to-purple-600 text-xs font-bold text-white">
              <?php echo strtoupper(substr($recipient_name, 0, 1)); ?>
            </div>
            <span class="text-sm font-semibold text-white"><?php echo htmlspecialchars($recipient_name); ?></span>
            <input type="hidden" name="recipient_id" value="<?php echo intval($recipient_id); ?>">
          </div>
          <?php else: ?>
          <div class="relative">
            <input type="text" id="userSearch" placeholder="Search by username..." autocomplete="off"
                   class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-3 py-2 text-sm text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none">
            <input type="hidden" name="recipient_id" id="recipientId">
          </div>
          <div id="searchResults" class="absolute z-10 mt-1 hidden w-full rounded-lg border border-gh-border bg-gh-panel shadow-lg"></div>
          
          <div id="selectedUser" class="mt-2 hidden">
            <div class="flex items-center gap-2 rounded-lg border border-gh-accent bg-gh-accent/10 px-3 py-2">
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-pink-600 to-purple-600 text-xs font-bold text-white">
                <span id="selectedInitial"></span>
              </div>
              <span class="flex-1 text-sm font-semibold text-white" id="selectedName"></span>
              <button type="button" onclick="clearSelection()" class="text-gh-muted hover:text-red-500" aria-label="Clear selected recipient">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
          </div>
          <p class="mt-1 text-xs text-gh-muted">Start typing to select a recipient.</p>
          <?php endif; ?>
        </div>

        <div class="mb-4">
          <label class="mb-2 block text-sm font-semibold text-white">
            Subject <span class="text-red-500">*</span>
          </label>
          <input type="text" name="subject" value="<?php echo htmlspecialchars($listing_subject); ?>" 
                 placeholder="What's this about?" maxlength="100"
                 class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-3 py-2 text-sm text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none" required>
        </div>

        <div class="mb-4">
          <label class="mb-2 block text-sm font-semibold text-white">
            Message <span class="text-red-500">*</span>
          </label>
          <textarea name="message" rows="8" placeholder="Type your message here..."
                    class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-3 py-2 text-sm text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none" required></textarea>
          <div class="flex items-center justify-between mt-1">
            <p class="text-xs text-gh-muted">Be respectful and follow community guidelines</p>
            <div class="text-10px text-gh-muted font-medium flex items-center gap-1">
              <i class="bi bi-shield-check text-gh-accent"></i>
              Protected By AI Moderation
            </div>
          </div>
        </div>

        <div class="flex gap-2">
          <button type="submit" class="flex-1 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-4 py-2.5 text-sm font-bold text-white transition-all hover:brightness-110">
            <i class="bi bi-send-fill mr-2"></i>
            Send Message
          </button>
          <a href="messages-inbox.php" class="rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm font-semibold text-gh-fg transition-all hover:border-gh-accent">
            Cancel
          </a>
        </div>

      </form>
    </div>

  </div>
</div>

<?php if(!$recipient_id): ?>
<script>
let searchTimeout = null;
const userSearch = document.getElementById('userSearch');
const searchResults = document.getElementById('searchResults');
const recipientId = document.getElementById('recipientId');
const selectedUser = document.getElementById('selectedUser');

// Client-side guard: prevent empty submits
document.getElementById('composeForm').addEventListener('submit', function(e) {
    const rid = recipientId.value.trim();
    if(!rid) {
        e.preventDefault();
        userSearch.focus();
        alert('Please select a recipient from the search results.');
    }
});

userSearch.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();
    
    if(query.length < 2) {
        searchResults.classList.add('hidden');
        return;
    }
    
    searchTimeout = setTimeout(() => searchUsers(query), 250);
});

async function searchUsers(query) {
    try {
        const response = await fetch(`ajax/search-users.php?q=${encodeURIComponent(query)}`);
        const result = await response.json();
        
        if(result.success && result.users.length > 0) {
            displaySearchResults(result.users);
        } else {
            searchResults.innerHTML = '<div class="p-3 text-center text-sm text-gh-muted">No users found</div>';
            searchResults.classList.remove('hidden');
        }
    } catch(error) {
        console.error('Search error:', error);
    }
}

function displaySearchResults(users) {
    let html = '';
    users.forEach(user => {
        const initial = user.username.charAt(0).toUpperCase();
        const verified = user.is_verified ? '<i class="bi bi-patch-check-fill text-sm text-green-500"></i>' : '';
        const premium = user.is_premium ? '<i class="bi bi-gem text-sm text-purple-500"></i>' : '';
        
        html += `
        <button type="button" onclick="selectUser(${user.id}, '${escapeHtml(user.username)}')" 
                class="flex w-full items-center gap-2 p-3 text-left transition-all hover:bg-gh-panel2">
          <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-pink-600 to-purple-600 text-xs font-bold text-white">
            ${initial}
          </div>
          <div class="flex-1">
            <div class="flex items-center gap-1">
              <span class="text-sm font-semibold text-white">${escapeHtml(user.username)}</span>
              ${verified}
              ${premium}
            </div>
          </div>
        </button>`;
    });
    
    searchResults.innerHTML = html;
    searchResults.classList.remove('hidden');
}

function selectUser(id, username) {
    recipientId.value = String(id);
    userSearch.value = username;
    userSearch.disabled = true;
    searchResults.classList.add('hidden');
    
    document.getElementById('selectedInitial').textContent = username.charAt(0).toUpperCase();
    document.getElementById('selectedName').textContent = username;
    selectedUser.classList.remove('hidden');
}

function clearSelection() {
    recipientId.value = '';
    selectedUser.classList.add('hidden');
    userSearch.disabled = false;
    userSearch.value = '';
    userSearch.focus();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('click', function(e) {
    if(!searchResults.contains(e.target) && e.target !== userSearch) {
        searchResults.classList.add('hidden');
    }
});
</script>
<?php endif; ?>

<?php include 'views/footer.php'; ?>
