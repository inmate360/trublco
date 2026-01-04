<?php
session_start();
require_once '../config/database.php';

// Check if user is admin
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=/admin/announcements.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check admin status
try {
    $query = "SELECT id, username, is_admin FROM users WHERE id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user || !$user['is_admin']) {
        header('Location: ../index.php');
        exit();
    }
} catch(PDOException $e) {
    error_log("Admin verification error: " . $e->getMessage());
    die("Database error.");
}

$success = '';
$error = '';

// Ensure announcements table exists
try {
    $query = "CREATE TABLE IF NOT EXISTS site_announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
        show_on_homepage BOOLEAN DEFAULT 1,
        show_on_all_pages BOOLEAN DEFAULT 0,
        priority INT DEFAULT 0,
        start_date DATETIME NULL,
        end_date DATETIME NULL,
        is_active BOOLEAN DEFAULT 1,
        is_scrolling BOOLEAN DEFAULT 0,
        scroll_speed INT DEFAULT 50,
        background_color VARCHAR(20) DEFAULT '#4267f5',
        text_color VARCHAR(20) DEFAULT '#ffffff',
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_active (is_active),
        INDEX idx_priority (priority),
        INDEX idx_scrolling (is_scrolling, is_active)
    )";
    $db->exec($query);
} catch(PDOException $e) {
    error_log("Error creating announcements table: " . $e->getMessage());
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['create_announcement'])) {
        try {
            $query = "INSERT INTO site_announcements (title, message, type, show_on_homepage, show_on_all_pages, priority, start_date, end_date, is_scrolling, scroll_speed, background_color, text_color, created_by)
                      VALUES (:title, :message, :type, :homepage, :all_pages, :priority, :start_date, :end_date, :is_scrolling, :scroll_speed, :bg_color, :text_color, :created_by)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':title', $_POST['title']);
            $stmt->bindParam(':message', $_POST['message']);
            $stmt->bindParam(':type', $_POST['type']);
            $homepage = isset($_POST['show_on_homepage']) ? 1 : 0;
            $all_pages = isset($_POST['show_on_all_pages']) ? 1 : 0;
            $stmt->bindParam(':homepage', $homepage, PDO::PARAM_INT);
            $stmt->bindParam(':all_pages', $all_pages, PDO::PARAM_INT);
            $stmt->bindParam(':priority', $_POST['priority'], PDO::PARAM_INT);
            $start = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $end = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $stmt->bindParam(':start_date', $start);
            $stmt->bindParam(':end_date', $end);
            $is_scrolling = isset($_POST['is_scrolling']) ? 1 : 0;
            $scroll_speed = intval($_POST['scroll_speed'] ?? 50);
            $bg_color = $_POST['background_color'] ?? '#4267f5';
            $txt_color = $_POST['text_color'] ?? '#ffffff';
            $stmt->bindParam(':is_scrolling', $is_scrolling, PDO::PARAM_INT);
            $stmt->bindParam(':scroll_speed', $scroll_speed, PDO::PARAM_INT);
            $stmt->bindParam(':bg_color', $bg_color);
            $stmt->bindParam(':text_color', $txt_color);
            $stmt->bindParam(':created_by', $_SESSION['user_id'], PDO::PARAM_INT);
            
            if($stmt->execute()) {
                $success = 'Announcement created successfully!';
            }
        } catch(PDOException $e) {
            error_log("Error creating announcement: " . $e->getMessage());
            $error = 'Failed to create announcement';
        }
    }
    
    if(isset($_POST['toggle_status'])) {
        try {
            $query = "UPDATE site_announcements SET is_active = NOT is_active WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $_POST['announcement_id'], PDO::PARAM_INT);
            $stmt->execute();
            $success = 'Announcement status updated!';
        } catch(PDOException $e) {
            $error = 'Failed to update status';
        }
    }
    
    if(isset($_POST['delete_announcement'])) {
        try {
            $query = "DELETE FROM site_announcements WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $_POST['announcement_id'], PDO::PARAM_INT);
            $stmt->execute();
            $success = 'Announcement deleted!';
        } catch(PDOException $e) {
            $error = 'Failed to delete announcement';
        }
    }
}

// Get all announcements
try {
    $query = "SELECT sa.*, u.username 
              FROM site_announcements sa
              LEFT JOIN users u ON sa.created_by = u.id
              ORDER BY sa.priority DESC, sa.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching announcements: " . $e->getMessage());
    $announcements = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements Management - Admin</title>
    <style>
        :root {
            --bg-dark: #0a0a0f;
            --bg-card: #1a1a2e;
            --border-color: #2d2d44;
            --primary-blue: #4267f5;
            --text-white: #ffffff;
            --text-gray: #a0a0b0;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --danger-red: #ef4444;
            --info-cyan: #06b6d4;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg-dark); color: var(--text-white); line-height: 1.6; }
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 1rem; }
        .admin-header { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; margin-bottom: 2rem; }
        .admin-header h1 { font-size: 2rem; background: linear-gradient(135deg, var(--primary-blue), var(--info-cyan)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem; }
        .admin-nav { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .admin-nav a { padding: 1rem; background: var(--bg-card); border: 2px solid var(--border-color); border-radius: 12px; text-decoration: none; color: var(--text-white); text-align: center; transition: all 0.3s; font-weight: 500; }
        .admin-nav a:hover { background: rgba(66, 103, 245, 0.1); border-color: var(--primary-blue); transform: translateY(-2px); }
        .admin-nav a.active { background: linear-gradient(135deg, rgba(66, 103, 245, 0.2), rgba(6, 182, 212, 0.2)); border-color: var(--primary-blue); }
        .admin-section { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; margin-bottom: 2rem; }
        .admin-section h2 { color: var(--primary-blue); margin-bottom: 1.5rem; font-size: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-white); font-weight: 500; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group input[type="datetime-local"], .form-group textarea, .form-group select { width: 100%; padding: 0.875rem; background: rgba(255, 255, 255, 0.05); border: 2px solid var(--border-color); border-radius: 8px; color: var(--text-white); font-size: 1rem; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--primary-blue); background: rgba(66, 103, 245, 0.1); }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .form-group input[type="checkbox"] { width: 20px; height: 20px; margin-right: 0.5rem; }
        .btn-primary { padding: 0.875rem 1.75rem; border-radius: 8px; background: linear-gradient(135deg, var(--primary-blue), var(--info-cyan)); color: white; border: none; cursor: pointer; font-weight: 600; font-size: 1rem; transition: all 0.3s; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(66, 103, 245, 0.4); }
        .btn-success { background: linear-gradient(135deg, var(--success-green), #059669); }
        .btn-danger { background: linear-gradient(135deg, var(--danger-red), #dc2626); }
        .alert { padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid var(--success-green); color: var(--success-green); }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 1px solid var(--danger-red); color: var(--danger-red); }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: rgba(66, 103, 245, 0.1); padding: 1rem; text-align: left; color: var(--text-white); font-weight: 600; border-bottom: 2px solid var(--border-color); }
        .data-table td { padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-gray); }
        .data-table tr:hover { background: rgba(66, 103, 245, 0.05); }
        .action-btn { padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-right: 0.5rem; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; }
        .action-btn.edit { background: rgba(245, 158, 11, 0.2); color: var(--warning-orange); }
        .action-btn.delete { background: rgba(239, 68, 68, 0.2); color: var(--danger-red); }
        .action-btn:hover { transform: translateY(-2px); }
        .type-badge { padding: 0.375rem 0.875rem; border-radius: 16px; font-size: 0.8rem; font-weight: 600; display: inline-block; }
        .type-info { background: rgba(6, 182, 212, 0.2); color: var(--info-cyan); }
        .type-success { background: rgba(16, 185, 129, 0.2); color: var(--success-green); }
        .type-warning { background: rgba(245, 158, 11, 0.2); color: var(--warning-orange); }
        .type-danger { background: rgba(239, 68, 68, 0.2); color: var(--danger-red); }
        @media (max-width: 768px) { .admin-nav { grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); } }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>📢 Announcements Management</h1>
            <p style="color: var(--text-gray);">Create and manage site-wide announcements for your users</p>
        </div>

        <div class="admin-nav">
            <a href="dashboard.php">📊 Dashboard</a>
            <a href="users.php">👥 Users</a>
            <a href="listings.php">📝 Listings</a>
            <a href="upgrades.php">💎 Upgrades</a>
            <a href="reports.php">🚨 Reports</a>
            <a href="moderate-listings.php">⚖️ Moderate</a>
            <a href="announcements.php" class="active">📢 Announcements</a>
            <a href="categories.php">📁 Categories</a>
            <a href="settings.php">⚙️ Settings</a>
        </div>

        <?php if($success): ?>
        <div class="alert alert-success"><span style="font-size: 1.5rem;">✓</span><span><?php echo htmlspecialchars($success); ?></span></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error"><span style="font-size: 1.5rem;">✗</span><span><?php echo htmlspecialchars($error); ?></span></div>
        <?php endif; ?>

        <div class="admin-section">
            <h2>➕ Create New Announcement</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" required placeholder="e.g., Site Maintenance Scheduled">
                </div>
                
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" rows="4" required placeholder="Enter your announcement message..."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" required>
                            <option value="info">ℹ️ Info (Blue)</option>
                            <option value="success">✅ Success (Green)</option>
                            <option value="warning">⚠️ Warning (Orange)</option>
                            <option value="danger">🚨 Danger (Red)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Priority (0-100)</label>
                        <input type="number" name="priority" value="0" min="0" max="100">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Start Date (optional)</label>
                        <input type="datetime-local" name="start_date">
                    </div>
                    
                    <div class="form-group">
                        <label>End Date (optional)</label>
                        <input type="datetime-local" name="end_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" name="show_on_homepage" value="1" checked>
                        Show on homepage
                    </label>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" name="show_on_all_pages" value="1">
                        Show on all pages
                    </label>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" name="is_scrolling" value="1" id="scrolling_toggle" onchange="toggleScrollingOptions()">
                        <strong style="color: var(--warning-orange);">🎬 Display as scrolling marquee (above header)</strong>
                    </label>
                    <small style="color: var(--text-gray); margin-left: 28px; display: block; margin-top: 0.25rem;">When enabled, this announcement will scroll across the top of all pages</small>
                </div>
                
                <div id="scrolling_options" style="display: none; padding: 1.5rem; background: rgba(66, 103, 245, 0.05); border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 1rem;">
                    <h3 style="color: var(--primary-blue); margin-bottom: 1rem; font-size: 1.1rem;">🎨 Scrolling Banner Options</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div class="form-group">
                            <label>Scroll Speed (10-100)</label>
                            <input type="number" name="scroll_speed" value="50" min="10" max="100" placeholder="50">
                            <small style="color: var(--text-gray); display: block; margin-top: 0.25rem;">Pixels per second</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Background Color</label>
                            <input type="text" name="background_color" value="#4267f5" placeholder="#4267f5">
                            <small style="color: var(--text-gray); display: block; margin-top: 0.25rem;">Hex color code</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Text Color</label>
                            <input type="text" name="text_color" value="#ffffff" placeholder="#ffffff">
                            <small style="color: var(--text-gray); display: block; margin-top: 0.25rem;">Hex color code</small>
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="create_announcement" class="btn-primary">📢 Create Announcement</button>
            </form>
        </div>

        <div class="admin-section">
            <h2>📋 All Announcements (<?php echo count($announcements); ?>)</h2>
            <?php if(count($announcements) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Display</th>
                            <th>Scrolling</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($announcements as $announcement): ?>
                        <tr>
                            <td><?php echo $announcement['id']; ?></td>
                            <td><strong style="color: var(--text-white);"><?php echo htmlspecialchars($announcement['title']); ?></strong></td>
                            <td><span class="type-badge type-<?php echo $announcement['type']; ?>"><?php echo ucfirst($announcement['type']); ?></span></td>
                            <td><?php echo $announcement['priority']; ?></td>
                            <td style="font-size: 0.85rem;">
                                <?php echo $announcement['show_on_homepage'] ? '🏠 Home' : ''; ?>
                                <?php echo $announcement['show_on_all_pages'] ? '🌐 All' : ''; ?>
                            </td>
                            <td>
                                <?php if(isset($announcement['is_scrolling']) && $announcement['is_scrolling']): ?>
                                    <span style="color: var(--warning-orange); font-weight: 600;">🎬 Marquee</span>
                                <?php else: ?>
                                    <span style="color: var(--text-gray);">Static</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: <?php echo $announcement['is_active'] ? 'var(--success-green)' : 'var(--danger-red)'; ?>">
                                    <?php echo $announcement['is_active'] ? '✓ Active' : '✗ Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($announcement['username']); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                    <button type="submit" name="toggle_status" class="action-btn edit">Toggle</button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this announcement?');">
                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                    <button type="submit" name="delete_announcement" class="action-btn delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 3rem; background: rgba(66, 103, 245, 0.05); border-radius: 10px;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📢</div>
                <h3>No Announcements Yet</h3>
                <p style="color: var(--text-gray);">Create your first announcement above</p>
            </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; padding: 2rem; color: var(--text-gray);">
            <p><a href="dashboard.php" style="color: var(--primary-blue); text-decoration: none;">← Back to Dashboard</a></p>
        </div>
    </div>
    
    <script>
    function toggleScrollingOptions() {
        const checkbox = document.getElementById('scrolling_toggle');
        const options = document.getElementById('scrolling_options');
        options.style.display = checkbox.checked ? 'block' : 'none';
    }
    </script>
</body>
</html>