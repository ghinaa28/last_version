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

// Profile is read-only - no editing functionality

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

// Get recent applications with internship details
$applications_sql = "SELECT 
    ia.application_id,
    ia.status,
    ia.application_date,
    ia.cover_letter,
    i.title as internship_title,
    c.company_name,
    i.location,
    i.duration,
    i.start_date
    FROM internship_applications ia
    LEFT JOIN internships i ON ia.internship_id = i.internship_id
    LEFT JOIN companies c ON i.company_id = c.company_id
    WHERE ia.student_id = ?
    ORDER BY ia.application_date DESC
    LIMIT 5";

$stmt = $conn->prepare($applications_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$recent_applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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



        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header .section-title {
            margin-bottom: 0;
        }

        .status-badge.status-pending {
            background: #f59e0b;
            color: white;
        }

        .status-badge.status-accepted {
            background: var(--success);
            color: white;
        }

        .status-badge.status-rejected {
            background: #ef4444;
            color: white;
        }

        .applications-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .application-item {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
            transition: all 0.2s ease;
        }

        .application-item:hover {
            border-color: var(--brand);
            box-shadow: 0 2px 8px rgba(14, 165, 168, 0.1);
        }

        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .application-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--ink);
            margin: 0;
        }

        .application-details {
            color: var(--muted);
        }

        .company-name {
            font-weight: 500;
            color: var(--ink);
            margin: 0 0 0.5rem 0;
        }

        .application-meta {
            font-size: 0.9rem;
            margin: 0;
        }

        .application-meta i {
            margin-right: 0.25rem;
        }

        .separator {
            margin: 0 0.5rem;
        }

        .section-footer {
            margin-top: 1.5rem;
            text-align: center;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state p {
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }

        .info-item.full-width {
            grid-column: 1 / -1;
        }

        .info-item.full-width .info-value {
            white-space: pre-line;
            line-height: 1.6;
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



                <div class="info-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Personal Information
                        </h3>
                        <a href="student_update_profile.php" class="btn btn-primary">
                            <i class="fas fa-edit"></i>
                            Update Profile
                        </a>
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
                            <span class="info-value <?php echo empty($student['student_id_number']) ? 'empty' : ''; ?>">
                                <?php echo !empty($student['student_id_number']) ? htmlspecialchars($student['student_id_number']) : 'Not provided'; ?>
                            </span>
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
                        <i class="fas fa-lightbulb"></i>
                        Training Interests & Skills
                    </h3>
                    <div class="info-grid">
                        <div class="info-item full-width">
                            <span class="info-label">Training Interests</span>
                            <span class="info-value <?php echo empty($student['training_interests']) ? 'empty' : ''; ?>">
                                <?php echo !empty($student['training_interests']) ? nl2br(htmlspecialchars($student['training_interests'])) : 'Not specified'; ?>
                            </span>
                        </div>
                        <div class="info-item full-width">
                            <span class="info-label">Preferred Fields</span>
                            <span class="info-value <?php echo empty($student['preferred_fields']) ? 'empty' : ''; ?>">
                                <?php echo !empty($student['preferred_fields']) ? nl2br(htmlspecialchars($student['preferred_fields'])) : 'Not specified'; ?>
                            </span>
                        </div>
                        <div class="info-item full-width">
                            <span class="info-label">Skills</span>
                            <span class="info-value <?php echo empty($student['skills']) ? 'empty' : ''; ?>">
                                <?php echo !empty($student['skills']) ? nl2br(htmlspecialchars($student['skills'])) : 'Not specified'; ?>
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
                        <i class="fas fa-briefcase"></i>
                        Recent Applications
                    </h3>
                    <?php if (!empty($recent_applications)): ?>
                        <div class="applications-list">
                            <?php foreach ($recent_applications as $app): ?>
                                <div class="application-item">
                                    <div class="application-header">
                                        <h4 class="application-title"><?php echo htmlspecialchars($app['internship_title']); ?></h4>
                                        <span class="status-badge status-<?php echo strtolower($app['status']); ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </div>
                                    <div class="application-details">
                                        <p class="company-name"><?php echo htmlspecialchars($app['company_name']); ?></p>
                                        <p class="application-meta">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($app['location']); ?>
                                            <span class="separator">•</span>
                                            <i class="fas fa-calendar"></i> Applied <?php echo date('M d, Y', strtotime($app['application_date'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="section-footer">
                            <a href="my_applications.php" class="btn btn-secondary">
                                <i class="fas fa-list"></i>
                                View All Applications
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-briefcase"></i>
                            <p>No applications yet</p>
                            <a href="browse_internships.php" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                                Browse Internships
                            </a>
                        </div>
                    <?php endif; ?>
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

</body>
</html>

