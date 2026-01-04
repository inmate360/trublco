<?php
session_start();
require_once 'config/database.php';

// Check if user is admin - inline authentication
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';

// Handle moderation actions
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $story_id = (int)$_POST['story_id'];
    $action = $_POST['action'];

    if($action === 'approve') {
        $query = "UPDATE stories SET status = 'approved', moderated_at = NOW(), moderated_by = :admin_id WHERE id = :id";
        $message = 'Story approved and published!';
    } elseif($action === 'reject') {
        $reject_reason = $_POST['reject_reason'] ?? '';
        $query = "UPDATE stories SET status = 'rejected', moderated_at = NOW(), moderated_by = :admin_id, reject_reason = :reason WHERE id = :id";
        $message = 'Story rejected.';
    } elseif($action === 'feature') {
        $query = "UPDATE stories SET is_featured = NOT is_featured WHERE id = :id";
        $message = 'Featured status toggled!';
    } elseif($action === 'delete') {
        $query = "DELETE FROM stories WHERE id = :id";
        $message = 'Story deleted.';
    }

    if(isset($query)) {
        try {
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $story_id, PDO::PARAM_INT);
            if($action === 'approve' || $action === 'reject') {
                $stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
            }
            if($action === 'reject') {
                $stmt->bindParam(':reason', $reject_reason);
            }
            $stmt->execute();
        } catch(PDOException $e) {
            error_log("Moderation error: " . $e->getMessage());
            $message = 'Error processing action.';
        }
    }
}

// Fetch stories by status
$status_filter = $_GET['status'] ?? 'pending';

try {
    $query = "SELECT s.*, 
              u.username as moderator_name
              FROM stories s
              LEFT JOIN users u ON s.moderated_by = u.id
              WHERE s.status = :status
              ORDER BY s.created_at DESC
              LIMIT 50";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status_filter);
    $stmt->execute();
    $stories = $stmt->fetchAll();

    // Get counts
    $count_query = "SELECT 
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
                    FROM stories";
    $count_stmt = $db->query($count_query);
    $counts = $count_stmt->fetch();

} catch(PDOException $e) {
    error_log("Error fetching stories: " . $e->getMessage());
    $stories = [];
    $counts = ['pending_count' => 0, 'approved_count' => 0, 'rejected_count' => 0];
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

<div class="min-h-screen bg-gh-bg py-6">
  <div class="mx-auto max-w-6xl px-4">

    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gh-fg">Story Moderation</h1>
        <p class="text-sm text-gh-muted">Review and manage submitted stories</p>
      </div>
      <a href="admin-dashboard.php" class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold text-gh-fg transition-all hover:border-gh-accent">
        <i class="bi bi-arrow-left mr-2"></i>Back to Dashboard
      </a>
    </div>

    <!-- Success Message -->
    <?php if($message): ?>
      <div class="mb-6 rounded-lg border border-green-500 bg-green-500/10 p-4">
        <i class="bi bi-check-circle-fill mr-2 text-green-500"></i>
        <span class="text-green-500"><?php echo htmlspecialchars($message); ?></span>
      </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
      <div class="rounded-xl border border-gh-border bg-gh-panel p-4">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm text-gh-muted">Pending Review</div>
            <div class="text-2xl font-bold text-yellow-500"><?php echo number_format($counts['pending_count']); ?></div>
          </div>
          <i class="bi bi-clock-fill text-3xl text-yellow-500"></i>
        </div>
      </div>

      <div class="rounded-xl border border-gh-border bg-gh-panel p-4">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm text-gh-muted">Approved</div>
            <div class="text-2xl font-bold text-green-500"><?php echo number_format($counts['approved_count']); ?></div>
          </div>
          <i class="bi bi-check-circle-fill text-3xl text-green-500"></i>
        </div>
      </div>

      <div class="rounded-xl border border-gh-border bg-gh-panel p-4">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm text-gh-muted">Rejected</div>
            <div class="text-2xl font-bold text-red-500"><?php echo number_format($counts['rejected_count']); ?></div>
          </div>
          <i class="bi bi-x-circle-fill text-3xl text-red-500"></i>
        </div>
      </div>
    </div>

    <!-- Filter Tabs -->
    <div class="mb-6 flex gap-2 overflow-x-auto pb-2">
      <a href="?status=pending" 
         class="<?php echo $status_filter === 'pending' ? 'bg-yellow-500 text-white' : 'border border-gh-border bg-gh-panel text-gh-fg hover:border-gh-accent'; ?> whitespace-nowrap rounded-lg px-4 py-2 font-semibold transition-all">
        Pending (<?php echo $counts['pending_count']; ?>)
      </a>
      <a href="?status=approved" 
         class="<?php echo $status_filter === 'approved' ? 'bg-green-500 text-white' : 'border border-gh-border bg-gh-panel text-gh-fg hover:border-gh-accent'; ?> whitespace-nowrap rounded-lg px-4 py-2 font-semibold transition-all">
        Approved (<?php echo $counts['approved_count']; ?>)
      </a>
      <a href="?status=rejected" 
         class="<?php echo $status_filter === 'rejected' ? 'bg-red-500 text-white' : 'border border-gh-border bg-gh-panel text-gh-fg hover:border-gh-accent'; ?> whitespace-nowrap rounded-lg px-4 py-2 font-semibold transition-all">
        Rejected (<?php echo $counts['rejected_count']; ?>)
      </a>
    </div>

    <!-- Stories List -->
    <?php if(count($stories) > 0): ?>
      <div class="space-y-4">
        <?php foreach($stories as $story): ?>
          <div class="rounded-xl border border-gh-border bg-gh-panel p-6">

            <!-- Header -->
            <div class="mb-4 flex items-start justify-between gap-4">
              <div class="flex-1">
                <div class="mb-2 flex items-center gap-2">
                  <h3 class="text-xl font-bold text-gh-fg">
                    <?php echo htmlspecialchars($story['title']); ?>
                  </h3>
                  <?php if($story['is_featured']): ?>
                    <span class="rounded bg-gradient-to-r from-pink-600 to-purple-600 px-2 py-1 text-xs font-bold text-white">
                      FEATURED
                    </span>
                  <?php endif; ?>
                </div>

                <div class="flex flex-wrap gap-3 text-sm text-gh-muted">
                  <span><i class="bi bi-tag-fill mr-1"></i><?php echo $categories[$story['category']] ?? 'Other'; ?></span>
                  <span><i class="bi bi-person mr-1"></i><?php echo htmlspecialchars($story['author_name']); ?></span>
                  <?php if($story['age']): ?>
                    <span><i class="bi bi-calendar mr-1"></i><?php echo $story['age']; ?> years old</span>
                  <?php endif; ?>
                  <?php if($story['location']): ?>
                    <span><i class="bi bi-geo-alt-fill mr-1"></i><?php echo htmlspecialchars($story['location']); ?></span>
                  <?php endif; ?>
                  <span><i class="bi bi-clock mr-1"></i><?php echo date('M d, Y g:i A', strtotime($story['created_at'])); ?></span>
                  <span><i class="bi bi-laptop mr-1"></i><?php echo htmlspecialchars($story['ip_address']); ?></span>
                </div>
              </div>
            </div>

            <!-- Story Content -->
            <div class="mb-4 rounded-lg border border-gh-border bg-gh-panel2 p-4">
              <div class="max-h-60 overflow-y-auto text-sm text-gh-fg">
                <?php echo nl2br(htmlspecialchars($story['content'])); ?>
              </div>
              <div class="mt-2 text-xs text-gh-muted">
                Word count: <?php echo str_word_count($story['content']); ?> | 
                Character count: <?php echo strlen($story['content']); ?>
              </div>
            </div>

            <!-- Moderation Actions -->
            <div class="flex flex-wrap gap-2">
              <?php if($story['status'] === 'pending'): ?>
                <!-- Approve Button -->
                <form method="POST" class="inline-block">
                  <input type="hidden" name="story_id" value="<?php echo $story['id']; ?>">
                  <input type="hidden" name="action" value="approve">
                  <button type="submit" 
                          class="rounded-lg bg-green-500 px-4 py-2 font-semibold text-white transition-all hover:bg-green-600">
                    <i class="bi bi-check-circle mr-1"></i>Approve
                  </button>
                </form>

                <!-- Reject Button -->
                <button onclick="showRejectModal(<?php echo $story['id']; ?>)" 
                        class="rounded-lg bg-red-500 px-4 py-2 font-semibold text-white transition-all hover:bg-red-600">
                  <i class="bi bi-x-circle mr-1"></i>Reject
                </button>
              <?php endif; ?>

              <?php if($story['status'] === 'approved'): ?>
                <!-- Feature Toggle -->
                <form method="POST" class="inline-block">
                  <input type="hidden" name="story_id" value="<?php echo $story['id']; ?>">
                  <input type="hidden" name="action" value="feature">
                  <button type="submit" 
                          class="rounded-lg bg-purple-500 px-4 py-2 font-semibold text-white transition-all hover:bg-purple-600">
                    <i class="bi bi-star mr-1"></i><?php echo $story['is_featured'] ? 'Unfeature' : 'Feature'; ?>
                  </button>
                </form>
              <?php endif; ?>

              <!-- View Public -->
              <?php if($story['status'] === 'approved'): ?>
                <a href="story-view.php?id=<?php echo $story['id']; ?>" 
                   target="_blank"
                   class="rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2 font-semibold text-gh-fg transition-all hover:border-gh-accent">
                  <i class="bi bi-eye mr-1"></i>View Public
                </a>
              <?php endif; ?>

              <!-- Delete Button -->
              <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this story?');">
                <input type="hidden" name="story_id" value="<?php echo $story['id']; ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" 
                        class="rounded-lg border border-red-500 bg-red-500/10 px-4 py-2 font-semibold text-red-500 transition-all hover:bg-red-500 hover:text-white">
                  <i class="bi bi-trash mr-1"></i>Delete
                </button>
              </form>
            </div>

            <!-- Moderation Info -->
            <?php if($story['moderated_at']): ?>
              <div class="mt-4 rounded-lg border border-gh-border bg-gh-panel2 p-3 text-sm text-gh-muted">
                <i class="bi bi-info-circle mr-1"></i>
                Moderated by <span class="font-semibold"><?php echo htmlspecialchars($story['moderator_name'] ?? 'Unknown'); ?></span> 
                on <?php echo date('M d, Y g:i A', strtotime($story['moderated_at'])); ?>
                <?php if($story['reject_reason']): ?>
                  <br><span class="text-red-500">Reason: <?php echo htmlspecialchars($story['reject_reason']); ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="rounded-xl border border-gh-border bg-gh-panel p-12 text-center">
        <i class="bi bi-inbox text-6xl text-gh-muted opacity-20"></i>
        <h3 class="mt-4 text-xl font-bold text-gh-fg">No Stories Found</h3>
        <p class="mt-2 text-gh-muted">No stories with status: <?php echo htmlspecialchars($status_filter); ?></p>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="w-full max-w-md rounded-xl border border-gh-border bg-gh-panel p-6">
    <h3 class="mb-4 text-xl font-bold text-gh-fg">Reject Story</h3>
    <form method="POST" id="rejectForm">
      <input type="hidden" name="story_id" id="rejectStoryId">
      <input type="hidden" name="action" value="reject">

      <label class="mb-2 block text-sm font-semibold text-gh-fg">Rejection Reason</label>
      <textarea name="reject_reason" 
                rows="4" 
                required
                placeholder="Explain why this story is being rejected..."
                class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-3 text-gh-fg focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50"></textarea>

      <div class="mt-4 flex gap-2">
        <button type="submit" 
                class="flex-1 rounded-lg bg-red-500 px-4 py-3 font-semibold text-white hover:bg-red-600">
          Reject Story
        </button>
        <button type="button" 
                onclick="hideRejectModal()" 
                class="flex-1 rounded-lg border border-gh-border bg-gh-panel2 px-4 py-3 font-semibold text-gh-fg hover:border-gh-accent">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function showRejectModal(storyId) {
  document.getElementById('rejectStoryId').value = storyId;
  document.getElementById('rejectModal').classList.remove('hidden');
  document.getElementById('rejectModal').classList.add('flex');
}

function hideRejectModal() {
  document.getElementById('rejectModal').classList.add('hidden');
  document.getElementById('rejectModal').classList.remove('flex');
}
</script>

<?php include 'views/footer.php'; ?>
