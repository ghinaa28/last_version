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
$sql = "SELECT i.*, c.company_name, c.industry, c.logo_path,
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

// Check if already applied
if ($internship['has_applied'] > 0) {
    header("Location: internship_details.php?id=" . $internship_id);
    exit();
}

// Check if application deadline has passed
$deadline_passed = strtotime($internship['application_deadline']) < time();
if ($deadline_passed) {
    header("Location: internship_details.php?id=" . $internship_id);
    exit();
}

$success_message = "";
$error_message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cover_letter = trim($_POST['cover_letter'] ?? '');
    $motivation = trim($_POST['motivation'] ?? '');
    $relevant_experience = trim($_POST['relevant_experience'] ?? '');
    $why_this_company = trim($_POST['why_this_company'] ?? '');
    $career_goals = trim($_POST['career_goals'] ?? '');
    $additional_info = trim($_POST['additional_info'] ?? '');
    
    // Check monthly application limit policy
    $current_month = date('Y-m');
    $monthly_applications_sql = "SELECT COUNT(*) as monthly_count 
                                FROM internship_applications 
                                WHERE student_id = ? 
                                AND DATE_FORMAT(application_date, '%Y-%m') = ?";
    $stmt = $conn->prepare($monthly_applications_sql);
    $stmt->bind_param("is", $student_id, $current_month);
    $stmt->execute();
    $monthly_count = $stmt->get_result()->fetch_assoc()['monthly_count'];
    
    // Validation
    $errors = [];
    
    // Check if student has already applied this month
    if ($monthly_count >= 1) {
        $errors[] = "You can only apply for one internship per month. You have already applied for an internship this month. Please wait until next month to apply for another internship.";
    }
    
    if (empty($cover_letter)) {
        $errors[] = "Cover letter is required";
    } elseif (strlen($cover_letter) < 100) {
        $errors[] = "Cover letter must be at least 100 characters long";
    }
    
    if (empty($motivation)) {
        $errors[] = "Motivation statement is required";
    } elseif (strlen($motivation) < 50) {
        $errors[] = "Motivation statement must be at least 50 characters long";
    }
    
    if (empty($why_this_company)) {
        $errors[] = "Why this company is required";
    } elseif (strlen($why_this_company) < 30) {
        $errors[] = "Why this company must be at least 30 characters long";
    }
    
    if (empty($errors)) {
        // Insert application into database
        $stmt = $conn->prepare("INSERT INTO internship_applications (internship_id, student_id, cover_letter, motivation, relevant_experience, why_this_company, career_goals, additional_info, application_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')");
        
        if ($stmt) {
            $stmt->bind_param("iissssss", $internship_id, $student_id, $cover_letter, $motivation, $relevant_experience, $why_this_company, $career_goals, $additional_info);
            
            if ($stmt->execute()) {
                $success_message = "Your application has been submitted successfully! The company will review your application and get back to you soon.";
            } else {
                $error_message = "Failed to submit application. Please try again.";
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
    <title>Apply for <?php echo htmlspecialchars($internship['title']); ?> - Internship Application</title>
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
            max-width: 1000px;
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

        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .job-info h1 {
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
            width: 50px;
            height: 50px;
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

        .application-form {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section:last-child {
            margin-bottom: 0;
        }

        .form-section h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-label.required::after {
            content: " *";
            color: var(--error);
        }

        .form-textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-family: inherit;
            font-size: 1rem;
            line-height: 1.6;
            resize: vertical;
            transition: var(--transition);
            background: var(--bg-primary);
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .form-input {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-family: inherit;
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .form-help {
            font-size: 0.875rem;
            color: var(--muted);
            margin-top: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--line);
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

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .alert-error {
            background: rgba(248, 113, 113, 0.1);
            color: #dc2626;
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        .character-count {
            font-size: 0.875rem;
            color: var(--muted);
            text-align: right;
            margin-top: 0.5rem;
        }

        .character-count.warning {
            color: var(--warning);
        }

        .character-count.error {
            color: var(--error);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .application-header {
                flex-direction: column;
                gap: 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
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
                <a href="internship_details.php?id=<?php echo $internship_id; ?>">Internship Details</a>
                <i class="fas fa-chevron-right"></i>
                <span>Apply Now</span>
            </div>

            <div class="application-header">
                <div class="job-info">
                    <h1>Apply for <?php echo htmlspecialchars($internship['title']); ?></h1>
                    <div class="company-info">
                        <?php if ($internship['logo_path']): ?>
                            <img src="<?php echo htmlspecialchars($internship['logo_path']); ?>" alt="Company Logo" class="company-logo">
                        <?php endif; ?>
                        <div class="company-details">
                            <h3><?php echo htmlspecialchars($internship['company_name']); ?></h3>
                            <p><?php echo htmlspecialchars($internship['industry']); ?></p>
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
                    </div>
                </div>
            </div>
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

        <?php if (!$success_message): ?>
            <form method="POST" class="application-form">
                <div class="form-section">
                    <h3>
                        <i class="fas fa-file-alt"></i>
                        Cover Letter
                    </h3>
                    <div class="form-group">
                        <label for="cover_letter" class="form-label required">Cover Letter</label>
                        <textarea 
                            id="cover_letter" 
                            name="cover_letter" 
                            class="form-textarea" 
                            rows="8" 
                            placeholder="Write a compelling cover letter explaining why you're interested in this internship and what makes you a great candidate..."
                            required
                        ><?php echo htmlspecialchars($_POST['cover_letter'] ?? ''); ?></textarea>
                        <div class="form-help">Minimum 100 characters. Explain your interest in the role and relevant qualifications.</div>
                        <div class="character-count" id="cover_letter_count">0 characters</div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>
                        <i class="fas fa-heart"></i>
                        Motivation & Goals
                    </h3>
                    <div class="form-group">
                        <label for="motivation" class="form-label required">Why are you interested in this internship?</label>
                        <textarea 
                            id="motivation" 
                            name="motivation" 
                            class="form-textarea" 
                            rows="5" 
                            placeholder="Explain what attracts you to this specific internship and how it aligns with your career goals..."
                            required
                        ><?php echo htmlspecialchars($_POST['motivation'] ?? ''); ?></textarea>
                        <div class="form-help">Minimum 50 characters. Share your motivation and career aspirations.</div>
                        <div class="character-count" id="motivation_count">0 characters</div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>
                        <i class="fas fa-briefcase"></i>
                        Experience & Skills
                    </h3>
                    <div class="form-group">
                        <label for="relevant_experience" class="form-label">Relevant Experience</label>
                        <textarea 
                            id="relevant_experience" 
                            name="relevant_experience" 
                            class="form-textarea" 
                            rows="6" 
                            placeholder="Describe any relevant work experience, projects, or skills that make you a good fit for this internship..."
                        ><?php echo htmlspecialchars($_POST['relevant_experience'] ?? ''); ?></textarea>
                        <div class="form-help">Optional. Highlight relevant experience, projects, or skills.</div>
                        <div class="character-count" id="relevant_experience_count">0 characters</div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>
                        <i class="fas fa-building"></i>
                        Company Interest
                    </h3>
                    <div class="form-group">
                        <label for="why_this_company" class="form-label required">Why are you interested in this company?</label>
                        <textarea 
                            id="why_this_company" 
                            name="why_this_company" 
                            class="form-textarea" 
                            rows="5" 
                            placeholder="Explain what attracts you to this specific company, their values, mission, or work culture..."
                            required
                        ><?php echo htmlspecialchars($_POST['why_this_company'] ?? ''); ?></textarea>
                        <div class="form-help">Minimum 30 characters. Show your research about the company.</div>
                        <div class="character-count" id="why_this_company_count">0 characters</div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>
                        <i class="fas fa-target"></i>
                        Career Goals & Additional Information
                    </h3>
                    <div class="form-group">
                        <label for="career_goals" class="form-label">Career Goals</label>
                        <textarea 
                            id="career_goals" 
                            name="career_goals" 
                            class="form-textarea" 
                            rows="4" 
                            placeholder="Describe your career aspirations and how this internship fits into your long-term goals..."
                        ><?php echo htmlspecialchars($_POST['career_goals'] ?? ''); ?></textarea>
                        <div class="form-help">Optional. Share your career vision and how this internship helps you achieve it.</div>
                        <div class="character-count" id="career_goals_count">0 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="additional_info" class="form-label">Additional Information</label>
                        <textarea 
                            id="additional_info" 
                            name="additional_info" 
                            class="form-textarea" 
                            rows="4" 
                            placeholder="Any additional information you'd like to share with the company..."
                        ><?php echo htmlspecialchars($_POST['additional_info'] ?? ''); ?></textarea>
                        <div class="form-help">Optional. Any other relevant information.</div>
                        <div class="character-count" id="additional_info_count">0 characters</div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="internship_details.php?id=<?php echo $internship_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Details
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Submit Application
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="application-form">
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--success); margin-bottom: 1rem;"></i>
                    <h2 style="color: var(--ink); margin-bottom: 1rem;">Application Submitted Successfully!</h2>
                    <p style="color: var(--muted); margin-bottom: 2rem;">Your application has been sent to <?php echo htmlspecialchars($internship['company_name']); ?>. They will review your application and get back to you soon.</p>
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="browse_internships.php" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Browse More Internships
                        </a>
                        <a href="student_dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i>
                            Back to Portal
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Character count functionality
        function updateCharacterCount(textareaId, countId) {
            const textarea = document.getElementById(textareaId);
            const countElement = document.getElementById(countId);
            
            if (textarea && countElement) {
                const count = textarea.value.length;
                countElement.textContent = count + ' characters';
                
                // Add warning/error classes based on count
                countElement.classList.remove('warning', 'error');
                if (count > 0 && count < 100) {
                    countElement.classList.add('warning');
                } else if (count > 2000) {
                    countElement.classList.add('error');
                }
            }
        }

        // Initialize character counts
        document.addEventListener('DOMContentLoaded', function() {
            const textareas = ['cover_letter', 'motivation', 'relevant_experience', 'why_this_company', 'career_goals', 'additional_info'];
            
            textareas.forEach(function(textareaId) {
                const textarea = document.getElementById(textareaId);
                if (textarea) {
                    // Initial count
                    updateCharacterCount(textareaId, textareaId + '_count');
                    
                    // Update on input
                    textarea.addEventListener('input', function() {
                        updateCharacterCount(textareaId, textareaId + '_count');
                    });
                }
            });
        });

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const coverLetter = document.getElementById('cover_letter').value.trim();
                    const motivation = document.getElementById('motivation').value.trim();
                    const whyThisCompany = document.getElementById('why_this_company').value.trim();
                    
                    if (coverLetter.length < 100) {
                        e.preventDefault();
                        alert('Cover letter must be at least 100 characters long.');
                        return;
                    }
                    
                    if (motivation.length < 50) {
                        e.preventDefault();
                        alert('Motivation statement must be at least 50 characters long.');
                        return;
                    }
                    
                    if (whyThisCompany.length < 30) {
                        e.preventDefault();
                        alert('Why this company must be at least 30 characters long.');
                        return;
                    }
                });
            }
        });
    </script>
</body>
</html>
