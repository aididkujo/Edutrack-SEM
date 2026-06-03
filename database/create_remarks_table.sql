CREATE TABLE IF NOT EXISTS `student_remarks` (
  `remarkID` int NOT NULL AUTO_INCREMENT,
  `courseID` int NOT NULL,
  `studentID` int NOT NULL,
  `lecturerID` int NOT NULL,
  `remark` text NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`remarkID`),
  FOREIGN KEY (`courseID`) REFERENCES `course`(`courseID`) ON DELETE CASCADE,
  FOREIGN KEY (`studentID`) REFERENCES `user`(`userID`) ON DELETE CASCADE,
  FOREIGN KEY (`lecturerID`) REFERENCES `user`(`userID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;