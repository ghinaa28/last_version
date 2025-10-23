-- SQL script to add missing academic fields to the students table
-- Run this script if you have an existing database without these columns

-- Add student_id_number column
ALTER TABLE students ADD COLUMN IF NOT EXISTS student_id_number VARCHAR(50) AFTER phone;

-- Add academic_year column
ALTER TABLE students ADD COLUMN IF NOT EXISTS academic_year VARCHAR(50) AFTER student_id_number;

-- Add gpa column
ALTER TABLE students ADD COLUMN IF NOT EXISTS gpa DECIMAL(3,2) AFTER academic_year;

-- Add expected_graduation column
ALTER TABLE students ADD COLUMN IF NOT EXISTS expected_graduation VARCHAR(7) AFTER gpa;

-- Add date_of_birth column (if it doesn't exist)
ALTER TABLE students ADD COLUMN IF NOT EXISTS date_of_birth DATE AFTER expected_graduation;

-- Add training interests and preferences columns
ALTER TABLE students ADD COLUMN IF NOT EXISTS training_interests TEXT AFTER date_of_birth;
ALTER TABLE students ADD COLUMN IF NOT EXISTS preferred_fields TEXT AFTER training_interests;
ALTER TABLE students ADD COLUMN IF NOT EXISTS skills TEXT AFTER preferred_fields;
ALTER TABLE students ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(500) AFTER skills;

-- Update any existing records to have default values for required fields
UPDATE students SET 
    student_id_number = CONCAT('STU', student_id) 
WHERE student_id_number IS NULL OR student_id_number = '';

UPDATE students SET 
    academic_year = 'Not specified' 
WHERE academic_year IS NULL OR academic_year = '';

-- Ensure internship_applications table has the correct structure
-- (This is just for reference - the table should already exist)
-- CREATE TABLE IF NOT EXISTS internship_applications (
--     application_id INT AUTO_INCREMENT PRIMARY KEY,
--     internship_id INT NOT NULL,
--     student_id INT NOT NULL,
--     cover_letter TEXT,
--     motivation TEXT,
--     relevant_experience TEXT,
--     why_this_company TEXT,
--     career_goals TEXT,
--     additional_info TEXT,
--     application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     status ENUM('pending', 'reviewed', 'shortlisted', 'interviewed', 'accepted', 'rejected') DEFAULT 'pending',
--     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--     FOREIGN KEY (internship_id) REFERENCES internships(internship_id) ON DELETE CASCADE,
--     FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
-- );

-- Display the updated table structure
DESCRIBE students;
