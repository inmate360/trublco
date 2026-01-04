<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Message.php';
require_once '../classes/BlockedUsers.php';
require_once '../classes/RateLimiter.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = new Message($db);
$blockedUsers = new BlockedUsers($db);
$rateLimiter = new RateLimiter($db);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

// Rate limiting
$rateCheck = $rateLimiter->checkLimit($user_id, 'chat_api', 60, 60); // 60 requests per minute
if(!$rateCheck['allowed']) {
    echo json_encode(['success' => false, 'error' => 'Rate limit exceeded']);
    exit();
}

switch($action) {
    case 'send':
        $receiver_id = (int)$_POST['receiver_id'];
        $msg = trim($_POST['message'] ?? '');
        $image = $_FILES['image'] ?? null;
        
        // Check if blocked
        if($blockedUsers->isBlockedByEither($user_id, $receiver_id)) {
            echo json_encode(['success' => false, 'error' => 'Cannot send message']);
            exit();
        }
        
        $image_url = null;
        if($image && $image['size'] > 0) {
            $upload_result = $message->uploadImage($image, $user_id);
            if($upload_result['success']) {
                $image_url = $upload_result['url'];
            }
        }
        
        if(empty($msg) && empty($image_url)) {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            exit();
        }
        
        $result = $message->send($user_id, $receiver_id, $msg, $image_url);
        echo json_encode($result);
        break;
        
    case 'get_messages':
        $other_user_id = (int)$_GET['user_id'];
        $offset = (int)($_GET['offset'] ?? 0);
        
        // Check if blocked
        if($blockedUsers->isBlockedByEither($user_id, $other_user_id)) {
            echo json_encode(['success' => false, 'error' => 'Cannot view messages']);
            exit();
        }
        
        $messages = $message->getConversation($user_id, $other_user_id, 50, $offset);
        
        // Mark messages as read
        $message->markConversationAsRead($other_user_id, $user_id);
        
        echo json_encode([
            'success' => true,
            'messages' => $messages,
            'has_more' => count($messages) == 50
        ]);
        break;
        
    case 'mark_read':
        $message_id = (int)$_POST['message_id'];
        $result = $message->markAsRead($message_id, $user_id);
        echo json_encode(['success' => $result]);
        break;
        
    case 'typing':
        $other_user_id = (int)$_POST['user_id'];
        $is_typing = $_POST['is_typing'] === 'true';
        
        $result = $message->setTypingIndicator($user_id, $other_user_id, $is_typing);
        echo json_encode(['success' => $result]);
        break;
        
    case 'check_typing':
        $other_user_id = (int)$_GET['user_id'];
        $is_typing = $message->getTypingIndicator($user_id, $other_user_id);
        
        echo json_encode([
            'success' => true,
            'is_typing' => $is_typing
        ]);
        break;
        
    case 'add_reaction':
        $message_id = (int)$_POST['message_id'];
        $reaction = $_POST['reaction'];
        
        // Validate reaction emoji
        $allowed_reactions = ['❤️', '😂', '😮', '😢', '😡', '👍', '👎'];
        if(!in_array($reaction, $allowed_reactions)) {
            echo json_encode(['success' => false, 'error' => 'Invalid reaction']);
            exit();
        }
        
        $result = $message->addReaction($message_id, $user_id, $reaction);
        echo json_encode(['success' => $result]);
        break;
        
    case 'remove_reaction':
        $message_id = (int)$_POST['message_id'];
        $result = $message->removeReaction($message_id, $user_id);
        echo json_encode(['success' => $result]);
        break;
        
    case 'block_user':
        $blocked_id = (int)$_POST['user_id'];
        $reason = $_POST['reason'] ?? null;
        
        $result = $blockedUsers->blockUser($user_id, $blocked_id, $reason);
        echo json_encode($result);
        break;
        
    case 'unblock_user':
        $blocked_id = (int)$_POST['user_id'];
        $result = $blockedUsers->unblockUser($user_id, $blocked_id);
        echo json_encode($result);
        break;
        
    case 'check_blocked':
        $other_user_id = (int)$_GET['user_id'];
        $is_blocked = $blockedUsers->isBlockedByEither($user_id, $other_user_id);
        
        echo json_encode([
            'success' => true,
            'is_blocked' => $is_blocked,
            'blocked_by_me' => $blockedUsers->isBlocked($user_id, $other_user_id),
            'blocked_by_them' => $blockedUsers->isBlocked($other_user_id, $user_id)
        ]);
        break;
        
    case 'get_unread_count':
        $count = $message->getTotalUnreadCount($user_id);
        echo json_encode([
            'success' => true,
            'count' => $count
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>