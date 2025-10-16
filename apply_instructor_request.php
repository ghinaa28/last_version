<?php
session_start();
require_once 'connection.php';

// Check if user is logged in as instructor
if (!isset($_SESSION['instructor_id'])) {
    header("Location: login.php");
    exit();
}

$instructor_id = $_SESSION['instructor_id'];
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error_message = ""; // Initialize error message

if (!$request_id) {
    header("Location: browse_instructor_requests.php");
    exit();
}

// Get instructor request details
$stmt = $conn->prepare("SELECT ir.*, c.company_name 
                       FROM instructor_requests ir 
                       JOIN companies c ON ir.company_id = c.company_id 
                       WHERE ir.instructor_request_id = ? AND ir.status = 'active'");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

if (!$request) {
    header("Location: browse_instructor_requests.php");
    exit();
}

// Check if deadline has passed
if (strtotime($request['application_deadline']) < time()) {
    $error_message = "The application deadline for this opportunity has passed.";
}

// Check if instructor has already applied
$stmt = $conn->prepare("SELECT * FROM instructor_applications WHERE instructor_request_id = ? AND instructor_id = ?");
$stmt->bind_param("ii", $request_id, $instructor_id);
$stmt->execute();
$existing_application = $stmt->get_result()->fetch_assoc();

if ($existing_application) {
    $error_message = "You have already applied for this opportunity.";
}

$success_message = "";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing_application && empty($error_message)) {
    try {
        $motivation_message = $conn->real_escape_string($_POST['motivation_message']);
        $relevant_experience = $conn->real_escape_string($_POST['relevant_experience']);
        $availability = $conn->real_escape_string($_POST['availability']);
        $additional_info = $conn->real_escape_string($_POST['additional_info']);
        
        // Handle CV upload
        $cv_path = null;
        if (isset($_FILES['cv']) && $_FILES['cv']['error'] == 0) {
            $upload_dir = "uploads/instructor_applications/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['pdf', 'doc', 'docx'];
            
            if (in_array(strtolower($file_extension), $allowed_extensions)) {
                $filename = uniqid() . '_' . time() . '.' . $file_extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['cv']['tmp_name'], $filepath)) {
                    $cv_path = $filepath;
                }
            } else {
                $error_message = "Invalid file type. Please upload a PDF, DOC, or DOCX file.";
            }
        }
        
        if (empty($error_message)) {
            // Insert application into database
            $stmt = $conn->prepare("INSERT INTO instructor_applications (
                instructor_request_id, instructor_id, motivation_message, relevant_experience, 
                availability, additional_info, cv_path, status, applied_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            
            $stmt->bind_param("iisssss", $request_id, $instructor_id, $motivation_message, $relevant_experience, $availability, $additional_info, $cv_path);
            
            if ($stmt->execute()) {
                $success_message = "Your application has been submitted successfully! The company will review your application and get back to you.";
            } else {
                $error_message = "Error submitting application: " . $conn->error;
            }
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Instructor Opportunity - Instructor Portal</title>
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
            max-width: 1000px;
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

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .opportunity-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--line);
            height: fit-content;
        }

        .opportunity-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .company-logo {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-md);
            object-fit: cover;
        }

        .company-info h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .company-info p {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .opportunity-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1rem;
        }

        .opportunity-details {
            display: grid;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .detail-label {
            font-size: 0.9rem;
            color: var(--text-light);
            font-weight: 600;
        }

        .detail-value {
            font-size: 0.9rem;
            color: var(--text-dark);
        }

        .compensation {
            font-weight: 700;
            color: var(--success);
        }

        .meta-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
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

        .badge-info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }

        .form-container {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--line);
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-label.required::after {
            content: " *";
            color: var(--error);
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-textarea.large {
            min-height: 150px;
        }

        .file-upload {
            border: 2px dashed var(--border-light);
            border-radius: var(--radius-lg);
            padding: 2rem;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
        }

        .file-upload:hover {
            border-color: var(--brand);
            background: rgba(14, 165, 168, 0.05);
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-upload-icon {
            font-size: 2rem;
            color: var(--brand);
            margin-bottom: 0.5rem;
        }

        .file-upload-text {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .help-text {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 0.25rem;
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

        .btn-primary {
            background: var(--brand);
            color: white;
        }

        .btn-primary:hover {
            background: var(--brand-2);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-dark);
            border: 2px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--border-light);
            border-color: var(--text-light);
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
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

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="instructor_dashboard.php">Instructor Portal</a>
                <i class="fas fa-chevron-right"></i>
                <a href="browse_instructor_requests.php">Browse Opportunities</a>
                <i class="fas fa-chevron-right"></i>
                <span>Apply</span>
            </div>
            <h1 class="page-title">Apply for Instructor Opportunity</h1>
            <p class="page-subtitle">Submit your application for this teaching opportunity.</p>
        </div>

        <div class="content-grid">
            <!-- Opportunity Details -->
            <div class="opportunity-card">
                <div class="opportunity-header">
                    <div class="company-logo" style="background: var(--brand); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                        <?php echo strtoupper(substr($request['company_name'], 0, 2)); ?>
                    </div>
                    <div class="company-info">
                        <h3><?php echo htmlspecialchars($request['company_name']); ?></h3>
                        <p><?php echo htmlspecialchars($request['location']); ?></p>
                    </div>
                </div>

                <h2 class="opportunity-title"><?php echo htmlspecialchars($request['course_title']); ?></h2>

                <div class="meta-badges">
                    <span class="meta-badge badge-primary"><?php echo ucfirst($request['course_type']); ?></span>
                    <span class="meta-badge badge-info"><?php echo ucfirst($request['experience_level']); ?></span>
                    <?php if ($request['is_online']): ?>
                        <span class="meta-badge badge-success">Online</span>
                    <?php endif; ?>
                </div>

                <div class="opportunity-details">
                    <div class="detail-item">
                        <span class="detail-label">Duration</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request['course_duration']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Compensation</span>
                        <span class="detail-value compensation">
                            $<?php echo number_format($request['compensation_amount'], 2); ?>
                            <?php if ($request['compensation_type'] === 'hourly'): ?>/hour<?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Compensation Type</span>
                        <span class="detail-value"><?php echo ucfirst($request['compensation_type']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Application Deadline</span>
                        <span class="detail-value"><?php echo date('M j, Y', strtotime($request['application_deadline'])); ?></span>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Course Description
                    </h3>
                    <p style="color: var(--text-light); line-height: 1.6;"><?php echo nl2br(htmlspecialchars($request['course_description'])); ?></p>
                </div>

                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user-check"></i>
                        Requirements
                    </h3>
                    <div style="margin-bottom: 1rem;">
                        <h4 style="font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 0.5rem;">Required Qualifications:</h4>
                        <p style="color: var(--text-light); line-height: 1.6;"><?php echo nl2br(htmlspecialchars($request['required_qualifications'])); ?></p>
                    </div>
                    <div>
                        <h4 style="font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 0.5rem;">Skills Required:</h4>
                        <p style="color: var(--text-light); line-height: 1.6;"><?php echo nl2br(htmlspecialchars($request['skills_required'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Application Form -->
            <div class="form-container">
                <?php if ($success_message): ?>
                    <div class="message message-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="message message-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!$existing_application && empty($error_message)): ?>
                    <form method="POST" action="apply_instructor_request.php?id=<?php echo $request_id; ?>" enctype="multipart/form-data">
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-paper-plane"></i>
                                Application Details
                            </h3>
                            
                            <div class="form-group">
                                <label class="form-label required">Motivation Message</label>
                                <textarea name="motivation_message" class="form-textarea large" placeholder="Why are you interested in this teaching opportunity? What makes you the right fit for this role?" required></textarea>
                                <div class="help-text">Explain your interest and motivation for this teaching position</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label required">Relevant Experience</label>
                                <textarea name="relevant_experience" class="form-textarea" placeholder="Describe your teaching experience, relevant work experience, and achievements that make you qualified for this role..." required></textarea>
                                <div class="help-text">Highlight your teaching and professional experience</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label required">Availability</label>
                                <textarea name="availability" class="form-textarea" placeholder="When are you available to teach? Include your preferred schedule, time zones, and any constraints..." required></textarea>
                                <div class="help-text">Specify your teaching availability and schedule preferences</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Additional Information</label>
                                <textarea name="additional_info" class="form-textarea" placeholder="Any additional information you'd like to share with the company..."></textarea>
                                <div class="help-text">Optional: Share any other relevant information</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Upload CV/Resume</label>
                                <div class="file-upload" onclick="document.getElementById('cv-upload').click()">
                                    <div class="file-upload-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <div class="file-upload-text">
                                        <strong>Click to upload CV/Resume</strong><br>
                                        PDF, DOC, or DOCX (Max 5MB)
                                    </div>
                                </div>
                                <input type="file" id="cv-upload" name="cv" accept=".pdf,.doc,.docx" style="display: none;">
                                <div class="help-text">Upload your CV or resume (optional but recommended)</div>
                            </div>
                        </div>

                        <div class="btn-group">
                            <a href="browse_instructor_requests.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                Back to Opportunities
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                Submit Application
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="btn-group">
                        <a href="browse_instructor_requests.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Opportunities
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // File upload preview
        document.getElementById('cv-upload').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const uploadDiv = document.querySelector('.file-upload');
                uploadDiv.innerHTML = `
                    <div class="file-upload-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="file-upload-text">
                        <strong>${file.name}</strong><br>
                        ${(file.size / 1024 / 1024).toFixed(2)} MB
                    </div>
                `;
            }
        });
    </script>
</body>
</html>
