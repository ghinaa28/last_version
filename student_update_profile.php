<?php
session_start();
include "connection.php";

// Check if user is logged in as student
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

// Get student information
$student_id = $_SESSION['student_id'];
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle form submission for profile updates
$success_message = "";
$error_message = "";

// Debug: Check if form is being submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Form submitted with method: " . $_SERVER['REQUEST_METHOD']);
    error_log("POST data: " . print_r($_POST, true));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Sanitize and validate input data
    $first_name = trim($conn->real_escape_string($_POST['first_name']));
    $last_name = trim($conn->real_escape_string($_POST['last_name']));
    $university = trim($conn->real_escape_string($_POST['university']));
    $department = trim($conn->real_escape_string($_POST['department']));
    $phone = trim($conn->real_escape_string($_POST['phone']));
    $student_id_number = trim($conn->real_escape_string($_POST['student_id_number']));
    $academic_year = trim($conn->real_escape_string($_POST['academic_year']));
    $gpa = !empty($_POST['gpa']) ? floatval($_POST['gpa']) : null;
    $expected_graduation = !empty($_POST['expected_graduation']) ? $conn->real_escape_string($_POST['expected_graduation']) : null;
    $training_interests = !empty($_POST['training_interests']) ? trim($conn->real_escape_string($_POST['training_interests'])) : null;
    $preferred_fields = !empty($_POST['preferred_fields']) ? trim($conn->real_escape_string($_POST['preferred_fields'])) : null;
    $skills = !empty($_POST['skills']) ? trim($conn->real_escape_string($_POST['skills'])) : null;
    
    // Validation
    $errors = [];
    
    // Required field validation
    if (empty($first_name) || strlen($first_name) < 2) {
        $errors[] = "First name must be at least 2 characters long.";
    }
    if (empty($last_name) || strlen($last_name) < 2) {
        $errors[] = "Last name must be at least 2 characters long.";
    }
    if (empty($university) || strlen($university) < 2) {
        $errors[] = "University name must be at least 2 characters long.";
    }
    if (empty($department) || strlen($department) < 2) {
        $errors[] = "Department must be at least 2 characters long.";
    }
    if (empty($student_id_number) || strlen($student_id_number) < 3) {
        $errors[] = "Student ID number must be at least 3 characters long.";
    }
    if (empty($academic_year)) {
        $errors[] = "Academic year is required.";
    }
    
    // GPA validation
    if ($gpa !== null && ($gpa < 0 || $gpa > 4)) {
        $errors[] = "GPA must be between 0.0 and 4.0.";
    }
    
    // Phone validation (if provided)
    if (!empty($phone) && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,15}$/', $phone)) {
        $errors[] = "Please enter a valid phone number.";
    }
    
    // Check for duplicate student ID number (excluding current student)
    if (!empty($student_id_number)) {
        $check_sql = "SELECT student_id FROM students WHERE student_id_number = ? AND student_id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $student_id_number, $student_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Student ID number is already in use by another student.";
        }
    }
    
    // Handle profile photo upload
    $profile_photo_path = $student['profile_photo']; // Keep existing photo by default
    
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "uploads/students/profile_photos/";
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_info = pathinfo($_FILES['profile_photo']['name']);
        $file_extension = strtolower($file_info['extension']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        // Validate file type
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "Profile photo must be a JPG, PNG, or GIF file.";
        }
        
        // Validate file size (max 2MB)
        if ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
            $errors[] = "Profile photo must be smaller than 2MB.";
        }
        
        // Validate image dimensions
        $image_info = getimagesize($_FILES['profile_photo']['tmp_name']);
        if ($image_info === false) {
            $errors[] = "Invalid image file.";
        } else {
            $max_width = 800;
            $max_height = 800;
            if ($image_info[0] > $max_width || $image_info[1] > $max_height) {
                $errors[] = "Profile photo dimensions must not exceed {$max_width}x{$max_height} pixels.";
            }
        }
        
        if (empty($errors)) {
            // Generate unique filename
            $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                // Delete old profile photo if it exists
                if (!empty($student['profile_photo']) && file_exists($student['profile_photo'])) {
                    unlink($student['profile_photo']);
                }
                $profile_photo_path = $upload_path;
            } else {
                $errors[] = "Failed to upload profile photo.";
            }
        }
    }
    
    // If no validation errors, update the profile
    if (empty($errors)) {
        $update_sql = "UPDATE students SET 
                      first_name = ?, 
                      last_name = ?, 
                      university = ?, 
                      department = ?, 
                      phone = ?,
                      student_id_number = ?,
                      academic_year = ?,
                      gpa = ?,
                      expected_graduation = ?,
                      training_interests = ?,
                      preferred_fields = ?,
                      skills = ?,
                      profile_photo = ?
                      WHERE student_id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssssssssssssi", $first_name, $last_name, $university, $department, $phone, $student_id_number, $academic_year, $gpa, $expected_graduation, $training_interests, $preferred_fields, $skills, $profile_photo_path, $student_id);
        
        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Refresh student data
            $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();
        } else {
            $error_message = "Error updating profile: " . $conn->error;
            error_log("Database error: " . $conn->error);
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
    <title>Update Profile - Student Portal</title>
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

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1rem;
            border: 2px dashed var(--border);
            border-radius: var(--radius-lg);
            background: var(--bg-primary);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .file-input-label:hover {
            border-color: var(--brand);
            background: rgba(14, 165, 168, 0.05);
        }

        .current-photo {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
        }

        .current-photo img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
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
                <a href="student_dashboard.php">Student Portal</a>
                <i class="fas fa-chevron-right"></i>
                <a href="student_profile.php">My Profile</a>
                <i class="fas fa-chevron-right"></i>
                <span>Update Profile</span>
            </div>
            <h1 class="page-title">Update Profile</h1>
            <p class="page-subtitle">Keep your profile information up to date</p>
        </div>

        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">
                    <i class="fas fa-user-edit"></i>
                    Update Personal Information
                </h2>
                <p class="form-subtitle">Modify your profile details and preferences</p>
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

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <div class="message" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); color: #1d4ed8;">
                    <i class="fas fa-info-circle"></i>
                    Form submitted successfully! Processing your request...
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" enctype="multipart/form-data">
                <div class="form-grid">
                    <!-- Personal Information -->
                    <div class="form-group">
                        <label class="form-label required">First Name</label>
                        <input type="text" name="first_name" class="form-input" 
                               value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Last Name</label>
                        <input type="text" name="last_name" class="form-input" 
                               value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-input" 
                               value="<?php echo htmlspecialchars($student['phone']); ?>"
                               placeholder="Enter your phone number">
                        <div class="help-text">Optional - Include country code if international</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" 
                               value="<?php echo htmlspecialchars($student['email']); ?>" disabled>
                        <div class="help-text">Email cannot be changed. Contact support if needed.</div>
                    </div>

                    <!-- Academic Information -->
                    <div class="form-group">
                        <label class="form-label required">University</label>
                        <input type="text" name="university" class="form-input" 
                               value="<?php echo htmlspecialchars($student['university']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Department/Major</label>
                        <input type="text" name="department" class="form-input" 
                               value="<?php echo htmlspecialchars($student['department']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Student ID Number</label>
                        <input type="text" name="student_id_number" class="form-input" 
                               value="<?php echo htmlspecialchars($student['student_id_number'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Academic Year</label>
                        <select name="academic_year" class="form-input" required>
                            <option value="">Select your academic year</option>
                            <option value="1st Year" <?php echo ($student['academic_year'] ?? '') == '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                            <option value="2nd Year" <?php echo ($student['academic_year'] ?? '') == '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                            <option value="3rd Year" <?php echo ($student['academic_year'] ?? '') == '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                            <option value="4th Year" <?php echo ($student['academic_year'] ?? '') == '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                            <option value="5th Year" <?php echo ($student['academic_year'] ?? '') == '5th Year' ? 'selected' : ''; ?>>5th Year</option>
                            <option value="Graduate" <?php echo ($student['academic_year'] ?? '') == 'Graduate' ? 'selected' : ''; ?>>Graduate</option>
                            <option value="Postgraduate" <?php echo ($student['academic_year'] ?? '') == 'Postgraduate' ? 'selected' : ''; ?>>Postgraduate</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">GPA</label>
                        <input type="number" name="gpa" class="form-input" 
                               value="<?php echo htmlspecialchars($student['gpa'] ?? ''); ?>" 
                               step="0.01" min="0" max="4" placeholder="0.0-4.0">
                        <div class="help-text">Optional - Enter your current GPA</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Expected Graduation Date</label>
                        <input type="month" name="expected_graduation" class="form-input" 
                               value="<?php echo htmlspecialchars($student['expected_graduation'] ?? ''); ?>">
                        <div class="help-text">Optional - When do you expect to graduate?</div>
                    </div>

                    <!-- Profile Photo -->
                    <div class="form-group full-width">
                        <label class="form-label">Profile Photo</label>
                        
                        <?php if (!empty($student['profile_photo']) && file_exists($student['profile_photo'])): ?>
                            <div class="current-photo">
                                <img src="<?php echo htmlspecialchars($student['profile_photo']); ?>" alt="Current Profile Photo">
                                <div>
                                    <strong>Current Photo</strong>
                                    <div class="help-text">Upload a new photo to replace the current one</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="file-input-wrapper">
                            <input type="file" name="profile_photo" class="file-input" accept="image/*">
                            <label class="file-input-label">
                                <i class="fas fa-camera"></i>
                                <span>Choose Profile Photo or drag and drop</span>
                            </label>
                        </div>
                        <div class="help-text">JPG, PNG, or GIF. Max 2MB. Recommended: 400x400 pixels</div>
                    </div>

                    <!-- Training Interests & Skills -->
                    <div class="form-group full-width">
                        <label class="form-label">Training Interests</label>
                        <textarea name="training_interests" class="form-input" rows="4" 
                                  placeholder="Describe your training interests and what you'd like to learn..."><?php echo htmlspecialchars($student['training_interests'] ?? ''); ?></textarea>
                        <div class="help-text">What areas would you like to develop or learn more about?</div>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Preferred Fields</label>
                        <textarea name="preferred_fields" class="form-input" rows="3" 
                                  placeholder="List your preferred fields of work or study..."><?php echo htmlspecialchars($student['preferred_fields'] ?? ''); ?></textarea>
                        <div class="help-text">What fields or industries interest you most?</div>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Skills</label>
                        <textarea name="skills" class="form-input" rows="3" 
                                  placeholder="List your skills and competencies..."><?php echo htmlspecialchars($student['skills'] ?? ''); ?></textarea>
                        <div class="help-text">What skills do you currently have or are developing?</div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="student_profile.php" class="btn btn-secondary">
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
            console.log('Form submission started');
            
            const firstName = document.querySelector('input[name="first_name"]').value.trim();
            const lastName = document.querySelector('input[name="last_name"]').value.trim();
            const university = document.querySelector('input[name="university"]').value.trim();
            const department = document.querySelector('input[name="department"]').value.trim();
            const studentId = document.querySelector('input[name="student_id_number"]').value.trim();
            const academicYear = document.querySelector('select[name="academic_year"]').value;

            console.log('Form data:', {
                firstName, lastName, university, department, studentId, academicYear
            });

            if (!firstName || !lastName || !university || !department || !studentId || !academicYear) {
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

            // GPA validation
            const gpa = document.querySelector('input[name="gpa"]').value;
            if (gpa && (parseFloat(gpa) < 0 || parseFloat(gpa) > 4)) {
                e.preventDefault();
                alert('GPA must be between 0.0 and 4.0.');
                return false;
            }
            
            console.log('Form validation passed, submitting...');
        });

        // File input preview
        document.querySelector('input[name="profile_photo"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const currentPhoto = document.querySelector('.current-photo');
                    if (currentPhoto) {
                        currentPhoto.querySelector('img').src = e.target.result;
                    } else {
                        // Create preview if no current photo exists
                        const preview = document.createElement('div');
                        preview.className = 'current-photo';
                        preview.innerHTML = `
                            <img src="${e.target.result}" alt="Photo Preview">
                            <div>
                                <strong>New Photo Preview</strong>
                                <div class="help-text">This will replace your current profile photo</div>
                            </div>
                        `;
                        document.querySelector('input[name="profile_photo"]').parentNode.insertBefore(preview, document.querySelector('.file-input-wrapper'));
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
