<?php
session_start();
require_once 'config/database.php';
require_once 'classes/PrivateMessaging.php';
require_once 'classes/ContentModerator.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$pm = new PrivateMessaging($db);

$thread_id = isset($_GET['thread']) ? (int)$_GET['thread'] : 0;

if(!$thread_id) {
    header('Location: messages-inbox.php');
    exit();
}

$thread = $pm->getThread($thread_id, $_SESSION['user_id']);

if(!$thread) {
    header('Location: messages-inbox.php');
    exit();
}

// Determine other user
$is_starter = ($thread['starter_id'] == $_SESSION['user_id']);
$other_user = $is_starter ? [
    'id' => $thread['recipient_id'],
    'name' => $thread['recipient_name'],
    'verified' => $thread['recipient_verified'],
    'image' => $thread['recipient_image']
] : [
    'id' => $thread['starter_id'],
    'name' => $thread['starter_name'],
    'verified' => $thread['starter_verified'],
    'image' => $thread['starter_image']
];

// Mark as read
$pm->markAsRead($thread_id, $_SESSION['user_id']);

// Handle AJAX reply
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_reply'])) {
    header('Content-Type: application/json');
    
    $message = trim($_POST['message']);
    
    if(empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        exit;
    }
    
    $result = $pm->sendReply($thread_id, $_SESSION['user_id'], $message);
    
    if($result['success']) {
        // AI Content Moderation
        $moderator = new ContentModerator($db);
        $moderationResult = $moderator->moderateText(
            $message, 
            'message', 
            $result['message_id'], 
            $_SESSION['user_id']
        );
        
        if ($moderationResult['risk_level'] === 'critical') {
            // Delete the message
            $deleteQuery = "DELETE FROM messages WHERE id = :message_id";
            $deleteStmt = $db->prepare($deleteQuery);
            $deleteStmt->execute([':message_id' => $result['message_id']]);
            
            echo json_encode([
                'success' => false, 
                'error' => 'Your message was blocked due to policy violations.'
            ]);
            exit;
        }
        
        if ($moderationResult['risk_level'] === 'high') {
            $result['warning'] = 'This message has been flagged for review.';
        }
    }
    
    echo json_encode($result);
    exit;
}

include 'views/header.php';
?>

<div class="flex h-screen flex-col bg-gh-bg">

  <!-- Chat Header -->
  <div class="border-b border-gh-border bg-gh-panel px-4 py-3">
    <div class="mx-auto flex max-w-4xl items-center justify-between">
      <div class="flex items-center gap-3">
        <a href="messages-inbox.php" class="flex h-9 w-9 items-center justify-center rounded-lg text-gh-muted transition-all hover:bg-gh-panel2 hover:text-gh-fg">
          <i class="bi bi-arrow-left text-lg"></i>
        </a>
        
        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-pink-600 to-purple-600 text-base font-bold text-white">
          <?php echo strtoupper(substr($other_user['name'], 0, 1)); ?>
        </div>
        
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-base font-bold text-white"><?php echo htmlspecialchars($other_user['name']); ?></h2>
            <?php if($other_user['verified']): ?>
            <i class="bi bi-patch-check-fill text-sm text-green-500"></i>
            <?php endif; ?>
          </div>
          <p class="text-xs text-gh-muted"><?php echo htmlspecialchars($thread['subject']); ?></p>
        </div>
      </div>

      <div class="hidden md:flex items-center gap-2 rounded-lg border border-purple-500/30 bg-purple-500/10 px-3 py-1.5">
        <i class="bi bi-shield-check text-purple-400"></i>
        <span class="text-xs font-semibold text-purple-400">AI Protected</span>
      </div>
    </div>
  </div>

  <!-- Messages Container -->
  <div id="messagesContainer" class="flex-1 overflow-y-auto bg-gh-bg px-4 py-6">
    <div class="mx-auto max-w-4xl space-y-4">
      <?php foreach($thread['messages'] as $msg): ?>
      <?php $is_mine = ($msg['sender_id'] == $_SESSION['user_id']); ?>
      
      <div class="flex <?php echo $is_mine ? 'justify-end' : 'justify-start'; ?>" data-message-id="<?php echo $msg['id']; ?>">
        <div class="<?php echo $is_mine ? 'max-w-[70%]' : 'max-w-[70%]'; ?>">
          
          <?php if(!$is_mine): ?>
          <div class="mb-1 flex items-center gap-2 text-xs text-gh-muted">
            <span class="font-semibold"><?php echo htmlspecialchars($msg['username']); ?></span>
            <span><?php echo date('M d, g:i A', strtotime($msg['created_at'])); ?></span>
          </div>
          <?php endif; ?>
          
          <div class="<?php echo $is_mine ? 'rounded-2xl rounded-br-md bg-gradient-to-r from-pink-600 to-purple-600 text-white' : 'rounded-2xl rounded-bl-md border border-gh-border bg-gh-panel text-gh-fg'; ?> px-4 py-3">
            <p class="whitespace-pre-wrap text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
          </div>
          
          <?php if($is_mine): ?>
          <div class="mt-1 text-right text-xs text-gh-muted">
            <?php echo date('M d, g:i A', strtotime($msg['created_at'])); ?>
          </div>
          <?php endif; ?>
          
        </div>
      </div>
      
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Reply Input -->
  <div class="border-t border-gh-border bg-gh-panel px-4 py-3">
    <div class="mx-auto max-w-4xl">
      <form id="replyForm" class="flex gap-2">
        <textarea id="messageInput" name="message" placeholder="Type your message..." rows="1"
                  class="min-h-[44px] max-h-32 flex-1 resize-none rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none" required></textarea>
        <button type="submit" id="sendBtn" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 text-white transition-all hover:brightness-110 disabled:opacity-50">
          <i class="bi bi-send-fill"></i>
        </button>
      </form>
      <p class="text-center text-10px text-gh-muted mt-2 flex items-center justify-center gap-1">
        <i class="bi bi-shield-check text-gh-accent"></i>
        Protected by Perplexity AI
      </p>
    </div>
  </div>

</div>

<script>
const threadId = <?php echo $thread_id; ?>;
const userId = <?php echo $_SESSION['user_id']; ?>;
let lastMessageId = <?php echo !empty($thread['messages']) ? end($thread['messages'])['id'] : 0; ?>;

// Auto-resize textarea
const messageInput = document.getElementById('messageInput');
messageInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 128) + 'px';
});

// Handle form submission
document.getElementById('replyForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const message = messageInput.value.trim();
    if(!message) return;
    
    const sendBtn = document.getElementById('sendBtn');
    sendBtn.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('ajax_reply', '1');
        formData.append('message', message);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if(result.success) {
            messageInput.value = '';
            messageInput.style.height = 'auto';
            
            if(result.warning) {
                alert(result.warning);
            }
            
            pollNewMessages();
        } else {
            alert(result.error || 'Failed to send message');
        }
    } catch(error) {
        console.error('Error:', error);
        alert('Failed to send message');
    } finally {
        sendBtn.disabled = false;
        messageInput.focus();
    }
});

// Poll for new messages
async function pollNewMessages() {
    try {
        const response = await fetch(`ajax/poll-messages.php?thread=${threadId}&since=${lastMessageId}`);
        const result = await response.json();
        
        if(result.success && result.messages.length > 0) {
            const container = document.querySelector('#messagesContainer > div');
            
            result.messages.forEach(msg => {
                const isMine = (msg.sender_id == userId);
                const html = generateMessageHTML(msg, isMine);
                container.insertAdjacentHTML('beforeend', html);
                lastMessageId = Math.max(lastMessageId, msg.id);
            });
            
            scrollToBottom();
        }
    } catch(error) {
        console.error('Polling error:', error);
    }
}

function generateMessageHTML(msg, isMine) {
    const date = new Date(msg.created_at);
    const formattedDate = date.toLocaleString('en-US', { 
        month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' 
    });
    
    if(isMine) {
        return `
        <div class="flex justify-end" data-message-id="${msg.id}">
          <div class="max-w-[70%]">
            <div class="rounded-2xl rounded-br-md bg-gradient-to-r from-pink-600 to-purple-600 px-4 py-3 text-white">
              <p class="whitespace-pre-wrap text-sm leading-relaxed">${escapeHtml(msg.message)}</p>
            </div>
            <div class="mt-1 text-right text-xs text-gh-muted">${formattedDate}</div>
          </div>
        </div>`;
    } else {
        return `
        <div class="flex justify-start" data-message-id="${msg.id}">
          <div class="max-w-[70%]">
            <div class="mb-1 flex items-center gap-2 text-xs text-gh-muted">
              <span class="font-semibold">${escapeHtml(msg.username)}</span>
              <span>${formattedDate}</span>
            </div>
            <div class="rounded-2xl rounded-bl-md border border-gh-border bg-gh-panel px-4 py-3 text-gh-fg">
              <p class="whitespace-pre-wrap text-sm leading-relaxed">${escapeHtml(msg.message)}</p>
            </div>
          </div>
        </div>`;
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/\n/g, '<br>');
}

function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    container.scrollTop = container.scrollHeight;
}

scrollToBottom();
setInterval(pollNewMessages, 3000);
messageInput.focus();
</script>

<?php include 'views/footer.php'; ?>
