-- Run once in phpMyAdmin (database: workload_system).
-- Adds the columns the editable Instructors page needs.
ALTER TABLE `faculty`
  ADD COLUMN `specialty`       varchar(100) DEFAULT NULL AFTER `faculty_name`,
  ADD COLUMN `available_days`  varchar(30)  DEFAULT NULL AFTER `specialty`,
  ADD COLUMN `available_from`  time         DEFAULT NULL AFTER `available_days`,
  ADD COLUMN `available_to`    time         DEFAULT NULL AFTER `available_from`;

-- Optional: starter instructors so the page isn't empty.
-- The password is a random unusable hash (these rows are for scheduling, not login).
INSERT INTO `faculty` (`username`, `faculty_name`, `specialty`, `available_days`, `available_from`, `available_to`, `password`, `role`) VALUES
('ramon.torres',  'Engr. Ramon Torres',  'Power Systems',   'Mon,Tue,Wed', '08:00:00', '12:00:00', '!', 'instructor'),
('liza.fernandez','Engr. Liza Fernandez','Control Systems', 'Tue,Wed,Thu', '13:00:00', '17:00:00', '!', 'instructor'),
('michael.ong',   'Engr. Michael Ong',   'Electronics',     'Mon,Wed,Fri', '09:00:00', '15:00:00', '!', 'instructor');

-- Optional cleanup of test rows in the current dump (empty room codes, duplicate "Room 303", "LOL").
-- Room 3 is used by schedule 40, so only the unused ones are removed.
DELETE FROM `rooms` WHERE `room_id` IN (4, 5);
UPDATE `rooms` SET `room_code` = '303' WHERE `room_id` = 3 AND `room_code` = '';
