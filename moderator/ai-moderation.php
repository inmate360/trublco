<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Moderator.php';
require_once '../classes/AIContentFilter.php';
require_once '../classes/BulkModeration.php';
require_once '../classes/CSRF.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$moderator = new Moderator($db);
if(!$moderator->isModerator($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$aiFilter = new AIContentFilter($db);
$bulkMod = new BulkModeration($db);

$success = '';
$error = '';

// Handle bulk action
if(isset($_POST['bulk_action']) && isset($_POST['selected_items'])) {
    if(!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['bulk_action'];
        $content_type = $_POST['content_type'];
        $items = $_POST['selected_items'];
        $reason = $_POST['bulk_reason'] ?? 'Bulk moderation action';
        
        $result = $bulkMod->processBulkAction($action, $content_type, $items, $reason, $_SESSION['user_id']);
        
        if($result['success']) {
            $success = "Processed {$result['processed']} items successfully";
            if($result['failed'] > 0) {
                $success .= ", {$result['failed']} failed";
            }
        } else {
            $error = $result['error'] ?? 'Bulk action failed';
        }
    }
}

// Get flagged content
$flagged_listings = [];
$flagged_images = [];
$flagged_text = [];

$query = "SELECT l.*, u.username, c.name as category_name,
          tm.toxicity_score, tm.spam_score, tm.sexual_content_score
          FROM listings l
          LEFT JOIN users u ON l.user_id = u.id
          LEFT JOIN categories c ON l.category_id = c.id
          LEFT JOIN text_moderation tm ON tm.content_id = l.id AND tm.content_type = 'listing'
          WHERE l.moderation_status = 'flagged' OR l.auto_flagged = TRUE
          ORDER BY l.created_at DESC
          LIMIT 50";
$stmt = $db->prepare($query);
$stmt->execute();
$flagged_listings = $stmt->fetchAll();

$query = "SELECT im.*, u.username
          FROM image_moderation im
          LEFT JOIN users u ON im.user_id = u.id
          WHERE im.status = 'flagged'
          ORDER BY im.nsfw_score DESC
          LIMIT 50";
$stmt = $db->prepare($query);
$stmt->execute();
$flagged_images = $stmt->fetchAll();

$query = "SELECT tm.*, u.username,
          CASE tm.content_type
            WHEN 'listing' THEN (SELECT title FROM listings WHERE id = tm.content_id)
            WHEN 'message' THEN 'Message content'
          END as content_title
          FROM text_moderation tm
          LEFT JOIN users u ON tm.user_id = u.id
          WHERE tm.status = 'flagged'
          ORDER BY tm.toxicity_score DESC, tm.spam_score DESC
          LIMIT 50";
$stmt = $db->prepare($query);
$stmt->execute();
$flagged_text = $stmt->fetchAll();

// Get moderation stats
$query = "SELECT 
          (SELECT COUNT(*) FROM listings WHERE moderation_status = 'flagged') as flagged_listings_count,
          (SELECT COUNT(*) FROM image_moderation WHERE status = 'flagged') as flagged_images_count,
          (SELECT COUNT(*) FROM text_moderation WHERE status = 'flagged') as flagged_text_count,
          (SELECT COUNT(*) FROM user_warnings WHERE acknowledged = FALSE) as unacknowledged_warnings,
          (SELECT COUNT(*) FROM moderation_appeals WHERE status = 'pending') as pending_appeals";
$stmt = $db->prepare($query);
$stmt->execute();
$stats = $stmt->fetch();

$csrf_token = CSRF::getToken();

include '../views/header.php';
?>

<link rel="stylesheet" href="../assets/css/dark-blue-theme.css">

<style>
.mod-container {
    max-width: 1600px;
    margin: 2rem auto;
    padding: 0 20px;
}

.mod-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.stat-value.warning { color: var(--warning-orange); }
.stat-value.danger { color: var(--danger-red); }
.stat-value.success { color: var(--success-green); }

.stat-label {
    color: var(--text-gray);
    font-size: 0.9rem;
}

.mod-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid var(--border-color);
}

.mod-tab {
    padding: 1rem 2rem;
    background: transparent;
    border: none;
    color: var(--text-gray);
    cursor: pointer;
    transition: all 0.3s;
    border-bottom: 3px solid transparent;
}

.mod-tab.active {
    color: var(--primary-blue);
    border-bottom-color: var(--primary-blue);
}

.mod-tab:hover {
    color: var(--text-white);
}

.mod-content {
    display: none;
}

.mod-content.active {
    display: block;
}

.flagged-item {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.flagged-item.high-risk {
    border-color: var(--danger-red);
    background: rgba(239, 68, 68, 0.05);
}

.flagged-item.medium-risk {
    border-color: var(--warning-orange);
    background: rgba(245, 158, 11, 0.05);
}

.score-badges {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
}

.score-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.score-badge.high {
    background: rgba(239, 68, 68, 0.2);
    color: var(--danger-red);
}

.score-badge.medium {
    background: rgba(245, 158, 11, 0.2);
    color: var(--warning-orange);
}

.score-badge.low {
    background: rgba(66, 103, 245, 0.2);
    color: var(--primary-blue);
}

.bulk-actions {
    background: rgba(66, 103, 245, 0.1);
    border: 2px solid var(--primary-blue);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 2rem;
    display: none;
}

.bulk-actions.show {
    display: block;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.flagged-content {
    background: rgba(0, 0, 0, 0.3);
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
    max-height: 150px;
    overflow-y: auto;
}

.flagged-words {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.flagged-word {
    background: var(--danger-red);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 8px;
    font-size: 0.85rem;
}

@media (max-width: 768px) {
    .mod-tabs {
        flex-direction: column;
        gap: 0;
    }
    
    .mod-tab {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border-color);
    }
}
</style>

<div class="page-content">
    <div class="mod-container">
        <div style="margin-bottom: 2rem;">
            <h1>🤖 AI Content Moderation</h1>
            <p style="color: var(--text-gray);">Automated content filtering and bulk moderation tools</p>
        </div>

        <?php if($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="mod-stats">
            <div class="stat-card">
                <div class="stat-value danger"><?php echo $stats['flagged_listings_count']; ?></div>
                <div class="stat-label">Flagged Listings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value warning"><?php echo $stats['flagged_images_count']; ?></div>
                <div class="stat-label">Flagged Images</div>
            </div>
            <div class="stat-card">
                <div class="stat-value warning"><?php echo $stats['flagged_text_count']; ?></div>
                <div class="stat-label">Flagged Text</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['unacknowledged_warnings']; ?></div>
                <div class="stat-label">Active Warnings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['pending_appeals']; ?></div>
                <div class="stat-label">Pending Appeals</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mod-tabs">
            <button class="mod-tab active" onclick="switchTab('listings')">
                Flagged Listings (<?php echo count($flagged_listings); ?>)
            </button>
            <button class="mod-tab" onclick="switchTab('images')">
                Flagged Images (<?php echo count($flagged_images); ?>)
            </button>
            <button class="mod-tab" onclick="switchTab('text')">
                Flagged Text (<?php echo count($flagged_text); ?>)
            </button>
            <button class="mod-tab" onclick="switchTab('bulk')">
                Bulk Actions
            </button>
        </div>

        <!-- Flagged Listings -->
        <div class="mod-content active" id="listings-content">
            <h2 style="margin-bottom: 1.5rem;">Flagged Listings</h2>
            
            <?php if(count($flagged_listings) > 0): ?>
                <div class="bulk-actions" id="bulkActions">
                    <form method="POST">
                        <?php echo CSRF::getHiddenInput(); ?>
                        <input type="hidden" name="content_type" value="listing">
                        
                        <div style="display: flex; gap: 1rem; align-items: end;">
                            <div class="form-group" style="flex: 1;">
                                <label>Bulk Action</label>
                                <select name="bulk_action" required>
                                    <option value="">Select action...</option>
                                    <option value="approve">Approve All</option>
                                    <option value="reject">Reject All</option>
                                    <option value="delete">Delete All</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <label>Reason (optional)</label>
                                <input type="text" name="bulk_reason" placeholder="Reason for action...">
                            </div>
                            <button type="submit" class="btn-primary">Apply to Selected</button>
                        </div>
                        <div id="selectedItems"></div>
                    </form>
                </div>
                
                <?php foreach($flagged_listings as $item): 
                    $riskLevel = 'low';
                    if($item['toxicity_score'] > 70 || $item['spam_score'] > 70) {
                        $riskLevel = 'high';
                    } elseif($item['toxicity_score'] > 50 || $item['spam_score'] > 50) {
                        $riskLevel = 'medium';
                    }
                ?>
                <div class="flagged-item <?php echo $riskLevel; ?>-risk">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <input type="checkbox" 
                                       class="item-checkbox" 
                                       data-id="<?php echo $item['id']; ?>"
                                       onchange="updateBulkActions()">
                                <h3 style="margin: 0;"><?php echo htmlspecialchars($item['title']); ?></h3>
                            </label>
                            
                            <div style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: 0.5rem;">
                                By <?php echo htmlspecialchars($item['username']); ?> • 
                                <?php echo htmlspecialchars($item['category_name']); ?> •
                                <?php echo date('M j, Y g:i A', strtotime($item['created_at'])); ?>
                            </div>
                            
                            <div class="score-badges">
                                <?php if($item['toxicity_score'] > 0): ?>
                                <span class="score-badge <?php echo $item['toxicity_score'] > 70 ? 'high' : ($item['toxicity_score'] > 50 ? 'medium' : 'low'); ?>">
                                    Toxicity: <?php echo round($item['toxicity_score']); ?>%
                                </span>
                                <?php endif; ?>
                                
                                <?php if($item['spam_score'] > 0): ?>
                                <span class="score-badge <?php echo $item['spam_score'] > 70 ? 'high' : ($item['spam_score'] > 50 ? 'medium' : 'low'); ?>">
                                    Spam: <?php echo round($item['spam_score']); ?>%
                                </span>
                                <?php endif; ?>
                                
                                <?php if($item['sexual_content_score'] > 0): ?>
                                <span class="score-badge <?php echo $item['sexual_content_score'] > 70 ? 'high' : ($item['sexual_content_score'] > 50 ? 'medium' : 'low'); ?>">
                                    Sexual: <?php echo round($item['sexual_content_score']); ?>%
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flagged-content">
                                <?php echo nl2br(htmlspecialchars(substr($item['description'], 0, 300))); ?>...
                            </div>
                        </div>
                        
                        <div class="action-buttons" style="flex-direction: column;">
                            <a href="../listing.php?id=<?php echo $item['id']; ?>" target="_blank" class="btn-secondary btn-small">
                                View Full
                            </a>
                            <button class="btn-primary btn-small" onclick="approveListing(<?php echo $item['id']; ?>)">
                                Approve
                            </button>
                            <button class="btn-danger btn-small" onclick="rejectListing(<?php echo $item['id']; ?>)">
                                Reject
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">✅</div>
                    <h3>No Flagged Listings</h3>
                    <p style="color: var(--text-gray);">All listings have been reviewed</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Flagged Images -->
        <div class="mod-content" id="images-content">
            <h2 style="margin-bottom: 1.5rem;">Flagged Images</h2>
            
            <?php if(count($flagged_images) > 0): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem;">
                    <?php foreach($flagged_images as $img): ?>
                    <div class="flagged-item <?php echo $img['nsfw_score'] > 70 ? 'high' : 'medium'; ?>-risk">
                        <img src="<?php echo htmlspecialchars($img['image_url']); ?>" 
                             style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px; margin-bottom: 0.5rem;"
                             alt="Flagged image">
                        
                        <div style="font-size: 0.85rem; color: var(--text-gray); margin-bottom: 0.5rem;">
                            By <?php echo htmlspecialchars($img['username']); ?>
                        </div>
                        
                        <div class="score-badges">
                            <span class="score-badge <?php echo $img['nsfw_score'] > 70 ? 'high' : 'medium'; ?>">
                                NSFW: <?php echo round($img['nsfw_score']); ?>%
                            </span>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="btn-primary btn-small" onclick="approveImage(<?php echo $img['id']; ?>)">
                                Approve
                            </button>
                            <button class="btn-danger btn-small" onclick="rejectImage(<?php echo $img['id']; ?>)">
                                Reject
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">✅</div>
                    <h3>No Flagged Images</h3>
                    <p style="color: var(--text-gray);">All images have been reviewed</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Flagged Text -->
        <div class="mod-content" id="text-content">
            <h2 style="margin-bottom: 1.5rem;">Flagged Text Content</h2>
            
            <?php if(count($flagged_text) > 0): ?>
                <?php foreach($flagged_text as $text): ?>
                <div class="flagged-item <?php echo $text['toxicity_score'] > 70 ? 'high' : 'medium'; ?>-risk">
                    <h4><?php echo htmlspecialchars($text['content_title']); ?></h4>
                    <div style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: 0.5rem;">
                        By <?php echo htmlspecialchars($text['username']); ?> • 
                        <?php echo date('M j, Y', strtotime($text['created_at'])); ?>
                    </div>
                    
                    <div class="score-badges">
                        <span class="score-badge <?php echo $text['toxicity_score'] > 70 ? 'high' : 'medium'; ?>">
                            Toxicity: <?php echo round($text['toxicity_score']); ?>%
                        </span>
                        <span class="score-badge <?php echo $text['spam_score'] > 70 ? 'high' : 'medium'; ?>">
                            Spam: <?php echo round($text['spam_score']); ?>%
                        </span>
                    </div>
                    
                    <?php if($text['flagged_words']): 
                        $flaggedWords = json_decode($text['flagged_words'], true);
                        if(!empty($flaggedWords)):
                    ?>
                    <div class="flagged-words">
                        <?php foreach($flaggedWords as $word): ?>
                        <span class="flagged-word"><?php echo htmlspecialchars($word['word']); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; endif; ?>
                    
                    <div class="flagged-content">
                        <?php echo nl2br(htmlspecialchars($text['text_content'])); ?>
                    </div>
                    
                    <div class="action-buttons">
                        <button class="btn-primary btn-small" onclick="approveText(<?php echo $text['id']; ?>)">
                            Approve
                        </button>
                        <button class="btn-danger btn-small" onclick="rejectText(<?php echo $text['id']; ?>)">
                            Reject
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">✅</div>
                    <h3>No Flagged Text</h3>
                    <p style="color: var(--text-gray);">All text content has been reviewed</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bulk Actions Tab -->
        <div class="mod-content" id="bulk-content">
            <h2>Bulk Actions</h2>
            <p style="color: var(--text-gray); margin-bottom: 2rem;">
                Select items from any tab and apply actions in bulk
            </p>
            <!-- Bulk actions form will appear here when items are selected -->
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    // Hide all content
    document.querySelectorAll('.mod-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active from all tabs
    document.querySelectorAll('.mod-tab').forEach(t => {
        t.classList.remove('active');
    });
    
    // Show selected content and activate tab
    document.getElementById(tab + '-content').classList.add('active');
    event.target.classList.add('active');
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const bulkActions = document.getElementById('bulkActions');
    
    if(checkboxes.length > 0) {
        bulkActions.classList.add('show');
        
        // Create hidden inputs for selected items
        let html = '';
        checkboxes.forEach(cb => {
            html += `<input type="hidden" name="selected_items[]" value="${cb.dataset.id}">`;
        });
        document.getElementById('selectedItems').innerHTML = html;
    } else {
        bulkActions.classList.remove('show');
    }
}

function approveListing(id) {
    if(!confirm('Approve this listing?')) return;
    
    fetch('/moderator/moderate-action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=approve&type=listing&id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert('Failed to approve: ' + (data.error || 'Unknown error'));
        }
    });
}

function rejectListing(id) {
    const reason = prompt('Reason for rejection:');
    if(!reason) return;
    
    fetch('/moderator/moderate-action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=reject&type=listing&id=${id}&reason=${encodeURIComponent(reason)}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert('Failed to reject: ' + (data.error || 'Unknown error'));
        }
    });
}

function approveImage(id) {
    if(!confirm('Approve this image?')) return;
    
    fetch('/moderator/moderate-action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=approve&type=image&id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert('Failed to approve: ' + (data.error || 'Unknown error'));
        }
    });
}

function rejectImage(id) {
    if(!confirm('Reject and delete this image?')) return;
    
    fetch('/moderator/moderate-action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=reject&type=image&id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert('Failed to reject: ' + (data.error || 'Unknown error'));
        }
    });
}

function approveText(id) {
    if(!confirm('Approve this text content?')) return;
    
    fetch('/moderator/moderate-action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=approve&type=text&id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert('Failed to approve: ' + (data.error || 'Unknown error'));
        }
    });
}

function rejectText(id) {
    const reason = prompt('Reason for rejection:');
    if(!reason) return;
    
    fetch('/moderator/moderate-action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=reject&type=text&id=${id}&reason=${encodeURIComponent(reason)}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert('Failed to reject: ' + (data.error || 'Unknown error'));
        }
    });
}
</script>

<?php include '../views/footer.php'; ?>