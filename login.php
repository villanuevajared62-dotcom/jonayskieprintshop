<?php
// login.php
session_name('user_session'); // Unique name for user
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

function sendJsonResponse($data) {
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        sendJsonResponse(['success' => false, 'message' => 'Email and password are required']);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid email format']);
    }

    // ===============================
    // 🗄 DATABASE LOGIN (normal users)
    // ===============================
    $pdo = getDBConnection();
    if (!$pdo) {
        sendJsonResponse(['success' => false, 'message' => 'Database connection failed']);
    }

    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password_hash, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid email or password']);
    }

    // ✅ SESSION SETUP
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();

    if ($remember) {
        $token = bin2hex(random_bytes(32));
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
    }

    $redirect = ($user['role'] === 'admin') ? 'admin.php' : 'dashboard.php';

    sendJsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'redirect' => $redirect,
        'user' => [
            'id' => $user['id'],
            'name' => $_SESSION['user_name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - Jonayskie Prints</title>
    <link rel="stylesheet" href="./css/login.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
        /* Optional: make admin button stand out */
        .btn-admin {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            color: #fff;
        }
        .btn-admin:hover {
            background: linear-gradient(135deg, #b91c1c 0%, #7f1d1d 100%);
        }
        .auth-divider span {
            color: #555;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-print"></i>
                    <span>Jonayskie Prints</span>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to your account to manage your orders</p>
            </div>

            <form class="auth-form" id="loginForm" method="POST" action="login.php" novalidate>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" required />
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required />
                        <button type="button" class="password-toggle" onclick="togglePassword('password')" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" name="remember" />
                        <span class="checkmark"></span>
                        Remember me
                    </label>
                    <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-full">Sign In</button>

                <div class="auth-divider">
                    <span>Don't have an account?</span>
                </div>
                <a href="register.php" class="btn btn-secondary btn-full">Create Account</a>

                <div class="auth-divider">
                    <span>Are you an admin?</span>
                </div>
                <a href="login_admin.php" class="btn btn-admin btn-full">
                    <i class="fas fa-shield-alt"></i> Admin Login
                </a>
            </form>

            <div class="auth-footer">
                <a href="index.html">← Back to Home</a>
            </div>
        </div>
    </div>

    <div id="notification"></div>

    <script>
        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling.querySelector('i');
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        const form = document.getElementById('loginForm');
        const notification = document.getElementById('notification');

        function showNotification(message, type = 'error') {
            notification.textContent = message;
            notification.className = type;
            notification.style.display = 'block';
            setTimeout(() => notification.style.display = 'none', 4000);
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' },
                });
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => window.location.href = data.redirect, 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            }
        });
    </script>
</body>
</html>
