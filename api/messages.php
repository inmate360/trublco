<?php
session_start();
require_once '../config/database.php';
require_once '../classes/UnifiedMessaging.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$messaging = new UnifiedMessaging($db);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch($action) {
        case 'send':
            $receiver_id = (int)$_POST['receiver_id'];
            $message = trim($_POST['message'] ?? '');
            $image_url = $_POST['image_url'] ?? null;
            
            if(empty($message) && empty($image_url)) {
                echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
                exit();
            }
            
            $result = $messaging->sendMessage($user_id, $receiver_id, $message, $image_url);
            echo json_encode($result);
            break;
            
        case 'get_conversation':
            $other_user_id = (int)($_GET['user_id'] ?? $_POST['user_id']);
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);
            
            $messages = $messaging->getConversation($user_id, $other_user_id, $limit, $offset);
            
            // Mark as read
            $messaging->markAsRead($other_user_id, $user_id);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'count' => count($messages)
            ]);
            break;
            
        case 'get_conversations':
            $conversations = $messaging->getConversationsList($user_id);
            echo json_encode([
                'success' => true,
                'conversations' => $conversations
            ]);
            break;
            
        case 'check_limit':
            $limitInfo = $messaging->canSendMessage($user_id);
            echo json_encode([
                'success' => true,
                'limit_info' => $limitInfo
            ]);
            break;
            
        case 'upload_image':
            if(!isset($_FILES['image'])) {
                echo json_encode(['success' => false, 'error' => 'No image uploaded']);
                exit();
            }
            
            $result = $messaging->uploadImage($_FILES['image'], $user_id);
            echo json_encode($result);
            break;
            
        case 'mark_read':
            $sender_id = (int)$_POST['sender_id'];
            $success = $messaging->markAsRead($sender_id, $user_id);
            echo json_encode(['success' => $success]);
            break;
            
        case 'get_unread_count':
            $count = $messaging->getUnreadCount($user_id);
            echo json_encode([
                'success' => true,
                'unread_count' => $count
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch(Exception $e) {
    error_log("Messages API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>