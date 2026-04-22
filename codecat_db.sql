CREATE DATABASE IF NOT EXISTS codecat_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE codecat_db;

DROP TABLE IF EXISTS classroom_members;
DROP TABLE IF EXISTS classrooms;
DROP TABLE IF EXISTS student_level_progress;
DROP TABLE IF EXISTS teacher_levels;
DROP TABLE IF EXISTS teacher_reports;
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

CREATE TABLE classrooms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_classrooms_teacher (teacher_id),
    CONSTRAINT fk_classrooms_teacher
        FOREIGN KEY (teacher_id) REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE TABLE classroom_members (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    classroom_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_classroom_student (classroom_id, student_id),
    KEY idx_classroom_members_student (student_id),
    CONSTRAINT fk_classroom_members_classroom
        FOREIGN KEY (classroom_id) REFERENCES classrooms(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_classroom_members_student
        FOREIGN KEY (student_id) REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE TABLE teacher_levels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL,
    instructions TEXT NOT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') NOT NULL DEFAULT 'beginner',
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    grid_width INT UNSIGNED NOT NULL DEFAULT 6,
    grid_height INT UNSIGNED NOT NULL DEFAULT 6,
    start_x INT UNSIGNED NOT NULL DEFAULT 0,
    start_y INT UNSIGNED NOT NULL DEFAULT 0,
    goal_x INT UNSIGNED NOT NULL DEFAULT 5,
    goal_y INT UNSIGNED NOT NULL DEFAULT 5,
    walls TEXT NULL,
    spikes TEXT NULL,
    entities LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_teacher_levels_teacher (teacher_id),
    KEY idx_teacher_levels_classroom (classroom_id),
    CONSTRAINT fk_teacher_levels_teacher
        FOREIGN KEY (teacher_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_teacher_levels_classroom
        FOREIGN KEY (classroom_id) REFERENCES classrooms(id)
        ON DELETE CASCADE
);

CREATE TABLE student_level_progress (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    level_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed') NOT NULL DEFAULT 'not_started',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_played_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_level_progress (level_id, student_id),
    KEY idx_student_level_progress_student (student_id),
    CONSTRAINT fk_student_level_progress_level
        FOREIGN KEY (level_id) REFERENCES teacher_levels(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_student_level_progress_student
        FOREIGN KEY (student_id) REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE TABLE teacher_reports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    title VARCHAR(160) NOT NULL,
    report_type ENUM('generated_pdf', 'imported_pdf') NOT NULL,
    summary TEXT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_teacher_reports_teacher (teacher_id),
    KEY idx_teacher_reports_classroom (classroom_id),
    CONSTRAINT fk_teacher_reports_teacher
        FOREIGN KEY (teacher_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_teacher_reports_classroom
        FOREIGN KEY (classroom_id) REFERENCES classrooms(id)
        ON DELETE SET NULL
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
