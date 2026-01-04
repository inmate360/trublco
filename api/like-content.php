<?php
session_start();
require_once '../config/database.php';
require_once '../classes/MediaContent.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$content_id = (int)($data['content_id'] ?? 0);
$action = $data['action'] ?? 'like';

$database = new Database();
$db = $database->getConnection();
$mediaContent = new MediaContent($db);

if($action == 'like') {
    $result = $mediaContent->likeContent($content_id, $_SESSION['user_id']);
} else {
    $result = $mediaContent->unlikeContent($content_id, $_SESSION['user_id']);
}

echo json_encode($result);