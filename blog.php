<?php
session_start();
require_once 'config/database.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

// Check for maintenance mode
if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

// Fetch blog posts from forum (category: general, user: mike/id=1)
$blog_posts = [];
try {
    $query = "SELECT t.*, u.username, u.avatar, c.name as category_name,
              (SELECT content FROM forum_posts WHERE thread_id = t.id ORDER BY created_at ASC LIMIT 1) as first_post_content
              FROM forum_threads t
              LEFT JOIN users u ON t.user_id = u.id
              LEFT JOIN forum_categories c ON t.category_id = c.id
              WHERE c.slug = 'general' AND t.user_id = 1 AND t.is_deleted = FALSE
              ORDER BY t.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $blog_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Blog fetch error: " . $e->getMessage());
}

include 'views/header.php';
?>

<style>
:root {
  --gh-canvas-default: #0d1117;
  --gh-canvas-overlay: #161b22;
  --gh-fg-default: #e6edf3;
  --gh-fg-muted: #7d8590;
  --gh-border-default: #30363d;
  --gh-accent-fg: #2f81f7;
}

body {
  background: var(--gh-canvas-default);
  color: var(--gh-fg-default);
}

.blog-card {
    background: var(--gh-canvas-overlay);
    border: 1px solid var(--gh-border-default);
    border-radius: 12px;
    padding: 24px;
    transition: all 0.2s ease;
}

.blog-card:hover {
    border-color: var(--gh-accent-fg);
    transform: translateY(-2px);
}
</style>

<div class="min-h-screen py-12">
    <div class="mx-auto max-w-5xl px-4">
        <div class="mb-12 text-center">
            <div class="mb-4 inline-flex h-20 w-20 items-center justify-center rounded-full bg-gh-accent/10 text-4xl">
                📝
            </div>
            <h1 class="text-4xl font-bold text-white">trubl Blog</h1>
            <p class="mt-4 text-lg text-gh-muted">Updates and news from the trubl team</p>
        </div>

        <?php if(empty($blog_posts)): ?>
            <div class="rounded-xl border border-gh-border bg-gh-panel p-12 text-center">
                <i class="bi bi-journal-x text-5xl text-gh-muted mb-4"></i>
                <h3 class="text-xl font-bold text-white">No posts found</h3>
                <p class="text-gh-muted">Check back later for new updates!</p>
            </div>
        <?php else: ?>
            <div class="grid gap-8 md:grid-cols-2">
                <?php foreach($blog_posts as $post): ?>
                    <div class="blog-card flex flex-col">
                        <div class="mb-4 flex items-center justify-between">
                            <span class="rounded-full bg-gh-accent/20 px-3 py-1 text-xs font-bold text-gh-accent">
                                <?php echo htmlspecialchars($post['category_name']); ?>
                            </span>
                            <span class="text-xs text-gh-muted">
                                <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                            </span>
                        </div>
                        
                        <h2 class="mb-4 text-2xl font-bold text-white hover:text-gh-accent transition-colors">
                            <a href="forum-thread.php?slug=<?php echo htmlspecialchars($post['slug']); ?>">
                                <?php echo htmlspecialchars($post['title']); ?>
                            </a>
                        </h2>
                        
                        <div class="mb-6 flex-grow text-gh-muted line-clamp-3 leading-relaxed">
                            <?php 
                            $excerpt = strip_tags($post['first_post_content'] ?? '');
                            echo htmlspecialchars(substr($excerpt, 0, 200)) . (strlen($excerpt) > 200 ? '...' : ''); 
                            ?>
                        </div>
                        
                        <div class="flex items-center justify-between pt-6 border-t border-gh-border">
                            <div class="flex items-center gap-2">
                                <div class="h-8 w-8 rounded-full bg-gradient-to-br from-pink-500 to-purple-600 flex items-center justify-center text-xs font-bold text-white">
                                    <?php echo strtoupper(substr($post['username'], 0, 1)); ?>
                                </div>
                                <span class="text-sm font-medium text-gh-fg"><?php echo htmlspecialchars($post['username']); ?></span>
                            </div>
                            <a href="forum-thread.php?slug=<?php echo htmlspecialchars($post['slug']); ?>" class="text-sm font-bold text-gh-accent hover:underline">
                                Read More →
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'views/footer.php'; ?>
