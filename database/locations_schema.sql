-- Location tables for state/city selection

USE doublelist_clone;

-- States Table
CREATE TABLE states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    abbreviation VARCHAR(2) NOT NULL UNIQUE,
    country VARCHAR(100) DEFAULT 'United States',
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    INDEX idx_abbreviation (abbreviation),
    INDEX idx_active (is_active)
);

-- Cities Table
CREATE TABLE cities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    state_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    post_count INT DEFAULT 0,
    FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE CASCADE,
    UNIQUE KEY unique_city_state (state_id, slug),
    INDEX idx_state (state_id),
    INDEX idx_slug (slug),
    INDEX idx_active (is_active)
);

-- Update listings table to include city_id
ALTER TABLE listings ADD COLUMN city_id INT NULL AFTER location;
ALTER TABLE listings ADD FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL;
ALTER TABLE listings ADD INDEX idx_city (city_id);

-- Insert US States
INSERT INTO states (name, abbreviation, display_order) VALUES
('Alabama', 'AL', 1), ('Alaska', 'AK', 2), ('Arizona', 'AZ', 3), ('Arkansas', 'AR', 4),
('California', 'CA', 5), ('Colorado', 'CO', 6), ('Connecticut', 'CT', 7), ('Delaware', 'DE', 8),
('Florida', 'FL', 9), ('Georgia', 'GA', 10), ('Hawaii', 'HI', 11), ('Idaho', 'ID', 12),
('Illinois', 'IL', 13), ('Indiana', 'IN', 14), ('Iowa', 'IA', 15), ('Kansas', 'KS', 16),
('Kentucky', 'KY', 17), ('Louisiana', 'LA', 18), ('Maine', 'ME', 19), ('Maryland', 'MD', 20),
('Massachusetts', 'MA', 21), ('Michigan', 'MI', 22), ('Minnesota', 'MN', 23), ('Mississippi', 'MS', 24),
('Missouri', 'MO', 25), ('Montana', 'MT', 26), ('Nebraska', 'NE', 27), ('Nevada', 'NV', 28),
('New Hampshire', 'NH', 29), ('New Jersey', 'NJ', 30), ('New Mexico', 'NM', 31), ('New York', 'NY', 32),
('North Carolina', 'NC', 33), ('North Dakota', 'ND', 34), ('Ohio', 'OH', 35), ('Oklahoma', 'OK', 36),
('Oregon', 'OR', 37), ('Pennsylvania', 'PA', 38), ('Rhode Island', 'RI', 39), ('South Carolina', 'SC', 40),
('South Dakota', 'SD', 41), ('Tennessee', 'TN', 42), ('Texas', 'TX', 43), ('Utah', 'UT', 44),
('Vermont', 'VT', 45), ('Virginia', 'VA', 46), ('Washington', 'WA', 47), ('West Virginia', 'WV', 48),
('Wisconsin', 'WI', 49), ('Wyoming', 'WY', 50);

-- Insert some major cities for popular states
INSERT INTO cities (state_id, name, slug, display_order) VALUES
-- California
(5, 'Los Angeles', 'los-angeles', 1),
(5, 'San Francisco', 'san-francisco', 2),
(5, 'San Diego', 'san-diego', 3),
(5, 'Sacramento', 'sacramento', 4),
(5, 'San Jose', 'san-jose', 5),

-- Texas
(43, 'Houston', 'houston', 1),
(43, 'Dallas', 'dallas', 2),
(43, 'Austin', 'austin', 3),
(43, 'San Antonio', 'san-antonio', 4),

-- New York
(32, 'New York City', 'new-york-city', 1),
(32, 'Buffalo', 'buffalo', 2),
(32, 'Rochester', 'rochester', 3),

-- Florida
(9, 'Miami', 'miami', 1),
(9, 'Orlando', 'orlando', 2),
(9, 'Tampa', 'tampa', 3),
(9, 'Jacksonville', 'jacksonville', 4),

-- Georgia
(10, 'Atlanta', 'atlanta', 1),
(10, 'Savannah', 'savannah', 2),
(10, 'Augusta', 'augusta', 3),

-- Illinois
(13, 'Chicago', 'chicago', 1),
(13, 'Springfield', 'springfield', 2),

-- Pennsylvania
(38, 'Philadelphia', 'philadelphia', 1),
(38, 'Pittsburgh', 'pittsburgh', 2),

-- Arizona
(3, 'Phoenix', 'phoenix', 1),
(3, 'Tucson', 'tucson', 2),

-- Washington
(47, 'Seattle', 'seattle', 1),
(47, 'Spokane', 'spokane', 2);

-- Update categories to match DoubleList style
TRUNCATE TABLE categories;

INSERT INTO categories (name, slug, description, icon, display_order) VALUES
-- Connect Now Section
('Guys for Guys', 'guys-for-guys', 'Men seeking men', '👨‍❤️‍👨', 1),
('Women for Guys', 'women-for-guys', 'Women seeking men', '👩‍❤️‍👨', 2),
('Guys for Women', 'guys-for-women', 'Men seeking women', '👨‍❤️‍👩', 3),
('Women for Women', 'women-for-women', 'Women seeking women', '👩‍❤️‍👩', 4),
('Couples for Couples', 'couples-for-couples', 'Couples seeking couples', '💑', 5),
('Couples for Her', 'couples-for-her', 'Couples seeking women', '💑', 6),
('Couples for Him', 'couples-for-him', 'Couples seeking men', '💑', 7),
('Males for Couples', 'males-for-couples', 'Men seeking couples', '👨', 8),
('Females for Couples', 'females-for-couples', 'Women seeking couples', '👩', 9),
('Gay for Straight', 'gay-for-straight', 'Gay seeking straight', '🌈', 10),
('Straight for Gay', 'straight-for-gay', 'Straight seeking gay', '💫', 11),

-- Let's Date Section
('Platonic / Friendships', 'platonic', 'Friends and platonic relationships', '🤝', 12),
('Dating Misc', 'dating-misc', 'Other dating connections', '💝', 13);