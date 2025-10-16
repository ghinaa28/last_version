<?php
session_start();
include 'connection.php';

// Redirect if no email in session
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['user_type'])) {
    header("Location: forgotpass.php");
    exit();
}

$email = $_SESSION['reset_email'];
$user_type = $_SESSION['user_type'];
$error_message = '';
$success_message = '';

// Determine table and ID column
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = trim($_POST['verification_code']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($code) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        // Check code and expiry
        $stmt = $conn->prepare("SELECT reset_code, reset_code_expiry FROM $table WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $current_time = new DateTime();
            $expiry_time = new DateTime($row['reset_code_expiry']);

            if ($row['reset_code'] === $code && $current_time <= $expiry_time) {
                // Update password and clear reset code
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE $table SET password = ?, reset_code = NULL, reset_code_expiry = NULL WHERE email = ?");
                $update->bind_param("ss", $hashed_password, $email);

                if ($update->execute()) {
                    $success_message = "Password successfully reset! You can now <a href='login.php'>login</a>.";
                    // Clear session
                    unset($_SESSION['reset_email'], $_SESSION['user_type']);
                } else {
                    $error_message = "Failed to reset password. Please try again.";
                }
            } else {
                $error_message = "Invalid or expired verification code.";
            }
        } else {
            $error_message = "User not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify Code - TheraSpace</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%); margin:0; padding:20px; display:flex; justify-content:center; align-items:center; min-height:100vh;}
        .container {background:white; padding:40px; border-radius:15px; box-shadow:0 10px 20px rgba(0,0,0,0.1); width:100%; max-width:400px;}
        h2 {color: rgb(56,123,189); text-align:center; margin-bottom:30px;}
        .form-group {margin-bottom:20px;}
        label {display:block; margin-bottom:8px; color:#4a5568;}
        input[type="text"], input[type="password"] {width:100%; padding:12px; border:1px solid #e2e8f0; border-radius:8px; font-size:16px; transition: all 0.3s;}
        input:focus {outline:none; border-color: rgb(56,123,189); box-shadow:0 0 0 3px rgba(56,123,189,0.1);}
        .submit-btn {background: linear-gradient(135deg, rgb(46,121,187), rgb(56,123,189)); color:white; border:none; padding:12px 20px; border-radius:8px; cursor:pointer; width:100%; font-size:16px; font-weight:500; transition: all 0.3s;}
        .submit-btn:hover {transform:translateY(-2px); box-shadow:0 4px 12px rgba(56,123,189,0.2);}
        .error-message {background:#FEE2E2; color:#991B1B; padding:12px; border-radius:8px; margin-bottom:20px; text-align:center;}
        .success-message {background:#D1FAE5; color:#065F46; padding:12px; border-radius:8px; margin-bottom:20px; text-align:center;}
        .back-btn {display:inline-flex; align-items:center; gap:0.5rem; padding:0.75rem 1.5rem; background-color:#f1f3f5; color:#495057; text-decoration:none; border-radius:8px; transition: all 0.3s ease; margin-bottom:2rem;}
        .back-btn:hover {background-color:#e9ecef; transform:translateX(-5px);}
    </style>
</head>
<body>
    <div class="container">
        <a href="forgotpass.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Forgot Password</a>
        <h2>Verify Code & Reset Password</h2>

        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php else: ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="verification_code">Verification Code:</label>
                <input type="text" id="verification_code" name="verification_code" maxlength="6" required>
            </div>
            <div class="form-group">
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="submit-btn"><i class="fas fa-key"></i> Reset Password</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
