-- OPTIONAL. The app creates this table by itself the first time it is needed.
-- Run it only if you prefer to create it yourself in phpMyAdmin (database: workload_system).
CREATE TABLE IF NOT EXISTS `password_requests` (
  `request_id`   int(11)   NOT NULL AUTO_INCREMENT,
  `faculty_id`   int(11)   NOT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `handled`      tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`request_id`),
  KEY `idx_pr_faculty` (`faculty_id`),
  CONSTRAINT `fk_pr_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
