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

// Get search and filter parameters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$category = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';
$level = isset($_GET['level']) ? $conn->real_escape_string($_GET['level']) : '';
$type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';

// Build query
$where_conditions = ["c.status = 'published'"];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(c.course_title LIKE ? OR c.course_description LIKE ? OR i.first_name LIKE ? OR i.last_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
}

if (!empty($category)) {
    $where_conditions[] = "c.course_category = ?";
    $params[] = $category;
    $param_types .= "s";
}

if (!empty($level)) {
    $where_conditions[] = "c.course_level = ?";
    $params[] = $level;
    $param_types .= "s";
}

if (!empty($type)) {
    if ($type === 'online') {
        $where_conditions[] = "c.is_online = 1";
    } elseif ($type === 'in_person') {
        $where_conditions[] = "c.is_online = 0";
    }
}

$where_clause = implode(' AND ', $where_conditions);

$sql = "SELECT 
    c.*,
    i.first_name as instructor_first_name,
    i.last_name as instructor_last_name,
    i.department,
    AVG(cr.rating) as average_rating,
    COUNT(cr.review_id) as total_reviews,
    COUNT(ce.enrollment_id) as total_enrollments,
    CASE WHEN ce_student.enrollment_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled
    FROM courses c
    JOIN instructors i ON c.instructor_id = i.instructor_id
    LEFT JOIN course_reviews cr ON c.course_id = cr.course_id
    LEFT JOIN course_enrollments ce ON c.course_id = ce.course_id
    LEFT JOIN course_enrollments ce_student ON c.course_id = ce_student.course_id AND ce_student.student_id = ?
    WHERE $where_clause
    GROUP BY c.course_id
    ORDER BY c.created_at DESC";

$params = array_merge([$student_id], $params);
$param_types = "i" . $param_types;

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unique categories and levels for filters
$categories_sql = "SELECT DISTINCT course_category FROM courses WHERE status = 'published' ORDER BY course_category";
$categories_result = $conn->query($categories_sql);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

$levels_sql = "SELECT DISTINCT course_level FROM courses WHERE status = 'published' ORDER BY course_level";
$levels_result = $conn->query($levels_sql);
$levels = $levels_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Courses - Student Portal</title>
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

        .filters {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .filter-input {
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
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

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
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

        .course-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .stars {
            display: flex;
            gap: 2px;
        }

        .star {
            color: #d1d5db;
            font-size: 0.9rem;
        }

        .star.filled {
            color: #fbbf24;
        }

        .rating-text {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .course-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--brand);
            margin-bottom: 1rem;
        }

        .course-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-enroll {
            flex: 1;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            color: var(--text-white);
        }

        .btn-enroll:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-enrolled {
            flex: 1;
            background: var(--success);
            color: var(--text-white);
            cursor: not-allowed;
        }

        .btn-view {
            background: var(--bg-secondary);
            color: var(--text-dark);
            border: 2px solid var(--border-light);
        }

        .btn-view:hover {
            background: var(--brand);
            color: var(--text-white);
            border-color: var(--brand);
        }

        .featured-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            color: var(--text-white);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .courses-grid {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
                align-items: stretch;
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
                <span>Browse Courses</span>
            </div>
            <h1 class="page-title">Browse Courses</h1>
            <p class="page-subtitle">Discover courses created by expert instructors</p>
        </div>

        <div class="filters">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search courses..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <select name="category" class="filter-input">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['course_category']); ?>" <?php echo $category === $cat['course_category'] ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $cat['course_category'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Level</label>
                        <select name="level" class="filter-input">
                            <option value="">All Levels</option>
                            <?php foreach ($levels as $level_item): ?>
                                <option value="<?php echo htmlspecialchars($level_item['course_level']); ?>" <?php echo $level === $level_item['course_level'] ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($level_item['course_level']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Type</label>
                        <select name="type" class="filter-input">
                            <option value="">All Types</option>
                            <option value="online" <?php echo $type === 'online' ? 'selected' : ''; ?>>Online</option>
                            <option value="in_person" <?php echo $type === 'in_person' ? 'selected' : ''; ?>>In-Person</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                    <a href="browse_courses.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <?php if (empty($courses)): ?>
            <div class="no-courses">
                <div class="no-courses-icon">📚</div>
                <h3>No Courses Found</h3>
                <p>No courses match your current search criteria. Try adjusting your filters or check back later for new courses.</p>
                <a href="browse_courses.php" class="btn btn-primary">
                    <i class="fas fa-refresh"></i>
                    View All Courses
                </a>
            </div>
        <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <?php if ($course['is_featured']): ?>
                            <div class="featured-badge">Featured</div>
                        <?php endif; ?>
                        
                        <div class="course-header">
                            <div>
                                <span class="course-category"><?php echo ucfirst(str_replace('_', ' ', $course['course_category'])); ?></span>
                                <span class="course-level"><?php echo ucfirst($course['course_level']); ?></span>
                            </div>
                        </div>

                        <h3 class="course-title"><?php echo htmlspecialchars($course['course_title']); ?></h3>
                        <p class="course-instructor">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($course['instructor_first_name'] . ' ' . $course['instructor_last_name']); ?>
                            <span style="color: var(--muted);">• <?php echo htmlspecialchars($course['department']); ?></span>
                        </p>

                        <p class="course-description"><?php echo htmlspecialchars($course['course_description']); ?></p>

                        <div class="course-details">
                            <div class="detail-item">
                                <div class="detail-label">Duration</div>
                                <div class="detail-value"><?php echo htmlspecialchars($course['course_duration']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Type</div>
                                <div class="detail-value"><?php echo $course['is_online'] ? 'Online' : 'In-Person'; ?></div>
                            </div>
                        </div>

                        <?php if ($course['average_rating']): ?>
                            <div class="course-rating">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?php echo $i <= round($course['average_rating']) ? 'filled' : ''; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text">
                                    <?php echo number_format($course['average_rating'], 1); ?> 
                                    (<?php echo $course['total_reviews']; ?> reviews)
                                </span>
                            </div>
                        <?php endif; ?>

                        <div class="course-price">
                            <?php if ($course['course_price'] > 0): ?>
                                <?php echo $course['currency'] . ' ' . number_format($course['course_price'], 2); ?>
                            <?php else: ?>
                                Free
                            <?php endif; ?>
                        </div>

                        <div class="course-actions">
                            <?php if ($course['is_enrolled']): ?>
                                <button class="btn btn-enrolled" disabled>
                                    <i class="fas fa-check"></i>
                                    Enrolled
                                </button>
                            <?php else: ?>
                                <button class="btn btn-enroll" onclick="enrollCourse(<?php echo $course['course_id']; ?>)">
                                    <i class="fas fa-plus"></i>
                                    Enroll Now
                                </button>
                            <?php endif; ?>
                            <a href="course_details_student.php?id=<?php echo $course['course_id']; ?>" class="btn btn-view">
                                <i class="fas fa-eye"></i>
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function enrollCourse(courseId) {
            if (confirm('Are you sure you want to enroll in this course?')) {
                // Create a form to submit the enrollment
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'enroll_course.php';
                
                const courseIdInput = document.createElement('input');
                courseIdInput.type = 'hidden';
                courseIdInput.name = 'course_id';
                courseIdInput.value = courseId;
                
                form.appendChild(courseIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
