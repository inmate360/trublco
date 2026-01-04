<?php
session_start();
require_once '../config/database.php';
require_once '../classes/PrivateMessaging.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$thread_id = isset($_GET['thread']) ? (int)$_GET['thread'] : 0;
$since_id = isset($_GET['since']) ? (int)$_GET['since'] : 0;

if(!$thread_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid thread']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $pm = new PrivateMessaging($db);

    $messages = $pm->getNewMessages($thread_id, $since_id, $_SESSION['user_id']);

    // Mark as read
    if(!empty($messages)) {
        $pm->markAsRead($thread_id, $_SESSION['user_id']);
    }

    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);

} catch(PDOException $e) {
    error_log("Poll messages error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
