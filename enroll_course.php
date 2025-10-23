<?php
session_start();
include "connection.php";

// Check if user is logged in as student
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Check if course_id is provided
if (!isset($_POST['course_id']) || !is_numeric($_POST['course_id'])) {
    header("Location: browse_courses.php");
    exit();
}

$course_id = intval($_POST['course_id']);

// Check if course exists and is published
$stmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ? AND status = 'published'");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    $_SESSION['error'] = "Course not found or not available for enrollment.";
    header("Location: browse_courses.php");
    exit();
}

// Check if student is already enrolled
$stmt = $conn->prepare("SELECT * FROM course_enrollments WHERE course_id = ? AND student_id = ?");
$stmt->bind_param("ii", $course_id, $student_id);
$stmt->execute();
$existing_enrollment = $stmt->get_result()->fetch_assoc();

if ($existing_enrollment) {
    $_SESSION['error'] = "You are already enrolled in this course.";
    header("Location: browse_courses.php");
    exit();
}

// Check if course has reached maximum students
if ($course['max_students'] > 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) as current_enrollments FROM course_enrollments WHERE course_id = ? AND status = 'enrolled'");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $enrollment_count = $stmt->get_result()->fetch_assoc();
    
    if ($enrollment_count['current_enrollments'] >= $course['max_students']) {
        $_SESSION['error'] = "This course has reached its maximum capacity.";
        header("Location: browse_courses.php");
        exit();
    }
}

// Enroll student in course
$stmt = $conn->prepare("INSERT INTO course_enrollments (course_id, student_id, enrollment_date, status) VALUES (?, ?, NOW(), 'enrolled')");
$stmt->bind_param("ii", $course_id, $student_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Successfully enrolled in the course!";
} else {
    $_SESSION['error'] = "Failed to enroll in the course. Please try again.";
}

header("Location: browse_courses.php");
exit();
?>
