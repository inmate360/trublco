-- Additional tables for Messaging, Image Uploads, and Featured Ads

USE doublelist_clone;

-- Conversations Table (for organizing messages)
CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user1_unread INT DEFAULT 0,
    user2_unread INT DEFAULT 0,
    is_archived BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_conversation (listing_id, user1_id, user2_id),
    INDEX idx_user1 (user1_id),
    INDEX idx_user2 (user2_id),
    INDEX idx_last_message (last_message_at)
);

-- Update Messages Table for conversation threading
DROP TABLE IF EXISTS messages;
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    is_deleted_by_sender BOOLEAN DEFAULT FALSE,
    is_deleted_by_receiver BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_conversation (conversation_id),
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_created (created_at)
);

-- Listing Images Table
CREATE TABLE listing_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    width INT,
    height INT,
    is_primary BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    INDEX idx_listing (listing_id),
    INDEX idx_primary (is_primary)
);

-- Featured Ads Requests Table
CREATE TABLE featured_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    user_id INT NOT NULL,
    duration_days INT NOT NULL DEFAULT 7,
    price DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'expired', 'cancelled') DEFAULT 'pending',
    payment_id INT NULL,
    stripe_payment_intent_id VARCHAR(255),
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES moderators(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_listing (listing_id),
    INDEX idx_user (user_id)
);

-- Update Featured Listings Table
DROP TABLE IF EXISTS featured_listings;
CREATE TABLE featured_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL UNIQUE,
    user_id INT NOT NULL,
    request_id INT NOT NULL,
    featured_from DATETIME NOT NULL,
    featured_until DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES featured_requests(id) ON DELETE CASCADE,
    INDEX idx_active (is_active, featured_until),
    INDEX idx_listing (listing_id)
);

-- Notifications Table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('message', 'featured_approved', 'featured_rejected', 'listing_flagged', 'subscription_expiring', 'other') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500),
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read)
);

-- Update Payment History for featured ads
ALTER TABLE payment_history 
ADD COLUMN payment_type ENUM('subscription', 'featured_ad', 'other') DEFAULT 'subscription',
ADD COLUMN related_id INT NULL COMMENT 'subscription_id or featured_request_id';

-- Featured Ad Pricing Table
CREATE TABLE featured_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    duration_days INT NOT NULL UNIQUE,
    price DECIMAL(10, 2) NOT NULL,
    discount_percent DECIMAL(5, 2) DEFAULT 0,
    description VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0
);

-- Insert default featured pricing
INSERT INTO featured_pricing (duration_days, price, discount_percent, description, display_order) VALUES
(1, 9.99, 0, '24 Hour Featured Ad', 1),
(3, 24.99, 10, '3 Day Featured Ad', 2),
(7, 49.99, 20, '7 Day Featured Ad', 3),
(14, 89.99, 25, '14 Day Featured Ad', 4),
(30, 149.99, 30, '30 Day Featured Ad', 5);