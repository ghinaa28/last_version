<?php
session_start();
include "connection.php";

// Check if user is logged in as student
if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['student_id'];
$company_id = $_GET['company_id'] ?? '';

if (empty($company_id)) {
    echo json_encode(['success' => false, 'message' => 'Company ID required']);
    exit();
}

// Get internships for the company that the student has applied to
$sql = "SELECT i.internship_id, i.title, i.department, i.type, i.location
        FROM internships i
        JOIN internship_applications ia ON i.internship_id = ia.internship_id
        WHERE i.company_id = ? AND ia.student_id = ?
        ORDER BY i.title";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $company_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

$internships = [];
while ($row = $result->fetch_assoc()) {
    $internships[] = $row;
}

echo json_encode([
    'success' => true,
    'internships' => $internships
]);
?>
