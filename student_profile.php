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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $university = $conn->real_escape_string($_POST['university']);
    $department = $conn->real_escape_string($_POST['department']);
    $phone = $conn->real_escape_string($_POST['phone']);
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($university) || empty($department)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Update student profile
        $update_sql = "UPDATE students SET 
                      first_name = ?, 
                      last_name = ?, 
                      university = ?, 
                      department = ?, 
                      phone = ? 
                      WHERE student_id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssssi", $first_name, $last_name, $university, $department, $phone, $student_id);
        
        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Refresh student data
            $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();
        } else {
            $error_message = "Error updating profile: " . $conn->error;
        }
    }
}

// Get application statistics
$stats_sql = "SELECT 
    COUNT(*) as total_applications,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count
    FROM internship_applications 
    WHERE student_id = ?";

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Portal</title>
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

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }

        .profile-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .profile-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 3rem;
            color: var(--text-white);
            font-weight: 800;
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--ink);
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .profile-role {
            color: var(--muted);
            text-align: center;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--brand);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--muted);
            font-weight: 600;
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

        .main-content {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .info-section {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .info-label {
            font-weight: 600;
            color: var(--muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            color: var(--text-dark);
            font-weight: 500;
            font-size: 1.1rem;
        }

        .info-value.empty {
            color: var(--muted);
            font-style: italic;
        }

        .cv-section {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .cv-status {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-uploaded {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .status-missing {
            background: rgba(251, 191, 36, 0.1);
            color: #d97706;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .profile-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Form Styles */
        .form-container {
            background: var(--bg-primary);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-light);
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .form-label {
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
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

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
        }

        .btn-edit {
            background: var(--brand);
            color: var(--text-white);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-edit:hover {
            background: var(--brand-2);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-cancel {
            background: var(--bg-secondary);
            color: var(--muted);
            border: 2px solid var(--border-light);
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-cancel:hover {
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

        .edit-mode .info-item {
            display: none;
        }

        .edit-mode .form-container {
            display: block;
        }

        .form-container {
            display: none;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header .section-title {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="student_dashboard.php">Student Portal</a>
                <i class="fas fa-chevron-right"></i>
                <span>My Profile</span>
            </div>
            <h1 class="page-title">My Profile</h1>
            <p class="page-subtitle">Manage your personal information and track your progress</p>
        </div>

        <div class="profile-grid">
            <div class="profile-sidebar">
                <div class="profile-card">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                    </div>
                    <h2 class="profile-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                    <p class="profile-role">Student</p>
                    
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                            <div class="stat-label">Applications</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['accepted_count']; ?></div>
                            <div class="stat-label">Accepted</div>
                        </div>
                    </div>

                    <a href="student_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Portal
                    </a>
                </div>
            </div>

            <div class="main-content">
                <!-- Success/Error Messages -->
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

                <!-- Edit Profile Form -->
                <div class="form-container" id="editForm">
                    <div class="form-header">
                        <h3 class="form-title">
                            <i class="fas fa-edit"></i>
                            Edit Personal Information
                        </h3>
                    </div>
                    <form method="POST" action="">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">University *</label>
                                <input type="text" name="university" class="form-input" 
                                       value="<?php echo htmlspecialchars($student['university']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Department *</label>
                                <input type="text" name="department" class="form-input" 
                                       value="<?php echo htmlspecialchars($student['department']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-input" 
                                       value="<?php echo htmlspecialchars($student['phone']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-input" 
                                       value="<?php echo htmlspecialchars($student['email']); ?>" disabled>
                                <small style="color: var(--muted); font-size: 0.8rem; margin-top: 0.25rem;">
                                    Email cannot be changed. Contact support if needed.
                                </small>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="cancelEdit()">
                                <i class="fas fa-times"></i>
                                Cancel
                            </button>
                            <button type="submit" name="update_profile" class="btn-edit">
                                <i class="fas fa-save"></i>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <div class="info-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Personal Information
                        </h3>
                        <button class="btn-edit" onclick="editProfile()">
                            <i class="fas fa-edit"></i>
                            Edit Profile
                        </button>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Full Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email Address</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Phone Number</span>
                            <span class="info-value <?php echo empty($student['phone']) ? 'empty' : ''; ?>">
                                <?php echo !empty($student['phone']) ? htmlspecialchars($student['phone']) : 'Not provided'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date of Birth</span>
                            <span class="info-value <?php echo empty($student['date_of_birth']) ? 'empty' : ''; ?>">
                                <?php echo !empty($student['date_of_birth']) ? date('M d, Y', strtotime($student['date_of_birth'])) : 'Not provided'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="info-section">
                    <h3 class="section-title">
                        <i class="fas fa-graduation-cap"></i>
                        Academic Information
                    </h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">University</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['university']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Department</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['department']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Student ID</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['student_id_number']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Academic Year</span>
                            <span class="info-value <?php echo empty($student['academic_year']) ? 'empty' : ''; ?>">
                                <?php echo !empty($student['academic_year']) ? htmlspecialchars($student['academic_year']) : 'Not specified'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">GPA</span>
                            <span class="info-value <?php echo empty($student['gpa']) ? 'empty' : ''; ?>">
                                <?php echo !empty($student['gpa']) ? htmlspecialchars($student['gpa']) : 'Not provided'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Expected Graduation</span>
                            <span class="info-value <?php echo empty($student['expected_graduation']) ? 'empty' : ''; ?>">
                                <?php echo !empty($student['expected_graduation']) ? date('M Y', strtotime($student['expected_graduation'])) : 'Not specified'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="info-section">
                    <h3 class="section-title">
                        <i class="fas fa-file-alt"></i>
                        Documents & CV
                    </h3>
                    <div class="cv-section">
                        <div class="cv-status">
                            <i class="fas fa-file-pdf"></i>
                            <span class="status-badge <?php echo $student['cv_path'] ? 'status-uploaded' : 'status-missing'; ?>">
                                <?php echo $student['cv_path'] ? 'CV Uploaded' : 'CV Not Uploaded'; ?>
                            </span>
                        </div>
                        <?php if ($student['cv_path']): ?>
                            <p style="color: var(--muted); margin-bottom: 1rem;">
                                Your CV has been uploaded and is available to companies when you apply for internships.
                            </p>
                            <a href="<?php echo htmlspecialchars($student['cv_path']); ?>" target="_blank" class="btn btn-primary">
                                <i class="fas fa-download"></i>
                                View CV
                            </a>
                        <?php else: ?>
                            <p style="color: var(--muted); margin-bottom: 1rem;">
                                Upload your CV to make your applications more competitive. Companies can view your CV when you apply for internships.
                            </p>
                            <button class="btn btn-primary" onclick="alert('CV upload feature coming soon!')">
                                <i class="fas fa-upload"></i>
                                Upload CV
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-section">
                    <h3 class="section-title">
                        <i class="fas fa-chart-line"></i>
                        Application Statistics
                    </h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Total Applications</span>
                            <span class="info-value"><?php echo $stats['total_applications']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Pending Review</span>
                            <span class="info-value"><?php echo $stats['pending_count']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Accepted Applications</span>
                            <span class="info-value"><?php echo $stats['accepted_count']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Success Rate</span>
                            <span class="info-value">
                                <?php 
                                $success_rate = $stats['total_applications'] > 0 ? 
                                    round(($stats['accepted_count'] / $stats['total_applications']) * 100, 1) : 0;
                                echo $success_rate . '%';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editProfile() {
            document.body.classList.add('edit-mode');
            document.getElementById('editForm').style.display = 'block';
            // Scroll to form
            document.getElementById('editForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function cancelEdit() {
            document.body.classList.remove('edit-mode');
            document.getElementById('editForm').style.display = 'none';
        }

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
            const university = document.querySelector('input[name="university"]').value.trim();
            const department = document.querySelector('input[name="department"]').value.trim();

            if (!firstName || !lastName || !university || !department) {
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
        });
    </script>
</body>
</html>

