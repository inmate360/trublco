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
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if(!$conversation_id || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

if(strlen($message) > 5000) {
    echo json_encode(['success' => false, 'error' => 'Message too long']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $pm = new PrivateMessaging($db);

    $result = $pm->sendMessage($conversation_id, $_SESSION['user_id'], $message);

    if($result['success']) {
        echo json_encode([
            'success' => true,
            'message_id' => $result['message_id'],
            'timestamp' => date('g:i A')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ]);
    }

} catch(Exception $e) {
    error_log("Error in send-message.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
