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

// Handle certificate creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_certificate') {
    $student_id = intval($_POST['student_id']);
    $course_name = $conn->real_escape_string($_POST['course_name']);
    $course_description = $conn->real_escape_string($_POST['course_description']);
    $completion_date = $_POST['completion_date'];
    
    // Generate unique certificate number
    $certificate_number = 'CERT-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
    // Check if certificate number already exists
    $check_cert = $conn->prepare("SELECT certificate_id FROM certificates WHERE certificate_number = ?");
    $check_cert->bind_param("s", $certificate_number);
    $check_cert->execute();
    
    while ($check_cert->get_result()->num_rows > 0) {
        $certificate_number = 'CERT-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $check_cert->bind_param("s", $certificate_number);
        $check_cert->execute();
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO certificates (company_id, student_id, course_name, course_description, completion_date, certificate_number) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $company_id, $student_id, $course_name, $course_description, $completion_date, $certificate_number);
        
        if ($stmt->execute()) {
            $success_message = "Certificate issued successfully! Certificate Number: " . $certificate_number;
        } else {
            $error_message = "Error creating certificate: " . $conn->error;
        }
    } catch (Exception $e) {
        $error_message = "Error creating certificate: " . $e->getMessage();
    }
}

// Handle certificate status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $certificate_id = intval($_POST['certificate_id']);
    $new_status = $_POST['status'];
    
    // Verify the certificate belongs to this company
    $verify_stmt = $conn->prepare("SELECT certificate_id FROM certificates WHERE certificate_id = ? AND company_id = ?");
    $verify_stmt->bind_param("ii", $certificate_id, $company_id);
    $verify_stmt->execute();
    
    if ($verify_stmt->get_result()->num_rows > 0) {
        $update_stmt = $conn->prepare("UPDATE certificates SET status = ? WHERE certificate_id = ?");
        $update_stmt->bind_param("si", $new_status, $certificate_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Certificate status updated successfully!";
        } else {
            $error_message = "Error updating certificate status: " . $conn->error;
        }
    } else {
        $error_message = "Unauthorized action!";
    }
}

// Get company's issued certificates
$certificates_sql = "SELECT 
    c.*,
    s.first_name,
    s.last_name,
    s.email as student_email,
    comp.company_name
    FROM certificates c
    JOIN students s ON c.student_id = s.student_id
    JOIN companies comp ON c.company_id = comp.company_id
    WHERE c.company_id = ?
    ORDER BY c.issued_date DESC";

$stmt = $conn->prepare($certificates_sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$certificates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all students for certificate creation
$students_sql = "SELECT student_id, first_name, last_name, email FROM students ORDER BY first_name, last_name";
$students_result = $conn->query($students_sql);
$students = $students_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Certificates - Company Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #0ea5a8;
            --brand-2: #22d3ee;
            --ink: #0b1f3a;
            --muted: #475569;
            --panel: #ffffff;
            --line: #e5e7eb;
            --success: #4ade80;
            --error: #f87171;
            --warning: #fbbf24;
            --text-dark: #0f172a;
            --text-light: #475569;
            --text-white: #ffffff;
            --bg-primary: #ffffff;
            --bg-secondary: #f6f8fb;
            --border-light: #e5e7eb;
            --border-focus: #0ea5a8;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, #f6f8fb 0%, #e2e8f0 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: var(--muted);
        }

        .breadcrumb a {
            color: var(--brand);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .breadcrumb a:hover {
            color: var(--brand-2);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--muted);
            font-size: 1.1rem;
        }

        .message {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .message.success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border: 1px solid #10b981;
            color: #065f46;
        }

        .message.error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: 1px solid #ef4444;
            color: #991b1b;
        }

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .btn {
            background: var(--brand);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn:hover {
            background: var(--brand-2);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: #6b7280;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-success {
            background: var(--success);
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: var(--warning);
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-danger {
            background: var(--error);
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .certificates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
        }

        .certificate-card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .certificate-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            height: 4px;
            width: 100%;
            background: linear-gradient(90deg, var(--brand), var(--brand-2));
        }

        .certificate-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .certificate-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .certificate-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .certificate-number {
            font-size: 0.9rem;
            color: var(--muted);
            font-family: monospace;
            background: var(--bg-secondary);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }

        .status-revoked {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
        }

        .certificate-info {
            margin-bottom: 1.5rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: var(--ink);
        }

        .info-value {
            color: var(--muted);
            text-align: right;
        }

        .certificate-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .no-certificates {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--panel);
            border: 2px dashed var(--border-light);
            border-radius: var(--radius-lg);
            margin: 2rem 0;
        }

        .no-certificates .icon {
            font-size: 4rem;
            color: var(--brand);
            margin-bottom: 1rem;
        }

        .no-certificates h3 {
            color: var(--ink);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .no-certificates p {
            color: var(--muted);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background-color: var(--panel);
            margin: 5% auto;
            padding: 0;
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--line);
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
            color: white;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .close {
            color: white;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.2s ease;
        }

        .close:hover {
            opacity: 0.7;
        }

        .modal form {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--ink);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--line);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .certificates-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
            
            .modal-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="company_dashboard.php">Company Portal</a>
                <i class="fas fa-chevron-right"></i>
                <span>Manage Certificates</span>
            </div>
            <h1 class="page-title">Manage Certificates</h1>
            <p class="page-subtitle">Issue and manage professional certificates for students who complete courses</p>
        </div>

        <?php if ($success_message): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="actions-bar">
            <h2>Issued Certificates (<?php echo count($certificates); ?>)</h2>
            <button class="btn btn-success" onclick="openCreateModal()">
                <i class="fas fa-plus"></i>
                Issue New Certificate
            </button>
        </div>

        <?php if (empty($certificates)): ?>
            <div class="no-certificates">
                <div class="icon">🏆</div>
                <h3>No Certificates Issued Yet</h3>
                <p>You haven't issued any certificates yet. Start by creating certificates for students who have completed courses with your company.</p>
                <button class="btn btn-success" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i>
                    Issue Your First Certificate
                </button>
            </div>
        <?php else: ?>
            <div class="certificates-grid">
                <?php foreach ($certificates as $certificate): ?>
                    <div class="certificate-card">
                        <div class="certificate-header">
                            <div>
                                <h3 class="certificate-title"><?php echo htmlspecialchars($certificate['course_name']); ?></h3>
                                <div class="certificate-number"><?php echo htmlspecialchars($certificate['certificate_number']); ?></div>
                            </div>
                            <span class="status-badge status-<?php echo $certificate['status']; ?>">
                                <?php echo ucfirst($certificate['status']); ?>
                            </span>
                        </div>
                        
                        <div class="certificate-info">
                            <div class="info-item">
                                <span class="info-label">Student:</span>
                                <span class="info-value"><?php echo htmlspecialchars($certificate['first_name'] . ' ' . $certificate['last_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($certificate['student_email']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Completion Date:</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($certificate['completion_date'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Issued Date:</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($certificate['issued_date'])); ?></span>
                            </div>
                        </div>

                        <?php if ($certificate['course_description']): ?>
                            <div style="margin-bottom: 1.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md);">
                                <strong>Course Description:</strong><br>
                                <span style="color: var(--muted);"><?php echo htmlspecialchars($certificate['course_description']); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="certificate-actions">
                            <button class="btn btn-sm btn-secondary" onclick="viewCertificate(<?php echo $certificate['certificate_id']; ?>)">
                                <i class="fas fa-eye"></i>
                                View
                            </button>
                            <button class="btn btn-sm btn-warning" onclick="openStatusModal(<?php echo $certificate['certificate_id']; ?>, '<?php echo $certificate['status']; ?>')">
                                <i class="fas fa-edit"></i>
                                Status
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create Certificate Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Issue New Certificate</h3>
                <span class="close" onclick="closeModal('createModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_certificate">
                <div class="form-group">
                    <label for="student_id">Student *</label>
                    <select name="student_id" id="student_id" required>
                        <option value="">Select a student</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['student_id']; ?>">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="course_name">Course Name *</label>
                    <input type="text" name="course_name" id="course_name" required placeholder="e.g., Advanced Web Development">
                </div>
                <div class="form-group">
                    <label for="course_description">Course Description</label>
                    <textarea name="course_description" id="course_description" rows="4" placeholder="Describe the course content and learning outcomes..."></textarea>
                </div>
                <div class="form-group">
                    <label for="completion_date">Completion Date *</label>
                    <input type="date" name="completion_date" id="completion_date" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Issue Certificate</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Certificate Status</h3>
                <span class="close" onclick="closeModal('statusModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="certificate_id" id="status_certificate_id">
                <div class="form-group">
                    <label for="status_select">Status</label>
                    <select name="status" id="status_select">
                        <option value="active">Active</option>
                        <option value="revoked">Revoked</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function openStatusModal(certificateId, currentStatus) {
            document.getElementById('status_certificate_id').value = certificateId;
            document.getElementById('status_select').value = currentStatus;
            document.getElementById('statusModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function viewCertificate(certificateId) {
            // Open certificate in new window
            window.open('view_certificate.php?id=' + certificateId, '_blank');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById('createModal');
            const statusModal = document.getElementById('statusModal');
            
            if (event.target === createModal) {
                closeModal('createModal');
            }
            if (event.target === statusModal) {
                closeModal('statusModal');
            }
        }

        // Set default completion date to today
        document.getElementById('completion_date').valueAsDate = new Date();
    </script>
</body>
</html>
