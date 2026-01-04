<?php
// install/setup-moderation-database.php

require_once '../config/database.php';

class ModerationDatabaseSetup {
    private $db;
    private $errors = [];
    private $success = [];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Check if table exists
     */
    private function tableExists($tableName) {
        $result = $this->db->query("SHOW TABLES LIKE '{$tableName}'");
        return $result && $result->num_rows > 0;
    }
    
    /**
     * Check if column exists in table
     */
    private function columnExists($tableName, $columnName) {
        $result = $this->db->query("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND COLUMN_NAME = '{$columnName}'
        ");
        
        if ($result) {
            $row = $result->fetch_assoc();
            return $row['count'] > 0;
        }
        return false;
    }
    
    /**
     * Create moderation_queue table
     */
    public function createModerationQueueTable() {
        if ($this->tableExists('moderation_queue')) {
            $this->success[] = "Table 'moderation_queue' already exists";
            return true;
        }
        
        $sql = "CREATE TABLE `moderation_queue` (
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
        
        if ($this->db->query($sql)) {
            $this->success[] = "✓ Created 'moderation_queue' table successfully";
            return true;
        } else {
            $this->errors[] = "✗ Error creating 'moderation_queue' table: " . $this->db->error;
            return false;
        }
    }
    
    /**
     * Create moderation_settings table
     */
    public function createModerationSettingsTable() {
        if ($this->tableExists('moderation_settings')) {
            $this->success[] = "Table 'moderation_settings' already exists";
            return true;
        }
        
        $sql = "CREATE TABLE `moderation_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `content_type` VARCHAR(50) NOT NULL UNIQUE,
            `auto_reject_threshold` DECIMAL(3,2) DEFAULT 0.80,
            `auto_flag_threshold` DECIMAL(3,2) DEFAULT 0.50,
            `blocked_categories` JSON,
            `enabled` BOOLEAN DEFAULT TRUE,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if ($this->db->query($sql)) {
            $this->success[] = "✓ Created 'moderation_settings' table successfully";
            
            // Insert default settings
            $this->insertDefaultSettings();
            return true;
        } else {
            $this->errors[] = "✗ Error creating 'moderation_settings' table: " . $this->db->error;
            return false;
        }
    }
    
    /**
     * Insert default moderation settings
     */
    private function insertDefaultSettings() {
        $settings = [
            ['listing', '["sexual_minors", "hate_speech", "violence", "illegal_activity", "spam"]'],
            ['story', '["sexual_minors", "hate_speech", "extreme_violence", "self_harm"]'],
            ['forum_post', '["sexual_minors", "hate_speech", "harassment", "doxxing"]'],
            ['profile', '["sexual_minors", "hate_speech", "impersonation"]'],
            ['message', '["sexual_minors", "harassment", "spam", "phishing"]']
        ];
        
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO moderation_settings (content_type, blocked_categories) 
            VALUES (?, ?)
        ");
        
        foreach ($settings as $setting) {
            $stmt->bind_param('ss', $setting[0], $setting[1]);
            $stmt->execute();
        }
        
        $this->success[] = "✓ Inserted default moderation settings";
    }
    
    /**
     * Add missing columns to existing table
     */
    public function addMissingColumns() {
        if (!$this->tableExists('moderation_queue')) {
            return false;
        }
        
        $columns = [
            ['content_type', "ENUM('listing', 'story', 'forum_post', 'message', 'profile') NOT NULL"],
            ['content_id', 'INT NOT NULL'],
            ['user_id', 'INT NOT NULL'],
            ['content_text', 'TEXT'],
            ['content_images', 'TEXT'],
            ['status', "ENUM('pending', 'approved', 'rejected', 'flagged') DEFAULT 'pending'"],
            ['moderation_score', 'JSON'],
            ['flagged_categories', 'JSON'],
            ['confidence_score', 'DECIMAL(3,2)'],
            ['auto_action', 'BOOLEAN DEFAULT FALSE'],
            ['reviewed_by', 'INT'],
            ['reviewed_at', 'DATETIME'],
            ['created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP']
        ];
        
        $added = 0;
        foreach ($columns as $column) {
            if (!$this->columnExists('moderation_queue', $column[0])) {
                $sql = "ALTER TABLE moderation_queue ADD COLUMN `{$column[0]}` {$column[1]}";
                if ($this->db->query($sql)) {
                    $this->success[] = "✓ Added column '{$column[0]}' to moderation_queue";
                    $added++;
                } else {
                    $this->errors[] = "✗ Error adding column '{$column[0]}': " . $this->db->error;
                }
            }
        }
        
        if ($added === 0) {
            $this->success[] = "All columns already exist in moderation_queue";
        }
        
        return true;
    }
    
    /**
     * Add indexes if missing
     */
    public function addIndexes() {
        $indexes = [
            ['idx_status', 'moderation_queue', 'status'],
            ['idx_content', 'moderation_queue', 'content_type, content_id'],
            ['idx_user', 'moderation_queue', 'user_id']
        ];
        
        foreach ($indexes as $index) {
            // Check if index exists
            $result = $this->db->query("
                SHOW INDEX FROM {$index[1]} WHERE Key_name = '{$index[0]}'
            ");
            
            if ($result && $result->num_rows === 0) {
                $sql = "ALTER TABLE {$index[1]} ADD INDEX {$index[0]} ({$index[2]})";
                if ($this->db->query($sql)) {
                    $this->success[] = "✓ Added index '{$index[0]}'";
                } else {
                    $this->errors[] = "✗ Error adding index '{$index[0]}': " . $this->db->error;
                }
            }
        }
    }
    
    /**
     * Run full setup
     */
    public function runSetup() {
        echo "<h2>Setting up Moderation Database...</h2>\n";
        
        $this->createModerationQueueTable();
        $this->createModerationSettingsTable();
        $this->addMissingColumns();
        $this->addIndexes();
        
        return [
            'success' => $this->success,
            'errors' => $this->errors
        ];
    }
    
    /**
     * Verify setup
     */
    public function verifySetup() {
        $issues = [];
        
        // Check tables exist
        if (!$this->tableExists('moderation_queue')) {
            $issues[] = "Table 'moderation_queue' does not exist";
        }
        if (!$this->tableExists('moderation_settings')) {
            $issues[] = "Table 'moderation_settings' does not exist";
        }
        
        // Check critical columns
        $criticalColumns = ['content_type', 'content_id', 'user_id', 'status'];
        foreach ($criticalColumns as $col) {
            if (!$this->columnExists('moderation_queue', $col)) {
                $issues[] = "Critical column '{$col}' missing from moderation_queue";
            }
        }
        
        return empty($issues) ? ['status' => 'OK', 'message' => 'Database setup is complete!'] : ['status' => 'ERROR', 'issues' => $issues];
    }
}

// Run if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'setup-moderation-database.php') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Moderation Database Setup - Basehit</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body { background: #0a0a0f; color: #f5f5f7; font-family: system-ui; }
            .success { color: #00d9a3; }
            .error { color: #e94560; }
        </style>
    </head>
    <body class="p-8">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-4xl font-bold mb-8">Basehit Moderation Database Setup</h1>
            
            <?php
            $setup = new ModerationDatabaseSetup($db);
            $results = $setup->runSetup();
            ?>
            
            <div class="bg-gray-900 rounded-lg p-6 mb-6">
                <h2 class="text-2xl font-bold mb-4">Setup Results</h2>
                
                <?php if (!empty($results['success'])): ?>
                    <div class="mb-4">
                        <h3 class="text-xl font-semibold mb-2 success">✓ Success:</h3>
                        <ul class="space-y-1">
                            <?php foreach ($results['success'] as $msg): ?>
                                <li class="success">• <?= htmlspecialchars($msg) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($results['errors'])): ?>
                    <div class="mb-4">
                        <h3 class="text-xl font-semibold mb-2 error">✗ Errors:</h3>
                        <ul class="space-y-1">
                            <?php foreach ($results['errors'] as $msg): ?>
                                <li class="error">• <?= htmlspecialchars($msg) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php
            $verification = $setup->verifySetup();
            ?>
            
            <div class="bg-gray-900 rounded-lg p-6">
                <h2 class="text-2xl font-bold mb-4">Verification</h2>
                <?php if ($verification['status'] === 'OK'): ?>
                    <p class="success text-xl">✓ <?= $verification['message'] ?></p>
                    <p class="mt-4">You can now use the moderation system.</p>
                    <a href="../admin-moderation.php" class="inline-block mt-4 px-6 py-3 bg-pink-600 rounded-lg hover:bg-pink-700">
                        Go to Moderation Dashboard
                    </a>
                <?php else: ?>
                    <p class="error text-xl">✗ Setup incomplete</p>
                    <ul class="mt-4 space-y-1">
                        <?php foreach ($verification['issues'] as $issue): ?>
                            <li class="error">• <?= htmlspecialchars($issue) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button onclick="location.reload()" class="mt-4 px-6 py-3 bg-blue-600 rounded-lg hover:bg-blue-700">
                        Retry Setup
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
