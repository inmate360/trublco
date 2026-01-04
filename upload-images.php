<?php
session_start();
require_once 'config/database.php';
require_once 'classes/ImageUpload.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if(!isset($_POST['listing_id']) || !isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required data']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$imageUpload = new ImageUpload($db);

$listing_id = $_POST['listing_id'];

// Verify listing belongs to user
$query = "SELECT * FROM listings WHERE id = :id AND user_id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $listing_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

if($stmt->rowCount() == 0) {
    echo json_encode(['success' => false, 'error' => 'Listing not found or access denied']);
    exit();
}

// Upload image
$result = $imageUpload->uploadImage($listing_id, $_FILES['image']);

echo json_encode($result);
?>