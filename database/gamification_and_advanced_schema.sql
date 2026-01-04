-- Gamification and Advanced Features Schema

USE doublelist_clone;

-- Badges System
CREATE TABLE badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(10),
    category ENUM('profile', 'activity', 'social', 'special', 'milestone') DEFAULT 'activity',
    points INT DEFAULT 10,
    requirement_type ENUM('profile_completion', 'message_count', 'login_streak', 'photo_upload', 'listing_post', 'favorites_received', 'profile_views', 'verified', 'custom') NOT NULL,
    requirement_value INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    rarity ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Badges
CREATE TABLE user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_displayed BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_badge (user_id, badge_id),
    INDEX idx_user (user_id)
);

-- User Points/Gamification
CREATE TABLE user_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    total_points INT DEFAULT 0,
    level INT DEFAULT 1,
    experience INT DEFAULT 0,
    login_streak INT DEFAULT 0,
    last_login_date DATE NULL,
    profile_views_given INT DEFAULT 0,
    messages_sent INT DEFAULT 0,
    listings_posted INT DEFAULT 0,
    favorites_received INT DEFAULT 0,
    quiz_score INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Point History
CREATE TABLE point_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
);

-- Personality Quizzes
CREATE TABLE personality_quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    points_reward INT DEFAULT 50,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Quiz Questions
CREATE TABLE quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'scale', 'text') DEFAULT 'multiple_choice',
    options JSON COMMENT 'Array of options for multiple choice',
    display_order INT DEFAULT 0,
    FOREIGN KEY (quiz_id) REFERENCES personality_quizzes(id) ON DELETE CASCADE
);

-- User Quiz Results
CREATE TABLE user_quiz_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quiz_id INT NOT NULL,
    answers JSON,
    result_summary TEXT,
    score INT DEFAULT 0,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES personality_quizzes(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
);

-- AI Matching Scores (Enhanced)
ALTER TABLE match_scores ADD COLUMN compatibility_score INT DEFAULT 0;
ALTER TABLE match_scores ADD COLUMN personality_match INT DEFAULT 0;
ALTER TABLE match_scores ADD COLUMN interest_overlap INT DEFAULT 0;
ALTER TABLE match_scores ADD COLUMN activity_compatibility INT DEFAULT 0;
ALTER TABLE match_scores ADD COLUMN communication_style INT DEFAULT 0;

-- Push Notifications
CREATE TABLE push_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type ENUM('message', 'match', 'view', 'favorite', 'badge', 'daily_match', 'nearby', 'system') NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    action_url VARCHAR(500),
    related_user_id INT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    INDEX idx_type (notification_type)
);

-- Notification Preferences
CREATE TABLE notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    email_notifications BOOLEAN DEFAULT TRUE,
    push_notifications BOOLEAN DEFAULT TRUE,
    message_alerts BOOLEAN DEFAULT TRUE,
    match_alerts BOOLEAN DEFAULT TRUE,
    view_alerts BOOLEAN DEFAULT FALSE,
    favorite_alerts BOOLEAN DEFAULT TRUE,
    badge_alerts BOOLEAN DEFAULT TRUE,
    daily_match_alerts BOOLEAN DEFAULT TRUE,
    nearby_alerts BOOLEAN DEFAULT FALSE,
    marketing_emails BOOLEAN DEFAULT FALSE,
    quiet_hours_start TIME NULL,
    quiet_hours_end TIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Social Media Connections
CREATE TABLE social_connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider ENUM('facebook', 'google', 'twitter', 'instagram', 'linkedin') NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    access_token TEXT,
    refresh_token TEXT,
    profile_url VARCHAR(500),
    profile_data JSON,
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_sync TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_provider (user_id, provider),
    INDEX idx_provider (provider, provider_user_id)
);

-- Incognito Sessions
CREATE TABLE incognito_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_active (is_active, expires_at)
);

-- User Settings Extended
CREATE TABLE user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    incognito_mode BOOLEAN DEFAULT FALSE,
    allow_analytics BOOLEAN DEFAULT TRUE,
    show_in_search BOOLEAN DEFAULT TRUE,
    allow_message_requests BOOLEAN DEFAULT TRUE,
    read_receipts BOOLEAN DEFAULT TRUE,
    typing_indicators BOOLEAN DEFAULT TRUE,
    profile_visibility ENUM('public', 'members_only', 'favorites_only') DEFAULT 'public',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Daily Matches
CREATE TABLE daily_matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    matched_user_id INT NOT NULL,
    match_score INT NOT NULL,
    match_date DATE NOT NULL,
    is_viewed BOOLEAN DEFAULT FALSE,
    is_liked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (matched_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_daily_match (user_id, matched_user_id, match_date),
    INDEX idx_user_date (user_id, match_date)
);

-- Insert Default Badges
INSERT INTO badges (name, slug, description, icon, category, points, requirement_type, requirement_value, rarity) VALUES
('New Member', 'new-member', 'Welcome to the community!', '🌟', 'milestone', 10, 'custom', 1, 'common'),
('Profile Master', 'profile-master', 'Complete your profile 100%', '✅', 'profile', 50, 'profile_completion', 100, 'rare'),
('Photo Pro', 'photo-pro', 'Upload 5 photos', '📷', 'profile', 30, 'photo_upload', 5, 'common'),
('Conversation Starter', 'conversation-starter', 'Send 50 messages', '💬', 'social', 40, 'message_count', 50, 'common'),
('Social Butterfly', 'social-butterfly', 'Send 200 messages', '🦋', 'social', 100, 'message_count', 200, 'rare'),
('Popular', 'popular', 'Receive 50 profile views', '👁️', 'social', 60, 'profile_views', 50, 'rare'),
('Heartthrob', 'heartthrob', 'Receive 20 favorites', '❤️', 'social', 80, 'favorites_received', 20, 'epic'),
('Week Warrior', 'week-warrior', '7 day login streak', '🔥', 'activity', 50, 'login_streak', 7, 'rare'),
('Month Master', 'month-master', '30 day login streak', '💪', 'activity', 150, 'login_streak', 30, 'epic'),
('Verified User', 'verified', 'Verified account', '✓', 'special', 100, 'verified', 1, 'epic'),
('Listing Legend', 'listing-legend', 'Post 10 listings', '📝', 'activity', 70, 'listing_post', 10, 'rare'),
('Premium Member', 'premium', 'Premium subscription', '👑', 'special', 200, 'custom', 1, 'legendary');

-- Insert Default Quiz
INSERT INTO personality_quizzes (title, description, category, points_reward) VALUES
('Love Language Quiz', 'Discover your love language and improve your connections', 'relationship', 50),
('Dating Personality', 'Find out your dating style', 'personality', 50);

-- Insert Quiz Questions
INSERT INTO quiz_questions (quiz_id, question, question_type, options, display_order) VALUES
(1, 'What makes you feel most loved?', 'multiple_choice', '["Words of affirmation", "Quality time", "Physical touch", "Acts of service", "Receiving gifts"]', 1),
(1, 'How do you prefer to show love?', 'multiple_choice', '["Verbal compliments", "Spending time together", "Physical affection", "Helping with tasks", "Giving thoughtful gifts"]', 2),
(2, 'What''s your ideal first date?', 'multiple_choice', '["Coffee and conversation", "Adventure activity", "Nice dinner", "Casual drinks", "Something creative"]', 1),
(2, 'How do you approach relationships?', 'multiple_choice', '["Take it slow", "Jump right in", "Let it happen naturally", "Carefully planned", "Go with the flow"]', 2);