<?php
session_start();
include "connection.php";

// Check if certificate ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: login.php");
    exit();
}

$certificate_id = intval($_GET['id']);

// Create certificates table if it doesn't exist
$create_certificates_table = "CREATE TABLE IF NOT EXISTS certificates (
    certificate_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    student_id INT NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    course_description TEXT,
    completion_date DATE NOT NULL,
    certificate_number VARCHAR(50) UNIQUE NOT NULL,
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'revoked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_company_id (company_id),
    INDEX idx_student_id (student_id),
    INDEX idx_certificate_number (certificate_number)
)";

$conn->query($create_certificates_table);

// Get certificate details
$certificate_sql = "SELECT 
    c.*,
    s.first_name,
    s.last_name,
    s.email as student_email,
    comp.company_name,
    comp.logo_path,
    comp.address as company_address
    FROM certificates c
    JOIN students s ON c.student_id = s.student_id
    JOIN companies comp ON c.company_id = comp.company_id
    WHERE c.certificate_id = ? AND c.status = 'active'";

$stmt = $conn->prepare($certificate_sql);
$stmt->bind_param("i", $certificate_id);
$stmt->execute();
$certificate = $stmt->get_result()->fetch_assoc();

if (!$certificate) {
    die("Certificate not found or has been revoked.");
}

// Check if user has permission to download this certificate
$can_download = false;

if (isset($_SESSION['student_id']) && $_SESSION['student_id'] == $certificate['student_id']) {
    $can_download = true; // Student downloading their own certificate
} elseif (isset($_SESSION['company_id']) && $_SESSION['company_id'] == $certificate['company_id']) {
    $can_download = true; // Company downloading their issued certificate
} elseif (isset($_SESSION['admin_id'])) {
    $can_download = true; // Admin can download any certificate
}

if (!$can_download) {
    die("You don't have permission to download this certificate.");
}

// Generate PDF using HTML to PDF conversion
// For this example, we'll use a simple HTML to PDF approach
// In production, you might want to use libraries like TCPDF, FPDF, or wkhtmltopdf

$html = generateCertificateHTML($certificate);

// Set headers for PDF download
$filename = 'Certificate_' . $certificate['certificate_number'] . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// For now, we'll redirect to the view page with print parameter
// In a real implementation, you would generate actual PDF content here
header("Location: view_certificate.php?id=" . $certificate_id . "&print=1");

function generateCertificateHTML($certificate) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Certificate - ' . htmlspecialchars($certificate['course_name']) . '</title>
        <style>
            @page {
                size: A4 landscape;
                margin: 0.5in;
            }
            body {
                font-family: "Times New Roman", serif;
                margin: 0;
                padding: 0;
                background: white;
                color: #333;
            }
            .certificate {
                width: 100%;
                height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                text-align: center;
                border: 3px solid #0ea5a8;
                position: relative;
            }
            .certificate::before {
                content: "";
                position: absolute;
                top: 20px;
                left: 20px;
                right: 20px;
                bottom: 20px;
                border: 2px solid #0ea5a8;
                border-radius: 10px;
            }
            .header {
                margin-bottom: 40px;
            }
            .title {
                font-size: 36px;
                font-weight: bold;
                color: #0ea5a8;
                margin-bottom: 10px;
            }
            .subtitle {
                font-size: 18px;
                color: #666;
            }
            .award-text {
                font-size: 20px;
                margin: 20px 0;
            }
            .student-name {
                font-size: 32px;
                font-weight: bold;
                color: #0b1f3a;
                margin: 20px 0;
            }
            .course-name {
                font-size: 24px;
                font-weight: bold;
                color: #0ea5a8;
                margin: 20px 0;
            }
            .details {
                margin: 30px 0;
                font-size: 16px;
            }
            .company-info {
                margin-top: 40px;
                font-size: 16px;
            }
            .certificate-number {
                position: absolute;
                bottom: 20px;
                right: 20px;
                font-size: 12px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="certificate">
            <div class="header">
                <div class="title">CERTIFICATE OF COMPLETION</div>
                <div class="subtitle">Professional Achievement Recognition</div>
            </div>
            
            <div class="award-text">This is to certify that</div>
            
            <div class="student-name">' . htmlspecialchars($certificate['first_name'] . ' ' . $certificate['last_name']) . '</div>
            
            <div class="award-text">has successfully completed the course</div>
            
            <div class="course-name">' . htmlspecialchars($certificate['course_name']) . '</div>
            
            <div class="details">
                <div>Completion Date: ' . date('F d, Y', strtotime($certificate['completion_date'])) . '</div>
                <div>Issued Date: ' . date('F d, Y', strtotime($certificate['issued_date'])) . '</div>
            </div>
            
            <div class="company-info">
                <div><strong>' . htmlspecialchars($certificate['company_name']) . '</strong></div>
                <div>Authorized Training Provider</div>
            </div>
            
            <div class="certificate-number">Certificate #' . htmlspecialchars($certificate['certificate_number']) . '</div>
        </div>
    </body>
    </html>';
    
    return $html;
}
?>
