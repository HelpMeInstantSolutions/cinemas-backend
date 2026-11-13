-- Database Schema for Cinemas App (cinemas.mybhiwani.in)
CREATE DATABASE IF NOT EXISTS `cinemas_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cinemas_db`;

-- Admins Table
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Genres Table
CREATE TABLE IF NOT EXISTS `genres` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `icon` VARCHAR(100) DEFAULT 'bi-film',
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dailymotion Accounts Table
CREATE TABLE IF NOT EXISTS `dm_accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `channel_name` VARCHAR(150) DEFAULT NULL,
  `uid` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `client_id` VARCHAR(150) NOT NULL,
  `client_secret` VARCHAR(255) NOT NULL,
  `refresh_token` TEXT NOT NULL,
  `access_token` TEXT,
  `expires_at` INT DEFAULT 0,
  `is_default` TINYINT(1) DEFAULT 0,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Media Catalog (Movies and Web Series)
CREATE TABLE IF NOT EXISTS `media_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(20) NOT NULL DEFAULT 'movie', -- 'movie' or 'series'
  `genre_id` INT DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `poster` VARCHAR(500) DEFAULT NULL,
  `backdrop` VARCHAR(500) DEFAULT NULL,
  `release_year` VARCHAR(10) DEFAULT '2024',
  `duration` VARCHAR(50) DEFAULT '2h 15m',
  `rating` VARCHAR(10) DEFAULT '8.8',
  `language` VARCHAR(50) DEFAULT 'Hindi',
  `dailymotion_id` VARCHAR(100) DEFAULT NULL,
  `stream_url` VARCHAR(500) DEFAULT NULL,
  `is_featured` TINYINT(1) DEFAULT 0,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`genre_id`) REFERENCES `genres`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seasons (for Web Series)
CREATE TABLE IF NOT EXISTS `seasons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `series_id` INT NOT NULL,
  `season_number` INT NOT NULL DEFAULT 1,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `poster` VARCHAR(500) DEFAULT NULL,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`series_id`) REFERENCES `media_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Episodes (for Seasons)
CREATE TABLE IF NOT EXISTS `episodes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `series_id` INT NOT NULL,
  `season_id` INT NOT NULL,
  `dm_account_id` INT DEFAULT 0,
  `episode_number` INT NOT NULL DEFAULT 1,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `thumbnail` VARCHAR(500) DEFAULT NULL,
  `dailymotion_id` VARCHAR(100) NOT NULL,
  `stream_url` VARCHAR(500) DEFAULT NULL,
  `duration` VARCHAR(50) DEFAULT '45:00',
  `is_free_preview` TINYINT(1) DEFAULT 0,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`series_id`) REFERENCES `media_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backward Compatibility Views
CREATE OR REPLACE VIEW `categories` AS SELECT `id`, `title`, `icon`, `status`, `created_at` FROM `genres`;
CREATE OR REPLACE VIEW `courses` AS SELECT `id`, `genre_id` AS `category_id`, `title`, `description`, `poster` AS `thumbnail`, 0.00 AS `price`, `status`, `created_at` FROM `media_items`;
CREATE OR REPLACE VIEW `chapters` AS SELECT `id`, `series_id` AS `course_id`, `title`, `description`, `season_number` AS `sort_order`, `status`, `created_at` FROM `seasons`;
CREATE OR REPLACE VIEW `videos` AS SELECT `id`, `series_id` AS `course_id`, `season_id` AS `chapter_id`, `dm_account_id`, `title`, `description`, `dailymotion_id`, `duration`, `episode_number` AS `sort_order`, `is_free_preview`, `status`, `created_at` FROM `episodes`;

-- Seed Admin (default: admin / admin123)
INSERT INTO `admins` (`username`, `password`, `name`) 
VALUES ('admin', '$2y$10$tZz27lUu88.3d37jD2o1Qel3RzQx1qH2t97xOfeZqN0Nl9GvI549S', 'Cinema Admin')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Genres
INSERT INTO `genres` (`id`, `title`, `icon`) VALUES
(1, 'Action & Thriller', 'bi-lightning-charge'),
(2, 'Bollywood Blockbusters', 'bi-camera-reels'),
(3, 'Hollywood Hindi Dubbed', 'bi-globe-americas'),
(4, 'Web Series & Crime', 'bi-tv'),
(5, 'Comedy & Family', 'bi-emoji-smile'),
(6, 'Sci-Fi & Fantasy', 'bi-stars')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Connected Dailymotion Account
INSERT INTO `dm_accounts` (`id`, `name`, `channel_name`, `uid`, `email`, `client_id`, `client_secret`, `refresh_token`, `access_token`, `expires_at`, `is_default`, `status`)
VALUES (1, 'Cinemas Master Streamer', 'neniyo7267', 'x6mtvoi', 'neniyo7267@artegin.com', '20339e78eaa0487f12ea', 'd416efc0eddc6d29e1900833176cbddb5373a1e3', 'd84f059ff876d79d93e091c2ffab5fc390a7dce7', 'bnhdJjMwPRlcJi4nPyUfHggHAgA1QwUYMnYHB1IlKQw-', 1794623595, 1, 1)
ON DUPLICATE KEY UPDATE `channel_name`='neniyo7267', `uid`='x6mtvoi', `email`='neniyo7267@artegin.com', `client_id`='20339e78eaa0487f12ea', `client_secret`='d416efc0eddc6d29e1900833176cbddb5373a1e3', `refresh_token`='d84f059ff876d79d93e091c2ffab5fc390a7dce7';

-- Seed Sample Movie
INSERT INTO `media_items` (`id`, `type`, `genre_id`, `title`, `description`, `poster`, `backdrop`, `release_year`, `duration`, `rating`, `language`, `dailymotion_id`, `is_featured`, `status`) VALUES
(1, 'movie', 1, 'Jawan: Extended Cut', 'A high-octane action thriller detailing an emotional journey of a man who is set to rectify the wrongs in the society.', 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=600&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1574267432553-4b4628081c31?w=1200&auto=format&fit=crop&q=80', '2023', '2h 45m', '8.8', 'Hindi', 'x8n0001', 1, 1),
(2, 'series', 4, 'Mirzapur: Season of Vengeance', 'A shocking incident at a wedding procession ignites a series of events entangling the lives of two families in the lawless city of Mirzapur.', 'https://images.unsplash.com/photo-1616530940355-351fabd9524b?w=600&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1518676590629-3dcbd9c5a5c9?w=1200&auto=format&fit=crop&q=80', '2024', '3 Seasons', '9.1', 'Hindi', '', 1, 1)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Seasons for Mirzapur
INSERT INTO `seasons` (`id`, `series_id`, `season_number`, `title`, `description`, `poster`, `status`) VALUES
(1, 2, 1, 'Season 1: Rule of Akhandanand', 'The rise of Kaleen Bhaiya and the conflict with Guddu & Bablu.', 'https://images.unsplash.com/photo-1616530940355-351fabd9524b?w=600&auto=format&fit=crop&q=80', 1),
(2, 2, 2, 'Season 2: Retribution', 'Blood boils and empires clash for total control of Purvanchal.', 'https://images.unsplash.com/photo-1616530940355-351fabd9524b?w=600&auto=format&fit=crop&q=80', 1)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Episodes for Mirzapur
INSERT INTO `episodes` (`id`, `series_id`, `season_id`, `dm_account_id`, `episode_number`, `title`, `description`, `thumbnail`, `dailymotion_id`, `duration`, `is_free_preview`, `status`) VALUES
(1, 2, 1, 1, 1, 'Episode 1: Bhaukaal', 'Akhandanand Tripathi is a carpet exporter and the mafia don of Mirzapur.', 'https://images.unsplash.com/photo-1518676590629-3dcbd9c5a5c9?w=600&auto=format&fit=crop&q=80', 'x8n0002', '48:10', 1, 1),
(2, 2, 1, 1, 2, 'Episode 2: Gaddi', 'Guddu and Bablu must decide where their true loyalty lies.', 'https://images.unsplash.com/photo-1518676590629-3dcbd9c5a5c9?w=600&auto=format&fit=crop&q=80', 'x8n0003', '52:15', 0, 1),
(3, 2, 2, 1, 1, 'Episode 1: Dhenkul', 'The war begins anew as surviving factions regroup for vengeance.', 'https://images.unsplash.com/photo-1518676590629-3dcbd9c5a5c9?w=600&auto=format&fit=crop&q=80', 'x8n0004', '56:00', 1, 1)
ON DUPLICATE KEY UPDATE `id`=`id`;
