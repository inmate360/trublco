<?php
session_start();
require_once 'config/database.php';
require_once 'classes/ImageUpload.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || !isset($_POST['image_id']) || !isset($_POST['listing_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$imageUpload = new ImageUpload($db);

// Verify ownership
$query = "SELECT l.user_id FROM listing_images li
          LEFT JOIN listings l ON li.listing_id = l.id
          WHERE li.id = :image_id AND l.user_id = :user_id
          LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':image_id', $_POST['image_id']);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

if($stmt->rowCount() == 0) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

$result = $imageUpload->setPrimaryImage($_POST['image_id'], $_POST['listing_id']);

echo json_encode(['success' => $result]);
?>