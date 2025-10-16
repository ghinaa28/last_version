-- Database tables for Instructor Service
-- Run this SQL to create the necessary tables for the instructor service

-- Table for instructor requests posted by companies
CREATE TABLE IF NOT EXISTS instructor_requests (
    instructor_request_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    course_title VARCHAR(255) NOT NULL,
    course_description TEXT NOT NULL,
    required_qualifications TEXT NOT NULL,
    skills_required TEXT NOT NULL,
    course_duration VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    is_online BOOLEAN DEFAULT FALSE,
    compensation_type ENUM('hourly', 'salary', 'project', 'negotiable') NOT NULL,
    compensation_amount DECIMAL(10,2) NOT NULL,
    application_deadline DATE NOT NULL,
    max_applications INT DEFAULT NULL,
    course_type ENUM('technical', 'business', 'language', 'soft_skills', 'certification', 'workshop', 'seminar', 'other') NOT NULL,
    experience_level ENUM('beginner', 'intermediate', 'advanced', 'expert') NOT NULL,
    status ENUM('active', 'closed', 'filled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    INDEX idx_company_id (company_id),
    INDEX idx_status (status),
    INDEX idx_course_type (course_type),
    INDEX idx_experience_level (experience_level),
    INDEX idx_application_deadline (application_deadline)
);

-- Table for instructor applications
CREATE TABLE IF NOT EXISTS instructor_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    instructor_request_id INT NOT NULL,
    instructor_id INT NOT NULL,
    motivation_message TEXT NOT NULL,
    relevant_experience TEXT NOT NULL,
    availability TEXT NOT NULL,
    additional_info TEXT DEFAULT NULL,
    cv_path VARCHAR(500) DEFAULT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    review_notes TEXT DEFAULT NULL,
    FOREIGN KEY (instructor_request_id) REFERENCES instructor_requests(instructor_request_id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES instructors(instructor_id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (instructor_request_id, instructor_id),
    INDEX idx_instructor_request_id (instructor_request_id),
    INDEX idx_instructor_id (instructor_id),
    INDEX idx_status (status),
    INDEX idx_applied_at (applied_at)
);

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_instructor_requests_company_status ON instructor_requests(company_id, status);
CREATE INDEX IF NOT EXISTS idx_instructor_requests_deadline_status ON instructor_requests(application_deadline, status);
CREATE INDEX IF NOT EXISTS idx_instructor_applications_request_status ON instructor_applications(instructor_request_id, status);

-- Insert sample data (optional - for testing)
-- INSERT INTO instructor_requests (company_id, course_title, course_description, required_qualifications, skills_required, course_duration, location, compensation_type, compensation_amount, application_deadline, course_type, experience_level) VALUES
-- (1, 'Advanced React Development', 'Learn advanced React concepts including hooks, context, and performance optimization', 'Bachelor degree in Computer Science or equivalent experience', 'React, JavaScript, HTML, CSS, Git', '8 weeks', 'New York, NY', 'hourly', 75.00, '2024-02-15', 'technical', 'advanced'),
-- (1, 'Business Communication Skills', 'Improve professional communication and presentation skills', 'Teaching experience preferred', 'Communication, Presentation, Public Speaking', '6 weeks', 'Online', 'salary', 3000.00, '2024-02-20', 'soft_skills', 'intermediate');
