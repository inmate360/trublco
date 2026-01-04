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

// Verify ownership
$query = "SELECT * FROM user_photos WHERE id = :photo_id AND user_id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':photo_id', $_POST['photo_id']);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

if($stmt->rowCount() == 0) {
    echo json_encode(['success' => false, 'error' => 'Photo not found']);
    exit();
}

// Remove primary flag from all photos
$query = "UPDATE user_photos SET is_primary = FALSE WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

// Set new primary
$query = "UPDATE user_photos SET is_primary = TRUE WHERE id = :photo_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':photo_id', $_POST['photo_id']);

if($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to set primary']);
}
?>