<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if(!isset($_FILES['photo'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check photo limit
$query = "SELECT COUNT(*) as count FROM user_photos WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->fetch();

if($result['count'] >= 10) {
    echo json_encode(['success' => false, 'error' => 'Maximum 10 photos allowed']);
    exit();
}

$file = $_FILES['photo'];
$upload_dir = 'uploads/profiles/';

// Create directory if it doesn't exist
if(!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Validate file
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 5242880; // 5MB

if($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload error occurred']);
    exit();
}

if($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'error' => 'File size exceeds 5MB limit']);
    exit();
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if(!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP allowed']);
    exit();
}

// Check if it's actually an image
$image_info = getimagesize($file['tmp_name']);
if($image_info === false) {
    echo json_encode(['success' => false, 'error' => 'File is not a valid image']);
    exit();
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'profile_' . $_SESSION['user_id'] . '_' . uniqid() . '_' . time() . '.' . $extension;
$filepath = $upload_dir . $filename;

// Move uploaded file
if(!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save photo']);
    exit();
}

// Check if this is the first photo (make it primary)
$is_primary = $result['count'] == 0;

// Save to database
$query = "INSERT INTO user_photos 
          (user_id, filename, file_path, file_size, is_primary) 
          VALUES (:user_id, :filename, :filepath, :filesize, :is_primary)";

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->bindParam(':filename', $filename);
$stmt->bindParam(':filepath', $filepath);
$stmt->bindParam(':filesize', $file['size']);
$stmt->bindParam(':is_primary', $is_primary, PDO::PARAM_BOOL);

if($stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'photo_id' => $db->lastInsertId(),
        'filepath' => $filepath
    ]);
} else {
    unlink($filepath);
    echo json_encode(['success' => false, 'error' => 'Failed to save photo data']);
}
?>