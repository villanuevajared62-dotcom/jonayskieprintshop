<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    // Sanitize inputs
    $firstName = sanitizeInput($_POST['firstName'] ?? '');
    $lastName = sanitizeInput($_POST['lastName'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    if (!validateEmail($email)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }

    if (!validatePhone($phone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
        exit;
    }

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long']);
        exit;
    }

    if ($password !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email address is already registered']);
        exit;
    }

    // Hash password and insert user as customer
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'customer';

    $stmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, email, phone, password_hash, role) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $result = $stmt->execute([$firstName, $lastName, $email, $phone, $passwordHash, $role]);

    if ($result) {
        $userId = $pdo->lastInsertId();

        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $firstName . ' ' . $lastName;
        $_SESSION['user_role'] = 'customer';
        $_SESSION['login_time'] = time();

        echo json_encode([
            'success' => true,
            'message' => 'Registration successful!',
            'user_id' => $userId,
            'redirect' => 'login.php'
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create user account']);
        exit;
    }

} else {
    // Show the registration form HTML
    ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Register - Jonayskie Prints</title>
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
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom animations and gradients */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .slide-up { animation: slideUp 0.6s ease; }
        .bg-gradient-auth { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .password-strength { margin-top: 0.25rem; height: 3px; border-radius: 2px; transition: all 0.3s ease; }
        .password-strength.weak { background: #ef4444; width: 33%; }
        .password-strength.medium { background: #f59e0b; width: 66%; }
        .password-strength.strong { background: #10b981; width: 100%; }
    </style>
</head>
<body class="bg-gradient-auth min-h-screen flex items-center justify-center p-2 py-4">
    <div class="w-full max-w-lg slide-up">
        <div class="bg-white rounded-2xl p-6 shadow-2xl">
            <div class="text-center mb-4">
                <div class="flex justify-center mb-2 items-center">
                    <i class="fas fa-print text-primary-600 mr-2 text-2xl"></i>
                    <span class="text-xl font-bold text-gray-800">Jonayskie Prints</span>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-1">Create Account</h2>
                <p class="text-sm text-gray-600">Join our community - it's completely free!</p>
            </div>

            <form class="space-y-4" id="registerForm" action="register.php" method="POST" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="firstName" class="block text-sm font-semibold text-gray-700 mb-1">First Name</label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" id="firstName" name="firstName" required class="w-full pl-10 pr-3 py-2.5 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:border-primary-600 focus:ring-1 focus:ring-primary-100 transition-all duration-300" />
                        </div>
                    </div>

                    <div>
                        <label for="lastName" class="block text-sm font-semibold text-gray-700 mb-1">Last Name</label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" id="lastName" name="lastName" required class="w-full pl-10 pr-3 py-2.5 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:border-primary-600 focus:ring-1 focus:ring-primary-100 transition-all duration-300" />
                        </div>
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-1">Email Address</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="email" id="email" name="email" required class="w-full pl-10 pr-3 py-2.5 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:border-primary-600 focus:ring-1 focus:ring-primary-100 transition-all duration-300" />
                    </div>
                </div>

                <div>
                    <label for="phone" class="block text-sm font-semibold text-gray-700 mb-1">Phone Number</label>
                    <div class="relative">
                        <i class="fas fa-phone absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="tel" id="phone" name="phone" required class="w-full pl-10 pr-3 py-2.5 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:border-primary-600 focus:ring-1 focus:ring-primary-100 transition-all duration-300" />
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="password" id="password" name="password" required class="w-full pl-10 pr-10 py-2.5 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:border-primary-600 focus:ring-1 focus:ring-primary-100 transition-all duration-300" />
                        <button type="button" class="password-toggle absolute right-3 top-1/2 transform -translate-y-1/2 bg-transparent border-none text-gray-500 cursor-pointer p-1" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                </div>

                <div>
                    <label for="confirmPassword" class="block text-sm font-semibold text-gray-700 mb-1">Confirm Password</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="password" id="confirmPassword" name="confirmPassword" required class="w-full pl-10 pr-10 py-2.5 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:border-primary-600 focus:ring-1 focus:ring-primary-100 transition-all duration-300" />
                        <button type="button" class="password-toggle absolute right-3 top-1/2 transform -translate-y-1/2 bg-transparent border-none text-gray-500 cursor-pointer p-1" onclick="togglePassword('confirmPassword')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center text-sm pt-1">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="terms" name="terms" required class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 mr-2" />
                        <span class="text-gray-700">I agree to the <a href="#" class="text-primary-600 hover:text-primary-700 font-medium">Terms of Service</a></span>
                    </label>
                </div>

                <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-4 rounded-xl transition-all duration-300 transform hover:-translate-y-0.5 hover:shadow-lg">Create Account</button>

                <div class="relative text-center my-3 text-sm text-gray-600">
                    <div class="absolute top-1/2 left-0 right-0 h-px bg-gray-200 -translate-y-1/2"></div>
                    <span class="relative bg-white px-3">Already have an account?</span>
                </div>

                <a href="login.php" class="block w-full bg-white border-2 border-primary-600 text-primary-600 hover:bg-primary-50 font-semibold py-3 px-4 rounded-xl text-center transition-all duration-300">Sign In</a>
            </form>

            <div class="text-center mt-5">
                <a href="index.html" class="text-sm text-gray-600 hover:text-primary-600 font-medium transition-colors duration-300">← Back to Home</a>
            </div>
        </div>
    </div>

    <div id="popupMessage" class="fixed top-5 right-5 px-5 py-3 rounded-lg text-white font-bold hidden z-50 shadow-lg text-sm"></div>

    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthIndicator = document.getElementById('passwordStrength');
        passwordInput.addEventListener('input', function() {
            const strength = this.value.length < 6 ? 'weak' : this.value.length < 10 ? 'medium' : 'strong';
            strengthIndicator.className = `password-strength ${strength}`;
        });

        function togglePassword(id) {
            const input = document.getElementById(id);
            const button = input.nextElementSibling;
            const icon = button.querySelector('i');
            
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

        function showPopup(message, isSuccess = false) {
            const popup = document.getElementById('popupMessage');
            popup.textContent = message;
            popup.style.backgroundColor = isSuccess ? '#4CAF50' : '#f44336';
            popup.style.display = 'block';

            setTimeout(() => {
                popup.style.display = 'none';
            }, 2500);
        }

        document.getElementById('registerForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const formData = new FormData(this);

            fetch('register.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showPopup(data.message || 'Registration successful!', true);
                    setTimeout(() => {
                        window.location.href = data.redirect || 'login.php';
                    }, 1500);
                } else {
                    showPopup(data.message || 'Registration failed');
                }
            })
            .catch(() => {
                showPopup('An unexpected error occurred.');
            });
        });
    </script>
</body>
</html>

<?php
}
?>