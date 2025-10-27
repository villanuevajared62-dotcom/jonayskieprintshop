<?php
// login_admin.php
session_name('admin_session'); // Unique session name for admin
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
    // 🔐 HARD-CODED ADMIN ACCOUNT
    // ===============================
    $hardcoded_email = 'admin@jonayskieprints.com';
    $hardcoded_hash = '$2b$12$b5EFDTBIBweXYXvYHmFyKOSxy32/fElSlizORgP0llZ8rSytaoao2'; // JONAYADMIN2025

    if ($email === $hardcoded_email && password_verify($password, $hardcoded_hash)) {
        $_SESSION['user_id'] = 0;
        $_SESSION['user_email'] = $hardcoded_email;
        $_SESSION['user_name'] = 'Admin User';
        $_SESSION['user_role'] = 'admin';
        $_SESSION['login_time'] = time();

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            setcookie('admin_remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
        }

        sendJsonResponse([
            'success' => true,
            'message' => 'Welcome Admin!',
            'redirect' => 'admin.php',
            'user' => [
                'id' => 0,
                'name' => 'Admin User',
                'email' => $hardcoded_email,
                'role' => 'admin'
            ]
        ]);
    }

    // ===============================
    // 🗄 DATABASE ADMIN LOGIN
    // ===============================
    $pdo = getDBConnection();
    if (!$pdo) {
        sendJsonResponse(['success' => false, 'message' => 'Database connection failed']);
    }

    // ✅ ONLY GET ADMIN USERS
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password_hash, role FROM users WHERE email = ? AND role = 'admin'");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid admin credentials']);
    }

    // ✅ SESSION SETUP FOR ADMIN
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_role'] = 'admin';
    $_SESSION['login_time'] = time();

    if ($remember) {
        $token = bin2hex(random_bytes(32));
        setcookie('admin_remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
    }

    sendJsonResponse([
        'success' => true,
        'message' => 'Welcome Admin!',
        'redirect' => 'admin.php',
        'user' => [
            'id' => $user['id'],
            'name' => $_SESSION['user_name'],
            'email' => $user['email'],
            'role' => 'admin'
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
    <title>Admin Login - Jonayskie Prints</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        purple: {
                            400: '#a78bfa',
                            500: '#8b5cf6',
                            600: '#7c3aed',
                        },
                        red: {
                            500: '#ef4444',
                            600: '#dc2626',
                            700: '#b91c1c',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom gradients and animations */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .slide-up { animation: slideUp 0.6s ease; }
        .bg-gradient-purple { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-gradient-red { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); }
        .hover\:bg-gradient-red-hover:hover { background: linear-gradient(135deg, #b91c1c 0%, #7f1d1d 100%); }
    </style>
</head>
<body class="bg-gradient-purple min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md slide-up">
        <div class="bg-white rounded-3xl p-8 shadow-2xl">
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <i class="fas fa-shield-alt text-purple-600 text-4xl mr-2"></i>
                    <span class="text-2xl font-bold text-gray-800 self-center">Jonayskie Prints</span>
                </div>
                <span class="inline-block bg-red-600 text-white px-3 py-1 rounded-full text-xs font-semibold mb-2">ADMIN ACCESS</span>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Admin Login</h2>
                <p class="text-gray-600">Authorized personnel only</p>
            </div>

            <form class="space-y-6" id="loginForm" method="POST" action="login_admin.php" novalidate>
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Admin Email</label>
                    <div class="relative">
                        <i class="fas fa-user-shield absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="email" id="email" name="email" required placeholder="admin@jonayskieprints.com" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-purple-600 focus:ring-1 focus:ring-purple-100 transition-all duration-300 placeholder-gray-400" />
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Admin Password</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="password" id="password" name="password" required placeholder="Enter your password" class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-purple-600 focus:ring-1 focus:ring-purple-100 transition-all duration-300 placeholder-gray-400" />
                        <button type="button" class="password-toggle absolute right-3 top-1/2 transform -translate-y-1/2 bg-transparent border-none text-gray-500 cursor-pointer p-1" onclick="togglePassword('password')" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-start text-sm">
                    <label class="flex items-center cursor-pointer relative flex-1">
                        <input type="checkbox" name="remember" class="sr-only peer" />
                        <div class="w-5 h-5 border-2 border-gray-300 rounded mr-2 flex items-center justify-center transition-all duration-300 peer-checked:bg-purple-600 peer-checked:border-purple-600"></div>
                        <span class="text-gray-700 select-none">Remember me</span>
                    </label>
                </div>

                <button type="submit" class="w-full bg-gradient-red hover:bg-gradient-red-hover text-white font-semibold py-3 px-4 rounded-lg transition-all duration-300 flex items-center justify-center">
                    <span class="mr-2">→</span> Admin Sign In
                </button>

                <div class="relative text-center my-6 text-gray-500 before:absolute before:top-1/2 before:left-0 before:right-0 before:h-px before:bg-gray-200 before:-translate-y-1/2">
                    <span class="relative bg-white px-4">Not an admin?</span>
                </div>

                <a href="login.php" class="block w-full border-2 border-gray-300 text-gray-700 hover:bg-gray-50 font-semibold py-3 px-4 rounded-lg text-center transition-all duration-300">Customer Login</a>
            </form>

            <div class="text-center mt-8">
                <a href="index.html" class="text-gray-600 hover:text-purple-600 font-medium transition-colors duration-300">← Back to Home</a>
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
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        const form = document.getElementById('loginForm');
        const notification = document.getElementById('notification');

        function showNotification(message, type = 'error') {
            notification.textContent = message;
            notification.className = `fixed top-5 right-5 px-6 py-4 rounded-lg text-white font-bold ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} shadow-lg z-50`;
            notification.style.display = 'block';

            setTimeout(() => {
                notification.style.display = 'none';
            }, 4000);
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(form);

            try {
                const response = await fetch('login_admin.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('An error occurred. Please try again.', 'error');
                console.error('Error:', error);
            }
        });
    </script>
</body>
</html>