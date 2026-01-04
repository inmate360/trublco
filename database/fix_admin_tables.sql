-- Fix missing columns for admin pages

USE doublelist_clone;

-- Fix membership_plans table
ALTER TABLE membership_plans 
ADD COLUMN IF NOT EXISTS duration_days INT DEFAULT 30 AFTER price;

-- Update existing plans with duration
UPDATE membership_plans SET duration_days = 30 WHERE duration_days IS NULL OR duration_days = 0;

-- Ensure reports table has correct structure
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    reported_type ENUM('listing', 'user', 'message') NOT NULL,
    reported_id INT NOT NULL,
    reason VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
    action_taken TEXT,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_reporter (reporter_id),
    INDEX idx_reported (reported_type, reported_id)
);