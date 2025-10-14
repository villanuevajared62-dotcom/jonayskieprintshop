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

    // Hash password and insert user
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Determine user role (customer by default)
$adminKeyInput = $_POST['adminKey'] ?? ''; 
$secretAdminKey = 'JONAYADMIN2025'; // ← change this to anything only you know

if ($adminKeyInput === $secretAdminKey) {
    $role = 'admin';
} else {
    $role = 'customer';
}

// Hash password and insert user
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users (first_name, last_name, email, phone, password_hash, role) 
    VALUES (?, ?, ?, ?, ?, ?)
");

$result = $stmt->execute([$firstName, $lastName, $email, $phone, $passwordHash, $role]);


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
            'user_id' => $userId
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
    <link rel="stylesheet" href="./css/register.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-print"></i>
                    <span>Jonayskie Prints</span>
                </div>
                <h2>Create Account</h2>
                <p>Join our community - it's completely free!</p>
            </div>


            <form class="auth-form" id="registerForm" action="register.php" method="POST" novalidate>
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">First Name</label>
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" id="firstName" name="firstName" required />
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="lastName">Last Name</label>
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" id="lastName" name="lastName" required />
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" required />
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-group">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="phone" name="phone" required />
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required />
                        <button type="button" class="password-toggle" onclick="togglePassword('password')"></button>
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirmPassword" name="confirmPassword" required />
                        <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')"></button>
                    </div>
                                <!-- Optional: Admin Key (only you know) -->
<div class="form-group" style="display:none;">
    <label for="adminKey">JONAYADMIN2025</label>
    <input type="text" id="adminKey" name="adminKey" value="">
</div>

                </div>

                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" name="terms" required />
                        <span class="checkmark"></span>
                        I agree to the <a href="#" class="terms-link">Terms of Service</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-full">Create Account</button>

                <div class="auth-divider">
                    <span>Already have an account?</span>
                </div>

                <a href="login.php" class="btn btn-secondary btn-full">Sign In</a>
            </form>

            <div class="auth-footer">
                <a href="index.html">← Back to Home</a>
            </div>
        </div>
    </div>

    <div id="popupMessage"></div>

    <script>
        function togglePassword(id) {
            const input = document.getElementById(id);
            if (input.type === "password") {
                input.type = "text";
            } else {
                input.type = "password";
            }
        }

        function showPopup(message, isSuccess = false) {
            const popup = document.getElementById('popupMessage');
            popup.textContent = message;
            popup.style.backgroundColor = isSuccess ? '#4CAF50' : '#f44336'; // green or red
            popup.style.display = 'block';

            setTimeout(() => {
                popup.style.display = 'none';
                if(isSuccess) {
                    // Redirect to login after showing success message for 2.5 seconds
                    window.location.href = 'login.php';
                }
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
