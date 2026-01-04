<?php
session_start();
require_once 'config/database.php';

$db = getConnection();

$story_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'] ?? null;
$ip_address = $_SERVER['REMOTE_ADDR'];

if(!$story_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid story ID']);
    exit;
}

try {
    // Check if story is locked
    $lock_check = $db->prepare("SELECT is_locked FROM stories WHERE id = ?");
    $lock_check->execute([$story_id]);
    $story = $lock_check->fetch();

    if($story && $story['is_locked'] == 1) {
        echo json_encode(['success' => false, 'message' => 'This story is locked by an admin']);
        exit;
    }

    // Check if already liked
    if($user_id) {
        $check_query = "SELECT id FROM story_likes WHERE story_id = :story_id AND user_id = :user_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':story_id', $story_id);
        $check_stmt->bindParam(':user_id', $user_id);
    } else {
        $check_query = "SELECT id FROM story_likes WHERE story_id = :story_id AND ip_address = :ip";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':story_id', $story_id);
        $check_stmt->bindParam(':ip', $ip_address);
    }

    $check_stmt->execute();
    $existing = $check_stmt->fetch();

    if($existing) {
        // Unlike
        $delete_query = "DELETE FROM story_likes WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $existing['id']);
        $delete_stmt->execute();
        $liked = false;
    } else {
        // Like
        $insert_query = "INSERT INTO story_likes (story_id, user_id, ip_address, created_at)
                        VALUES (:story_id, :user_id, :ip, NOW())";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':story_id', $story_id);
        $insert_stmt->bindParam(':user_id', $user_id);
        $insert_stmt->bindParam(':ip', $ip_address);
        $insert_stmt->execute();
        $liked = true;
    }

    // Get updated count
    $count_query = "SELECT COUNT(*) FROM story_likes WHERE story_id = :story_id";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->bindParam(':story_id', $story_id);
    $count_stmt->execute();
    $like_count = $count_stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'like_count' => (int)$like_count
    ]);

} catch(PDOException $e) {
    error_log("Like error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>