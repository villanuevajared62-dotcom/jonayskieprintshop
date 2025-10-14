<?php
session_start();
include 'config.php';

// ✅ Use the Composer autoloader (since you installed PHPMailer via Composer)
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$pdo = getDBConnection();
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    if (!validateEmail($email)) {
        $message = "<div class='message error'>✗ Invalid email format.</div>";
    } else {
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $message = "<div class='message success'>✓ If that email exists, instructions have been sent.</div>";

        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(50));
            $expires = time() + 3600; // 1 hour

            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = :email");
            $stmt->execute(['email' => $email]);
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE password_resets SET token = :token, expires = :expires WHERE email = :email");
            } else {
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires) VALUES (:email, :token, :expires)");
            }
            $stmt->execute(['email' => $email, 'token' => $token, 'expires' => $expires]);

            // Generate OTP
            $otp = rand(100000, 999999);
            $otpExpires = time() + 600; // 10 minutes
            $stmt = $pdo->prepare("INSERT INTO password_otp (email, otp, expires, verified) VALUES (:email, :otp, :expires, 0)");
            $stmt->execute(['email' => $email, 'otp' => $otp, 'expires' => $otpExpires]);

            // Send reset link and OTP
            $resetLink = "http://localhost/2NDYEARPROJECT/reset_password.php?token=$token";
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;

                // ⚠️ Use Gmail App Password, not your real password!
                $mail->Username = 'villanuevajared62@gmail.com';
                $mail->Password = 'your_app_password_here'; 

                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('villanuevajared62@gmail.com', 'Jonayskie Prints');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset & OTP - Jonayskie Prints';
                $mail->Body = "
                    <h2 style='color: #333; margin-top: 0;'>Hello!</h2>
                    <p>You requested a password reset for Jonayskie Prints.</p>
                    <p>Click here to reset: <a href='$resetLink'>$resetLink</a></p>
                    <p>Your OTP code (for extra security): <b>$otp</b></p>
                    <p>OTP expires in 10 minutes. Reset link expires in 1 hour.</p>
                ";

                $mail->send();
            } catch (Exception $e) {
                error_log("Mail error: " . $mail->ErrorInfo);
            }

            // Redirect to OTP verification
            header("Location: verify_otp.php?email=" . urlencode($email));
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Forgot Password | Jonayskie Prints</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background: #e6eefc; font-family: 'Poppins', sans-serif; }
        .container { background: #fff; max-width: 400px; margin: 60px auto; padding: 35px 30px; border-radius: 10px; box-shadow: 0 8px 25px #3b82f690; }
        h2 { color: #274c77; margin-bottom: 15px; }
        label { font-weight: 500; color: #274c77; }
        input[type="email"] { width: 100%; padding: 10px; margin-bottom: 20px; border: 2px solid #b7c8ed; border-radius: 6px; }
        button { background: #3b82f6; color: #fff; border: none; padding: 12px; border-radius: 6px; width: 100%; font-size: 16px; font-weight: 600; cursor: pointer; }
        button:hover { background: #2563eb; }
        .message { margin-top: 20px; padding: 12px; border-radius: 6px; font-size: 15px; }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Forgot Password?</h2>
        <form method="POST">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="your@email.com" required autocomplete="email">
            <button type="submit">Send Reset Link & OTP</button>
        </form>
        <?php echo $message; ?>
    </div>
</body>
</html>