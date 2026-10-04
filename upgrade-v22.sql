-- DigitalMax LMS v22 upgrade: Announcements & Notice Board
-- Purani install par phpMyAdmin me chalana (ya system khud bana lega - self-healing)

CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  target_type ENUM('all_students','specific_course','specific_batch') NOT NULL DEFAULT 'all_students',
  course_id INT NULL,
  batch_id INT NULL,
  priority ENUM('normal','urgent','pinned') NOT NULL DEFAULT 'normal',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS announcement_reads (
  announcement_id INT NOT NULL,
  student_id INT NOT NULL,
  read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (announcement_id, student_id),
  FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
