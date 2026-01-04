<?php
session_start();
require_once '../config/database.php';
require_once '../classes/PrivateMessaging.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$pm = new PrivateMessaging($db);

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$thread_id = (int)$_POST['thread_id'];
$message = trim($_POST['message'] ?? '');
$quoted_message_id = !empty($_POST['quoted_message_id']) ? (int)$_POST['quoted_message_id'] : null;

if(empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit();
}

$attachment = !empty($_FILES['attachment']['tmp_name']) ? $_FILES['attachment'] : null;

$result = $pm->replyToThread($thread_id, $_SESSION['user_id'], $message, $quoted_message_id, $attachment);

echo json_encode($result);