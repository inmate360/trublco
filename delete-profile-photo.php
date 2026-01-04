<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || !isset($_POST['photo_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get photo data and verify ownership
$query = "SELECT * FROM user_photos WHERE id = :photo_id AND user_id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':photo_id', $_POST['photo_id']);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$photo = $stmt->fetch();

if(!$photo) {
    echo json_encode(['success' => false, 'error' => 'Photo not found']);
    exit();
}

// Delete file
if(file_exists($photo['file_path'])) {
    unlink($photo['file_path']);
}

// Delete from database
$query = "DELETE FROM user_photos WHERE id = :photo_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':photo_id', $_POST['photo_id']);

if($stmt->execute()) {
    // If this was primary, make another photo primary
    if($photo['is_primary']) {
        $query = "UPDATE user_photos SET is_primary = TRUE 
                  WHERE user_id = :user_id 
                  ORDER BY uploaded_at ASC 
                  LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to delete photo']);
}
?>