    <?php
    // config.php - Database Configuration and Utility Functions

    // Database Configuration
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'printingshop');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('PASSWORD_MIN_LENGTH', 8);

    // ==================== Utility Functions ====================

    // Sanitize input
    if (!function_exists('sanitizeInput')) {
        function sanitizeInput($data) {
            return htmlspecialchars(strip_tags(trim($data)));
        }
    }

    // Validate email
    if (!function_exists('validateEmail')) {
        function validateEmail($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        }
    }

    // Validate phone
    if (!function_exists('validatePhone')) {
        function validatePhone($phone) {
            return preg_match('/^(\+63|0)?9\d{9}$/', $phone);
        }
    }

    // Send JSON response
    if (!function_exists('sendJsonResponse')) {
        function sendJsonResponse($data) {
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }
    }

    // Get PDO database connection
    if (!function_exists('getDBConnection')) {
        function getDBConnection() {
            try {
                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                return $pdo;
            } catch (PDOException $e) {
                error_log("Database Connection Error: " . $e->getMessage());
                return false;
            }
        }
    }

    // Calculate order amount
    if (!function_exists('calculateOrderAmount')) {
        function calculateOrderAmount($service, $quantity) {
            $prices = [
                'print' => 2.00,
                'photocopy' => 1.50,
                'scanning' => 3.00,
                'photo-development' => 15.00,
                'laminating' => 5.00
            ];
            return isset($prices[$service]) ? $prices[$service] * $quantity : 0;
        }
    }

    // Format currency
    if (!function_exists('formatCurrency')) {
        function formatCurrency($amount) {
            return '₱' . number_format($amount, 2);
        }
    }

    // Check login
    if (!function_exists('isLoggedIn')) {
        function isLoggedIn() {
            return isset($_SESSION['user_id']);
        }
    }

    // Check admin
    if (!function_exists('isAdmin')) {
        function isAdmin() {
            return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
        }
    }

    // Redirect
    if (!function_exists('redirect')) {
        function redirect($url) {
            header("Location: $url");
            exit;
        }
    }

    // CSRF token generation
    if (!function_exists('generateToken')) {
        function generateToken() {
            return bin2hex(random_bytes(32));
        }
    }

    // CSRF token verification
    if (!function_exists('verifyToken')) {
        function verifyToken($token) {
            return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
        }
    }
    ?>
