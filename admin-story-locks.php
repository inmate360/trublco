<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();
// Check if user is admin
if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: index.php');
    exit();
}

$success = '';
$error = '';

// Handle lock/unlock action
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['story_id'])) {
    $story_id = (int)$_POST['story_id'];
    $action = $_POST['action'];

    try {
        if($action == 'lock') {
            $stmt = $db->prepare("UPDATE stories SET is_locked = 1 WHERE id = ?");
            $stmt->execute([$story_id]);
            $success = 'Story locked successfully';
        } elseif($action == 'unlock') {
            $stmt = $db->prepare("UPDATE stories SET is_locked = 0 WHERE id = ?");
            $stmt->execute([$story_id]);
            $success = 'Story unlocked successfully';
        }
    } catch(PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Get all stories with lock status
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$where_clause = '';
if($filter == 'locked') {
    $where_clause = 'WHERE is_locked = 1';
} elseif($filter == 'unlocked') {
    $where_clause = 'WHERE is_locked = 0';
}

try {
    $query = "SELECT s.*, 
              (SELECT COUNT(*) FROM story_likes WHERE story_id = s.id) as like_count,
              (SELECT COUNT(*) FROM story_comments WHERE story_id = s.id) as comment_count
              FROM stories s
              $where_clause
              ORDER BY s.created_at DESC
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $stories = $stmt->fetchAll();

    // Get total count
    $count_query = "SELECT COUNT(*) FROM stories s $where_clause";
    $count_stmt = $db->query($count_query);
    $total_count = $count_stmt->fetchColumn();
    $total_pages = ceil($total_count / $per_page);

} catch(PDOException $e) {
    $stories = [];
    $total_pages = 0;
}

include 'views/header.php';
?>

<div class="min-h-screen bg-gh-bg py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gh-fg mb-2">Story Lock Management</h1>
            <p class="text-gh-muted">Lock or unlock stories to restrict content and disable interactions</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if($success): ?>
            <div class="mb-6 bg-green-500/10 border border-green-500 rounded-lg p-4">
                <div class="flex items-center gap-3">
                    <i class="bi bi-check-circle-fill text-green-500 text-xl"></i>
                    <p class="text-green-500 font-medium"><?php echo $success; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="mb-6 bg-red-500/10 border border-red-500 rounded-lg p-4">
                <div class="flex items-center gap-3">
                    <i class="bi bi-exclamation-triangle-fill text-red-500 text-xl"></i>
                    <p class="text-red-500 font-medium"><?php echo $error; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats & Filters -->
        <div class="bg-gh-panel border border-gh-border rounded-lg p-4 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gh-fg"><?php echo $total_count; ?></p>
                        <p class="text-xs text-gh-muted">Total Stories</p>
                    </div>
                </div>

                <div class="flex gap-2">
                    <a href="admin-story-locks.php?filter=all" 
                       class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $filter == 'all' ? 'bg-gh-accent text-white' : 'bg-gh-panel2 text-gh-muted hover:bg-gh-border'; ?>">
                        All Stories
                    </a>
                    <a href="admin-story-locks.php?filter=locked" 
                       class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $filter == 'locked' ? 'bg-red-500 text-white' : 'bg-gh-panel2 text-gh-muted hover:bg-gh-border'; ?>">
                        <i class="bi bi-lock-fill"></i> Locked
                    </a>
                    <a href="admin-story-locks.php?filter=unlocked" 
                       class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $filter == 'unlocked' ? 'bg-green-500 text-white' : 'bg-gh-panel2 text-gh-muted hover:bg-gh-border'; ?>">
                        <i class="bi bi-unlock-fill"></i> Unlocked
                    </a>
                </div>
            </div>
        </div>

        <!-- Stories Table -->
        <div class="bg-gh-panel border border-gh-border rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gh-panel2 border-b border-gh-border">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gh-muted uppercase">Story</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gh-muted uppercase">Stats</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gh-muted uppercase">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gh-muted uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gh-border">
                        <?php if(empty($stories)): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gh-muted">
                                    <i class="bi bi-inbox text-4xl mb-2"></i>
                                    <p>No stories found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($stories as $story): ?>
                                <tr class="hover:bg-gh-panel2 transition">
                                    <td class="px-4 py-4">
                                        <div class="flex items-start gap-3">
                                            <?php if($story['is_locked']): ?>
                                                <i class="bi bi-lock-fill text-red-500 text-lg mt-1"></i>
                                            <?php else: ?>
                                                <i class="bi bi-unlock-fill text-green-500 text-lg mt-1"></i>
                                            <?php endif; ?>
                                            <div class="flex-1 min-w-0">
                                                <a href="story-view.php?id=<?php echo $story['id']; ?>" 
                                                   target="_blank"
                                                   class="font-semibold text-gh-fg hover:text-gh-accent transition line-clamp-1">
                                                    <?php echo htmlspecialchars($story['title']); ?>
                                                </a>
                                                <p class="text-sm text-gh-muted mt-1">
                                                    By <?php echo htmlspecialchars($story['author_name']); ?> • 
                                                    <?php echo date('M d, Y', strtotime($story['created_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-col gap-1 text-sm text-gh-muted">
                                            <span><i class="bi bi-heart-fill text-pink-500"></i> <?php echo number_format($story['like_count']); ?></span>
                                            <span><i class="bi bi-chat-fill text-blue-500"></i> <?php echo number_format($story['comment_count']); ?></span>
                                            <span><i class="bi bi-eye-fill"></i> <?php echo number_format($story['views']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php if($story['is_locked']): ?>
                                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-red-500/20 text-red-500 text-xs font-semibold">
                                                <i class="bi bi-lock-fill"></i>
                                                LOCKED
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-green-500/20 text-green-500 text-xs font-semibold">
                                                <i class="bi bi-unlock-fill"></i>
                                                ACTIVE
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <form method="POST" class="inline" onsubmit="return confirm('<?php echo $story['is_locked'] ? 'Unlock' : 'Lock'; ?> this story?');">
                                                <input type="hidden" name="story_id" value="<?php echo $story['id']; ?>">
                                                <input type="hidden" name="action" value="<?php echo $story['is_locked'] ? 'unlock' : 'lock'; ?>">
                                                <?php if($story['is_locked']): ?>
                                                    <button type="submit" 
                                                            class="px-3 py-1.5 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-sm font-medium">
                                                        <i class="bi bi-unlock-fill"></i> Unlock
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" 
                                                            class="px-3 py-1.5 bg-red-500 text-white rounded-lg hover:bg-red-600 transition text-sm font-medium">
                                                        <i class="bi bi-lock-fill"></i> Lock
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                            <a href="story-view.php?id=<?php echo $story['id']; ?>" 
                                               target="_blank"
                                               class="px-3 py-1.5 bg-gh-panel2 text-gh-fg rounded-lg hover:bg-gh-border transition text-sm">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
            <div class="mt-6 flex items-center justify-center gap-2">
                <?php if($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>" 
                       class="px-4 py-2 bg-gh-panel border border-gh-border rounded-lg text-gh-fg hover:border-gh-accent transition">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>

                <span class="px-4 py-2 text-gh-muted">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </span>

                <?php if($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>" 
                       class="px-4 py-2 bg-gh-panel border border-gh-border rounded-lg text-gh-fg hover:border-gh-accent transition">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Info Panel -->
        <div class="mt-8 bg-blue-500/10 border border-blue-500 rounded-lg p-6">
            <h3 class="font-bold text-blue-500 mb-3 flex items-center gap-2">
                <i class="bi bi-info-circle-fill"></i>
                About Story Locking
            </h3>
            <ul class="space-y-2 text-sm text-gh-muted">
                <li class="flex items-start gap-2">
                    <i class="bi bi-check-circle-fill text-blue-500 mt-0.5"></i>
                    <span>Locked stories are blurred and cannot be liked or commented on</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="bi bi-check-circle-fill text-blue-500 mt-0.5"></i>
                    <span>Users can still see that the story exists but cannot view the full content</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="bi bi-check-circle-fill text-blue-500 mt-0.5"></i>
                    <span>Use this feature for content that violates guidelines or requires review</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="bi bi-check-circle-fill text-blue-500 mt-0.5"></i>
                    <span>Stories can be unlocked at any time to restore full functionality</span>
                </li>
            </ul>
        </div>

    </div>
</div>

<?php include 'views/footer.php'; ?>
