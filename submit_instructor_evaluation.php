<?php
session_start();
include "connection.php";

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];
$success_message = "";
$error_message = "";

// Create instructor_evaluations table if it doesn't exist
$create_evaluations_table = "CREATE TABLE IF NOT EXISTS instructor_evaluations (
    evaluation_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    instructor_id INT NOT NULL,
    instructor_request_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    evaluation_text TEXT DEFAULT NULL,
    teaching_quality INT NOT NULL CHECK (teaching_quality >= 1 AND teaching_quality <= 5),
    communication INT NOT NULL CHECK (communication >= 1 AND communication <= 5),
    punctuality INT NOT NULL CHECK (punctuality >= 1 AND punctuality <= 5),
    professionalism INT NOT NULL CHECK (professionalism >= 1 AND professionalism <= 5),
    would_recommend BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES instructors(instructor_id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_request_id) REFERENCES instructor_requests(instructor_request_id) ON DELETE CASCADE,
    UNIQUE KEY unique_evaluation (company_id, instructor_id, instructor_request_id),
    INDEX idx_company_id (company_id),
    INDEX idx_instructor_id (instructor_id),
    INDEX idx_rating (rating)
)";

$conn->query($create_evaluations_table);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['course_id', 'instructor_id', 'rating', 'teaching_quality', 'communication', 'punctuality', 'professionalism'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        $course_id = intval($_POST['course_id']);
        $instructor_id = intval($_POST['instructor_id']);
        $rating = intval($_POST['rating']);
        $teaching_quality = intval($_POST['teaching_quality']);
        $communication = intval($_POST['communication']);
        $punctuality = intval($_POST['punctuality']);
        $professionalism = intval($_POST['professionalism']);
        $evaluation_text = isset($_POST['evaluation_text']) ? trim($_POST['evaluation_text']) : '';
        $would_recommend = isset($_POST['would_recommend']) ? 1 : 0;
        $is_edit = isset($_POST['is_edit']) ? intval($_POST['is_edit']) : 0;

        // Validate rating ranges
        if ($rating < 1 || $rating > 5 || $teaching_quality < 1 || $teaching_quality > 5 || 
            $communication < 1 || $communication > 5 || $punctuality < 1 || $punctuality > 5 || 
            $professionalism < 1 || $professionalism > 5) {
            throw new Exception("All ratings must be between 1 and 5.");
        }

        // Verify that the company owns this instructor request
        $verify_sql = "SELECT ir.instructor_request_id, ia.status 
                       FROM instructor_requests ir 
                       JOIN instructor_applications ia ON ir.instructor_request_id = ia.instructor_request_id 
                       WHERE ir.instructor_request_id = ? AND ir.company_id = ? AND ia.instructor_id = ? AND ia.status = 'accepted'";
        
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param("iii", $course_id, $company_id, $instructor_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            throw new Exception("You can only evaluate instructors who have been accepted for your courses.");
        }

        if ($is_edit) {
            // Update existing evaluation
            $update_sql = "UPDATE instructor_evaluations 
                           SET rating = ?, evaluation_text = ?, teaching_quality = ?, communication = ?, 
                               punctuality = ?, professionalism = ?, would_recommend = ?, updated_at = CURRENT_TIMESTAMP
                           WHERE company_id = ? AND instructor_id = ? AND instructor_request_id = ?";
            
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("isiiiiiiii", $rating, $evaluation_text, $teaching_quality, 
                                   $communication, $punctuality, $professionalism, $would_recommend, 
                                   $company_id, $instructor_id, $course_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Your evaluation has been updated successfully!";
            } else {
                throw new Exception("Error updating evaluation: " . $conn->error);
            }
        } else {
            // Insert new evaluation
            $insert_sql = "INSERT INTO instructor_evaluations 
                           (company_id, instructor_id, instructor_request_id, rating, evaluation_text, 
                            teaching_quality, communication, punctuality, professionalism, would_recommend) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iiiisiiiii", $company_id, $instructor_id, $course_id, 
                                   $rating, $evaluation_text, $teaching_quality, $communication, 
                                   $punctuality, $professionalism, $would_recommend);
            
            if ($insert_stmt->execute()) {
                $success_message = "Your evaluation has been submitted successfully! Thank you for your feedback.";
            } else {
                if ($conn->errno === 1062) { // Duplicate entry error
                    throw new Exception("You have already evaluated this instructor for this course.");
                } else {
                    throw new Exception("Error submitting evaluation: " . $conn->error);
                }
            }
        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Redirect back to company dashboard with message
$redirect_url = "company_dashboard.php";
if ($success_message) {
    $redirect_url .= "?success=" . urlencode($success_message);
} elseif ($error_message) {
    $redirect_url .= "?error=" . urlencode($error_message);
}

header("Location: " . $redirect_url);
exit();
?>
