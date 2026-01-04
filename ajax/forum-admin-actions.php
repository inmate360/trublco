<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Forum.php';

header('Content-Type: application/json');

// Check if user is admin/moderator
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'moderator'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$forum = new Forum($db);

$action = $_POST['action'] ?? '';
$thread_id = (int)($_POST['thread_id'] ?? 0);
$post_id = (int)($_POST['post_id'] ?? 0);

$result = ['success' => false, 'error' => 'Invalid action'];

switch($action) {
    case 'toggle_pin':
        $result = $forum->togglePin($thread_id, $_SESSION['user_role']);
        break;
        
    case 'toggle_lock':
        $result = $forum->toggleLock($thread_id, $_SESSION['user_role']);
        break;
        
    case 'delete_thread':
        $result = $forum->deleteThread($thread_id, $_SESSION['user_role']);
        break;
        
    case 'delete_post':
        $result = $forum->deletePost($post_id, $_SESSION['user_role']);
        break;
        
    case 'move_thread':
        $category_id = (int)($_POST['category_id'] ?? 0);
        $result = $forum->moveThread($thread_id, $category_id, $_SESSION['user_role']);
        break;

    case 'edit_thread':
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $result = $forum->editThread($thread_id, $title, $content, $_SESSION['user_role']);
        break;
        
    case 'edit_post':
        $content = $_POST['content'] ?? '';
        $result = $forum->editPost($post_id, $content, $_SESSION['user_role']);
        break;
}

echo json_encode($result);
?>
