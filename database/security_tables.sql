-- Security System Database Schema
-- Date: 2025-01-17

USE doublelist_clone;

-- Add security columns to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE AFTER email,
ADD COLUMN IF NOT EXISTS email_verified_at TIMESTAMP NULL AFTER email_verified,
ADD COLUMN IF NOT EXISTS registration_ip VARCHAR(45) NULL AFTER email_verified_at,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL AFTER created_at,
ADD COLUMN IF NOT EXISTS last_ip VARCHAR(45) NULL AFTER last_login,
ADD COLUMN IF NOT EXISTS is_suspended BOOLEAN DEFAULT FALSE AFTER is_admin,
ADD COLUMN IF NOT EXISTS is_banned BOOLEAN DEFAULT FALSE AFTER is_suspended;

-- Email verifications table
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at)
);

-- Rate limiting table
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    action VARCHAR(50) NOT NULL,
    attempts INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_identifier_action (identifier, action),
    INDEX idx_identifier_action (identifier, action),
    INDEX idx_blocked (blocked_until)
);

-- Blocked IPs table
CREATE TABLE IF NOT EXISTS blocked_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    reason TEXT,
    blocked_by INT NULL,
    blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    UNIQUE KEY unique_ip (ip_address),
    INDEX idx_ip (ip_address),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (blocked_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Security logs table
CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_ip (ip_address),
    INDEX idx_action (action),
    INDEX idx_severity (severity),
    INDEX idx_created (created_at DESC)
);

-- Add indexes for performance
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_email_verified (email_verified);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_last_login (last_login);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_suspended_banned (is_suspended, is_banned);