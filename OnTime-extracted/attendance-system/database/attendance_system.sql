-- ============================================================
--  Employee Attendance System — Full Database Schema
--  File   : attendance_system.sql
--  Import : phpMyAdmin > Import, or: mysql -u root < attendance_system.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `attendance_system`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `attendance_system`;

-- ============================================================
-- Drop tables in reverse-dependency order to avoid FK errors
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `leave_requests`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `employee_schedules`;
DROP TABLE IF EXISTS `schedules`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `registrations`;
DROP TABLE IF EXISTS `positions`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. users
-- ============================================================
CREATE TABLE `users` (
  `id`          INT           AUTO_INCREMENT PRIMARY KEY,
  `username`    VARCHAR(100)  UNIQUE NOT NULL,
  `password`    VARCHAR(255)  NOT NULL,
  `role`        ENUM('admin','instructor','staff') NOT NULL,
  `employee_id` INT           NULL,                  -- FK to employees (added after employees table)
  `status`      ENUM('active','inactive','pending') DEFAULT 'pending',
  `created_at`  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. departments
-- ============================================================
CREATE TABLE `departments` (
  `id`          INT           AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(150)  NOT NULL,
  `description` TEXT,
  `created_at`  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. positions
-- ============================================================
CREATE TABLE `positions` (
  `id`            INT           AUTO_INCREMENT PRIMARY KEY,
  `title`         VARCHAR(150)  NOT NULL,
  `employee_type` ENUM('instructor','staff') NOT NULL,
  `department_id` INT           NULL,
  `created_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_positions_department`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. registrations  (pending employee applications)
-- ============================================================
CREATE TABLE `registrations` (
  `id`               INT           AUTO_INCREMENT PRIMARY KEY,
  `full_name`        VARCHAR(200)  NOT NULL,
  `email`            VARCHAR(150)  NOT NULL,
  `contact_number`   VARCHAR(20),
  `honorific`        ENUM('Mr.','Ms.','Mrs.','Dr.','Prof.') NOT NULL,
  `age`              INT           NOT NULL,
  `gender`           ENUM('Male','Female','Other') NOT NULL,
  `employee_type`    ENUM('instructor','staff') NOT NULL,
  `department_id`    INT           NULL,
  `specialization`   VARCHAR(200)  NULL,
  `password_hash`    VARCHAR(255)  NOT NULL,
  `status`           ENUM('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` TEXT          NULL,
  `applied_at`       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at`      TIMESTAMP     NULL,
  `reviewed_by`      INT           NULL,               -- FK to users.id
  CONSTRAINT `fk_registrations_department`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. employees
-- ============================================================
CREATE TABLE `employees` (
  `id`                INT           AUTO_INCREMENT PRIMARY KEY,
  `employee_code`     VARCHAR(20)   UNIQUE NOT NULL,
  `full_name`         VARCHAR(200)  NOT NULL,
  `email`             VARCHAR(150)  UNIQUE NOT NULL,
  `contact_number`    VARCHAR(20),
  `honorific`         ENUM('Mr.','Ms.','Mrs.','Dr.','Prof.'),
  `age`               INT,
  `gender`            ENUM('Male','Female','Other'),
  `employee_type`     ENUM('instructor','staff') NOT NULL,
  `department_id`     INT,
  `position_id`       INT           NULL,
  `specialization`    VARCHAR(200)  NULL,
  `date_hired`        DATE,
  `employment_status` ENUM('active','inactive','on_leave') DEFAULT 'active',
  `profile_picture`   VARCHAR(255)  NULL,
  `registration_id`   INT           NULL,
  `created_at`        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_employees_department`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_employees_position`
    FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_employees_registration`
    FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Now add FK columns on tables that need employees / users
-- ============================================================

-- users.employee_id -> employees.id
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- registrations.reviewed_by -> users.id
ALTER TABLE `registrations`
  ADD CONSTRAINT `fk_registrations_reviewed_by`
    FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================================
-- 6. schedules
-- ============================================================
CREATE TABLE `schedules` (
  `id`                      INT            AUTO_INCREMENT PRIMARY KEY,
  `schedule_name`           VARCHAR(150)   NOT NULL,
  `time_in`                 TIME           NOT NULL,
  `time_out`                TIME           NOT NULL,
  `work_days`               VARCHAR(100)   NOT NULL,  -- JSON array e.g. ["Mon","Tue","Wed","Thu","Fri"]
  `grace_period_minutes`    INT            DEFAULT 15,
  `break_duration_minutes`  INT            DEFAULT 60,
  `required_hours`          DECIMAL(4,2)   DEFAULT 8.00,
  `is_overnight`            TINYINT(1)     DEFAULT 0,
  `created_at`              TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. employee_schedules
-- ============================================================
CREATE TABLE `employee_schedules` (
  `id`             INT   AUTO_INCREMENT PRIMARY KEY,
  `employee_id`    INT   NOT NULL,
  `schedule_id`    INT   NOT NULL,
  `effective_date` DATE  NOT NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_empsch_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_empsch_schedule`
    FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. attendance
-- ============================================================
CREATE TABLE `attendance` (
  `id`                  INT           AUTO_INCREMENT PRIMARY KEY,
  `employee_id`         INT           NOT NULL,
  `attendance_date`     DATE          NOT NULL,
  `scheduled_time_in`   TIME          NULL,
  `scheduled_time_out`  TIME          NULL,
  `actual_time_in`      DATETIME      NULL,
  `actual_time_out`     DATETIME      NULL,
  `total_hours`         DECIMAL(5,2)  NULL,
  `attendance_status`   ENUM('present','late','absent','on_leave','half_day') DEFAULT 'absent',
  `completion_status`   ENUM('completed','incomplete','early_leave','pending') DEFAULT 'pending',
  `early_leave_reason`  TEXT          NULL,
  `is_corrected`        TINYINT(1)    DEFAULT 0,
  `correction_reason`   TEXT          NULL,
  `corrected_by`        INT           NULL,
  `created_at`          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_attendance` (`employee_id`, `attendance_date`),
  CONSTRAINT `fk_attendance_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_corrected_by`
    FOREIGN KEY (`corrected_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. leave_requests
-- ============================================================
CREATE TABLE `leave_requests` (
  `id`               INT    AUTO_INCREMENT PRIMARY KEY,
  `employee_id`      INT    NOT NULL,
  `leave_type`       ENUM('sick','vacation','emergency','maternity','paternity','unpaid') NOT NULL,
  `start_date`       DATE   NOT NULL,
  `end_date`         DATE   NOT NULL,
  `reason`           TEXT   NOT NULL,
  `status`           ENUM('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` TEXT   NULL,
  `reviewed_by`      INT    NULL,
  `reviewed_at`      TIMESTAMP NULL,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_leave_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_reviewed_by`
    FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. notifications
-- ============================================================
CREATE TABLE `notifications` (
  `id`         INT           AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT           NOT NULL,
  `title`      VARCHAR(200)  NOT NULL,
  `message`    TEXT          NOT NULL,
  `is_read`    TINYINT(1)    DEFAULT 0,
  `created_at` TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notif_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. audit_logs
-- ============================================================
CREATE TABLE `audit_logs` (
  `id`             INT           AUTO_INCREMENT PRIMARY KEY,
  `user_id`        INT           NOT NULL,
  `action`         VARCHAR(200)  NOT NULL,
  `description`    TEXT,
  `affected_table` VARCHAR(100),
  `affected_id`    INT           NULL,
  `ip_address`     VARCHAR(45),
  `created_at`     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_audit_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DEFAULT DATA INSERTS
-- ============================================================

-- 1. Admin user (username = 'admin', password = 'wasd123'; change after first login)
INSERT INTO `users` (`username`, `password`, `role`, `employee_id`, `status`) VALUES
  ('admin', '$2y$10$OBkfMU8SXe.4D3LboUwGFOOFm1Ljy.M2UuEdoYpc.4ii3mm2Auj82', 'admin', NULL, 'active');

-- 2. Departments
INSERT INTO `departments` (`name`, `description`) VALUES
  ('Administration', 'Handles overall school administration and management'),
  ('Faculty',        'Academic teaching staff and instructors'),
  ('Registrar',      'Manages student records and enrollment'),
  ('Finance',        'Handles billing, fees, and financial transactions'),
  ('Guidance',       'Student counseling and welfare services'),
  ('Maintenance',    'Facility upkeep and repairs'),
  ('Security',       'Campus security and safety');

-- 3. Positions  (department_id references above inserts)
INSERT INTO `positions` (`title`, `employee_type`, `department_id`) VALUES
  ('Principal',          'staff',      1),   -- Administration
  ('Vice Principal',     'staff',      1),   -- Administration
  ('Teacher',            'instructor', 2),   -- Faculty
  ('Registrar Officer',  'staff',      3),   -- Registrar
  ('Cashier',            'staff',      4),   -- Finance
  ('Guidance Officer',   'staff',      5),   -- Guidance
  ('Security Guard',     'staff',      7),   -- Security
  ('Housekeeping Staff', 'staff',      6);   -- Maintenance

-- 4. Default schedule — Morning Shift Mon–Fri 07:00–15:00
INSERT INTO `schedules`
  (`schedule_name`, `time_in`, `time_out`, `work_days`, `grace_period_minutes`, `break_duration_minutes`, `required_hours`, `is_overnight`)
VALUES
  ('Morning Shift', '07:00:00', '15:00:00', '["Mon","Tue","Wed","Thu","Fri"]', 15, 60, 8.00, 0);

-- ============================================================
-- End of attendance_system.sql
-- ============================================================
