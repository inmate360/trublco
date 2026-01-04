<?php
session_start();
require_once '../config/database.php';
require_once '../classes/PrivateMessaging.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$conversation_id = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : 0;

if(!$conversation_id) {
    echo json_encode(['success' => false, 'error' => 'Missing conversation ID']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $pm = new PrivateMessaging($db);

    $result = $pm->deleteConversation($conversation_id, $_SESSION['user_id']);

    if($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete conversation']);
    }

} catch(Exception $e) {
    error_log("Error in delete-conversation.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
