<?php
include 'connection.php';

// Upload File Function
function uploadFile($file, $folder, $allowedTypes = ['jpg','jpeg','png','pdf']) {
    if(isset($file) && $file['error'] == 0){
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if(!in_array($ext, $allowedTypes)) return null;
        if(!is_dir($folder)) mkdir($folder, 0777, true);
        $filename = time() . "_" . basename($file['name']);
        $target = $folder . "/" . $filename;
        if(move_uploaded_file($file['tmp_name'], $target)){
            return $target;
        }
    }
    return null;
}

$success = "";
$error = "";

if($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get email first for verification
    $email = isset($_POST['email']) ? $conn->real_escape_string($_POST['email']) : '';

    // ✅ Validate email format
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = "Please enter a valid email address.";
    } else {
        // Check if email already exists in database
        $check_email_sql = "SELECT COUNT(*) as count FROM (
            SELECT email FROM students WHERE email = ?
            UNION ALL
            SELECT email FROM companies WHERE email = ?
            UNION ALL
            SELECT email FROM instructors WHERE email = ?
        ) as all_users";
        
        $check_stmt = $conn->prepare($check_email_sql);
        $check_stmt->bind_param("sss", $email, $email, $email);
        $check_stmt->execute();
        $email_check = $check_stmt->get_result()->fetch_assoc();
        
        if ($email_check['count'] > 0) {
            $error = "This email address is already registered. Please use a different email or try logging in.";
        }
    }

    // Proceed only if no errors
    if(empty($error)) {

        // --- STUDENT INSERT ---
        if(isset($_POST['first_name']) && isset($_POST['last_name']) && isset($_POST['university']) && !isset($_POST['company_name']) && !isset($_POST['bio'])) {
            $first_name = $conn->real_escape_string($_POST['first_name']);
            $last_name = $conn->real_escape_string($_POST['last_name']);
            $full_name = $first_name . ' ' . $last_name; // For session storage
            $university = $conn->real_escape_string($_POST['university']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $department = $conn->real_escape_string($_POST['department']);
            $phone = $conn->real_escape_string($_POST['phone']);
            $cv_path = uploadFile($_FILES['cv'], "uploads/students/cv");

            // Use prepared statement for better security and reliability
            $sql = "INSERT INTO students (first_name, last_name, university, email, password, department, phone, cv_path, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $conn->prepare($sql);
            if($stmt) {
                $stmt->bind_param("ssssssss", $first_name, $last_name, $university, $email, $password, $department, $phone, $cv_path);
                
                if($stmt->execute()) {
                    session_start();
                    $_SESSION['student_id'] = $conn->insert_id;
                    $_SESSION['student_name'] = $full_name;
                    $stmt->close();
                    header("Location: student_dashboard.php");
                    exit();
                } else {
                    $error = "Error creating student account: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Error preparing statement: " . $conn->error;
            }
        }

        // --- COMPANY INSERT ---
        elseif(isset($_POST['company_name'])) {
            $company = $conn->real_escape_string($_POST['company_name']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $phone = $conn->real_escape_string($_POST['phone']);
            $industry = $conn->real_escape_string($_POST['industry']);
            $website = $conn->real_escape_string($_POST['website']);
            $logo = uploadFile($_FILES['logo'], "uploads/companies/logo");
            $status = 'pending';

            // Start transaction
            $conn->begin_transaction();

            try {
                // Insert company (without address fields since we're using locations now)
                $sql = "INSERT INTO companies (company_name, email, password, phone, industry, website, logo_path, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                $stmt = $conn->prepare($sql);
                if(!$stmt) {
                    throw new Exception("Error preparing company statement: " . $conn->error);
                }
                
                $stmt->bind_param("ssssssss", $company, $email, $password, $phone, $industry, $website, $logo, $status);
                
                if(!$stmt->execute()) {
                    throw new Exception("Error creating company: " . $stmt->error);
                }
                
                $stmt->close();

                $company_id = $conn->insert_id;

                // Insert primary location
                $location_name = $conn->real_escape_string($_POST['location_name']);
                $location_type = $conn->real_escape_string($_POST['location_type']);
                $address = $conn->real_escape_string($_POST['address']);
                $city = $conn->real_escape_string($_POST['city']);
                $country = $conn->real_escape_string($_POST['country']);
                $postal_code = $conn->real_escape_string($_POST['postal_code']);
                $location_phone = $conn->real_escape_string($_POST['location_phone']);
                $location_email = $conn->real_escape_string($_POST['location_email']);

                $location_sql = "INSERT INTO company_locations (company_id, location_name, location_type, address, city, country, postal_code, phone, email, is_primary, status, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, 'active', NOW())";

                $location_stmt = $conn->prepare($location_sql);
                if(!$location_stmt) {
                    throw new Exception("Error preparing location statement: " . $conn->error);
                }
                
                $location_stmt->bind_param("issssssss", $company_id, $location_name, $location_type, $address, $city, $country, $postal_code, $location_phone, $location_email);
                
                if(!$location_stmt->execute()) {
                    throw new Exception("Error creating primary location: " . $location_stmt->error);
                }
                
                $location_stmt->close();

                // Commit transaction
                $conn->commit();
                $success = "Your company account has been created successfully!<br>Please note your account is pending admin approval.";
                
            } catch (Exception $e) {
                // Rollback transaction
                $conn->rollback();
                $error = "Error: " . $e->getMessage();
            }
        }

        // --- INSTRUCTOR INSERT ---
        elseif(isset($_POST['first_name']) && isset($_POST['last_name']) && isset($_POST['bio'])){
            $first_name = $conn->real_escape_string($_POST['first_name']);
            $last_name = $conn->real_escape_string($_POST['last_name']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $phone = $conn->real_escape_string($_POST['phone']);
            $department = $conn->real_escape_string($_POST['department']);
            $university = $conn->real_escape_string($_POST['university_name']);
            $bio = $conn->real_escape_string($_POST['bio']);

            $photo = isset($_FILES['photo']) ? uploadFile($_FILES['photo'], "uploads/instructors/photo") : null;
            $cv = isset($_FILES['upload_cv']) ? uploadFile($_FILES['upload_cv'], "uploads/instructors/cv") : null;

            $sql = "INSERT INTO instructors 
                    (first_name, last_name, email, password, phone, department, university_name, photo_path, upload_cv, bio, status, created_at) 
                    VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

            $stmt = $conn->prepare($sql);
            if($stmt) {
                $stmt->bind_param("ssssssssss", $first_name, $last_name, $email, $password, $phone, $department, $university, $photo, $cv, $bio);
                
                if($stmt->execute()) {
                    $success = "Your instructor account has been created successfully!<br>Please note your account is pending admin approval.";
                } else {
                    $error = "Error creating instructor account: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Error preparing instructor statement: " . $conn->error;
            }
        }

    } // end if no error

} // end POST

// Don't close connection here as we need it for the HTML output
// $conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign Up — Internship System</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
  --bg-gradient: linear-gradient(135deg, #0ea5a8 0%, #22d3ee 100%);
  --bg-glass: rgba(255, 255, 255, 0.25);
  --border-light: #e5e7eb;
  --border-focus: #0ea5a8;
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  --radius-sm: 0.375rem;
  --radius-md: 0.5rem;
  --radius-lg: 0.75rem;
  --radius-xl: 1rem;
  --radius-2xl: 1.5rem;
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-fast: all 0.15s ease;
}

/* Links */
.form-link {
  color: var(--brand);
  text-decoration: none;
  font-weight: 500;
  transition: var(--transition-fast);
}

.form-link:hover {
  color: var(--brand-2);
  text-decoration: underline;
}

/* Form footer */
.form-footer {
  text-align: center;
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-light);
}

.form-footer p {
  color: var(--text-light);
  font-size: 0.9rem;
  margin: 0;
}

/* Reset */
* { 
  box-sizing: border-box; 
  margin: 0; 
  padding: 0; 
}

body {
  font-family: "Inter", sans-serif;
  min-height: 100vh;
  background: var(--bg-gradient);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  position: relative;
  overflow-x: hidden;
}

/* Animated background elements */
body::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: 
    radial-gradient(circle at 20% 80%, rgba(14, 165, 168, 0.1) 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, rgba(34, 211, 238, 0.1) 0%, transparent 50%),
    radial-gradient(circle at 40% 40%, rgba(11, 31, 58, 0.05) 0%, transparent 50%);
  animation: float 20s ease-in-out infinite;
  z-index: -1;
}

@keyframes float {
  0%, 100% { transform: translate(0, 0) rotate(0deg); }
  33% { transform: translate(30px, -30px) rotate(120deg); }
  66% { transform: translate(-20px, 20px) rotate(240deg); }
}

/* Main container */
.container {
  width: 100%;
  max-width: 1200px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2rem;
}

/* Signup card */
.signup-card {
  background: var(--bg-glass);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: var(--radius-2xl);
  box-shadow: var(--shadow-2xl);
  overflow: hidden;
  display: flex;
  min-height: 700px;
  width: 100%;
  max-width: 1000px;
  position: relative;
}

/* Left panel - Branding */
.brand-panel {
  flex: 1;
  background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
  padding: 3rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  color: var(--text-white);
  position: relative;
  overflow: hidden;
}

.brand-panel::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
  animation: pulse 4s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); opacity: 0.5; }
  50% { transform: scale(1.1); opacity: 0.8; }
}

.brand-logo {
  font-size: 3rem;
  margin-bottom: 1rem;
  animation: bounce 2s infinite;
}

@keyframes bounce {
  0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
  40% { transform: translateY(-10px); }
  60% { transform: translateY(-5px); }
}

.brand-title {
  font-size: 2.5rem;
  font-weight: 800;
  margin-bottom: 1rem;
  text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.brand-subtitle {
  font-size: 1.1rem;
  opacity: 0.9;
  line-height: 1.6;
  max-width: 300px;
}

/* Right panel - Form */
.form-panel {
  flex: 1;
  padding: 3rem;
  background: var(--bg-primary);
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
  overflow-y: auto;
}

.form-wrapper {
  width: 100%;
  max-width: 400px;
  margin: 0 auto;
}

/* Role Selection */
.role-selection {
  text-align: center;
  animation: slideInUp 0.6s ease-out;
}

@keyframes slideInUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

.role-title {
  font-size: 1.8rem;
  font-weight: 700;
  color: var(--text-dark);
  margin-bottom: 0.5rem;
}

.role-subtitle {
  color: var(--text-light);
  margin-bottom: 2rem;
  font-size: 0.95rem;
}

.role-grid {
  display: grid;
  gap: 1rem;
  margin-bottom: 2rem;
}

.role-card {
  background: var(--bg-secondary);
  border: 2px solid var(--border-light);
  border-radius: var(--radius-xl);
  padding: 1.5rem;
  cursor: pointer;
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.role-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
  transition: left 0.5s;
}

.role-card:hover::before {
  left: 100%;
}

.role-card:hover {
  border-color: var(--brand);
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

.role-card.selected {
  border-color: var(--brand);
  background: linear-gradient(135deg, var(--brand), var(--brand-2));
  color: var(--text-white);
}

.role-icon {
  font-size: 2rem;
  margin-bottom: 0.5rem;
  display: block;
}

.role-name {
  font-weight: 600;
  font-size: 1.1rem;
  margin-bottom: 0.25rem;
}

.role-description {
  font-size: 0.85rem;
  opacity: 0.8;
}

/* Forms */
.signup-form {
  display: none;
  animation: slideInUp 0.6s ease-out;
}

.signup-form.active {
  display: block;
}

.form-header {
  text-align: center;
  margin-bottom: 2rem;
}

.form-title {
  font-size: 1.8rem;
  font-weight: 700;
  color: var(--text-dark);
  margin-bottom: 0.5rem;
}

.form-subtitle {
  color: var(--text-light);
  font-size: 0.95rem;
}

/* Form fields */
.form-group {
  margin-bottom: 1.5rem;
  position: relative;
}

.form-label {
  display: block;
  font-weight: 500;
  color: var(--text-dark);
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.form-input, .form-textarea {
  width: 100%;
  padding: 1rem 1rem 1rem 3rem;
  border: 2px solid var(--border-light);
  border-radius: var(--radius-lg);
  font-size: 1rem;
  transition: var(--transition);
  background: var(--bg-primary);
  color: var(--text-dark);
}

.form-textarea {
  padding: 1rem;
  resize: none;
  line-height: 1.5;
  min-height: 100px;
}

.form-input:focus, .form-textarea:focus {
  outline: none;
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
}

.form-input::placeholder, .form-textarea::placeholder {
  color: var(--text-light);
}

.input-icon {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-light);
  font-size: 1.1rem;
  pointer-events: none;
}

.form-input:focus + .input-icon {
  color: var(--brand);
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
  gap: 0.5rem;
  padding: 1rem;
  border: 2px dashed var(--border-light);
  border-radius: var(--radius-lg);
  background: var(--bg-secondary);
  cursor: pointer;
  transition: var(--transition);
  color: var(--text-light);
}

.file-input-label:hover {
  border-color: var(--brand);
  background: rgba(14, 165, 168, 0.05);
}

/* Buttons */
.btn {
  width: 100%;
  padding: 1rem 1.5rem;
  border: none;
  border-radius: var(--radius-lg);
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  transition: var(--transition);
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
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

.btn-primary:active {
  transform: translateY(0);
}

.btn-secondary {
  background: var(--bg-secondary);
  color: var(--text-dark);
  border: 2px solid var(--border-light);
  margin-top: 1rem;
}

.btn-secondary:hover {
  background: var(--brand);
  color: var(--text-white);
  border-color: var(--brand);
}

/* Location Section Styles */
.location-section {
  background: #f8fafc;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
  margin: 1.5rem 0;
}

.section-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.section-title::before {
  content: "📍";
  font-size: 1.2rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }
}
/* Messages */
.message {
  padding: 1rem;
  border-radius: var(--radius-lg);
  margin-bottom: 1.5rem;
  font-weight: 500;
  text-align: center;
  animation: slideInDown 0.4s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

@keyframes slideInDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

.message-error {
  background: rgba(248, 113, 113, 0.1);
  border: 1px solid rgba(248, 113, 113, 0.3);
  color: #dc2626;
}

.message-success {
  background: rgba(74, 222, 128, 0.1);
  border: 1px solid rgba(74, 222, 128, 0.3);
  color: #059669;
}

.message-pending {
  background: rgba(251, 191, 36, 0.1);
  border: 1px solid rgba(251, 191, 36, 0.3);
  color: #d97706;
}

/* Toast notifications */
.toast {
  position: fixed;
  top: 2rem;
  right: 2rem;
  padding: 1rem 1.5rem;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-xl);
  color: var(--text-white);
  font-weight: 500;
  z-index: 1000;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  animation: slideInRight 0.4s ease;
  max-width: 400px;
}

@keyframes slideInRight {
  from { opacity: 0; transform: translateX(100%); }
  to { opacity: 1; transform: translateX(0); }
}

.toast-success {
  background: linear-gradient(135deg, var(--success), #10b981);
}

.toast-pending {
  background: linear-gradient(135deg, var(--warning), #f59e0b);
}

.toast-error {
  background: linear-gradient(135deg, var(--error), #ef4444);
}

/* Responsive Design */
@media (max-width: 768px) {
  .container {
    padding: 1rem;
  }
  
  .signup-card {
    flex-direction: column;
    min-height: auto;
  }
  
  .brand-panel {
    padding: 2rem 1.5rem;
    min-height: 200px;
  }
  
  .brand-title {
    font-size: 2rem;
  }
  
  .brand-logo {
    font-size: 2.5rem;
  }
  
  .form-panel {
    padding: 2rem 1.5rem;
  }
  
  .role-grid {
    grid-template-columns: 1fr;
  }
  
  .role-card {
    padding: 1rem;
  }
  
  .toast {
    right: 1rem;
    left: 1rem;
    max-width: none;
  }
}

@media (max-width: 480px) {
  .brand-panel {
    padding: 1.5rem 1rem;
  }
  
  .form-panel {
    padding: 1.5rem 1rem;
  }
  
  .brand-title {
    font-size: 1.8rem;
  }
  
  .form-title {
    font-size: 1.5rem;
  }
}

</style>
</head>
<body>
  <!-- Toast notifications -->
<?php if($success || $error): ?>
    <div class="toast <?php echo $success ? ((strpos($success,'pending')!==false)?'toast-pending':'toast-success') : 'toast-error'; ?>">
      <i class="fas <?php echo $success ? (strpos($success,'pending')!==false ? 'fa-clock' : 'fa-check-circle') : 'fa-exclamation-circle'; ?>"></i>
      <span><?php echo $success ?? $error; ?></span>
</div>
<script>
  <?php if($success && strpos($success,'pending')===false): ?>
        setTimeout(()=>{ window.location.href="login.php"; }, 3000);
  <?php endif; ?>
</script>
<?php endif; ?>

  <div class="container">
    <div class="signup-card">
      <!-- Brand Panel -->
      <div class="brand-panel">
        <div class="brand-logo">
          <i class="fas fa-user-plus"></i>
        </div>
        <h1 class="brand-title">Join Us Today</h1>
        <p class="brand-subtitle">Create your account and start your internship journey with us</p>
  </div>

      <!-- Form Panel -->
      <div class="form-panel">
        <div class="form-wrapper">
      <!-- Role Selection -->
      <div class="role-selection" id="roleSelection">
            <h2 class="role-title">Choose Your Role</h2>
            <p class="role-subtitle">Select how you'd like to join our platform</p>
            
            <div class="role-grid">
              <div class="role-card" data-role="Student">
                <span class="role-icon">🎓</span>
                <div class="role-name">Student</div>
                <div class="role-description">Find internships and build your career</div>
              </div>
              
              <div class="role-card" data-role="Company">
                <span class="role-icon">🏢</span>
                <div class="role-name">Company</div>
                <div class="role-description">Post opportunities and hire talent</div>
              </div>
              
              <div class="role-card" data-role="Instructor">
                <span class="role-icon">👨‍🏫</span>
                <div class="role-name">Instructor</div>
                <div class="role-description">Guide students and manage programs</div>
              </div>
            </div>
          </div>

          <!-- Student Form -->
          <form class="signup-form" id="studentForm" action="signup.php" method="POST" enctype="multipart/form-data">
            <div class="form-header">
              <h2 class="form-title">Student Registration</h2>
              <p class="form-subtitle">Create your student account to start applying for internships</p>
            </div>

            <div class="form-group">
              <label class="form-label">First Name</label>
              <input type="text" name="first_name" class="form-input" placeholder="Enter your first name" required>
              <i class="fas fa-user input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Last Name</label>
              <input type="text" name="last_name" class="form-input" placeholder="Enter your last name" required>
              <i class="fas fa-user input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">University</label>
              <input type="text" name="university" class="form-input" placeholder="Enter your university name" required>
              <i class="fas fa-university input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-input" placeholder="Enter your email" required>
              <i class="fas fa-envelope input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-input" placeholder="Create a password" required>
              <i class="fas fa-lock input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Department</label>
              <input type="text" name="department" class="form-input" placeholder="Enter your department" required>
              <i class="fas fa-graduation-cap input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Phone Number (Optional)</label>
              <input type="text" name="phone" class="form-input" placeholder="Enter your phone number">
              <i class="fas fa-phone input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Upload CV (PDF)</label>
              <div class="file-input-wrapper">
                <input type="file" name="cv" class="file-input" accept=".pdf" required>
                <label class="file-input-label">
                  <i class="fas fa-file-pdf"></i>
                  <span>Choose PDF file or drag and drop</span>
                </label>
              </div>
            </div>

            <button type="submit" class="btn btn-primary">
              <i class="fas fa-user-plus"></i>
              Create Student Account
            </button>

            <button type="button" class="btn btn-secondary" onclick="goBack()">
              <i class="fas fa-arrow-left"></i>
              Back to Role Selection
            </button>

            <div class="form-footer">
              <p>Already have an account? <a href="login.php" class="form-link">Sign in here</a></p>
            </div>
          </form>

          <!-- Company Form -->
          <form class="signup-form" id="companyForm" action="signup.php" method="POST" enctype="multipart/form-data">
            <div class="form-header">
              <h2 class="form-title">Company Registration</h2>
              <p class="form-subtitle">Create your company account to post internships and hire talent</p>
            </div>

            <div class="form-group">
              <label class="form-label">Company Name</label>
              <input type="text" name="company_name" class="form-input" placeholder="Enter company name" required>
              <i class="fas fa-building input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-input" placeholder="Enter company email" required>
              <i class="fas fa-envelope input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-input" placeholder="Create a password" required>
              <i class="fas fa-lock input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Industry</label>
              <input type="text" name="industry" class="form-input" placeholder="Enter your industry" required>
              <i class="fas fa-industry input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Phone Number (Optional)</label>
              <input type="text" name="phone" class="form-input" placeholder="Enter company phone">
              <i class="fas fa-phone input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Website (Optional)</label>
              <input type="url" name="website" class="form-input" placeholder="Enter company website">
              <i class="fas fa-globe input-icon"></i>
            </div>

            <!-- Primary Location Section -->
            <div class="location-section">
              <h3 class="section-title">Primary Location (Required)</h3>
              
              <div class="form-group">
                <label class="form-label">Location Name</label>
                <input type="text" name="location_name" class="form-input" placeholder="e.g., Main Office, Headquarters" required>
                <i class="fas fa-building input-icon"></i>
              </div>

              <div class="form-group">
                <label class="form-label">Location Type</label>
                <select name="location_type" class="form-input" required>
                  <option value="">Select location type</option>
                  <option value="head_office">Head Office</option>
                  <option value="branch">Branch</option>
                  <option value="training_center">Training Center</option>
                </select>
                <i class="fas fa-map-marker-alt input-icon"></i>
              </div>

              <div class="form-group">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-input" placeholder="Enter complete address" rows="3" required></textarea>
                <i class="fas fa-map-marker-alt input-icon"></i>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">City</label>
                  <input type="text" name="city" class="form-input" placeholder="Enter city" required>
                  <i class="fas fa-city input-icon"></i>
                </div>

                <div class="form-group">
                  <label class="form-label">Country</label>
                  <input type="text" name="country" class="form-input" placeholder="Enter country" required>
                  <i class="fas fa-flag input-icon"></i>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Postal Code (Optional)</label>
                  <input type="text" name="postal_code" class="form-input" placeholder="Enter postal code">
                  <i class="fas fa-mail-bulk input-icon"></i>
                </div>

                <div class="form-group">
                  <label class="form-label">Location Phone (Optional)</label>
                  <input type="text" name="location_phone" class="form-input" placeholder="Enter location phone">
                  <i class="fas fa-phone input-icon"></i>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Location Email (Optional)</label>
                <input type="email" name="location_email" class="form-input" placeholder="Enter location email">
                <i class="fas fa-envelope input-icon"></i>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Upload Logo (Optional)</label>
              <div class="file-input-wrapper">
                <input type="file" name="logo" class="file-input" accept="image/*">
                <label class="file-input-label">
                  <i class="fas fa-image"></i>
                  <span>Choose image file or drag and drop</span>
                </label>
              </div>
            </div>

            <button type="submit" class="btn btn-primary">
              <i class="fas fa-building"></i>
              Create Company Account
            </button>

            <button type="button" class="btn btn-secondary" onclick="goBack()">
              <i class="fas fa-arrow-left"></i>
              Back to Role Selection
            </button>

            <div class="form-footer">
              <p>Already have an account? <a href="login.php" class="form-link">Sign in here</a></p>
            </div>
          </form>

          <!-- Instructor Form -->
          <form class="signup-form" id="instructorForm" action="signup.php" method="POST" enctype="multipart/form-data">
            <div class="form-header">
              <h2 class="form-title">Instructor Registration</h2>
              <p class="form-subtitle">Create your instructor account to guide students and manage programs</p>
            </div>

            <div class="form-group">
              <label class="form-label">First Name</label>
              <input type="text" name="first_name" class="form-input" placeholder="Enter your first name" required>
              <i class="fas fa-user input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Last Name</label>
              <input type="text" name="last_name" class="form-input" placeholder="Enter your last name" required>
              <i class="fas fa-user input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-input" placeholder="Enter your email" required>
              <i class="fas fa-envelope input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-input" placeholder="Create a password" required>
              <i class="fas fa-lock input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Phone Number (Optional)</label>
              <input type="text" name="phone" class="form-input" placeholder="Enter your phone number">
              <i class="fas fa-phone input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Department</label>
              <input type="text" name="department" class="form-input" placeholder="Enter your department">
              <i class="fas fa-graduation-cap input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">University Name</label>
              <input type="text" name="university_name" class="form-input" placeholder="Enter university name">
              <i class="fas fa-university input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Upload Photo (Optional)</label>
              <div class="file-input-wrapper">
                <input type="file" name="photo" class="file-input" accept="image/*">
                <label class="file-input-label">
                  <i class="fas fa-camera"></i>
                  <span>Choose image file or drag and drop</span>
                </label>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Upload CV (Optional)</label>
              <div class="file-input-wrapper">
                <input type="file" name="upload_cv" class="file-input" accept=".pdf,.doc,.docx">
                <label class="file-input-label">
                  <i class="fas fa-file-alt"></i>
                  <span>Choose document file or drag and drop</span>
                </label>
        </div>
      </div>

            <div class="form-group">
              <label class="form-label">Bio (Optional)</label>
              <textarea name="bio" class="form-textarea" placeholder="Tell us about yourself and your experience" rows="3"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
              <i class="fas fa-chalkboard-teacher"></i>
              Create Instructor Account
            </button>

            <button type="button" class="btn btn-secondary" onclick="goBack()">
              <i class="fas fa-arrow-left"></i>
              Back to Role Selection
            </button>

            <div class="form-footer">
              <p>Already have an account? <a href="login.php" class="form-link">Sign in here</a></p>
            </div>
          </form>
               </div>
    </div>
  </div>
</div>

<script>
    // Role selection functionality
const roleSelection = document.getElementById('roleSelection');
const studentForm = document.getElementById('studentForm');
const companyForm = document.getElementById('companyForm');
const instructorForm = document.getElementById('instructorForm');

    // Add click handlers to role cards
    document.querySelectorAll('.role-card').forEach(card => {
      card.addEventListener('click', () => {
        const role = card.dataset.role;
        
        // Remove selected class from all cards
        document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
        
        // Add selected class to clicked card
        card.classList.add('selected');
        
        // Hide role selection and show appropriate form
        roleSelection.style.display = "none";
        
        // Hide all forms first
        studentForm.style.display = "none";
        companyForm.style.display = "none";
        instructorForm.style.display = "none";
        
        // Show the selected form
        if (role === "Student") {
          studentForm.style.display = "block";
          studentForm.classList.add('active');
        } else if (role === "Company") {
          companyForm.style.display = "block";
          companyForm.classList.add('active');
        } else if (role === "Instructor") {
          instructorForm.style.display = "block";
          instructorForm.classList.add('active');
        }
      });
    });

    // Back button functionality
function goBack() {
      // Hide all forms
      studentForm.style.display = "none";
      companyForm.style.display = "none";
      instructorForm.style.display = "none";
      
      // Remove active classes
      studentForm.classList.remove('active');
      companyForm.classList.remove('active');
      instructorForm.classList.remove('active');
      
      // Show role selection
      roleSelection.style.display = "block";
      
      // Clear selected role
      document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
    }

    // Add smooth transitions
    document.addEventListener('DOMContentLoaded', function() {
      // Add entrance animation to role cards
      const roleCards = document.querySelectorAll('.role-card');
      roleCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.style.animation = 'slideInUp 0.6s ease-out forwards';
      });
    });

    // Form validation enhancement
    const forms = document.querySelectorAll('.signup-form');
    forms.forEach(form => {
      const inputs = form.querySelectorAll('.form-input');
      
      inputs.forEach(input => {
        input.addEventListener('focus', function() {
          this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
          if (!this.value) {
            this.parentElement.classList.remove('focused');
          }
        });
      });
    });

    // File input enhancement
    document.querySelectorAll('.file-input').forEach(input => {
      input.addEventListener('change', function() {
        const label = this.nextElementSibling;
        const fileName = this.files[0]?.name || 'No file chosen';
        label.querySelector('span').textContent = fileName;
      });
    });
</script>

</body>
</html>

<?php
// Close connection at the end
$conn->close();
?>