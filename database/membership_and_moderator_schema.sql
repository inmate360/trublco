-- Additional tables for Membership Plans and Moderator System

USE doublelist_clone;

-- Membership Plans Table
CREATE TABLE membership_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    billing_cycle ENUM('monthly', 'quarterly', 'yearly') NOT NULL,
    features JSON,
    max_active_listings INT DEFAULT 5,
    featured_listings_per_month INT DEFAULT 0,
    priority_support BOOLEAN DEFAULT FALSE,
    can_message BOOLEAN DEFAULT TRUE,
    badge_color VARCHAR(20),
    badge_text VARCHAR(50),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Subscriptions Table
CREATE TABLE user_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    stripe_customer_id VARCHAR(255),
    stripe_subscription_id VARCHAR(255),
    stripe_payment_intent_id VARCHAR(255),
    status ENUM('active', 'canceled', 'expired', 'past_due', 'trial') DEFAULT 'active',
    current_period_start DATETIME,
    current_period_end DATETIME,
    cancel_at_period_end BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES membership_plans(id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_stripe_customer (stripe_customer_id)
);

-- Payment History Table
CREATE TABLE payment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_id INT,
    stripe_payment_id VARCHAR(255),
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('succeeded', 'pending', 'failed', 'refunded') DEFAULT 'pending',
    description TEXT,
    payment_method VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES user_subscriptions(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
);

-- Moderators Table
CREATE TABLE moderators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    role ENUM('moderator', 'senior_moderator', 'admin') DEFAULT 'moderator',
    permissions JSON,
    can_delete_listings BOOLEAN DEFAULT TRUE,
    can_ban_users BOOLEAN DEFAULT FALSE,
    can_view_reports BOOLEAN DEFAULT TRUE,
    can_manage_categories BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Moderator Actions Log Table
CREATE TABLE moderator_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moderator_id INT NOT NULL,
    action_type ENUM('delete_listing', 'ban_user', 'warn_user', 'approve_listing', 'reject_report', 'approve_report', 'edit_listing', 'other') NOT NULL,
    target_type ENUM('listing', 'user', 'report', 'message', 'other') NOT NULL,
    target_id INT NOT NULL,
    reason TEXT,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (moderator_id) REFERENCES moderators(id) ON DELETE CASCADE,
    INDEX idx_moderator (moderator_id),
    INDEX idx_action (action_type),
    INDEX idx_created (created_at)
);

-- User Bans Table
CREATE TABLE user_bans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    banned_by INT NOT NULL,
    reason TEXT NOT NULL,
    ban_type ENUM('temporary', 'permanent') DEFAULT 'temporary',
    banned_until DATETIME NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (banned_by) REFERENCES moderators(id),
    INDEX idx_user (user_id),
    INDEX idx_active (is_active)
);

-- Update Reports Table with moderator assignment
ALTER TABLE reports 
ADD COLUMN assigned_to INT NULL,
ADD COLUMN resolved_at TIMESTAMP NULL,
ADD COLUMN resolution_notes TEXT NULL,
ADD FOREIGN KEY (assigned_to) REFERENCES moderators(id) ON DELETE SET NULL;

-- Update Users Table for membership
ALTER TABLE users 
ADD COLUMN current_plan_id INT NULL,
ADD COLUMN is_premium BOOLEAN DEFAULT FALSE,
ADD COLUMN role ENUM('user', 'moderator', 'admin') DEFAULT 'user',
ADD FOREIGN KEY (current_plan_id) REFERENCES membership_plans(id) ON DELETE SET NULL;

-- Featured Listings Table
CREATE TABLE featured_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    user_id INT NOT NULL,
    featured_until DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_listing (listing_id),
    INDEX idx_active (is_active, featured_until)
);

-- Insert Default Membership Plans
INSERT INTO membership_plans (name, slug, description, price, billing_cycle, features, max_active_listings, featured_listings_per_month, priority_support, badge_color, badge_text, display_order) VALUES
('Free', 'free', 'Basic access to post listings', 0.00, 'monthly', 
 '["Post up to 3 active listings", "Basic search", "Community support"]', 
 3, 0, FALSE, '#95a5a6', 'Free', 1),

('Basic', 'basic', 'Perfect for casual users', 9.99, 'monthly',
 '["Post up to 10 active listings", "Priority in search results", "Email support", "No ads"]',
 10, 1, FALSE, '#3498db', 'Basic', 2),

('Premium', 'premium', 'For serious users', 19.99, 'monthly',
 '["Unlimited active listings", "Featured listings (5/month)", "Priority support", "No ads", "Profile badge", "Advanced filters"]',
 999, 5, TRUE, '#e74c3c', 'Premium', 3),

('VIP', 'vip', 'Ultimate experience', 39.99, 'monthly',
 '["Unlimited active listings", "Featured listings (20/month)", "24/7 Priority support", "No ads", "VIP badge", "Advanced filters", "See who viewed your profile", "Boost listings"]',
 999, 20, TRUE, '#f39c12', 'VIP', 4);

-- Insert sample moderator (you'll need to create this user first)
-- This is just a placeholder - update with actual user_id
-- INSERT INTO moderators (user_id, role, can_ban_users, can_manage_categories) 
-- VALUES (1, 'admin', TRUE, TRUE);