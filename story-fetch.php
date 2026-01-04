<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

$story_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if(!$story_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid story ID']);
    exit();
}

try {
    $query = "SELECT s.*, s.is_locked, 
              (SELECT COUNT(*) FROM story_likes WHERE story_id = s.id) as like_count,
              (SELECT COUNT(*) FROM story_comments WHERE story_id = s.id) as comment_count
              FROM stories s 
              WHERE s.id = :id AND s.status = 'approved'";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $story_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $story = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$story) {
        echo json_encode(['success' => false, 'message' => 'Story not found']);
        exit();
    }
    
    // Update view count
    $update_query = "UPDATE stories SET views = views + 1 WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':id', $story_id, PDO::PARAM_INT);
    $update_stmt->execute();
    
    echo json_encode([
        'success' => true,
        'story' => $story
    ]);
    
} catch(PDOException $e) {
    error_log("Error fetching story: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
