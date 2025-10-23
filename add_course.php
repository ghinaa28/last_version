<?php
session_start();
include "connection.php";

// Check if user is logged in as instructor
if (!isset($_SESSION['instructor_id'])) {
    header("Location: login.php");
    exit();
}

$instructor_id = $_SESSION['instructor_id'];

// Get instructor information
$stmt = $conn->prepare("SELECT * FROM instructors WHERE instructor_id = ?");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$instructor = $stmt->get_result()->fetch_assoc();

if (!$instructor) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$success_message = "";
$error_message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_title = trim($_POST['course_title']);
    $course_description = trim($_POST['course_description']);
    $course_category = $_POST['course_category'];
    $course_level = $_POST['course_level'];
    $course_duration = trim($_POST['course_duration']);
    $course_price = floatval($_POST['course_price']);
    $currency = $_POST['currency'];
    $is_online = isset($_POST['is_online']) ? 1 : 0;
    $location = trim($_POST['location']);
    $max_students = !empty($_POST['max_students']) ? intval($_POST['max_students']) : NULL;
    $course_materials = trim($_POST['course_materials']);
    $prerequisites = trim($_POST['prerequisites']);
    $learning_outcomes = trim($_POST['learning_outcomes']);
    $course_schedule = trim($_POST['course_schedule']);
    $status = $_POST['status'];
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;

    // Validate required fields
    if (empty($course_title) || empty($course_description) || empty($course_duration)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Handle file upload for course image
        $course_image = NULL;
        if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] == 0) {
            $upload_dir = "uploads/courses/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['course_image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $file_extension = pathinfo($_FILES['course_image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['course_image']['tmp_name'], $upload_path)) {
                    $course_image = $upload_path;
                }
            }
        }

        // Insert course into database
        $stmt = $conn->prepare("INSERT INTO courses (instructor_id, course_title, course_description, course_category, course_level, course_duration, course_price, currency, is_online, location, max_students, course_image, course_materials, prerequisites, learning_outcomes, course_schedule, status, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("isssssdssisssssss", 
            $instructor_id, 
            $course_title, 
            $course_description, 
            $course_category, 
            $course_level, 
            $course_duration, 
            $course_price, 
            $currency, 
            $is_online, 
            $location, 
            $max_students, 
            $course_image, 
            $course_materials, 
            $prerequisites, 
            $learning_outcomes, 
            $course_schedule, 
            $status, 
            $is_featured
        );

        if ($stmt->execute()) {
            $success_message = "Course added successfully!";
            // Clear form data
            $_POST = array();
        } else {
            $error_message = "Error adding course: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Course - Instructor Portal</title>
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
            max-width: 1000px;
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
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

        .form-input,
        .form-select,
        .form-textarea {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--brand);
        }

        .form-checkbox label {
            font-weight: 500;
            color: var(--text-dark);
            cursor: pointer;
        }

        .btn {
            padding: 0.75rem 1.5rem;
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
            text-align: center;
            justify-content: center;
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

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .alert-error {
            background: rgba(248, 113, 113, 0.1);
            color: #dc2626;
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--line);
        }

        .file-upload {
            position: relative;
            display: inline-block;
            cursor: pointer;
            width: 100%;
        }

        .file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            border: 2px dashed var(--border-light);
            border-radius: var(--radius-lg);
            background: var(--bg-secondary);
            transition: var(--transition);
        }

        .file-upload:hover .file-upload-label {
            border-color: var(--brand);
            background: rgba(14, 165, 168, 0.05);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
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
                <span>Add Course</span>
            </div>
            <h1 class="page-title">Add New Course</h1>
            <p class="page-subtitle">Create and publish your own course for students to enroll</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label required" for="course_title">Course Title</label>
                        <input type="text" id="course_title" name="course_title" class="form-input" 
                               value="<?php echo isset($_POST['course_title']) ? htmlspecialchars($_POST['course_title']) : ''; ?>" 
                               placeholder="Enter course title" required>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label required" for="course_description">Course Description</label>
                        <textarea id="course_description" name="course_description" class="form-textarea" 
                                  placeholder="Describe what students will learn in this course" required><?php echo isset($_POST['course_description']) ? htmlspecialchars($_POST['course_description']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label required" for="course_category">Category</label>
                        <select id="course_category" name="course_category" class="form-select" required>
                            <option value="">Select Category</option>
                            <option value="technical" <?php echo (isset($_POST['course_category']) && $_POST['course_category'] == 'technical') ? 'selected' : ''; ?>>Technical</option>
                            <option value="business" <?php echo (isset($_POST['course_category']) && $_POST['course_category'] == 'business') ? 'selected' : ''; ?>>Business</option>
                            <option value="language" <?php echo (isset($_POST['course_category']) && $_POST['course_category'] == 'language') ? 'selected' : ''; ?>>Language</option>
                            <option value="soft_skills" <?php echo (isset($_POST['course_category']) && $_POST['course_category'] == 'soft_skills') ? 'selected' : ''; ?>>Soft Skills</option>
                            <option value="certification" <?php echo (isset($_POST['course_category']) && $_POST['course_category'] == 'certification') ? 'selected' : ''; ?>>Certification</option>
                            <option value="workshop" <?php echo (isset($_POST['course_category']) && $_POST['course_category'] == 'workshop') ? 'selected' : ''; ?>>Workshop</option>
                            <option value="seminar" <?php echo (isset($_POST['course_category']) && $_POST['course_category'] == 'seminar') ? 'selected' : ''; ?>>Seminar</option>
                            <option value="other" <?php echo (isset($_POST['course_category']) && $_POST['course_category'] == 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required" for="course_level">Level</label>
                        <select id="course_level" name="course_level" class="form-select" required>
                            <option value="">Select Level</option>
                            <option value="beginner" <?php echo (isset($_POST['course_level']) && $_POST['course_level'] == 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                            <option value="intermediate" <?php echo (isset($_POST['course_level']) && $_POST['course_level'] == 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="advanced" <?php echo (isset($_POST['course_level']) && $_POST['course_level'] == 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                            <option value="expert" <?php echo (isset($_POST['course_level']) && $_POST['course_level'] == 'expert') ? 'selected' : ''; ?>>Expert</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required" for="course_duration">Duration</label>
                        <input type="text" id="course_duration" name="course_duration" class="form-input" 
                               value="<?php echo isset($_POST['course_duration']) ? htmlspecialchars($_POST['course_duration']) : ''; ?>" 
                               placeholder="e.g., 4 weeks, 2 months, 40 hours" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="course_price">Price</label>
                        <input type="number" id="course_price" name="course_price" class="form-input" 
                               value="<?php echo isset($_POST['course_price']) ? htmlspecialchars($_POST['course_price']) : '0'; ?>" 
                               placeholder="0.00" min="0" step="0.01">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="currency">Currency</label>
                        <select id="currency" name="currency" class="form-select">
                            <option value="USD" <?php echo (isset($_POST['currency']) && $_POST['currency'] == 'USD') ? 'selected' : ''; ?>>USD</option>
                            <option value="EUR" <?php echo (isset($_POST['currency']) && $_POST['currency'] == 'EUR') ? 'selected' : ''; ?>>EUR</option>
                            <option value="GBP" <?php echo (isset($_POST['currency']) && $_POST['currency'] == 'GBP') ? 'selected' : ''; ?>>GBP</option>
                            <option value="CAD" <?php echo (isset($_POST['currency']) && $_POST['currency'] == 'CAD') ? 'selected' : ''; ?>>CAD</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="max_students">Max Students</label>
                        <input type="number" id="max_students" name="max_students" class="form-input" 
                               value="<?php echo isset($_POST['max_students']) ? htmlspecialchars($_POST['max_students']) : ''; ?>" 
                               placeholder="Leave empty for unlimited" min="1">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo (isset($_POST['status']) && $_POST['status'] == 'published') ? 'selected' : ''; ?>>Published</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <div class="form-checkbox">
                            <input type="checkbox" id="is_online" name="is_online" <?php echo (isset($_POST['is_online']) && $_POST['is_online']) ? 'checked' : ''; ?>>
                            <label for="is_online">This is an online course</label>
                        </div>
                    </div>

                    <div class="form-group" id="location-group" style="display: none;">
                        <label class="form-label" for="location">Location</label>
                        <input type="text" id="location" name="location" class="form-input" 
                               value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>" 
                               placeholder="Enter physical location">
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label" for="course_image">Course Image</label>
                        <div class="file-upload">
                            <input type="file" id="course_image" name="course_image" accept="image/*">
                            <label for="course_image" class="file-upload-label">
                                <i class="fas fa-upload"></i>
                                <span>Choose course image (optional)</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label" for="course_materials">Course Materials</label>
                        <textarea id="course_materials" name="course_materials" class="form-textarea" 
                                  placeholder="List the materials students will need (books, software, etc.)"><?php echo isset($_POST['course_materials']) ? htmlspecialchars($_POST['course_materials']) : ''; ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label" for="prerequisites">Prerequisites</label>
                        <textarea id="prerequisites" name="prerequisites" class="form-textarea" 
                                  placeholder="What students should know before taking this course"><?php echo isset($_POST['prerequisites']) ? htmlspecialchars($_POST['prerequisites']) : ''; ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label" for="learning_outcomes">Learning Outcomes</label>
                        <textarea id="learning_outcomes" name="learning_outcomes" class="form-textarea" 
                                  placeholder="What students will be able to do after completing this course"><?php echo isset($_POST['learning_outcomes']) ? htmlspecialchars($_POST['learning_outcomes']) : ''; ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label" for="course_schedule">Schedule</label>
                        <textarea id="course_schedule" name="course_schedule" class="form-textarea" 
                                  placeholder="Course schedule, meeting times, deadlines, etc."><?php echo isset($_POST['course_schedule']) ? htmlspecialchars($_POST['course_schedule']) : ''; ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <div class="form-checkbox">
                            <input type="checkbox" id="is_featured" name="is_featured" <?php echo (isset($_POST['is_featured']) && $_POST['is_featured']) ? 'checked' : ''; ?>>
                            <label for="is_featured">Feature this course (show in featured courses section)</label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="manage_courses.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Add Course
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle location field based on online course checkbox
        document.getElementById('is_online').addEventListener('change', function() {
            const locationGroup = document.getElementById('location-group');
            const locationInput = document.getElementById('location');
            
            if (this.checked) {
                locationGroup.style.display = 'none';
                locationInput.value = '';
            } else {
                locationGroup.style.display = 'block';
            }
        });

        // Initialize location field visibility
        document.getElementById('is_online').dispatchEvent(new Event('change'));

        // File upload preview
        document.getElementById('course_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const label = document.querySelector('.file-upload-label span');
                label.textContent = file.name;
            }
        });
    </script>
</body>
</html>
