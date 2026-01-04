<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Forum.php';

$database = new Database();
$db = $database->getConnection();

$forum = new Forum($db);
$slug = $_GET['slug'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$sort = $_GET['sort'] ?? 'latest';

$category = $forum->getCategoryBySlug($slug);

if(!$category) {
    header('Location: forum.php');
    exit();
}

$threads = $forum->getThreads($category['id'], $page, 20, $sort);
$total_threads = $forum->getThreadCount($category['id']);
$total_pages = ceil($total_threads / 20);

include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-pink-900 to-red-900 py-12">
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDEzNGg3djdjMCAxLjEwNS0uODk1IDItMiAyaC0zYy0xLjEwNSAwLTItLjg5NS0yLTJ2LTd6bTAtMTRoN3Y3YzAgMS4xMDUtLjg5NSAyLTIgMmgtM2MtMS4xMDUgMC0yLS44OTUtMi0ydi03eiIvPjwvZz48L2c+PC9zdmc+')] opacity-10"></div>
    
    <div class="relative mx-auto max-w-6xl px-4">
        <!-- Breadcrumb -->
        <div class="mb-4 flex items-center gap-2 text-sm text-pink-200">
            <a href="forum.php" class="hover:text-white">
                <i class="bi bi-house-fill"></i> Forum
            </a>
            <i class="bi bi-chevron-right"></i>
            <span class="text-white"><?php echo htmlspecialchars($category['name']); ?></span>
        </div>

        <div class="flex items-center gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl text-3xl" 
                 style="background: <?php echo $category['color']; ?>; box-shadow: 0 10px 30px <?php echo $category['color']; ?>40;">
                <i class="<?php echo $category['icon'] ?? 'bi bi-chat-fill'; ?> text-white"></i>
            </div>
            <div class="flex-1">
                <h1 class="mb-2 text-4xl font-bold text-white md:text-5xl">
                    <?php echo htmlspecialchars($category['name']); ?>
                </h1>
                <p class="text-pink-200"><?php echo htmlspecialchars($category['description'] ?? ''); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-12">
    <div class="mx-auto max-w-6xl px-4">

        <!-- Action Bar -->
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <span class="text-sm text-gh-muted">
                    <i class="bi bi-chat-dots-fill mr-1"></i>
                    <?php echo number_format($total_threads); ?> Threads
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <select class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm text-white focus:border-gh-accent focus:outline-none" onchange="window.location.href='forum-category.php?slug=<?php echo $slug; ?>&sort='+this.value">
                    <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>Latest Activity</option>
                    <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                    <option value="replies" <?php echo $sort === 'replies' ? 'selected' : ''; ?>>Most Replies</option>
                </select>

                <?php if(isset($_SESSION['user_id'])): ?>
                <a href="forum-create-thread.php?category=<?php echo $category['id']; ?>" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-4 py-2 text-sm font-bold text-white shadow-lg transition-all hover:brightness-110">
                    <i class="bi bi-plus-circle-fill"></i>
                    New Thread
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Threads List -->
        <?php if(empty($threads)): ?>
        <div class="rounded-lg border border-gh-border bg-gh-panel p-12 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gh-border text-3xl text-gh-muted">
                <i class="bi bi-inbox"></i>
            </div>
            <h3 class="mb-2 text-xl font-bold text-white">No threads yet. Be the first to start a discussion!</h3>
            <?php if(isset($_SESSION['user_id'])): ?>
            <a href="forum-create-thread.php?category=<?php echo $category['id']; ?>" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-2 font-bold text-white">
                <i class="bi bi-plus-circle"></i>
                Create First Thread
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach($threads as $thread): ?>
            <a href="forum-thread.php?slug=<?php echo $thread['slug']; ?>" 
               class="group block rounded-lg border border-gh-border bg-gh-panel p-5 transition-all hover:border-gh-accent hover:shadow-lg">
                <div class="mb-3 flex items-start gap-4">
                    <div class="flex-1">
                        <h3 class="mb-2 text-lg font-bold text-white group-hover:text-gh-accent">
                            <?php if($thread['is_pinned']): ?>
                            <i class="bi bi-pin-angle-fill text-yellow-500"></i>
                            <?php endif; ?>
                            <?php if($thread['is_locked']): ?>
                            <i class="bi bi-lock-fill text-red-500"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($thread['title']); ?>
                        </h3>
                        <div class="flex flex-wrap items-center gap-3 text-xs text-gh-muted">
                            <span class="flex items-center gap-1">
                                <i class="bi bi-person-fill"></i>
                                <?php echo htmlspecialchars($thread['username']); ?>
                            </span>
                            <span class="flex items-center gap-1">
                                <i class="bi bi-clock-fill"></i>
                                <?php echo date('M d, Y', strtotime($thread['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex gap-4 text-sm text-gh-muted">
                        <span class="flex items-center gap-1">
                            <i class="bi bi-reply-fill text-gh-accent"></i>
                            <?php echo $thread['replies_count'] ?? 0; ?> replies
                        </span>
                        <span class="flex items-center gap-1">
                            <i class="bi bi-eye-fill text-blue-500"></i>
                            <?php echo $thread['views_count'] ?? 0; ?> views
                        </span>
                    </div>
                    <i class="bi bi-arrow-right text-gh-muted transition-transform group-hover:translate-x-1"></i>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="mt-8 flex justify-center">
            <nav class="flex gap-2">
                <?php if($page > 1): ?>
                <a href="?slug=<?php echo $slug; ?>&page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?>" class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php endif; ?>

                <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?slug=<?php echo $slug; ?>&page=<?php echo $i; ?>&sort=<?php echo $sort; ?>" 
                   class="rounded-lg border px-4 py-2 text-sm font-semibold transition-all <?php echo $i === $page ? 'border-gh-accent bg-gh-accent text-white' : 'border-gh-border bg-gh-panel text-white hover:border-gh-accent'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>

                <?php if($page < $total_pages): ?>
                <a href="?slug=<?php echo $slug; ?>&page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?>" class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'views/footer.php'; ?>
