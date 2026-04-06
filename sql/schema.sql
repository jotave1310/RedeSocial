-- ============================================================
-- CARVASILVA - Database Schema
-- MySQL / MariaDB
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

CREATE DATABASE IF NOT EXISTS carvasilva
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE carvasilva;

-- ------------------------------------------------------------
-- ROOMS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rooms (code, name) VALUES
    ('1A', 'Turma 1A'),
    ('1B', 'Turma 1B'),
    ('1C', 'Turma 1C'),
    ('2A', 'Turma 2A'),
    ('2B', 'Turma 2B'),
    ('2C', 'Turma 2C'),
    ('3A', 'Turma 3A'),
    ('3B', 'Turma 3B'),
    ('3C', 'Turma 3C')
ON DUPLICATE KEY UPDATE
    name = VALUES(name);

-- ------------------------------------------------------------
-- USERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
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
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_room
        FOREIGN KEY (room_id) REFERENCES rooms(id),
    INDEX idx_users_credits (credits),
    INDEX idx_users_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- POSTS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS posts (
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
    CONSTRAINT fk_posts_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_posts_repost
        FOREIGN KEY (repost_of) REFERENCES posts(id) ON DELETE SET NULL,
    INDEX idx_posts_created (created_at),
    INDEX idx_posts_user (user_id),
    INDEX idx_posts_type (type),
    INDEX idx_posts_anonymous (is_anonymous)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- COMMENTS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    content VARCHAR(500) NOT NULL,
    is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_post
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_comments_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- LIKES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS likes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_like (post_id, user_id),
    CONSTRAINT fk_likes_post
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_likes_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- FOLLOWS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS follows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    follower_id INT UNSIGNED NOT NULL,
    following_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_follow (follower_id, following_id),
    CONSTRAINT fk_follows_follower
        FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_follows_following
        FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- BETS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    creator_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    type ENUM('event', 'custom', 'head2head', 'pool') NOT NULL DEFAULT 'custom',
    status ENUM('open', 'closed', 'resolved', 'cancelled') NOT NULL DEFAULT 'open',
    min_entry BIGINT UNSIGNED NOT NULL DEFAULT 10,
    max_entry BIGINT UNSIGNED DEFAULT NULL,
    fee_percent TINYINT UNSIGNED NOT NULL DEFAULT 5,
    total_pool BIGINT UNSIGNED NOT NULL DEFAULT 0,
    deadline TIMESTAMP NOT NULL,
    resolved_option_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_bets_creator
        FOREIGN KEY (creator_id) REFERENCES users(id),
    INDEX idx_bets_status (status),
    INDEX idx_bets_deadline (deadline)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- BET OPTIONS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bet_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bet_id INT UNSIGNED NOT NULL,
    label VARCHAR(150) NOT NULL,
    total_bet BIGINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_bet_options_bet
        FOREIGN KEY (bet_id) REFERENCES bets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- BET ENTRIES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bet_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bet_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    option_id INT UNSIGNED NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    payout BIGINT UNSIGNED DEFAULT NULL,
    status ENUM('pending', 'won', 'lost', 'refunded') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_entry (bet_id, user_id),
    CONSTRAINT fk_bet_entries_bet
        FOREIGN KEY (bet_id) REFERENCES bets(id),
    CONSTRAINT fk_bet_entries_user
        FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_bet_entries_option
        FOREIGN KEY (option_id) REFERENCES bet_options(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CREDIT TRANSACTIONS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS credit_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    amount BIGINT NOT NULL,
    balance_after BIGINT UNSIGNED NOT NULL,
    type ENUM('signup_bonus', 'daily_login', 'post_bonus', 'like_received', 'bet_entry', 'bet_win', 'bet_refund', 'tip_sent', 'tip_received', 'admin_grant', 'admin_deduct') NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    reference_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_credit_transactions_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_transactions_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- NOTIFICATIONS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('like', 'comment', 'follow', 'bet_resolved', 'tip_received', 'milestone', 'bet_created') NOT NULL,
    content VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- DIRECT MESSAGES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_sender
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_receiver
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_messages_conversation (sender_id, receiver_id, created_at),
    INDEX idx_messages_receiver (receiver_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- BADGES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS badges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    badge_key VARCHAR(50) NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_badge (user_id, badge_key),
    CONSTRAINT fk_badges_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ANONYMOUS RATE LIMIT
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS anonymous_rate_limit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    last_post TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_anon_user (user_id),
    CONSTRAINT fk_anonymous_rate_limit_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TIPS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,
    post_id INT UNSIGNED NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tips_sender
        FOREIGN KEY (sender_id) REFERENCES users(id),
    CONSTRAINT fk_tips_receiver
        FOREIGN KEY (receiver_id) REFERENCES users(id),
    CONSTRAINT fk_tips_post
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
