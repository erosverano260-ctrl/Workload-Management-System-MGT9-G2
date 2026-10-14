-- Workload system database dump (database: workload_system)
-- Import first, then run instructors_migration.sql.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `workload_system`;
USE `workload_system`;

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(10) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `year_level` tinyint(4) NOT NULL,
  `units` tinyint(4) NOT NULL,
  `status` enum('Open','Closed') DEFAULT 'Open'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT INTO `courses` (`course_id`, `course_code`, `course_name`, `year_level`, `units`, `status`) VALUES
(2, 'EE301', 'Power System 1', 1, 3, 'Open'),
(3, 'IP123', 'Programming', 2, 3, 'Open'),
(4, 'ECE101', 'Fundamentals of Electrical Engineering', 1, 3, 'Open'),
(5, 'ECE102', 'Engineering Mathematics 1', 1, 3, 'Open'),
(6, 'ECE201', 'Circuit Analysis', 2, 3, 'Open'),
(7, 'ECE202', 'Digital Electronics', 2, 3, 'Open'),
(8, 'ECE301', 'Control Systems Engineering', 3, 3, 'Open'),
(9, 'ECE302', 'Microprocessor Systems', 3, 3, 'Open'),
(10, 'ECE401', 'Power Systems Analysis', 4, 3, 'Open'),
(11, 'ECE402', 'Engineering Design Project', 4, 3, 'Open');

CREATE TABLE `faculty` (
  `faculty_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `faculty_name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_code` varchar(100) NOT NULL,
  `room_name` varchar(50) NOT NULL,
  `capacity` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT INTO `rooms` (`room_id`, `room_code`, `room_name`, `capacity`) VALUES
(1, '103', 'ENGINEERING LABORATORY', 40),
(2, '101', 'Lab 2', 40),
(3, '', 'Room 303', 40),
(4, '', 'Room 303', 40),
(5, '999', 'LOL', 40);

CREATE TABLE `schedules` (
  `schedule_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `room_id` int(11) NOT NULL,
  `section` varchar(20) DEFAULT NULL,
  `day_of_week` varchar(10) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `cell_color` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT INTO `schedules` (`schedule_id`, `course_id`, `faculty_id`, `room_id`, `section`, `day_of_week`, `start_time`, `end_time`, `cell_color`) VALUES
(38, 2, NULL, 1, 'BSIT-1A', 'Monday', '07:00:00', '07:30:00', '#3ddb8c'),
(39, 2, NULL, 1, 'BSIT-1A', 'Monday', '07:30:00', '08:00:00', '#67e0a3'),
(40, 4, NULL, 3, 'BSIT-1A', 'Tuesday', '10:00:00', '12:00:00', '#67e0a3'),
(41, 3, NULL, 1, 'BSIT-2A', 'Wednesday', '08:00:00', '10:00:00', '#40e291');

ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_code` (`course_code`);
ALTER TABLE `faculty` ADD PRIMARY KEY (`faculty_id`);
ALTER TABLE `rooms` ADD PRIMARY KEY (`room_id`);
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `room_id` (`room_id`);

ALTER TABLE `courses`   MODIFY `course_id`   int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
ALTER TABLE `faculty`   MODIFY `faculty_id`  int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `rooms`     MODIFY `room_id`     int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `schedules` MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`),
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`),
  ADD CONSTRAINT `schedules_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`);
COMMIT;
