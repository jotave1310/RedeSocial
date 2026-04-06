-- ============================================================
-- CARVASILVA — Database Schema
-- MySQL / MariaDB
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ------------------------------------------------------------
-- ROOMS
-- ------------------------------------------------------------
CREATE TABLE rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO rooms (code, name) VALUES
    ('1A', 'Turma 1A'), ('1B', 'Turma 1B'), ('1C', 'Turma 1C'),
    ('2A', 'Turma 2A'), ('2B', 'Turma 2B'), ('2C', 'Turma 2C'),
    ('3A', 'Turma 3A'), ('3B', 'Turma 3B'), ('3C', 'Turma 3C');

-- ------------------------------------------------------------
-- USERS
-- ------------------------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    room_id INT UNSIGNED NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    bio VARCHAR(160) DEFAULT NULL,
    credits BIGINT UNSIGNED NOT NULL DEFAULT 1000,
    role ENUM('user', 'moderator', 'admin') NOT NULL DEFAULT 'user',
    is_banned TINYINT(1) NOT NULL DEFAULT 0,
    ban_reason VARCHAR(255) DEFAULT NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index for ranking queries
CREATE INDEX idx_users_credits ON users (credits DESC);

-- ------------------------------------------------------------
-- POSTS
-- ------------------------------------------------------------
CREATE TABLE posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    type ENUM('standard', 'image', 'credit_flex', 'milestone', 'anonymous', 'bet_reaction') NOT NULL DEFAULT 'standard',
    image_path VARCHAR(255) DEFAULT NULL,
    is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
    repost_of INT UNSIGNED DEFAULT NULL,
    repost_comment TEXT DEFAULT NULL,
    like_count INT UNSIGNED NOT NULL DEFAULT 0,
    comment_count INT UNSIGNED NOT NULL DEFAULT 0,
    repost_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (repost_of) REFERENCES posts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_posts_created ON posts (created_at DESC);
CREATE INDEX idx_posts_user ON posts (user_id);
CREATE INDEX idx_posts_type ON posts (type);
CREATE INDEX idx_posts_anonymous ON posts (is_anonymous);

-- ------------------------------------------------------------
-- COMMENTS
-- ------------------------------------------------------------
CREATE TABLE comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    content VARCHAR(500) NOT NULL,
    is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_comments_post ON comments (post_id);

-- ------------------------------------------------------------
-- LIKES
-- ------------------------------------------------------------
CREATE TABLE likes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_like (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- FOLLOWS
-- ------------------------------------------------------------
CREATE TABLE follows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    follower_id INT UNSIGNED NOT NULL,
    following_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_follow (follower_id, following_id),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- BETS
-- ------------------------------------------------------------
CREATE TABLE bets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    creator_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    type ENUM('event', 'custom', 'head2head', 'pool') NOT NULL DEFAULT 'custom',
    status ENUM('open', 'closed', 'resolved', 'cancelled') NOT NULL DEFAULT 'open',
    min_entry BIGINT UNSIGNED NOT NULL DEFAULT 10,
    max_entry BIGINT UNSIGNED DEFAULT NULL,
    fee_percent TINYINT UNSIGNED NOT NULL DEFAULT 5,
    total_pool BIGINT UNSIGNED NOT NULL DEFAULT 0,
    deadline TIMESTAMP NOT NULL,
    resolved_option_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (creator_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_bets_status ON bets (status);
CREATE INDEX idx_bets_deadline ON bets (deadline);

-- ------------------------------------------------------------
-- BET OPTIONS
-- ------------------------------------------------------------
CREATE TABLE bet_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bet_id INT UNSIGNED NOT NULL,
    label VARCHAR(150) NOT NULL,
    total_bet BIGINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (bet_id) REFERENCES bets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- BET ENTRIES
-- ------------------------------------------------------------
CREATE TABLE bet_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bet_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    option_id INT UNSIGNED NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    payout BIGINT UNSIGNED DEFAULT NULL,
    status ENUM('pending', 'won', 'lost', 'refunded') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_entry (bet_id, user_id),
    FOREIGN KEY (bet_id) REFERENCES bets(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (option_id) REFERENCES bet_options(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- CREDIT TRANSACTIONS
-- ------------------------------------------------------------
CREATE TABLE credit_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    amount BIGINT NOT NULL,
    balance_after BIGINT UNSIGNED NOT NULL,
    type ENUM('signup_bonus', 'daily_login', 'post_bonus', 'like_received', 'bet_entry', 'bet_win', 'bet_refund', 'tip_sent', 'tip_received', 'admin_grant', 'admin_deduct') NOT NULL,
    description VARCHAR(255),
    reference_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_transactions_user ON credit_transactions (user_id, created_at DESC);

-- ------------------------------------------------------------
-- NOTIFICATIONS
-- ------------------------------------------------------------
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('like', 'comment', 'follow', 'bet_resolved', 'tip_received', 'milestone', 'bet_created') NOT NULL,
    content VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_notifications_user ON notifications (user_id, is_read, created_at DESC);

-- ------------------------------------------------------------
-- DIRECT MESSAGES
-- ------------------------------------------------------------
CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_messages_conversation ON messages (sender_id, receiver_id, created_at DESC);
CREATE INDEX idx_messages_receiver ON messages (receiver_id, is_read);

-- ------------------------------------------------------------
-- BADGES
-- ------------------------------------------------------------
CREATE TABLE badges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    badge_key VARCHAR(50) NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_badge (user_id, badge_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- ANONYMOUS RATE LIMIT
-- ------------------------------------------------------------
CREATE TABLE anonymous_rate_limit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    last_post TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_anon_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- TIPS (Credit Tipping on posts)
-- ------------------------------------------------------------
CREATE TABLE tips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,
    post_id INT UNSIGNED NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
