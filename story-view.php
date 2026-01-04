<?php
session_start();
require_once 'config/database.php';

if(!isset($_GET['id'])) exit('No ID specified');
$story_id = (int)$_GET['id'];

$database = new Database();
$db = $database->getConnection();

// Increment views
$db->query("UPDATE stories SET views = views + 1 WHERE id = $story_id");

// Fetch story details WITHOUT JOINING USERS (since foreign key is missing)
// We use author_name directly from stories table.
$query = "SELECT s.*, 
            (SELECT COUNT(*) FROM story_likes WHERE story_id = s.id AND user_id = :user_id) as liked_by_me,
            (SELECT COUNT(*) FROM story_likes WHERE story_id = s.id) as like_count
          FROM stories s
          WHERE s.id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $story_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']); 
$stmt->execute();
$story = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$story) exit('<div class="p-10 text-center text-red-500">Story not found.</div>');

// Fetch comments (Assuming story_comments DOES have user_id linked to users table? 
// The screenshot didn't show story_comments structure. 
// If story_comments table was created by my previous SQL script, it HAS user_id.
// So we can still join users there.)
$c_query = "SELECT c.*, u.username, u.avatar 
            FROM story_comments c 
            LEFT JOIN users u ON c.user_id = u.id 
            WHERE c.story_id = :id 
            ORDER BY c.created_at DESC";
$c_stmt = $db->prepare($c_query);
$c_stmt->bindParam(':id', $story_id);
$c_stmt->execute();
$comments = $c_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Story Content (Left Side) -->
<div class="flex-1 overflow-y-auto border-r border-gh-border bg-gh-panel p-6 md:p-8" style="max-height: 80vh;">

    <?php if(!empty($story['cover_image'])): ?>
        <img src="uploads/stories/<?php echo htmlspecialchars($story['cover_image']); ?>" 
             class="mb-6 h-64 w-full rounded-xl object-cover shadow-lg">
    <?php endif; ?>

    <div class="mb-4 flex items-center gap-2">
        <span class="rounded-full bg-pink-500/10 px-3 py-1 text-xs font-bold uppercase text-pink-500">
            <?php echo htmlspecialchars($story['category']); // Using text column ?>
        </span>
        <span class="text-xs text-gh-muted"><?php echo date('F j, Y', strtotime($story['created_at'])); ?></span>
    </div>

    <h1 class="mb-6 text-3xl font-extrabold text-white"><?php echo htmlspecialchars($story['title']); ?></h1>

    <div class="prose prose-invert max-w-none text-gh-fg">
        <?php echo nl2br(htmlspecialchars($story['content'])); ?>
    </div>

</div>

<!-- Interactions (Right Side) -->
<div class="flex w-full flex-col bg-gh-bg md:w-96" style="height: 80vh;">

    <!-- Author & Actions -->
    <div class="border-b border-gh-border p-5">
        <div class="mb-4 flex items-center gap-3">
            <!-- Fallback Avatar -->
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-pink-600 to-purple-600 font-bold text-white">
                <?php echo strtoupper(substr($story['author_name'] ?? 'A', 0, 1)); ?>
            </div>

            <div class="flex-1">
                <div class="font-bold text-white"><?php echo htmlspecialchars($story['author_name'] ?? 'Anonymous'); ?></div>
                <div class="text-xs text-gh-muted">Author</div>
            </div>

            <!-- Can't link to message profile easily without user_id, disabling button for now or using author_name search? 
                 If we don't have author_id, we can't deep-link to message them reliably unless we lookup user by author_name.
            -->
            <!-- 
            <a href="#" class="flex h-8 w-8 items-center justify-center rounded-full bg-gh-panel2 text-gh-accent transition-all hover:bg-gh-accent hover:text-white" title="Messaging unavailable (guest post)">
                <i class="bi bi-chat-dots-fill opacity-50"></i>
            </a>
            -->
        </div>

        <div class="flex gap-2">
            <button onclick="likeStory(<?php echo $story['id']; ?>)" 
                    id="likeBtn-<?php echo $story['id']; ?>"
                    class="flex-1 rounded-lg border <?php echo $story['liked_by_me'] ? 'border-pink-500 bg-pink-500/10 text-pink-500' : 'border-gh-border bg-gh-panel2 text-gh-muted'; ?> py-2 text-sm font-semibold transition-all hover:border-pink-500 hover:text-pink-500">
                <i class="bi bi-heart-fill mr-1"></i> <span id="likeCount-<?php echo $story['id']; ?>"><?php echo $story['like_count']; ?></span> Likes
            </button>
        </div>
    </div>

    <!-- Comments List -->
    <div class="flex-1 space-y-4 overflow-y-auto p-5" id="commentsList">
        <?php if(empty($comments)): ?>
            <div class="py-10 text-center text-sm text-gh-muted">
                No comments yet. Be the first!
            </div>
        <?php else: ?>
            <?php foreach($comments as $comment): ?>
                <div class="flex gap-3">
                    <?php if(!empty($comment['avatar'])): ?>
                        <img src="uploads/avatars/<?php echo htmlspecialchars($comment['avatar']); ?>" class="h-8 w-8 rounded-full object-cover">
                    <?php else: ?>
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gh-panel2 text-xs font-bold text-gh-muted">
                            <?php echo strtoupper(substr($comment['username'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>

                    <div class="flex-1">
                        <div class="rounded-2xl rounded-tl-none bg-gh-panel2 px-3 py-2 text-sm text-gh-fg">
                            <span class="block text-xs font-bold text-gh-accent"><?php echo htmlspecialchars($comment['username']); ?></span>
                            <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                        </div>
                        <div class="mt-1 text-xs text-gh-muted"><?php echo date('M d, g:i A', strtotime($comment['created_at'])); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Comment Input -->
    <div class="border-t border-gh-border bg-gh-panel p-4">
        <?php if(isset($_SESSION['user_id'])): ?>
            <form onsubmit="postComment(event, <?php echo $story['id']; ?>)" class="relative">
                <input type="text" name="comment" required
                       placeholder="Write a comment..."
                       class="w-full rounded-full border border-gh-border bg-gh-panel2 py-2.5 pl-4 pr-12 text-sm text-white focus:border-gh-accent focus:outline-none">
                <button type="submit" <?php echo $story['is_locked'] ? 'disabled' : ''; ?> class="absolute right-1 top-1 flex h-8 w-8 items-center justify-center rounded-full bg-gh-accent text-white transition-all hover:brightness-110">
                    <i class="bi bi-send-fill text-xs"></i>
                </button>
            </form>
        <?php else: ?>
            <div class="text-center text-xs text-gh-muted">
                <a href="login.php" class="text-gh-accent hover:underline">Log in</a> to comment
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
function likeStory(id) {
    fetch('story-like.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'story_id=' + id
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            const btn = document.getElementById('likeBtn-' + id);
            const count = document.getElementById('likeCount-' + id);

            if(data.action === 'liked') {
                btn.classList.remove('border-gh-border', 'bg-gh-panel2', 'text-gh-muted');
                btn.classList.add('border-pink-500', 'bg-pink-500/10', 'text-pink-500');
                count.innerText = parseInt(count.innerText) + 1;
            } else {
                btn.classList.add('border-gh-border', 'bg-gh-panel2', 'text-gh-muted');
                btn.classList.remove('border-pink-500', 'bg-pink-500/10', 'text-pink-500');
                count.innerText = parseInt(count.innerText) - 1;
            }
        }
    });
}

function postComment(e, id) {
    e.preventDefault();
    const input = e.target.querySelector('input');
    const content = input.value;

    fetch('story-comment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `story_id=${id}&content=${encodeURIComponent(content)}`
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            input.value = '';
            openStoryModal(id); 
        } else {
            alert(data.error || 'Failed to post comment');
        }
    });
}
</script>
