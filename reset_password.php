<?php
session_start();
include 'config.php';
$pdo = getDBConnection();
$message = "";
$tokenValid = false;
$showLoginLink = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = :token AND expires > :time");
    $stmt->execute(['token' => $token, 'time' => time()]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reset) {
        // Check if OTP was verified
        $stmt = $pdo->prepare("SELECT verified FROM password_otp WHERE email = :email AND verified = 1 AND expires > :now ORDER BY id DESC LIMIT 1");
        $stmt->execute(['email' => $reset['email'], 'now' => time()]);
        $otpVerified = $stmt->fetch();
        if ($otpVerified) {
            $tokenValid = true;
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $newPassword = $_POST['password'];
                $confirmPassword = $_POST['confirm_password'];
                $minLength = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8;
                if (strlen($newPassword) < $minLength) {
                    $message = "<div class='message error'>✗ Password must be at least {$minLength} characters long.</div>";
                } elseif ($newPassword !== $confirmPassword) {
                    $message = "<div class='message error'>✗ Passwords do not match. Please try again.</div>";
                } else {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = :password WHERE email = :email");
                    $stmt->execute(['password' => $hashedPassword, 'email' => $reset['email']]);
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = :token");
                    $stmt->execute(['token' => $token]);
                    $message = "<div class='message success'>✓ Password successfully reset! You can now login with your new password.</div>";
                    $tokenValid = false;
                    $showLoginLink = true;
                }
            }
        } else {
            $message = "<div class='message error'>You must verify OTP before resetting your password.</div>";
        }
    } else {
        $message = "<div class='message error'>✗ Invalid or expired token.</div>";
    }
} else {
    $message = "<div class='message error'>✗ No reset token provided.</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reset Password | Jonayskie Prints</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Font Awesome for eye icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: #e6eefc; 
            font-family: 'Poppins', sans-serif; 
            margin: 0;
            padding: 20px;
        }
        .container { 
            background: #fff; 
            max-width: 400px; 
            margin: 60px auto; 
            padding: 35px 30px; 
            border-radius: 10px; 
            box-shadow: 0 8px 25px #3b82f690; 
        }
        h2 { 
            color: #274c77; 
            margin-bottom: 20px; 
            text-align: center;
        }
        label { 
            font-weight: 500; 
            color: #274c77; 
            display: block;
            margin-bottom: 5px;
        }
        .password-group { 
            position: relative; 
            margin-bottom: 15px; 
        }
        input[type="password"], input[type="text"] { 
            width: 100%; 
            padding: 10px 40px 10px 10px; 
            border: 2px solid #b7c8ed; 
            border-radius: 6px; 
            box-sizing: border-box;
            font-size: 16px;
        }
        .toggle-password { 
            position: absolute; 
            right: 10px; 
            top: 50%; 
            transform: translateY(-50%); 
            background: none; 
            border: none; 
            color: #6b7280; 
            cursor: pointer; 
            font-size: 16px; 
        }
        .toggle-password:hover { 
            color: #3b82f6; 
        }
        button[type="submit"] { 
            background: #3b82f6; 
            color: #fff; 
            border: none; 
            padding: 12px; 
            border-radius: 6px; 
            width: 100%; 
            font-size: 16px; 
            font-weight: 600; 
            cursor: pointer; 
        }
        button[type="submit"]:hover { 
            background: #2563eb; 
        }
        .message { 
            margin-top: 20px; 
            padding: 12px; 
            border-radius: 6px; 
            font-size: 15px; 
            text-align: center;
        }
        .success { 
            background: #d1fae5; 
            color: #065f46; 
            border: 1px solid #10b981; 
        }
        .error { 
            background: #fee2e2; 
            color: #991b1b; 
            border: 1px solid #ef4444; 
        }
        .login-link {
            display: block; 
            margin-top: 20px; 
            color: #2563eb; 
            text-align: center; 
            font-weight: 600; 
            text-decoration: none;
        }
        .login-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        <?php if ($tokenValid): ?>
            <form method="POST">
                <label for="password">New Password</label>
                <div class="password-group">
                    <input type="password" id="password" name="password" required minlength="<?php echo defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8; ?>" placeholder="Enter new password">
                    <button type="button" class="toggle-password" onclick="togglePassword('password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <label for="confirm_password">Confirm New Password</label>
                <div class="password-group">
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="<?php echo defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8; ?>" placeholder="Confirm new password">
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <button type="submit">Reset Password</button>
            </form>
        <?php endif; ?>
        <?php echo $message; ?>
        <?php if ($showLoginLink): ?>
            <a href="login.php" class="login-link">Go to Login Page →</a>
        <?php endif; ?>
    </div>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>