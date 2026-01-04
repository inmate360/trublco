<?php
session_start();
require_once 'config/database.php';
require_once 'classes/ImageUpload.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || !isset($_POST['image_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$imageUpload = new ImageUpload($db);

$result = $imageUpload->deleteImage($_POST['image_id'], $_SESSION['user_id']);

echo json_encode(['success' => $result]);
?>