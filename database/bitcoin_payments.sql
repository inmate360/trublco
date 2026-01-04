-- Bitcoin Payment System
-- Date: 2025-01-19

USE doublelist_clone;

-- Bitcoin Wallets Table
CREATE TABLE IF NOT EXISTS bitcoin_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    wallet_address VARCHAR(100) UNIQUE,
    wallet_label VARCHAR(255),
    balance DECIMAL(16, 8) DEFAULT 0.00000000,
    total_received DECIMAL(16, 8) DEFAULT 0.00000000,
    total_sent DECIMAL(16, 8) DEFAULT 0.00000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_wallet (wallet_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bitcoin Transactions Table
CREATE TABLE IF NOT EXISTS bitcoin_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_hash VARCHAR(100) UNIQUE,
    from_user_id INT NULL,
    to_user_id INT NULL,
    from_address VARCHAR(100),
    to_address VARCHAR(100),
    amount DECIMAL(16, 8) NOT NULL,
    usd_amount DECIMAL(10, 2),
    fee DECIMAL(16, 8) DEFAULT 0.00000000,
    status ENUM('pending', 'confirmed', 'failed', 'cancelled') DEFAULT 'pending',
    confirmations INT DEFAULT 0,
    transaction_type ENUM('deposit', 'withdrawal', 'transfer', 'payment', 'refund') NOT NULL,
    description TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_from_user (from_user_id),
    INDEX idx_to_user (to_user_id),
    INDEX idx_status (status),
    INDEX idx_type (transaction_type),
    INDEX idx_hash (transaction_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Premium Subscriptions with Bitcoin
CREATE TABLE IF NOT EXISTS premium_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_type ENUM('plus', 'premium', 'vip') NOT NULL,
    payment_method ENUM('bitcoin', 'stripe', 'paypal', 'coinbase') NOT NULL,
    amount_btc DECIMAL(16, 8),
    amount_usd DECIMAL(10, 2) NOT NULL,
    bitcoin_address VARCHAR(100),
    transaction_id INT NULL,
    status ENUM('pending', 'active', 'expired', 'cancelled') DEFAULT 'pending',
    start_date TIMESTAMP NULL,
    end_date TIMESTAMP NULL,
    auto_renew BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES bitcoin_transactions(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_end_date (end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment Requests (User to User)
CREATE TABLE IF NOT EXISTS payment_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    amount_btc DECIMAL(16, 8) NOT NULL,
    amount_usd DECIMAL(10, 2),
    bitcoin_address VARCHAR(100),
    description TEXT,
    status ENUM('pending', 'paid', 'declined', 'expired') DEFAULT 'pending',
    transaction_id INT NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES bitcoin_transactions(id) ON DELETE SET NULL,
    INDEX idx_from_user (from_user_id),
    INDEX idx_to_user (to_user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bitcoin Price Cache
CREATE TABLE IF NOT EXISTS bitcoin_price_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    price_usd DECIMAL(12, 2) NOT NULL,
    source VARCHAR(50) DEFAULT 'coinbase',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Coinbase Commerce Charges
CREATE TABLE IF NOT EXISTS coinbase_charges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    charge_id VARCHAR(100) UNIQUE NOT NULL,
    hosted_url TEXT,
    amount_btc DECIMAL(16, 8),
    amount_usd DECIMAL(10, 2) NOT NULL,
    description TEXT,
    status ENUM('pending', 'confirmed', 'failed', 'expired') DEFAULT 'pending',
    subscription_id INT NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    confirmed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES premium_subscriptions(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_charge (charge_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;