-- Add scrolling announcement fields to site_announcements table
-- This allows announcements to be displayed as a scrolling marquee above the header

ALTER TABLE site_announcements 
ADD COLUMN IF NOT EXISTS is_scrolling BOOLEAN DEFAULT 0 COMMENT 'Display as scrolling marquee above header',
ADD COLUMN IF NOT EXISTS scroll_speed INT DEFAULT 50 COMMENT 'Scroll speed in pixels per second (10-100)',
ADD COLUMN IF NOT EXISTS background_color VARCHAR(20) DEFAULT '#4267f5' COMMENT 'Background color for scrolling banner',
ADD COLUMN IF NOT EXISTS text_color VARCHAR(20) DEFAULT '#ffffff' COMMENT 'Text color for scrolling banner',
ADD INDEX idx_scrolling (is_scrolling, is_active);

-- Sample scrolling announcement
-- INSERT INTO site_announcements (title, message, type, is_scrolling, scroll_speed, background_color, text_color, show_on_all_pages, is_active, created_by, priority)
-- VALUES ('Important Update', 'Welcome to our site! Check out our latest features and updates.', 'info', 1, 50, '#4267f5', '#ffffff', 1, 1, 1, 10);
