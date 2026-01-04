<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to rate']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$story_id = isset($_POST['story_id']) ? (int)$_POST['story_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$user_id = $_SESSION['user_id'];

if(!$story_id || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    // Check if user already rated
    $check_query = "SELECT id FROM story_ratings WHERE story_id = :story_id AND user_id = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':story_id', $story_id);
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->execute();
    $existing = $check_stmt->fetch();

    if($existing) {
        // Update existing rating
        $update_query = "UPDATE story_ratings SET rating = :rating WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':rating', $rating);
        $update_stmt->bindParam(':id', $existing['id']);
        $update_stmt->execute();
    } else {
        // Insert new rating
        $insert_query = "INSERT INTO story_ratings (story_id, user_id, rating, created_at)
                        VALUES (:story_id, :user_id, :rating, NOW())";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':story_id', $story_id);
        $insert_stmt->bindParam(':user_id', $user_id);
        $insert_stmt->bindParam(':rating', $rating);
        $insert_stmt->execute();
    }

    // Get updated average
    $avg_query = "SELECT AVG(rating) as avg_rating FROM story_ratings WHERE story_id = :story_id";
    $avg_stmt = $db->prepare($avg_query);
    $avg_stmt->bindParam(':story_id', $story_id);
    $avg_stmt->execute();
    $result = $avg_stmt->fetch();

    echo json_encode([
        'success' => true,
        'rating' => $rating,
        'avg_rating' => round($result['avg_rating'], 1)
    ]);

} catch(PDOException $e) {
    error_log("Rating error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
