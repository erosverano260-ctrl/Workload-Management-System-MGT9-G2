-- OPTIONAL. The app creates all of this by itself the first time it is needed.
-- Run it once in phpMyAdmin (database: workload_system) only if you prefer to do it by hand.
-- MariaDB (XAMPP) syntax; on plain MySQL remove the "IF NOT EXISTS" after ADD COLUMN.

CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int(11)      NOT NULL AUTO_INCREMENT,
  `user_role`       varchar(10)  NOT NULL,
  `user_id`         int(11)      NOT NULL,
  `type`            varchar(30)  NOT NULL DEFAULT 'info',
  `message`         varchar(255) NOT NULL,
  `link`            varchar(120) DEFAULT NULL,
  `is_read`         tinyint(1)   NOT NULL DEFAULT 0,
  `created_at`      timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_notif_user` (`user_role`, `user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- the chair's decision on each request: when, and an optional note shown to the instructor
ALTER TABLE `faculty_designations`
  ADD COLUMN IF NOT EXISTS `decided_at`    timestamp    NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `decision_note` varchar(255) NULL DEFAULT NULL;
ALTER TABLE `overload_sessions`
  ADD COLUMN IF NOT EXISTS `decided_at`    timestamp    NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `decision_note` varchar(255) NULL DEFAULT NULL;
