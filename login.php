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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        },
                        danger: {
                            600: '#dc2626',
                            700: '#b91c1c',
                            800: '#991b1b',
                            900: '#7f1d1d',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom animations and gradients not covered by Tailwind */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .slide-up { animation: slideUp 0.6s ease; }
        .bg-gradient-auth { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-gradient-danger { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); }
        .hover\:bg-gradient-danger-hover:hover { background: linear-gradient(135deg, #b91c1c 0%, #7f1d1d 100%); }
    </style>
</head>
<body class="bg-gradient-auth min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md slide-up">
        <div class="bg-white rounded-3xl p-8 shadow-2xl">
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4 text-3xl">
                    <i class="fas fa-print text-primary-600 mr-2"></i>
                    <span class="text-2xl font-bold text-gray-800">Jonayskie Prints</span>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Welcome Back</h2>
                <p class="text-gray-600">Sign in to your account to manage your orders</p>
            </div>

            <form class="space-y-6" id="loginForm" method="POST" action="login.php" novalidate>
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="email" id="email" name="email" required class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-primary-600 focus:ring-1 focus:ring-primary-100 transition-all duration-300" />
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="password" id="password" name="password" required class="w-full pl-10 pr-12 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-primary-600 focus:ring-1 focus:ring-primary-100 transition-all duration-300" />
                        <button type="button" class="password-toggle absolute right-3 top-1/2 transform -translate-y-1/2 bg-transparent border-none text-gray-500 cursor-pointer p-1" onclick="togglePassword('password')" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="flex justify-between items-center text-sm">
                    <label class="flex items-center cursor-pointer relative">
                        <input type="checkbox" name="remember" class="sr-only" />
                        <div class="w-5 h-5 border-2 border-gray-200 rounded mr-2 flex items-center justify-center transition-all duration-300"></div>
                        <span class="text-gray-700 select-none">Remember me</span>
                    </label>
                    <a href="forgot_password.php" class="text-primary-600 hover:text-primary-700 font-medium transition-colors duration-300">Forgot Password?</a>
                </div>

                <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-4 rounded-xl transition-all duration-300 transform hover:-translate-y-0.5 hover:shadow-lg">Sign In</button>

                <div class="relative text-center my-4 text-gray-600 before:absolute before:top-1/2 before:left-0 before:right-0 before:h-px before:bg-gray-200 before:-translate-y-1/2 after:absolute after:hidden">
                    <span class="relative bg-white px-4">Don't have an account?</span>
                </div>
                <a href="register.php" class="block w-full bg-white border-2 border-primary-600 text-primary-600 hover:bg-primary-50 font-semibold py-3 px-4 rounded-xl text-center transition-all duration-300">Create Account</a>

                <div class="relative text-center my-4 text-gray-600 before:absolute before:top-1/2 before:left-0 before:right-0 before:h-px before:bg-gray-200 before:-translate-y-1/2 after:absolute after:hidden">
                    <span class="relative bg-white px-4">Are you an admin?</span>
                </div>
                <a href="login_admin.php" class="block w-full bg-gradient-danger hover:bg-gradient-danger-hover text-white font-semibold py-3 px-4 rounded-xl text-center transition-all duration-300 flex items-center justify-center">
                    <i class="fas fa-shield-alt mr-2"></i> Admin Login
                </a>
            </form>

            <div class="text-center mt-8">
                <a href="index.html" class="text-gray-600 hover:text-primary-600 font-medium transition-colors duration-300">← Back to Home</a>
            </div>
        </div>
    </div>

    <div id="notification" class="fixed top-5 right-5 px-6 py-4 rounded-lg text-white font-bold hidden z-50 shadow-lg"></div>

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
            notification.className = `fixed top-5 right-5 px-6 py-4 rounded-lg text-white font-bold ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} shadow-lg z-50`;
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