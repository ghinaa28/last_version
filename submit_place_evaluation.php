<?php
session_start();
include "connection.php";

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];

// Validate required fields
$required_fields = ['place_id', 'rating', 'location_quality', 'cleanliness', 'amenities', 'value_for_money'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        header("Location: evaluate_places.php?error=" . urlencode("Please fill in all required fields."));
        exit();
    }
}

// Sanitize and validate input
$place_id = intval($_POST['place_id']);
$rating = intval($_POST['rating']);
$location_quality = intval($_POST['location_quality']);
$cleanliness = intval($_POST['cleanliness']);
$amenities = intval($_POST['amenities']);
$value_for_money = intval($_POST['value_for_money']);
$evaluation_text = isset($_POST['evaluation_text']) ? trim($_POST['evaluation_text']) : '';
$would_recommend = isset($_POST['would_recommend']) ? 1 : 0;
$is_edit = isset($_POST['is_edit']) ? intval($_POST['is_edit']) : 0;

// Validate rating ranges
$ratings = [$rating, $location_quality, $cleanliness, $amenities, $value_for_money];
foreach ($ratings as $r) {
    if ($r < 1 || $r > 5) {
        header("Location: evaluate_places.php?error=" . urlencode("All ratings must be between 1 and 5."));
        exit();
    }
}

try {
    // Verify that the company has actually booked this place
    $verify_booking_sql = "SELECT pb.booking_id 
                          FROM place_bookings pb 
                          WHERE pb.company_id = ? AND pb.place_id = ?";
    $stmt = $conn->prepare($verify_booking_sql);
    $stmt->bind_param("ii", $company_id, $place_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        header("Location: evaluate_places.php?error=" . urlencode("You can only evaluate places you have booked."));
        exit();
    }
    
    if ($is_edit) {
        // Update existing evaluation
        $update_sql = "UPDATE place_evaluations 
                      SET rating = ?, 
                          evaluation_text = ?, 
                          location_quality = ?, 
                          cleanliness = ?, 
                          amenities = ?, 
                          value_for_money = ?, 
                          would_recommend = ?,
                          updated_at = CURRENT_TIMESTAMP
                      WHERE company_id = ? AND place_id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("isiiiiiii", $rating, $evaluation_text, $location_quality, $cleanliness, $amenities, $value_for_money, $would_recommend, $company_id, $place_id);
        
        if ($stmt->execute()) {
            header("Location: evaluate_places.php?success=" . urlencode("Place evaluation updated successfully!"));
        } else {
            header("Location: evaluate_places.php?error=" . urlencode("Failed to update evaluation. Please try again."));
        }
    } else {
        // Insert new evaluation
        $insert_sql = "INSERT INTO place_evaluations 
                      (company_id, place_id, rating, evaluation_text, location_quality, cleanliness, amenities, value_for_money, would_recommend) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iiisiiiii", $company_id, $place_id, $rating, $evaluation_text, $location_quality, $cleanliness, $amenities, $value_for_money, $would_recommend);
        
        if ($stmt->execute()) {
            header("Location: evaluate_places.php?success=" . urlencode("Place evaluation submitted successfully!"));
        } else {
            header("Location: evaluate_places.php?error=" . urlencode("Failed to submit evaluation. Please try again."));
        }
    }
    
} catch (Exception $e) {
    error_log("Place evaluation error: " . $e->getMessage());
    header("Location: evaluate_places.php?error=" . urlencode("An error occurred. Please try again."));
}

$conn->close();
?>
