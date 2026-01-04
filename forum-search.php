<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Forum.php';

$database = new Database();
$db = $database->getConnection();

$forum = new Forum($db);
$search_query = trim($_GET['q'] ?? '');
$page = (int)($_GET['page'] ?? 1);

$results = [];
$total_results = 0;

if($search_query) {
    // Search threads
    $query = "SELECT t.*, u.username, c.name as category_name, c.slug as category_slug, c.color 
              FROM forum_threads t 
              LEFT JOIN users u ON t.user_id = u.id 
              LEFT JOIN forum_categories c ON t.category_id = c.id 
              WHERE (t.title LIKE :search OR t.content LIKE :search) 
              AND t.is_deleted = FALSE 
              ORDER BY t.created_at DESC 
              LIMIT :offset, 20";
    
    $stmt = $db->prepare($query);
    $search_param = '%' . $search_query . '%';
    $offset = ($page - 1) * 20;
    $stmt->bindParam(':search', $search_param);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    // Get total count
    $count_query = "SELECT COUNT(*) FROM forum_threads t 
                    WHERE (t.title LIKE :search OR t.content LIKE :search) 
                    AND t.is_deleted = FALSE";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->bindParam(':search', $search_param);
    $count_stmt->execute();
    $total_results = $count_stmt->fetchColumn();
}

$total_pages = ceil($total_results / 20);

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
            <span class="text-white">Search</span>
        </div>

        <div class="text-center">
            <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-white/10 text-3xl backdrop-blur-sm">
                <i class="bi bi-search text-pink-300"></i>
            </div>
            <h1 class="mb-4 text-4xl font-bold text-white md:text-5xl">
                Search Forum
            </h1>
            
            <!-- Search Bar -->
            <form action="forum-search.php" method="GET" class="mx-auto max-w-2xl">
                <div class="relative">
                    <input 
                        type="text" 
                        name="q"
                        value="<?php echo htmlspecialchars($search_query); ?>"
                        placeholder="Enter keywords to find discussions..." 
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
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-12">
    <div class="mx-auto max-w-6xl px-4">

        <?php if($search_query): ?>
            <!-- Results Header -->
            <div class="mb-6">
                <h2 class="mb-2 text-2xl font-bold text-white">
                    <?php if($total_results > 0): ?>
                        Found <?php echo number_format($total_results); ?> result<?php echo $total_results != 1 ? 's' : ''; ?>
                    <?php else: ?>
                        No results found
                    <?php endif; ?>
                </h2>
                <p class="text-gh-muted">
                    Searching for: <span class="font-semibold text-white">"<?php echo htmlspecialchars($search_query); ?>"</span>
                </p>
            </div>

            <?php if(empty($results)): ?>
            <!-- No Results -->
            <div class="rounded-lg border border-gh-border bg-gh-panel p-12 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gh-border text-3xl text-gh-muted">
                    <i class="bi bi-search"></i>
                </div>
                <h3 class="mb-2 text-xl font-bold text-white">No threads found</h3>
                <p class="mb-6 text-sm text-gh-muted">
                    Try different keywords or browse our categories to find what you're looking for.
                </p>
                <div class="flex flex-wrap justify-center gap-3">
                    <a href="forum.php" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-2 font-bold text-white">
                        <i class="bi bi-arrow-left"></i>
                        Back to Forum
                    </a>
                    <a href="forum-create-thread.php" class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-6 py-2 font-bold text-white transition-all hover:border-gh-accent">
                        <i class="bi bi-plus-circle"></i>
                        Start New Thread
                    </a>
                </div>
            </div>
            <?php else: ?>
            <!-- Results List -->
            <div class="space-y-3">
                <?php foreach($results as $thread): ?>
                <a href="forum-thread.php?slug=<?php echo $thread['slug']; ?>" 
                   class="group block rounded-lg border border-gh-border bg-gh-panel p-5 transition-all hover:border-gh-accent hover:shadow-lg">
                    <div class="mb-3 flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <h3 class="mb-2 text-lg font-bold text-white group-hover:text-gh-accent">
                                <?php if($thread['is_pinned']): ?>
                                <i class="bi bi-pin-angle-fill text-yellow-500"></i>
                                <?php endif; ?>
                                <?php 
                                // Highlight search term in title
                                $highlighted_title = str_ireplace(
                                    $search_query, 
                                    '<mark class="bg-yellow-500/30 text-yellow-300">' . $search_query . '</mark>', 
                                    htmlspecialchars($thread['title'])
                                );
                                echo $highlighted_title;
                                ?>
                            </h3>
                            
                            <!-- Snippet -->
                            <p class="mb-3 text-sm text-gh-muted line-clamp-2">
                                <?php 
                                $content_snippet = substr(strip_tags($thread['content']), 0, 200);
                                $highlighted_snippet = str_ireplace(
                                    $search_query, 
                                    '<mark class="bg-yellow-500/30 text-yellow-300">' . $search_query . '</mark>', 
                                    htmlspecialchars($content_snippet)
                                );
                                echo $highlighted_snippet . '...';
                                ?>
                            </p>

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
                    <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>" class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold text-white transition-all hover:border-gh-accent">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <?php endif; ?>

                    <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>" 
                       class="rounded-lg border px-4 py-2 text-sm font-semibold transition-all <?php echo $i === $page ? 'border-gh-accent bg-gh-accent text-white' : 'border-gh-border bg-gh-panel text-white hover:border-gh-accent'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if($page < $total_pages): ?>
                    <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>" class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold text-white transition-all hover:border-gh-accent">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
            <?php endif; ?>

        <?php else: ?>
        <!-- Empty State -->
        <div class="rounded-lg border border-gh-border bg-gh-panel p-12 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-pink-500 to-purple-600 text-3xl text-white">
                <i class="bi bi-search"></i>
            </div>
            <h3 class="mb-2 text-xl font-bold text-white">Start Searching</h3>
            <p class="mb-6 text-sm text-gh-muted">
                Enter keywords above to find discussions, topics, or posts
            </p>
            
            <!-- Popular Topics -->
            <div class="mx-auto max-w-2xl">
                <h4 class="mb-4 text-sm font-semibold text-white">Popular Search Topics:</h4>
                <div class="flex flex-wrap justify-center gap-2">
                    <a href="?q=dating" class="rounded-full border border-gh-border bg-gh-bg px-4 py-2 text-sm text-white transition-all hover:border-gh-accent hover:text-gh-accent">
                        Dating
                    </a>
                    <a href="?q=tips" class="rounded-full border border-gh-border bg-gh-bg px-4 py-2 text-sm text-white transition-all hover:border-gh-accent hover:text-gh-accent">
                        Tips
                    </a>
                    <a href="?q=safety" class="rounded-full border border-gh-border bg-gh-bg px-4 py-2 text-sm text-white transition-all hover:border-gh-accent hover:text-gh-accent">
                        Safety
                    </a>
                    <a href="?q=stories" class="rounded-full border border-gh-border bg-gh-bg px-4 py-2 text-sm text-white transition-all hover:border-gh-accent hover:text-gh-accent">
                        Stories
                    </a>
                    <a href="?q=advice" class="rounded-full border border-gh-border bg-gh-bg px-4 py-2 text-sm text-white transition-all hover:border-gh-accent hover:text-gh-accent">
                        Advice
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Back to Forum -->
        <div class="mt-8 text-center">
            <a href="forum.php" class="inline-flex items-center gap-2 text-sm text-gh-accent transition-colors hover:text-gh-success">
                <i class="bi bi-arrow-left"></i>
                Back to Forum Home
            </a>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
