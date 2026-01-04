-- Advertising System Database Schema
-- Date: 2025-01-18

USE doublelist_clone;

-- Ad placements/zones
CREATE TABLE IF NOT EXISTS ad_placements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    placement_name VARCHAR(100) NOT NULL UNIQUE,
    placement_type ENUM('adsense', 'native', 'banner', 'sponsored_profile', 'premium_listing') NOT NULL,
    location VARCHAR(100) NOT NULL COMMENT 'Page location (homepage, listings, profile, etc)',
    position VARCHAR(50) NOT NULL COMMENT 'top, sidebar, between_listings, footer, etc',
    dimensions VARCHAR(50) NULL COMMENT 'Width x Height for banners',
    max_ads_per_page INT DEFAULT 3,
    rotation_enabled BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_location (location),
    INDEX idx_active (is_active)
);

-- AdSense configuration
CREATE TABLE IF NOT EXISTS adsense_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_name VARCHAR(100) NOT NULL,
    placement_id INT NOT NULL,
    client_id VARCHAR(100) NOT NULL COMMENT 'ca-pub-XXXXXXXXXXXXXXXX',
    slot_id VARCHAR(100) NOT NULL,
    ad_format ENUM('auto', 'rectangle', 'vertical', 'horizontal', 'link') DEFAULT 'auto',
    responsive BOOLEAN DEFAULT TRUE,
    test_mode BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    revenue DECIMAL(10, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (placement_id) REFERENCES ad_placements(id) ON DELETE CASCADE,
    INDEX idx_placement (placement_id),
    INDEX idx_active (is_active)
);

-- Custom banner ads
CREATE TABLE IF NOT EXISTS banner_ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    advertiser_name VARCHAR(100) NOT NULL,
    placement_id INT NOT NULL,
    banner_image VARCHAR(500) NOT NULL,
    destination_url VARCHAR(500) NOT NULL,
    alt_text VARCHAR(200),
    dimensions VARCHAR(50) COMMENT 'Width x Height',
    priority INT DEFAULT 0 COMMENT 'Higher priority = shown first',
    daily_budget DECIMAL(10, 2) NULL,
    cost_per_click DECIMAL(5, 2) NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    target_audience JSON NULL COMMENT 'Targeting criteria',
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    conversions INT DEFAULT 0,
    total_spent DECIMAL(10, 2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (placement_id) REFERENCES ad_placements(id) ON DELETE CASCADE,
    INDEX idx_placement (placement_id),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_active (is_active),
    INDEX idx_priority (priority DESC)
);

-- Sponsored/Premium listings
CREATE TABLE IF NOT EXISTS premium_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    user_id INT NOT NULL,
    placement_type ENUM('featured', 'sponsored', 'premium', 'top_ad') NOT NULL,
    duration_days INT NOT NULL,
    cost DECIMAL(10, 2) NOT NULL,
    impressions_limit INT NULL COMMENT 'Max impressions, NULL = unlimited',
    current_impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    auto_renew BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_listing (listing_id),
    INDEX idx_user (user_id),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_active (is_active),
    INDEX idx_type (placement_type)
);

-- Sponsored profiles
CREATE TABLE IF NOT EXISTS sponsored_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    profile_badge VARCHAR(50) DEFAULT 'verified' COMMENT 'Badge type to display',
    boost_level INT DEFAULT 1 COMMENT '1-3, higher = more visibility',
    duration_days INT NOT NULL,
    cost DECIMAL(10, 2) NOT NULL,
    profile_views INT DEFAULT 0,
    listing_views_boost INT DEFAULT 0,
    message_priority BOOLEAN DEFAULT FALSE,
    appears_in_featured BOOLEAN DEFAULT TRUE,
    start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    auto_renew BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_sponsorship (user_id, is_active),
    INDEX idx_user (user_id),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_active (is_active)
);

-- Native ads (looks like regular listings)
CREATE TABLE IF NOT EXISTS native_ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    advertiser_name VARCHAR(100) NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    image_url VARCHAR(500) NULL,
    destination_url VARCHAR(500) NOT NULL,
    category_id INT NULL COMMENT 'Show in specific category',
    city_id INT NULL COMMENT 'Show in specific city',
    cpc DECIMAL(5, 2) NOT NULL COMMENT 'Cost per click',
    daily_budget DECIMAL(10, 2) NOT NULL,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    total_spent DECIMAL(10, 2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_city (city_id),
    INDEX idx_active (is_active)
);

-- Ad impressions tracking
CREATE TABLE IF NOT EXISTS ad_impressions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ad_type ENUM('adsense', 'banner', 'native', 'premium_listing', 'sponsored_profile') NOT NULL,
    ad_id INT NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    page_url VARCHAR(500),
    referrer VARCHAR(500),
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ad (ad_type, ad_id),
    INDEX idx_user (user_id),
    INDEX idx_viewed (viewed_at),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB;

-- Ad clicks tracking
CREATE TABLE IF NOT EXISTS ad_clicks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ad_type ENUM('adsense', 'banner', 'native', 'premium_listing', 'sponsored_profile') NOT NULL,
    ad_id INT NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    page_url VARCHAR(500),
    referrer VARCHAR(500),
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ad (ad_type, ad_id),
    INDEX idx_user (user_id),
    INDEX idx_clicked (clicked_at),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB;

-- Ad revenue tracking
CREATE TABLE IF NOT EXISTS ad_revenue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    revenue_date DATE NOT NULL,
    ad_type ENUM('adsense', 'banner', 'native', 'premium_listing', 'sponsored_profile') NOT NULL,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    conversions INT DEFAULT 0,
    revenue DECIMAL(10, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_daily_revenue (revenue_date, ad_type),
    INDEX idx_date (revenue_date DESC)
);

-- Insert default ad placements
INSERT INTO ad_placements (placement_name, placement_type, location, position, dimensions, max_ads_per_page) VALUES
('Homepage Top Banner', 'banner', 'homepage', 'top', '728x90', 1),
('Homepage Sidebar', 'adsense', 'homepage', 'sidebar', '300x250', 2),
('Listings Top', 'adsense', 'listings', 'top', '728x90', 1),
('Listings Between Results', 'native', 'listings', 'between_listings', NULL, 3),
('Listings Sidebar', 'adsense', 'listings', 'sidebar', '300x600', 2),
('Listing Detail Sidebar', 'adsense', 'listing_detail', 'sidebar', '300x250', 2),
('Profile Page Banner', 'banner', 'profile', 'top', '728x90', 1),
('Mobile Bottom Banner', 'adsense', 'mobile', 'bottom', '320x50', 1),
('Featured Listings Section', 'premium_listing', 'homepage', 'featured', NULL, 6)
ON DUPLICATE KEY UPDATE placement_name = placement_name;