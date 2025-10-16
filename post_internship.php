<?php
session_start();
include "connection.php";

// Check and add working_days column if it doesn't exist
$check_column = "SHOW COLUMNS FROM internships LIKE 'working_days'";
$result = $conn->query($check_column);
if ($result->num_rows == 0) {
    $add_column = "ALTER TABLE internships ADD COLUMN working_days TEXT DEFAULT NULL";
    $conn->query($add_column);
}

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

// Get company information
$company_id = $_SESSION['company_id'];
$stmt = $conn->prepare("SELECT * FROM companies WHERE company_id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();

if (!$company) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$success = "";
$error = "";
$edit_mode = false;
$internship_data = null;

// Check if we're editing an existing internship
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_mode = true;
    
    // Get the internship data
    $stmt = $conn->prepare("SELECT * FROM internships WHERE internship_id = ? AND company_id = ?");
    $stmt->bind_param("ii", $edit_id, $company_id);
    $stmt->execute();
    $internship_data = $stmt->get_result()->fetch_assoc();
    
    if (!$internship_data) {
        $error = "Internship not found or you don't have permission to edit it.";
        $edit_mode = false;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $requirements = $conn->real_escape_string($_POST['requirements']);
    $benefits = $conn->real_escape_string($_POST['benefits']);
    $location = $conn->real_escape_string($_POST['location']);
    $duration = $conn->real_escape_string($_POST['duration']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $stipend = $_POST['stipend'];
    $type = $_POST['type']; // full-time, part-time, remote
    $department = $conn->real_escape_string($_POST['department']);
    $skills_required = $conn->real_escape_string($_POST['skills_required']);
    $application_deadline = $_POST['application_deadline'];
    $max_applications = intval($_POST['max_applications']);
    
    // Handle working days array
    $working_days = '';
    if (isset($_POST['working_days']) && is_array($_POST['working_days'])) {
        $working_days = implode(',', $_POST['working_days']);
    }
    $working_days = $conn->real_escape_string($working_days);
    
    // Handle poster image upload
    $poster_path = null;
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_type = $_FILES['poster']['type'];
        $file_size = $_FILES['poster']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            $file_extension = pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION);
            $new_filename = 'poster_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['poster']['tmp_name'], $upload_path)) {
                $poster_path = $upload_path;
            } else {
                $error = "Failed to upload poster image. Please try again.";
            }
        } else {
            $error = "Invalid poster image. Please upload a valid image file (JPEG, PNG, GIF, WebP) under 5MB.";
        }
    }

    // Validate required fields
    if (empty($title) || empty($description) || empty($requirements) || empty($location) || empty($start_date) || empty($end_date)) {
        $error = "Please fill in all required fields.";
    } else {
        // Check if we're editing or creating new
        if (isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
            // Update existing internship
            $edit_id = intval($_POST['edit_id']);
            
            // Verify the internship belongs to this company
            $verify_stmt = $conn->prepare("SELECT internship_id FROM internships WHERE internship_id = ? AND company_id = ?");
            $verify_stmt->bind_param("ii", $edit_id, $company_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows > 0) {
                // Build the update query
                $update_fields = [
                    "title = '$title'",
                    "description = '$description'",
                    "requirements = '$requirements'",
                    "benefits = '$benefits'",
                    "location = '$location'",
                    "duration = '$duration'",
                    "start_date = '$start_date'",
                    "end_date = '$end_date'",
                    "stipend = '$stipend'",
                    "type = '$type'",
                    "department = '$department'",
                    "skills_required = '$skills_required'",
                    "application_deadline = '$application_deadline'",
                    "max_applications = '$max_applications'",
                    "working_days = '$working_days'",
                    "updated_at = NOW()"
                ];
                
                // Add poster path update only if a new poster was uploaded
                if ($poster_path) {
                    $update_fields[] = "poster_path = '" . $conn->real_escape_string($poster_path) . "'";
                }
                
                $sql = "UPDATE internships SET " . implode(", ", $update_fields) . 
                       " WHERE internship_id = $edit_id AND company_id = $company_id";

                if ($conn->query($sql)) {
                    $success = "Internship updated successfully!";
                } else {
                    $error = "Error updating internship: " . $conn->error;
                }
            } else {
                $error = "Internship not found or you don't have permission to edit it.";
            }
        } else {
            // Insert new internship into database
            $poster_path_escaped = $poster_path ? "'" . $conn->real_escape_string($poster_path) . "'" : "NULL";
            $sql = "INSERT INTO internships (
                company_id, poster_path, title, description, requirements, benefits, location, 
                duration, start_date, end_date, stipend, type, department, 
                skills_required, application_deadline, max_applications, working_days,
                status, created_at
            ) VALUES (
                '$company_id', $poster_path_escaped, '$title', '$description', '$requirements', '$benefits', 
                '$location', '$duration', '$start_date', '$end_date', '$stipend', 
                '$type', '$department', '$skills_required', '$application_deadline', 
                '$max_applications', '$working_days', 'active', NOW()
            )";

            if ($conn->query($sql)) {
                $success = "Internship posted successfully! Students can now apply for this position.";
            } else {
                $error = "Error posting internship: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edit_mode ? 'Edit Internship' : 'Post Internship'; ?> - Company Portal</title>
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
            --bg-gradient: linear-gradient(135deg, #0ea5a8 0%, #22d3ee 100%);
            --border-light: #e5e7eb;
            --border-focus: #0ea5a8;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
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
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .header h1 {
            color: var(--ink);
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: var(--muted);
            font-size: 1.1rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--muted);
        }

        .breadcrumb a {
            color: var(--brand);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb a:hover {
            color: var(--brand-2);
        }

        .form-container {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-label.required::after {
            content: " *";
            color: var(--error);
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
            color: var(--text-dark);
        }

        .form-input[type="file"] {
            padding: 0.5rem 1rem;
            cursor: pointer;
        }

        .form-input[type="file"]::-webkit-file-upload-button {
            background: var(--brand);
            color: var(--text-white);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            margin-right: 1rem;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .form-input[type="file"]::-webkit-file-upload-button:hover {
            background: var(--brand-2);
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-textarea.large {
            min-height: 200px;
        }

        .form-input::placeholder, .form-textarea::placeholder {
            color: var(--text-light);
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            color: var(--text-white);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-dark);
            border: 2px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--brand);
            color: var(--text-white);
            border-color: var(--brand);
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .message {
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .message-success {
            background: rgba(74, 222, 128, 0.1);
            border: 1px solid rgba(74, 222, 128, 0.3);
            color: #059669;
        }

        .message-error {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #dc2626;
        }

        .help-text {
            font-size: 0.875rem;
            color: var(--muted);
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

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
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
                <?php if ($edit_mode): ?>
                    <a href="manage_internships.php">Manage Internships</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Edit Internship</span>
                <?php else: ?>
                    <span>Post Internship</span>
                <?php endif; ?>
            </div>
            <h1><?php echo $edit_mode ? 'Edit Internship' : 'Post New Internship'; ?></h1>
            <p><?php echo $edit_mode ? 'Update your internship opportunity details.' : 'Create an internship opportunity to attract talented students to your company.'; ?></p>
        </div>

        <div class="form-container">
            <?php if ($success): ?>
                <div class="message message-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message message-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="post_internship.php" enctype="multipart/form-data">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $internship_data['internship_id']; ?>">
                <?php endif; ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">Internship Title</label>
                        <input type="text" name="title" class="form-input" placeholder="e.g., Software Development Intern" value="<?php echo $edit_mode ? htmlspecialchars($internship_data['title']) : ''; ?>" required>
                        <div class="help-text">A clear, descriptive title for the internship position</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Internship Poster</label>
                        <input type="file" name="poster" class="form-input" accept="image/*">
                        <div class="help-text">Upload an attractive poster for your internship (JPEG, PNG, GIF, WebP - Max 5MB)</div>
                        <?php if ($edit_mode && $internship_data['poster_path']): ?>
                            <div style="margin-top: 0.5rem;">
                                <small style="color: var(--muted);">Current poster:</small><br>
                                <img src="<?php echo htmlspecialchars($internship_data['poster_path']); ?>" alt="Current poster" style="max-width: 200px; max-height: 100px; border-radius: 8px; margin-top: 0.25rem;">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Department</label>
                        <input type="text" name="department" class="form-input" placeholder="e.g., Engineering, Marketing, HR" value="<?php echo $edit_mode ? htmlspecialchars($internship_data['department']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Location</label>
                        <input type="text" name="location" class="form-input" placeholder="e.g., New York, NY or Remote" value="<?php echo $edit_mode ? htmlspecialchars($internship_data['location']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="">Select type</option>
                            <option value="full-time" <?php echo ($edit_mode && $internship_data['type'] === 'full-time') ? 'selected' : ''; ?>>Full-time</option>
                            <option value="part-time" <?php echo ($edit_mode && $internship_data['type'] === 'part-time') ? 'selected' : ''; ?>>Part-time</option>
                            <option value="remote" <?php echo ($edit_mode && $internship_data['type'] === 'remote') ? 'selected' : ''; ?>>Remote</option>
                            <option value="hybrid" <?php echo ($edit_mode && $internship_data['type'] === 'hybrid') ? 'selected' : ''; ?>>Hybrid</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Duration</label>
                        <input type="text" name="duration" class="form-input" placeholder="e.g., 3 months, 6 months, 1 year" value="<?php echo $edit_mode ? htmlspecialchars($internship_data['duration']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Working Days</label>
                        <div class="days-selection">
                            <div class="days-grid">
                                <label class="day-checkbox">
                                    <input type="checkbox" name="working_days[]" value="monday" <?php echo ($edit_mode && strpos($internship_data['working_days'], 'monday') !== false) ? 'checked' : ''; ?>>
                                    <span class="day-label">Monday</span>
                                </label>
                                <label class="day-checkbox">
                                    <input type="checkbox" name="working_days[]" value="tuesday" <?php echo ($edit_mode && strpos($internship_data['working_days'], 'tuesday') !== false) ? 'checked' : ''; ?>>
                                    <span class="day-label">Tuesday</span>
                                </label>
                                <label class="day-checkbox">
                                    <input type="checkbox" name="working_days[]" value="wednesday" <?php echo ($edit_mode && strpos($internship_data['working_days'], 'wednesday') !== false) ? 'checked' : ''; ?>>
                                    <span class="day-label">Wednesday</span>
                                </label>
                                <label class="day-checkbox">
                                    <input type="checkbox" name="working_days[]" value="thursday" <?php echo ($edit_mode && strpos($internship_data['working_days'], 'thursday') !== false) ? 'checked' : ''; ?>>
                                    <span class="day-label">Thursday</span>
                                </label>
                                <label class="day-checkbox">
                                    <input type="checkbox" name="working_days[]" value="friday" <?php echo ($edit_mode && strpos($internship_data['working_days'], 'friday') !== false) ? 'checked' : ''; ?>>
                                    <span class="day-label">Friday</span>
                                </label>
                                <label class="day-checkbox">
                                    <input type="checkbox" name="working_days[]" value="saturday" <?php echo ($edit_mode && strpos($internship_data['working_days'], 'saturday') !== false) ? 'checked' : ''; ?>>
                                    <span class="day-label">Saturday</span>
                                </label>
                                <label class="day-checkbox">
                                    <input type="checkbox" name="working_days[]" value="sunday" <?php echo ($edit_mode && strpos($internship_data['working_days'], 'sunday') !== false) ? 'checked' : ''; ?>>
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
                        <div class="help-text">Select the specific days when the intern is expected to work</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Stipend/Salary</label>
                        <input type="text" name="stipend" class="form-input" placeholder="e.g., $15/hour, $2000/month, Unpaid" value="<?php echo $edit_mode ? htmlspecialchars($internship_data['stipend']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Start Date</label>
                        <input type="date" name="start_date" class="form-input" value="<?php echo $edit_mode ? $internship_data['start_date'] : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">End Date</label>
                        <input type="date" name="end_date" class="form-input" value="<?php echo $edit_mode ? $internship_data['end_date'] : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Application Deadline</label>
                        <input type="date" name="application_deadline" class="form-input" value="<?php echo $edit_mode ? $internship_data['application_deadline'] : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Maximum Applications</label>
                        <input type="number" name="max_applications" class="form-input" placeholder="Leave empty for unlimited" min="1" value="<?php echo $edit_mode ? $internship_data['max_applications'] : ''; ?>">
                        <div class="help-text">Limit the number of applications you want to receive</div>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label class="form-label required">Job Description</label>
                    <textarea name="description" class="form-textarea large" placeholder="Describe the internship role, responsibilities, and what the intern will learn..." required><?php echo $edit_mode ? htmlspecialchars($internship_data['description']) : ''; ?></textarea>
                </div>

                <div class="form-group full-width">
                    <label class="form-label required">Requirements</label>
                    <textarea name="requirements" class="form-textarea" placeholder="List the skills, qualifications, and requirements for this internship..." required><?php echo $edit_mode ? htmlspecialchars($internship_data['requirements']) : ''; ?></textarea>
                </div>

                <div class="form-group full-width">
                    <label class="form-label">Skills Required</label>
                    <textarea name="skills_required" class="form-textarea" placeholder="e.g., Python, JavaScript, React, Communication skills, etc."><?php echo $edit_mode ? htmlspecialchars($internship_data['skills_required']) : ''; ?></textarea>
                </div>

                <div class="form-group full-width">
                    <label class="form-label">Benefits & Perks</label>
                    <textarea name="benefits" class="form-textarea" placeholder="List any benefits, perks, or learning opportunities you offer..."><?php echo $edit_mode ? htmlspecialchars($internship_data['benefits']) : ''; ?></textarea>
                </div>

                <div class="btn-group">
                    <a href="<?php echo $edit_mode ? 'manage_internships.php' : 'company_dashboard.php'; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-<?php echo $edit_mode ? 'save' : 'paper-plane'; ?>"></i>
                        <?php echo $edit_mode ? 'Update Internship' : 'Post Internship'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const startDate = new Date(document.querySelector('input[name="start_date"]').value);
            const endDate = new Date(document.querySelector('input[name="end_date"]').value);
            const deadline = new Date(document.querySelector('input[name="application_deadline"]').value);
            const today = new Date();

            if (startDate <= today) {
                alert('Start date must be in the future.');
                e.preventDefault();
                return;
            }

            if (endDate <= startDate) {
                alert('End date must be after start date.');
                e.preventDefault();
                return;
            }

            if (deadline <= today) {
                alert('Application deadline must be in the future.');
                e.preventDefault();
                return;
            }

            if (deadline >= startDate) {
                alert('Application deadline should be before the start date.');
                e.preventDefault();
                return;
            }
        });

        // Auto-fill end date based on duration
        document.querySelector('input[name="duration"]').addEventListener('input', function() {
            const duration = this.value.toLowerCase();
            const startDate = document.querySelector('input[name="start_date"]').value;
            
            if (startDate && duration.includes('month')) {
                const months = parseInt(duration.match(/\d+/)?.[0] || 3);
                const start = new Date(startDate);
                start.setMonth(start.getMonth() + months);
                document.querySelector('input[name="end_date"]').value = start.toISOString().split('T')[0];
            }
        });

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

        // Auto-suggest working days based on internship type
        document.querySelector('select[name="type"]').addEventListener('change', function() {
            const type = this.value;
            
            // Clear current selection first
            clearAllDays();
            
            // Suggest working days based on type
            if (type === 'full-time') {
                selectWeekdays();
            } else if (type === 'part-time') {
                // Select 3 random weekdays
                const weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
                const selectedDays = weekdays.slice(0, 3);
                const checkboxes = document.querySelectorAll('input[name="working_days[]"]');
                
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectedDays.includes(checkbox.value);
                });
            } else if (type === 'remote') {
                // For remote, don't auto-select any days
                clearAllDays();
            }
        });
    </script>
</body>
</html>

