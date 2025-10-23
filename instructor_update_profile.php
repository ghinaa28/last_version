<?php
session_start();
include "connection.php";

// Check if user is logged in as instructor
if (!isset($_SESSION['instructor_id'])) {
    header("Location: login.php");
    exit();
}

// Get instructor information
$instructor_id = $_SESSION['instructor_id'];
$stmt = $conn->prepare("SELECT * FROM instructors WHERE instructor_id = ?");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$instructor = $stmt->get_result()->fetch_assoc();

if (!$instructor) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle form submission for profile updates
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Sanitize and validate input data
    $first_name = trim($conn->real_escape_string($_POST['first_name']));
    $last_name = trim($conn->real_escape_string($_POST['last_name']));
    $university = !empty($_POST['university']) ? trim($conn->real_escape_string($_POST['university'])) : null;
    $department = !empty($_POST['department']) ? trim($conn->real_escape_string($_POST['department'])) : null;
    $position = !empty($_POST['position']) ? trim($conn->real_escape_string($_POST['position'])) : null;
    $phone = !empty($_POST['phone']) ? trim($conn->real_escape_string($_POST['phone'])) : null;
    $employee_id = !empty($_POST['employee_id']) ? trim($conn->real_escape_string($_POST['employee_id'])) : null;
    $years_experience = !empty($_POST['years_experience']) ? intval($_POST['years_experience']) : null;
    $highest_degree = !empty($_POST['highest_degree']) ? trim($conn->real_escape_string($_POST['highest_degree'])) : null;
    $specialties = !empty($_POST['specialties']) ? trim($conn->real_escape_string($_POST['specialties'])) : null;
    $bio = !empty($_POST['bio']) ? trim($conn->real_escape_string($_POST['bio'])) : null;
    
    // Validation
    $errors = [];
    
    // Required field validation
    if (empty($first_name) || strlen($first_name) < 2) {
        $errors[] = "First name must be at least 2 characters long.";
    }
    if (empty($last_name) || strlen($last_name) < 2) {
        $errors[] = "Last name must be at least 2 characters long.";
    }
    
    // Phone validation (if provided)
    if (!empty($phone) && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,15}$/', $phone)) {
        $errors[] = "Please enter a valid phone number.";
    }
    
    // Years of experience validation (if provided)
    if (!empty($years_experience) && ($years_experience < 0 || $years_experience > 50)) {
        $errors[] = "Years of experience must be between 0 and 50.";
    }
    
    // If no validation errors, update the profile
    if (empty($errors)) {
        $update_sql = "UPDATE instructors SET 
                      first_name = ?, 
                      last_name = ?, 
                      university = ?,
                      department = ?,
                      position = ?,
                      phone = ?,
                      employee_id = ?,
                      years_experience = ?,
                      highest_degree = ?,
                      specialties = ?,
                      bio = ?
                      WHERE instructor_id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssssssssssi", $first_name, $last_name, $university, $department, $position, $phone, $employee_id, $years_experience, $highest_degree, $specialties, $bio, $instructor_id);
        
        if ($stmt->execute()) {
            $success_message = "Instructor profile updated successfully!";
            // Refresh instructor data
            $stmt = $conn->prepare("SELECT * FROM instructors WHERE instructor_id = ?");
            $stmt->bind_param("i", $instructor_id);
            $stmt->execute();
            $instructor = $stmt->get_result()->fetch_assoc();
        } else {
            $error_message = "Error updating profile: " . $conn->error;
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile - Instructor Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #0ea5a8;
            --brand-2: #22d3ee;
            --ink: #0b1f3a;
            --muted: #475569;
            --panel: #ffffff;
            --bg-primary: #f8fafc;
            --bg-secondary: #f1f5f9;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --text-white: #ffffff;
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--ink);
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

        .page-title {
            font-size: 2.5rem;
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
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-light);
            max-width: 800px;
            margin: 0 auto;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-light);
        }

        .form-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .form-subtitle {
            color: var(--muted);
            font-size: 1rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-label.required::after {
            content: " *";
            color: var(--error);
        }

        .form-input {
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
            color: var(--ink);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .form-input:disabled {
            background: var(--bg-secondary);
            color: var(--muted);
            cursor: not-allowed;
        }

        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-light);
        }

        .btn {
            padding: 0.875rem 2rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            border: none;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--brand);
            color: var(--text-white);
        }

        .btn-primary:hover {
            background: var(--brand-2);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--muted);
            border: 2px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--border-light);
            color: var(--ink);
        }

        .message {
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .message-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #059669;
        }

        .message-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #dc2626;
        }

        .help-text {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 0.25rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .form-container {
                padding: 1.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
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
                <a href="instructor_profile.php">Instructor Profile</a>
                <i class="fas fa-chevron-right"></i>
                <span>Update Profile</span>
            </div>
            <h1 class="page-title">Update Instructor Profile</h1>
            <p class="page-subtitle">Keep your instructor information up to date</p>
        </div>

        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">
                    <i class="fas fa-user-edit"></i>
                    Update Personal Information
                </h2>
                <p class="form-subtitle">Modify your instructor profile details and expertise</p>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="message message-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="message message-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" enctype="multipart/form-data">
                <div class="form-grid">
                    <!-- Personal Information -->
                    <div class="form-group">
                        <label class="form-label required">First Name</label>
                        <input type="text" name="first_name" class="form-input" 
                               value="<?php echo htmlspecialchars($instructor['first_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Last Name</label>
                        <input type="text" name="last_name" class="form-input" 
                               value="<?php echo htmlspecialchars($instructor['last_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-input" 
                               value="<?php echo htmlspecialchars($instructor['phone'] ?? ''); ?>" 
                               placeholder="Enter your phone number">
                        <div class="help-text">Optional - Include country code if international</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Employee ID</label>
                        <input type="text" name="employee_id" class="form-input" 
                               value="<?php echo htmlspecialchars($instructor['employee_id'] ?? ''); ?>" 
                               placeholder="Enter your employee ID">
                        <div class="help-text">Optional - Your university employee ID</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" 
                               value="<?php echo htmlspecialchars($instructor['email']); ?>" disabled>
                        <div class="help-text">Email cannot be changed. Contact support if needed.</div>
                    </div>

                    <!-- Academic Information -->
                    <div class="form-group">
                        <label class="form-label">University</label>
                        <input type="text" name="university" class="form-input" 
                               value="<?php echo htmlspecialchars($instructor['university'] ?? ''); ?>" 
                               placeholder="Enter your university">
                        <div class="help-text">Optional - Your current university</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <input type="text" name="department" class="form-input" 
                               value="<?php echo htmlspecialchars($instructor['department'] ?? ''); ?>" 
                               placeholder="Enter your department">
                        <div class="help-text">Optional - Your academic department</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" class="form-input" 
                               value="<?php echo htmlspecialchars($instructor['position'] ?? ''); ?>" 
                               placeholder="e.g., Professor, Associate Professor, Lecturer">
                        <div class="help-text">Optional - Your academic position</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Years of Experience</label>
                        <input type="number" name="years_experience" class="form-input" 
                               value="<?php echo htmlspecialchars($instructor['years_experience'] ?? ''); ?>" 
                               min="0" max="50" placeholder="e.g., 10">
                        <div class="help-text">Optional - Years of teaching experience</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Highest Degree</label>
                        <select name="highest_degree" class="form-input">
                            <option value="">Select highest degree</option>
                            <option value="Bachelor's" <?php echo ($instructor['highest_degree'] ?? '') == 'Bachelor\'s' ? 'selected' : ''; ?>>Bachelor's</option>
                            <option value="Master's" <?php echo ($instructor['highest_degree'] ?? '') == 'Master\'s' ? 'selected' : ''; ?>>Master's</option>
                            <option value="PhD" <?php echo ($instructor['highest_degree'] ?? '') == 'PhD' ? 'selected' : ''; ?>>PhD</option>
                            <option value="Post-Doctoral" <?php echo ($instructor['highest_degree'] ?? '') == 'Post-Doctoral' ? 'selected' : ''; ?>>Post-Doctoral</option>
                        </select>
                        <div class="help-text">Optional - Your highest academic degree</div>
                    </div>

                    <!-- Specialties & Bio -->
                    <div class="form-group full-width">
                        <label class="form-label">Specialties & Expertise</label>
                        <textarea name="specialties" class="form-input" rows="3" 
                                  placeholder="List your areas of expertise, separated by commas (e.g., Machine Learning, Data Science, Software Engineering)"><?php echo htmlspecialchars($instructor['specialties'] ?? ''); ?></textarea>
                        <div class="help-text">Optional - List your areas of expertise and specializations</div>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Biography</label>
                        <textarea name="bio" class="form-input" rows="6" 
                                  placeholder="Tell us about your background, experience, and teaching philosophy..."><?php echo htmlspecialchars($instructor['bio'] ?? ''); ?></textarea>
                        <div class="help-text">Optional - Share your background and teaching experience</div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="instructor_profile.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-hide success messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.querySelector('.message-success');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        successMessage.remove();
                    }, 300);
                }, 5000);
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const firstName = document.querySelector('input[name="first_name"]').value.trim();
            const lastName = document.querySelector('input[name="last_name"]').value.trim();

            if (!firstName || !lastName) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }

            // Basic name validation
            if (firstName.length < 2 || lastName.length < 2) {
                e.preventDefault();
                alert('First name and last name must be at least 2 characters long.');
                return false;
            }

            // Years of experience validation
            const yearsExp = document.querySelector('input[name="years_experience"]').value;
            if (yearsExp && (parseInt(yearsExp) < 0 || parseInt(yearsExp) > 50)) {
                e.preventDefault();
                alert('Years of experience must be between 0 and 50.');
                return false;
            }
        });
    </script>
</body>
</html>
