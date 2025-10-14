<?php
session_start();
include 'config.php';
$pdo = getDBConnection();
$message = '';
$email = $_GET['email'] ?? $_POST['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $email && isset($_POST['otp'])) {
    $otp = $_POST['otp'];
    $stmt = $pdo->prepare("SELECT * FROM password_otp WHERE email = :email AND otp = :otp AND expires > :now AND verified = 0");
    $stmt->execute(['email' => $email, 'otp' => $otp, 'now' => time()]);
    $record = $stmt->fetch();
    if ($record) {
        $pdo->prepare("UPDATE password_otp SET verified = 1 WHERE id = :id")->execute(['id' => $record['id']]);
        $stmt = $pdo->prepare("SELECT token FROM password_resets WHERE email = :email ORDER BY expires DESC LIMIT 1");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        $token = $row ? $row['token'] : '';
        header("Location: reset_password.php?token=" . urlencode($token));
        exit;
    } else {
        $message = "<div class='message error'>Invalid or expired OTP.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Verify OTP | Jonayskie Prints</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background: #e6eefc; font-family: 'Poppins', sans-serif; }
        .container { background: #fff; max-width: 400px; margin: 60px auto; padding: 35px 30px; border-radius: 10px; box-shadow: 0 8px 25px #3b82f690; }
        h2 { color: #274c77; margin-bottom: 20px; }
        label { font-weight: 500; color: #274c77; }
        input[type="text"] { width: 100%; padding: 10px; margin-bottom: 20px; border: 2px solid #b7c8ed; border-radius: 6px; }
        button { background: #3b82f6; color: #fff; border: none; padding: 12px; border-radius: 6px; width: 100%; font-size: 16px; font-weight: 600; cursor: pointer; }
        button:hover { background: #2563eb; }
        .message { margin-top: 20px; padding: 12px; border-radius: 6px; font-size: 15px; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Enter OTP</h2>
        <form method="POST">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <label for="otp">OTP Code</label>
            <input type="text" id="otp" name="otp" maxlength="6" required placeholder="Enter 6-digit OTP">
            <button type="submit">Verify OTP</button>
        </form>
        <?php echo $message; ?>
    </div>
</body>
</html>