-- Advanced Features Schema

USE doublelist_clone;

-- User Profiles Extended
CREATE TABLE user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    bio TEXT,
    height INT COMMENT 'Height in cm',
    body_type ENUM('slim', 'athletic', 'average', 'curvy', 'muscular', 'heavyset', 'other'),
    ethnicity VARCHAR(100),
    relationship_status ENUM('single', 'married', 'divorced', 'widowed', 'separated', 'in_relationship', 'complicated'),
    looking_for JSON COMMENT 'Array of what user is looking for',
    interests JSON COMMENT 'Array of interests/hobbies',
    occupation VARCHAR(100),
    education ENUM('high_school', 'some_college', 'bachelors', 'masters', 'phd', 'other'),
    smoking ENUM('never', 'occasionally', 'regularly', 'trying_to_quit'),
    drinking ENUM('never', 'socially', 'regularly'),
    has_kids BOOLEAN DEFAULT FALSE,
    wants_kids ENUM('yes', 'no', 'maybe', 'have_and_want_more'),
    languages JSON COMMENT 'Languages spoken',
    display_distance BOOLEAN DEFAULT TRUE,
    show_age BOOLEAN DEFAULT TRUE,
    show_online_status BOOLEAN DEFAULT TRUE,
    profile_completion INT DEFAULT 0,
    last_online TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_body_type (body_type),
    INDEX idx_relationship (relationship_status)
);

-- User Photos
CREATE TABLE user_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    is_verified BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_primary (is_primary)
);

-- Profile Questions & Answers
CREATE TABLE profile_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    question_type ENUM('text', 'multiple_choice', 'rating') DEFAULT 'text',
    options JSON COMMENT 'For multiple choice questions',
    category VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0
);

CREATE TABLE user_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    question_id INT NOT NULL,
    answer TEXT NOT NULL,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES profile_questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_answer (user_id, question_id),
    INDEX idx_user (user_id)
);

-- User Locations (for precise location matching)
CREATE TABLE user_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    city_id INT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    postal_code VARCHAR(20),
    show_exact_location BOOLEAN DEFAULT FALSE,
    max_distance INT DEFAULT 50 COMMENT 'Maximum distance in miles for matches',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL,
    INDEX idx_coordinates (latitude, longitude)
);

-- User Preferences
CREATE TABLE user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    min_age INT DEFAULT 18,
    max_age INT DEFAULT 99,
    preferred_gender JSON COMMENT 'Array of preferred genders',
    preferred_body_types JSON,
    preferred_ethnicity JSON,
    max_distance INT DEFAULT 50,
    only_with_photos BOOLEAN DEFAULT FALSE,
    only_verified BOOLEAN DEFAULT FALSE,
    preferred_relationship_status JSON,
    deal_breakers JSON COMMENT 'Array of deal breakers',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User Views/Visits
CREATE TABLE profile_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    viewer_id INT NOT NULL,
    viewed_id INT NOT NULL,
    view_count INT DEFAULT 1,
    last_viewed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (viewed_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_view (viewer_id, viewed_id),
    INDEX idx_viewed (viewed_id),
    INDEX idx_viewer (viewer_id)
);

-- Favorites/Likes
CREATE TABLE user_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    favorited_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (favorited_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, favorited_user_id),
    INDEX idx_user (user_id),
    INDEX idx_favorited (favorited_user_id)
);

-- Blocks
CREATE TABLE user_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_block (blocker_id, blocked_id),
    INDEX idx_blocker (blocker_id),
    INDEX idx_blocked (blocked_id)
);

-- Match Score Cache (for performance)
CREATE TABLE match_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    matched_user_id INT NOT NULL,
    score INT DEFAULT 0,
    distance DECIMAL(10, 2) COMMENT 'Distance in miles',
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (matched_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_match (user_id, matched_user_id),
    INDEX idx_user_score (user_id, score DESC),
    INDEX idx_calculated (calculated_at)
);

-- Activity Log
CREATE TABLE user_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type ENUM('login', 'profile_view', 'message_sent', 'listing_posted', 'search', 'other'),
    activity_data JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (activity_type),
    INDEX idx_created (created_at)
);

-- Insert sample profile questions
INSERT INTO profile_questions (question, question_type, category, display_order) VALUES
('What are you looking for in a relationship?', 'text', 'relationship', 1),
('What do you do for fun?', 'text', 'lifestyle', 2),
('What\'s your ideal first date?', 'text', 'dating', 3),
('How would your friends describe you?', 'text', 'personality', 4),
('What\'s your favorite way to spend a weekend?', 'text', 'lifestyle', 5),
('What are your passions?', 'text', 'interests', 6),
('What\'s most important to you in a partner?', 'text', 'relationship', 7),
('Where do you see yourself in 5 years?', 'text', 'goals', 8),
('What\'s your love language?', 'multiple_choice', 'relationship', 9),
('Are you an introvert or extrovert?', 'multiple_choice', 'personality', 10);

-- Update users table
ALTER TABLE users ADD COLUMN latitude DECIMAL(10, 8) NULL;
ALTER TABLE users ADD COLUMN longitude DECIMAL(11, 8) NULL;
ALTER TABLE users ADD COLUMN last_activity TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN is_online BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN is_verified BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD INDEX idx_location (latitude, longitude);
ALTER TABLE users ADD INDEX idx_online (is_online);