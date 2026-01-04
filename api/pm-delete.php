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

if(!$thread_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid thread ID']);
    exit();
}

$result = $pm->deleteThread($thread_id, $_SESSION['user_id']);

echo json_encode(['success' => $result]);