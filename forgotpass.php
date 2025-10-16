<?php
session_start();
include 'connection.php';

// Set timezone
date_default_timezone_set('Asia/Amman');

// Load PHPMailer
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Generate random verification code
function generateVerificationCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Send verification email using PHPMailer
function sendVerificationEmail($email, $code) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'kabadakik4@gmail.com'; // <-- Replace with your Gmail
        $mail->Password = 'fxdm fnpw xcyj gjpq';    // <-- Replace with Gmail App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('kabadakik4@gmail.com', 'GIS System'); // same as above
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Verification Code - GIS';
        $mail->Body = "
        <html>
        <head><title>Password Reset Code</title></head>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Password Reset Verification Code</h2>
            <p>Your verification code for password reset is:</p>
            <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0;'>{$code}</div>
            <p>This code will expire in 10 minutes.</p>
            <p>If you didn't request this, please ignore this email.</p>
        </body>
        </html>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Initialize messages
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['email']) && !empty($_POST['user_type'])) {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $user_type = $_POST['user_type'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Determine table and ID column
            $table = '';
            $id_field = '';
            switch ($user_type) {
                case 'student':
                    $table = 'students';
                    $id_field = 'student_id';
                    break;
                case 'company':
                    $table = 'companies';
                    $id_field = 'company_id';
                    break;
                case 'instructor':
                    $table = 'instructors';
                    $id_field = 'instructor_id';
                    break;
                default:
                    $error_message = "Invalid user type.";
                    break;
            }

            if ($table && $id_field) {
                $check_sql = "SELECT $id_field FROM $table WHERE email = ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    $verification_code = generateVerificationCode();

                    $current_time = new DateTime();
                    $expiry_time = $current_time->modify('+10 minutes');
                    $expiry = $expiry_time->format('Y-m-d H:i:s');

                    $update_sql = "UPDATE $table SET reset_code = ?, reset_code_expiry = ? WHERE email = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param("sss", $verification_code, $expiry, $email);

                    if ($stmt->execute()) {
                        if (sendVerificationEmail($email, $verification_code)) {
                            $_SESSION['reset_email'] = $email;
                            $_SESSION['user_type'] = $user_type;
                            header("Location: verify_code.php");
                            exit();
                        } else {
                            $error_message = "Error sending verification code. Please try again.";
                        }
                    } else {
                        $error_message = "Error updating verification code. Please try again.";
                    }
                } else {
                    $error_message = "Email not found in our records.";
                }
            }
        }
    } else {
        $error_message = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password - TheraSpace</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%); margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh;}
        .container {background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); width: 100%; max-width: 400px;}
        h2 {color: rgb(56,123,189); text-align: center; margin-bottom: 30px;}
        .form-group {margin-bottom: 20px;}
        label {display:block; margin-bottom:8px; color:#4a5568;}
        input[type="email"], select {width:100%; padding:12px; border:1px solid #e2e8f0; border-radius:8px; font-size:16px; transition: all 0.3s;}
        input[type="email"]:focus, select:focus {outline:none; border-color: rgb(56,123,189); box-shadow:0 0 0 3px rgba(56,123,189,0.1);}
        .submit-btn {background: linear-gradient(135deg, rgb(46,121,187), rgb(56,123,189)); color:white; border:none; padding:12px 20px; border-radius:8px; cursor:pointer; width:100%; font-size:16px; font-weight:500; transition: all 0.3s;}
        .submit-btn:hover {transform:translateY(-2px); box-shadow:0 4px 12px rgba(56,123,189,0.2);}
        .error-message {background:#FEE2E2; color:#991B1B; padding:12px; border-radius:8px; margin-bottom:20px; text-align:center;}
        .back-btn {display:inline-flex; align-items:center; gap:0.5rem; padding:0.75rem 1.5rem; background-color:#f1f3f5; color:#495057; text-decoration:none; border-radius:8px; transition: all 0.3s ease; margin-bottom:2rem;}
        .back-btn:hover {background-color:#e9ecef; transform:translateX(-5px);}
    </style>
</head>
<body>
    <div class="container">
        <a href="login.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Login</a>
        <h2>Forgot Password</h2>
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="user_type">I am a:</label>
                <select id="user_type" name="user_type" required>
                    <option value="">Select user type</option>
                    <option value="student">Student</option>
                    <option value="company">Company</option>
                    <option value="instructor">Instructor</option>
                </select>
            </div>
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="submit-btn"><i class="fas fa-paper-plane"></i> Send Reset Code</button>
        </form>
    </div>
</body>
</html>
