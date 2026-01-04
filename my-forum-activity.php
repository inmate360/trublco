<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Forum.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$forum = new Forum($db);

// Get user stats
$query = "SELECT * FROM forum_user_stats WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$stats = $stmt->fetch();

// Get user threads
$query = "SELECT t.*, c.name as category_name, c.slug as category_slug, c.color
          FROM forum_threads t
          LEFT JOIN forum_categories c ON t.category_id = c.id
          WHERE t.user_id = :user_id AND t.is_deleted = FALSE
          ORDER BY t.created_at DESC
          LIMIT 20";
$stmt = $db->prepare($query);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$my_threads = $stmt->fetchAll();

// Get user posts
$query = "SELECT p.*, t.title as thread_title, t.slug as thread_slug
          FROM forum_posts p
          LEFT JOIN forum_threads t ON p.thread_id = t.id
          WHERE p.user_id = :user_id AND p.is_deleted = FALSE
          ORDER BY p.created_at DESC
          LIMIT 20";
$stmt = $db->prepare($query);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$my_posts = $stmt->fetchAll();

// Get subscriptions
$query = "SELECT t.*, c.name as category_name, c.slug as category_slug
          FROM forum_subscriptions fs
          LEFT JOIN forum_threads t ON fs.thread_id = t.id
          LEFT JOIN forum_categories c ON t.category_id = c.id
          WHERE fs.user_id = :user_id AND t.is_deleted = FALSE
          ORDER BY t.last_reply_at DESC
          LIMIT 20";
$stmt = $db->prepare($query);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$subscriptions = $stmt->fetchAll();

$tab = $_GET['tab'] ?? 'threads';

include 'views/header.php';
?>

<style>
.dashboard-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.dashboard-header {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 1.5rem;
    border-radius: 12px;
    text-align: center;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stat-label {
    opacity: 0.9;
}

.tabs-nav {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid var(--border-color);
    flex-wrap: wrap;
}

.tab-btn {
    padding: 1rem 1.5rem;
    background: transparent;
    border: none;
    color: var(--text-gray);
    cursor: pointer;
    transition: all 0.2s;
    border-bottom: 3px solid transparent;
    font-weight: 600;
}

.tab-btn.active {
    color: var(--primary-blue);
    border-bottom-color: var(--primary-blue);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.thread-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s;
}

.thread-card:hover {
    border-color: var(--primary-blue);
    transform: translateX(5px);
}

.thread-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    gap: 1rem;
}

.thread-card-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-white);
    text-decoration: none;
    display: block;
    margin-bottom: 0.5rem;
}

.thread-card-title:hover {
    color: var(--primary-blue);
}

.thread-actions {
    display: flex;
    gap: 0.5rem;
}

.action-icon-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 2px solid var(--border-color);
    background: transparent;
    color: var(--text-gray);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.action-icon-btn:hover {
    border-color: var(--primary-blue);
    color: var(--primary-blue);
}

.post-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.post-excerpt {
    color: var(--text-gray);
    margin: 1rem 0;
    line-height: 1.6;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state i {
    font-size: 5rem;
    opacity: 0.3;
    margin-bottom: 1rem;
}
</style>

<div class="dashboard-container">
    
    <!-- Header -->
    <div class="dashboard-header">
        <h1><i class="bi bi-person-circle"></i> My Forum Activity</h1>
        <p style="margin: 0.5rem 0 0; opacity: 0.9;">Manage your threads, posts, and subscriptions</p>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['threads_count'] ?? 0); ?></div>
                <div class="stat-label">Threads Created</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['posts_count'] ?? 0); ?></div>
                <div class="stat-label">Posts Made</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['reactions_received'] ?? 0); ?></div>
                <div class="stat-label">Reactions Received</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['reputation_score'] ?? 0); ?></div>
                <div class="stat-label">Reputation</div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="tabs-nav">
        <button class="tab-btn <?php echo $tab === 'threads' ? 'active' : ''; ?>" 
                onclick="switchTab('threads')">
            <i class="bi bi-file-text"></i> My Threads (<?php echo count($my_threads); ?>)
        </button>
        <button class="tab-btn <?php echo $tab === 'posts' ? 'active' : ''; ?>" 
                onclick="switchTab('posts')">
            <i class="bi bi-chat-dots"></i> My Posts (<?php echo count($my_posts); ?>)
        </button>
        <button class="tab-btn <?php echo $tab === 'subscriptions' ? 'active' : ''; ?>" 
                onclick="switchTab('subscriptions')">
            <i class="bi bi-bell"></i> Subscriptions (<?php echo count($subscriptions); ?>)
        </button>
    </div>

    <!-- My Threads Tab -->
    <div class="tab-content <?php echo $tab === 'threads' ? 'active' : ''; ?>" id="threads-tab">
        <?php if(empty($my_threads)): ?>
        <div class="empty-state">
            <i class="bi bi-file-text"></i>
            <h3>No threads yet</h3>
            <p class="text-muted">You haven't created any threads yet</p>
            <a href="/forum-create-thread.php" class="btn btn-primary mt-3">
                <i class="bi bi-plus-circle"></i> Create Your First Thread
            </a>
        </div>
        <?php else: ?>
            <?php foreach($my_threads as $thread): ?>
            <div class="thread-card">
                <div class="thread-card-header">
                    <div style="flex: 1;">
                        <a href="/forum-thread.php?slug=<?php echo $thread['slug']; ?>" class="thread-card-title">
                            <?php echo htmlspecialchars($thread['title']); ?>
                        </a>
                        <div class="d-flex gap-3 flex-wrap" style="font-size: 0.9rem; color: var(--text-gray);">
                            <span style="color: <?php echo $thread['color']; ?>">
                                <i class="bi bi-folder-fill"></i>
                                <?php echo htmlspecialchars($thread['category_name']); ?>
                            </span>
                            <span>
                                <i class="bi bi-chat-dots"></i>
                                <?php echo number_format($thread['replies_count']); ?> replies
                            </span>
                            <span>
                                <i class="bi bi-eye"></i>
                                <?php echo number_format($thread['views']); ?> views
                            </span>
                            <span>
                                <i class="bi bi-clock"></i>
                                <?php echo date('M j, Y', strtotime($thread['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="thread-actions">
                        <a href="/forum-edit-thread.php?id=<?php echo $thread['id']; ?>" 
                           class="action-icon-btn" 
                           title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button class="action-icon-btn" 
                                onclick="deleteThread(<?php echo $thread['id']; ?>)" 
                                title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- My Posts Tab -->
    <div class="tab-content <?php echo $tab === 'posts' ? 'active' : ''; ?>" id="posts-tab">
        <?php if(empty($my_posts)): ?>
        <div class="empty-state">
            <i class="bi bi-chat-dots"></i>
            <h3>No posts yet</h3>
            <p class="text-muted">You haven't posted any replies yet</p>
            <a href="/forum.php" class="btn btn-primary mt-3">
                <i class="bi bi-search"></i> Browse Forum
            </a>
        </div>
        <?php else: ?>
            <?php foreach($my_posts as $post): ?>
            <div class="post-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <a href="/forum-thread.php?slug=<?php echo $post['thread_slug']; ?>#post-<?php echo $post['id']; ?>" 
                       style="font-weight: 600; color: var(--text-white); text-decoration: none;">
                        Re: <?php echo htmlspecialchars($post['thread_title']); ?>
                    </a>
                    <span class="text-muted" style="font-size: 0.85rem;">
                        <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                    </span>
                </div>
                <div class="post-excerpt">
                    <?php 
                    $excerpt = strip_tags($post['content']);
                    echo htmlspecialchars(substr($excerpt, 0, 200));
                    echo strlen($excerpt) > 200 ? '...' : '';
                    ?>
                </div>
                <div class="d-flex gap-2">
                    <a href="/forum-thread.php?slug=<?php echo $post['thread_slug']; ?>#post-<?php echo $post['id']; ?>" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right"></i> View
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Subscriptions Tab -->
    <div class="tab-content <?php echo $tab === 'subscriptions' ? 'active' : ''; ?>" id="subscriptions-tab">
        <?php if(empty($subscriptions)): ?>
        <div class="empty-state">
            <i class="bi bi-bell"></i>
            <h3>No subscriptions</h3>
            <p class="text-muted">You're not subscribed to any threads yet</p>
        </div>
        <?php else: ?>
            <?php foreach($subscriptions as $thread): ?>
            <div class="thread-card">
                <div class="thread-card-header">
                    <div style="flex: 1;">
                        <a href="/forum-thread.php?slug=<?php echo $thread['slug']; ?>" class="thread-card-title">
                            <?php echo htmlspecialchars($thread['title']); ?>
                        </a>
                        <div class="d-flex gap-3 flex-wrap" style="font-size: 0.9rem; color: var(--text-gray);">
                            <span>
                                <i class="bi bi-folder-fill"></i>
                                <?php echo htmlspecialchars($thread['category_name']); ?>
                            </span>
                            <span>
                                <i class="bi bi-chat-dots"></i>
                                <?php echo number_format($thread['replies_count']); ?> replies
                            </span>
                            <span>
                                <i class="bi bi-clock"></i>
                                Last activity <?php echo date('M j, Y', strtotime($thread['last_reply_at'] ?? $thread['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                    <button class="action-icon-btn" 
                            onclick="unsubscribe(<?php echo $thread['id']; ?>)" 
                            title="Unsubscribe">
                        <i class="bi bi-bell-slash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function switchTab(tab) {
    // Update URL
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.pushState({}, '', url);
    
    // Update buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.closest('.tab-btn').classList.add('active');
    
    // Update content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(tab + '-tab').classList.add('active');
}

function deleteThread(threadId) {
    if(!confirm('Are you sure you want to delete this thread? This action cannot be undone.')) {
        return;
    }
    
    fetch('/api/forum.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete_thread&thread_id=' + threadId
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to delete thread');
        }
    });
}

function unsubscribe(threadId) {
    fetch('/api/forum.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=unsubscribe_thread&thread_id=' + threadId
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            location.reload();
        }
    });
}
</script>

<?php include 'views/footer.php'; ?>