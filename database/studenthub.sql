-- StudentHub database: schema, seed data, view and stored procedures.
-- Import in phpMyAdmin (Import tab) or:  mysql -u root < database/studenthub.sql
-- Works on MariaDB 10.4+ (XAMPP) and MySQL 8.
DROP DATABASE IF EXISTS studenthub;
CREATE DATABASE studenthub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studenthub;

-- ---------------------------------------------------------------- ER MODEL
-- courses 1──* students *──1 users(role) ; students *──* events via registrations
-- event_categories 1──* events ; users 1──* audit_logs ; users 1──* remember_tokens
-- Every non-key column depends only on its table's key (3NF): course and
-- category names live in lookup tables instead of being repeated as text.

CREATE TABLE users (
  user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','admin') NOT NULL DEFAULT 'student',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status)
) ENGINE=InnoDB;

CREATE TABLE courses (
  course_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(10) NOT NULL,
  name VARCHAR(120) NOT NULL,
  PRIMARY KEY (course_id),
  UNIQUE KEY uq_courses_code (code),
  UNIQUE KEY uq_courses_name (name)
) ENGINE=InnoDB;

CREATE TABLE students (
  student_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  course_id SMALLINT UNSIGNED NOT NULL,
  enrollment_no VARCHAR(20) DEFAULT NULL,
  full_name VARCHAR(100) NOT NULL,
  semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
  phone VARCHAR(20) DEFAULT NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',   -- soft delete
  archived_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (student_id),
  UNIQUE KEY uq_students_user (user_id),
  UNIQUE KEY uq_students_enrollment (enrollment_no),
  KEY idx_students_name (full_name),
  KEY idx_students_course_status (course_id, status),
  CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_students_course FOREIGN KEY (course_id) REFERENCES courses (course_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE event_categories (
  category_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  PRIMARY KEY (category_id),
  UNIQUE KEY uq_event_categories_name (name)
) ENGINE=InnoDB;

CREATE TABLE events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id SMALLINT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  location VARCHAR(150) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME DEFAULT NULL,
  capacity INT UNSIGNED NOT NULL DEFAULT 50,
  status ENUM('draft','published','closed','cancelled') NOT NULL DEFAULT 'published',
  poster_path VARCHAR(255) DEFAULT NULL,
  created_by BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id),
  KEY idx_events_status_starts (status, starts_at),
  KEY idx_events_category (category_id),
  CONSTRAINT fk_events_category FOREIGN KEY (category_id) REFERENCES event_categories (category_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_events_creator FOREIGN KEY (created_by) REFERENCES users (user_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE registrations (
  registration_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id BIGINT UNSIGNED NOT NULL,
  event_id BIGINT UNSIGNED NOT NULL,
  status ENUM('registered','cancelled','attended') NOT NULL DEFAULT 'registered',
  registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (registration_id),
  UNIQUE KEY uq_registrations_student_event (student_id, event_id),
  KEY idx_registrations_event_status (event_id, status),
  KEY idx_registrations_registered_at (registered_at),
  CONSTRAINT fk_registrations_student FOREIGN KEY (student_id) REFERENCES students (student_id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_registrations_event FOREIGN KEY (event_id) REFERENCES events (event_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE remember_tokens (
  token_hash CHAR(64) NOT NULL,                 -- SHA-256 of the cookie value
  user_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (token_hash),
  KEY idx_remember_user (user_id),
  KEY idx_remember_expiry (expires_at),
  CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  attempt_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(254) NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (attempt_id),
  KEY idx_attempts_email_time (email, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED DEFAULT NULL,
  details TEXT DEFAULT NULL,                    -- JSON text
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (audit_id),
  KEY idx_audit_created (created_at),
  KEY idx_audit_user (user_id),
  KEY idx_audit_action (action),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- SEED DATA

INSERT INTO courses (course_id, code, name) VALUES
  (1, 'CS', 'Computer Science'),
  (2, 'IT', 'Information Technology'),
  (3, 'CE', 'Computer Engineering'),
  (4, 'DS', 'Data Science');

INSERT INTO event_categories (category_id, name) VALUES
  (1, 'Workshop'),
  (2, 'Technology'),
  (3, 'Career'),
  (4, 'Cultural'),
  (5, 'Sports'),
  (6, 'Seminar');

INSERT INTO users (user_id, email, password_hash, role, status, last_login_at, created_at) VALUES
  (1, 'avery.lee@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', '2026-10-11 10:25:00', '2026-03-03 10:00:00'),
  (2, 'jordan.patel@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', '2026-10-12 17:03:00', '2026-04-02 10:00:00'),
  (3, 'morgan.chen@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', '2026-10-14 09:15:00', '2026-03-18 10:00:00'),
  (4, 'taylor.kim@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', '2026-10-19 09:14:00', '2026-03-19 10:00:00'),
  (5, 'casey.singh@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', '2026-10-02 11:02:00', '2026-04-10 10:00:00'),
  (6, 'riley.garcia@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', '2026-10-18 09:36:00', '2026-05-18 10:00:00'),
  (7, 'jamie.brown@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', '2026-10-04 17:36:00', '2026-04-12 10:00:00'),
  (8, 'alex.wilson@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', '2026-10-03 17:03:00', '2026-04-16 10:00:00'),
  (9, 'sam.lee@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', '2026-10-14 20:20:00', '2026-06-19 10:00:00'),
  (10, 'drew.martin@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', '2026-10-10 11:50:00', '2026-04-23 10:00:00'),
  (11, 'skylar.davis@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', '2026-10-19 12:33:00', '2026-06-11 10:00:00'),
  (12, 'quinn.moore@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', '2026-10-10 17:04:00', '2026-03-17 10:00:00'),
  (13, 'devon.taylor@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', NULL, '2026-05-05 10:00:00'),
  (14, 'robin.anderson@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', NULL, '2026-03-22 10:00:00'),
  (15, 'blake.thomas@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', NULL, '2026-05-11 10:00:00'),
  (16, 'cameron.jackson@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', NULL, '2026-06-19 10:00:00'),
  (17, 'finley.white@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', NULL, '2026-03-09 10:00:00'),
  (18, 'reese.harris@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', NULL, '2026-03-02 10:00:00'),
  (19, 'emerson.clark@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', NULL, '2026-06-10 10:00:00'),
  (20, 'rowan.lewis@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'inactive', NULL, '2026-05-01 10:00:00'),
  (21, 'aarav.shah@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', NULL, '2026-04-20 10:00:00'),
  (22, 'diya.mehta@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', NULL, '2026-03-07 10:00:00'),
  (23, 'kabir.desai@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', NULL, '2026-04-13 10:00:00'),
  (24, 'isha.trivedi@studenthub.test', '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS', 'student', 'active', NULL, '2026-03-06 10:00:00'),
  (25, 'admin@studenthub.test', '$2y$10$reOIwrkltCHDt6ttFmxWFudBRp6kOhTbr.I9pEQcD6qpDBRU/tihC', 'admin', 'active', '2026-10-21 09:15:00', '2026-03-01 09:00:00');

INSERT INTO students (student_id, user_id, course_id, enrollment_no, full_name, semester, phone, status) VALUES
  (1, 1, 1, 'D26DCS101', 'Avery Lee', 5, '9822633920', 'active'),
  (2, 2, 2, 'D26DIT102', 'Jordan Patel', 1, '9868202938', 'active'),
  (3, 3, 3, 'D26DCE103', 'Morgan Chen', 3, '9817933677', 'active'),
  (4, 4, 4, 'D26DDS104', 'Taylor Kim', 5, '9863241552', 'active'),
  (5, 5, 1, 'D26DCS105', 'Casey Singh', 3, '9829361589', 'active'),
  (6, 6, 2, 'D26DIT106', 'Riley Garcia', 5, '9834256684', 'active'),
  (7, 7, 3, 'D26DCE107', 'Jamie Brown', 1, '9883517017', 'active'),
  (8, 8, 4, 'D26DDS108', 'Alex Wilson', 5, '9881366283', 'active'),
  (9, 9, 1, 'D26DCS109', 'Sam Lee', 3, '9858530762', 'active'),
  (10, 10, 2, 'D26DIT110', 'Drew Martin', 1, '9820986393', 'active'),
  (11, 11, 3, 'D26DCE111', 'Skylar Davis', 5, '9870241505', 'active'),
  (12, 12, 4, 'D26DDS112', 'Quinn Moore', 3, '9832140838', 'active'),
  (13, 13, 1, 'D26DCS113', 'Devon Taylor', 3, '9866599395', 'active'),
  (14, 14, 2, 'D26DIT114', 'Robin Anderson', 1, '9884903659', 'active'),
  (15, 15, 3, 'D26DCE115', 'Blake Thomas', 5, '9857000147', 'active'),
  (16, 16, 4, 'D26DDS116', 'Cameron Jackson', 3, '9819229206', 'active'),
  (17, 17, 1, 'D26DCS117', 'Finley White', 3, '9899141000', 'active'),
  (18, 18, 2, 'D26DIT118', 'Reese Harris', 5, '9851554798', 'active'),
  (19, 19, 3, 'D26DCE119', 'Emerson Clark', 5, '9861780050', 'active'),
  (20, 20, 4, 'D26DDS120', 'Rowan Lewis', 3, '9857709585', 'active'),
  (21, 21, 1, 'D26DCS121', 'Aarav Shah', 1, '9876262352', 'active'),
  (22, 22, 2, 'D26DIT122', 'Diya Mehta', 3, '9827359750', 'active'),
  (23, 23, 3, 'D26DCE123', 'Kabir Desai', 3, '9876640001', 'active'),
  (24, 24, 4, 'D26DDS124', 'Isha Trivedi', 3, '9863907779', 'active');

INSERT INTO events (event_id, category_id, title, description, location, starts_at, ends_at, capacity, status, poster_path, created_by) VALUES
  (1, 1, 'Web Development Workshop', 'Hands-on session on accessible, responsive web fundamentals with HTML, CSS and PHP.', 'Lab 2', '2026-11-05 10:00:00', '2026-11-05 13:00:00', 40, 'published', 'uploads/posters/seed-1.jpg', 25),
  (2, 2, 'Campus Tech Showcase', 'Students demo software and hardware projects to faculty and industry guests.', 'Main Auditorium', '2026-11-12 13:00:00', '2026-11-12 16:30:00', 150, 'published', 'uploads/posters/seed-2.jpg', 25),
  (3, 3, 'Career Preparation Session', 'Resume reviews, mock interviews and a recruiter Q&A for final-year students.', 'Seminar Room A', '2026-11-19 11:00:00', '2026-11-19 12:30:00', 60, 'published', 'uploads/posters/seed-3.jpg', 25),
  (4, 2, 'Inter-College Hackathon', 'A 24-hour build sprint. Teams of three, mentors on call, prizes for the top five.', 'Innovation Hub', '2026-12-04 09:00:00', '2026-12-05 09:00:00', 18, 'published', 'uploads/posters/seed-4.jpg', 25),
  (5, 4, 'Annual Cultural Night', 'Music, dance and theatre performances by student clubs.', 'Open Air Theatre', '2026-12-12 18:00:00', '2026-12-12 22:00:00', 300, 'published', 'uploads/posters/seed-5.jpg', 25),
  (6, 6, 'Database Security Seminar', 'Prepared statements, hashing and least-privilege design, with live injection demos.', 'Seminar Room B', '2026-12-18 14:00:00', '2026-12-18 16:00:00', 50, 'draft', 'uploads/posters/seed-6.jpg', 25),
  (7, 1, 'Python for Data Workshop', 'Pandas, plotting and notebooks for first-time analysts.', 'Lab 4', '2026-09-14 10:00:00', '2026-09-14 13:00:00', 35, 'closed', 'uploads/posters/seed-7.jpg', 25),
  (8, 5, 'Freshers Sports Meet', 'Track events, cricket and relay races across all departments.', 'College Ground', '2026-08-22 08:00:00', '2026-08-22 14:00:00', 200, 'closed', 'uploads/posters/seed-8.jpg', 25),
  (9, 2, 'Open Source Contribution Day', 'Make your first pull request with help from alumni maintainers.', 'Lab 1', '2026-10-10 10:00:00', '2026-10-10 15:00:00', 30, 'closed', 'uploads/posters/seed-9.jpg', 25),
  (10, 6, 'Guest Lecture: Cloud Careers', 'A cloud architect on what the job is actually like.', 'Main Auditorium', '2026-11-27 15:00:00', '2026-11-27 16:30:00', 120, 'cancelled', 'uploads/posters/seed-10.jpg', 25);

INSERT INTO registrations (student_id, event_id, status, registered_at) VALUES
  (18, 2, 'registered', '2026-04-05 14:55:00'),
  (9, 5, 'registered', '2026-04-12 18:56:00'),
  (8, 9, 'attended', '2026-04-03 10:09:00'),
  (22, 1, 'registered', '2026-04-01 15:53:00'),
  (6, 2, 'registered', '2026-04-10 08:09:00'),
  (18, 3, 'registered', '2026-04-20 17:20:00'),
  (23, 7, 'registered', '2026-05-15 20:55:00'),
  (18, 5, 'registered', '2026-05-13 14:25:00'),
  (16, 5, 'registered', '2026-05-02 11:04:00'),
  (15, 9, 'attended', '2026-05-04 13:38:00'),
  (4, 7, 'attended', '2026-05-19 10:34:00'),
  (12, 7, 'attended', '2026-05-03 11:39:00'),
  (5, 2, 'registered', '2026-05-12 17:23:00'),
  (4, 8, 'attended', '2026-05-28 15:29:00'),
  (16, 2, 'cancelled', '2026-05-03 10:06:00'),
  (11, 2, 'registered', '2026-06-16 19:10:00'),
  (1, 1, 'cancelled', '2026-06-17 13:09:00'),
  (18, 7, 'registered', '2026-06-25 16:19:00'),
  (3, 2, 'registered', '2026-06-17 13:58:00'),
  (12, 1, 'registered', '2026-06-18 16:49:00'),
  (11, 1, 'registered', '2026-06-20 20:50:00'),
  (8, 5, 'registered', '2026-06-24 20:14:00'),
  (17, 5, 'registered', '2026-06-12 19:01:00'),
  (9, 1, 'registered', '2026-06-23 17:22:00'),
  (12, 3, 'registered', '2026-06-03 11:06:00'),
  (16, 1, 'registered', '2026-06-11 11:30:00'),
  (20, 7, 'registered', '2026-07-16 18:22:00'),
  (3, 8, 'attended', '2026-07-13 20:45:00'),
  (16, 9, 'attended', '2026-07-14 20:40:00'),
  (3, 5, 'registered', '2026-07-15 14:47:00'),
  (6, 9, 'registered', '2026-07-05 08:09:00'),
  (20, 5, 'registered', '2026-07-22 13:09:00'),
  (18, 9, 'registered', '2026-07-01 08:51:00'),
  (21, 8, 'attended', '2026-07-17 19:59:00'),
  (14, 1, 'registered', '2026-07-27 11:01:00'),
  (7, 2, 'registered', '2026-07-17 11:48:00'),
  (5, 7, 'attended', '2026-07-24 13:57:00'),
  (22, 5, 'registered', '2026-08-27 16:08:00'),
  (5, 9, 'registered', '2026-08-05 15:39:00'),
  (11, 5, 'registered', '2026-08-26 20:06:00'),
  (2, 1, 'registered', '2026-08-07 12:02:00'),
  (23, 2, 'registered', '2026-08-15 16:34:00'),
  (17, 1, 'registered', '2026-08-23 16:56:00'),
  (18, 1, 'registered', '2026-08-27 15:08:00'),
  (4, 5, 'cancelled', '2026-08-15 13:04:00'),
  (3, 1, 'registered', '2026-08-22 12:50:00'),
  (5, 3, 'registered', '2026-08-05 12:56:00'),
  (15, 1, 'registered', '2026-08-24 09:25:00'),
  (6, 1, 'registered', '2026-08-06 19:27:00'),
  (13, 3, 'registered', '2026-08-14 11:22:00'),
  (3, 3, 'registered', '2026-09-01 13:35:00'),
  (15, 7, 'registered', '2026-09-13 13:33:00'),
  (10, 8, 'attended', '2026-09-04 20:14:00'),
  (9, 7, 'attended', '2026-09-25 10:17:00'),
  (14, 2, 'registered', '2026-09-13 10:34:00'),
  (19, 5, 'registered', '2026-09-23 13:05:00'),
  (2, 9, 'attended', '2026-09-14 09:17:00'),
  (9, 8, 'attended', '2026-09-20 11:04:00'),
  (1, 3, 'registered', '2026-09-18 14:59:00'),
  (20, 9, 'attended', '2026-09-02 16:45:00'),
  (4, 9, 'attended', '2026-09-09 08:11:00'),
  (10, 2, 'registered', '2026-09-17 20:13:00'),
  (9, 3, 'registered', '2026-09-26 08:16:00'),
  (1, 7, 'attended', '2026-09-24 16:35:00'),
  (17, 2, 'registered', '2026-09-23 11:14:00'),
  (7, 9, 'attended', '2026-09-13 13:03:00'),
  (1, 8, 'attended', '2026-09-21 19:56:00'),
  (14, 9, 'attended', '2026-09-02 09:42:00'),
  (20, 1, 'registered', '2026-09-23 12:02:00'),
  (1, 2, 'registered', '2026-10-12 13:35:00'),
  (8, 7, 'attended', '2026-10-10 11:22:00'),
  (13, 8, 'registered', '2026-10-16 12:32:00'),
  (7, 1, 'registered', '2026-10-17 20:00:00'),
  (5, 5, 'registered', '2026-10-19 08:25:00'),
  (21, 1, 'registered', '2026-10-03 17:33:00'),
  (2, 5, 'registered', '2026-10-24 19:51:00'),
  (3, 7, 'attended', '2026-10-02 10:40:00'),
  (1, 4, 'registered', '2026-10-04 11:00:00'),
  (2, 4, 'registered', '2026-10-13 11:00:00'),
  (3, 4, 'registered', '2026-10-15 11:00:00'),
  (4, 4, 'registered', '2026-10-18 11:00:00'),
  (5, 4, 'registered', '2026-10-02 11:00:00'),
  (6, 4, 'registered', '2026-10-01 11:00:00'),
  (7, 4, 'registered', '2026-10-18 11:00:00'),
  (8, 4, 'registered', '2026-10-08 11:00:00'),
  (9, 4, 'registered', '2026-10-16 11:00:00'),
  (10, 4, 'registered', '2026-10-09 11:00:00'),
  (11, 4, 'registered', '2026-10-01 11:00:00');

INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES
  (25, 'login', 'user', 25, NULL, '127.0.0.1', '2026-10-21 09:15:00'),
  (25, 'event.created', 'event', 4, '{"title":"Inter-College Hackathon"}', '127.0.0.1', '2026-10-02 12:30:00'),
  (25, 'event.updated', 'event', 10, '{"status":{"from":"published","to":"cancelled"}}', '127.0.0.1', '2026-10-15 16:05:00'),
  (1, 'user.registered', 'user', 1, NULL, '127.0.0.1', '2026-03-12 10:00:00');

-- ------------------------------------------------- VIEW + STORED PROCEDURES
CREATE VIEW v_event_summary AS
SELECT e.event_id, e.title, e.description, e.location, e.starts_at, e.ends_at, e.capacity,
       e.status, e.poster_path, c.category_id, c.name AS category,
       COALESCE(SUM(r.status IN ('registered','attended')), 0) AS registered_count,
       GREATEST(e.capacity - COALESCE(SUM(r.status IN ('registered','attended')), 0), 0) AS seats_left
FROM events e
JOIN event_categories c ON c.category_id = e.category_id
LEFT JOIN registrations r ON r.event_id = e.event_id
GROUP BY e.event_id, c.category_id, c.name;

DELIMITER $$

-- Registers a student for an event atomically. Locks the event row so two
-- students cannot take the last seat at the same time.
CREATE PROCEDURE sp_register_for_event(IN p_student_id BIGINT UNSIGNED, IN p_event_id BIGINT UNSIGNED, OUT p_result VARCHAR(20))
BEGIN
  DECLARE v_status VARCHAR(20);
  DECLARE v_capacity INT UNSIGNED;
  DECLARE v_taken INT UNSIGNED;
  DECLARE v_existing VARCHAR(20);
  DECLARE v_start DATETIME;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; SET p_result = 'error'; END;

  START TRANSACTION;
  SELECT status, capacity, starts_at INTO v_status, v_capacity, v_start FROM events WHERE event_id = p_event_id FOR UPDATE;
  IF v_status IS NULL THEN
    SET p_result = 'not_found';
  ELSEIF v_status <> 'published' OR v_start < NOW() THEN
    SET p_result = 'closed';
  ELSE
    SELECT status INTO v_existing FROM registrations WHERE student_id = p_student_id AND event_id = p_event_id;
    IF v_existing IN ('registered','attended') THEN
      SET p_result = 'duplicate';
    ELSE
      SELECT COUNT(*) INTO v_taken FROM registrations WHERE event_id = p_event_id AND status IN ('registered','attended');
      IF v_taken >= v_capacity THEN
        SET p_result = 'full';
      ELSEIF v_existing = 'cancelled' THEN
        UPDATE registrations SET status = 'registered', registered_at = NOW() WHERE student_id = p_student_id AND event_id = p_event_id;
        SET p_result = 'ok';
      ELSE
        INSERT INTO registrations (student_id, event_id) VALUES (p_student_id, p_event_id);
        SET p_result = 'ok';
      END IF;
    END IF;
  END IF;
  COMMIT;
END$$

-- One round-trip for every number on the admin dashboard.
CREATE PROCEDURE sp_dashboard_stats()
BEGIN
  SELECT
    (SELECT COUNT(*) FROM users)                                              AS total_users,
    (SELECT COUNT(*) FROM users WHERE status = 'active')                      AS active_users,
    (SELECT COUNT(*) FROM students WHERE status = 'active')                   AS students,
    (SELECT COUNT(*) FROM students WHERE status = 'archived')                 AS archived_students,
    (SELECT COUNT(*) FROM events)                                             AS events,
    (SELECT COUNT(*) FROM events WHERE status = 'published' AND starts_at >= NOW()) AS open_events,
    (SELECT COUNT(*) FROM registrations WHERE status <> 'cancelled')          AS registrations,
    (SELECT COUNT(*) FROM audit_logs)                                         AS audit_entries;
END$$

DELIMITER ;
