<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Forum.php';

$database = new Database();
$db = $database->getConnection();

$forum = new Forum($db);
$categories = $forum->getCategories();

// Get recent threads across all categories
$query = "SELECT t.*, u.username, c.name as category_name, c.slug as category_slug, c.color 
          FROM forum_threads t 
          LEFT JOIN users u ON t.user_id = u.id 
          LEFT JOIN forum_categories c ON t.category_id = c.id 
          WHERE t.is_deleted = FALSE 
          ORDER BY t.created_at DESC LIMIT 10";
$stmt = $db->query($query);
$recent_threads = $stmt->fetchAll();

// Get forum stats
$query = "SELECT 
            (SELECT COUNT(*) FROM forum_threads WHERE is_deleted = FALSE) as total_threads,
            (SELECT COUNT(*) FROM forum_posts WHERE is_deleted = FALSE) as total_posts,
            (SELECT COUNT(DISTINCT user_id) FROM forum_threads) as total_members";
$stmt = $db->query($query);
$stats = $stmt->fetch();

include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-pink-900 to-red-900 py-12">
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDEzNGg3djdjMCAxLjEwNS0uODk1IDItMiAyaC0zYy0xLjEwNSAwLTItLjg5NS0yLTJ2LTd6bTAtMTRoN3Y3YzAgMS4xMDUtLjg5NSAyLTIgMmgtM2MtMS4xMDUgMC0yLS44OTUtMi0ydi03eiIvPjwvZz48L2c+PC9zdmc+')] opacity-10"></div>
    
    <div class="relative mx-auto max-w-6xl px-4 text-center">
        <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-white/10 text-3xl backdrop-blur-sm">
            <i class="bi bi-chat-square-dots-fill text-pink-300"></i>
        </div>
        <h1 class="mb-3 bg-gradient-to-r from-white via-pink-200 to-white bg-clip-text text-4xl font-bold text-transparent md:text-5xl">
            Community Forum
        </h1>
        <p class="mb-4 text-base text-pink-200">Connect, discuss, and share with the trubl community</p>
        
        <!-- Search Bar -->
        <form action="forum-search.php" method="GET" class="mx-auto max-w-2xl">
            <div class="relative">
                <input 
                    type="text" 
                    name="q"
                    placeholder="Search discussions..." 
                    class="w-full rounded-lg border border-white/30 bg-white/10 px-5 py-3 pl-12 text-white placeholder-pink-200 backdrop-blur-sm focus:border-white focus:outline-none focus:ring-2 focus:ring-white/20"
                >
                <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-pink-200"></i>
                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg bg-white px-4 py-1.5 text-sm font-bold text-pink-600 transition-all hover:scale-105">
                    Search
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-12">
    <div class="mx-auto max-w-7xl px-4">
       <!-- Footer style -->
<div class="flex items-center justify-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-4 py-2">
    <i class="bi bi-robot text-gh-accent"></i>
    <span class="text-xs font-semibold text-gh-fg">AI Moderation Powered by</span>
    <span class="bg-gradient-to-r from-pink-600 to-purple-600 bg-clip-text text-xs font-bold text-transparent">
        Perplexity Sonar Pro
    </span>
</div>
<br>
        <!-- Stats -->
        <div class="mb-8 grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-gh-border bg-gh-panel p-6 text-center transition-all hover:border-gh-accent">
                <div class="mb-2 text-4xl font-bold text-gh-accent"><?php echo number_format($stats['total_threads']); ?></div>
                <div class="flex items-center justify-center gap-2 text-sm font-semibold text-gh-muted">
                    <i class="bi bi-chat-dots-fill"></i>
                    Discussions
                </div>
            </div>
            <div class="rounded-lg border border-gh-border bg-gh-panel p-6 text-center transition-all hover:border-gh-success">
                <div class="mb-2 text-4xl font-bold text-gh-success"><?php echo number_format($stats['total_posts']); ?></div>
                <div class="flex items-center justify-center gap-2 text-sm font-semibold text-gh-muted">
                    <i class="bi bi-reply-fill"></i>
                    Posts
                </div>
            </div>
            <div class="rounded-lg border border-gh-border bg-gh-panel p-6 text-center transition-all hover:border-purple-500">
                <div class="mb-2 text-4xl font-bold text-purple-400"><?php echo number_format($stats['total_members']); ?></div>
                <div class="flex items-center justify-center gap-2 text-sm font-semibold text-gh-muted">
                    <i class="bi bi-people-fill"></i>
                    Members
                </div>
            </div>
        </div>

        <div class="grid gap-8 lg:grid-cols-3">
            
            <!-- Categories Sidebar -->
            <div class="lg:col-span-1">
                <div class="sticky top-4 space-y-4">
                    
                    <!-- New Thread Button -->
                    <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="forum-create-thread.php" class="group flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-3 font-bold text-white shadow-lg transition-all hover:brightness-110">
                        <i class="bi bi-plus-circle-fill"></i>
                        Start New Thread
                    </a>
                    <?php else: ?>
                    <a href="login.php?redirect=forum-create-thread.php" class="group flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-3 font-bold text-white shadow-lg transition-all hover:brightness-110">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Login to Post
                    </a>
                    <?php endif; ?>

                    <!-- Categories -->
                    <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
                        <h3 class="mb-4 flex items-center gap-2 text-lg font-bold text-white">
                            <i class="bi bi-grid-fill text-gh-accent"></i>
                            Categories
                        </h3>
                        <div class="space-y-2">
                            <?php foreach($categories as $category): ?>
                            <a href="forum-category.php?slug=<?php echo $category['slug']; ?>" 
                               class="group flex items-center justify-between rounded-lg border border-gh-border bg-gh-bg p-3 transition-all hover:border-gh-accent">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg text-lg" 
                                         style="background: <?php echo $category['color']; ?>20; color: <?php echo $category['color']; ?>">
                                        <i class="<?php echo $category['icon'] ?? 'bi bi-chat-fill'; ?>"></i>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-white"><?php echo htmlspecialchars($category['name']); ?></div>
                                        <div class="text-xs text-gh-muted"><?php echo $category['thread_count'] ?? 0; ?> threads</div>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-gh-muted transition-transform group-hover:translate-x-1"></i>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Forum Rules -->
                    <div class="rounded-lg border border-yellow-500/30 bg-gradient-to-br from-yellow-600/10 to-orange-600/10 p-6">
                        <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-white">
                            <i class="bi bi-shield-check text-yellow-500"></i>
                            Community Guidelines
                        </h3>
                        <ul class="space-y-2 text-xs text-gh-muted">
                            <li class="flex items-start gap-2">
                                <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
                                <span>Be respectful and courteous</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
                                <span>No spam or self-promotion</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
                                <span>Keep discussions on-topic</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
                                <span>Report rule violations</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Recent Threads -->
            <div class="lg:col-span-2">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-white">
                        <i class="bi bi-clock-history mr-2 text-gh-accent"></i>
                        Recent Discussions
                    </h2>
                    <select class="rounded-lg border border-gh-border bg-gh-bg px-4 py-2 text-sm text-white focus:border-gh-accent focus:outline-none" onchange="window.location.href='forum.php?sort='+this.value">
                        <option value="latest">Latest Activity</option>
                        <option value="popular">Most Popular</option>
                        <option value="replies">Most Replies</option>
                    </select>
                </div>

                <?php if(empty($recent_threads)): ?>
                <div class="rounded-lg border border-gh-border bg-gh-panel p-12 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gh-border text-3xl text-gh-muted">
                        <i class="bi bi-chat-dots"></i>
                    </div>
                    <h3 class="mb-2 text-xl font-bold text-white">No Discussions Yet</h3>
                    <p class="mb-6 text-sm text-gh-muted">Be the first to start a discussion!</p>
                    <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="forum-create-thread.php" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-2 font-bold text-white">
                        <i class="bi bi-plus-circle"></i>
                        Start First Thread
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach($recent_threads as $thread): ?>
                    <a href="forum-thread.php?slug=<?php echo $thread['slug']; ?>" 
                       class="group block rounded-lg border border-gh-border bg-gh-panel p-5 transition-all hover:border-gh-accent hover:shadow-lg">
                        <div class="mb-3 flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <h3 class="mb-2 text-lg font-bold text-white group-hover:text-gh-accent">
                                    <?php if($thread['is_pinned']): ?>
                                    <i class="bi bi-pin-angle-fill text-yellow-500"></i>
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
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5" 
                                          style="background: <?php echo $thread['color']; ?>20; color: <?php echo $thread['color']; ?>">
                                        <i class="bi bi-tag-fill"></i>
                                        <?php echo htmlspecialchars($thread['category_name']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex gap-4 text-sm text-gh-muted">
                                <span class="flex items-center gap-1">
                                    <i class="bi bi-reply-fill text-gh-accent"></i>
                                    <?php echo $thread['reply_count'] ?? 0; ?> replies
                                </span>
                                <span class="flex items-center gap-1">
                                    <i class="bi bi-eye-fill text-blue-500"></i>
                                    <?php echo $thread['view_count'] ?? 0; ?> views
                                </span>
                            </div>
                            <i class="bi bi-arrow-right text-gh-muted transition-transform group-hover:translate-x-1"></i>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- View All Button -->
                <div class="mt-6 text-center">
                    <a href="forum-category.php" class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-6 py-3 font-semibold text-white transition-all hover:border-gh-accent">
                        View All Discussions
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
