-- Database Schema for Doublelist Clone

CREATE DATABASE IF NOT EXISTS doublelist_clone;
USE doublelist_clone;

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email)
);

-- Categories Table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    display_order INT DEFAULT 0
);

-- Listings Table
CREATE TABLE listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255),
    age INT,
    gender ENUM('male', 'female', 'couple', 'trans', 'other'),
    seeking ENUM('male', 'female', 'couple', 'trans', 'any'),
    status ENUM('active', 'expired', 'deleted') DEFAULT 'active',
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    views INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_location (location),
    INDEX idx_created (created_at)
);

-- Messages Table
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_recipient (recipient_id),
    INDEX idx_listing (listing_id)
);

-- Reports Table
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    reporter_id INT,
    reason TEXT NOT NULL,
    status ENUM('pending', 'reviewed', 'action_taken') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert Default Categories
INSERT INTO categories (name, slug, description, icon, display_order) VALUES
('Dating', 'dating', 'Personal dating ads', '❤️', 1),
('Casual Encounters', 'casual', 'Casual encounters and hookups', '🔥', 2),
('Men Seeking Women', 'men-seeking-women', 'Men looking for women', '👨', 3),
('Women Seeking Men', 'women-seeking-men', 'Women looking for men', '👩', 4),
('Men Seeking Men', 'men-seeking-men', 'Men looking for men', '👨‍❤️‍👨', 5),
('Women Seeking Women', 'women-seeking-women', 'Women looking for women', '👩‍❤️‍👩', 6),
('Couples', 'couples', 'Couples seeking others', '💑', 7),
('Platonic', 'platonic', 'Friendship and platonic relationships', '🤝', 8),
('Misc Romance', 'misc-romance', 'Other romantic connections', '💝', 9);