  CREATE DATABASE IF NOT EXISTS internhub_nova CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE internhub_nova;

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED PRIMARY KEY,
  name VARCHAR(32) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS coordinators (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_login TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS classes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course VARCHAR(150) NOT NULL,
  sigla VARCHAR(50) NOT NULL,
  year INT,
  coordinator_id INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_classes_coordinator FOREIGN KEY (coordinator_id)
    REFERENCES coordinators(id) ON DELETE RESTRICT,
  UNIQUE KEY ux_class_sigla_year (sigla, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  class_id INT NOT NULL,
  first_login TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_students_class (class_id),
  CONSTRAINT fk_students_class FOREIGN KEY (class_id)
    REFERENCES classes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS companies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  address VARCHAR(400),
  email VARCHAR(255),
  phone VARCHAR(50),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS supervisors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  company_id INT UNSIGNED NOT NULL,
  first_login TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_supervisors_company (company_id),
  CONSTRAINT fk_supervisors_company FOREIGN KEY (company_id)
    REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS internships (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  title VARCHAR(200),
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  total_hours_required INT NOT NULL,
  min_hours_day DECIMAL(4,1) DEFAULT 6,
  lunch_break_minutes INT DEFAULT 60,
  status ENUM('active','completed') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_internship_company FOREIGN KEY (company_id)
    REFERENCES companies(id) ON DELETE RESTRICT,
  INDEX idx_internship_company (company_id),
  INDEX idx_internship_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_internships (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  internship_id INT UNSIGNED NOT NULL,
  assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_student_one_internship (student_id),
  CONSTRAINT fk_si_student FOREIGN KEY (student_id)
  REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_si_internship FOREIGN KEY (internship_id)
  REFERENCES internships(id) ON DELETE RESTRICT,
  INDEX idx_si_internship (internship_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS supervisor_internships (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supervisor_id INT UNSIGNED NOT NULL,
  internship_id INT UNSIGNED NOT NULL,
  assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_supervisor_one_internship (supervisor_id),
  CONSTRAINT fk_sii_supervisor FOREIGN KEY (supervisor_id) REFERENCES supervisors(id) ON DELETE CASCADE,
  CONSTRAINT fk_sii_internship FOREIGN KEY (internship_id) REFERENCES internships(id) ON DELETE RESTRICT,
  INDEX idx_sii_internship (internship_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hours (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  internship_id INT UNSIGNED NOT NULL,
  date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  duration_hours DECIMAL(4,1) NOT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  supervisor_reviewed_by INT UNSIGNED,
  supervisor_comment VARCHAR(1000),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME,
  CONSTRAINT fk_hours_student FOREIGN KEY (student_id)
  REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_hours_internship FOREIGN KEY (internship_id)
  REFERENCES internships(id) ON DELETE RESTRICT,
  CONSTRAINT fk_hours_supervisor FOREIGN KEY (supervisor_reviewed_by)
  REFERENCES supervisors(id) ON DELETE SET NULL,
  INDEX idx_hours_student_date (student_id, date),
  INDEX idx_hours_internship_status (internship_id, status),
  INDEX idx_hours_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS conversations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user1_role ENUM('student','supervisor','coordinator','admin') NOT NULL,
  user1_id INT UNSIGNED NOT NULL,
  user2_role ENUM('student','supervisor','coordinator','admin') NOT NULL,
  user2_id INT UNSIGNED NOT NULL,
  convo_key VARCHAR(100) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_convo_key (convo_key),
  INDEX idx_convo_users (user1_role, user1_id, user2_role, user2_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT NOT NULL,
  sender_role ENUM('student','supervisor','coordinator','admin') NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME,
  CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  INDEX idx_messages_conversation (conversation_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(255),
    file_path VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  user_role ENUM('student','supervisor','coordinator','admin') NOT NULL,
  reset_code VARCHAR(6) NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  is_used TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  INDEX idx_token_user (user_role, user_id),
  INDEX idx_token_code (reset_code),
  INDEX idx_token_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


DELIMITER $$

CREATE TRIGGER trg_hours_before_insert
BEFORE INSERT ON hours
FOR EACH ROW
BEGIN
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
END$$

CREATE TRIGGER trg_hours_before_update
BEFORE UPDATE ON hours
FOR EACH ROW
BEGIN
  IF OLD.status = 'approved' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Approved entries cannot be edited';
  END IF;
END$$

CREATE TRIGGER trg_conversations_before_insert
BEFORE INSERT ON conversations
FOR EACH ROW
BEGIN
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
    SET NEW.user1_id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(key_final,'|',1),':',-1) AS UNSIGNED);
    SET NEW.user2_role = SUBSTRING_INDEX(key_final,'|',-1);
    SET NEW.user2_id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(key_final,'|',-1),':',-1) AS UNSIGNED);
  END IF;
END$$

DELIMITER ;
