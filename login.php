<?php
session_start();
include "connection.php";

$error = "";
$role = isset($_POST['role']) ? $_POST['role'] : "";

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])){
    $email = $_POST['email'];
    $password = $_POST['password'];

    // تحديد الجدول والـ dashboard بناءً على الدور
    $table = "";
    $id_field = "";
    $dashboard = "";

    if($role === "Student"){
        $table = "students";
        $id_field = "student_id";
        $dashboard = "student_dashboard.php";
    } elseif($role === "Company"){
        $table = "companies";
        $id_field = "company_id";
        $dashboard = "company_dashboard.php";
    } elseif($role === "Instructor"){
        $table = "instructors";
        $id_field = "instructor_id";
        $dashboard = "instructor_dashboard.php";
    }

    if($table){
        $stmt = $conn->prepare("SELECT * FROM $table WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows === 1){
            $user = $result->fetch_assoc();
            if(password_verify($password, $user['password'])){
                
                // تحقق من حالة الموافقة فقط للشركات والمدرسين
                if($role === "Company" || $role === "Instructor"){
                    if($user['status'] === 'pending'){
                        $error = "Your account is still pending approval.";
                    } elseif($user['status'] === 'rejected'){
                        $error = "Your account has been rejected.";
                    } else {
                        $_SESSION[$id_field] = $user[$id_field];
                        header("Location: $dashboard");
                        exit;
                    }
                } else {
                    // الطلاب يتم تسجيل الدخول مباشرة
                    $_SESSION[$id_field] = $user[$id_field];
                    header("Location: $dashboard");
                    exit;
                }

            } else {
                $error = "Incorrect password!";
            }
        } else {
            // ✅ الرسالة الجديدة عندما لا يوجد البريد الإلكتروني في الجدول
            $error = "You don’t have an account. Please sign up.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Internship System</title>
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

/* Login card */
.login-card {
  background: var(--bg-glass);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: var(--radius-2xl);
  box-shadow: var(--shadow-2xl);
  overflow: hidden;
  display: flex;
  min-height: 600px;
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
}

/* Form wrapper */
.form-wrapper {
  width: 100%;
  max-width: 400px;
  margin: 0 auto;
}

/* Role selection */
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

/* Login form */
.login-form {
  display: none;
  animation: slideInUp 0.6s ease-out;
}

.login-form.active {
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

.form-input {
  width: 100%;
  padding: 1rem 1rem 1rem 3rem;
  border: 2px solid var(--border-light);
  border-radius: var(--radius-lg);
  font-size: 1rem;
  transition: var(--transition);
  background: var(--bg-primary);
  color: var(--text-dark);
}

.form-input:focus {
  outline: none;
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
}

.form-input::placeholder {
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
}

.btn-secondary:hover {
  background: var(--brand);
  color: var(--text-white);
  border-color: var(--brand);
}

.btn-back {
  background: transparent;
  color: var(--text-light);
  border: 2px solid var(--border-light);
  margin-top: 1rem;
}

.btn-back:hover {
  background: var(--bg-secondary);
  border-color: var(--text-light);
}

/* Messages */
.message {
  padding: 1rem;
  border-radius: var(--radius-lg);
  margin-bottom: 1.5rem;
  font-weight: 500;
  text-align: center;
  animation: slideInDown 0.4s ease;
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

.forgot-password {
  text-align: center;
  margin-top: 1rem;
  display: block;
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

/* Responsive Design */
@media (max-width: 768px) {
  .container {
    padding: 1rem;
  }
  
  .login-card {
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
  <div class="container">
    <div class="login-card">
      <!-- Brand Panel -->
      <div class="brand-panel">
        <div class="brand-logo">
          <i class="fas fa-graduation-cap"></i>
        </div>
        <h1 class="brand-title">Welcome Back</h1>
        <p class="brand-subtitle">login to your account and continue your internship journey</p>
      </div>

      <!-- Form Panel -->
      <div class="form-panel">
        <div class="form-wrapper">
          <!-- Role Selection -->
          <div class="role-selection" id="roleSelection" <?php if(!empty($error)) echo 'style="display:none;"'; ?>>
            <h2 class="role-title">Choose Your Role</h2>
            <p class="role-subtitle">Select how you'd like to access the platform</p>
            
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

          <!-- Login Form -->
          <form method="post" class="login-form" id="loginForm" style="display:<?php echo !empty($error) || !empty($role) ? 'block' : 'none'; ?>">
            <div class="form-header">
              <h2 class="form-title" id="formTitle"><?php echo !empty($role) ? htmlspecialchars($role) . " Login" : "Login"; ?></h2>
              <p class="form-subtitle">Enter your credentials to access your account</p>
            </div>

            <input type="hidden" name="role" id="roleInput" value="<?php echo htmlspecialchars($role); ?>">

            <?php if(!empty($error)): ?>
              <div class="message message-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
              </div>
            <?php endif; ?>

            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-input" placeholder="Enter your email" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
              <i class="fas fa-envelope input-icon"></i>
            </div>

            <div class="form-group">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
              <i class="fas fa-lock input-icon"></i>
            </div>

            <button type="submit" class="btn btn-primary">
              <i class="fas fa-sign-in-alt"></i>
              login
            </button>

            <a href="forgotpass.php" class="form-link forgot-password">
              <i class="fas fa-key"></i>
              Forgot your password?
            </a>

            <div class="form-footer">
              <p>Don't have an account? <a href="signup.php" class="form-link">Sign up here</a></p>
            </div>

            <button type="button" class="btn btn-back" onclick="goBack()">
              <i class="fas fa-arrow-left"></i>
              Back to Role Selection
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Role selection functionality
    const roleSelection = document.getElementById('roleSelection');
    const loginForm = document.getElementById('loginForm');
    const roleInput = document.getElementById('roleInput');
    const formTitle = document.getElementById('formTitle');

    // Add click handlers to role cards
    document.querySelectorAll('.role-card').forEach(card => {
      card.addEventListener('click', () => {
        const role = card.dataset.role;
        
        // Remove selected class from all cards
        document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
        
        // Add selected class to clicked card
        card.classList.add('selected');
        
        // Update form
        roleInput.value = role;
        formTitle.textContent = role + " Login";
        
        // Show login form with animation
        roleSelection.style.display = "none";
        loginForm.style.display = "block";
        loginForm.classList.add('active');
      });
    });

    // Back button functionality
    function goBack() {
      loginForm.style.display = "none";
      loginForm.classList.remove('active');
      roleSelection.style.display = "block";
      
      // Clear selected role
      document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
      roleInput.value = "";
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
    const form = document.getElementById('loginForm');
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
  </script>

</body>
</html>