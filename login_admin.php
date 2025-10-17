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
        <link rel="stylesheet" href="./css/login.css" />
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
        <style>
            /* Admin-specific styling */
            .auth-header .logo {
                color: #dc2626;
            }
            .btn-primary {
                background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            }
            .btn-primary:hover {
                background: linear-gradient(135deg, #b91c1c 0%, #7f1d1d 100%);
            }
            .admin-badge {
                display: inline-block;
                background: #dc2626;
                color: white;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                margin-top: 10px;
            }
        </style>
    </head>
    <body>
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <div class="logo">
                        <i class="fas fa-shield-alt"></i>
                        <span>Jonayskie Prints</span>
                    </div>
                    <span class="admin-badge">ADMIN ACCESS</span>
                    <h2>Admin Login</h2>
                    <p>Authorized personnel only</p>
                </div>

                <form class="auth-form" id="loginForm" method="POST" action="login_admin.php" novalidate>
                    <div class="form-group">
                        <label for="email">Admin Email</label>
                        <div class="input-group">
                            <i class="fas fa-user-shield"></i>
                            <input type="email" id="email" name="email" required placeholder="admin@jonayskieprints.com" />
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Admin Password</label>
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
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">
                        <i class="fas fa-sign-in-alt"></i> Admin Sign In
                    </button>

                    <div class="auth-divider">
                        <span>Not an admin?</span>
                    </div>

                    <a href="login.php" class="btn btn-secondary btn-full">Customer Login</a>
                </form>

                <div class="auth-footer">
                    <a href="index.html">← Back to Home</a>
                </div>
            </div>
        </div>

        <!-- Notification div -->
        <div id="notification"></div>

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
                notification.className = '';
                notification.classList.add(type);
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
                        // Redirect to admin dashboard
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