-- DigitalMax LMS v12 upgrade — smart video progress (resume playback)
-- (Sirf tab jab v11 ya purana version pehle se installed ho.)

CREATE TABLE IF NOT EXISTS video_progress (
  student_id INT NOT NULL,
  lecture_id INT NOT NULL,
  seconds INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (student_id, lecture_id),
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (lecture_id) REFERENCES lectures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
