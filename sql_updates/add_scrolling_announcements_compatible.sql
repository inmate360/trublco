-- Compatible SQL migration for older MySQL versions
-- This version avoids 'IF NOT EXISTS' on 'ADD COLUMN' which is only in MySQL 8.0.12+

-- Add scrolling announcement fields to site_announcements table
ALTER TABLE site_announcements 
ADD COLUMN is_scrolling BOOLEAN DEFAULT 0,
ADD COLUMN scroll_speed INT DEFAULT 50,
ADD COLUMN background_color VARCHAR(20) DEFAULT '#4267f5',
ADD COLUMN text_color VARCHAR(20) DEFAULT '#ffffff';

-- Add index separately
CREATE INDEX idx_scrolling ON site_announcements (is_scrolling, is_active);

-- Note: If you get an error that columns already exist, you can ignore it 
-- or run the columns one by one.
