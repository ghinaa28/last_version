<?php
session_start();
include 'connection.php';

// ✅ Ensure user came from verified step
if (
    !isset($_SESSION['verified_code']) ||
    !isset($_SESSION['reset_email']) ||
    !isset($_SESSION['user_type'])
) {
    header("Location: forgotpass.php");
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['password']) && !empty($_POST['confirm_password'])) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $email = $_SESSION['reset_email'];
        $user_type = $_SESSION['user_type'];

        // ✅ Password strength validation
        $errors = [];
        if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters long.";
        if (!preg_match('/[A-Z]/', $password)) $errors[] = "Password must contain at least one uppercase letter.";
        if (!preg_match('/[a-z]/', $password)) $errors[] = "Password must contain at least one lowercase letter.";
        if (!preg_match('/[0-9]/', $password)) $errors[] = "Password must contain at least one number.";
        if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = "Password must contain at least one special character.";
        if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

        if (empty($errors)) {
            // ✅ Determine table name
            switch (strtolower($user_type)) {
                case 'student':
                    $table = 'students';
                    $redirect = 'login.php';
                    break;
                case 'company':
                    $table = 'companies';
                    $redirect = 'login.php';
                    break;
                case 'instructor':
                    $table = 'instructor';
                    $redirect = 'login.php';
                    break;
                default:
                    $error_message = "Invalid user type.";
                    $table = '';
                    break;
            }

            if ($table) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $update_sql = "UPDATE $table SET password = ? WHERE email = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("ss", $hashed_password, $email);

                if ($stmt->execute()) {
                    // ✅ Clear sensitive session data
                    unset($_SESSION['verified_code'], $_SESSION['reset_email'], $_SESSION['user_type']);

                    $success_message = "Password has been reset successfully. Redirecting to login...";
                    header("refresh:3;url=$redirect");
                } else {
                    $error_message = "Error resetting password. Please try again.";
                }
                $stmt->close();
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    } else {
        $error_message = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - TheraSpace</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        h2 {
            color: rgb(56, 123, 189);
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
        }
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        input[type="password"]:focus {
            outline: none;
            border-color: rgb(56, 123, 189);
            box-shadow: 0 0 0 3px rgba(56, 123, 189, 0.1);
        }
        .submit-btn {
            background: linear-gradient(135deg, rgb(46, 121, 187), rgb(56, 123, 189));
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(56, 123, 189, 0.2);
        }
        .error-message {
            background: #FEE2E2;
            color: #991B1B;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .success-message {
            background: #D1FAE5;
            color: #065F46;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .password-requirements {
            font-size: 0.875rem;
            color: #4a5568;
            margin-top: 8px;
        }
        ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>

        <?php if ($error_message): ?>
            <div class="error-message"><?= $error_message ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="success-message"><?= $success_message ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="password">New Password:</label>
                <input type="password" id="password" name="password" required minlength="8">
                <div class="password-requirements">
                    Password must:
                    <ul>
                        <li>Be at least 8 characters long</li>
                        <li>Contain an uppercase and lowercase letter</li>
                        <li>Contain a number and a special character</li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-key"></i> Reset Password
            </button>
        </form>
    </div>

    <script>
        // Confirm password validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            this.setCustomValidity(
                this.value !== document.getElementById('password').value ? 'Passwords do not match' : ''
            );
        });
    </script>
</body>
</html>