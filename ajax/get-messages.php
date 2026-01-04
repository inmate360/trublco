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

$conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
$since_id = isset($_GET['since']) ? (int)$_GET['since'] : 0;

if(!$conversation_id) {
    echo json_encode(['success' => false, 'error' => 'Missing conversation ID']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $pm = new PrivateMessaging($db);

    $messages = $pm->getMessages($conversation_id, $_SESSION['user_id']);

    if($messages === false) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized or not found']);
        exit;
    }

    // Mark as read
    $pm->markAsRead($conversation_id, $_SESSION['user_id']);

    // Format messages for frontend
    $formatted_messages = array_map(function($msg) use ($_SESSION) {
        return [
            'id' => $msg['id'],
            'message' => $msg['message'],
            'sender_id' => $msg['sender_id'],
            'sender_username' => $msg['username'],
            'is_verified' => (bool)$msg['is_verified'],
            'is_own' => $msg['sender_id'] == $_SESSION['user_id'],
            'is_read' => (bool)$msg['is_read'],
            'created_at' => $msg['created_at'],
            'time' => date('g:i A', strtotime($msg['created_at']))
        ];
    }, $messages);

    echo json_encode([
        'success' => true,
        'messages' => $formatted_messages,
        'count' => count($formatted_messages)
    ]);

} catch(Exception $e) {
    error_log("Error in get-messages.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
