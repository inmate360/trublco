<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Message.php';
require_once 'includes/maintenance_check.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check for maintenance mode
if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

$message = new Message($db);
$conversation_id = $_GET['id'] ?? null;

if(!$conversation_id) {
    header('Location: messages.php');
    exit();
}

// Verify user is part of this conversation
$query = "SELECT c.*, 
          u1.username as user1_name, u1.is_online as user1_online,
          u2.username as user2_name, u2.is_online as user2_online
          FROM conversations c
          LEFT JOIN users u1 ON c.user1_id = u1.id
          LEFT JOIN users u2 ON c.user2_id = u2.id
          WHERE c.id = :conversation_id 
          AND (c.user1_id = :user_id OR c.user2_id = :user_id)
          LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':conversation_id', $conversation_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

$conversation = $stmt->fetch();

if(!$conversation) {
    $_SESSION['error'] = 'Conversation not found';
    header('Location: messages.php');
    exit();
}

// Get other user info
$other_user_id = $conversation['user1_id'] == $_SESSION['user_id'] ? $conversation['user2_id'] : $conversation['user1_id'];
$other_username = $conversation['user1_id'] == $_SESSION['user_id'] ? $conversation['user2_name'] : $conversation['user1_name'];
$other_user_online = $conversation['user1_id'] == $_SESSION['user_id'] ? $conversation['user1_online'] : $conversation['user2_online'];

// Get messages
$messages = $message->getConversation($conversation_id, $_SESSION['user_id']);

// Mark messages as read
$message->markAsRead($conversation_id, $_SESSION['user_id']);

// Handle new message submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $message_text = trim($_POST['message']);
    
    if(!empty($message_text)) {
        if($message->send($_SESSION['user_id'], $other_user_id, $message_text)) {
            // Refresh page to show new message
            header('Location: conversation.php?id=' . $conversation_id);
            exit();
        }
    }
}

include 'views/header.php';
?>

<style>
.conversation-page {
    padding: 1rem 0 6rem;
    min-height: calc(100vh - 150px);
}

.conversation-header {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.messages-container {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    max-height: 600px;
    overflow-y: auto;
}

.message-item {
    margin-bottom: 1.5rem;
    display: flex;
    flex-direction: column;
}

.message-item.sent {
    align-items: flex-end;
}

.message-item.received {
    align-items: flex-start;
}

.message-bubble {
    max-width: 70%;
    padding: 1rem;
    border-radius: 12px;
    word-wrap: break-word;
}

.message-item.sent .message-bubble {
    background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    color: white;
    border-bottom-right-radius: 4px;
}

.message-item.received .message-bubble {
    background: rgba(66, 103, 245, 0.1);
    color: var(--text-white);
    border-bottom-left-radius: 4px;
}

.message-time {
    font-size: 0.75rem;
    color: var(--text-gray);
    margin-top: 0.3rem;
}

.message-form {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1rem;
    position: sticky;
    bottom: 0;
    z-index: 100;
}

@media (max-width: 768px) {
    .message-bubble {
        max-width: 85%;
    }
    
    .messages-container {
        max-height: 500px;
    }
}
</style>

<div class="conversation-page">
    <div class="container-narrow">
        <div class="conversation-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="messages.php" style="color: var(--primary-blue); text-decoration: none;">
                    ← Back
                </a>
                <div>
                    <h2 style="margin: 0; font-size: 1.2rem;">
                        <?php echo htmlspecialchars($other_username); ?>
                    </h2>
                    <?php if($other_user_online): ?>
                    <small style="color: var(--success-green);">● Online</small>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <a href="profile.php?id=<?php echo $other_user_id; ?>" class="btn-secondary btn-small">
                    View Profile
                </a>
            </div>
        </div>
        
        <div class="messages-container" id="messagesContainer">
            <?php if(count($messages) > 0): ?>
                <?php foreach($messages as $msg): ?>
                <div class="message-item <?php echo $msg['sender_id'] == $_SESSION['user_id'] ? 'sent' : 'received'; ?>">
                    <div class="message-bubble">
                        <?php echo nl2br(htmlspecialchars($msg['message_text'])); ?>
                    </div>
                    <div class="message-time">
                        <?php 
                        $time_diff = time() - strtotime($msg['created_at']);
                        if($time_diff < 60) {
                            echo 'Just now';
                        } elseif($time_diff < 3600) {
                            echo floor($time_diff / 60) . ' minutes ago';
                        } elseif($time_diff < 86400) {
                            echo floor($time_diff / 3600) . ' hours ago';
                        } else {
                            echo date('M j, Y g:i A', strtotime($msg['created_at']));
                        }
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-gray);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">💬</div>
                    <p>No messages yet. Start the conversation!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="message-form">
            <form method="POST" action="" style="display: flex; gap: 1rem;">
                <textarea name="message" 
                          rows="2" 
                          required 
                          placeholder="Type your message..."
                          style="flex: 1; resize: none; background: rgba(66, 103, 245, 0.05); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.75rem; color: var(--text-white);"></textarea>
                <button type="submit" class="btn-primary" style="align-self: flex-end;">
                    Send 📤
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-scroll to bottom of messages
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('messagesContainer');
    if(container) {
        container.scrollTop = container.scrollHeight;
    }
});

// Auto-refresh every 10 seconds to check for new messages
setInterval(function() {
    location.reload();
}, 10000);
</script>

<?php include 'views/footer.php'; ?>