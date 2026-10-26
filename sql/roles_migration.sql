-- Run ONCE in phpMyAdmin (database: workload_system), AFTER workload_db.sql and instructors_migration.sql.
-- Adds: an `admins` table, and per-instructor login support on `faculty`.

CREATE TABLE IF NOT EXISTS `admins` (
  `admin_id`   int(11)      NOT NULL AUTO_INCREMENT,
  `username`   varchar(50)  NOT NULL,
  `full_name`  varchar(100) NOT NULL,
  `password`   varchar(255) NOT NULL,
  `created_at` timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `uq_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- 1 = the instructor must choose a new password at next sign-in (set whenever an admin issues a temporary one).
ALTER TABLE `faculty`
  ADD COLUMN `must_change_password` tinyint(1) NOT NULL DEFAULT 0 AFTER `password`,
  ADD UNIQUE KEY `uq_faculty_username` (`username`);

-- Starter instructors were created with an unusable password ('!'), so they cannot sign in until
-- an admin presses "Reset password" on the Instructors page. Nothing else to do here.

-- The first administrator is created in the browser: open setup.php (it locks itself afterwards).
