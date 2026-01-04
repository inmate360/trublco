-- Message Limits System for Free Users

USE doublelist_clone;

-- Add column to track messages sent today
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS messages_sent_today INT DEFAULT 0 AFTER is_online,
ADD COLUMN IF NOT EXISTS last_message_reset DATE NULL AFTER messages_sent_today;

-- Message limits in site_settings
INSERT INTO site_settings (setting_key, setting_value, description) VALUES
('free_message_limit', '5', 'Number of messages free users can send per day')
ON DUPLICATE KEY UPDATE setting_value = setting_value;