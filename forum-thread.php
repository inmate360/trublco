<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Forum.php';

$database = new Database();
$db = $database->getConnection();

$forum = new Forum($db);
$slug = $_GET['slug'] ?? '';
$page = (int)($_GET['page'] ?? 1);

$thread = $forum->getThreadBySlug($slug);

if(!$thread) {
    header('Location: forum.php');
    exit();
}

// Add default values for missing fields
$thread['is_locked'] = $thread['is_locked'] ?? false;
$thread['is_pinned'] = $thread['is_pinned'] ?? false;
$thread['views_count'] = $thread['views_count'] ?? 0;
$thread['replies_count'] = $thread['replies_count'] ?? 0;

// Increment views
$forum->incrementViews($thread['id']);

// Get posts
$posts = $forum->getPosts($thread['id'], $page, 20);
$total_posts = $thread['replies_count'];
$total_pages = ceil(($total_posts + 1) / 20);

// Handle new reply
$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    if($thread['is_locked']) {
        $error = 'This thread is locked and cannot accept new replies.';
    } else {
        $content = trim($_POST['content'] ?? '');
        
        if(empty($content)) {
            $error = 'Please enter a reply';
        } elseif(strlen($content) < 5) {
            $error = 'Reply must be at least 5 characters';
        } else {
            $result = $forum->createPost($_SESSION['user_id'], $thread['id'], $content);
            if($result['success']) {
                $success = 'Reply posted successfully!';
                header('Location: forum-thread.php?slug=' . $slug . '#post-' . $result['post_id']);
                exit();
            } else {
                $error = $result['error'] ?? 'Failed to post reply';
            }
        }
    }
}

include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-pink-900 to-red-900 py-8">
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDEzNGg3djdjMCAxLjEwNS0uODk1IDItMiAyaC0zYy0xLjEwNSAwLTItLjg5NS0yLTJ2LTd6bTAtMTRoN3Y3YzAgMS4xMDUtLjg5NSAyLTIgMmgtM2MtMS4xMDUgMC0yLS44OTUtMi0ydi03eiIvPjwvZz48L2c+PC9zdmc+')] opacity-10"></div>
    
    <div class="relative mx-auto max-w-6xl px-4">
        <!-- Breadcrumb -->
        <div class="mb-4 flex items-center gap-2 text-sm text-pink-200">
            <a href="forum.php" class="hover:text-white">
                <i class="bi bi-house-fill"></i> Forum
            </a>
            <i class="bi bi-chevron-right"></i>
            <a href="forum-category.php?slug=<?php echo $thread['category_slug']; ?>" class="hover:text-white">
                <?php echo htmlspecialchars($thread['category_name']); ?>
            </a>
            <i class="bi bi-chevron-right"></i>
            <span class="text-white">Thread</span>
        </div>

        <div class="flex items-start gap-4">
            <div class="flex-1">
                <h1 class="mb-3 text-3xl font-bold text-white md:text-4xl">
                    <?php if(!empty($thread['is_pinned'])): ?>
                    <i class="bi bi-pin-angle-fill text-yellow-300"></i>
                    <?php endif; ?>
                    <?php if(!empty($thread['is_locked'])): ?>
                    <i class="bi bi-lock-fill text-red-300"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($thread['title']); ?>
                </h1>
                <div class="flex flex-wrap items-center gap-4 text-sm text-pink-200">
                    <span class="flex items-center gap-1">
                        <i class="bi bi-person-fill"></i>
                        <?php echo htmlspecialchars($thread['username']); ?>
                    </span>
                    <span class="flex items-center gap-1">
                        <i class="bi bi-clock-fill"></i>
                        <?php echo date('M d, Y \a\t g:i A', strtotime($thread['created_at'])); ?>
                    </span>
                    <span class="flex items-center gap-1">
                        <i class="bi bi-eye-fill"></i>
                        <?php echo number_format($thread['views_count']); ?> views
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5" 
                          style="background: <?php echo $thread['category_color']; ?>40; color: <?php echo $thread['category_color']; ?>">
                        <i class="bi bi-tag-fill"></i>
                        <?php echo htmlspecialchars($thread['category_name']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admin Controls -->
<?php if(isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'moderator'])): ?>
<div class="bg-gh-bg py-4">
    <div class="mx-auto max-w-6xl px-4">
        <div class="rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-4">
            <div class="mb-3 flex items-center gap-2 text-yellow-400">
                <i class="bi bi-shield-fill-check"></i>
                <span class="font-bold">Admin Controls</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <button onclick="adminAction('toggle_pin', <?php echo $thread['id']; ?>)" 
                        class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-pin-angle-fill"></i>
                    <?php echo $thread['is_pinned'] ? 'Unpin' : 'Pin'; ?> Thread
                </button>
                
                <button onclick="adminAction('toggle_lock', <?php echo $thread['id']; ?>)" 
                        class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-lock-fill"></i>
                    <?php echo $thread['is_locked'] ? 'Unlock' : 'Lock'; ?> Thread
                </button>
                
                <button onclick="showMoveModal()" 
                        class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-arrow-right-square-fill"></i>
                    Move to Category
                </button>
                
                <button onclick="showEditModal()" 
                        class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-pencil-square"></i>
                    Edit Thread
                </button>
                
                <button onclick="if(confirm('Delete this thread? This cannot be undone.')) adminAction('delete_thread', <?php echo $thread['id']; ?>)" 
                        class="inline-flex items-center gap-2 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm font-semibold text-red-400 transition-all hover:border-red-500">
                    <i class="bi bi-trash-fill"></i>
                    Delete Thread
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Content -->
<div class="bg-gh-bg py-12">
    <div class="mx-auto max-w-6xl px-4">

        <?php if($success): ?>
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-green-500/30 bg-green-500/10 p-4">
            <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
            <div>
                <p class="font-semibold text-green-400">Success!</p>
                <p class="text-sm text-green-300"><?php echo htmlspecialchars($success); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/10 p-4">
            <i class="bi bi-exclamation-circle-fill mt-0.5 text-red-500"></i>
            <div>
                <p class="font-semibold text-red-400">Error</p>
                <p class="text-sm text-red-300"><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Original Post -->
        <div class="mb-6 rounded-lg border border-gh-border bg-gh-panel p-6" id="original-post">
            <div class="mb-4 flex items-start gap-4">
                <div class="shrink-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-pink-500 to-purple-600 text-lg font-bold text-white">
                        <?php echo strtoupper(substr($thread['username'], 0, 1)); ?>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="mb-1 flex items-center gap-2">
                        <span class="font-bold text-white"><?php echo htmlspecialchars($thread['username']); ?></span>
                        <?php if(($thread['user_role'] ?? '') == 'admin'): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-red-500/20 px-2 py-0.5 text-xs font-semibold text-red-400">
                            <i class="bi bi-shield-fill-check"></i>
                            Admin
                        </span>
                        <?php elseif(($thread['user_role'] ?? '') == 'moderator'): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-500/20 px-2 py-0.5 text-xs font-semibold text-blue-400">
                            <i class="bi bi-shield-check"></i>
                            Moderator
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-gh-muted">
                        <?php echo date('M d, Y \a\t g:i A', strtotime($thread['created_at'])); ?>
                    </div>
                </div>
            </div>
            
            <div class="prose prose-invert max-w-none">
                <p class="text-white leading-relaxed whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($thread['content'])); ?></p>
            </div>

            <!-- Thread Actions -->
            <div class="mt-4 flex flex-wrap gap-2 border-t border-gh-border pt-4">
                <button class="inline-flex items-center gap-1 rounded-lg border border-gh-border bg-gh-bg px-3 py-1.5 text-sm text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-hand-thumbs-up"></i>
                    Like
                </button>
                <button class="inline-flex items-center gap-1 rounded-lg border border-gh-border bg-gh-bg px-3 py-1.5 text-sm text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-reply-fill"></i>
                    Reply
                </button>
                <button class="inline-flex items-center gap-1 rounded-lg border border-gh-border bg-gh-bg px-3 py-1.5 text-sm text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-share-fill"></i>
                    Share
                </button>
            </div>
        </div>

        <!-- Replies Header -->
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-xl font-bold text-white">
                <i class="bi bi-reply-all-fill mr-2 text-gh-accent"></i>
                <?php echo number_format($total_posts); ?> Replies
            </h2>
            <select class="rounded-lg border border-gh-border bg-gh-panel px-3 py-1.5 text-sm text-white focus:border-gh-accent focus:outline-none">
                <option>Oldest First</option>
                <option>Newest First</option>
                <option>Most Liked</option>
            </select>
        </div>

        <!-- Replies -->
        <div class="mb-6 space-y-4">
            <?php if(empty($posts)): ?>
            <div class="rounded-lg border border-gh-border bg-gh-panel p-8 text-center">
                <i class="bi bi-chat-dots mb-3 text-4xl text-gh-muted"></i>
                <p class="text-gh-muted">No replies yet. Be the first to reply!</p>
            </div>
            <?php else: ?>
                <?php foreach($posts as $post): ?>
                <div class="rounded-lg border border-gh-border bg-gh-panel p-6" id="post-<?php echo $post['id']; ?>">
                    <div class="mb-4 flex items-start gap-4">
                        <div class="shrink-0">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-cyan-600 text-sm font-bold text-white">
                                <?php echo strtoupper(substr($post['username'], 0, 1)); ?>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="mb-1 flex items-center gap-2">
                                <span class="font-semibold text-white"><?php echo htmlspecialchars($post['username']); ?></span>
                                <?php if(($post['user_role'] ?? '') == 'admin'): ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-red-500/20 px-2 py-0.5 text-xs font-semibold text-red-400">
                                    <i class="bi bi-shield-fill-check"></i>
                                    Admin
                                </span>
                                <?php elseif(($post['user_role'] ?? '') == 'moderator'): ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-500/20 px-2 py-0.5 text-xs font-semibold text-blue-400">
                                    <i class="bi bi-shield-check"></i>
                                    Moderator
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gh-muted">
                                <?php echo date('M d, Y \a\t g:i A', strtotime($post['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="prose prose-invert max-w-none">
                        <p class="text-white leading-relaxed whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                    </div>

                    <!-- Post Actions -->
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-gh-border pt-4">
                        <button class="inline-flex items-center gap-1 rounded-lg border border-gh-border bg-gh-bg px-3 py-1.5 text-sm text-white transition-all hover:border-gh-accent">
                            <i class="bi bi-hand-thumbs-up"></i>
                            Like
                        </button>
                        <button class="inline-flex items-center gap-1 rounded-lg border border-gh-border bg-gh-bg px-3 py-1.5 text-sm text-white transition-all hover:border-gh-accent">
                            <i class="bi bi-reply-fill"></i>
                            Reply
                        </button>
                        
                        <?php if(isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'moderator'])): ?>
                        <button onclick="deletePost(<?php echo $post['id']; ?>)" class="inline-flex items-center gap-1 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-1.5 text-sm text-red-400 transition-all hover:border-red-500">
                            <i class="bi bi-trash-fill"></i>
                            Delete
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="mb-8 flex justify-center">
            <nav class="flex gap-2">
                <?php if($page > 1): ?>
                <a href="?slug=<?php echo $slug; ?>&page=<?php echo $page - 1; ?>" class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php endif; ?>

                <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?slug=<?php echo $slug; ?>&page=<?php echo $i; ?>" 
                   class="rounded-lg border px-4 py-2 text-sm font-semibold transition-all <?php echo $i === $page ? 'border-gh-accent bg-gh-accent text-white' : 'border-gh-border bg-gh-panel text-white hover:border-gh-accent'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>

                <?php if($page < $total_pages): ?>
                <a href="?slug=<?php echo $slug; ?>&page=<?php echo $page + 1; ?>" class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>

        <!-- Reply Form -->
        <?php if(isset($_SESSION['user_id'])): ?>
            <?php if(!empty($thread['is_locked'])): ?>
            <div class="rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-6 text-center">
                <i class="bi bi-lock-fill mb-2 text-3xl text-yellow-500"></i>
                <p class="font-semibold text-yellow-400">This thread is locked</p>
                <p class="text-sm text-yellow-300">New replies cannot be added to this discussion</p>
            </div>
            <?php else: ?>
            <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
                <h3 class="mb-4 text-lg font-bold text-white">
                    <i class="bi bi-reply-fill mr-2 text-gh-accent"></i>
                    Post a Reply
                </h3>
                <form method="POST" class="space-y-4">
                    <textarea 
                        name="content" 
                        rows="6" 
                        required
                        class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                        placeholder="Share your thoughts..."
                    ></textarea>
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-gh-muted">
                            <i class="bi bi-info-circle mr-1"></i>
                            Be respectful and follow community guidelines
                        </p>
                        <button 
                            type="submit"
                            class="group inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-2 font-bold text-white shadow-lg transition-all hover:brightness-110"
                        >
                            <i class="bi bi-send-fill"></i>
                            Post Reply
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        <?php else: ?>
        <div class="rounded-lg border border-gh-border bg-gh-panel p-8 text-center">
            <i class="bi bi-person-fill-lock mb-3 text-4xl text-gh-muted"></i>
            <h3 class="mb-2 text-lg font-bold text-white">Login to Reply</h3>
            <p class="mb-4 text-sm text-gh-muted">You must be logged in to post replies</p>
            <a href="login.php?redirect=forum-thread.php?slug=<?php echo $slug; ?>" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-2 font-bold text-white">
                <i class="bi bi-box-arrow-in-right"></i>
                Login to Continue
            </a>
        </div>
        <?php endif; ?>

        <!-- Back to Category -->
        <div class="mt-8 text-center">
            <a href="forum-category.php?slug=<?php echo $thread['category_slug']; ?>" class="inline-flex items-center gap-2 text-sm text-gh-accent transition-colors hover:text-gh-success">
                <i class="bi bi-arrow-left"></i>
                Back to <?php echo htmlspecialchars($thread['category_name']); ?>
            </a>
        </div>
    </div>
</div>

<?php if(isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'moderator'])): ?>
<script>
function adminAction(action, id, extraData = {}) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('thread_id', id);
    
    for(let key in extraData) {
        formData.append(key, extraData[key]);
    }
    
    fetch('ajax/forum-admin-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to perform action');
    });
}

function deletePost(postId) {
    if(!confirm('Delete this post? This cannot be undone.')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_post');
    formData.append('post_id', postId);
    
    fetch('ajax/forum-admin-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    });
}

function showMoveModal() {
    const categories = <?php echo json_encode($forum->getCategories()); ?>;
    let html = '<select id="move-category-select" class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-2 text-white mb-4">';
    categories.forEach(cat => {
        html += `<option value="${cat.id}">${cat.name}</option>`;
    });
    html += '</select>';
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/80';
    modal.innerHTML = `
        <div class="w-full max-w-md rounded-lg border border-gh-border bg-gh-panel p-6">
            <h3 class="mb-4 text-xl font-bold text-white">Move Thread to Category</h3>
            ${html}
            <div class="flex gap-3">
                <button onclick="moveThread()" class="flex-1 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-4 py-2 font-bold text-white">
                    Move Thread
                </button>
                <button onclick="this.closest('.fixed').remove()" class="rounded-lg border border-gh-border bg-gh-bg px-4 py-2 font-bold text-white">
                    Cancel
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

function moveThread() {
    const categoryId = document.getElementById('move-category-select').value;
    adminAction('move_thread', <?php echo $thread['id']; ?>, { category_id: categoryId });
}

function showEditModal() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/80 p-4';
    modal.innerHTML = `
        <div class="w-full max-w-2xl rounded-lg border border-gh-border bg-gh-panel p-6">
            <h3 class="mb-4 text-xl font-bold text-white">Edit Thread</h3>
            <form id="edit-thread-form" class="space-y-4">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-white">Title</label>
                    <input type="text" id="edit-title" value="<?php echo htmlspecialchars($thread['title']); ?>" 
                           class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-2 text-white">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-white">Content</label>
                    <textarea id="edit-content" rows="8" 
                              class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-2 text-white"><?php echo htmlspecialchars($thread['content']); ?></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="saveThreadEdit()" class="flex-1 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-4 py-2 font-bold text-white">
                        Save Changes
                    </button>
                    <button type="button" onclick="this.closest('.fixed').remove()" class="rounded-lg border border-gh-border bg-gh-bg px-4 py-2 font-bold text-white">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
}

function saveThreadEdit() {
    const title = document.getElementById('edit-title').value;
    const content = document.getElementById('edit-content').value;
    
    const formData = new FormData();
    formData.append('action', 'edit_thread');
    formData.append('thread_id', <?php echo $thread['id']; ?>);
    formData.append('title', title);
    formData.append('content', content);
    
    fetch('ajax/forum-admin-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    });
}
</script>
<?php endif; ?>

<?php include 'views/footer.php'; ?>
