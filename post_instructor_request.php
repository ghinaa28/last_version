<?php
session_start();
require_once 'connection.php';

// Check and add working_days column if it doesn't exist
$check_column = "SHOW COLUMNS FROM instructor_requests LIKE 'working_days'";
$result = $conn->query($check_column);
if ($result->num_rows == 0) {
    $add_column = "ALTER TABLE instructor_requests ADD COLUMN working_days TEXT DEFAULT NULL";
    $conn->query($add_column);
}

// Check and add field/area_of_expertise column if it doesn't exist
$check_field_column = "SHOW COLUMNS FROM instructor_requests LIKE 'area_of_expertise'";
$result_field = $conn->query($check_field_column);
if ($result_field->num_rows == 0) {
    $add_field_column = "ALTER TABLE instructor_requests ADD COLUMN area_of_expertise VARCHAR(100) DEFAULT NULL";
    $conn->query($add_field_column);
}

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];
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
        $area_of_expertise = $conn->real_escape_string($_POST['area_of_expertise']);
        
        // Handle working days array
        $working_days = '';
        if (isset($_POST['working_days']) && is_array($_POST['working_days'])) {
            $working_days = implode(',', $_POST['working_days']);
        }
        $working_days = $conn->real_escape_string($working_days);
        
        // Validate required fields
        if (empty($course_title) || empty($course_description) || empty($required_qualifications) || empty($skills_required) || empty($course_duration) || empty($location) || empty($compensation_type) || empty($compensation_amount) || empty($application_deadline)) {
            $error_message = "Please fill in all required fields.";
        } else {
            // Insert instructor request into database
            $sql = "INSERT INTO instructor_requests (
                company_id, course_title, course_description, required_qualifications, 
                skills_required, course_duration, location, is_online, compensation_type, 
                compensation_amount, application_deadline, max_applications, course_type, 
                experience_level, area_of_expertise, working_days, status, created_at
            ) VALUES (
                '$company_id', '$course_title', '$course_description', '$required_qualifications', 
                '$skills_required', '$course_duration', '$location', '$is_online', '$compensation_type', 
                '$compensation_amount', '$application_deadline', '$max_applications', '$course_type', 
                '$experience_level', '$area_of_expertise', '$working_days', 'active', NOW()
            )";

            if ($conn->query($sql)) {
                $success_message = "Instructor request posted successfully! Instructors can now apply for this opportunity.";
            } else {
                $error_message = "Error posting instructor request: " . $conn->error;
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
    <title>Post Instructor Request - Company Portal</title>
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
            --transition-fast: all 0.15s ease;
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
            flex-wrap: wrap;
            gap: 1rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-item input[type="checkbox"] {
            width: 1.2rem;
            height: 1.2rem;
            accent-color: var(--brand);
        }

        .checkbox-item label {
            font-size: 0.9rem;
            color: var(--text-dark);
            cursor: pointer;
        }

        .help-text {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 0.25rem;
        }

        .days-selection {
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            background: var(--bg-primary);
        }

        .days-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .day-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            background: var(--bg-secondary);
        }

        .day-checkbox:hover {
            border-color: var(--brand);
            background: rgba(14, 165, 168, 0.05);
        }

        .day-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--brand);
            cursor: pointer;
        }

        .day-checkbox input[type="checkbox"]:checked + .day-label {
            color: var(--brand);
            font-weight: 600;
        }

        .day-checkbox:has(input[type="checkbox"]:checked) {
            border-color: var(--brand);
            background: rgba(14, 165, 168, 0.1);
        }

        .day-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-dark);
            cursor: pointer;
            transition: var(--transition);
        }

        .quick-select-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
        }

        .quick-select-btn {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            background: var(--bg-secondary);
            color: var(--text-dark);
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .quick-select-btn:hover {
            border-color: var(--brand);
            background: var(--brand);
            color: var(--text-white);
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

        .compensation-group {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1rem;
            align-items: end;
        }

        .location-group {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1rem;
            align-items: end;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .compensation-group,
            .location-group {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .days-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .quick-select-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .quick-select-btn {
                text-align: center;
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
                <span>Post Instructor Request</span>
            </div>
            <h1 class="page-title">Post Instructor Request</h1>
            <p class="page-subtitle">Create a new instructor opportunity for your courses and training programs.</p>
        </div>

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

            <form method="POST" action="post_instructor_request.php">
                <!-- Course Information -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-graduation-cap"></i>
                        Course Information
                    </h3>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label required">Course Title</label>
                            <input type="text" name="course_title" class="form-input" placeholder="e.g., Advanced Web Development with React" required>
                            <div class="help-text">A clear, descriptive title for the course you need an instructor for</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Course Type</label>
                            <select name="course_type" class="form-select" required>
                                <option value="">Select course type</option>
                                <option value="technical">Technical</option>
                                <option value="business">Business</option>
                                <option value="language">Language</option>
                                <option value="soft_skills">Soft Skills</option>
                                <option value="certification">Certification</option>
                                <option value="workshop">Workshop</option>
                                <option value="seminar">Seminar</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Experience Level</label>
                            <select name="experience_level" class="form-select" required>
                                <option value="">Select level</option>
                                <option value="beginner">Beginner</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                                <option value="expert">Expert</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label required">Course Description</label>
                            <textarea name="course_description" class="form-textarea large" placeholder="Describe the course content, objectives, and what students will learn..." required></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Course Duration</label>
                            <input type="text" name="course_duration" class="form-input" placeholder="e.g., 8 weeks, 40 hours, 3 months" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Working Days</label>
                            <div class="days-selection">
                                <div class="days-grid">
                                    <label class="day-checkbox">
                                        <input type="checkbox" name="working_days[]" value="monday">
                                        <span class="day-label">Monday</span>
                                    </label>
                                    <label class="day-checkbox">
                                        <input type="checkbox" name="working_days[]" value="tuesday">
                                        <span class="day-label">Tuesday</span>
                                    </label>
                                    <label class="day-checkbox">
                                        <input type="checkbox" name="working_days[]" value="wednesday">
                                        <span class="day-label">Wednesday</span>
                                    </label>
                                    <label class="day-checkbox">
                                        <input type="checkbox" name="working_days[]" value="thursday">
                                        <span class="day-label">Thursday</span>
                                    </label>
                                    <label class="day-checkbox">
                                        <input type="checkbox" name="working_days[]" value="friday">
                                        <span class="day-label">Friday</span>
                                    </label>
                                    <label class="day-checkbox">
                                        <input type="checkbox" name="working_days[]" value="saturday">
                                        <span class="day-label">Saturday</span>
                                    </label>
                                    <label class="day-checkbox">
                                        <input type="checkbox" name="working_days[]" value="sunday">
                                        <span class="day-label">Sunday</span>
                                    </label>
                                </div>
                                <div class="quick-select-buttons">
                                    <button type="button" class="quick-select-btn" onclick="selectWeekdays()">Weekdays (Mon-Fri)</button>
                                    <button type="button" class="quick-select-btn" onclick="selectWeekend()">Weekend (Sat-Sun)</button>
                                    <button type="button" class="quick-select-btn" onclick="selectAllDays()">All Days</button>
                                    <button type="button" class="quick-select-btn" onclick="clearAllDays()">Clear All</button>
                                </div>
                            </div>
                            <div class="help-text">Select the specific days when the instructor is expected to work</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Maximum Applications</label>
                            <input type="number" name="max_applications" class="form-input" placeholder="Leave empty for unlimited" min="1">
                            <div class="help-text">Limit the number of applications you want to receive</div>
                        </div>
                    </div>
                </div>

                <!-- Location & Delivery -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Location & Delivery
                    </h3>
                    <div class="location-group">
                        <div class="form-group">
                            <label class="form-label required">Location</label>
                            <input type="text" name="location" class="form-input" placeholder="e.g., New York, NY or Online Platform" required>
                        </div>
                        <div class="form-group">
                            <div class="checkbox-item">
                                <input type="checkbox" name="is_online" id="is_online">
                                <label for="is_online">Online Course</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Requirements -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user-check"></i>
                        Instructor Requirements
                    </h3>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label required">Required Qualifications</label>
                            <textarea name="required_qualifications" class="form-textarea" placeholder="e.g., Bachelor's degree in Computer Science, 3+ years teaching experience, industry certifications..." required></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Area of Expertise</label>
                            <select name="area_of_expertise" class="form-select" required>
                                <option value="">Select field of expertise</option>
                                <optgroup label="Engineering & Technology">
                                    <option value="computer_science">Computer Science</option>
                                    <option value="software_engineering">Software Engineering</option>
                                    <option value="information_technology">Information Technology</option>
                                    <option value="data_science">Data Science</option>
                                    <option value="artificial_intelligence">Artificial Intelligence</option>
                                    <option value="cybersecurity">Cybersecurity</option>
                                    <option value="mechanical_engineering">Mechanical Engineering</option>
                                    <option value="electrical_engineering">Electrical Engineering</option>
                                    <option value="civil_engineering">Civil Engineering</option>
                                    <option value="chemical_engineering">Chemical Engineering</option>
                                    <option value="biomedical_engineering">Biomedical Engineering</option>
                                    <option value="aerospace_engineering">Aerospace Engineering</option>
                                </optgroup>
                                <optgroup label="Business & Management">
                                    <option value="business_administration">Business Administration</option>
                                    <option value="project_management">Project Management</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="finance">Finance</option>
                                    <option value="accounting">Accounting</option>
                                    <option value="human_resources">Human Resources</option>
                                    <option value="operations_management">Operations Management</option>
                                    <option value="entrepreneurship">Entrepreneurship</option>
                                    <option value="supply_chain">Supply Chain Management</option>
                                </optgroup>
                                <optgroup label="Healthcare & Life Sciences">
                                    <option value="medicine">Medicine</option>
                                    <option value="nursing">Nursing</option>
                                    <option value="pharmacy">Pharmacy</option>
                                    <option value="public_health">Public Health</option>
                                    <option value="biology">Biology</option>
                                    <option value="chemistry">Chemistry</option>
                                    <option value="biotechnology">Biotechnology</option>
                                    <option value="psychology">Psychology</option>
                                </optgroup>
                                <optgroup label="Education & Social Sciences">
                                    <option value="education">Education</option>
                                    <option value="curriculum_development">Curriculum Development</option>
                                    <option value="educational_technology">Educational Technology</option>
                                    <option value="language_teaching">Language Teaching</option>
                                    <option value="communication">Communication</option>
                                    <option value="journalism">Journalism</option>
                                    <option value="social_work">Social Work</option>
                                    <option value="counseling">Counseling</option>
                                </optgroup>
                                <optgroup label="Creative & Design">
                                    <option value="graphic_design">Graphic Design</option>
                                    <option value="web_design">Web Design</option>
                                    <option value="ui_ux_design">UI/UX Design</option>
                                    <option value="architecture">Architecture</option>
                                    <option value="interior_design">Interior Design</option>
                                    <option value="fashion_design">Fashion Design</option>
                                    <option value="fine_arts">Fine Arts</option>
                                    <option value="music">Music</option>
                                </optgroup>
                                <optgroup label="Mathematics & Sciences">
                                    <option value="mathematics">Mathematics</option>
                                    <option value="statistics">Statistics</option>
                                    <option value="physics">Physics</option>
                                    <option value="environmental_science">Environmental Science</option>
                                    <option value="geology">Geology</option>
                                    <option value="agriculture">Agriculture</option>
                                </optgroup>
                                <optgroup label="Legal & Compliance">
                                    <option value="law">Law</option>
                                    <option value="legal_compliance">Legal Compliance</option>
                                    <option value="intellectual_property">Intellectual Property</option>
                                    <option value="corporate_governance">Corporate Governance</option>
                                </optgroup>
                                <optgroup label="Other">
                                    <option value="sports_fitness">Sports & Fitness</option>
                                    <option value="hospitality">Hospitality</option>
                                    <option value="logistics">Logistics</option>
                                    <option value="quality_assurance">Quality Assurance</option>
                                    <option value="other">Other</option>
                                </optgroup>
                            </select>
                            <div class="help-text">Select the specific field or area of expertise required for this instructor position</div>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label required">Skills Required</label>
                            <textarea name="skills_required" class="form-textarea" placeholder="e.g., React, Node.js, JavaScript, Teaching experience, Communication skills..." required></textarea>
                        </div>
                    </div>
                </div>

                <!-- Compensation -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-dollar-sign"></i>
                        Compensation
                    </h3>
                    <div class="compensation-group">
                        <div class="form-group">
                            <label class="form-label required">Compensation Type</label>
                            <select name="compensation_type" class="form-select" required>
                                <option value="">Select type</option>
                                <option value="hourly">Hourly Rate</option>
                                <option value="salary">Fixed Salary</option>
                                <option value="project">Project-based</option>
                                <option value="negotiable">Negotiable</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Amount ($)</label>
                            <input type="number" name="compensation_amount" class="form-input" min="0" step="0.01" placeholder="e.g., 50.00" required>
                            <div class="help-text">Enter the compensation amount</div>
                        </div>
                    </div>
                </div>

                <!-- Application Details -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-calendar-alt"></i>
                        Application Details
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Application Deadline</label>
                            <input type="date" name="application_deadline" class="form-input" required>
                        </div>
                    </div>
                </div>

                <div class="btn-group">
                    <a href="company_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Post Instructor Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Quick select functions for working days
        function selectWeekdays() {
            const weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
            const checkboxes = document.querySelectorAll('input[name="working_days[]"]');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = weekdays.includes(checkbox.value);
            });
        }

        function selectWeekend() {
            const weekend = ['saturday', 'sunday'];
            const checkboxes = document.querySelectorAll('input[name="working_days[]"]');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = weekend.includes(checkbox.value);
            });
        }

        function selectAllDays() {
            const checkboxes = document.querySelectorAll('input[name="working_days[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
        }

        function clearAllDays() {
            const checkboxes = document.querySelectorAll('input[name="working_days[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        }
    </script>
</body>
</html>
