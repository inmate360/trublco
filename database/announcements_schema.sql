-- Announcements System

USE doublelist_clone;

CREATE TABLE IF NOT EXISTS site_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'danger') DEFAULT 'info',
    is_active BOOLEAN DEFAULT TRUE,
    show_on_homepage BOOLEAN DEFAULT TRUE,
    show_on_all_pages BOOLEAN DEFAULT FALSE,
    priority INT DEFAULT 0 COMMENT 'Higher priority shows first',
    start_date DATETIME NULL,
    end_date DATETIME NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_active (is_active, start_date, end_date),
    INDEX idx_priority (priority DESC)
);

-- Add is_admin column to users table if not exists
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_moderator BOOLEAN DEFAULT FALSE;

-- Insert sample announcement
INSERT INTO site_announcements (title, message, type, created_by, priority) VALUES
('Welcome to Turnpage!', 'Browse local hookup classifieds in your area. Post your ad for free!', 'success', 1, 10);