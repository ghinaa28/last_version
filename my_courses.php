<?php
session_start();
include "connection.php";

// Check if user is logged in as student
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Get student's enrolled courses
$sql = "SELECT 
    ce.*,
    c.course_title,
    c.course_description,
    c.course_duration,
    c.course_price,
    c.currency,
    c.is_online,
    c.location,
    c.course_category,
    c.course_level,
    i.first_name as instructor_first_name,
    i.last_name as instructor_last_name,
    i.department
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.course_id
    JOIN instructors i ON c.instructor_id = i.instructor_id
    WHERE ce.student_id = ?
    ORDER BY ce.enrollment_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enrollments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Student Portal</title>
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

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
        }

        .course-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            transition: var(--transition);
            position: relative;
        }

        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .course-category {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(14, 165, 168, 0.1);
            color: var(--brand);
            border: 1px solid rgba(14, 165, 168, 0.3);
        }

        .course-level {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .status-badge {
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

        .course-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .course-instructor {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .course-description {
            color: var(--text-dark);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .course-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            align-items: center;
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

        .course-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--brand);
            margin-bottom: 1rem;
        }

        .enrollment-date {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .course-actions {
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

        .btn-success {
            background: var(--success);
            color: var(--text-white);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .no-courses {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--panel);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .no-courses-icon {
            font-size: 4rem;
            color: var(--muted);
            margin-bottom: 1rem;
        }

        .no-courses h3 {
            color: var(--ink);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .no-courses p {
            color: var(--muted);
            margin-bottom: 2rem;
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

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .courses-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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
                <span>My Courses</span>
            </div>
            <h1 class="page-title">My Courses</h1>
            <p class="page-subtitle">Track your enrolled courses and progress</p>
        </div>

        <?php if (!empty($enrollments)): ?>
            <?php
            // Calculate statistics
            $total_courses = count($enrollments);
            $completed_courses = count(array_filter($enrollments, function($e) { return $e['status'] === 'completed'; }));
            $active_courses = count(array_filter($enrollments, function($e) { return $e['status'] === 'enrolled'; }));
            ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_courses; ?></div>
                    <div class="stat-label">Total Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $active_courses; ?></div>
                    <div class="stat-label">Active Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $completed_courses; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>

            <div class="courses-grid">
                <?php foreach ($enrollments as $enrollment): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <div>
                                <span class="course-category"><?php echo ucfirst(str_replace('_', ' ', $enrollment['course_category'])); ?></span>
                                <span class="course-level"><?php echo ucfirst($enrollment['course_level']); ?></span>
                            </div>
                            <span class="status-badge status-<?php echo $enrollment['status']; ?>">
                                <?php echo ucfirst($enrollment['status']); ?>
                            </span>
                        </div>

                        <h3 class="course-title"><?php echo htmlspecialchars($enrollment['course_title']); ?></h3>
                        <p class="course-instructor">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($enrollment['instructor_first_name'] . ' ' . $enrollment['instructor_last_name']); ?>
                            <span style="color: var(--muted);">• <?php echo htmlspecialchars($enrollment['department']); ?></span>
                        </p>

                        <p class="course-description"><?php echo htmlspecialchars($enrollment['course_description']); ?></p>

                        <div class="course-details">
                            <div class="detail-item">
                                <div class="detail-label">Duration</div>
                                <div class="detail-value"><?php echo htmlspecialchars($enrollment['course_duration']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Type</div>
                                <div class="detail-value"><?php echo $enrollment['is_online'] ? 'Online' : 'In-Person'; ?></div>
                            </div>
                        </div>

                        <div class="course-price">
                            <?php if ($enrollment['course_price'] > 0): ?>
                                <?php echo $enrollment['currency'] . ' ' . number_format($enrollment['course_price'], 2); ?>
                            <?php else: ?>
                                Free
                            <?php endif; ?>
                        </div>

                        <div class="enrollment-date">
                            <i class="fas fa-calendar"></i>
                            Enrolled on <?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?>
                        </div>

                        <div class="course-actions">
                            <a href="course_details_student.php?id=<?php echo $enrollment['course_id']; ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i>
                                View Details
                            </a>
                            <?php if ($enrollment['status'] === 'completed'): ?>
                                <a href="my_certificates.php" class="btn btn-success">
                                    <i class="fas fa-certificate"></i>
                                    View Certificate
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-courses">
                <div class="no-courses-icon">📚</div>
                <h3>No Courses Yet</h3>
                <p>You haven't enrolled in any courses yet. Start your learning journey by browsing available courses.</p>
                <a href="browse_courses.php" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Browse Courses
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
