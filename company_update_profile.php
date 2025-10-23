<?php
session_start();
include "connection.php";

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

// Get company information
$company_id = $_SESSION['company_id'];
$stmt = $conn->prepare("SELECT * FROM companies WHERE company_id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();

if (!$company) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle form submission for profile updates
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Sanitize and validate input data
    $company_name = trim($conn->real_escape_string($_POST['company_name']));
    $industry = trim($conn->real_escape_string($_POST['industry']));
    $company_size = !empty($_POST['company_size']) ? trim($conn->real_escape_string($_POST['company_size'])) : null;
    $founded_year = !empty($_POST['founded_year']) ? intval($_POST['founded_year']) : null;
    $website = !empty($_POST['website']) ? trim($conn->real_escape_string($_POST['website'])) : null;
    $phone = !empty($_POST['phone']) ? trim($conn->real_escape_string($_POST['phone'])) : null;
    $description = !empty($_POST['description']) ? trim($conn->real_escape_string($_POST['description'])) : null;
    
    // Validation
    $errors = [];
    
    // Required field validation
    if (empty($company_name) || strlen($company_name) < 2) {
        $errors[] = "Company name must be at least 2 characters long.";
    }
    if (empty($industry) || strlen($industry) < 2) {
        $errors[] = "Industry must be at least 2 characters long.";
    }
    
    // Website validation (if provided)
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = "Please enter a valid website URL (include http:// or https://).";
    }
    
    // Phone validation (if provided)
    if (!empty($phone) && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,15}$/', $phone)) {
        $errors[] = "Please enter a valid phone number.";
    }
    
    // Founded year validation (if provided)
    if (!empty($founded_year) && ($founded_year < 1800 || $founded_year > date('Y'))) {
        $errors[] = "Founded year must be between 1800 and " . date('Y') . ".";
    }
    
    // Handle company logo upload
    $logo_path = $company['logo_path']; // Keep existing logo by default
    
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "uploads/companies/logos/";
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_info = pathinfo($_FILES['logo']['name']);
        $file_extension = strtolower($file_info['extension']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        // Validate file type
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "Company logo must be a JPG, PNG, or GIF file.";
        }
        
        // Validate file size (max 5MB)
        if ($_FILES['logo']['size'] > 5 * 1024 * 1024) {
            $errors[] = "Company logo must be smaller than 5MB.";
        }
        
        // Validate image dimensions
        $image_info = getimagesize($_FILES['logo']['tmp_name']);
        if ($image_info === false) {
            $errors[] = "Invalid image file.";
        } else {
            $max_width = 1200;
            $max_height = 1200;
            if ($image_info[0] > $max_width || $image_info[1] > $max_height) {
                $errors[] = "Company logo dimensions must not exceed {$max_width}x{$max_height} pixels.";
            }
        }
        
        if (empty($errors)) {
            // Generate unique filename
            $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                // Delete old logo if it exists
                if (!empty($company['logo_path']) && file_exists($company['logo_path'])) {
                    unlink($company['logo_path']);
                }
                $logo_path = $upload_path;
            } else {
                $errors[] = "Failed to upload company logo.";
            }
        }
    }
    
    // If no validation errors, update the profile
    if (empty($errors)) {
        $update_sql = "UPDATE companies SET 
                      company_name = ?, 
                      industry = ?, 
                      company_size = ?,
                      founded_year = ?,
                      website = ?,
                      phone = ?,
                      description = ?,
                      logo_path = ?
                      WHERE company_id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssssssssi", $company_name, $industry, $company_size, $founded_year, $website, $phone, $description, $logo_path, $company_id);
        
        if ($stmt->execute()) {
            $success_message = "Company profile updated successfully!";
            // Refresh company data
            $stmt = $conn->prepare("SELECT * FROM companies WHERE company_id = ?");
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            $company = $stmt->get_result()->fetch_assoc();
        } else {
            $error_message = "Error updating profile: " . $conn->error;
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
    <title>Update Profile - Company Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #0ea5a8;
            --brand-2: #22d3ee;
            --ink: #0b1f3a;
            --muted: #475569;
            --panel: #ffffff;
            --bg-primary: #f8fafc;
            --bg-secondary: #f1f5f9;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --text-white: #ffffff;
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--ink);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            margin-bottom: 2rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
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
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--muted);
            font-size: 1.1rem;
        }

        .form-container {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-light);
            max-width: 800px;
            margin: 0 auto;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-light);
        }

        .form-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .form-subtitle {
            color: var(--muted);
            font-size: 1rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-label.required::after {
            content: " *";
            color: var(--error);
        }

        .form-input {
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
            color: var(--ink);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .form-input:disabled {
            background: var(--bg-secondary);
            color: var(--muted);
            cursor: not-allowed;
        }

        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1rem;
            border: 2px dashed var(--border);
            border-radius: var(--radius-lg);
            background: var(--bg-primary);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .file-input-label:hover {
            border-color: var(--brand);
            background: rgba(14, 165, 168, 0.05);
        }

        .current-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
        }

        .current-logo img {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-lg);
            object-fit: cover;
            border: 2px solid var(--border);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-light);
        }

        .btn {
            padding: 0.875rem 2rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            border: none;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--brand);
            color: var(--text-white);
        }

        .btn-primary:hover {
            background: var(--brand-2);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--muted);
            border: 2px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--border-light);
            color: var(--ink);
        }

        .message {
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .message-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #059669;
        }

        .message-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #dc2626;
        }

        .help-text {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 0.25rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .form-container {
                padding: 1.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="company_dashboard.php">Company Portal</a>
                <i class="fas fa-chevron-right"></i>
                <a href="company_profile.php">Company Profile</a>
                <i class="fas fa-chevron-right"></i>
                <span>Update Profile</span>
            </div>
            <h1 class="page-title">Update Company Profile</h1>
            <p class="page-subtitle">Keep your company information up to date</p>
        </div>

        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">
                    <i class="fas fa-building"></i>
                    Update Company Information
                </h2>
                <p class="form-subtitle">Modify your company details and branding</p>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="message message-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="message message-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" enctype="multipart/form-data">
                <div class="form-grid">
                    <!-- Company Information -->
                    <div class="form-group">
                        <label class="form-label required">Company Name</label>
                        <input type="text" name="company_name" class="form-input" 
                               value="<?php echo htmlspecialchars($company['company_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Industry</label>
                        <input type="text" name="industry" class="form-input" 
                               value="<?php echo htmlspecialchars($company['industry']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Company Size</label>
                        <select name="company_size" class="form-input">
                            <option value="">Select company size</option>
                            <option value="1-10 employees" <?php echo ($company['company_size'] ?? '') == '1-10 employees' ? 'selected' : ''; ?>>1-10 employees</option>
                            <option value="11-50 employees" <?php echo ($company['company_size'] ?? '') == '11-50 employees' ? 'selected' : ''; ?>>11-50 employees</option>
                            <option value="51-200 employees" <?php echo ($company['company_size'] ?? '') == '51-200 employees' ? 'selected' : ''; ?>>51-200 employees</option>
                            <option value="201-500 employees" <?php echo ($company['company_size'] ?? '') == '201-500 employees' ? 'selected' : ''; ?>>201-500 employees</option>
                            <option value="501-1000 employees" <?php echo ($company['company_size'] ?? '') == '501-1000 employees' ? 'selected' : ''; ?>>501-1000 employees</option>
                            <option value="1000+ employees" <?php echo ($company['company_size'] ?? '') == '1000+ employees' ? 'selected' : ''; ?>>1000+ employees</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Founded Year</label>
                        <input type="number" name="founded_year" class="form-input" 
                               value="<?php echo htmlspecialchars($company['founded_year'] ?? ''); ?>" 
                               min="1800" max="<?php echo date('Y'); ?>" placeholder="e.g., 2020">
                        <div class="help-text">Optional - Year your company was founded</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Website</label>
                        <input type="url" name="website" class="form-input" 
                               value="<?php echo htmlspecialchars($company['website'] ?? ''); ?>" 
                               placeholder="https://www.example.com">
                        <div class="help-text">Optional - Include http:// or https://</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-input" 
                               value="<?php echo htmlspecialchars($company['phone'] ?? ''); ?>" 
                               placeholder="Enter your phone number">
                        <div class="help-text">Optional - Include country code if international</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" 
                               value="<?php echo htmlspecialchars($company['email']); ?>" disabled>
                        <div class="help-text">Email cannot be changed. Contact support if needed.</div>
                    </div>

                    <!-- Company Logo -->
                    <div class="form-group full-width">
                        <label class="form-label">Company Logo</label>
                        
                        <?php if (!empty($company['logo_path']) && file_exists($company['logo_path'])): ?>
                            <div class="current-logo">
                                <img src="<?php echo htmlspecialchars($company['logo_path']); ?>" alt="Current Company Logo">
                                <div>
                                    <strong>Current Logo</strong>
                                    <div class="help-text">Upload a new logo to replace the current one</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="file-input-wrapper">
                            <input type="file" name="logo" class="file-input" accept="image/*">
                            <label class="file-input-label">
                                <i class="fas fa-image"></i>
                                <span>Choose Company Logo or drag and drop</span>
                            </label>
                        </div>
                        <div class="help-text">JPG, PNG, or GIF. Max 5MB. Recommended: 400x400 pixels</div>
                    </div>

                    <!-- Company Description -->
                    <div class="form-group full-width">
                        <label class="form-label">Company Description</label>
                        <textarea name="description" class="form-input" rows="6" 
                                  placeholder="Describe your company, its mission, values, and what makes it unique..."><?php echo htmlspecialchars($company['description'] ?? ''); ?></textarea>
                        <div class="help-text">Tell potential interns about your company culture and opportunities</div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="company_profile.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-hide success messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.querySelector('.message-success');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        successMessage.remove();
                    }, 300);
                }, 5000);
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const companyName = document.querySelector('input[name="company_name"]').value.trim();
            const industry = document.querySelector('input[name="industry"]').value.trim();

            if (!companyName || !industry) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }

            // Basic name validation
            if (companyName.length < 2) {
                e.preventDefault();
                alert('Company name must be at least 2 characters long.');
                return false;
            }

            // Website validation
            const website = document.querySelector('input[name="website"]').value;
            if (website && !website.match(/^https?:\/\/.+/)) {
                e.preventDefault();
                alert('Website must start with http:// or https://');
                return false;
            }
        });

        // File input preview
        document.querySelector('input[name="logo"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const currentLogo = document.querySelector('.current-logo');
                    if (currentLogo) {
                        currentLogo.querySelector('img').src = e.target.result;
                    } else {
                        // Create preview if no current logo exists
                        const preview = document.createElement('div');
                        preview.className = 'current-logo';
                        preview.innerHTML = `
                            <img src="${e.target.result}" alt="Logo Preview">
                            <div>
                                <strong>New Logo Preview</strong>
                                <div class="help-text">This will replace your current company logo</div>
                            </div>
                        `;
                        document.querySelector('input[name="logo"]').parentNode.insertBefore(preview, document.querySelector('.file-input-wrapper'));
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
