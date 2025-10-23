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

// Get internship applications for students in instructor's courses
$sql = "SELECT 
    ia.*,
    s.first_name,
    s.last_name,
    s.email,
    s.phone,
    s.department as student_department,
    s.year_of_study,
    s.cv_path,
    i.title as internship_title,
    i.description as internship_description,
    i.location as internship_location,
    i.type as internship_type,
    c.company_name,
    c.industry,
    ce.course_title,
    ce.course_category
    FROM internship_applications ia
    JOIN students s ON ia.student_id = s.student_id
    JOIN internships i ON ia.internship_id = i.internship_id
    JOIN companies c ON i.company_id = c.company_id
    JOIN course_enrollments ce ON s.student_id = ce.student_id
    JOIN courses co ON ce.course_id = co.course_id
    WHERE co.instructor_id = ?
    ORDER BY ia.application_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_sql = "SELECT 
    COUNT(ia.application_id) as total_applications,
    COUNT(CASE WHEN ia.status = 'pending' THEN 1 END) as pending_applications,
    COUNT(CASE WHEN ia.status = 'approved' THEN 1 END) as approved_applications,
    COUNT(CASE WHEN ia.status = 'rejected' THEN 1 END) as rejected_applications
    FROM internship_applications ia
    JOIN students s ON ia.student_id = s.student_id
    JOIN course_enrollments ce ON s.student_id = ce.student_id
    JOIN courses c ON ce.course_id = c.course_id
    WHERE c.instructor_id = ?";

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Applications - Instructor Portal</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--brand);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--muted);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .applications-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
            gap: 2rem;
        }

        .application-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            transition: var(--transition);
        }

        .application-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-white);
            font-size: 1.2rem;
            font-weight: 700;
        }

        .student-details h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .student-details p {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.1);
            color: #d97706;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .status-approved {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .status-rejected {
            background: rgba(248, 113, 113, 0.1);
            color: #dc2626;
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        .internship-info {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1rem;
        }

        .internship-title {
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .internship-meta {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .internship-description {
            color: var(--text-dark);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .application-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            padding: 0.75rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            text-align: center;
        }

        .detail-label {
            font-size: 0.75rem;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .detail-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--ink);
        }

        .application-actions {
            display: flex;
            gap: 0.5rem;
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
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-success {
            background: var(--success);
            color: var(--text-white);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-error {
            background: var(--error);
            color: var(--text-white);
        }

        .btn-error:hover {
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

        .no-applications {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--panel);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .no-applications-icon {
            font-size: 4rem;
            color: var(--muted);
            margin-bottom: 1rem;
        }

        .no-applications h3 {
            color: var(--ink);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .no-applications p {
            color: var(--muted);
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .applications-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .application-actions {
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
                <span>Review Applications</span>
            </div>
            <h1 class="page-title">Internship Applications</h1>
            <p class="page-subtitle">Review and approve student internship applications</p>
        </div>

        <?php if (!empty($applications)): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['approved_applications']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['rejected_applications']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <div class="applications-grid">
                <?php foreach ($applications as $application): ?>
                    <div class="application-card">
                        <div class="application-header">
                            <div class="student-info">
                                <div class="student-avatar">
                                    <?php echo strtoupper(substr($application['first_name'], 0, 1) . substr($application['last_name'], 0, 1)); ?>
                                </div>
                                <div class="student-details">
                                    <h4><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($application['email']); ?></p>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo $application['status']; ?>">
                                <?php echo ucfirst($application['status']); ?>
                            </span>
                        </div>

                        <div class="internship-info">
                            <div class="internship-title"><?php echo htmlspecialchars($application['internship_title']); ?></div>
                            <div class="internship-meta">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($application['company_name']); ?> • 
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($application['internship_location']); ?> • 
                                <i class="fas fa-tag"></i> <?php echo ucfirst($application['internship_type']); ?>
                            </div>
                            <div class="internship-description">
                                <?php echo htmlspecialchars(substr($application['internship_description'], 0, 150)) . '...'; ?>
                            </div>
                        </div>

                        <div class="application-details">
                            <div class="detail-item">
                                <div class="detail-label">Department</div>
                                <div class="detail-value"><?php echo htmlspecialchars($application['student_department']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Year</div>
                                <div class="detail-value"><?php echo htmlspecialchars($application['year_of_study']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Course</div>
                                <div class="detail-value"><?php echo htmlspecialchars($application['course_title']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Applied</div>
                                <div class="detail-value"><?php echo date('M d, Y', strtotime($application['application_date'])); ?></div>
                            </div>
                        </div>

                        <div class="application-actions">
                            <?php if ($application['status'] === 'pending'): ?>
                                <button class="btn btn-success" onclick="approveApplication(<?php echo $application['application_id']; ?>)">
                                    <i class="fas fa-check"></i>
                                    Approve
                                </button>
                                <button class="btn btn-error" onclick="rejectApplication(<?php echo $application['application_id']; ?>)">
                                    <i class="fas fa-times"></i>
                                    Reject
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($application['cv_path']): ?>
                                <a href="<?php echo htmlspecialchars($application['cv_path']); ?>" target="_blank" class="btn btn-secondary">
                                    <i class="fas fa-file-pdf"></i>
                                    View CV
                                </a>
                            <?php endif; ?>
                            
                            <a href="mailto:<?php echo htmlspecialchars($application['email']); ?>" class="btn btn-primary">
                                <i class="fas fa-envelope"></i>
                                Contact
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-applications">
                <div class="no-applications-icon">📝</div>
                <h3>No Applications Yet</h3>
                <p>No internship applications from your students yet. Students need to apply for internships to appear here.</p>
                <a href="instructor_dashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function approveApplication(applicationId) {
            if (confirm('Are you sure you want to approve this application?')) {
                // This would make an AJAX call to approve the application
                alert('Application approval feature coming soon!');
            }
        }

        function rejectApplication(applicationId) {
            if (confirm('Are you sure you want to reject this application?')) {
                // This would make an AJAX call to reject the application
                alert('Application rejection feature coming soon!');
            }
        }
    </script>
</body>
</html>
