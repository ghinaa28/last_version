<?php
require_once 'connection.php';

echo "<h2>Creating Course Management Tables</h2>";

// Create courses table for individual instructor courses
$sql1 = "CREATE TABLE IF NOT EXISTS courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    instructor_id INT NOT NULL,
    course_title VARCHAR(255) NOT NULL,
    course_description TEXT NOT NULL,
    course_category ENUM('technical', 'business', 'language', 'soft_skills', 'certification', 'workshop', 'seminar', 'other') NOT NULL,
    course_level ENUM('beginner', 'intermediate', 'advanced', 'expert') NOT NULL,
    course_duration VARCHAR(100) NOT NULL,
    course_price DECIMAL(10,2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'USD',
    is_online BOOLEAN DEFAULT TRUE,
    location VARCHAR(255) DEFAULT NULL,
    max_students INT DEFAULT NULL,
    course_image VARCHAR(500) DEFAULT NULL,
    course_materials TEXT DEFAULT NULL,
    prerequisites TEXT DEFAULT NULL,
    learning_outcomes TEXT DEFAULT NULL,
    course_schedule TEXT DEFAULT NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES instructors(instructor_id) ON DELETE CASCADE,
    INDEX idx_instructor_id (instructor_id),
    INDEX idx_status (status),
    INDEX idx_category (course_category),
    INDEX idx_level (course_level),
    INDEX idx_featured (is_featured)
)";

if ($conn->query($sql1)) {
    echo "<p style='color: green;'>✓ courses table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating courses table: " . $conn->error . "</p>";
}

// Create course_enrollments table for student enrollments
$sql2 = "CREATE TABLE IF NOT EXISTS course_enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('enrolled', 'completed', 'dropped') DEFAULT 'enrolled',
    completion_date TIMESTAMP NULL DEFAULT NULL,
    certificate_issued BOOLEAN DEFAULT FALSE,
    certificate_path VARCHAR(500) DEFAULT NULL,
    progress_percentage INT DEFAULT 0,
    last_accessed TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (course_id, student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_student_id (student_id),
    INDEX idx_status (status)
)";

if ($conn->query($sql2)) {
    echo "<p style='color: green;'>✓ course_enrollments table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating course_enrollments table: " . $conn->error . "</p>";
}

// Create course_reviews table for student reviews
$sql3 = "CREATE TABLE IF NOT EXISTS course_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT DEFAULT NULL,
    content_quality INT NOT NULL CHECK (content_quality >= 1 AND content_quality <= 5),
    instructor_effectiveness INT NOT NULL CHECK (instructor_effectiveness >= 1 AND instructor_effectiveness <= 5),
    course_structure INT NOT NULL CHECK (course_structure >= 1 AND course_structure <= 5),
    would_recommend BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (course_id, student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_student_id (student_id),
    INDEX idx_rating (rating)
)";

if ($conn->query($sql3)) {
    echo "<p style='color: green;'>✓ course_reviews table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating course_reviews table: " . $conn->error . "</p>";
}

// Add indexes for better performance
$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_courses_instructor_status ON courses(instructor_id, status)",
    "CREATE INDEX IF NOT EXISTS idx_courses_category_status ON courses(course_category, status)",
    "CREATE INDEX IF NOT EXISTS idx_enrollments_course_status ON course_enrollments(course_id, status)",
    "CREATE INDEX IF NOT EXISTS idx_reviews_course_rating ON course_reviews(course_id, rating)"
];

foreach ($indexes as $index_sql) {
    if ($conn->query($index_sql)) {
        echo "<p style='color: green;'>✓ Index created successfully</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Index creation: " . $conn->error . "</p>";
    }
}

echo "<h3>Course Management Setup Complete!</h3>";
echo "<p style='color: green; font-weight: bold;'>The Course Management System is now ready to use.</p>";
echo "<p><a href='instructor_dashboard.php'>← Back to Instructor Dashboard</a></p>";
?>
