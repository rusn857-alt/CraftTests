-- =============================================
-- Система тестирования - Структура базы данных
-- Версия: 1.0
-- =============================================

-- Создание базы данных
CREATE DATABASE IF NOT EXISTS `test_system` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `test_system`;

-- =============================================
-- Таблица: administrators (Администраторы)
-- =============================================
CREATE TABLE IF NOT EXISTS `administrators` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `login` VARCHAR(50) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Таблица: tests (Тесты)
-- =============================================
CREATE TABLE IF NOT EXISTS `tests` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(100) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `status` ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'draft',
    `settings` JSON DEFAULT NULL,
    `created_by` INT(11) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `fk_tests_created_by` (`created_by`),
    CONSTRAINT `fk_tests_administrators` FOREIGN KEY (`created_by`) 
        REFERENCES `administrators` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Таблица: questions (Вопросы)
-- =============================================
CREATE TABLE IF NOT EXISTS `questions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `test_id` INT(11) NOT NULL,
    `type` ENUM('single', 'multiple', 'text', 'number') NOT NULL,
    `text` TEXT NOT NULL,
    `points` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_questions_test_id` (`test_id`),
    CONSTRAINT `fk_questions_tests` FOREIGN KEY (`test_id`) 
        REFERENCES `tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Таблица: answer_options (Варианты ответов)
-- =============================================
CREATE TABLE IF NOT EXISTS `answer_options` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) NOT NULL,
    `text` TEXT NOT NULL,
    `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
    `points` DECIMAL(5,2) DEFAULT NULL,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_answer_options_question_id` (`question_id`),
    CONSTRAINT `fk_answer_options_questions` FOREIGN KEY (`question_id`) 
        REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Таблица: users (Пользователи, проходящие тесты)
-- =============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Таблица: test_sessions (Сессии прохождения тестов)
-- =============================================
CREATE TABLE IF NOT EXISTS `test_sessions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `test_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `session_token` VARCHAR(64) NOT NULL,
    `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `status` ENUM('in_progress', 'completed', 'abandoned') NOT NULL DEFAULT 'in_progress',
    `total_score` DECIMAL(10,2) DEFAULT NULL,
    `max_possible_score` DECIMAL(10,2) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_session_token` (`session_token`),
    KEY `fk_test_sessions_test_id` (`test_id`),
    KEY `fk_test_sessions_user_id` (`user_id`),
    CONSTRAINT `fk_test_sessions_tests` FOREIGN KEY (`test_id`) 
        REFERENCES `tests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_test_sessions_users` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Таблица: user_answers (Ответы пользователей)
-- =============================================
CREATE TABLE IF NOT EXISTS `user_answers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `session_id` INT(11) NOT NULL,
    `question_id` INT(11) NOT NULL,
    `answer_text` TEXT,
    `answer_option_id` INT(11) DEFAULT NULL,
    `is_correct` TINYINT(1) DEFAULT NULL,
    `points_earned` DECIMAL(5,2) DEFAULT NULL,
    `answered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_user_answers_session_id` (`session_id`),
    KEY `fk_user_answers_question_id` (`question_id`),
    KEY `fk_user_answers_answer_option_id` (`answer_option_id`),
    CONSTRAINT `fk_user_answers_test_sessions` FOREIGN KEY (`session_id`) 
        REFERENCES `test_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_answers_questions` FOREIGN KEY (`question_id`) 
        REFERENCES `questions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_answers_answer_options` FOREIGN KEY (`answer_option_id`) 
        REFERENCES `answer_options` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Начальные данные
-- =============================================

-- Создание администратора (пароль: admin8800)
INSERT INTO `administrators` (`login`, `password_hash`) VALUES 
('admin', '$2y$10$YourHashHere') 
-- ВНИМАНИЕ: Замените на реальный хеш из PHP: password_hash('admin8800', PASSWORD_DEFAULT)
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`);

-- =============================================
-- Индексы для оптимизации запросов
-- =============================================
CREATE INDEX idx_tests_status ON tests(status);
CREATE INDEX idx_questions_test_id_sort ON questions(test_id, sort_order);
CREATE INDEX idx_answer_options_question_sort ON answer_options(question_id, sort_order);
CREATE INDEX idx_test_sessions_user_status ON test_sessions(user_id, status);
CREATE INDEX idx_user_answers_session_question ON user_answers(session_id, question_id);

-- =============================================
-- Триггер для автоматического обновления updated_at
-- (MySQL 5.7 уже поддерживает ON UPDATE CURRENT_TIMESTAMP)
-- =============================================

-- =============================================
-- Комментарии к таблицам
-- =============================================
ALTER TABLE `administrators` COMMENT = 'Администраторы системы';
ALTER TABLE `tests` COMMENT = 'Тесты';
ALTER TABLE `questions` COMMENT = 'Вопросы тестов';
ALTER TABLE `answer_options` COMMENT = 'Варианты ответов';
ALTER TABLE `users` COMMENT = 'Пользователи, проходящие тесты';
ALTER TABLE `test_sessions` COMMENT = 'Сессии прохождения тестов';
ALTER TABLE `user_answers` COMMENT = 'Ответы пользователей на вопросы';