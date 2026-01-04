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

if (!$story_id) {
    echo json_encode(['success' => false, 'message' => 'Story ID required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Check if already liked
    $check_query = "SELECT id FROM story_likes WHERE story_id = :story_id AND user_id = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':story_id', $story_id);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        // Unlike
        $delete_query = "DELETE FROM story_likes WHERE story_id = :story_id AND user_id = :user_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':story_id', $story_id);
        $delete_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $delete_stmt->execute();
        
        $action = 'unliked';
    } else {
        // Like
        $insert_query = "INSERT INTO story_likes (story_id, user_id, created_at) VALUES (:story_id, :user_id, NOW())";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':story_id', $story_id);
        $insert_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $insert_stmt->execute();
        
        $action = 'liked';
        
        // Create notification for story owner
        $story_query = "SELECT user_id FROM stories WHERE id = :story_id";
        $story_stmt = $db->prepare($story_query);
        $story_stmt->bindParam(':story_id', $story_id);
        $story_stmt->execute();
        $story = $story_stmt->fetch();
        
        if ($story && $story['user_id'] != $_SESSION['user_id']) {
            $notif_query = "INSERT INTO notifications (user_id, type, message, link, created_at) 
                           VALUES (:user_id, 'story_like', :message, :link, NOW())";
            $notif_stmt = $db->prepare($notif_query);
            $notif_stmt->bindParam(':user_id', $story['user_id']);
            $message = "Someone liked your story";
            $link = "story-view.php?id=" . $story_id;
            $notif_stmt->bindParam(':message', $message);
            $notif_stmt->bindParam(':link', $link);
            $notif_stmt->execute();
        }
    }
    
    // Get updated like count
    $count_query = "SELECT COUNT(*) as count FROM story_likes WHERE story_id = :story_id";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->bindParam(':story_id', $story_id);
    $count_stmt->execute();
    $count = $count_stmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'likes' => $count
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
