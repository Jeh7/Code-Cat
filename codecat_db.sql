CREATE DATABASE IF NOT EXISTS codecat_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE codecat_db;

DROP TABLE IF EXISTS user_achievements;
DROP TABLE IF EXISTS achievements;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('student', 'teacher', 'admin', 'na') NOT NULL DEFAULT 'na',
    register_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
);

CREATE TABLE achievements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_achievements_title (title)
);

CREATE TABLE user_achievements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    achievement_id INT UNSIGNED NOT NULL,
    unlocked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_achievement (user_id, achievement_id),
    CONSTRAINT fk_user_achievements_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_user_achievements_achievement
        FOREIGN KEY (achievement_id) REFERENCES achievements(id)
        ON DELETE CASCADE
);

INSERT INTO achievements (title, description) VALUES
    ('First Login', 'Log in to Code Cat for the first time.'),
    ('Ready to Learn', 'Create an account and choose a role.'),
    ('Puzzle Starter', 'Launch the game from the dashboard.'),
    ('Achievement Hunter', 'Unlock your first achievement.'),
    ('Teacher Mode', 'Register with the teacher role.');

-- Optional admin account.
-- Password: admin123
INSERT INTO users (username, password, email, role) VALUES
    ('admin', '$2y$10$zbDMZakFTagiFJFS7GhTUO30PlUevadIS2EYW6kt2LfbJAUWujyJ6', 'admin@codecat.local', 'admin');
