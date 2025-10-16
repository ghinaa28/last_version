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

// Get internship ID from URL
$internship_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$internship_id) {
    header("Location: browse_internships.php");
    exit();
}

// Get internship details with company information
$sql = "SELECT i.*, c.company_name, c.industry, c.logo_path, c.website, c.address,
        (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.internship_id) as application_count,
        (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.internship_id AND ia.student_id = ?) as has_applied
        FROM internships i 
        JOIN companies c ON i.company_id = c.company_id 
        WHERE i.internship_id = ? AND i.status = 'active'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student_id, $internship_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: browse_internships.php");
    exit();
}

$internship = $result->fetch_assoc();

// Check if application deadline has passed
$deadline_passed = strtotime($internship['application_deadline']) < time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($internship['title']); ?> - Internship Details</title>
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

        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .job-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .company-logo {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-lg);
            object-fit: cover;
            border: 2px solid var(--line);
        }

        .company-details h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .company-details p {
            color: var(--muted);
            font-size: 0.95rem;
        }

        .job-badges {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-type {
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            color: var(--text-white);
        }

        .badge-location {
            background: rgba(34, 211, 238, 0.1);
            color: var(--brand-2);
            border: 1px solid rgba(34, 211, 238, 0.3);
        }

        .badge-department {
            background: rgba(71, 85, 105, 0.1);
            color: var(--muted);
            border: 1px solid rgba(71, 85, 105, 0.3);
        }

        .badge-deadline {
            background: rgba(251, 191, 36, 0.1);
            color: #d97706;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .badge-deadline.expired {
            background: rgba(248, 113, 113, 0.1);
            color: var(--error);
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 1rem 2rem;
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

        .btn-success {
            background: var(--success);
            color: var(--text-white);
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: var(--warning);
            color: var(--text-white);
        }

        .btn-warning:hover {
            background: #d97706;
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

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .main-content {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .info-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .info-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--line);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: var(--muted);
        }

        .info-value {
            font-weight: 600;
            color: var(--text-dark);
            text-align: right;
        }

        .section {
            margin-bottom: 2rem;
        }

        .section:last-child {
            margin-bottom: 0;
        }

        .section h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-content {
            color: var(--text-dark);
            line-height: 1.7;
        }

        .section-content p {
            margin-bottom: 1rem;
        }

        .section-content ul {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }

        .section-content li {
            margin-bottom: 0.5rem;
        }

        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .skill-tag {
            background: rgba(14, 165, 168, 0.1);
            color: var(--brand);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid rgba(14, 165, 168, 0.3);
        }

        .application-status {
            background: var(--success);
            color: var(--text-white);
            padding: 1rem;
            border-radius: var(--radius-lg);
            text-align: center;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .application-status.warning {
            background: var(--warning);
        }

        .application-status.error {
            background: var(--error);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .job-header {
                flex-direction: column;
                gap: 1rem;
            }

            .action-buttons {
                width: 100%;
            }

            .btn {
                flex: 1;
                justify-content: center;
            }

            .company-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
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
                <a href="browse_internships.php">Browse Internships</a>
                <i class="fas fa-chevron-right"></i>
                <span>Internship Details</span>
            </div>

            <div class="job-header">
                <div>
                    <h1 class="job-title"><?php echo htmlspecialchars($internship['title']); ?></h1>
                    <div class="company-info">
                        <?php if ($internship['logo_path']): ?>
                            <img src="<?php echo htmlspecialchars($internship['logo_path']); ?>" alt="Company Logo" class="company-logo">
                        <?php endif; ?>
                        <div class="company-details">
                            <h3><?php echo htmlspecialchars($internship['company_name']); ?></h3>
                            <p><?php echo htmlspecialchars($internship['industry']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="action-buttons">
                    <?php if ($internship['has_applied'] > 0): ?>
                        <button class="btn btn-warning" disabled>
                            <i class="fas fa-check-circle"></i>
                            Already Applied
                        </button>
                    <?php elseif ($deadline_passed): ?>
                        <button class="btn btn-secondary" disabled>
                            <i class="fas fa-calendar-times"></i>
                            Application Closed
                        </button>
                    <?php else: ?>
                        <a href="apply_internship.php?id=<?php echo $internship['internship_id']; ?>" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i>
                            Apply Now
                        </a>
                    <?php endif; ?>
                    <a href="browse_internships.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Listings
                    </a>
                </div>
            </div>

            <div class="job-badges">
                <span class="badge badge-type"><?php echo ucfirst($internship['type']); ?></span>
                <span class="badge badge-location">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars($internship['location']); ?>
                </span>
                <?php if ($internship['department']): ?>
                    <span class="badge badge-department">
                        <i class="fas fa-building"></i>
                        <?php echo htmlspecialchars($internship['department']); ?>
                    </span>
                <?php endif; ?>
                <span class="badge badge-deadline <?php echo $deadline_passed ? 'expired' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    Deadline: <?php echo date('M d, Y', strtotime($internship['application_deadline'])); ?>
                </span>
            </div>
        </div>

        <div class="content-grid">
            <div class="main-content">
                <?php if ($internship['has_applied'] > 0): ?>
                    <div class="application-status">
                        <i class="fas fa-check-circle"></i>
                        You have already applied for this internship. Check your applications for updates.
                    </div>
                <?php elseif ($deadline_passed): ?>
                    <div class="application-status error">
                        <i class="fas fa-calendar-times"></i>
                        The application deadline for this internship has passed.
                    </div>
                <?php endif; ?>

                <div class="section">
                    <h3>
                        <i class="fas fa-info-circle"></i>
                        Job Description
                    </h3>
                    <div class="section-content">
                        <?php echo nl2br(htmlspecialchars($internship['description'])); ?>
                    </div>
                </div>

                <div class="section">
                    <h3>
                        <i class="fas fa-list-check"></i>
                        Requirements
                    </h3>
                    <div class="section-content">
                        <?php echo nl2br(htmlspecialchars($internship['requirements'])); ?>
                    </div>
                </div>

                <?php if ($internship['skills_required']): ?>
                    <div class="section">
                        <h3>
                            <i class="fas fa-tools"></i>
                            Skills Required
                        </h3>
                        <div class="section-content">
                            <div class="skills-list">
                                <?php 
                                $skills = explode(',', $internship['skills_required']);
                                foreach ($skills as $skill): 
                                    $skill = trim($skill);
                                    if (!empty($skill)):
                                ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($internship['benefits']): ?>
                    <div class="section">
                        <h3>
                            <i class="fas fa-gift"></i>
                            Benefits & Perks
                        </h3>
                        <div class="section-content">
                            <?php echo nl2br(htmlspecialchars($internship['benefits'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sidebar">
                <div class="info-card">
                    <h3>
                        <i class="fas fa-calendar-alt"></i>
                        Timeline
                    </h3>
                    <div class="info-item">
                        <span class="info-label">Start Date</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($internship['start_date'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">End Date</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($internship['end_date'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Duration</span>
                        <span class="info-value"><?php echo htmlspecialchars($internship['duration']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Application Deadline</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($internship['application_deadline'])); ?></span>
                    </div>
                </div>

                <div class="info-card">
                    <h3>
                        <i class="fas fa-info"></i>
                        Details
                    </h3>
                    <div class="info-item">
                        <span class="info-label">Type</span>
                        <span class="info-value"><?php echo ucfirst($internship['type']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Location</span>
                        <span class="info-value"><?php echo htmlspecialchars($internship['location']); ?></span>
                    </div>
                    <?php if ($internship['stipend']): ?>
                        <div class="info-item">
                            <span class="info-label">Stipend</span>
                            <span class="info-value"><?php echo htmlspecialchars($internship['stipend']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="info-label">Applications</span>
                        <span class="info-value"><?php echo $internship['application_count']; ?> received</span>
                    </div>
                </div>

                <div class="info-card">
                    <h3>
                        <i class="fas fa-building"></i>
                        Company Info
                    </h3>
                    <div class="info-item">
                        <span class="info-label">Company</span>
                        <span class="info-value"><?php echo htmlspecialchars($internship['company_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Industry</span>
                        <span class="info-value"><?php echo htmlspecialchars($internship['industry']); ?></span>
                    </div>
                    <?php if ($internship['website']): ?>
                        <div class="info-item">
                            <span class="info-label">Website</span>
                            <span class="info-value">
                                <a href="<?php echo htmlspecialchars($internship['website']); ?>" target="_blank" style="color: var(--brand);">
                                    Visit Website
                                </a>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if ($internship['address']): ?>
                        <div class="info-item">
                            <span class="info-label">Address</span>
                            <span class="info-value"><?php echo htmlspecialchars($internship['address']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

