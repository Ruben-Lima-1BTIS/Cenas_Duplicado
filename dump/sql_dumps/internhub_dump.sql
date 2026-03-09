-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 07:36 PM
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
-- Database: `internhub_nova`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `course` varchar(150) NOT NULL,
  `sigla` varchar(50) NOT NULL,
  `year` int(11) DEFAULT NULL,
  `coordinator_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `course`, `sigla`, `year`, `coordinator_id`, `created_at`) VALUES
(1, 'Técnico de Informática - Sistemas', '1ºATIS', 1, 2, '2025-11-17 17:20:02'),
(2, 'Técnico de Informática - Sistemas', '1ºBTIS', 1, 2, '2025-11-17 17:27:24'),
(3, 'Técnico de Informática - Sistemas', '2ºATIS', 2, 3, '2025-11-17 17:27:33'),
(4, 'Técnico de Informática - Sistemas', '2ºBTIS', 2, 3, '2025-11-17 17:27:43'),
(5, 'Técnico de Informática - Sistemas', '3ºATIS', 3, 1, '2025-11-17 17:27:57'),
(6, 'Técnico de Informática - Sistemas', '3ºBTIS', 3, 1, '2025-11-17 17:29:54');

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `address` varchar(400) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `name`, `address`, `email`, `phone`, `created_at`) VALUES
(1, 'Proside', 'Amadora, Lisboa', 'paulo.alves@proside.pt', '910000000', '2025-11-17 17:36:03');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` bigint(20) NOT NULL,
  `user1_role` enum('student','supervisor','coordinator','admin') NOT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_role` enum('student','supervisor','coordinator','admin') NOT NULL,
  `user2_id` int(11) NOT NULL,
  `convo_key` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `conversations`
--
DELIMITER $$
CREATE TRIGGER `trg_conversations_before_insert` BEFORE INSERT ON `conversations` FOR EACH ROW BEGIN
  DECLARE a_key VARCHAR(60);
  DECLARE b_key VARCHAR(60);
  DECLARE key_final VARCHAR(120);

  SET a_key = CONCAT(NEW.user1_role, ':', NEW.user1_id);
  SET b_key = CONCAT(NEW.user2_role, ':', NEW.user2_id);

  IF a_key <= b_key THEN
    SET NEW.convo_key = CONCAT(a_key,'|',b_key);
  ELSE
    SET key_final = CONCAT(b_key,'|',a_key);
    SET NEW.convo_key = key_final;
    SET NEW.user1_role = SUBSTRING_INDEX(key_final,'|',1);
    SET NEW.user1_id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(key_final,'|',1),':',-1) AS INT);
    SET NEW.user2_role = SUBSTRING_INDEX(key_final,'|',-1);
    SET NEW.user2_id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(key_final,'|',-1),':',-1) AS INT);
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `coordinators`
--

CREATE TABLE `coordinators` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_login` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coordinators`
--

INSERT INTO `coordinators` (`id`, `name`, `email`, `password_hash`, `first_login`, `created_at`) VALUES
(1, 'Dr. Marco', 'marco@gmail.com', '$2y$10$Vcy/iIPJNHpyGcBpA/DlvO1xve1/dK7RttlrgcvWluphKZmBJFxKe', 1, '2025-11-17 17:19:02'),
(2, 'Dr. José', 'jose@gmail.com', '$2y$10$PgO1Yb2cGDF0yctD6dCdhuGn8dL2/P0YOp/boMapkwI.4QW4EFcEy', 1, '2025-11-17 17:19:32'),
(3, 'Dr. Claudia', 'claudia@gmail.com', '$2y$10$7Qm7sG5S89BS.zliMdq9cOzjGORDZVIm7SAbwsdigOZvQrK8Khlcq', 1, '2025-11-17 17:19:52');

-- --------------------------------------------------------

--
-- Table structure for table `hours`
--

CREATE TABLE `hours` (
  `id` bigint(20) NOT NULL,
  `student_id` int(11) NOT NULL,
  `internship_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration_hours` decimal(4,1) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `supervisor_reviewed_by` int(11) DEFAULT NULL,
  `supervisor_comment` varchar(1000) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `hours`
--
DELIMITER $$
CREATE TRIGGER `trg_hours_before_insert` BEFORE INSERT ON `hours` FOR EACH ROW BEGIN
  DECLARE assigned_count INT DEFAULT 0;
  DECLARE intern_start DATE;
  DECLARE intern_end DATE;
  DECLARE lunch_min INT DEFAULT 60;
  DECLARE raw_minutes INT;
  DECLARE duration_hr DECIMAL(5,2);

  SELECT COUNT(*) INTO assigned_count
  FROM student_internships
  WHERE student_id = NEW.student_id AND internship_id = NEW.internship_id;

  IF assigned_count = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Student is not assigned to that internship';
  END IF;

  SELECT start_date, end_date, lunch_break_minutes INTO intern_start, intern_end, lunch_min
  FROM internships WHERE id = NEW.internship_id LIMIT 1;

  IF NEW.date < intern_start OR NEW.date > intern_end THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Log date is outside internship dates';
  END IF;

  IF NEW.date > CURDATE() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot log future dates';
  END IF;

  IF WEEKDAY(NEW.date) IN (5,6) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot log on weekends';
  END IF;

  IF NEW.start_time >= NEW.end_time THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'start_time must be before end_time';
  END IF;

  SET raw_minutes = TIME_TO_SEC(NEW.end_time)/60 - TIME_TO_SEC(NEW.start_time)/60;
  SET duration_hr = ROUND((raw_minutes - lunch_min)/60 * 2)/2;

  IF duration_hr <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duration after lunch must be positive';
  END IF;

  SET NEW.duration_hours = duration_hr;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_hours_before_update` BEFORE UPDATE ON `hours` FOR EACH ROW BEGIN
  IF OLD.status = 'approved' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Approved entries cannot be edited';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `internships`
--

CREATE TABLE `internships` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_hours_required` int(11) NOT NULL,
  `min_hours_day` decimal(4,1) DEFAULT 6.0,
  `lunch_break_minutes` int(11) DEFAULT 60,
  `status` enum('active','completed') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `internships`
--

INSERT INTO `internships` (`id`, `company_id`, `title`, `start_date`, `end_date`, `total_hours_required`, `min_hours_day`, `lunch_break_minutes`, `status`, `created_at`) VALUES
(1, 1, 'Estágios Proside 2023/2026', '2025-12-15', '2026-04-28', 650, 7.0, 60, 'active', '2025-11-17 17:43:37');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` bigint(20) NOT NULL,
  `conversation_id` bigint(20) NOT NULL,
  `sender_role` enum('student','supervisor','coordinator','admin') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `class_id` int(11) NOT NULL,
  `first_login` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `name`, `email`, `password_hash`, `class_id`, `first_login`, `created_at`) VALUES
(1, 'Ruben Lima', '5488@eclisboa.net', '$2y$10$oOFBOyLmFbm5w5hlBoa0ce2pnLxe1aQUSyW9wTz.2RFrcG4KoTsE6', 6, 1, '2025-11-17 17:44:52');

-- --------------------------------------------------------

--
-- Table structure for table `student_internships`
--

CREATE TABLE `student_internships` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `internship_id` int(11) NOT NULL,
  `assigned_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_internships`
--

INSERT INTO `student_internships` (`id`, `student_id`, `internship_id`, `assigned_at`) VALUES
(1, 1, 1, '2025-11-17 17:44:52');

-- --------------------------------------------------------

--
-- Table structure for table `supervisors`
--

CREATE TABLE `supervisors` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `company_id` int(11) NOT NULL,
  `first_login` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisors`
--

INSERT INTO `supervisors` (`id`, `name`, `email`, `password_hash`, `company_id`, `first_login`, `created_at`) VALUES
(1, 'Mariana Tavares', 'mariana.tavares@proside.pt', '$2y$10$ctzL/CMeYdFosVEy3oRc1epDs9icKQBJZKU6zKEIasqSZbCONEEje', 1, 1, '2025-11-17 17:44:18');

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_internships`
--

CREATE TABLE `supervisor_internships` (
  `id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `internship_id` int(11) NOT NULL,
  `assigned_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor_internships`
--

INSERT INTO `supervisor_internships` (`id`, `supervisor_id`, `internship_id`, `assigned_at`) VALUES
(1, 1, 1, '2025-11-17 17:44:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_class_sigla_year` (`sigla`,`year`),
  ADD KEY `fk_classes_coordinator` (`coordinator_id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_convo_key` (`convo_key`),
  ADD KEY `idx_convo_users` (`user1_role`,`user1_id`,`user2_role`,`user2_id`);

--
-- Indexes for table `coordinators`
--
ALTER TABLE `coordinators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `hours`
--
ALTER TABLE `hours`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_hours_supervisor` (`supervisor_reviewed_by`),
  ADD KEY `idx_hours_student_date` (`student_id`,`date`),
  ADD KEY `idx_hours_internship_status` (`internship_id`,`status`),
  ADD KEY `idx_hours_date` (`date`);

--
-- Indexes for table `internships`
--
ALTER TABLE `internships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_internship_company` (`company_id`),
  ADD KEY `idx_internship_dates` (`start_date`,`end_date`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_messages_conversation` (`conversation_id`,`created_at`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_students_class` (`class_id`);

--
-- Indexes for table `student_internships`
--
ALTER TABLE `student_internships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_student_one_internship` (`student_id`),
  ADD KEY `idx_si_internship` (`internship_id`);

--
-- Indexes for table `supervisors`
--
ALTER TABLE `supervisors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_supervisors_company` (`company_id`);

--
-- Indexes for table `supervisor_internships`
--
ALTER TABLE `supervisor_internships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_supervisor_one_internship` (`supervisor_id`),
  ADD KEY `idx_sii_internship` (`internship_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coordinators`
--
ALTER TABLE `coordinators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hours`
--
ALTER TABLE `hours`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `internships`
--
ALTER TABLE `internships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_internships`
--
ALTER TABLE `student_internships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `supervisors`
--
ALTER TABLE `supervisors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `supervisor_internships`
--
ALTER TABLE `supervisor_internships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `fk_classes_coordinator` FOREIGN KEY (`coordinator_id`) REFERENCES `coordinators` (`id`);

--
-- Constraints for table `hours`
--
ALTER TABLE `hours`
  ADD CONSTRAINT `fk_hours_internship` FOREIGN KEY (`internship_id`) REFERENCES `internships` (`id`),
  ADD CONSTRAINT `fk_hours_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hours_supervisor` FOREIGN KEY (`supervisor_reviewed_by`) REFERENCES `supervisors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `internships`
--
ALTER TABLE `internships`
  ADD CONSTRAINT `fk_internship_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`);

--
-- Constraints for table `student_internships`
--
ALTER TABLE `student_internships`
  ADD CONSTRAINT `fk_si_internship` FOREIGN KEY (`internship_id`) REFERENCES `internships` (`id`),
  ADD CONSTRAINT `fk_si_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supervisors`
--
ALTER TABLE `supervisors`
  ADD CONSTRAINT `fk_supervisors_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

--
-- Constraints for table `supervisor_internships`
--
ALTER TABLE `supervisor_internships`
  ADD CONSTRAINT `fk_sii_internship` FOREIGN KEY (`internship_id`) REFERENCES `internships` (`id`),
  ADD CONSTRAINT `fk_sii_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `supervisors` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
