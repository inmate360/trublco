<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$story_id = $data['story_id'] ?? null;
$comment_text = trim($data['comment'] ?? '');

if (!$story_id || empty($comment_text)) {
    echo json_encode(['success' => false, 'message' => 'Story ID and comment required']);
    exit;
}

if (strlen($comment_text) > 500) {
    echo json_encode(['success' => false, 'message' => 'Comment too long (max 500 characters)']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Insert comment
    $query = "INSERT INTO story_comments (story_id, user_id, comment, created_at) 
              VALUES (:story_id, :user_id, :comment, NOW())";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':story_id', $story_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':comment', $comment_text);
    $stmt->execute();
    
    $comment_id = $db->lastInsertId();
    
    // Get comment with user info
    $get_query = "SELECT c.*, u.username, u.avatar 
                  FROM story_comments c 
                  LEFT JOIN users u ON c.user_id = u.id 
                  WHERE c.id = :comment_id";
    $get_stmt = $db->prepare($get_query);
    $get_stmt->bindParam(':comment_id', $comment_id);
    $get_stmt->execute();
    $comment = $get_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Create notification for story owner
    $story_query = "SELECT user_id FROM stories WHERE id = :story_id";
    $story_stmt = $db->prepare($story_query);
    $story_stmt->bindParam(':story_id', $story_id);
    $story_stmt->execute();
    $story = $story_stmt->fetch();
    
    if ($story && $story['user_id'] != $_SESSION['user_id']) {
        $notif_query = "INSERT INTO notifications (user_id, type, message, link, created_at) 
                       VALUES (:user_id, 'story_comment', :message, :link, NOW())";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $story['user_id']);
        $message = $comment['username'] . " commented on your story";
        $link = "story-view.php?id=" . $story_id;
        $notif_stmt->bindParam(':message', $message);
        $notif_stmt->bindParam(':link', $link);
        $notif_stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'comment' => $comment
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
