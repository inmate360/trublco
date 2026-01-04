<?php
session_start();
require_once 'config/database.php';
require_once 'classes/UnifiedMessaging.php';
require_once 'includes/maintenance_check.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

$messaging = new UnifiedMessaging($db);

// Get other user if specified
$other_user_id = (int)($_GET['user'] ?? 0);
$other_user = null;

if($other_user_id) {
    $query = "SELECT id, username, is_online, last_seen FROM users WHERE id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $other_user_id);
    $stmt->execute();
    $other_user = $stmt->fetch();
}

// Get user's message limit info
$limitInfo = $messaging->canSendMessage($_SESSION['user_id']);

// Get conversations list
$conversations = $messaging->getConversationsList($_SESSION['user_id']);

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/light-theme.css">

<style>
/* All the existing CSS styles from before */
body {
    background: #0a1628;
    margin: 0;
    padding: 0;
}

.messages-wrapper {
    width: 100%;
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: #0a1628;
}

.messages-container {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 0;
    height: calc(100vh - 60px);
    background: #0a1628;
    overflow: hidden;
}

.conversations-sidebar {
    border-right: 1px solid rgba(66, 103, 245, 0.2);
    display: flex;
    flex-direction: column;
    background: #0d1b2a;
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(66, 103, 245, 0.2);
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
}

.message-limit-banner {
    background: rgba(245, 158, 11, 0.15);
    padding: 1rem;
    border-bottom: 1px solid rgba(66, 103, 245, 0.2);
    text-align: center;
}

.message-limit-banner.premium {
    background: rgba(251, 191, 36, 0.15);
}

.conversations-list {
    flex: 1;
    overflow-y: auto;
    background: #0d1b2a;
}

.conversation-item {
    padding: 1rem;
    border-bottom: 1px solid rgba(66, 103, 245, 0.1);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.conversation-item:hover {
    background: rgba(66, 103, 245, 0.1);
}

.conversation-item.active {
    background: rgba(66, 103, 245, 0.2);
    border-left: 3px solid var(--primary-blue);
}

.chat-area {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #0a1628;
}

.chat-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(66, 103, 245, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #0d1b2a;
}

.messages-area {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    background: #0a1628;
}

.message-bubble {
    max-width: 75%;
    padding: 0.75rem 1rem;
    border-radius: 16px;
    word-wrap: break-word;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.message-sent {
    align-self: flex-end;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
    border-bottom-right-radius: 4px;
}

.message-received {
    align-self: flex-start;
    background: #1e293b;
    color: var(--text-white);
    border-bottom-left-radius: 4px;
    border: 1px solid rgba(66, 103, 245, 0.2);
}

.message-time {
    font-size: 0.7rem;
    opacity: 0.7;
    margin-top: 0.35rem;
    display: block;
}

.message-input-area {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(66, 103, 245, 0.2);
    background: #0d1b2a;
}

.message-input-form {
    display: flex;
    gap: 0.75rem;
    align-items: flex-end;
}

.message-input {
    flex: 1;
    padding: 0.875rem 1.25rem;
    background: #0a1628;
    border: 2px solid rgba(66, 103, 245, 0.3);
    border-radius: 24px;
    color: var(--text-white);
    resize: none;
    max-height: 120px;
    font-family: inherit;
    font-size: 0.95rem;
}

.message-input:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(66, 103, 245, 0.15);
}

.send-btn {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    border: none;
    color: white;
    font-size: 1.3rem;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.send-btn:hover:not(:disabled) {
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(66, 103, 245, 0.5);
}

.send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .messages-container {
        grid-template-columns: 1fr;
        height: calc(100vh - 140px);
    }
    
    .conversations-sidebar {
        display: none;
    }
}
</style>

<div class="messages-wrapper">
    <div class="messages-container">
        
        <!-- Conversations Sidebar -->
        <div class="conversations-sidebar">
            <div class="sidebar-header">
                <h2 style="margin: 0 0 0.5rem">💬 Messages</h2>
                <p style="opacity: 0.9; font-size: 0.9rem; margin: 0;">
                    <?php echo count($conversations); ?> conversation<?php echo count($conversations) != 1 ? 's' : ''; ?>
                </p>
            </div>
            
            <div class="message-limit-banner <?php echo $limitInfo['is_premium'] ? 'premium' : ''; ?>">
                <div style="font-size: 0.85rem; color: var(--text-white); line-height: 1.6;" id="limitBanner">
                    <?php if($limitInfo['is_premium']): ?>
                    ⭐ <strong>Premium</strong> - Unlimited Messages
                    <?php else: ?>
                    <strong id="remainingCount"><?php echo $limitInfo['remaining']; ?></strong> / 25 messages left<br>
                    <a href="/membership.php" style="color: var(--featured-gold); text-decoration: none; font-weight: 600;">🔥 Upgrade</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="conversations-list">
                <?php foreach($conversations as $conv): ?>
                <div class="conversation-item <?php echo $other_user_id == $conv['contact_id'] ? 'active' : ''; ?>" 
                     onclick="location.href='/messages-chat-simple.php?user=<?php echo $conv['contact_id']; ?>'">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue)); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; position: relative;">
                        👤
                        <?php if($conv['is_online']): ?>
                        <span style="position: absolute; bottom: 0; right: 0; width: 14px; height: 14px; background: var(--success-green); border: 3px solid #0d1b2a; border-radius: 50%;"></span>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; color: var(--text-white); margin-bottom: 0.25rem;">
                            <?php echo htmlspecialchars($conv['username']); ?>
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-gray); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo htmlspecialchars(substr($conv['last_message'] ?? 'No messages', 0, 35)); ?>
                        </div>
                    </div>
                    <?php if($conv['unread_count'] > 0): ?>
                    <span style="background: var(--primary-blue); color: white; padding: 0.25rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 700;">
                        <?php echo $conv['unread_count']; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="chat-area">
            <?php if($other_user): ?>
            <div class="chat-header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue)); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        👤
                    </div>
                    <div>
                        <div style="font-size: 1.15rem; font-weight: 600; color: var(--text-white);">
                            <?php echo htmlspecialchars($other_user['username']); ?>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-gray);">
                            <?php echo $other_user['is_online'] ? '🟢 Online' : '⚪ Offline'; ?>
                        </div>
                    </div>
                </div>
                <a href="/profile.php?id=<?php echo $other_user['id']; ?>" class="btn-secondary btn-small">
                    View Profile
                </a>
            </div>
            
            <div class="messages-area" id="messagesArea">
                <div style="text-align: center; padding: 3rem 2rem; color: var(--text-gray);">
                    <div style="font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.5;">⏳</div>
                    <p>Loading messages...</p>
                </div>
            </div>
            
            <div class="message-input-area">
                <?php if(!$limitInfo['is_premium']): ?>
                <div style="background: rgba(245, 158, 11, 0.15); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.75rem; font-size: 0.85rem; border: 1px solid rgba(245, 158, 11, 0.3);">
                    ⚠️ Phone numbers blocked for free users. 
                    <a href="/membership.php" style="color: var(--featured-gold); text-decoration: underline;">Upgrade</a>
                </div>
                <?php endif; ?>
                
                <form class="message-input-form" id="messageForm" onsubmit="return sendMessage(event)">
                    <textarea 
                        id="messageInput" 
                        class="message-input" 
                        placeholder="<?php echo $limitInfo['can_send'] ? 'Type a message...' : 'Daily limit reached'; ?>"
                        rows="1"
                        <?php echo !$limitInfo['can_send'] ? 'disabled' : ''; ?>></textarea>
                    <button type="submit" class="send-btn" id="sendBtn" <?php echo !$limitInfo['can_send'] ? 'disabled' : ''; ?>>➤</button>
                </form>
            </div>
            <?php else: ?>
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; padding: 2rem;">
                <div style="font-size: 5rem; margin-bottom: 1rem; opacity: 0.5;">💬</div>
                <h3 style="margin: 0 0 0.5rem; color: var(--text-white);">Select a conversation</h3>
                <p style="margin: 0; opacity: 0.8; color: var(--text-gray);">Choose someone to start messaging</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const userId = <?php echo $_SESSION['user_id']; ?>;
const otherUserId = <?php echo $other_user_id ?: 'null'; ?>;
const isPremium = <?php echo $limitInfo['is_premium'] ? 'true' : 'false'; ?>;
let lastMessageId = 0;
let pollInterval;

function loadMessages() {
    if(!otherUserId) return;
    
    fetch(`/api/messages.php?action=get_conversation&user_id=${otherUserId}`)
        .then(r => r.json())
        .then(data => {
            if(data.success && data.messages) {
                displayMessages(data.messages);
            }
        })
        .catch(e => console.error('Error:', e));
}

function displayMessages(messages) {
    const area = document.getElementById('messagesArea');
    const wasBottom = area.scrollHeight - area.scrollTop === area.clientHeight;
    
    area.innerHTML = '';
    
    if(messages.length === 0) {
        area.innerHTML = '<div style="text-align: center; padding: 3rem; color: var(--text-gray);"><div style="font-size: 3.5rem; opacity: 0.5;">👋</div><p>No messages yet. Say hello!</p></div>';
        return;
    }
    
    messages.forEach(msg => {
        const bubble = document.createElement('div');
        bubble.className = `message-bubble ${msg.sender_id == userId ? 'message-sent' : 'message-received'}`;
        
        const time = new Date(msg.created_at).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'});
        bubble.innerHTML = `${escapeHtml(msg.message)}<span class="message-time">${time}</span>`;
        
        area.appendChild(bubble);
        lastMessageId = Math.max(lastMessageId, msg.id);
    });
    
    if(wasBottom || messages.length) {
        setTimeout(() => area.scrollTop = area.scrollHeight, 50);
    }
}

function sendMessage(e) {
    e.preventDefault();
    
    const input = document.getElementById('messageInput');
    const btn = document.getElementById('sendBtn');
    const message = input.value.trim();
    
    if(!message || !otherUserId) return false;
    
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('receiver_id', otherUserId);
    formData.append('message', message);
    
    fetch('/api/messages.php', {method: 'POST', body: formData})
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                input.value = '';
                input.style.height = 'auto';
                loadMessages();
                
                if(data.limit_info && !isPremium) {
                    const count = document.getElementById('remainingCount');
                    if(count) count.textContent = data.limit_info.remaining;
                }
            } else if(data.censored || data.upgrade_required) {
                alert(data.error);
            } else {
                alert(data.error || 'Failed to send');
            }
        })
        .catch(e => {
            console.error('Error:', e);
            alert('Failed to send message');
        })
        .finally(() => {
            btn.disabled = false;
            input.focus();
        });
    
    return false;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-resize
const input = document.getElementById('messageInput');
if(input) {
    input.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    
    input.addEventListener('keydown', function(e) {
        if(e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage(e);
        }
    });
}

// Initialize
if(otherUserId) {
    loadMessages();
    pollInterval = setInterval(loadMessages, 2000); // Poll every 2 seconds
}

window.addEventListener('beforeunload', () => {
    if(pollInterval) clearInterval(pollInterval);
});
</script>

<?php include 'views/footer.php'; ?>