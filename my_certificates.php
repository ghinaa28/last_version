<?php
session_start();
include "connection.php";

// Check if user is logged in as student
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Create certificates table if it doesn't exist
$create_certificates_table = "CREATE TABLE IF NOT EXISTS certificates (
    certificate_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    student_id INT NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    course_description TEXT,
    completion_date DATE NOT NULL,
    certificate_number VARCHAR(50) UNIQUE NOT NULL,
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'revoked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_company_id (company_id),
    INDEX idx_student_id (student_id),
    INDEX idx_certificate_number (certificate_number)
)";

$conn->query($create_certificates_table);

// Get student information
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Get student's certificates
$certificates_sql = "SELECT 
    c.*,
    comp.company_name,
    comp.logo_path
    FROM certificates c
    JOIN companies comp ON c.company_id = comp.company_id
    WHERE c.student_id = ? AND c.status = 'active'
    ORDER BY c.issued_date DESC";

$stmt = $conn->prepare($certificates_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$certificates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get certificate statistics
$stats_sql = "SELECT 
    COUNT(*) as total_certificates,
    COUNT(CASE WHEN YEAR(issued_date) = YEAR(CURDATE()) THEN 1 END) as this_year_certificates,
    COUNT(DISTINCT company_id) as companies_count
    FROM certificates 
    WHERE student_id = ? AND status = 'active'";

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
    <title>My Certificates - Student Portal</title>
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
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, #f6f8fb 0%, #e2e8f0 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
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
            text-align: center;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: var(--muted);
        }

        .breadcrumb a {
            color: var(--brand);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .breadcrumb a:hover {
            color: var(--brand-2);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .page-subtitle {
            color: var(--muted);
            font-size: 1.1rem;
        }

        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            height: 4px;
            width: 100%;
            background: linear-gradient(90deg, var(--brand), var(--brand-2));
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: var(--brand);
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .certificates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
        }

        .certificate-card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .certificate-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            height: 4px;
            width: 100%;
            background: linear-gradient(90deg, var(--brand), var(--brand-2));
        }

        .certificate-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .certificate-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .certificate-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .certificate-number {
            font-size: 0.9rem;
            color: var(--muted);
            font-family: monospace;
            background: var(--bg-secondary);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
        }

        .company-logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--brand);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .company-details h4 {
            color: var(--ink);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .company-details p {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .certificate-info {
            margin-bottom: 1.5rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: var(--ink);
        }

        .info-value {
            color: var(--muted);
            text-align: right;
        }

        .certificate-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            background: var(--brand);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn:hover {
            background: var(--brand-2);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: #6b7280;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-success {
            background: var(--success);
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .no-certificates {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--panel);
            border: 2px dashed var(--border-light);
            border-radius: var(--radius-lg);
            margin: 2rem 0;
        }

        .no-certificates .icon {
            font-size: 4rem;
            color: var(--brand);
            margin-bottom: 1rem;
        }

        .no-certificates h3 {
            color: var(--ink);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .no-certificates p {
            color: var(--muted);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .certificate-badge {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .certificates-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-section {
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
                <span>My Certificates</span>
            </div>
            <h1 class="page-title">
                <span>🏆</span>
                My Certificates
            </h1>
            <p class="page-subtitle">View and manage your professional certificates</p>
        </div>

        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-icon">🏆</div>
                <div class="stat-number"><?php echo $stats['total_certificates']; ?></div>
                <div class="stat-label">Total Certificates</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-number"><?php echo $stats['this_year_certificates']; ?></div>
                <div class="stat-label">This Year</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🏢</div>
                <div class="stat-number"><?php echo $stats['companies_count']; ?></div>
                <div class="stat-label">Companies</div>
            </div>
        </div>

        <?php if (empty($certificates)): ?>
            <div class="no-certificates">
                <div class="icon">🏆</div>
                <h3>No Certificates Yet</h3>
                <p>You haven't received any certificates yet. Complete courses with companies to earn professional certificates that showcase your skills and achievements.</p>
                <a href="browse_internships.php" class="btn btn-success">
                    <i class="fas fa-search"></i>
                    Browse Opportunities
                </a>
            </div>
        <?php else: ?>
            <div class="certificates-grid">
                <?php foreach ($certificates as $certificate): ?>
                    <div class="certificate-card">
                        <div class="certificate-header">
                            <div>
                                <h3 class="certificate-title"><?php echo htmlspecialchars($certificate['course_name']); ?></h3>
                                <div class="certificate-number"><?php echo htmlspecialchars($certificate['certificate_number']); ?></div>
                            </div>
                            <span class="certificate-badge">Verified</span>
                        </div>
                        
                        <div class="company-info">
                            <div class="company-logo">
                                <?php if ($certificate['logo_path']): ?>
                                    <img src="<?php echo htmlspecialchars($certificate['logo_path']); ?>" alt="Company Logo" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($certificate['company_name'], 0, 2)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="company-details">
                                <h4><?php echo htmlspecialchars($certificate['company_name']); ?></h4>
                                <p>Issued Certificate</p>
                            </div>
                        </div>
                        
                        <div class="certificate-info">
                            <div class="info-item">
                                <span class="info-label">Completion Date:</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($certificate['completion_date'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Issued Date:</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($certificate['issued_date'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status:</span>
                                <span class="info-value" style="color: var(--success); font-weight: 600;">Active</span>
                            </div>
                        </div>

                        <?php if ($certificate['course_description']): ?>
                            <div style="margin-bottom: 1.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md);">
                                <strong>Course Description:</strong><br>
                                <span style="color: var(--muted);"><?php echo htmlspecialchars($certificate['course_description']); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="certificate-actions">
                            <button class="btn btn-sm btn-secondary" onclick="viewCertificate(<?php echo $certificate['certificate_id']; ?>)">
                                <i class="fas fa-eye"></i>
                                View Certificate
                            </button>
                            <button class="btn btn-sm btn-success" onclick="downloadCertificate(<?php echo $certificate['certificate_id']; ?>)">
                                <i class="fas fa-download"></i>
                                Download PDF
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function viewCertificate(certificateId) {
            // Open certificate in new window
            window.open('view_certificate.php?id=' + certificateId, '_blank');
        }

        function downloadCertificate(certificateId) {
            // Download certificate as PDF
            window.open('download_certificate.php?id=' + certificateId, '_blank');
        }
    </script>
</body>
</html>
