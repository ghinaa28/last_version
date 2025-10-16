<?php
session_start();
require_once 'connection.php';

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$request_id) {
    header("Location: manage_instructor_requests.php");
    exit();
}

// Get instructor request details and verify ownership
$stmt = $conn->prepare("SELECT ir.*, c.company_name 
                       FROM instructor_requests ir 
                       JOIN companies c ON ir.company_id = c.company_id 
                       WHERE ir.instructor_request_id = ? AND ir.company_id = ?");
$stmt->bind_param("ii", $request_id, $company_id);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

if (!$request) {
    header("Location: manage_instructor_requests.php");
    exit();
}

// Handle application status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['application_id'])) {
    $application_id = (int)$_POST['application_id'];
    $action = $_POST['action'];
    $review_notes = isset($_POST['review_notes']) ? $conn->real_escape_string($_POST['review_notes']) : '';
    
    if (in_array($action, ['accept', 'reject'])) {
        $new_status = $action === 'accept' ? 'accepted' : 'rejected';
        
        $stmt = $conn->prepare("UPDATE instructor_applications 
                               SET status = ?, reviewed_at = NOW(), review_notes = ?
                               WHERE application_id = ? AND instructor_request_id = ?");
        $stmt->bind_param("ssii", $new_status, $review_notes, $application_id, $request_id);
        
        if ($stmt->execute()) {
            $success_message = "Application " . $action . "ed successfully!";
        } else {
            $error_message = "Failed to update application status.";
        }
    }
}

// Get all applications for this request
$stmt = $conn->prepare("SELECT ia.*, i.first_name, i.last_name, i.email, i.phone, i.department, i.university_name, i.bio
                       FROM instructor_applications ia
                       JOIN instructors i ON ia.instructor_id = i.instructor_id
                       WHERE ia.instructor_request_id = ?
                       ORDER BY ia.applied_at DESC");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$applications = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Applications - <?php echo htmlspecialchars($request['course_title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #0ea5a8;
            --brand-2: #0891b2;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --ink: #1f2937;
            --text-dark: #374151;
            --text-light: #6b7280;
            --muted: #9ca3af;
            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --border-light: #e5e7eb;
            --border-focus: var(--brand);
            --line: #d1d5db;
            --panel: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            margin-bottom: 2rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .breadcrumb a {
            color: var(--brand);
            text-decoration: none;
            transition: var(--transition);
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

        .request-summary {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--line);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .request-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .request-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .meta-badge {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-primary {
            background: rgba(14, 165, 168, 0.1);
            color: var(--brand);
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .badge-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
        }

        .applications-grid {
            display: grid;
            gap: 1.5rem;
        }

        .application-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--line);
            transition: var(--transition);
        }

        .application-card:hover {
            box-shadow: var(--shadow-md);
        }

        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .instructor-info h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .instructor-info p {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 600;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .status-accepted {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
        }

        .application-details {
            display: grid;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-section {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: var(--radius-lg);
            border-left: 4px solid var(--brand);
        }

        .detail-section h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .detail-section p {
            color: var(--text-light);
            line-height: 1.6;
        }

        .cv-section {
            margin-bottom: 1.5rem;
        }

        .cv-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--brand);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .cv-link:hover {
            color: var(--brand-2);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-lg);
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-error {
            background: var(--error);
            color: white;
        }

        .btn-error:hover {
            background: #dc2626;
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-dark);
            border: 2px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--border-light);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: var(--panel);
            margin: 5% auto;
            padding: 2rem;
            border-radius: var(--radius-xl);
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
        }

        .close {
            color: var(--muted);
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: var(--transition);
        }

        .close:hover {
            color: var(--text-dark);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
            resize: vertical;
            min-height: 100px;
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .message {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .message-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .message-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .no-applications {
            text-align: center;
            padding: 3rem;
            color: var(--text-light);
        }

        .no-applications i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--muted);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .request-header {
                flex-direction: column;
                gap: 1rem;
            }

            .application-header {
                flex-direction: column;
                gap: 1rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: stretch;
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
                <a href="manage_instructor_requests.php">Manage Instructor Requests</a>
                <i class="fas fa-chevron-right"></i>
                <span>View Applications</span>
            </div>
            <h1 class="page-title">View Applications</h1>
            <p class="page-subtitle">Review and manage applications for your instructor opportunity.</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="message message-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Request Summary -->
        <div class="request-summary">
            <div class="request-header">
                <div>
                    <h2 class="request-title"><?php echo htmlspecialchars($request['course_title']); ?></h2>
                    <div class="request-meta">
                        <span class="meta-badge badge-primary"><?php echo ucfirst($request['course_type']); ?></span>
                        <span class="meta-badge badge-success"><?php echo ucfirst($request['experience_level']); ?></span>
                        <?php if ($request['is_online']): ?>
                            <span class="meta-badge badge-warning">Online</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <a href="manage_instructor_requests.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Requests
                    </a>
                </div>
            </div>
        </div>

        <!-- Applications -->
        <div class="applications-grid">
            <?php if (count($applications) === 0): ?>
                <div class="no-applications">
                    <i class="fas fa-users"></i>
                    <h3>No Applications Yet</h3>
                    <p>No instructors have applied for this opportunity yet. Check back later or share the opportunity to attract more applicants.</p>
                </div>
            <?php else: ?>
                <?php foreach ($applications as $application): ?>
                    <div class="application-card">
                        <div class="application-header">
                            <div class="instructor-info">
                                <h3><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></h3>
                                <p><?php echo htmlspecialchars($application['email']); ?></p>
                                <p>Department: <?php echo htmlspecialchars($application['department']); ?> | University: <?php echo htmlspecialchars($application['university_name']); ?></p>
                            </div>
                            <div class="status-badge status-<?php echo $application['status']; ?>">
                                <?php echo ucfirst($application['status']); ?>
                            </div>
                        </div>

                        <div class="application-details">
                            <div class="detail-section">
                                <h4>Motivation Message</h4>
                                <p><?php echo nl2br(htmlspecialchars($application['motivation_message'])); ?></p>
                            </div>

                            <div class="detail-section">
                                <h4>Relevant Experience</h4>
                                <p><?php echo nl2br(htmlspecialchars($application['relevant_experience'])); ?></p>
                            </div>

                            <div class="detail-section">
                                <h4>Availability</h4>
                                <p><?php echo nl2br(htmlspecialchars($application['availability'])); ?></p>
                            </div>

                            <?php if (!empty($application['additional_info'])): ?>
                                <div class="detail-section">
                                    <h4>Additional Information</h4>
                                    <p><?php echo nl2br(htmlspecialchars($application['additional_info'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($application['cv_path'])): ?>
                                <div class="cv-section">
                                    <a href="<?php echo htmlspecialchars($application['cv_path']); ?>" target="_blank" class="cv-link">
                                        <i class="fas fa-file-pdf"></i>
                                        View CV/Resume
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($application['status'] === 'pending'): ?>
                            <div class="action-buttons">
                                <button class="btn btn-success" onclick="openModal('accept', <?php echo $application['application_id']; ?>)">
                                    <i class="fas fa-check"></i>
                                    Accept
                                </button>
                                <button class="btn btn-error" onclick="openModal('reject', <?php echo $application['application_id']; ?>)">
                                    <i class="fas fa-times"></i>
                                    Reject
                                </button>
                            </div>
                        <?php elseif ($application['status'] === 'accepted'): ?>
                            <div class="action-buttons">
                                <span class="status-badge status-accepted">
                                    <i class="fas fa-check-circle"></i>
                                    Application Accepted
                                </span>
                            </div>
                        <?php elseif ($application['status'] === 'rejected'): ?>
                            <div class="action-buttons">
                                <span class="status-badge status-rejected">
                                    <i class="fas fa-times-circle"></i>
                                    Application Rejected
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($application['review_notes'])): ?>
                            <div class="detail-section" style="margin-top: 1rem;">
                                <h4>Review Notes</h4>
                                <p><?php echo nl2br(htmlspecialchars($application['review_notes'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for Accept/Reject -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Review Application</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" id="actionForm">
                <input type="hidden" name="action" id="actionInput">
                <input type="hidden" name="application_id" id="applicationIdInput">
                
                <div class="form-group">
                    <label class="form-label" for="reviewNotes">Review Notes (Optional)</label>
                    <textarea name="review_notes" id="reviewNotes" class="form-textarea" placeholder="Add any notes about your decision..."></textarea>
                </div>
                
                <div class="action-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn" id="submitBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(action, applicationId) {
            const modal = document.getElementById('actionModal');
            const modalTitle = document.getElementById('modalTitle');
            const actionInput = document.getElementById('actionInput');
            const applicationIdInput = document.getElementById('applicationIdInput');
            const submitBtn = document.getElementById('submitBtn');
            
            actionInput.value = action;
            applicationIdInput.value = applicationId;
            
            if (action === 'accept') {
                modalTitle.textContent = 'Accept Application';
                submitBtn.textContent = 'Accept Application';
                submitBtn.className = 'btn btn-success';
            } else {
                modalTitle.textContent = 'Reject Application';
                submitBtn.textContent = 'Reject Application';
                submitBtn.className = 'btn btn-error';
            }
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('actionModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('actionModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
