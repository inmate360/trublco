<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Forum.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$forum = new Forum($db);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch($action) {
        case 'create_thread':
            $category_id = (int)$_POST['category_id'];
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));
            
            if(empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'error' => 'Title and content are required']);
                exit();
            }
            
            if(strlen($title) > 500) {
                echo json_encode(['success' => false, 'error' => 'Title is too long']);
                exit();
            }
            
            $result = $forum->createThread($user_id, $category_id, $title, $content, $tags);
            echo json_encode($result);
            break;
            
        case 'create_post':
            $thread_id = (int)$_POST['thread_id'];
            $content = trim($_POST['content']);
            
            if(empty($content)) {
                echo json_encode(['success' => false, 'error' => 'Content is required']);
                exit();
            }
            
            // Check if thread is locked
            $query = "SELECT is_locked FROM forum_threads WHERE id = :thread_id";
            $stmt = $db->prepare($query);
            $stmt->execute(['thread_id' => $thread_id]);
            $thread = $stmt->fetch();
            
            if($thread && $thread['is_locked']) {
                echo json_encode(['success' => false, 'error' => 'This thread is locked']);
                exit();
            }
            
            $result = $forum->createPost($user_id, $thread_id, $content);
            echo json_encode($result);
            break;
            
        case 'update_thread':
            $thread_id = (int)$_POST['thread_id'];
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            
            if(empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'error' => 'Title and content are required']);
                exit();
            }
            
            $success = $forum->updateThread($thread_id, $user_id, $title, $content);
            echo json_encode(['success' => $success]);
            break;
            
        case 'delete_thread':
            $thread_id = (int)$_POST['thread_id'];
            $success = $forum->deleteThread($thread_id, $user_id);
            echo json_encode(['success' => $success]);
            break;
            
        case 'delete_post':
            $post_id = (int)$_POST['post_id'];
            
            // Verify ownership
            $query = "SELECT user_id FROM forum_posts WHERE id = :post_id";
            $stmt = $db->prepare($query);
            $stmt->execute(['post_id' => $post_id]);
            $post = $stmt->fetch();
            
            if(!$post || $post['user_id'] != $user_id) {
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit();
            }
            
            $query = "UPDATE forum_posts SET is_deleted = TRUE, deleted_at = NOW() WHERE id = :post_id";
            $stmt = $db->prepare($query);
            $success = $stmt->execute(['post_id' => $post_id]);
            
            echo json_encode(['success' => $success]);
            break;
            
        case 'toggle_reaction':
            $thread_id = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : null;
            $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : null;
            $type = $_POST['type'] ?? 'like';
            
            $result = $forum->toggleReaction($user_id, $thread_id, $post_id, $type);
            echo json_encode($result);
            break;
            
        case 'subscribe_thread':
            $thread_id = (int)$_POST['thread_id'];
            
            $query = "INSERT INTO forum_subscriptions (user_id, thread_id) 
                      VALUES (:user_id, :thread_id)
                      ON DUPLICATE KEY UPDATE notify_replies = TRUE";
            $stmt = $db->prepare($query);
            $success = $stmt->execute([
                'user_id' => $user_id,
                'thread_id' => $thread_id
            ]);
            
            echo json_encode(['success' => $success, 'action' => 'subscribed']);
            break;
            
        case 'unsubscribe_thread':
            $thread_id = (int)$_POST['thread_id'];
            
            $query = "DELETE FROM forum_subscriptions 
                      WHERE user_id = :user_id AND thread_id = :thread_id";
            $stmt = $db->prepare($query);
            $success = $stmt->execute([
                'user_id' => $user_id,
                'thread_id' => $thread_id
            ]);
            
            echo json_encode(['success' => $success, 'action' => 'unsubscribed']);
            break;
            
        case 'report_content':
            $thread_id = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : null;
            $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : null;
            $reason = $_POST['reason'] ?? 'other';
            $description = trim($_POST['description'] ?? '');
            
            $query = "INSERT INTO forum_reports (reporter_id, thread_id, post_id, reason, description) 
                      VALUES (:reporter_id, :thread_id, :post_id, :reason, :description)";
            $stmt = $db->prepare($query);
            $success = $stmt->execute([
                'reporter_id' => $user_id,
                'thread_id' => $thread_id,
                'post_id' => $post_id,
                'reason' => $reason,
                'description' => $description
            ]);
            
            echo json_encode(['success' => $success]);
            break;
            
        case 'search':
            $search_term = trim($_GET['q'] ?? '');
            $page = (int)($_GET['page'] ?? 1);
            
            if(empty($search_term)) {
                echo json_encode(['success' => false, 'error' => 'Search term is required']);
                exit();
            }
            
            $threads = $forum->searchThreads($search_term, $page, 20);
            echo json_encode([
                'success' => true,
                'threads' => $threads,
                'page' => $page
            ]);
            break;
            
        case 'get_user_threads':
            $query = "SELECT t.*, c.name as category_name, c.slug as category_slug
                      FROM forum_threads t
                      LEFT JOIN forum_categories c ON t.category_id = c.id
                      WHERE t.user_id = :user_id AND t.is_deleted = FALSE
                      ORDER BY t.created_at DESC
                      LIMIT 50";
            $stmt = $db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            $threads = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'threads' => $threads
            ]);
            break;
            
        case 'get_user_posts':
            $query = "SELECT p.*, t.title as thread_title, t.slug as thread_slug
                      FROM forum_posts p
                      LEFT JOIN forum_threads t ON p.thread_id = t.id
                      WHERE p.user_id = :user_id AND p.is_deleted = FALSE
                      ORDER BY p.created_at DESC
                      LIMIT 50";
            $stmt = $db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            $posts = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'posts' => $posts
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch(Exception $e) {
    error_log("Forum API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred'
    ]);
}
?>