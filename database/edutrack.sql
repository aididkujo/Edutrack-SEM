-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 28, 2025 at 04:47 PM
-- Server version: 8.0.30
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `edutrack`
--

-- --------------------------------------------------------

--
-- Table structure for table `assessment`
--

CREATE TABLE `assessment` (
  `assessmentID` int NOT NULL,
  `tittle` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `createdDate` date NOT NULL,
  `openAt` datetime DEFAULT NULL,
  `closeAt` datetime DEFAULT NULL,
  `durationMinutes` int DEFAULT NULL,
  `dueDate` date NOT NULL,
  `courseID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendanceID` int NOT NULL,
  `slotID` int DEFAULT NULL,
  `sessionDate` date NOT NULL,
  `status` enum('Present','Absent','Late','Excused') NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `checkedInAt` datetime DEFAULT NULL,
  `courseID` int NOT NULL,
  `userID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_slot`
--

CREATE TABLE `attendance_slot` (
  `slotID` int NOT NULL,
  `courseID` int NOT NULL,
  `slotDate` date NOT NULL,
  `slotTime` time NOT NULL,
  `code` varchar(20) NOT NULL,
  `createdBy` int NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course`
--

CREATE TABLE `course` (
  `courseID` int NOT NULL,
  `courseName` varchar(255) NOT NULL,
  `userID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `course`
--

INSERT INTO `course` (`courseID`, `courseName`, `userID`) VALUES
(1, 'BCN1053 Data Communication and Networking', 4),
(2, 'BCI2353 Algorithm and Complexity', 5),
(3, 'BCN3243 Cloud Computing Technology', 6);

-- --------------------------------------------------------

--
-- Table structure for table `enrollment`
--

CREATE TABLE `enrollment` (
  `enrollmentID` int NOT NULL,
  `courseID` int NOT NULL,
  `userID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `enrollment`
--

INSERT INTO `enrollment` (`enrollmentID`, `courseID`, `userID`) VALUES
(1, 1, 7),
(2, 2, 7),
(3, 3, 7),
(4, 1, 8),
(5, 2, 8),
(6, 3, 8),
(7, 1, 9),
(8, 2, 9),
(9, 3, 9),
(10, 1, 10),
(11, 2, 10),
(12, 3, 10),
(13, 1, 11),
(14, 2, 11),
(15, 3, 11),
(16, 1, 12),
(17, 2, 12),
(18, 3, 12),
(19, 1, 13),
(20, 2, 13),
(21, 3, 13);

-- --------------------------------------------------------

--
-- Table structure for table `evaluation`
--

CREATE TABLE `evaluation` (
  `evaluationID` int NOT NULL,
  `evaluatorID` int NOT NULL COMMENT 'User who gives evaluation',
  `evaluateeID` int NOT NULL COMMENT 'User who is evaluated',
  `courseID` int NOT NULL COMMENT 'Course related to evaluation',
  `evaluationType` enum('student_to_lecturer','lecturer_to_student') NOT NULL,
  `q1_rating` int NOT NULL,
  `q2_rating` int NOT NULL,
  `q3_rating` int NOT NULL,
  `q4_rating` int NOT NULL,
  `q5_rating` int NOT NULL,
  `comments` text,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `notes`
--

CREATE TABLE `notes` (
  `noteID` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `filePath` varchar(255) NOT NULL,
  `uploadedDate` date NOT NULL,
  `courseID` int NOT NULL,
  `lecturerID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `passwordresetsID` int NOT NULL,
  `userID` int NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `progress_summary`
--

CREATE TABLE `progress_summary` (
  `summaryID` int NOT NULL,
  `total_average` decimal(5,2) NOT NULL DEFAULT '0.00',
  `attendance_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `courseID` int NOT NULL,
  `userID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `progress_summary`
--

INSERT INTO `progress_summary` (`summaryID`, `total_average`, `attendance_rate`, `last_updated`, `courseID`, `userID`) VALUES
(1, '0.00', '0.00', '2025-12-15 03:41:56', 1, 7),
(2, '0.00', '0.00', '2025-12-15 03:41:56', 2, 7),
(3, '0.00', '0.00', '2025-12-15 03:41:56', 3, 7);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_answer`
--

CREATE TABLE `quiz_answer` (
  `answerID` int NOT NULL,
  `attemptID` int NOT NULL,
  `questionID` int NOT NULL,
  `selectedOptionID` int DEFAULT NULL,
  `answerText` text,
  `score` decimal(6,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempt`
--

CREATE TABLE `quiz_attempt` (
  `attemptID` int NOT NULL,
  `assessmentID` int NOT NULL,
  `userID` int NOT NULL,
  `startedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submittedAt` datetime DEFAULT NULL,
  `totalScore` decimal(6,2) NOT NULL DEFAULT '0.00',
  `status` enum('in_progress','submitted','graded') NOT NULL DEFAULT 'in_progress'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_option`
--

CREATE TABLE `quiz_option` (
  `optionID` int NOT NULL,
  `questionID` int NOT NULL,
  `optionText` varchar(255) NOT NULL,
  `isCorrect` tinyint(1) NOT NULL DEFAULT '0',
  `optionOrder` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_question`
--

CREATE TABLE `quiz_question` (
  `questionID` int NOT NULL,
  `assessmentID` int NOT NULL,
  `questionType` enum('objective','subjective') NOT NULL,
  `questionText` text NOT NULL,
  `imagePath` varchar(255) DEFAULT NULL,
  `marks` decimal(5,2) NOT NULL DEFAULT '1.00',
  `questionOrder` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `submission`
--

CREATE TABLE `submission` (
  `submissionID` int NOT NULL,
  `submitDate` date NOT NULL,
  `filePath` varchar(255) DEFAULT NULL,
  `submissionText` text,
  `submittedAt` datetime DEFAULT NULL,
  `grade` decimal(5,2) NOT NULL,
  `assessmentID` int NOT NULL,
  `userID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `userID` int NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('student','lecturer','admin') NOT NULL,
  `status` enum('pending','active','deactivated','rejected') NOT NULL DEFAULT 'pending',
  `age` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `force_password_reset` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`userID`, `full_name`, `email`, `password_hash`, `role`, `status`, `age`, `approved_at`, `created_at`, `updated_at`, `last_login_at`, `deleted_at`, `force_password_reset`) VALUES
(1, 'Admin User', 'admin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', 30, '2025-12-14 15:13:27', '2025-12-14 15:13:27', '2025-12-14 15:13:27', NULL, NULL, 0),
(2, 'Student User', 'stu@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', 20, '2025-12-14 15:13:27', '2025-12-14 15:13:27', '2025-12-14 15:13:27', NULL, NULL, 0),
(3, 'Lecturer User', 'lect@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'lecturer', 'active', 35, '2025-12-14 15:13:27', '2025-12-14 15:13:27', '2025-12-14 15:13:27', NULL, NULL, 0),
(4, 'Ts. Dr. Hoh Wei Siang', 'bcn1053.lecturer@edutrack.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'lecturer', 'active', NULL, '2025-12-14 15:14:40', '2025-12-14 15:14:40', '2025-12-28 16:29:03', '2025-12-28 16:29:03', NULL, 0),
(5, 'Dr. Nur Syafiqah binti Mohd Nafis', 'bci2353.lecturer@edutrack.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'lecturer', 'active', NULL, '2025-12-14 15:14:40', '2025-12-14 15:14:40', '2025-12-14 15:18:29', '2025-12-14 15:18:29', NULL, 0),
(6, 'Ts. Dr. Abdullah Fairuzzullah', 'bcn3243.lecturer@edutrack.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'lecturer', 'active', NULL, '2025-12-14 15:14:40', '2025-12-14 15:14:40', '2025-12-14 15:14:40', NULL, NULL, 0),
(7, 'Umie Kalsum Ghazali', 'cb22045@student.edutrack.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NULL, '2025-12-14 15:15:44', '2025-12-14 15:15:44', '2025-12-28 16:43:18', '2025-12-28 16:43:18', NULL, 0),
(8, 'Azleen Farsiyaa', 'cb22136@student.edutrack.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NULL, '2025-12-14 15:15:44', '2025-12-14 15:15:44', '2025-12-14 15:15:44', NULL, NULL, 0),
(9, 'Bagaber Ali', 'cb22017@student.edutrack.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NULL, '2025-12-14 15:15:44', '2025-12-14 15:15:44', '2025-12-14 15:15:44', NULL, NULL, 0),
(10, 'Muhammad Yasrin', 'cb23102@student.edutrack.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NULL, '2025-12-14 15:15:44', '2025-12-14 15:15:44', '2025-12-14 15:15:44', NULL, NULL, 0),
(11, 'Ahmad Sayuti', 'cb23096@student.edutrack.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NULL, '2025-12-14 15:15:44', '2025-12-14 15:15:44', '2025-12-14 15:15:44', NULL, NULL, 0),
(12, 'Abdul Razzaq Aziz', 'cb22056@student.edutrack.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NULL, '2025-12-14 15:15:44', '2025-12-14 15:15:44', '2025-12-14 15:15:44', NULL, NULL, 0),
(13, 'Muhammad Syarifudin', 'cb22126@student.edutrack.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NULL, '2025-12-14 15:15:44', '2025-12-14 15:15:44', '2025-12-14 15:15:44', NULL, NULL, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assessment`
--
ALTER TABLE `assessment`
  ADD PRIMARY KEY (`assessmentID`),
  ADD KEY `courseID` (`courseID`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendanceID`),
  ADD UNIQUE KEY `uq_slot_user` (`slotID`,`userID`),
  ADD KEY `courseID` (`courseID`),
  ADD KEY `userID` (`userID`),
  ADD KEY `idx_slotID` (`slotID`);

--
-- Indexes for table `attendance_slot`
--
ALTER TABLE `attendance_slot`
  ADD PRIMARY KEY (`slotID`),
  ADD UNIQUE KEY `uq_course_datetime` (`courseID`,`slotDate`,`slotTime`),
  ADD KEY `idx_course_date` (`courseID`,`slotDate`),
  ADD KEY `idx_code` (`code`);

--
-- Indexes for table `course`
--
ALTER TABLE `course`
  ADD PRIMARY KEY (`courseID`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `enrollment`
--
ALTER TABLE `enrollment`
  ADD PRIMARY KEY (`enrollmentID`),
  ADD KEY `courseID` (`courseID`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `evaluation`
--
ALTER TABLE `evaluation`
  ADD PRIMARY KEY (`evaluationID`),
  ADD UNIQUE KEY `uq_eval_once` (`evaluatorID`,`evaluateeID`,`courseID`,`evaluationType`),
  ADD KEY `idx_eval_evaluator` (`evaluatorID`),
  ADD KEY `idx_eval_evaluatee` (`evaluateeID`),
  ADD KEY `idx_eval_course` (`courseID`),
  ADD KEY `idx_eval_type` (`evaluationType`),
  ADD KEY `idx_eval_created` (`createdAt`);

--
-- Indexes for table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`noteID`),
  ADD KEY `courseID` (`courseID`),
  ADD KEY `lecturerID` (`lecturerID`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`passwordresetsID`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `progress_summary`
--
ALTER TABLE `progress_summary`
  ADD PRIMARY KEY (`summaryID`),
  ADD KEY `courseID` (`courseID`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `quiz_answer`
--
ALTER TABLE `quiz_answer`
  ADD PRIMARY KEY (`answerID`),
  ADD KEY `attemptID` (`attemptID`),
  ADD KEY `questionID` (`questionID`),
  ADD KEY `selectedOptionID` (`selectedOptionID`);

--
-- Indexes for table `quiz_attempt`
--
ALTER TABLE `quiz_attempt`
  ADD PRIMARY KEY (`attemptID`),
  ADD KEY `assessmentID` (`assessmentID`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `quiz_option`
--
ALTER TABLE `quiz_option`
  ADD PRIMARY KEY (`optionID`),
  ADD KEY `questionID` (`questionID`);

--
-- Indexes for table `quiz_question`
--
ALTER TABLE `quiz_question`
  ADD PRIMARY KEY (`questionID`),
  ADD KEY `assessmentID` (`assessmentID`);

--
-- Indexes for table `submission`
--
ALTER TABLE `submission`
  ADD PRIMARY KEY (`submissionID`),
  ADD KEY `assessmentID` (`assessmentID`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`userID`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assessment`
--
ALTER TABLE `assessment`
  MODIFY `assessmentID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendanceID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `attendance_slot`
--
ALTER TABLE `attendance_slot`
  MODIFY `slotID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `enrollment`
--
ALTER TABLE `enrollment`
  MODIFY `enrollmentID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `evaluation`
--
ALTER TABLE `evaluation`
  MODIFY `evaluationID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `noteID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `quiz_answer`
--
ALTER TABLE `quiz_answer`
  MODIFY `answerID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `quiz_attempt`
--
ALTER TABLE `quiz_attempt`
  MODIFY `attemptID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quiz_option`
--
ALTER TABLE `quiz_option`
  MODIFY `optionID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_question`
--
ALTER TABLE `quiz_question`
  MODIFY `questionID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `evaluation`
--
ALTER TABLE `evaluation`
  ADD CONSTRAINT `fk_eval_course` FOREIGN KEY (`courseID`) REFERENCES `course` (`courseID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_eval_evaluatee` FOREIGN KEY (`evaluateeID`) REFERENCES `user` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_eval_evaluator` FOREIGN KEY (`evaluatorID`) REFERENCES `user` (`userID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
