-- Scholarship Calculation System Database Schema
-- UTF-8 encoding, for Latvian vocational school

CREATE TABLE IF NOT EXISTS subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    type ENUM('VIMP', 'PROF') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    personal_code VARCHAR(20) NOT NULL UNIQUE,
    group_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    subject_id INT,
    subject_name VARCHAR(255),
    grade_type VARCHAR(100) NOT NULL,
    grade_value DECIMAL(5,2),
    is_insufficient TINYINT(1) DEFAULT 0,
    grade_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    INDEX idx_student_subject (student_id, subject_id),
    INDEX idx_grade_date (grade_date),
    INDEX idx_insufficient (is_insufficient)
);

CREATE TABLE IF NOT EXISTS scholarship_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    monthly_budget DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_period (period_start, period_end)
);

CREATE TABLE IF NOT EXISTS scholarship_brackets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_id INT NOT NULL,
    range_from DECIMAL(3,1) NOT NULL,
    range_to DECIMAL(3,1) NOT NULL,
    amount DECIMAL(8,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (config_id) REFERENCES scholarship_config(id) ON DELETE CASCADE,
    INDEX idx_config_range (config_id, range_from, range_to),
    UNIQUE KEY uk_config_range (config_id, range_from, range_to)
);

CREATE TABLE IF NOT EXISTS scholarship_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_id INT NOT NULL,
    student_id INT NOT NULL,
    average_grade DECIMAL(4,2),
    scholarship_amount DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    scholarship_reason VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (config_id) REFERENCES scholarship_config(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_config_student (config_id, student_id),
    UNIQUE KEY uk_config_student (config_id, student_id)
);

CREATE TABLE IF NOT EXISTS excluded_grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (config_id) REFERENCES scholarship_config(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    INDEX idx_config_student_subject (config_id, student_id, subject_id),
    UNIQUE KEY uk_config_student_subject (config_id, student_id, subject_id)
);