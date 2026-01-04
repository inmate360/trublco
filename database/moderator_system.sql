-- Moderator System

USE doublelist_clone;

-- Add moderator column to users if not exists
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS is_moderator BOOLEAN DEFAULT FALSE AFTER is_admin;

-- Create moderator_logs table to track moderator actions
CREATE TABLE IF NOT EXISTS moderator_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moderator_id INT NOT NULL,
    action_type ENUM('user_suspend', 'user_unsuspend', 'user_warn', 'listing_approve', 'listing_reject', 'listing_delete', 'report_resolve', 'report_dismiss') NOT NULL,
    target_type ENUM('user', 'listing', 'report') NOT NULL,
    target_id INT NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_moderator (moderator_id),
    INDEX idx_action (action_type),
    INDEX idx_created (created_at DESC)
);

-- Add suspended column to users
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS is_suspended BOOLEAN DEFAULT FALSE AFTER is_moderator,
ADD COLUMN IF NOT EXISTS suspended_until TIMESTAMP NULL AFTER is_suspended,
ADD COLUMN IF NOT EXISTS suspension_reason TEXT NULL AFTER suspended_until;