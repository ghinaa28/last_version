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
$stmt = $conn->prepare("SELECT * FROM instructor_requests WHERE instructor_request_id = ? AND company_id = ?");
$stmt->bind_param("ii", $request_id, $company_id);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

if (!$request) {
    header("Location: manage_instructor_requests.php");
    exit();
}

$success_message = "";
$error_message = "";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $course_title = $conn->real_escape_string($_POST['course_title']);
        $course_description = $conn->real_escape_string($_POST['course_description']);
        $required_qualifications = $conn->real_escape_string($_POST['required_qualifications']);
        $skills_required = $conn->real_escape_string($_POST['skills_required']);
        $course_duration = $conn->real_escape_string($_POST['course_duration']);
        $location = $conn->real_escape_string($_POST['location']);
        $is_online = isset($_POST['is_online']) ? 1 : 0;
        $compensation_type = $conn->real_escape_string($_POST['compensation_type']);
        $compensation_amount = (float)$_POST['compensation_amount'];
        $application_deadline = $_POST['application_deadline'];
        $max_applications = (int)$_POST['max_applications'];
        $course_type = $conn->real_escape_string($_POST['course_type']);
        $experience_level = $conn->real_escape_string($_POST['experience_level']);
        $status = $conn->real_escape_string($_POST['status']);
        
        // Validate required fields
        if (empty($course_title) || empty($course_description) || empty($required_qualifications) || empty($skills_required) || empty($course_duration) || empty($location) || empty($compensation_type) || empty($compensation_amount) || empty($application_deadline)) {
            $error_message = "Please fill in all required fields.";
        } else {
            // Update instructor request
            $stmt = $conn->prepare("UPDATE instructor_requests SET 
                course_title = ?, course_description = ?, required_qualifications = ?, 
                skills_required = ?, course_duration = ?, location = ?, is_online = ?, 
                compensation_type = ?, compensation_amount = ?, application_deadline = ?, 
                max_applications = ?, course_type = ?, experience_level = ?, status = ?, 
                updated_at = NOW() 
                WHERE instructor_request_id = ? AND company_id = ?");
            
            $stmt->bind_param("ssssssisdissssii", 
                $course_title, $course_description, $required_qualifications, 
                $skills_required, $course_duration, $location, $is_online, 
                $compensation_type, $compensation_amount, $application_deadline, 
                $max_applications, $course_type, $experience_level, $status, 
                $request_id, $company_id);
            
            if ($stmt->execute()) {
                $success_message = "Instructor request updated successfully!";
                // Refresh the request data
                $stmt = $conn->prepare("SELECT * FROM instructor_requests WHERE instructor_request_id = ? AND company_id = ?");
                $stmt->bind_param("ii", $request_id, $company_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $request = $result->fetch_assoc();
            } else {
                $error_message = "Error updating instructor request: " . $conn->error;
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
    <title>Edit Instructor Request - <?php echo htmlspecialchars($request['course_title']); ?></title>
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: 1.2rem;
            height: 1.2rem;
            accent-color: var(--brand);
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

            .form-grid {
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
                <a href="company_dashboard.php">Company Portal</a>
                <i class="fas fa-chevron-right"></i>
                <a href="manage_instructor_requests.php">Manage Instructor Requests</a>
                <i class="fas fa-chevron-right"></i>
                <span>Edit Request</span>
            </div>
            <h1 class="page-title">Edit Instructor Request</h1>
            <p class="page-subtitle">Update the details of your instructor opportunity.</p>
        </div>

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

        <div class="form-container">
            <form method="POST" action="edit_instructor_request.php?id=<?php echo $request_id; ?>">
                <!-- Course Information -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-graduation-cap"></i>
                        Course Information
                    </h3>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label required">Course Title</label>
                            <input type="text" name="course_title" class="form-input" value="<?php echo htmlspecialchars($request['course_title']); ?>" required>
                            <div class="help-text">A clear, descriptive title for the course you need an instructor for</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Course Type</label>
                            <select name="course_type" class="form-select" required>
                                <option value="">Select course type</option>
                                <option value="technical" <?php echo $request['course_type'] === 'technical' ? 'selected' : ''; ?>>Technical</option>
                                <option value="business" <?php echo $request['course_type'] === 'business' ? 'selected' : ''; ?>>Business</option>
                                <option value="language" <?php echo $request['course_type'] === 'language' ? 'selected' : ''; ?>>Language</option>
                                <option value="soft_skills" <?php echo $request['course_type'] === 'soft_skills' ? 'selected' : ''; ?>>Soft Skills</option>
                                <option value="certification" <?php echo $request['course_type'] === 'certification' ? 'selected' : ''; ?>>Certification</option>
                                <option value="workshop" <?php echo $request['course_type'] === 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                                <option value="seminar" <?php echo $request['course_type'] === 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                                <option value="other" <?php echo $request['course_type'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Experience Level</label>
                            <select name="experience_level" class="form-select" required>
                                <option value="">Select experience level</option>
                                <option value="beginner" <?php echo $request['experience_level'] === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                <option value="intermediate" <?php echo $request['experience_level'] === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                <option value="advanced" <?php echo $request['experience_level'] === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                <option value="expert" <?php echo $request['experience_level'] === 'expert' ? 'selected' : ''; ?>>Expert</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label required">Course Description</label>
                            <textarea name="course_description" class="form-textarea large" required><?php echo htmlspecialchars($request['course_description']); ?></textarea>
                            <div class="help-text">Provide a detailed description of what the course will cover</div>
                        </div>
                    </div>
                </div>

                <!-- Requirements -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user-check"></i>
                        Requirements
                    </h3>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label required">Required Qualifications</label>
                            <textarea name="required_qualifications" class="form-textarea" required><?php echo htmlspecialchars($request['required_qualifications']); ?></textarea>
                            <div class="help-text">List the educational and professional qualifications required</div>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label required">Skills Required</label>
                            <textarea name="skills_required" class="form-textarea" required><?php echo htmlspecialchars($request['skills_required']); ?></textarea>
                            <div class="help-text">List the specific skills and technologies the instructor should know</div>
                        </div>
                    </div>
                </div>

                <!-- Logistics -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-calendar-alt"></i>
                        Logistics
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Course Duration</label>
                            <input type="text" name="course_duration" class="form-input" value="<?php echo htmlspecialchars($request['course_duration']); ?>" required>
                            <div class="help-text">e.g., "8 weeks", "3 months", "40 hours"</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Location</label>
                            <input type="text" name="location" class="form-input" value="<?php echo htmlspecialchars($request['location']); ?>" required>
                            <div class="help-text">City, state, or "Online" for remote teaching</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Application Deadline</label>
                            <input type="date" name="application_deadline" class="form-input" value="<?php echo $request['application_deadline']; ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Maximum Applications</label>
                            <input type="number" name="max_applications" class="form-input" value="<?php echo $request['max_applications']; ?>" min="1">
                            <div class="help-text">Leave empty for unlimited applications</div>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="is_online" id="is_online" <?php echo $request['is_online'] ? 'checked' : ''; ?>>
                                <label for="is_online">This is an online course</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compensation -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-dollar-sign"></i>
                        Compensation
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Compensation Type</label>
                            <select name="compensation_type" class="form-select" required>
                                <option value="">Select compensation type</option>
                                <option value="hourly" <?php echo $request['compensation_type'] === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                <option value="salary" <?php echo $request['compensation_type'] === 'salary' ? 'selected' : ''; ?>>Salary</option>
                                <option value="project" <?php echo $request['compensation_type'] === 'project' ? 'selected' : ''; ?>>Project-based</option>
                                <option value="negotiable" <?php echo $request['compensation_type'] === 'negotiable' ? 'selected' : ''; ?>>Negotiable</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Compensation Amount</label>
                            <input type="number" name="compensation_amount" class="form-input" value="<?php echo $request['compensation_amount']; ?>" step="0.01" min="0" required>
                            <div class="help-text">Amount in USD</div>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-cog"></i>
                        Status
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Request Status</label>
                            <select name="status" class="form-select" required>
                                <option value="active" <?php echo $request['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="closed" <?php echo $request['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                <option value="filled" <?php echo $request['status'] === 'filled' ? 'selected' : ''; ?>>Filled</option>
                            </select>
                            <div class="help-text">Active: Accepting applications, Closed: No longer accepting, Filled: Position filled</div>
                        </div>
                    </div>
                </div>

                <div class="btn-group">
                    <a href="manage_instructor_requests.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

