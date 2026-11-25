-- OPTIONAL: the app creates this table by itself the first time a notification is sent or read.
-- Run it in phpMyAdmin (database: workload_system) only if you prefer to create it by hand.
CREATE TABLE IF NOT EXISTS `notifications` (
  `notif_id`   int(11)      NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11)      NOT NULL,
  `kind`       varchar(20)  NOT NULL DEFAULT 'info',     -- approved | rejected | info
  `title`      varchar(150) NOT NULL,
  `body`       varchar(500) NOT NULL DEFAULT '',
  `link`       varchar(100) NOT NULL DEFAULT '',
  `is_read`    tinyint(1)   NOT NULL DEFAULT 0,
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notif_id`),
  KEY `idx_faculty_read` (`faculty_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
