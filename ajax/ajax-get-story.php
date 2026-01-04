<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$story_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'] ?? 0;

if(!$story_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid story ID']);
    exit;
}

try {
    // Fetch story with user interaction data
    $query = "SELECT s.*,
              (SELECT COUNT(*) FROM story_likes WHERE story_id = s.id) as like_count,
              (SELECT COUNT(*) FROM story_likes WHERE story_id = s.id AND user_id = :user_id) as user_liked,
              (SELECT COUNT(*) FROM story_comments WHERE story_id = s.id) as comment_count,
              (SELECT AVG(rating) FROM story_ratings WHERE story_id = s.id) as avg_rating,
              (SELECT rating FROM story_ratings WHERE story_id = s.id AND user_id = :user_id) as user_rating
              FROM stories s
              WHERE s.id = :id AND s.status = 'approved'";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $story_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    $story = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$story) {
        echo json_encode(['success' => false, 'message' => 'Story not found']);
        exit;
    }

    // Update view count
    $update_query = "UPDATE stories SET views = views + 1 WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':id', $story_id, PDO::PARAM_INT);
    $update_stmt->execute();

    // Fetch comments with user info
    $comment_query = "SELECT c.*, u.username, u.avatar
                      FROM story_comments c
                      LEFT JOIN users u ON c.user_id = u.id
                      WHERE c.story_id = :id
                      ORDER BY c.created_at DESC
                      LIMIT 50";

    $comment_stmt = $db->prepare($comment_query);
    $comment_stmt->bindParam(':id', $story_id, PDO::PARAM_INT);
    $comment_stmt->execute();

    $story['comments'] = $comment_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'story' => $story,
        'logged_in' => !empty($user_id)
    ]);

} catch(PDOException $e) {
    error_log("Error fetching story: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
