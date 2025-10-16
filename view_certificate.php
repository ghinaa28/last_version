<?php
session_start();
include "connection.php";

// Check if certificate ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: login.php");
    exit();
}

$certificate_id = intval($_GET['id']);

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

// Get certificate details
$certificate_sql = "SELECT 
    c.*,
    s.first_name,
    s.last_name,
    s.email as student_email,
    comp.company_name,
    comp.logo_path,
    comp.address as company_address
    FROM certificates c
    JOIN students s ON c.student_id = s.student_id
    JOIN companies comp ON c.company_id = comp.company_id
    WHERE c.certificate_id = ? AND c.status = 'active'";

$stmt = $conn->prepare($certificate_sql);
$stmt->bind_param("i", $certificate_id);
$stmt->execute();
$certificate = $stmt->get_result()->fetch_assoc();

if (!$certificate) {
    die("Certificate not found or has been revoked.");
}

// Check if user has permission to view this certificate
$can_view = false;

if (isset($_SESSION['student_id']) && $_SESSION['student_id'] == $certificate['student_id']) {
    $can_view = true; // Student viewing their own certificate
} elseif (isset($_SESSION['company_id']) && $_SESSION['company_id'] == $certificate['company_id']) {
    $can_view = true; // Company viewing their issued certificate
} elseif (isset($_SESSION['admin_id'])) {
    $can_view = true; // Admin can view any certificate
}

if (!$can_view) {
    die("You don't have permission to view this certificate.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - <?php echo htmlspecialchars($certificate['course_name']); ?></title>
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
            --text-dark: #0f172a;
            --text-light: #475569;
            --text-white: #ffffff;
            --bg-primary: #ffffff;
            --bg-secondary: #f6f8fb;
            --border-light: #e5e7eb;
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
            padding: 2rem;
        }

        .certificate-container {
            max-width: 800px;
            margin: 0 auto;
            background: var(--panel);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            border: 1px solid var(--line);
        }

        .certificate-header {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .certificate-header::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }

        .certificate-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .certificate-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .certificate-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .certificate-body {
            padding: 3rem 2rem;
            text-align: center;
        }

        .award-text {
            font-size: 1.5rem;
            color: var(--muted);
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .student-name {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .course-details {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin: 2rem 0;
            border: 1px solid var(--border-light);
        }

        .course-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1rem;
        }

        .course-description {
            color: var(--muted);
            font-size: 1.1rem;
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }

        .certificate-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .info-card {
            background: var(--panel);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .info-label {
            font-size: 0.9rem;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-size: 1.1rem;
            color: var(--ink);
            font-weight: 600;
        }

        .company-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin: 2rem 0;
            padding: 1.5rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
        }

        .company-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--brand);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .company-info h3 {
            color: var(--ink);
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .company-info p {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .certificate-footer {
            background: var(--bg-secondary);
            padding: 2rem;
            text-align: center;
            border-top: 1px solid var(--border-light);
        }

        .certificate-number {
            font-family: monospace;
            font-size: 1rem;
            color: var(--muted);
            background: var(--panel);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            display: inline-block;
            border: 1px solid var(--border-light);
        }

        .verification-info {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: var(--muted);
        }

        .actions {
            position: fixed;
            top: 2rem;
            right: 2rem;
            display: flex;
            gap: 1rem;
            z-index: 1000;
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
            box-shadow: var(--shadow-md);
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
            background: #4ade80;
        }

        .btn-success:hover {
            background: #059669;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .certificate-title {
                font-size: 2rem;
            }
            
            .student-name {
                font-size: 2rem;
            }
            
            .certificate-body {
                padding: 2rem 1rem;
            }
            
            .actions {
                position: static;
                justify-content: center;
                margin-bottom: 2rem;
            }
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .actions {
                display: none;
            }
            
            .certificate-container {
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>
<body>

    <div class="actions">
        <button class="btn btn-secondary" onclick="window.print()">
            <i class="fas fa-print"></i>
            Print
        </button>
        <a href="download_certificate.php?id=<?php echo $certificate_id; ?>" class="btn btn-success">
            <i class="fas fa-download"></i>
            Download PDF
        </a>
        <button class="btn btn-secondary" onclick="window.close()">
            <i class="fas fa-times"></i>
            Close
        </button>
    </div>

    <div class="certificate-container">
        <div class="certificate-header">
            <div class="certificate-icon">🏆</div>
            <h1 class="certificate-title">Certificate of Completion</h1>
            <p class="certificate-subtitle">Professional Achievement Recognition</p>
        </div>

        <div class="certificate-body">
            <p class="award-text">This is to certify that</p>
            
            <h2 class="student-name"><?php echo htmlspecialchars($certificate['first_name'] . ' ' . $certificate['last_name']); ?></h2>
            
            <p class="award-text">has successfully completed the course</p>

            <div class="course-details">
                <h3 class="course-name"><?php echo htmlspecialchars($certificate['course_name']); ?></h3>
                <?php if ($certificate['course_description']): ?>
                    <p class="course-description"><?php echo htmlspecialchars($certificate['course_description']); ?></p>
                <?php endif; ?>
            </div>

            <div class="certificate-info">
                <div class="info-card">
                    <div class="info-label">Completion Date</div>
                    <div class="info-value"><?php echo date('F d, Y', strtotime($certificate['completion_date'])); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Issued Date</div>
                    <div class="info-value"><?php echo date('F d, Y', strtotime($certificate['issued_date'])); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Status</div>
                    <div class="info-value" style="color: #4ade80;">Verified</div>
                </div>
            </div>

            <div class="company-section">
                <div class="company-logo">
                    <?php if ($certificate['logo_path']): ?>
                        <img src="<?php echo htmlspecialchars($certificate['logo_path']); ?>" alt="Company Logo" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($certificate['company_name'], 0, 2)); ?>
                    <?php endif; ?>
                </div>
                <div class="company-info">
                    <h3><?php echo htmlspecialchars($certificate['company_name']); ?></h3>
                    <p>Authorized Training Provider</p>
                </div>
            </div>
        </div>

        <div class="certificate-footer">
            <div class="certificate-number">Certificate #<?php echo htmlspecialchars($certificate['certificate_number']); ?></div>
            <div class="verification-info">
                This certificate can be verified online at our official portal.<br>
                Issued on <?php echo date('F d, Y', strtotime($certificate['issued_date'])); ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus for better printing experience
        window.onload = function() {
            // Add some interactive elements
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
            });
        };
    </script>
</body>
</html>
