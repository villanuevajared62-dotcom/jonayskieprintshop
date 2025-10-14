<?php
// api.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

require_once 'config.php';

header('Content-Type: application/json');

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function checkAuth() {
    if (empty($_SESSION['user_id'])) {
        sendResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }
}

$pdo = getDBConnection();
if (!$pdo) {
    sendResponse(['success' => false, 'message' => 'Database connection failed'], 500);
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;

switch ($action) {
    case 'getDashboardStats':
        checkAuth();
        $userId = $_SESSION['user_id'];

        // Get total orders
        $totalOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $totalOrders->execute([$userId]);
        $totalOrders = (int)$totalOrders->fetchColumn();

        // Pending orders count
        $pendingOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'pending'");
        $pendingOrders->execute([$userId]);
        $pendingOrders = (int)$pendingOrders->fetchColumn();

        // Completed orders count
        $completedOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'completed'");
        $completedOrders->execute([$userId]);
        $completedOrders = (int)$completedOrders->fetchColumn();

        // Total spent (sum of completed orders)
        $totalSpent = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id = ? AND status = 'completed'");
        $totalSpent->execute([$userId]);
        $totalSpent = (float)$totalSpent->fetchColumn();

        sendResponse([
            'success' => true,
            'data' => [
                'totalOrders' => $totalOrders,
                'pendingOrders' => $pendingOrders,
                'completedOrders' => $completedOrders,
                'totalSpent' => number_format($totalSpent, 2),
            ]
        ]);
        break;

    case 'getServices':
        checkAuth();

        $stmt = $pdo->query("SELECT id, name, description, price FROM services ORDER BY name ASC");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendResponse(['success' => true, 'services' => $services]);
        break;

    case 'createOrder':
        checkAuth();

        $userId = $_SESSION['user_id'];

        // Basic validation
        $serviceId = $_POST['service_id'] ?? null;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
        $specifications = $_POST['specifications'] ?? '';
        $deliveryOption = $_POST['delivery_option'] ?? 'pickup';
        $deliveryAddress = $_POST['delivery_address'] ?? null;
        $paymentMethod = $_POST['payment_method'] ?? 'cash';

        if (!$serviceId || $quantity <= 0 || empty($specifications)) {
            sendResponse(['success' => false, 'message' => 'Missing required fields'], 400);
        }

        // Get service price
        $stmt = $pdo->prepare("SELECT price FROM services WHERE id = ?");
        $stmt->execute([$serviceId]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$service) {
            sendResponse(['success' => false, 'message' => 'Invalid service selected'], 400);
        }

        $totalAmount = $service['price'] * $quantity;

        // Insert order
        $stmt = $pdo->prepare("INSERT INTO orders 
            (user_id, service_id, quantity, specifications, delivery_option, delivery_address, payment_method, total_amount, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

        $result = $stmt->execute([$userId, $serviceId, $quantity, $specifications, $deliveryOption, $deliveryAddress, $paymentMethod, $totalAmount]);

        if (!$result) {
            sendResponse(['success' => false, 'message' => 'Failed to create order'], 500);
        }

        $orderId = $pdo->lastInsertId();

        // Handle file uploads
        if (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
            $uploadDir = __DIR__ . '/uploads/orders/' . $orderId . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['files']['tmp_name'][$i];
                    $fileName = basename($_FILES['files']['name'][$i]);
                    $targetPath = $uploadDir . $fileName;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $stmtFile = $pdo->prepare("INSERT INTO order_files (order_id, file_path, uploaded_at) VALUES (?, ?, NOW())");
                        $relativePath = 'uploads/orders/' . $orderId . '/' . $fileName;
                        $stmtFile->execute([$orderId, $relativePath]);
                    }
                }
            }
        }

        sendResponse(['success' => true, 'message' => 'Order placed successfully', 'order_id' => $orderId]);
        break;

   

    case 'getProfile':
        checkAuth();
        $userId = $_SESSION['user_id'];

        $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            sendResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        sendResponse(['success' => true, 'user' => $user]);
        break;

    case 'updateProfile':
        checkAuth();
        $userId = $_SESSION['user_id'];

        $firstName = $_POST['first_name'] ?? null;
        $lastName = $_POST['last_name'] ?? null;
        $email = $_POST['email'] ?? null;

        if (!$firstName || !$lastName || !$email) {
            sendResponse(['success' => false, 'message' => 'Missing profile fields'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse(['success' => false, 'message' => 'Invalid email format'], 400);
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            sendResponse(['success' => false, 'message' => 'Email already taken'], 400);
        }

        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
        $updated = $stmt->execute([$firstName, $lastName, $email, $userId]);

        if ($updated) {
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $firstName . ' ' . $lastName;
            sendResponse(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            sendResponse(['success' => false, 'message' => 'Failed to update profile'], 500);
        }
        break;

    default:
        sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
