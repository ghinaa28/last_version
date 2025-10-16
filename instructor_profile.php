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

// Get instructor statistics
// Note: This assumes students table has instructor_id column
// If not, we'll set default values
try {
    $stats_sql = "SELECT 
        COUNT(*) as total_students
        FROM students 
        WHERE instructor_id = ?";
    
    $stmt = $conn->prepare($stats_sql);
    $stmt->bind_param("i", $instructor_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Set active_students to total_students since we don't have status column
    $stats['active_students'] = $stats['total_students'];
} catch (Exception $e) {
    // If the query fails (e.g., instructor_id column doesn't exist), set default values
    $stats = [
        'total_students' => 0,
        'active_students' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Profile - Instructor Portal</title>
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

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .status-approved {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.1);
            color: #d97706;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .status-rejected {
            background: rgba(248, 113, 113, 0.1);
            color: #dc2626;
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        .description-section {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .description-text {
            color: var(--text-dark);
            line-height: 1.7;
        }

        .specialties-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .specialty-tag {
            background: rgba(14, 165, 168, 0.1);
            color: var(--brand);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid rgba(14, 165, 168, 0.3);
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="instructor_dashboard.php">Instructor Portal</a>
                <i class="fas fa-chevron-right"></i>
                <span>Instructor Profile</span>
            </div>
            <h1 class="page-title">Instructor Profile</h1>
            <p class="page-subtitle">Manage your instructor information and track your students</p>
        </div>

        <div class="profile-grid">
            <div class="profile-sidebar">
                <div class="profile-card">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($instructor['first_name'], 0, 1) . substr($instructor['last_name'], 0, 1)); ?>
                    </div>
                    <h2 class="profile-name"><?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name']); ?></h2>
                    <p class="profile-role">Instructor</p>
                    
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                            <div class="stat-label">Total Students</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['active_students']; ?></div>
                            <div class="stat-label">Active Students</div>
                        </div>
                    </div>

                    <a href="instructor_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Portal
                    </a>
                </div>
            </div>

            <div class="main-content">
                <div class="info-section">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i>
                        Personal Information
                    </h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Full Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email Address</span>
                            <span class="info-value"><?php echo htmlspecialchars($instructor['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Phone Number</span>
                            <span class="info-value <?php echo empty($instructor['phone']) ? 'empty' : ''; ?>">
                                <?php echo !empty($instructor['phone']) ? htmlspecialchars($instructor['phone']) : 'Not provided'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Employee ID</span>
                            <span class="info-value <?php echo empty($instructor['employee_id']) ? 'empty' : ''; ?>">
                                <?php echo !empty($instructor['employee_id']) ? htmlspecialchars($instructor['employee_id']) : 'Not provided'; ?>
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
                            <span class="info-value <?php echo empty($instructor['university']) ? 'empty' : ''; ?>">
                                <?php echo !empty($instructor['university']) ? htmlspecialchars($instructor['university']) : 'Not specified'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Department</span>
                            <span class="info-value <?php echo empty($instructor['department']) ? 'empty' : ''; ?>">
                                <?php echo !empty($instructor['department']) ? htmlspecialchars($instructor['department']) : 'Not specified'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Position</span>
                            <span class="info-value <?php echo empty($instructor['position']) ? 'empty' : ''; ?>">
                                <?php echo !empty($instructor['position']) ? htmlspecialchars($instructor['position']) : 'Not specified'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Years of Experience</span>
                            <span class="info-value <?php echo empty($instructor['years_experience']) ? 'empty' : ''; ?>">
                                <?php echo !empty($instructor['years_experience']) ? htmlspecialchars($instructor['years_experience']) . ' years' : 'Not specified'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Highest Degree</span>
                            <span class="info-value <?php echo empty($instructor['highest_degree']) ? 'empty' : ''; ?>">
                                <?php echo !empty($instructor['highest_degree']) ? htmlspecialchars($instructor['highest_degree']) : 'Not specified'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="status-badge <?php echo (!empty($instructor['status']) && $instructor['status'] === 'approved') ? 'status-approved' : ((!empty($instructor['status']) && $instructor['status'] === 'pending') ? 'status-pending' : 'status-rejected'); ?>">
                                <?php echo !empty($instructor['status']) ? ucfirst($instructor['status']) : 'Unknown'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($instructor['specialties'])): ?>
                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-tools"></i>
                            Specialties & Expertise
                        </h3>
                        <div class="specialties-list">
                            <?php 
                            $specialties = explode(',', $instructor['specialties']);
                            foreach ($specialties as $specialty): 
                                $specialty = trim($specialty);
                                if (!empty($specialty)):
                            ?>
                                <span class="specialty-tag"><?php echo htmlspecialchars($specialty); ?></span>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($instructor['bio'])): ?>
                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Biography
                        </h3>
                        <div class="description-section">
                            <div class="description-text">
                                <?php echo nl2br(htmlspecialchars($instructor['bio'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="info-section">
                    <h3 class="section-title">
                        <i class="fas fa-chart-line"></i>
                        Instructor Statistics
                    </h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Total Students Assigned</span>
                            <span class="info-value"><?php echo $stats['total_students']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Active Students</span>
                            <span class="info-value"><?php echo $stats['active_students']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Account Status</span>
                            <span class="status-badge <?php echo (!empty($instructor['status']) && $instructor['status'] === 'approved') ? 'status-approved' : ((!empty($instructor['status']) && $instructor['status'] === 'pending') ? 'status-pending' : 'status-rejected'); ?>">
                                <?php echo !empty($instructor['status']) ? ucfirst($instructor['status']) : 'Unknown'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Registration Date</span>
                            <span class="info-value <?php echo empty($instructor['created_at']) ? 'empty' : ''; ?>">
                                <?php echo !empty($instructor['created_at']) ? date('M d, Y', strtotime($instructor['created_at'])) : 'Not available'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
