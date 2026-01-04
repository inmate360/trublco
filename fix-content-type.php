<?php
// fix-content-type.php - Diagnostic and Repair Tool
require_once 'config/database.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Content Type Issues - Basehit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'gh-bg': '#0a0a0f',
                        'gh-panel': '#1a1a2e',
                        'gh-border': '#2a2a3e',
                        'gh-accent': '#e94560',
                        'gh-success': '#00d9a3',
                        'gh-fg': '#f5f5f7'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gh-bg text-gh-fg p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-4xl font-bold mb-6">
            <i class="bi bi-wrench text-gh-accent"></i>
            Content Type Diagnostic & Repair
        </h1>
        
        <?php
        echo '<div class="space-y-4">';
        
        // Step 1: Check if database connection works
        echo '<div class="bg-gh-panel border border-gh-border rounded-lg p-4">';
        echo '<h2 class="text-xl font-bold mb-2">1. Database Connection</h2>';
        if ($db && !$db->connect_error) {
            echo '<p class="text-gh-success">✓ Connected to: ' . htmlspecialchars($db->host_info) . '</p>';
            $dbName = $db->query("SELECT DATABASE()")->fetch_row()[0];
            echo '<p class="text-gh-muted">Database: ' . htmlspecialchars($dbName) . '</p>';
        } else {
            echo '<p class="text-red-500">✗ Database connection failed!</p>';
            exit;
        }
        echo '</div>';
        
        // Step 2: Check if moderation_queue table exists
        echo '<div class="bg-gh-panel border border-gh-border rounded-lg p-4">';
        echo '<h2 class="text-xl font-bold mb-2">2. Table Existence</h2>';
        $tableExists = $db->query("SHOW TABLES LIKE 'moderation_queue'")->num_rows > 0;
        
        if ($tableExists) {
            echo '<p class="text-gh-success">✓ Table "moderation_queue" exists</p>';
        } else {
            echo '<p class="text-red-500">✗ Table "moderation_queue" does NOT exist</p>';
            echo '<p class="text-yellow-500 mt-2">Creating table...</p>';
            
            $createTable = "CREATE TABLE `moderation_queue` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `content_type` ENUM('listing', 'story', 'forum_post', 'message', 'profile') NOT NULL,
                `content_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `content_text` TEXT,
                `content_images` TEXT,
                `status` ENUM('pending', 'approved', 'rejected', 'flagged') DEFAULT 'pending',
                `moderation_score` JSON,
                `flagged_categories` JSON,
                `confidence_score` DECIMAL(3,2),
                `auto_action` BOOLEAN DEFAULT FALSE,
                `reviewed_by` INT,
                `reviewed_at` DATETIME,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_status` (`status`),
                INDEX `idx_content` (`content_type`, `content_id`),
                INDEX `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if ($db->query($createTable)) {
                echo '<p class="text-gh-success mt-1">✓ Table created successfully!</p>';
                $tableExists = true;
            } else {
                echo '<p class="text-red-500 mt-1">✗ Failed to create table: ' . $db->error . '</p>';
            }
        }
        echo '</div>';
        
        // Step 3: Check table structure
        if ($tableExists) {
            echo '<div class="bg-gh-panel border border-gh-border rounded-lg p-4">';
            echo '<h2 class="text-xl font-bold mb-2">3. Table Structure</h2>';
            
            $columns = $db->query("DESCRIBE moderation_queue")->fetch_all(MYSQLI_ASSOC);
            
            echo '<div class="overflow-x-auto">';
            echo '<table class="w-full text-sm">';
            echo '<thead><tr class="border-b border-gh-border">';
            echo '<th class="text-left py-2 px-3">Field</th>';
            echo '<th class="text-left py-2 px-3">Type</th>';
            echo '<th class="text-left py-2 px-3">Null</th>';
            echo '<th class="text-left py-2 px-3">Default</th>';
            echo '</tr></thead><tbody>';
            
            $hasContentType = false;
            foreach ($columns as $col) {
                if ($col['Field'] === 'content_type') {
                    $hasContentType = true;
                    echo '<tr class="border-b border-gh-border bg-green-500/10">';
                } else {
                    echo '<tr class="border-b border-gh-border">';
                }
                echo '<td class="py-2 px-3">' . htmlspecialchars($col['Field']) . '</td>';
                echo '<td class="py-2 px-3 text-gh-muted">' . htmlspecialchars($col['Type']) . '</td>';
                echo '<td class="py-2 px-3 text-gh-muted">' . htmlspecialchars($col['Null']) . '</td>';
                echo '<td class="py-2 px-3 text-gh-muted">' . htmlspecialchars($col['Default'] ?? 'NULL') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
            
            if ($hasContentType) {
                echo '<p class="text-gh-success mt-3">✓ Column "content_type" exists</p>';
            } else {
                echo '<p class="text-red-500 mt-3">✗ Column "content_type" is MISSING!</p>';
                echo '<p class="text-yellow-500 mt-2">Adding content_type column...</p>';
                
                $addColumn = "ALTER TABLE moderation_queue 
                    ADD COLUMN content_type ENUM('listing', 'story', 'forum_post', 'message', 'profile') NOT NULL AFTER id";
                
                if ($db->query($addColumn)) {
                    echo '<p class="text-gh-success mt-1">✓ Column added successfully!</p>';
                } else {
                    echo '<p class="text-red-500 mt-1">✗ Failed to add column: ' . $db->error . '</p>';
                }
            }
            echo '</div>';
            
            // Step 4: Check for data
            echo '<div class="bg-gh-panel border border-gh-border rounded-lg p-4">';
            echo '<h2 class="text-xl font-bold mb-2">4. Data Check</h2>';
            
            $count = $db->query("SELECT COUNT(*) as total FROM moderation_queue")->fetch_assoc()['total'];
            echo '<p class="text-gh-muted">Total rows in table: <span class="text-gh-fg font-bold">' . $count . '</span></p>';
            
            if ($count > 0) {
                $sample = $db->query("SELECT id, content_type, content_id, status, created_at FROM moderation_queue LIMIT 5")->fetch_all(MYSQLI_ASSOC);
                echo '<div class="mt-3 overflow-x-auto">';
                echo '<p class="text-sm text-gh-muted mb-2">Sample data (first 5 rows):</p>';
                echo '<table class="w-full text-sm">';
                echo '<thead><tr class="border-b border-gh-border">';
                echo '<th class="text-left py-2 px-3">ID</th>';
                echo '<th class="text-left py-2 px-3">Content Type</th>';
                echo '<th class="text-left py-2 px-3">Content ID</th>';
                echo '<th class="text-left py-2 px-3">Status</th>';
                echo '<th class="text-left py-2 px-3">Created</th>';
                echo '</tr></thead><tbody>';
                
                foreach ($sample as $row) {
                    echo '<tr class="border-b border-gh-border">';
                    echo '<td class="py-2 px-3">' . $row['id'] . '</td>';
                    echo '<td class="py-2 px-3 text-gh-accent">' . htmlspecialchars($row['content_type']) . '</td>';
                    echo '<td class="py-2 px-3">' . $row['content_id'] . '</td>';
                    echo '<td class="py-2 px-3">' . $row['status'] . '</td>';
                    echo '<td class="py-2 px-3 text-gh-muted">' . date('M j, g:i A', strtotime($row['created_at'])) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }
            echo '</div>';
            
            // Step 5: Test insert
            echo '<div class="bg-gh-panel border border-gh-border rounded-lg p-4">';
            echo '<h2 class="text-xl font-bold mb-2">5. Test Insert</h2>';
            
            $testInsert = "INSERT INTO moderation_queue 
                (content_type, content_id, user_id, content_text, status) 
                VALUES ('listing', 999, 1, 'Test content for diagnostic', 'pending')";
            
            if ($db->query($testInsert)) {
                $insertId = $db->insert_id;
                echo '<p class="text-gh-success">✓ Test insert successful! ID: ' . $insertId . '</p>';
                
                // Clean up test data
                $db->query("DELETE FROM moderation_queue WHERE id = $insertId");
                echo '<p class="text-gh-muted text-sm mt-1">Test data cleaned up</p>';
            } else {
                echo '<p class="text-red-500">✗ Test insert failed: ' . $db->error . '</p>';
            }
            echo '</div>';
        }
        
        // Step 6: Check moderation_settings table
        echo '<div class="bg-gh-panel border border-gh-border rounded-lg p-4">';
        echo '<h2 class="text-xl font-bold mb-2">6. Moderation Settings Table</h2>';
        
        $settingsExists = $db->query("SHOW TABLES LIKE 'moderation_settings'")->num_rows > 0;
        
        if ($settingsExists) {
            echo '<p class="text-gh-success">✓ Table "moderation_settings" exists</p>';
            $settingsCount = $db->query("SELECT COUNT(*) as total FROM moderation_settings")->fetch_assoc()['total'];
            echo '<p class="text-gh-muted">Settings rows: ' . $settingsCount . '</p>';
            
            if ($settingsCount === 0) {
                echo '<p class="text-yellow-500 mt-2">No settings found. Inserting defaults...</p>';
                $db->query("INSERT IGNORE INTO moderation_settings (content_type, blocked_categories) VALUES
                    ('listing', '[\"sexual_minors\", \"hate_speech\", \"violence\", \"illegal_activity\", \"spam\"]'),
                    ('story', '[\"sexual_minors\", \"hate_speech\", \"extreme_violence\", \"self_harm\"]'),
                    ('forum_post', '[\"sexual_minors\", \"hate_speech\", \"harassment\", \"doxxing\"]'),
                    ('profile', '[\"sexual_minors\", \"hate_speech\", \"impersonation\"]'),
                    ('message', '[\"sexual_minors\", \"harassment\", \"spam\", \"phishing\"]')
                ");
                echo '<p class="text-gh-success mt-1">✓ Default settings added</p>';
            }
        } else {
            echo '<p class="text-red-500">✗ Table "moderation_settings" does NOT exist</p>';
            echo '<p class="text-yellow-500 mt-2">Creating settings table...</p>';
            
            $createSettings = "CREATE TABLE `moderation_settings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `content_type` VARCHAR(50) NOT NULL UNIQUE,
                `auto_reject_threshold` DECIMAL(3,2) DEFAULT 0.80,
                `auto_flag_threshold` DECIMAL(3,2) DEFAULT 0.50,
                `blocked_categories` JSON,
                `enabled` BOOLEAN DEFAULT TRUE,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if ($db->query($createSettings)) {
                echo '<p class="text-gh-success mt-1">✓ Settings table created!</p>';
                
                $db->query("INSERT IGNORE INTO moderation_settings (content_type, blocked_categories) VALUES
                    ('listing', '[\"sexual_minors\", \"hate_speech\", \"violence\", \"illegal_activity\", \"spam\"]'),
                    ('story', '[\"sexual_minors\", \"hate_speech\", \"extreme_violence\", \"self_harm\"]'),
                    ('forum_post', '[\"sexual_minors\", \"hate_speech\", \"harassment\", \"doxxing\"]'),
                    ('profile', '[\"sexual_minors\", \"hate_speech\", \"impersonation\"]'),
                    ('message', '[\"sexual_minors\", \"harassment\", \"spam\", \"phishing\"]')
                ");
                echo '<p class="text-gh-success mt-1">✓ Default settings inserted!</p>';
            } else {
                echo '<p class="text-red-500 mt-1">✗ Failed: ' . $db->error . '</p>';
            }
        }
        echo '</div>';
        
        // Final Summary
        echo '<div class="bg-gradient-to-r from-gh-accent/20 to-purple-500/20 border border-gh-accent/30 rounded-lg p-6 mt-6">';
        echo '<h2 class="text-2xl font-bold mb-3">✓ Diagnostic Complete!</h2>';
        echo '<p class="text-gh-muted mb-4">Your moderation system should now be working correctly.</p>';
        echo '<div class="flex gap-3">';
        echo '<a href="moderation.php" class="px-6 py-3 bg-gh-accent text-white font-semibold rounded-lg hover:bg-red-600 transition">Go to Moderation Dashboard</a>';
        echo '<a href="flagged-content.php" class="px-6 py-3 bg-gh-panel border border-gh-border rounded-lg hover:bg-gh-border transition">View Public Page</a>';
        echo '<button onclick="location.reload()" class="px-6 py-3 bg-gh-panel border border-gh-border rounded-lg hover:bg-gh-border transition">Run Again</button>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        ?>
        
    </div>
</body>
</html>
