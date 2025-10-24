<?php
session_start();
include "connection.php";

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];

// Get company information
$stmt = $conn->prepare("SELECT * FROM companies WHERE company_id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();

if (!$company) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $duration = trim($_POST['duration']);
    $mode = $_POST['mode'];
    $category = $_POST['category'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $instructor_id = !empty($_POST['instructor_id']) ? intval($_POST['instructor_id']) : null;
    $location = trim($_POST['location']);
    $requirements = trim($_POST['requirements']);
    $learning_outcomes = trim($_POST['learning_outcomes']);
    $certificate_option = isset($_POST['certificate_option']) ? 1 : 0;
    
    // Validation
    if (empty($title) || empty($description) || empty($duration) || empty($mode) || empty($category) || empty($start_date) || empty($end_date)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Insert course into database
        $sql = "INSERT INTO courses (title, description, duration, mode, category, start_date, end_date, instructor_id, location, requirements, learning_outcomes, certificate_option, created_by_type, created_by_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'company', ?, 'active', NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssssii", $title, $description, $duration, $mode, $category, $start_date, $end_date, $instructor_id, $location, $requirements, $learning_outcomes, $certificate_option, $company_id);
        
        if ($stmt->execute()) {
            $success_message = "Course created successfully!";
            // Clear form data
            $title = $description = $duration = $requirements = $learning_outcomes = $location = '';
            $mode = $category = $start_date = $end_date = '';
            $instructor_id = null;
            $certificate_option = 0;
        } else {
            $error_message = "Error creating course: " . $conn->error;
        }
    }
}

// Get available instructors for dropdown
$instructors_sql = "SELECT instructor_id, first_name, last_name, department FROM instructors ORDER BY last_name, first_name";
$instructors_result = $conn->query($instructors_sql);
$instructors = $instructors_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Course - Company Portal</title>
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
            max-width: 800px;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .form-label.required::after {
            content: " *";
            color: var(--error);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-help {
            font-size: 0.9rem;
            color: var(--muted);
            margin-top: 0.5rem;
            font-style: italic;
        }

        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            background: var(--bg-primary);
            transition: var(--transition);
        }

        .form-select:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
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

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            font-weight: 600;
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

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-row {
                grid-template-columns: 1fr;
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
                <span>Create Course</span>
            </div>
            <h1 class="page-title">Create New Course</h1>
            <p class="page-subtitle">Add a new training course for students and employees</p>
        </div>

        <div class="form-container">
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

            <form method="POST" action="">
                <div class="form-group">
                    <label for="title" class="form-label required">Course Title</label>
                    <input type="text" id="title" name="title" class="form-input" 
                           value="<?php echo htmlspecialchars($title ?? ''); ?>" 
                           placeholder="Enter course title" required>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label required">Description</label>
                    <textarea id="description" name="description" class="form-input form-textarea" 
                              placeholder="Describe the course content, objectives, and what students will learn" required><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="duration" class="form-label required">Duration</label>
                    <input type="text" id="duration" name="duration" class="form-input" 
                           value="<?php echo htmlspecialchars($duration ?? ''); ?>" 
                           placeholder="e.g., 4 weeks, 40 hours, 2 months" required>
                </div>

                <div class="form-group">
                    <label for="mode" class="form-label required">Mode</label>
                    <select id="mode" name="mode" class="form-select" required>
                        <option value="">Select mode</option>
                        <option value="Online" <?php echo (isset($mode) && $mode === 'Online') ? 'selected' : ''; ?>>Online</option>
                        <option value="Onsite" <?php echo (isset($mode) && $mode === 'Onsite') ? 'selected' : ''; ?>>Onsite</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category" class="form-label required">Category</label>
                    <select id="category" name="category" class="form-select" required>
                        <option value="">Select category</option>
                        <option value="technical" <?php echo (isset($category) && $category === 'technical') ? 'selected' : ''; ?>>Technical</option>
                        <option value="business" <?php echo (isset($category) && $category === 'business') ? 'selected' : ''; ?>>Business</option>
                        <option value="language" <?php echo (isset($category) && $category === 'language') ? 'selected' : ''; ?>>Language</option>
                        <option value="soft_skills" <?php echo (isset($category) && $category === 'soft_skills') ? 'selected' : ''; ?>>Soft Skills</option>
                        <option value="certification" <?php echo (isset($category) && $category === 'certification') ? 'selected' : ''; ?>>Certification</option>
                        <option value="workshop" <?php echo (isset($category) && $category === 'workshop') ? 'selected' : ''; ?>>Workshop</option>
                        <option value="seminar" <?php echo (isset($category) && $category === 'seminar') ? 'selected' : ''; ?>>Seminar</option>
                        <option value="other" <?php echo (isset($category) && $category === 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date" class="form-label required">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-input" 
                               value="<?php echo htmlspecialchars($start_date ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date" class="form-label required">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-input" 
                               value="<?php echo htmlspecialchars($end_date ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="instructor_id" class="form-label">Instructor (Optional)</label>
                    <select id="instructor_id" name="instructor_id" class="form-select">
                        <option value="">Select instructor (optional)</option>
                        <?php foreach ($instructors as $instructor): ?>
                            <option value="<?php echo $instructor['instructor_id']; ?>" 
                                    <?php echo (isset($instructor_id) && $instructor_id == $instructor['instructor_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name'] . ' (' . $instructor['department'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="location" class="form-label">Location (Optional)</label>
                    <input type="text" id="location" name="location" class="form-input" 
                           value="<?php echo htmlspecialchars($location ?? ''); ?>" 
                           placeholder="Enter course location (for onsite courses)">
                </div>

                <div class="form-group">
                    <label for="requirements" class="form-label">Requirements</label>
                    <textarea id="requirements" name="requirements" class="form-input form-textarea" 
                              placeholder="List any prerequisites, skills, or requirements for this course"><?php echo htmlspecialchars($requirements ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="learning_outcomes" class="form-label">Learning Outcomes</label>
                    <textarea id="learning_outcomes" name="learning_outcomes" class="form-input form-textarea" 
                              placeholder="Describe what students will learn and achieve after completing this course"><?php echo htmlspecialchars($learning_outcomes ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="certificate_option" name="certificate_option" value="1" 
                               <?php echo (isset($certificate_option) && $certificate_option) ? 'checked' : ''; ?>>
                        Certificate Option
                    </label>
                    <p class="form-help">Check this box if students will receive a certificate upon completion</p>
                </div>

                <div class="form-actions">
                    <a href="company_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Create Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
