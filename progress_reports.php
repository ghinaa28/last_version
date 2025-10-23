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

// Get student progress data
$sql = "SELECT 
    s.student_id,
    s.first_name,
    s.last_name,
    s.email,
    s.department as student_department,
    s.year_of_study,
    c.course_title,
    c.course_category,
    c.course_level,
    ce.enrollment_date,
    ce.status as enrollment_status,
    ce.completion_date,
    COUNT(ia.application_id) as total_applications,
    COUNT(CASE WHEN ia.status = 'approved' THEN 1 END) as approved_applications,
    COUNT(CASE WHEN ia.status = 'completed' THEN 1 END) as completed_internships
    FROM students s
    JOIN course_enrollments ce ON s.student_id = ce.student_id
    JOIN courses c ON ce.course_id = c.course_id
    LEFT JOIN internship_applications ia ON s.student_id = ia.student_id
    WHERE c.instructor_id = ?
    GROUP BY s.student_id, c.course_id
    ORDER BY ce.enrollment_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get overall statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT s.student_id) as total_students,
    COUNT(ce.enrollment_id) as total_enrollments,
    COUNT(CASE WHEN ce.status = 'enrolled' THEN 1 END) as active_students,
    COUNT(CASE WHEN ce.status = 'completed' THEN 1 END) as completed_students,
    COUNT(ia.application_id) as total_applications,
    COUNT(CASE WHEN ia.status = 'approved' THEN 1 END) as approved_applications
    FROM students s
    JOIN course_enrollments ce ON s.student_id = ce.student_id
    JOIN courses c ON ce.course_id = c.course_id
    LEFT JOIN internship_applications ia ON s.student_id = ia.student_id
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
    <title>Progress Reports - Instructor Portal</title>
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

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
        }

        .report-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            transition: var(--transition);
        }

        .report-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .student-header {
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

        .progress-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .progress-bar {
            width: 100px;
            height: 8px;
            background: var(--bg-secondary);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--ink);
        }

        .course-info {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1rem;
        }

        .course-title {
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .course-meta {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .enrollment-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-enrolled {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .status-completed {
            background: rgba(14, 165, 168, 0.1);
            color: var(--brand);
            border: 1px solid rgba(14, 165, 168, 0.3);
        }

        .status-dropped {
            background: rgba(248, 113, 113, 0.1);
            color: #dc2626;
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        .progress-details {
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

        .report-actions {
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

        .no-reports {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--panel);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .no-reports-icon {
            font-size: 4rem;
            color: var(--muted);
            margin-bottom: 1rem;
        }

        .no-reports h3 {
            color: var(--ink);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .no-reports p {
            color: var(--muted);
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .reports-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .report-actions {
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
                <span>Progress Reports</span>
            </div>
            <h1 class="page-title">Student Progress Reports</h1>
            <p class="page-subtitle">Track student progress and generate academic reports</p>
        </div>

        <?php if (!empty($students)): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['active_students']; ?></div>
                    <div class="stat-label">Active Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['completed_students']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['approved_applications']; ?></div>
                    <div class="stat-label">Approved Internships</div>
                </div>
            </div>

            <div class="reports-grid">
                <?php foreach ($students as $student): ?>
                    <?php
                    // Calculate progress percentage
                    $progress = 0;
                    if ($student['enrollment_status'] === 'completed') {
                        $progress = 100;
                    } elseif ($student['enrollment_status'] === 'enrolled') {
                        $progress = 50; // Assume 50% for active students
                    }
                    ?>
                    <div class="report-card">
                        <div class="student-header">
                            <div class="student-info">
                                <div class="student-avatar">
                                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                </div>
                                <div class="student-details">
                                    <h4><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($student['email']); ?></p>
                                </div>
                            </div>
                            <div class="progress-indicator">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                                <span class="progress-text"><?php echo $progress; ?>%</span>
                            </div>
                        </div>

                        <div class="course-info">
                            <div class="course-title"><?php echo htmlspecialchars($student['course_title']); ?></div>
                            <div class="course-meta">
                                <?php echo ucfirst(str_replace('_', ' ', $student['course_category'])); ?> • 
                                <?php echo ucfirst($student['course_level']); ?>
                            </div>
                            <span class="enrollment-status status-<?php echo $student['enrollment_status']; ?>">
                                <?php echo ucfirst($student['enrollment_status']); ?>
                            </span>
                        </div>

                        <div class="progress-details">
                            <div class="detail-item">
                                <div class="detail-label">Department</div>
                                <div class="detail-value"><?php echo htmlspecialchars($student['student_department']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Year</div>
                                <div class="detail-value"><?php echo htmlspecialchars($student['year_of_study']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Applications</div>
                                <div class="detail-value"><?php echo $student['total_applications']; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Internships</div>
                                <div class="detail-value"><?php echo $student['completed_internships']; ?></div>
                            </div>
                        </div>

                        <div class="report-actions">
                            <button class="btn btn-primary" onclick="generateReport(<?php echo $student['student_id']; ?>)">
                                <i class="fas fa-file-pdf"></i>
                                Generate Report
                            </button>
                            <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>" class="btn btn-secondary">
                                <i class="fas fa-envelope"></i>
                                Contact
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-reports">
                <div class="no-reports-icon">📊</div>
                <h3>No Students Yet</h3>
                <p>No students are enrolled in your courses yet. Create courses and wait for students to enroll.</p>
                <a href="add_course.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Create Course
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function generateReport(studentId) {
            // This would generate a PDF report for the student
            alert('Report generation feature coming soon!');
        }
    </script>
</body>
</html>
