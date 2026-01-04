<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle tweet/post actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Post new tweet
    if ($action === 'post_tweet') {
        $content = trim($_POST['content'] ?? '');
        $media_url = $_POST['media_url'] ?? null;

        if (!empty($content) && strlen($content) <= 280) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO tweets (user_id, content, media_url, created_at, like_count, retweet_count, reply_count, view_count) 
                    VALUES (:user_id, :content, :media_url, NOW(), 0, 0, 0, 0)
                ");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':content' => $content,
                    ':media_url' => $media_url
                ]);
                header('Location: dashboard.php?success=posted');
                exit();
            } catch(PDOException $e) {
                error_log("Tweet post error: " . $e->getMessage());
            }
        }
    }

    // Like/Unlike tweet
    if ($action === 'like_tweet') {
        $tweet_id = $_POST['tweet_id'] ?? 0;
        try {
            $stmt = $db->prepare("SELECT id FROM tweet_likes WHERE user_id = :user_id AND tweet_id = :tweet_id");
            $stmt->execute([':user_id' => $user_id, ':tweet_id' => $tweet_id]);

            if ($stmt->fetch()) {
                $stmt = $db->prepare("DELETE FROM tweet_likes WHERE user_id = :user_id AND tweet_id = :tweet_id");
                $stmt->execute([':user_id' => $user_id, ':tweet_id' => $tweet_id]);
                $stmt = $db->prepare("UPDATE tweets SET like_count = like_count - 1 WHERE id = :tweet_id");
                $stmt->execute([':tweet_id' => $tweet_id]);
            } else {
                $stmt = $db->prepare("INSERT INTO tweet_likes (user_id, tweet_id, created_at) VALUES (:user_id, :tweet_id, NOW())");
                $stmt->execute([':user_id' => $user_id, ':tweet_id' => $tweet_id]);
                $stmt = $db->prepare("UPDATE tweets SET like_count = like_count + 1 WHERE id = :tweet_id");
                $stmt->execute([':tweet_id' => $tweet_id]);
            }
            echo json_encode(['success' => true]);
            exit();
        } catch(PDOException $e) {
            error_log("Like error: " . $e->getMessage());
        }
    }

    // Retweet
    if ($action === 'retweet') {
        $tweet_id = $_POST['tweet_id'] ?? 0;
        try {
            $stmt = $db->prepare("SELECT id FROM retweets WHERE user_id = :user_id AND tweet_id = :tweet_id");
            $stmt->execute([':user_id' => $user_id, ':tweet_id' => $tweet_id]);

            if ($stmt->fetch()) {
                $stmt = $db->prepare("DELETE FROM retweets WHERE user_id = :user_id AND tweet_id = :tweet_id");
                $stmt->execute([':user_id' => $user_id, ':tweet_id' => $tweet_id]);
                $stmt = $db->prepare("UPDATE tweets SET retweet_count = retweet_count - 1 WHERE id = :tweet_id");
                $stmt->execute([':tweet_id' => $tweet_id]);
            } else {
                $stmt = $db->prepare("INSERT INTO retweets (user_id, tweet_id, created_at) VALUES (:user_id, :tweet_id, NOW())");
                $stmt->execute([':user_id' => $user_id, ':tweet_id' => $tweet_id]);
                $stmt = $db->prepare("UPDATE tweets SET retweet_count = retweet_count + 1 WHERE id = :tweet_id");
                $stmt->execute([':tweet_id' => $tweet_id]);
            }
            echo json_encode(['success' => true]);
            exit();
        } catch(PDOException $e) {
            error_log("Retweet error: " . $e->getMessage());
        }
    }

    // Bookmark
    if ($action === 'bookmark') {
        $tweet_id = $_POST['tweet_id'] ?? 0;
        try {
            $stmt = $db->prepare("SELECT id FROM bookmarks WHERE user_id = :user_id AND tweet_id = :tweet_id");
            $stmt->execute([':user_id' => $user_id, ':tweet_id' => $tweet_id]);

            if ($stmt->fetch()) {
                $stmt = $db->prepare("DELETE FROM bookmarks WHERE user_id = :user_id AND tweet_id = :tweet_id");
                $stmt->execute([':user_id' => $user_id, ':tweet_id' => $tweet_id]);
            } else {
                $stmt = $db->prepare("INSERT INTO bookmarks (user_id, tweet_id, created_at) VALUES (:user_id, :tweet_id, NOW())");
                $stmt->execute([':user_id' => $user_id, ':tweet_id' => $tweet_id]);
            }
            echo json_encode(['success' => true]);
            exit();
        } catch(PDOException $e) {
            error_log("Bookmark error: " . $e->getMessage());
        }
    }
}

// Fetch user info
try {
    $stmt = $db->prepare("
        SELECT u.*, 
            (SELECT COUNT(*) FROM follows WHERE follower_id = :user_id) as following_count,
            (SELECT COUNT(*) FROM follows WHERE following_id = :user_id) as followers_count,
            (SELECT COUNT(*) FROM tweets WHERE user_id = :user_id) as tweet_count
        FROM users u 
        WHERE u.id = :user_id
    ");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $user = ['username' => $username, 'email' => '', 'avatar' => null];
}

// Fetch tweets for feed
$tweets = [];
try {
    $stmt = $db->prepare("
        SELECT 
            t.*,
            u.username,
            u.avatar,
            u.is_verified,
            (SELECT COUNT(*) FROM tweet_likes WHERE tweet_id = t.id) as like_count,
            (SELECT COUNT(*) FROM retweets WHERE tweet_id = t.id) as retweet_count,
            (SELECT COUNT(*) FROM tweet_replies WHERE tweet_id = t.id) as reply_count,
            (SELECT id FROM tweet_likes WHERE tweet_id = t.id AND user_id = :user_id) as user_liked,
            (SELECT id FROM retweets WHERE tweet_id = t.id AND user_id = :user_id) as user_retweeted,
            (SELECT id FROM bookmarks WHERE tweet_id = t.id AND user_id = :user_id) as user_bookmarked
        FROM tweets t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.is_active = 1
        AND (
            t.user_id IN (SELECT following_id FROM follows WHERE follower_id = :user_id)
            OR t.user_id = :user_id
        )
        ORDER BY t.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([':user_id' => $user_id]);
    $tweets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Tweet fetch error: " . $e->getMessage());
}

// Get trending topics
$trending = [];
try {
    $stmt = $db->query("
        SELECT hashtag, COUNT(*) as count
        FROM (
            SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(content, '#', -1), ' ', 1) as hashtag
            FROM tweets
            WHERE content LIKE '%#%'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ) as hashtags
        WHERE hashtag != ''
        GROUP BY hashtag
        ORDER BY count DESC
        LIMIT 5
    ");
    $trending = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Trending fetch error: " . $e->getMessage());
}

// Get suggested users to follow
$suggested_users = [];
try {
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.avatar, u.bio, u.is_verified,
            (SELECT COUNT(*) FROM follows WHERE following_id = u.id) as follower_count
        FROM users u
        WHERE u.id != :user_id
        AND u.id NOT IN (SELECT following_id FROM follows WHERE follower_id = :user_id)
        ORDER BY follower_count DESC
        LIMIT 3
    ");
    $stmt->execute([':user_id' => $user_id]);
    $suggested_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Suggested users fetch error: " . $e->getMessage());
}

// Helper functions
function time_ago($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    if ($diff < 60) return $diff . 's';
    if ($diff < 3600) return floor($diff / 60) . 'm';
    if ($diff < 86400) return floor($diff / 3600) . 'h';
    if ($diff < 604800) return floor($diff / 86400) . 'd';
    return date('M j', $time);
}

function format_number($num) {
    if ($num >= 1000000) return round($num / 1000000, 1) . 'M';
    if ($num >= 1000) return round($num / 1000, 1) . 'K';
    return $num;
}

function get_initial($username) {
    return strtoupper(substr($username ?? 'U', 0, 1));
}

function get_gradient($index) {
    $gradients = [
        'from-pink-500 to-purple-600',
        'from-blue-500 to-cyan-600',
        'from-green-500 to-emerald-600',
        'from-orange-500 to-red-600',
        'from-purple-500 to-pink-600',
        'from-yellow-500 to-orange-600',
    ];
    return $gradients[$index % count($gradients)];
}

$page_title = "Dashboard - Lustifieds";
include 'views/header.php';
?>

<!-- Custom Styles for Twitter-like Dashboard -->
<style>
    .tweet-card:hover {
        background-color: rgba(255, 255, 255, 0.03);
    }
    .char-counter.warning {
        color: #fbbf24;
    }
    .char-counter.error {
        color: #ef4444;
    }
    .sidebar-nav a:hover {
        background-color: rgba(236, 72, 153, 0.1);
    }
</style>

<div class="bg-gh-bg min-h-screen">
    <div class="max-w-7xl mx-auto flex">

        <!-- Sidebar Navigation -->
        <aside class="w-20 xl:w-72 min-h-screen sticky top-0 flex flex-col p-3 xl:p-6 border-r border-gh-border">
            <div class="mb-8">
                <a href="index.php" class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-pink-500 to-purple-600 flex items-center justify-center">
                        <span class="text-white font-bold text-xl">L</span>
                    </div>
                    <span class="hidden xl:inline text-xl font-bold text-gh-fg">lustifieds</span>
                </a>
            </div>

            <nav class="flex-1 space-y-2 sidebar-nav">
                <a href="dashboard.php" class="flex items-center gap-4 px-4 py-3 rounded-full text-gh-accent font-medium">
                    <i class="bi bi-house-fill text-2xl xl:text-xl"></i>
                    <span class="hidden xl:inline text-xl">Home</span>
                </a>
                <a href="explore.php" class="flex items-center gap-4 px-4 py-3 rounded-full text-gh-muted hover:text-gh-fg">
                    <i class="bi bi-hash text-2xl xl:text-xl"></i>
                    <span class="hidden xl:inline text-xl">Explore</span>
                </a>
                <a href="notifications.php" class="flex items-center gap-4 px-4 py-3 rounded-full text-gh-muted hover:text-gh-fg">
                    <i class="bi bi-bell text-2xl xl:text-xl"></i>
                    <span class="hidden xl:inline text-xl">Notifications</span>
                </a>
                <a href="messages.php" class="flex items-center gap-4 px-4 py-3 rounded-full text-gh-muted hover:text-gh-fg">
                    <i class="bi bi-envelope text-2xl xl:text-xl"></i>
                    <span class="hidden xl:inline text-xl">Messages</span>
                </a>
                <a href="bookmarks.php" class="flex items-center gap-4 px-4 py-3 rounded-full text-gh-muted hover:text-gh-fg">
                    <i class="bi bi-bookmark text-2xl xl:text-xl"></i>
                    <span class="hidden xl:inline text-xl">Bookmarks</span>
                </a>
                <a href="browse.php" class="flex items-center gap-4 px-4 py-3 rounded-full text-gh-muted hover:text-gh-fg">
                    <i class="bi bi-people-fill text-2xl xl:text-xl"></i>
                    <span class="hidden xl:inline text-xl">Browse</span>
                </a>
                <a href="profile.php" class="flex items-center gap-4 px-4 py-3 rounded-full text-gh-muted hover:text-gh-fg">
                    <i class="bi bi-person text-2xl xl:text-xl"></i>
                    <span class="hidden xl:inline text-xl">Profile</span>
                </a>
                <a href="post-ad.php" class="mt-4 w-full px-6 py-3 bg-gh-accent text-white rounded-full font-bold text-lg hover:brightness-110 transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-plus-lg xl:hidden"></i>
                    <span class="hidden xl:inline">Post</span>
                </a>
            </nav>

            <!-- User Menu -->
            <div class="mt-auto">
                <button class="flex items-center gap-3 p-3 rounded-full hover:bg-gh-panel w-full">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center flex-shrink-0">
                        <span class="font-bold text-white"><?php echo get_initial($user['username']); ?></span>
                    </div>
                    <div class="hidden xl:block flex-1 text-left min-w-0">
                        <div class="font-bold truncate text-gh-fg"><?php echo htmlspecialchars($user['username']); ?></div>
                        <div class="text-sm text-gh-muted truncate">@<?php echo htmlspecialchars($user['username']); ?></div>
                    </div>
                    <i class="bi bi-three-dots hidden xl:inline text-gh-muted"></i>
                </button>
            </div>
        </aside>

        <!-- Main Feed -->
        <main class="flex-1 max-w-2xl border-r border-gh-border">

            <!-- Header -->
            <div class="sticky top-0 z-10 bg-gh-bg/95 backdrop-blur-sm border-b border-gh-border">
                <div class="px-4 py-3">
                    <h1 class="text-xl font-bold text-gh-fg">Home</h1>
                </div>
                <div class="flex border-b border-gh-border">
                    <button class="flex-1 py-4 font-semibold border-b-4 border-gh-accent text-gh-fg hover:bg-gh-panel/50">
                        For you
                    </button>
                    <button class="flex-1 py-4 font-semibold text-gh-muted hover:bg-gh-panel/50">
                        Following
                    </button>
                </div>
            </div>

            <!-- Tweet Composer -->
            <div class="border-b border-gh-border p-4">
                <form method="POST" action="" class="flex gap-3">
                    <input type="hidden" name="action" value="post_tweet">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center flex-shrink-0">
                        <span class="font-bold text-white"><?php echo get_initial($user['username']); ?></span>
                    </div>
                    <div class="flex-1">
                        <textarea 
                            name="content" 
                            id="tweetContent"
                            rows="3" 
                            placeholder="What\'s happening?!" 
                            maxlength="280"
                            class="w-full bg-transparent text-xl outline-none resize-none placeholder-gh-muted text-gh-fg"
                        ></textarea>

                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-gh-border">
                            <div class="flex items-center gap-3">
                                <button type="button" class="text-gh-accent hover:bg-gh-accent/10 p-2 rounded-full">
                                    <i class="bi bi-image text-xl"></i>
                                </button>
                                <button type="button" class="text-gh-accent hover:bg-gh-accent/10 p-2 rounded-full">
                                    <i class="bi bi-bar-chart text-xl"></i>
                                </button>
                                <button type="button" class="text-gh-accent hover:bg-gh-accent/10 p-2 rounded-full">
                                    <i class="bi bi-emoji-smile text-xl"></i>
                                </button>
                                <button type="button" class="text-gh-accent hover:bg-gh-accent/10 p-2 rounded-full">
                                    <i class="bi bi-calendar-event text-xl"></i>
                                </button>
                            </div>
                            <div class="flex items-center gap-3">
                                <span id="charCount" class="char-counter text-sm text-gh-muted">0/280</span>
                                <button 
                                    type="submit" 
                                    class="px-6 py-2 bg-gh-accent text-white rounded-full font-bold hover:brightness-110 disabled:opacity-50 transition-all"
                                    id="postBtn"
                                    disabled
                                >
                                    Post
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tweets Feed -->
            <div id="tweetsFeed">
                <?php if (empty($tweets)): ?>
                    <div class="p-8 text-center text-gh-muted">
                        <i class="bi bi-chat-left-text text-4xl mb-4"></i>
                        <p class="text-lg font-semibold text-gh-fg">No posts yet!</p>
                        <p class="text-sm mt-2">Follow people to see their posts here</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tweets as $index => $tweet): ?>
                        <article class="tweet-card border-b border-gh-border p-4 transition-colors cursor-pointer">
                            <div class="flex gap-3">
                                <div class="w-12 h-12 rounded-full bg-gradient-to-br <?php echo get_gradient($index); ?> flex items-center justify-center flex-shrink-0">
                                    <span class="font-bold text-lg text-white"><?php echo get_initial($tweet['username']); ?></span>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-bold hover:underline text-gh-fg"><?php echo htmlspecialchars($tweet['username']); ?></span>
                                        <?php if ($tweet['is_verified']): ?>
                                            <i class="bi bi-patch-check-fill text-blue-500 text-sm"></i>
                                        <?php endif; ?>
                                        <span class="text-gh-muted text-sm">@<?php echo htmlspecialchars($tweet['username']); ?></span>
                                        <span class="text-gh-muted text-sm">·</span>
                                        <span class="text-gh-muted text-sm"><?php echo time_ago($tweet['created_at']); ?></span>
                                    </div>

                                    <div class="text-base mb-3 whitespace-pre-wrap break-words text-gh-fg">
                                        <?php 
                                        $content = htmlspecialchars($tweet['content']);
                                        $content = preg_replace('/#(\w+)/', '<a href="hashtag.php?tag=$1" class="text-gh-accent hover:underline">#$1</a>', $content);
                                        $content = preg_replace('/@(\w+)/', '<a href="profile.php?user=$1" class="text-gh-accent hover:underline">@$1</a>', $content);
                                        echo $content;
                                        ?>
                                    </div>

                                    <?php if ($tweet['media_url']): ?>
                                        <div class="mb-3 rounded-2xl overflow-hidden border border-gh-border">
                                            <img src="<?php echo htmlspecialchars($tweet['media_url']); ?>" alt="Tweet media" class="w-full">
                                        </div>
                                    <?php endif; ?>

                                    <div class="flex items-center justify-between max-w-md mt-3">
                                        <button class="group flex items-center gap-2 text-gh-muted hover:text-blue-500" onclick="replyToTweet(<?php echo $tweet['id']; ?>)">
                                            <div class="p-2 rounded-full group-hover:bg-blue-500/10">
                                                <i class="bi bi-chat"></i>
                                            </div>
                                            <span class="text-sm"><?php echo format_number($tweet['reply_count']); ?></span>
                                        </button>

                                        <button class="group flex items-center gap-2 text-gh-muted hover:text-green-500 <?php echo $tweet['user_retweeted'] ? 'text-green-500' : ''; ?>" onclick="retweet(<?php echo $tweet['id']; ?>)">
                                            <div class="p-2 rounded-full group-hover:bg-green-500/10">
                                                <i class="bi bi-repeat"></i>
                                            </div>
                                            <span class="text-sm"><?php echo format_number($tweet['retweet_count']); ?></span>
                                        </button>

                                        <button class="group flex items-center gap-2 text-gh-muted hover:text-pink-500 <?php echo $tweet['user_liked'] ? 'text-pink-500' : ''; ?>" onclick="likeTweet(<?php echo $tweet['id']; ?>)">
                                            <div class="p-2 rounded-full group-hover:bg-pink-500/10">
                                                <i class="<?php echo $tweet['user_liked'] ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                                            </div>
                                            <span class="text-sm"><?php echo format_number($tweet['like_count']); ?></span>
                                        </button>

                                        <button class="group flex items-center gap-2 text-gh-muted hover:text-gh-accent" onclick="share(<?php echo $tweet['id']; ?>)">
                                            <div class="p-2 rounded-full group-hover:bg-gh-accent/10">
                                                <i class="bi bi-share"></i>
                                            </div>
                                        </button>

                                        <button class="group flex items-center gap-2 text-gh-muted hover:text-gh-accent <?php echo $tweet['user_bookmarked'] ? 'text-gh-accent' : ''; ?>" onclick="bookmark(<?php echo $tweet['id']; ?>)">
                                            <div class="p-2 rounded-full group-hover:bg-gh-accent/10">
                                                <i class="<?php echo $tweet['user_bookmarked'] ? 'bi-bookmark-fill' : 'bi-bookmark'; ?>"></i>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>

        <!-- Right Sidebar -->
        <aside class="hidden lg:block w-96 p-4 space-y-4">

            <!-- Search -->
            <div class="sticky top-0 bg-gh-bg pb-2 z-10">
                <div class="relative">
                    <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-gh-muted"></i>
                    <input 
                        type="text" 
                        placeholder="Search" 
                        class="w-full bg-gh-panel rounded-full py-3 px-12 outline-none focus:ring-2 focus:ring-gh-accent border border-gh-border text-gh-fg placeholder-gh-muted"
                    >
                </div>
            </div>

            <!-- Trending -->
            <?php if (!empty($trending)): ?>
            <div class="bg-gh-panel rounded-2xl overflow-hidden border border-gh-border">
                <h2 class="px-4 py-3 text-xl font-bold text-gh-fg">Trending Now</h2>
                <?php foreach ($trending as $index => $trend): ?>
                    <a href="hashtag.php?tag=<?php echo urlencode($trend['hashtag']); ?>" class="block px-4 py-3 hover:bg-gh-bg transition-colors">
                        <div class="text-xs text-gh-muted mb-1"><?php echo $index + 1; ?> · Trending</div>
                        <div class="font-bold text-gh-fg">#<?php echo htmlspecialchars($trend['hashtag']); ?></div>
                        <div class="text-xs text-gh-muted"><?php echo format_number($trend['count']); ?> posts</div>
                    </a>
                <?php endforeach; ?>
                <a href="trending.php" class="block px-4 py-3 text-gh-accent hover:bg-gh-bg">
                    Show more
                </a>
            </div>
            <?php endif; ?>

            <!-- Who to Follow -->
            <?php if (!empty($suggested_users)): ?>
            <div class="bg-gh-panel rounded-2xl overflow-hidden border border-gh-border">
                <h2 class="px-4 py-3 text-xl font-bold text-gh-fg">Who to follow</h2>
                <?php foreach ($suggested_users as $suggested): ?>
                    <div class="px-4 py-3 hover:bg-gh-bg flex items-center justify-between">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center flex-shrink-0">
                                <span class="font-bold text-white"><?php echo get_initial($suggested['username']); ?></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1">
                                    <span class="font-bold truncate text-gh-fg"><?php echo htmlspecialchars($suggested['username']); ?></span>
                                    <?php if ($suggested['is_verified']): ?>
                                        <i class="bi bi-patch-check-fill text-blue-500 text-xs"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="text-sm text-gh-muted truncate">@<?php echo htmlspecialchars($suggested['username']); ?></div>
                            </div>
                        </div>
                        <button class="px-4 py-2 bg-white text-black rounded-full font-bold text-sm hover:bg-gray-200">
                            Follow
                        </button>
                    </div>
                <?php endforeach; ?>
                <a href="explore.php" class="block px-4 py-3 text-gh-accent hover:bg-gh-bg">
                    Show more
                </a>
            </div>
            <?php endif; ?>

            <!-- Footer Links -->
            <div class="px-4 text-xs text-gh-muted space-x-2 flex flex-wrap">
                <a href="terms.php" class="hover:underline">Terms</a>
                <a href="privacy.php" class="hover:underline">Privacy</a>
                <a href="help.php" class="hover:underline">Help</a>
                <span>© 2026 Lustifieds</span>
            </div>
        </aside>
    </div>
</div>

<script>
    // Character counter
    const tweetContent = document.getElementById('tweetContent');
    const charCount = document.getElementById('charCount');
    const postBtn = document.getElementById('postBtn');

    tweetContent.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = length + '/280';

        if (length === 0) {
            postBtn.disabled = true;
            charCount.className = 'char-counter text-sm text-gh-muted';
        } else if (length > 260) {
            charCount.className = 'char-counter text-sm warning';
            postBtn.disabled = false;
        } else if (length > 280) {
            charCount.className = 'char-counter text-sm error';
            postBtn.disabled = true;
        } else {
            charCount.className = 'char-counter text-sm text-gh-muted';
            postBtn.disabled = false;
        }
    });

    // Like tweet
    function likeTweet(tweetId) {
        fetch('dashboard.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=like_tweet&tweet_id=' + tweetId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    // Retweet
    function retweet(tweetId) {
        fetch('dashboard.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=retweet&tweet_id=' + tweetId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    // Bookmark
    function bookmark(tweetId) {
        fetch('dashboard.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=bookmark&tweet_id=' + tweetId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    // Reply
    function replyToTweet(tweetId) {
        alert('Reply feature coming soon!');
    }

    // Share
    function share(tweetId) {
        if (navigator.share) {
            navigator.share({
                title: 'Share this post',
                url: window.location.origin + '/tweet.php?id=' + tweetId
            });
        } else {
            alert('Share link: ' + window.location.origin + '/tweet.php?id=' + tweetId);
        }
    }
</script>

<?php include 'views/footer.php'; ?>
