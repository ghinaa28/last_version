<?php
session_start();
include "connection.php";

// Check if user is logged in as student
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$company_id = $_GET['company_id'] ?? '';
$internship_id = $_GET['internship_id'] ?? '';

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

// Get company information if company_id is provided
$company = null;
if ($company_id) {
    $stmt = $conn->prepare("SELECT * FROM companies WHERE company_id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
}

// Get internship information if internship_id is provided
$internship = null;
if ($internship_id) {
    $stmt = $conn->prepare("SELECT i.*, c.company_name FROM internships i JOIN companies c ON i.company_id = c.company_id WHERE i.internship_id = ?");
    $stmt->bind_param("i", $internship_id);
    $stmt->execute();
    $internship = $stmt->get_result()->fetch_assoc();
}

// Get companies that the student has applied to
$applied_companies_sql = "SELECT DISTINCT c.company_id, c.company_name, c.industry, c.logo_path,
                         COUNT(ia.application_id) as application_count
                         FROM companies c
                         JOIN internships i ON c.company_id = i.company_id
                         JOIN internship_applications ia ON i.internship_id = ia.internship_id
                         WHERE ia.student_id = ?
                         GROUP BY c.company_id, c.company_name, c.industry, c.logo_path
                         ORDER BY c.company_name";
$stmt = $conn->prepare($applied_companies_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$applied_companies = $stmt->get_result();

// Get internships for the selected company (if any)
$company_internships = null;
if ($company_id) {
    $internships_sql = "SELECT i.internship_id, i.title, i.department, i.type, i.location
                       FROM internships i
                       JOIN internship_applications ia ON i.internship_id = ia.internship_id
                       WHERE i.company_id = ? AND ia.student_id = ?
                       ORDER BY i.title";
    $stmt = $conn->prepare($internships_sql);
    $stmt->bind_param("ii", $company_id, $student_id);
    $stmt->execute();
    $company_internships = $stmt->get_result();
}

$success_message = "";
$error_message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_company_id = $_POST['company_id'] ?? '';
    $selected_internship_id = $_POST['internship_id'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($selected_company_id)) {
        $errors[] = "Please select a company";
    }
    
    if (empty($selected_internship_id)) {
        $errors[] = "Please select an internship";
    }
    
    if (empty($subject)) {
        $errors[] = "Subject is required";
    } elseif (strlen($subject) < 5) {
        $errors[] = "Subject must be at least 5 characters long";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    } elseif (strlen($message) < 20) {
        $errors[] = "Message must be at least 20 characters long";
    }
    
    if (empty($errors)) {
        // Insert message into database
        $stmt = $conn->prepare("INSERT INTO messages (student_id, company_id, internship_id, subject, message_content, sender_type, is_read, created_at) VALUES (?, ?, ?, ?, ?, 'student', 0, NOW())");
        
        if ($stmt) {
            $stmt->bind_param("iiiss", $student_id, $selected_company_id, $selected_internship_id, $subject, $message);
            
            if ($stmt->execute()) {
                $success_message = "Your message has been sent successfully! The company will receive your message and respond accordingly.";
            } else {
                $error_message = "Failed to send message. Please try again.";
            }
        } else {
            $error_message = "Database error. Please try again.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Company - Student Portal</title>
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
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --radius: 12px;
            --radius-lg: 16px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, rgba(14,165,168,0.05), rgba(34,211,238,0.05));
            color: var(--ink);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: var(--brand);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .breadcrumb a:hover {
            color: var(--brand-2);
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--muted);
            font-size: 1.1rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .companies-section {
            background: var(--panel);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--line);
        }

        .companies-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .company-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .company-item {
            padding: 1rem;
            border: 2px solid var(--line);
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .company-item:hover {
            border-color: var(--brand);
            background: rgba(14,165,168,0.05);
        }

        .company-item.selected {
            border-color: var(--brand);
            background: rgba(14,165,168,0.1);
        }

        .company-logo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--line);
        }

        .company-info h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .company-info p {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .application-count {
            background: var(--brand);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: auto;
        }

        .form-section {
            background: var(--panel);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--line);
        }

        .form-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--line);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--brand);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .btn {
            background: var(--brand);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn:hover {
            background: var(--brand-2);
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .btn:disabled {
            background: var(--muted);
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16,185,129,0.1);
            color: var(--success);
            border: 1px solid rgba(16,185,129,0.2);
        }

        .alert-error {
            background: rgba(239,68,68,0.1);
            color: var(--error);
            border: 1px solid rgba(239,68,68,0.2);
        }

        .no-companies {
            text-align: center;
            padding: 2rem;
            color: var(--muted);
        }

        .no-companies i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--line);
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 1rem;
            }
            
            h1 {
                font-size: 2rem;
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
                <span>Contact Company</span>
            </div>
            <h1>Contact Company</h1>
            <p class="subtitle">Send a message to companies you've applied to for internships</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <div class="companies-section">
                <h2>
                    <i class="fas fa-building"></i>
                    Companies You've Applied To
                </h2>
                
                <?php if ($applied_companies->num_rows > 0): ?>
                    <div class="company-list">
                        <?php while ($comp = $applied_companies->fetch_assoc()): ?>
                            <div class="company-item" onclick="selectCompany(<?php echo $comp['company_id']; ?>, '<?php echo htmlspecialchars($comp['company_name']); ?>')">
                                <?php if ($comp['logo_path']): ?>
                                    <img src="<?php echo htmlspecialchars($comp['logo_path']); ?>" alt="<?php echo htmlspecialchars($comp['company_name']); ?>" class="company-logo">
                                <?php else: ?>
                                    <div class="company-logo" style="background: var(--brand); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                        <?php echo strtoupper(substr($comp['company_name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="company-info">
                                    <h3><?php echo htmlspecialchars($comp['company_name']); ?></h3>
                                    <p><?php echo htmlspecialchars($comp['industry']); ?></p>
                                </div>
                                <span class="application-count"><?php echo $comp['application_count']; ?> application<?php echo $comp['application_count'] > 1 ? 's' : ''; ?></span>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-companies">
                        <i class="fas fa-inbox"></i>
                        <h3>No Applications Yet</h3>
                        <p>You need to apply for internships first before you can contact companies.</p>
                        <a href="browse_internships.php" class="btn" style="margin-top: 1rem;">
                            <i class="fas fa-search"></i>
                            Browse Internships
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-section">
                <h2>
                    <i class="fas fa-envelope"></i>
                    Send Message
                </h2>
                
                <form method="POST" action="contact_company.php">
                    <input type="hidden" name="company_id" id="selected_company_id" value="<?php echo htmlspecialchars($company_id); ?>">
                    
                    <div class="form-group">
                        <label for="company_name">Company</label>
                        <input type="text" id="selected_company_name" value="<?php echo $company ? htmlspecialchars($company['company_name']) : ''; ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="internship_id">Internship *</label>
                        <select name="internship_id" id="internship_id" required>
                            <option value="">Select an internship</option>
                            <?php if ($company_internships): ?>
                                <?php while ($intern = $company_internships->fetch_assoc()): ?>
                                    <option value="<?php echo $intern['internship_id']; ?>" <?php echo $internship_id == $intern['internship_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($intern['title']); ?> - <?php echo htmlspecialchars($intern['department']); ?> (<?php echo htmlspecialchars($intern['type']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject *</label>
                        <input type="text" name="subject" id="subject" placeholder="Enter message subject" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea name="message" id="message" placeholder="Write your message to the company..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn" id="send_btn" disabled>
                        <i class="fas fa-paper-plane"></i>
                        Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function selectCompany(companyId, companyName) {
            // Remove selected class from all items
            document.querySelectorAll('.company-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Add selected class to clicked item
            event.currentTarget.classList.add('selected');
            
            // Update form fields
            document.getElementById('selected_company_id').value = companyId;
            document.getElementById('selected_company_name').value = companyName;
            
            // Load internships for this company
            loadInternships(companyId);
            
            // Enable send button
            document.getElementById('send_btn').disabled = false;
        }

        function loadInternships(companyId) {
            // Clear existing options
            const internshipSelect = document.getElementById('internship_id');
            internshipSelect.innerHTML = '<option value="">Loading internships...</option>';
            
            // Fetch internships via AJAX
            fetch(`get_company_internships.php?company_id=${companyId}&student_id=<?php echo $student_id; ?>`)
                .then(response => response.json())
                .then(data => {
                    internshipSelect.innerHTML = '<option value="">Select an internship</option>';
                    if (data.success && data.internships.length > 0) {
                        data.internships.forEach(internship => {
                            const option = document.createElement('option');
                            option.value = internship.internship_id;
                            option.textContent = `${internship.title} - ${internship.department} (${internship.type})`;
                            internshipSelect.appendChild(option);
                        });
                    } else {
                        internshipSelect.innerHTML = '<option value="">No internships available</option>';
                    }
                })
                .catch(error => {
                    console.error('Error loading internships:', error);
                    internshipSelect.innerHTML = '<option value="">Error loading internships</option>';
                });
        }

        // Auto-select company if provided in URL
        <?php if ($company_id): ?>
            document.addEventListener('DOMContentLoaded', function() {
                selectCompany(<?php echo $company_id; ?>, '<?php echo $company ? htmlspecialchars($company['company_name']) : ''; ?>');
            });
        <?php endif; ?>
    </script>
</body>
</html>
