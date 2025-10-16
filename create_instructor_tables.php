<?php
require_once 'connection.php';

echo "<h2>Creating Instructor Service Tables</h2>";

// Create instructor_requests table
$sql1 = "CREATE TABLE IF NOT EXISTS instructor_requests (
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
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE
)";

if ($conn->query($sql1)) {
    echo "<p style='color: green;'>✓ instructor_requests table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating instructor_requests table: " . $conn->error . "</p>";
}

// Create instructor_applications table
$sql2 = "CREATE TABLE IF NOT EXISTS instructor_applications (
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
    UNIQUE KEY unique_application (instructor_request_id, instructor_id)
)";

if ($conn->query($sql2)) {
    echo "<p style='color: green;'>✓ instructor_applications table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating instructor_applications table: " . $conn->error . "</p>";
}

// Add indexes for better performance
$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_instructor_requests_company_status ON instructor_requests(company_id, status)",
    "CREATE INDEX IF NOT EXISTS idx_instructor_requests_deadline_status ON instructor_requests(application_deadline, status)",
    "CREATE INDEX IF NOT EXISTS idx_instructor_applications_request_status ON instructor_applications(instructor_request_id, status)"
];

foreach ($indexes as $index_sql) {
    if ($conn->query($index_sql)) {
        echo "<p style='color: green;'>✓ Index created successfully</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Index creation: " . $conn->error . "</p>";
    }
}

echo "<h3>Setup Complete!</h3>";
echo "<p style='color: green; font-weight: bold;'>The Instructor Service is now ready to use.</p>";
echo "<p><a href='company_dashboard.php'>← Back to Company Dashboard</a></p>";

$conn->close();
?>


