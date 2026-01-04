-- Maintenance Mode System

USE doublelist_clone;

-- Site settings table
CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_key (setting_key)
);

-- Insert default settings
INSERT INTO site_settings (setting_key, setting_value, description) VALUES
('maintenance_mode', '0', 'Enable/disable maintenance mode (0=disabled, 1=enabled)'),
('maintenance_message', 'We are currently performing scheduled maintenance. Please check back soon!', 'Message to display during maintenance'),
('maintenance_title', 'Site Maintenance', 'Title for maintenance page'),
('coming_soon_mode', '0', 'Enable/disable coming soon mode (0=disabled, 1=enabled)'),
('coming_soon_message', 'Turnpage is coming soon! We are working hard to bring you the best local hookup classifieds experience.', 'Message for coming soon page'),
('coming_soon_launch_date', NULL, 'Expected launch date for coming soon page'),
('allow_admin_access', '1', 'Allow admin access during maintenance (0=no, 1=yes)')
ON DUPLICATE KEY UPDATE setting_key = setting_key;