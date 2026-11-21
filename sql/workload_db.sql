-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 21, 2026 at 02:32 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `workload_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `full_name`, `password`, `created_at`) VALUES
(1, 'eros', 'erosgubalane', '$2y$10$JTVQavMinrB1ShluyVs/c.9sjkp2ubtMbVDZAjMyYoWopBXuM2EeS', '2026-11-01 10:40:18');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(10) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `year_level` tinyint(4) NOT NULL,
  `units` tinyint(4) NOT NULL,
  `status` enum('Open','Closed') DEFAULT 'Open'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_code`, `course_name`, `year_level`, `units`, `status`) VALUES
(3, 'IP123', 'Programming', 2, 2, 'Open'),
(6, 'ECE201', 'Circuit Analysis', 2, 1, 'Open'),
(7, 'ECE202', 'Digital Electronics', 2, 3, 'Open'),
(8, 'ECE301', 'Control Systems Engineering', 3, 3, 'Open'),
(9, 'ECE302', 'Microprocessor Systems', 3, 3, 'Open'),
(10, 'ECE401', 'Power Systems Analysis', 4, 3, 'Open'),
(11, 'ECE402', 'Engineering Design Project', 4, 3, 'Open'),
(12, 'TLE8', 'le', 2, 1, 'Open');

-- --------------------------------------------------------

--
-- Table structure for table `designations`
--

CREATE TABLE `designations` (
  `designation_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `hours` decimal(4,1) NOT NULL DEFAULT 0.0,
  `service_credit` decimal(4,1) NOT NULL DEFAULT 0.0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `designations`
--

INSERT INTO `designations` (`designation_id`, `name`, `hours`, `service_credit`) VALUES
(1, 'Dept. Chair', 0.0, 0.0),
(2, 'BSEE Adviser', 0.0, 0.0);

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `faculty_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `faculty_name` varchar(100) NOT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `available_days` varchar(30) DEFAULT NULL,
  `available_from` time DEFAULT NULL,
  `available_to` time DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `role` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `faculty`
--

INSERT INTO `faculty` (`faculty_id`, `username`, `faculty_name`, `specialty`, `available_days`, `available_from`, `available_to`, `password`, `must_change_password`, `role`) VALUES
(1, 'ramon.torres', 'Engr. Ramon Torres', 'Power Systems', 'Mon,Tue,Wed', '08:00:00', '12:00:00', '!', 0, 'instructor'),
(2, 'liza.fernandez', 'Engr. Liza Fernandez', 'Control Systems', 'Tue,Wed,Thu', '13:00:00', '17:00:00', '$2y$10$nb/RzEATXIOLZGWzI1ziu.obDNQGQKu3dLhWcRsmImPcASg0.zPuS', 0, 'instructor'),
(3, 'michael.ong', 'Engr. Michael Ong', 'Electronics', 'Mon,Wed,Fri', '09:00:00', '15:00:00', '!', 0, 'instructor');

-- --------------------------------------------------------

--
-- Table structure for table `faculty_designations`
--

CREATE TABLE `faculty_designations` (
  `fd_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `designation_id` int(11) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `faculty_designations`
--

INSERT INTO `faculty_designations` (`fd_id`, `faculty_id`, `designation_id`, `status`, `created_at`) VALUES
(2, 2, 2, 'Approved', '2026-11-21 13:29:27');

-- --------------------------------------------------------

--
-- Table structure for table `overload_sessions`
--

CREATE TABLE `overload_sessions` (
  `session_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `day_of_week` varchar(10) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `overload_sessions`
--

INSERT INTO `overload_sessions` (`session_id`, `faculty_id`, `day_of_week`, `start_time`, `end_time`, `status`, `created_at`) VALUES
(1, 2, 'Monday', '13:00:00', '14:00:00', 'Pending', '2026-11-21 13:01:57'),
(2, 2, 'Tuesday', '16:00:00', '17:00:00', 'Pending', '2026-11-21 13:02:13');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_code` varchar(100) NOT NULL,
  `room_name` varchar(50) NOT NULL,
  `capacity` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_code`, `room_name`, `capacity`) VALUES
(1, '103', 'ENGINEERING LABORATORY', 40),
(2, '101', 'Lab 2', 40),
(3, '303', 'Room 303', 40);

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `schedule_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `room_id` int(11) NOT NULL,
  `section` varchar(20) DEFAULT NULL,
  `class_type` enum('Lecture','Laboratory') NOT NULL DEFAULT 'Lecture',
  `day_of_week` varchar(10) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `cell_color` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`schedule_id`, `course_id`, `faculty_id`, `room_id`, `section`, `class_type`, `day_of_week`, `start_time`, `end_time`, `cell_color`) VALUES
(48, 12, 2, 1, 'BSEE-2A', 'Lecture', 'Monday', '08:30:00', '09:30:00', '#67e0a3'),
(49, 6, 2, 1, 'BSEE-2A', 'Lecture', 'Monday', '09:30:00', '10:30:00', '#67e0a3'),
(50, 12, 2, 1, 'BSEE-2A', 'Lecture', 'Tuesday', '07:30:00', '08:30:00', '#67e0a3'),
(51, 7, 2, 1, 'BSEE-2A', 'Laboratory', 'Tuesday', '08:30:00', '11:30:00', '#67e0a3'),
(52, 12, 2, 1, 'BSEE-2A', 'Laboratory', 'Wednesday', '10:30:00', '11:30:00', '#67e0a3'),
(53, 3, 2, 1, 'BSEE-2A', 'Lecture', 'Thursday', '08:00:00', '10:00:00', '#67e0a3'),
(54, 6, 2, 1, 'BSEE-2A', 'Lecture', 'Thursday', '10:00:00', '11:00:00', '#67e0a3'),
(55, 12, 2, 1, 'BSEE-2A', 'Lecture', 'Monday', '14:00:00', '15:00:00', '#67e0a3'),
(56, 6, 2, 1, 'BSEE-2A', 'Lecture', 'Monday', '15:00:00', '16:00:00', '#67e0a3'),
(57, 7, 2, 1, 'BSEE-2A', 'Laboratory', 'Monday', '16:00:00', '19:00:00', '#67e0a3'),
(58, 7, 2, 1, 'BSEE-2A', 'Lecture', 'Tuesday', '17:30:00', '19:00:00', '#67e0a3'),
(59, 6, 2, 1, 'BSEE-2A', 'Lecture', 'Tuesday', '15:00:00', '16:00:00', '#67e0a3'),
(60, 12, 2, 1, 'BSEE-2A', 'Lecture', 'Wednesday', '15:00:00', '16:00:00', '#67e0a3'),
(61, 12, 2, 1, 'BSEE-2A', 'Lecture', 'Thursday', '15:00:00', '16:00:00', '#67e0a3'),
(62, 3, 2, 1, 'BSEE-2A', 'Lecture', 'Wednesday', '13:00:00', '15:00:00', '#67e0a3'),
(63, 3, 2, 2, 'BSEE-2A', 'Lecture', 'Thursday', '13:00:00', '15:00:00', '#67e0a3'),
(64, 7, 2, 2, 'BSEE-2A', 'Lecture', 'Wednesday', '16:00:00', '19:00:00', '#67e0a3'),
(65, 7, 2, 1, 'BSEE-2A', 'Laboratory', 'Thursday', '16:00:00', '19:00:00', '#67e0a3'),
(66, 6, 2, 1, 'BSEE-2A', 'Laboratory', 'Monday', '07:30:00', '08:30:00', '#67e0a3');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `uq_admin_username` (`username`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_code` (`course_code`);

--
-- Indexes for table `designations`
--
ALTER TABLE `designations`
  ADD PRIMARY KEY (`designation_id`),
  ADD UNIQUE KEY `uq_designation_name` (`name`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`faculty_id`),
  ADD UNIQUE KEY `uq_faculty_username` (`username`);

--
-- Indexes for table `faculty_designations`
--
ALTER TABLE `faculty_designations`
  ADD PRIMARY KEY (`fd_id`),
  ADD UNIQUE KEY `uq_fac_des` (`faculty_id`,`designation_id`),
  ADD KEY `idx_fd_des` (`designation_id`);

--
-- Indexes for table `overload_sessions`
--
ALTER TABLE `overload_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_overload_faculty` (`faculty_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `room_id` (`room_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `designations`
--
ALTER TABLE `designations`
  MODIFY `designation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `faculty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `faculty_designations`
--
ALTER TABLE `faculty_designations`
  MODIFY `fd_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `overload_sessions`
--
ALTER TABLE `overload_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `faculty_designations`
--
ALTER TABLE `faculty_designations`
  ADD CONSTRAINT `fk_fd_designation` FOREIGN KEY (`designation_id`) REFERENCES `designations` (`designation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fd_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE;

--
-- Constraints for table `overload_sessions`
--
ALTER TABLE `overload_sessions`
  ADD CONSTRAINT `fk_overload_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`),
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`),
  ADD CONSTRAINT `schedules_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
