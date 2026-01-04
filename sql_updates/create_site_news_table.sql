-- Create the new site_news table for the entirely new announcements system
CREATE TABLE IF NOT EXISTS site_news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    is_scrolling BOOLEAN DEFAULT 0,
    scroll_speed INT DEFAULT 50,
    bg_color VARCHAR(20) DEFAULT '#2f81f7',
    text_color VARCHAR(20) DEFAULT '#ffffff',
    is_active BOOLEAN DEFAULT 1,
    priority INT DEFAULT 0,
    start_date DATETIME NULL,
    end_date DATETIME NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_active_news (is_active, is_scrolling),
    INDEX idx_news_priority (priority)
);
