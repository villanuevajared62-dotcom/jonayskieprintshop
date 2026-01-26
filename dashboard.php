<?php
    // dashboard.php - FIXED SECURITY & DYNAMIC PRICING FROM 'pricing' TABLE
    // Show errors for debugging (remove on production)
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    session_name('user_session'); // Unique name for user
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once 'config.php'; // Include the database connection
    // ============================================
    // STRICT SESSION VALIDATION (NEW)
    // ============================================
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        // NO VALID SESSION - DESTROY AND REDIRECT
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        setcookie(session_name(), '', time() - 3600, '/');
        header('Location: login.php?reason=no_session');
        exit;
    }
    // ONLY ALLOW CUSTOMERS (NOT ADMIN)
    if ($_SESSION['user_role'] !== 'customer') {
        if ($_SESSION['user_role'] === 'admin') {
            header('Location: admin.php');
            exit;
        }
        // INVALID ROLE
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        setcookie(session_name(), '', time() - 3600, '/');
        header('Location: login.php?reason=invalid_role');
        exit;
    }
    // REGENERATE SESSION ID FOR SECURITY (every 30 mins)
    // SAME CHANGE - 1 HOUR INTERVAL
    if (!isset($_SESSION['last_regen']) || (time() - $_SESSION['last_regen'] > 3600)) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
    // PREVENT BACK BUTTON CACHING
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    // Get the DB connection
    $conn = getDBConnection();
    if (!$conn) {
        die("Database connection failed.");
    }
    if (isset($_REQUEST['action'])) {
        header('Content-Type: application/json');
        function sendResponse($success, $message = '', $data = []) {
            echo json_encode([
                'success' => $success,
                'message' => $message,
                'data' => $data
            ]);
            exit;
        }
        function requireLogin() {
            if (!isset($_SESSION['user_id'])) {
                sendResponse(false, 'User not authenticated');
            }
        }
        $action = $_REQUEST['action'];
        try {
            switch ($action) {
                case 'getServices':
                    requireLogin();
                    // DYNAMIC: Fetch all prices from 'pricing' table - FIXED: Added photocopying
                    $stmt = $conn->prepare("
                        SELECT print_bw, print_color, photocopying, scanning, photo_development, laminating
                        FROM pricing WHERE id = 1
                    ");
                    $stmt->execute();
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    // Fallback if no pricing row - FIXED: Added photocopying
                    if (!$row) {
                        error_log("No pricing row found (id=1). Using defaults.");
                        $row = [
                            'print_bw' => 1.00,
                            'print_color' => 2.00,
                            'photocopying' => 2.00,
                            'scanning' => 5.00,
                            'photo_development' => 15.00,
                            'laminating' => 20.00
                        ];
                    }
                    sendResponse(true, '', ['prices' => $row]);
                    break;
                case 'getDashboardStats':
                    requireLogin();
                    $user_id = $_SESSION['user_id'];
                    // Total orders
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $totalOrders = (int)$stmt->fetchColumn();
                    // Pending orders
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'pending'");
                    $stmt->execute([$user_id]);
                    $pendingOrders = (int)$stmt->fetchColumn();
                    // Completed orders
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'completed'");
                    $stmt->execute([$user_id]);
                    $completedOrders = (int)$stmt->fetchColumn();
                    // Total spent (only completed orders) - FIXED: Use stored total_amount
                    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = ? AND status = 'completed'");
                    $stmt->execute([$user_id]);
                    $totalSpent = number_format((float)$stmt->fetchColumn(), 2);
                    sendResponse(true, '', [
                        'totalOrders' => $totalOrders,
                        'pendingOrders' => $pendingOrders,
                        'completedOrders' => $completedOrders,
                        'totalSpent' => $totalSpent
                    ]);
                    break;
                case 'getOrder':
                    requireLogin();
                    $order_id = $_GET['order_id'] ?? '';
                    if (!$order_id) {
                        sendResponse(false, 'Order ID required');
                    }
                    $user_id = $_SESSION['user_id'];
                    // Fetch single order, only if pending (matches edit logic)
                    $stmt = $conn->prepare("
                        SELECT id AS order_id, service, quantity, specifications,
                               delivery_option, delivery_address, status, payment_method,
                               created_at, total_amount
                        FROM orders
                        WHERE id = ? AND user_id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$order_id, $user_id]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$order) {
                        sendResponse(false, 'Order not found or not editable');
                    }
                    sendResponse(true, '', $order); // Returns order as data (matches JS expectation: result.data)
                    break;
                case 'createOrder':
                    requireLogin();
                    $user_id = $_SESSION['user_id'];
               
                    // Validate inputs
                    $service = $_POST['service'] ?? '';
                    $quantity = intval($_POST['quantity'] ?? 0);
                    $specifications = $_POST['specifications'] ?? '';
                    $delivery_option = $_POST['delivery_option'] ?? 'pickup';
                    $delivery_address = $_POST['delivery_address'] ?? null;
                    $payment_method = $_POST['payment_method'] ?? 'cash';
                    $paper_size = $_POST['paper_size'] ?? 'A4';
                    $photo_size = $_POST['photo_size'] ?? 'A4';
                    $color_option = $_POST['color_option'] ?? 'bw';
                    $add_lamination = isset($_POST['add_lamination']) && $_POST['add_lamination'] === 'on';
               
                    if (!$service || $quantity < 1 || !$specifications) {
                        sendResponse(false, 'Missing or invalid fields');
                    }
               
                    if ($delivery_option === 'delivery' && empty($delivery_address)) {
                        sendResponse(false, 'Delivery address is required for delivery');
                    }
               
                    // Fetch prices
                    $stmt = $conn->prepare("
                        SELECT print_bw, print_color, photocopying, scanning, photo_development, laminating
                        FROM pricing WHERE id = 1
                    ");
                    $stmt->execute();
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row) {
                        $row = [
                            'print_bw' => 1.00,
                            'print_color' => 2.00,
                            'photocopying' => 2.00,
                            'scanning' => 5.00,
                            'photo_development' => 15.00,
                            'laminating' => 20.00
                        ];
                    }
               
                    // FIXED: Calculate price with proper paper size multipliers
                    $service_lower = strtolower($service);
               
                    // Paper size multipliers (applies to Print, Photocopy, Scanning)
                    $paperMultipliers = [
                        'A4' => 1.0,
                        'Short' => 1.0,
                        'Long' => 1.2
                    ];
                    $multiplier = $paperMultipliers[$paper_size] ?? 1.0;
               
                    $servicePrice = 0.0;
               
                    switch ($service_lower) {
                        case 'print':
                            $basePrice = ($color_option === 'color') ? $row['print_color'] : $row['print_bw'];
                            $servicePrice = $basePrice * $multiplier;
                            break;
                       
                        case 'photocopy':
                            // FIXED: Photocopy should use photocopying price with multiplier
                            $servicePrice = $row['photocopying'] * $multiplier;
                            break;
                       
                        case 'scanning':
                            $servicePrice = $row['scanning'] * $multiplier;
                            break;
                       
                        case 'photo development':
                            // Photo development doesn't use paper multiplier
                            $servicePrice = $row['photo_development'];
                            break;
                       
                        case 'laminating':
                            // Laminating doesn't use paper multiplier
                            $servicePrice = $row['laminating'];
                            break;
                       
                        default:
                            $servicePrice = 0;
                    }
               
                    if ($servicePrice <= 0) {
                        sendResponse(false, 'Invalid service or price not found');
                    }
               
                    // Calculate total
                    $total_amount = $servicePrice * $quantity;
               
                    // Add lamination if checked (and not already laminating service)
                    if ($add_lamination && $service_lower !== 'laminating') {
                        $total_amount += ($row['laminating'] * $quantity);
                        $specifications .= "\nAdd Lamination: Yes";
                    }
               
                    // Build specifications string with all options
                    $specifications = trim($specifications);
                    if (in_array($service, ['Print', 'Photocopy', 'Scanning'])) {
                        $specs_parts = [$specifications];
                        $specs_parts[] = "Paper Size: {$paper_size}";
                        if ($service === 'Print') {
                            $print_type = $color_option === 'color' ? 'Color' : 'Black & White';
                            $specs_parts[] = "Print Type: {$print_type}";
                        } else if ($service === 'Photocopy') {
                            $specs_parts[] = "Copy Type: Color";
                        } else if ($service === 'Scanning') {
                            $scan_type = $color_option === 'color' ? 'Color' : 'Black & White';
                            $specs_parts[] = "Scan Type: {$scan_type}";
                        }
                        $specifications = implode("\n", $specs_parts);
                    }
               
                    if ($service_lower === 'photo development') {
                        $specs_parts = [$specifications];
                        $specs_parts[] = "Photo Size: Glossy {$photo_size}";
                        $specifications = implode("\n", $specs_parts);
                    }
               
                    // Set delivery_address to null if pickup
                    $delivery_address = ($delivery_option === 'delivery') ? $delivery_address : null;
               
                    // Begin transaction
                    $conn->beginTransaction();
               
                    // Insert order - NOW WITH CORRECT TOTAL_AMOUNT
                    $stmt = $conn->prepare("
                        INSERT INTO orders (
                            user_id, service, quantity, specifications,
                            delivery_option, delivery_address, payment_method,
                            total_amount, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->execute([
                        $user_id, $service, $quantity, $specifications,
                        $delivery_option, $delivery_address, $payment_method,
                        $total_amount
                    ]);
                    $order_id = $conn->lastInsertId();
               
                    // Handle file upload
                    $uploadedFiles = [];
                    if (!empty($_FILES['files']['name'][0])) {
                        $uploadDir = __DIR__ . "/uploads/orders/{$order_id}/";
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
                            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                                $tmp = $_FILES['files']['tmp_name'][$i];
                                $safe = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['files']['name'][$i]));
                                $target = $uploadDir . $safe;
                                if (move_uploaded_file($tmp, $target)) {
                                    $relative = "uploads/orders/{$order_id}/{$safe}";
                                    $stmtF = $conn->prepare("INSERT INTO order_files (order_id, filename, filepath, uploaded_at) VALUES (?, ?, ?, NOW())");
                                    $stmtF->execute([$order_id, $safe, $relative]);
                                    $uploadedFiles[] = $safe;
                                }
                            }
                        }
                    }
               
                    // Commit transaction
                    $conn->commit();
                    sendResponse(true, 'Order placed successfully', [
                        'order_id' => $order_id,
                        'uploaded_files' => $uploadedFiles,
                        'total_amount' => number_format($total_amount, 2)
                    ]);
                    break;
               
                case 'getOrders':
                    requireLogin();
                    $user_id = $_SESSION['user_id'];
                    $status = $_GET['status'] ?? '';
                    if ($status !== '') {
                        $stmt = $conn->prepare("SELECT id AS order_id, service, quantity, specifications, delivery_option, delivery_address, status, payment_method, created_at, total_amount FROM orders WHERE user_id = ? AND status = ? ORDER BY created_at DESC");
                        $stmt->execute([$user_id, $status]);
                    } else {
                        $stmt = $conn->prepare("SELECT id AS order_id, service, quantity, specifications, delivery_option, delivery_address, status, payment_method, created_at, total_amount FROM orders WHERE user_id = ? ORDER BY created_at DESC");
                        $stmt->execute([$user_id]);
                    }
                    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    sendResponse(true, '', ['orders' => $orders]);
                    break;
               
                case 'updateOrder':
                    requireLogin();
                    $user_id = $_SESSION['user_id'];
                    $order_id = $_POST['order_id'] ?? '';
                    $service = $_POST['service'] ?? '';
                    $quantity = intval($_POST['quantity'] ?? 0);
                    $specifications = $_POST['specifications'] ?? '';
                    $delivery_option = $_POST['delivery_option'] ?? 'pickup';
                    $delivery_address = $_POST['delivery_address'] ?? '';
                    $paper_size = $_POST['paper_size'] ?? 'A4';
                    $photo_size = $_POST['photo_size'] ?? 'A4';
                    $color_option = $_POST['color_option'] ?? 'bw';
                    $add_lamination = isset($_POST['add_lamination']) && $_POST['add_lamination'] === 'on';
               
                    // Validation
                    if (!$order_id || !$service || $quantity < 1 || !$specifications) {
                        sendResponse(false, 'Missing or invalid fields');
                    }
               
                    if ($delivery_option === 'delivery' && empty($delivery_address)) {
                        sendResponse(false, 'Delivery address is required for delivery');
                    }
               
                    // Check if order exists, belongs to user, and is pending
                    $stmt = $conn->prepare("SELECT id, status FROM orders WHERE id = ? AND user_id = ?");
                    $stmt->execute([$order_id, $user_id]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$order) {
                        sendResponse(false, 'Order not found or you do not have permission to edit it.');
                    }
                    if ($order['status'] !== 'pending') {
                        sendResponse(false, 'Only pending orders can be edited.');
                    }
               
                    // Fetch prices
                    $stmt = $conn->prepare("
                        SELECT print_bw, print_color, photocopying, scanning, photo_development, laminating
                        FROM pricing WHERE id = 1
                    ");
                    $stmt->execute();
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row) {
                        $row = [
                            'print_bw' => 1.00,
                            'print_color' => 2.00,
                            'photocopying' => 2.00,
                            'scanning' => 5.00,
                            'photo_development' => 15.00,
                            'laminating' => 20.00
                        ];
                    }
               
                    // FIXED: Calculate new total_amount with proper multipliers
                    $service_lower = strtolower($service);
               
                    // Paper size multipliers
                    $paperMultipliers = [
                        'A4' => 1.0,
                        'Short' => 1.0,
                        'Long' => 1.2
                    ];
                    $multiplier = $paperMultipliers[$paper_size] ?? 1.0;
               
                    $servicePrice = 0.0;
               
                    switch ($service_lower) {
                        case 'print':
                            $basePrice = ($color_option === 'color') ? $row['print_color'] : $row['print_bw'];
                            $servicePrice = $basePrice * $multiplier;
                            break;
                       
                        case 'photocopy':
                            // FIXED: Use photocopying with multiplier
                            $servicePrice = $row['photocopying'] * $multiplier;
                            break;
                       
                        case 'scanning':
                            $servicePrice = $row['scanning'] * $multiplier;
                            break;
                       
                        case 'photo development':
                            $servicePrice = $row['photo_development'];
                            break;
                       
                        case 'laminating':
                            $servicePrice = $row['laminating'];
                            break;
                       
                        default:
                            $servicePrice = 0;
                    }
               
                    if ($servicePrice <= 0) {
                        sendResponse(false, 'Invalid service or price not found');
                    }
               
                    $total_amount = $servicePrice * $quantity;
               
                    if ($add_lamination && $service_lower !== 'laminating') {
                        $total_amount += ($row['laminating'] * $quantity);
                        $specifications .= "\nAdd Lamination: Yes";
                    }
               
                    // Append options to specifications if applicable
                    $specifications = trim($specifications);
                    if (in_array($service, ['Print', 'Photocopy', 'Scanning'])) {
                        $specs_parts = [$specifications];
                        $specs_parts[] = "Paper Size: {$paper_size}";
                        if ($service === 'Print') {
                            $print_type = $color_option === 'color' ? 'Color' : 'Black & White';
                            $specs_parts[] = "Print Type: {$print_type}";
                        } else if ($service === 'Photocopy') {
                            $specs_parts[] = "Copy Type: Color";
                        } else if ($service === 'Scanning') {
                            $scan_type = $color_option === 'color' ? 'Color' : 'Black & White';
                            $specs_parts[] = "Scan Type: {$scan_type}";
                        }
                        $specifications = implode("\n", $specs_parts);
                    }
               
                    if ($service_lower === 'photo development') {
                        $specs_parts = [$specifications];
                        $specs_parts[] = "Photo Size: Glossy {$photo_size}";
                        $specifications = implode("\n", $specs_parts);
                    }
               
                    // Set delivery_address to null if pickup
                    $delivery_address = ($delivery_option === 'delivery') ? $delivery_address : null;
               
                    // Handle new files upload - REPLACE if new files provided
                    $uploadedFiles = [];
                    $replaceFiles = !empty($_FILES['new_files']['name'][0]);
                    if ($replaceFiles) {
                        // Delete existing files from DB and disk
                        $stmtDel = $conn->prepare("DELETE FROM order_files WHERE order_id = ?");
                        $stmtDel->execute([$order_id]);
                        $uploadDir = __DIR__ . "/uploads/orders/{$order_id}/";
                        if (is_dir($uploadDir)) {
                            $files = glob($uploadDir . '*');
                            foreach ($files as $file) {
                                if (is_file($file)) {
                                    unlink($file);
                                }
                            }
                            rmdir($uploadDir);
                        }
                        // Recreate directory
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        // Upload new files
                        for ($i = 0; $i < count($_FILES['new_files']['name']); $i++) {
                            if ($_FILES['new_files']['error'][$i] === UPLOAD_ERR_OK) {
                                $tmp = $_FILES['new_files']['tmp_name'][$i];
                                $safe = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['new_files']['name'][$i]));
                                $target = $uploadDir . $safe;
                                if (move_uploaded_file($tmp, $target)) {
                                    $relative = "uploads/orders/{$order_id}/{$safe}";
                                    $stmtF = $conn->prepare("INSERT INTO order_files (order_id, filename, filepath, uploaded_at) VALUES (?, ?, ?, NOW())");
                                    $stmtF->execute([$order_id, $safe, $relative]);
                                    $uploadedFiles[] = $safe;
                                }
                            }
                        }
                    }
               
                    // UPDATE ORDER WITH CORRECT TOTAL_AMOUNT
                    try {
                        $conn->beginTransaction();
                        $stmt = $conn->prepare("
                            UPDATE orders
                            SET service = ?,
                                quantity = ?,
                                specifications = ?,
                                delivery_option = ?,
                                delivery_address = ?,
                                total_amount = ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $service,
                            $quantity,
                            $specifications,
                            $delivery_option,
                            $delivery_address,
                            $total_amount,
                            $order_id
                        ]);
                        $conn->commit();
                        sendResponse(true, 'Order updated successfully', [
                            'uploaded_files' => $uploadedFiles,
                            'replaced_files' => $replaceFiles,
                            'total_amount' => number_format($total_amount, 2)
                        ]);
                    } catch (Exception $e) {
                        if ($conn->inTransaction()) $conn->rollBack();
                        sendResponse(false, 'Database error: ' . $e->getMessage());
                    }
                    break;
               
                default:
                    sendResponse(false, 'Invalid action');
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            sendResponse(false, 'Error: ' . $e->getMessage());
        }
    }
   // ──────────────────────────────────────────────────────────────
   // PROFILE UPDATE – now uses the SAME toast system as orders
   // ──────────────────────────────────────────────────────────────
   if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
       if (!isset($_SESSION['user_id'])) {
           die("Unauthorized");
       }
       $user_id = $_SESSION['user_id'];
       // ---- INPUT ----
       $first_name = trim($_POST['first_name'] ?? '');
       $last_name = trim($_POST['last_name'] ?? '');
       $email = trim($_POST['email'] ?? '');
       $phone = trim($_POST['phone'] ?? '');
       $current_password = $_POST['current_password'] ?? '';
       $new_password = $_POST['new_password'] ?? '';
       $confirm_password = $_POST['confirm_password'] ?? '';
       $errors = [];
       // ---- BASIC VALIDATION ----
       if (!$first_name) $errors[] = "First name is required.";
       if (!$last_name) $errors[] = "Last name is required.";
       if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
       // FIXED: Phone validation - must be numeric only
       if (!empty($phone) && !ctype_digit($phone)) {
           $errors[] = "Phone number must contain numbers only (no letters or symbols).";
       }
       $change_password = !empty($current_password) || !empty($new_password) || !empty($confirm_password);
       if ($change_password) {
           if (!$current_password) $errors[] = "Current password is required.";
           if (!$new_password) $errors[] = "New password cannot be empty.";
           if ($new_password !== $confirm_password) $errors[] = "Passwords do not match.";
           // ADDED: Stronger password policy (optional - at least 8 chars, 1 upper, 1 number)
           if (strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
               $errors[] = "New password must be at least 8 characters with 1 uppercase and 1 number.";
           }
       }
       // ---- IF NO ERRORS → UPDATE DB ----
       if (empty($errors)) {
           try {
               $conn->beginTransaction();
             
               // FIXED: Fetch current email for uniqueness check (simplified, no unnecessary fetch)
               $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
               $stmt->execute([$user_id]);
               $current_email = $stmt->fetchColumn();
               if ($email !== $current_email) { // Only check if changed
                   $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                   $stmt->execute([$email, $user_id]);
                   if ($stmt->fetch()) {
                       throw new Exception("Email already exists. Please use a different email.");
                   }
               }
             
               // Update basic info (REMOVED: rowCount check - 0 rows is OK if no changes)
               $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?");
               $stmt->execute([$first_name, $last_name, $email, $phone, $user_id]);
             
               // FIXED: Log if no rows affected (no throw - it's normal if no changes)
               $rowsAffected = $stmt->rowCount();
               if ($rowsAffected === 0) {
                   error_log("Profile update: No rows affected for user_id=$user_id (no changes made).");
               } else {
                   error_log("Profile update: Successfully updated $rowsAffected row(s) for user_id=$user_id.");
               }
             
               // Password change (optional) - now reached even if basic has no changes
               if ($change_password) {
                   $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id=?");
                   $stmt->execute([$user_id]);
                   $hash = $stmt->fetchColumn();
                   if (!password_verify($current_password, $hash)) {
                       throw new Exception("Current password is incorrect.");
                   }
                   $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                   $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE id=?");
                   $stmt->execute([$new_hash, $user_id]);
                 
                   // Keep: Check password update rows (should always affect if provided)
                   $passRows = $stmt->rowCount();
                   if ($passRows === 0) {
                       error_log("Password update: No rows affected for user_id=$user_id.");
                       throw new Exception("Password update failed. Please try again.");
                   }
                   error_log("Password updated successfully for user_id=$user_id.");
               }
             
               $conn->commit();
               // SUCCESS → store toast data
               $_SESSION['toast_message'] = "Profile updated successfully.";
               $_SESSION['toast_type'] = "success";
           } catch (Exception $e) {
               if ($conn->inTransaction()) $conn->rollBack();
               error_log("Profile update error for user_id=$user_id: " . $e->getMessage());
               $errors[] = $e->getMessage();
           }
       }
       // ---- ERRORS → store toast data ----
       if (!empty($errors)) {
           $_SESSION['toast_message'] = implode(" | ", $errors); // Better separator
           $_SESSION['toast_type'] = "error";
       }
       // redirect back to the same page (toast will be shown by JS)
       header("Location: dashboard.php");
       exit;
   }
   // ✅ FETCH USER DATA AFTER POST PROCESSING (so it gets the updated values)
   $user_id = $_SESSION['user_id'];
   $stmt = $conn->prepare("SELECT first_name, last_name, email, phone, role FROM users WHERE id = ?");
   $stmt->execute([$user_id]);
   $user = $stmt->fetch(PDO::FETCH_ASSOC);
   // NEW: If no user found, log and redirect to login
   if (!$user) {
       error_log("User not found in DB for session user_id=$user_id. Logging out.");
       if (session_status() == PHP_SESSION_ACTIVE) {
           session_destroy();
       }
       setcookie(session_name(), '', time() - 3600, '/');
       header('Location: login.php?reason=user_not_found');
       exit;
   }
   // Check if the user data is retrieved
   $first_name = $user['first_name'];
   $last_name = $user['last_name'];
   $email = $user['email'];
   $phone = $user['phone'];
   $role = $user['role'];
   ?>
   <!DOCTYPE html>
   <html lang="en">
   <head>
       <meta charset="UTF-8" />
       <meta name="viewport" content="width=device-width, initial-scale=1.0" />
       <title>Dashboard - Jonayskie Prints</title>
       <!-- Font Awesome -->
       <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
       <!-- Tailwind CSS CDN -->
       <script src="https://cdn.tailwindcss.com"></script>
       <!-- Chart.js -->
       <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
       <!-- Custom Tailwind Config for Theme -->
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
                               900: '#1e3a8a',
                           }
                       }
                   }
               }
           }
       </script>
  
       <style>
           /* Custom media queries for mobile optimization */
           @media (max-width: 640px) {
               .price-board {
                   padding: 0.75rem !important;
               }
               .price-header {
                   font-size: 1.125rem !important;
                   margin-bottom: 0.75rem !important;
                   padding-bottom: 0.5rem !important;
               }
               .price-grid {
                   gap: 0.5rem !important;
               }
               .price-card {
                   padding: 0.5rem !important;
               }
               .price-icon {
                   width: 2rem !important;
                   height: 2rem !important;
                   margin-bottom: 0.25rem !important;
               }
               .price-icon i {
                   font-size: 0.75rem !important;
               }
               .price-name {
                   font-size: 0.75rem !important;
                   margin-bottom: 0.25rem !important;
               }
               .price-amount {
                   font-size: 1.125rem !important;
                   margin-bottom: 0.25rem !important;
               }
               .price-unit {
                   font-size: 0.625rem !important;
               }
               .stats-card {
                   padding: 0.75rem !important;
               }
               .stats-icon {
                   padding: 0.5rem !important;
               }
               .stats-icon i {
                   font-size: 1rem !important;
               }
               .stats-number {
                   font-size: 1.25rem !important;
               }
               .stats-label {
                   font-size: 0.75rem !important;
               }
               .recent-header {
                   padding: 0.75rem !important;
                   font-size: 1.125rem !important;
               }
               .table-th, .table-td {
                   padding-left: 0.5rem !important;
                   padding-right: 0.5rem !important;
                   font-size: 0.75rem !important;
               }
               .dashboard-section {
                   padding: 0.5rem !important;
               }
               .recent-orders-table {
                   font-size: 0.75rem !important;
               }
               .recent-orders-table th,
               .recent-orders-table td {
                   padding: 0.5rem !important;
               }
               .stats-grid {
                   gap: 0.75rem !important;
               }
           }
           @media (max-width: 480px) {
               .price-header {
                   font-size: 1rem !important;
               }
               .stats-number {
                   font-size: 1.125rem !important;
               }
               .recent-header {
                   font-size: 1rem !important;
                   padding: 0.5rem !important;
               }
           }
           /* Custom Toast Styles */
           .toast {
           position: fixed;
           top: 20px;
           right: 20px;
           min-width: 300px;
           background: linear-gradient(135deg, #4CAF50, #45a049);
           color: white;
           padding: 16px;
           border-radius: 8px;
           box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
           z-index: 1000;
           transform: translateX(100%);
           transition: transform 0.3s ease, opacity 0.3s ease;
           font-family: Arial, sans-serif;
           font-size: 14px;
           }
           .toast.error {
           background: linear-gradient(135deg, #f44336, #d32f2f);
           }
           .toast.show {
           transform: translateX(0);
           opacity: 1;
           }
           .toast.hidden {
           opacity: 0;
           pointer-events: none;
           }
           .toast-content {
           display: flex;
           align-items: center;
           justify-content: center;
           border-radius: 50%;
           transition: background 0.2s;
           }
           .toast-close:hover {
           background: rgba(255, 255, 255, 0.2);
           }
       </style>
   </head>
   <body class="bg-gray-50 min-h-screen">
       <!-- Toast Notification -->
       <div id="toast-notification" class="toast hidden">
       <div class="toast-content">
           <span id="toast-message"></span>
           <button class="toast-close">&times;</button>
       </div>
       </div>
       <div class="flex h-screen bg-gray-100">
           <!-- Sidebar -->
           <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out lg:static lg:inset-0">
               <div class="flex items-center justify-between p-4 bg-primary-600 text-white">
                   <div class="flex items-center space-x-2">
                       <i class="fas fa-print text-2xl"></i>
                       <span class="text-xl font-bold">Jonayskie Prints</span>
                   </div>
                   <button id="closeSidebar" class="lg:hidden text-white hover:text-gray-200">
                       <i class="fas fa-times"></i>
                   </button>
               </div>
               <nav class="mt-8 px-4">
                   <ul class="space-y-2">
                       <li><a href="#dashboard" class="nav-link flex items-center px-4 py-3 text-gray-700 bg-gray-100 rounded-lg active" data-section="dashboard">
                           <i class="fas fa-tachometer-alt mr-3 w-5"></i>
                           <span>Dashboard</span>
                       </a></li>
                       <li><a href="#new-order" class="nav-link flex items-center px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg" data-section="new-order">
                           <i class="fas fa-plus-circle mr-3 w-5"></i>
                           <span>New Order</span>
                       </a></li>
                       <li><a href="#orders" class="nav-link flex items-center px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg" data-section="orders">
                           <i class="fas fa-list-alt mr-3 w-5"></i>
                           <span>My Orders</span>
                       </a></li>
                       <li><a href="#profile" class="nav-link flex items-center px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg" data-section="profile">
                           <i class="fas fa-user mr-3 w-5"></i>
                           <span>Profile</span>
                       </a></li>
                   </ul>
               </nav>
  
               <div class="absolute bottom-0 w-full p-4 bg-gray-50 border-t">
                   <a href="logout.php" class="flex items-center text-red-600 hover:text-red-800">
                       <i class="fas fa-sign-out-alt mr-3 w-5"></i>
                       <span>Logout</span>
                   </a>
               </div>
           </aside>
           <!-- Mobile sidebar overlay -->
           <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden"></div>
           <!-- Main Content -->
           <div class="flex-1 flex flex-col overflow-hidden lg:ml-0">
               <!-- Header -->
               <header class="bg-white shadow-sm border-b">
                   <div class="px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                       <div class="flex items-center space-x-4">
                           <button id="sidebarToggle" class="lg:hidden text-gray-500 hover:text-gray-700">
                               <i class="fas fa-bars text-xl"></i>
                           </button>
                           <h1 id="pageTitle" class="text-2xl font-bold text-gray-900">Dashboard</h1>
                       </div>
                       <div class="flex items-center space-x-4">
                           <span class="text-sm text-gray-700">Welcome, <strong class="text-gray-900"><?php echo htmlspecialchars($first_name); ?></strong></span>
                           <div class="w-8 h-8 bg-primary-500 rounded-full flex items-center justify-center text-white font-semibold">
                               <i class="fas fa-user-circle text-sm"></i>
                           </div>
                       </div>
                   </div>
               </header>
               <!-- Content Area -->
               <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-2 sm:p-4 md:p-6">
                   <div class="max-w-7xl mx-auto">
                       <!-- Dashboard Section -->
                       <section id="dashboard-section" class="content-section space-y-2 sm:space-y-4 md:space-y-6">
                           <!-- Price Board -->
                           <div class="bg-gradient-to-r from-blue-600 to-purple-700 rounded-lg p-1 sm:p-2 md:p-4 text-white shadow-lg price-board">
                               <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-1 sm:mb-2 md:mb-3 pb-0.5 sm:pb-1 md:pb-2 border-b border-white/30">
                                   <h2 class="flex items-center space-x-1 sm:space-x-2 text-sm sm:text-base md:text-lg font-bold price-header">
                                       <i class="fas fa-tags text-xs"></i>
                                       <span>Current Pricing</span>
                                   </h2>
                                   <div class="flex items-center space-x-0.5 sm:space-x-1 mt-0.5 sm:mt-1 lg:mt-0 flex-wrap">
                                       <div class="flex items-center space-x-0.5 sm:space-x-1 bg-white/20 px-1 sm:px-1.5 py-0.5 rounded-full text-xs">
                                           <i class="fas fa-sync-alt text-xs"></i>
                                           <span id="priceUpdateTime" class="text-xs">Loading...</span>
                                       </div>
                                       <button id="refreshPrices" class="flex items-center space-x-0.5 sm:space-x-1 bg-white/20 px-1 sm:px-1.5 py-0.5 rounded-full text-xs hover:bg-white/30 transition-colors ml-0.5 sm:ml-0">
                                           <i class="fas fa-sync text-xs"></i>
                                           <span class="hidden sm:inline">Refresh</span>
                                       </button>
                                   </div>
                               </div>
                               <div id="priceItems" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2 sm:gap-3 md:gap-4 price-grid">
                                   <div class="flex items-center justify-center py-1.5 sm:py-2 md:py-3 text-white/70">
                                       <i class="fas fa-spinner fa-spin text-sm sm:text-base md:text-lg"></i>
                                       <span class="ml-0.5 sm:ml-1 text-xs">Loading prices...</span>
                                   </div>
                               </div>
                           </div>
              
                           <!-- Stats Grid -->
                           <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-4 md:gap-6 stats-grid">
                               <div class="bg-white rounded-xl p-3 sm:p-4 md:p-6 stats-card shadow-md hover:shadow-lg transition-shadow">
                                   <div class="flex items-center justify-between">
                                       <div class="p-2 sm:p-3 md:p-3 bg-blue-100 rounded-lg stats-icon">
                                           <i class="fas fa-shopping-cart text-blue-600 text-lg sm:text-xl"></i>
                                       </div>
                                       <div class="text-right">
                                           <h3 id="totalOrders" class="text-xl sm:text-2xl md:text-2xl font-bold text-gray-900 stats-number">0</h3>
                                           <p class="text-xs sm:text-sm text-gray-600 stats-label">Total Orders</p>
                                       </div>
                                   </div>
                               </div>
                               <div class="bg-white rounded-xl p-3 sm:p-4 md:p-6 stats-card shadow-md hover:shadow-lg transition-shadow">
                                   <div class="flex items-center justify-between">
                                       <div class="p-2 sm:p-3 md:p-3 bg-yellow-100 rounded-lg stats-icon">
                                           <i class="fas fa-clock text-yellow-600 text-lg sm:text-xl"></i>
                                       </div>
                                       <div class="text-right">
                                           <h3 id="pendingOrders" class="text-xl sm:text-2xl md:text-2xl font-bold text-gray-900 stats-number">0</h3>
                                           <p class="text-xs sm:text-sm text-gray-600 stats-label">Pending Orders</p>
                                       </div>
                                   </div>
                               </div>
                               <div class="bg-white rounded-xl p-3 sm:p-4 md:p-6 stats-card shadow-md hover:shadow-lg transition-shadow">
                                   <div class="flex items-center justify-between">
                                       <div class="p-2 sm:p-3 md:p-3 bg-green-100 rounded-lg stats-icon">
                                           <i class="fas fa-check-circle text-green-600 text-lg sm:text-xl"></i>
                                       </div>
                                       <div class="text-right">
                                           <h3 id="completedOrders" class="text-xl sm:text-2xl md:text-2xl font-bold text-gray-900 stats-number">0</h3>
                                           <p class="text-xs sm:text-sm text-gray-600 stats-label">Completed Orders</p>
                                       </div>
                                   </div>
                               </div>
                               <div class="bg-white rounded-xl p-3 sm:p-4 md:p-6 stats-card shadow-md hover:shadow-lg transition-shadow">
                                   <div class="flex items-center justify-between">
                                       <div class="p-2 sm:p-3 md:p-3 bg-purple-100 rounded-lg stats-icon">
                                           <i class="fas fa-peso-sign text-purple-600 text-lg sm:text-xl"></i>
                                       </div>
                                       <div class="text-right">
                                           <h3 id="totalSpent" class="text-xl sm:text-2xl md:text-2xl font-bold text-gray-900 stats-number">₱0.00</h3>
                                           <p class="text-xs sm:text-sm text-gray-600 stats-label">Total Spent</p>
                                       </div>
                                   </div>
                               </div>
                           </div>
                           <!-- Recent Orders -->
                           <div class="bg-white rounded-xl shadow-md overflow-hidden">
                               <div class="p-3 sm:p-4 md:p-6 recent-header border-b">
                                   <h2 class="text-lg sm:text-xl font-bold text-gray-900">Recent Orders</h2>
                               </div>
                               <div class="overflow-x-auto">
                                   <table class="min-w-full divide-y divide-gray-200 recent-orders-table">
                                       <thead class="bg-gray-50">
                                           <tr>
                                               <th class="px-2 sm:px-3 md:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider table-th">Order ID</th>
                                               <th class="px-2 sm:px-3 md:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider table-th">Service</th>
                                               <th class="px-2 sm:px-3 md:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider table-th">Date</th>
                                               <th class="px-2 sm:px-3 md:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider table-th">Status</th>
                                               <th class="px-2 sm:px-3 md:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider table-th">Amount</th>
                                           </tr>
                                       </thead>
                                       <tbody id="recentOrdersTable" class="bg-white divide-y divide-gray-200">
                                           <tr>
                                               <td colspan="5" class="px-3 sm:px-6 py-8 md:py-12 text-center text-gray-500 text-sm">No orders yet</td>
                                           </tr>
                                       </tbody>
                                   </table>
                               </div>
                           </div>
                       </section>
                       <!-- New Order Section -->
                       <section id="new-order-section" class="content-section hidden space-y-6">
                           <div class="bg-white rounded-xl shadow-md p-6">
                               <h2 class="text-2xl font-bold text-gray-900 mb-6">Create New Order</h2>
                               <form id="newOrderForm" class="space-y-6" enctype="multipart/form-data" novalidate>
                                   <!-- Step 1: Service Selection -->
                                   <div id="step-1" class="step active">
                                       <h3 class="text-lg font-semibold text-gray-900 mb-4">Step 1: Select Service & Basics</h3>
                                       <div class="form-group">
                                           <label for="serviceDropdown" class="block text-sm font-medium text-gray-700 mb-2">Select Service</label>
                                           <select id="serviceDropdown" name="service" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" required>
                                               <option value="">-- Select Service --</option>
                                           </select>
                                       </div>
                                       <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                           <div class="form-group">
                                               <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                                               <input type="number" id="quantity" name="quantity" min="1" value="1" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                                           </div>
                                           <div class="form-group">
                                               <label class="block text-sm font-medium text-gray-700 mb-2">Delivery Option</label>
                                               <div class="flex flex-wrap space-x-4 sm:space-x-6 gap-2">
                                                   <label class="flex items-center space-x-2">
                                                       <input type="radio" name="delivery_option" value="pickup" checked class="rounded border-gray-300">
                                                       <span class="text-sm text-gray-700">Pickup</span>
                                                   </label>
                                                   <label class="flex items-center space-x-2">
                                                       <input type="radio" name="delivery_option" value="delivery" class="rounded border-gray-300">
                                                       <span class="text-sm text-gray-700">Delivery</span>
                                                   </label>
                                               </div>
                                           </div>
                                       </div>
                                       <div id="deliveryAddressGroup" class="form-group hidden">
                                           <label for="delivery_address" class="block text-sm font-medium text-gray-700 mb-2">Delivery Address</label>
                                           <textarea id="delivery_address" name="delivery_address" rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="Enter your complete delivery address"></textarea>
                                       </div>
                                       <div class="flex justify-end space-x-4">
                                           <button type="button" id="next-step-1" class="px-6 py-2.5 bg-primary-600 text-white font-semibold rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:outline-none">Next</button>
                                       </div>
                                   </div>
                                   <!-- Step 2: Specifications & Options -->
                                   <div id="step-2" class="step hidden">
                                       <h3 class="text-lg font-semibold text-gray-900 mb-4">Step 2: Specifications & Options</h3>
                                       <div id="paperSizeGroup" class="form-group hidden">
                                           <label for="paper_size" class="block text-sm font-medium text-gray-700 mb-2">Paper Size</label>
                                           <select id="paper_size" name="paper_size" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                               <option value="A4">A4</option>
                                               <option value="Short">Short</option>
                                               <option value="Long">Long</option>
                                           </select>
                                       </div>
                                       <div id="photoSizeGroup" class="form-group hidden">
                                           <label for="photo_size" class="block text-sm font-medium text-gray-700 mb-2">Photo Size</label>
                                           <select id="photo_size" name="photo_size" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                               <option value="A4">Glossy A4</option>
                                               <option value="4x6">Glossy 4x6</option>
                                           </select>
                                       </div>
                                       <div id="colorOptionGroup" class="form-group hidden">
                                           <label class="block text-sm font-medium text-gray-700 mb-2">Print Type</label>
                                           <div class="flex space-x-6">
                                               <label class="flex items-center space-x-2">
                                                   <input type="radio" name="color_option" value="bw" checked class="rounded border-gray-300">
                                                   <span class="text-sm text-gray-700">Black & White</span>
                                               </label>
                                               <label class="flex items-center space-x-2">
                                                   <input type="radio" name="color_option" value="color" class="rounded border-gray-300">
                                                   <span class="text-sm text-gray-700">Color</span>
                                               </label>
                                           </div>
                                       </div>
                                       <div id="laminationGroup" class="form-group hidden">
                                           <label class="flex items-center space-x-2">
                                               <input type="checkbox" name="add_lamination" id="addLamination" class="rounded border-gray-300">
                                               <span class="text-sm text-gray-700">Add Lamination (₱<span id="lamPrice">20.00</span> extra per item)</span>
                                           </label>
                                       </div>
                                       <div class="form-group">
                                           <label for="specifications" class="block text-sm font-medium text-gray-700 mb-2">Specifications</label>
                                           <textarea id="specifications" name="specifications" rows="4" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="Describe your order details or type N/A if none." required></textarea>
                                       </div>
                                       <div class="flex justify-between">
                                           <button type="button" id="prev-step-2" class="px-6 py-2.5 bg-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-400">Previous</button>
                                           <button type="button" id="next-step-2" class="px-6 py-2.5 bg-primary-600 text-white font-semibold rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:outline-none">Next</button>
                                       </div>
                                   </div>
                                   <!-- Step 3: Files & Summary -->
                                   <div id="step-3" class="step hidden">
                                       <h3 class="text-lg font-semibold text-gray-900 mb-4">Step 3: Upload Files & Review</h3>
                                       <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                                           <div class="form-group">
                                               <label for="orderFiles" class="block text-sm font-medium text-gray-700 mb-2">Upload Files <span class="text-gray-500 text-xs">(Optional for Scanning/Laminating)</span></label>
                                               <input type="file" id="orderFiles" name="files[]" multiple class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                               <div class="file-list mt-2 space-y-1" id="fileList"></div>
                                           </div>
                                           <div class="form-group">
                                               <p class="text-sm font-medium text-blue-600">Payment Cash Only</p>
                                           </div>
                                       </div>
                                       <div class="bg-gray-50 p-4 rounded-lg">
                                           <h4 class="font-semibold text-gray-900 mb-3">Order Summary</h4>
                                           <div class="space-y-2 text-sm">
                                               <div class="flex justify-between"><span>Service:</span><span id="summaryService">-</span></div>
                                               <div class="flex justify-between"><span>Quantity:</span><span id="summaryQuantity">1</span></div>
                                               <div class="flex justify-between"><span>Delivery:</span><span id="summaryDelivery">Pickup</span></div>
                                               <div class="flex justify-between"><span>Paper Size:</span><span id="summaryPaper">A4</span></div>
                                               <div class="flex justify-between"><span>Print Type:</span><span id="summaryColor">Black & White</span></div>
                                               <div class="flex justify-between"><span>Lamination:</span><span id="summaryLamination">No</span></div>
                                               <div class="flex justify-between font-bold text-lg border-t pt-2 mt-2"><span>Total Price:</span><span id="summaryPrice" class="text-primary-600">₱0.00</span></div>
                                           </div>
                                       </div>
                                       <div class="flex justify-between pt-4 border-t">
                                           <button type="button" id="prev-step-3" class="px-6 py-2.5 bg-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-400">Previous</button>
                                           <button type="submit" id="submitOrder" class="px-6 py-2.5 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:outline-none">Place Order</button>
                                       </div>
                                   </div>
                               </form>
                           </div>
                       </section>
                       <!-- Orders Section -->
                       <section id="orders-section" class="content-section hidden space-y-6">
                           <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                               <h2 class="text-2xl font-bold text-gray-900">My Orders</h2>
                               <select id="filterStatus" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 w-full sm:w-auto">
                                   <option value="">All</option>
                                   <option value="pending">Pending</option>
                                   <option value="completed">Completed</option>
                               </select>
                           </div>
                           <div id="ordersList" class="bg-white rounded-xl shadow-md overflow-hidden">
                               <p class="p-6 text-center text-gray-500">Loading orders...</p>
                           </div>
                       </section>
                       <!-- Profile Section -->
                       <section id="profile-section" class="content-section hidden space-y-6">
                           <div class="bg-white rounded-xl shadow-md p-6">
                               <!-- ====================== PROFILE FORM (replace the old one) ====================== -->
                               <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 max-w-4xl mx-auto">
                                   <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-4 sm:mb-6">Update Your Profile</h2>
                                   <form method="POST" action="dashboard.php" class="space-y-4 sm:space-y-6 text-sm sm:text-base" id="profileForm">
                                       <input type="hidden" name="update_profile" value="1" />
                                       <!-- ==== BASIC INFO – 1 column on mobile, 2 on md+ ==== -->
                                       <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-6">
                                           <div class="form-group">
                                               <label for="first_name" class="block font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                                               <input type="text" id="first_name" name="first_name"
                                                      value="<?php echo htmlspecialchars($first_name); ?>" required
                                                      class="w-full p-2 sm:p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" />
                                           </div>
                                           <div class="form-group">
                                               <label for="last_name" class="block font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                                               <input type="text" id="last_name" name="last_name"
                                                      value="<?php echo htmlspecialchars($last_name); ?>" required
                                                      class="w-full p-2 sm:p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" />
                                           </div>
                                           <div class="form-group">
                                               <label for="email" class="block font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                                               <input type="email" id="email" name="email"
                                                      value="<?php echo htmlspecialchars($email); ?>" required
                                                      class="w-full p-2 sm:p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" />
                                           </div>
                                           <div class="form-group">
                                               <label for="phone" class="block font-medium text-gray-700 mb-1">Phone</label>
                                               <input type="tel" id="phone" name="phone"
                                                      value="<?php echo htmlspecialchars($phone); ?>"
                                                      pattern="[0-9]*"
                                                      title="Phone number must contain numbers only (e.g., 09123456789)"
                                                      maxlength="15"
                                                      class="w-full p-2 sm:p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" />
                                           </div>
                                       </div>
                                       <hr class="border-gray-200">
                                       <h3 class="font-semibold text-gray-900 text-base sm:text-lg">Change Password (Optional)</h3>
                                       <p class="text-sm text-gray-600 mb-3">Leave blank if you don't want to change your password.</p>
                                       <!-- ==== PASSWORD FIELDS – 1 column on mobile, 3 on md+ ==== -->
                                       <div class="grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-6">
                                           <!-- Current Password -->
                                           <div class="form-group relative">
                                               <label for="current_password" class="block font-medium text-gray-700 mb-1">Current Password</label>
                                               <div class="relative">
                                                   <input type="password" id="current_password" name="current_password"
                                                          placeholder="Enter current password"
                                                          class="w-full p-2 sm:p-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 password-field" />
                                                   <button type="button" tabindex="-1"
                                                           class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700"
                                                           onclick="togglePassword(this, 'current_password')">
                                                       <i class="fas fa-eye"></i>
                                                   </button>
                                               </div>
                                           </div>
                                           <!-- New Password -->
                                           <div class="form-group relative">
                                               <label for="new_password" class="block font-medium text-gray-700 mb-1">New Password</label>
                                               <div class="relative">
                                                   <input type="password" id="new_password" name="new_password"
                                                          placeholder="Enter new password"
                                                          class="w-full p-2 sm:p-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 password-field" />
                                                   <button type="button" tabindex="-1"
                                                           class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700"
                                                           onclick="togglePassword(this, 'new_password')">
                                                       <i class="fas fa-eye"></i>
                                                   </button>
                                               </div>
                                           </div>
                                           <!-- Confirm New Password -->
                                           <div class="form-group relative">
                                               <label for="confirm_password" class="block font-medium text-gray-700 mb-1">Confirm New Password</label>
                                               <div class="relative">
                                                   <input type="password" id="confirm_password" name="confirm_password"
                                                          placeholder="Confirm new password"
                                                          class="w-full p-2 sm:p-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 password-field" />
                                                   <button type="button" tabindex="-1"
                                                           class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700"
                                                           onclick="togglePassword(this, 'confirm_password')">
                                                       <i class="fas fa-eye"></i>
                                                   </button>
                                               </div>
                                           </div>
                                       </div>
                                       <div id="profileErrors" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mt-4"></div>
                                       <button type="submit"
                                               class="w-full sm:w-auto px-6 py-2.5 bg-primary-600 text-white font-semibold rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                           Update Profile
                                       </button>
                                   </form>
                               </div>
                           </div>
                       </section>
                   </div>
               </main>
           </div>
       </div>
       <!-- Edit Order Modal -->
      <!-- Edit Order Modal -->
       <div id="editOrderModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4 overflow-y-auto">
           <div class="bg-white p-4 sm:p-6 rounded-lg w-full max-w-2xl mx-auto my-8 max-h-[90vh] overflow-y-auto">
               <div class="flex justify-between items-center mb-4 sticky top-0 bg-white pb-4 border-b">
                   <h3 class="text-lg sm:text-xl font-bold text-gray-900">Edit Order</h3>
                   <button id="closeEditModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
               </div>
               <form id="editOrderForm" class="space-y-4 sm:space-y-5" enctype="multipart/form-data">
                   <input type="hidden" name="order_id" id="editOrderId">
  
                   <div class="form-group">
                       <label for="editService" class="block text-sm font-medium text-gray-700 mb-2">Service:</label>
                       <select id="editService" name="service" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                           <option value="">-- Select Service --</option>
                       </select>
                   </div>
                   <div class="form-group">
                       <label class="block text-sm font-medium text-gray-700 mb-2">Delivery Option</label>
                       <div class="flex space-x-6">
                           <label class="flex items-center space-x-2">
                               <input type="radio" name="delivery_option" value="pickup" class="rounded border-gray-300">
                               <span class="text-sm text-gray-700">Pickup</span>
                           </label>
                           <label class="flex items-center space-x-2">
                               <input type="radio" name="delivery_option" value="delivery" class="rounded border-gray-300">
                               <span class="text-sm text-gray-700">Delivery</span>
                           </label>
                       </div>
                   </div>
                   <div id="editDeliveryAddressGroup" class="form-group hidden">
                       <label for="editAddress" class="block text-sm font-medium text-gray-700 mb-2">Delivery Address:</label>
                       <textarea id="editAddress" name="delivery_address" rows="2" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                   </div>
  
                   <div class="form-group">
                       <label for="editQuantity" class="block text-sm font-medium text-gray-700 mb-2">Quantity:</label>
                       <input type="number" id="editQuantity" name="quantity" min="1" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" />
                   </div>
  
                   <div id="editPaperSizeGroup" class="form-group hidden">
                       <label for="edit_paper_size" class="block text-sm font-medium text-gray-700 mb-2">Paper Size:</label>
                       <select id="edit_paper_size" name="paper_size" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                           <option value="A4">A4</option>
                           <option value="Short">Short</option>
                           <option value="Long">Long</option>
                       </select>
                   </div>
                   <div id="editPhotoSizeGroup" class="form-group hidden">
                       <label for="edit_photo_size" class="block text-sm font-medium text-gray-700 mb-2">Photo Size:</label>
                       <select id="edit_photo_size" name="photo_size" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                           <option value="A4">Glossy A4</option>
                           <option value="4x6">Glossy 4x6</option>
                       </select>
                   </div>
  
                   <div id="editColorOptionGroup" class="form-group hidden">
                       <label class="block text-sm font-medium text-gray-700 mb-2">Print Type:</label>
                       <div class="flex space-x-6">
                           <label class="flex items-center space-x-2">
                               <input type="radio" name="color_option" value="bw" class="rounded border-gray-300">
                               <span class="text-sm text-gray-700">Black & White</span>
                           </label>
                           <label class="flex items-center space-x-2">
                               <input type="radio" name="color_option" value="color" class="rounded border-gray-300">
                               <span class="text-sm text-gray-700">Color</span>
                           </label>
                       </div>
                   </div>
                   <div id="editLaminationGroup" class="form-group hidden">
                       <label class="flex items-center space-x-2">
                           <input type="checkbox" name="add_lamination" id="editAddLamination" class="rounded border-gray-300">
                           <span class="text-sm text-gray-700">Add Lamination (₱<span id="editLamPrice">20.00</span> extra per item)</span>
                       </label>
                   </div>
  
                   <div class="form-group">
                       <label for="editSpecifications" class="block text-sm font-medium text-gray-700 mb-2">Specifications:</label>
                       <textarea id="editSpecifications" name="specifications" rows="3" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                   </div>
                   <div class="form-group">
                       <label for="editNewFiles" class="block text-sm font-medium text-gray-700 mb-2">Replace Files (Optional):</label>
                       <input type="file" id="editNewFiles" name="new_files[]" multiple class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                       <p class="text-xs text-gray-500 mt-1">Uploading new files will replace the existing ones.</p>
                   </div>
  
                   <button type="submit" class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">Save Changes</button>
               </form>
           </div>
       </div>
       <script>
           // Toast Notification Function
           function showToast(message, isError = false) {
               const toast = document.getElementById('toast-notification');
               const messageEl = document.getElementById('toast-message');
               const closeBtn = document.querySelector('.toast-close');
               if (!toast || !messageEl) return;
               // Set message and class
               messageEl.textContent = message;
               toast.classList.remove('error', 'hidden');
               if (isError) {
                   toast.classList.add('error');
               }
               toast.classList.add('show');
               // Auto-hide after 5 seconds
               setTimeout(() => {
                   toast.classList.remove('show');
                   setTimeout(() => {
                       toast.classList.add('hidden');
                       if (isError) toast.classList.remove('error');
                   }, 300); // Match transition duration
               }, 5000);
               // Close on button click
               closeBtn.onclick = () => {
                   toast.classList.remove('show');
                   setTimeout(() => {
                       toast.classList.add('hidden');
                       if (isError) toast.classList.remove('error');
                   }, 300);
               };
           }
           // CRITICAL FIX: Define showSection globally to avoid undefined errors
           async function showSection(name) {
               const sections = {
                   dashboard: document.getElementById('dashboard-section'),
                   'new-order': document.getElementById('new-order-section'),
                   orders: document.getElementById('orders-section'),
                   profile: document.getElementById('profile-section')
               };
               Object.values(sections).forEach(section => {
                   if (section) section.classList.add('hidden');
               });
               const targetSection = sections[name];
               if (targetSection) {
                   targetSection.classList.remove('hidden');
               }
               const navLinks = document.querySelectorAll('.nav-link');
               navLinks.forEach(link => {
                   link.classList.remove('bg-primary-600', 'text-white', 'bg-gray-100', 'text-gray-700', 'active');
                   if (link.dataset.section === name) {
                       link.classList.add('bg-primary-600', 'text-white', 'active');
                   } else {
                       link.classList.add('bg-gray-100', 'text-gray-700');
                   }
               });
               const pageTitle = document.getElementById('pageTitle');
               if (pageTitle) {
                   const title = name.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase());
                   pageTitle.textContent = title;
               }
               if (window.innerWidth <= 1024) {
                   const sidebar = document.getElementById('sidebar');
                   const sidebarOverlay = document.getElementById('sidebarOverlay');
                   if (sidebar) sidebar.classList.add('-translate-x-full');
                   if (sidebarOverlay) sidebarOverlay.classList.add('hidden');
               }
               // Load data for section
               if (name === 'dashboard') {
                   loadDashboardData();
                   refreshPrices(); // FIXED: Auto-refresh prices when switching to dashboard
               } else if (name === 'orders') {
                   loadOrders();
               } else if (name === 'new-order') {
                   // REMOVED: await loadServices(); // This was resetting the dropdown!
                   updateSummary(); // Just refresh summary with existing global prices
               }
           }
           // API base
           const API_BASE = 'dashboard.php';
           // Service icons
           const serviceIcons = {
               print: 'fa-print',
               photocopy: 'fa-copy',
               scanning: 'fa-scanner',
               'photo development': 'fa-camera',
               laminating: 'fa-layer-group'
           };
           const serviceUnits = {
               print: 'per page',
               photocopy: 'per page',
               scanning: 'per page',
               'photo development': 'per photo',
               laminating: 'per item'
           };
           // Default prices for fallback - FIXED: Added photocopying
           const defaultPrices = {
               print_bw: 1.00,
               print_color: 2.00,
               photocopying: 2.00,
               scanning: 5.00,
               photo_development: 15.00,
               laminating: 20.00
           };
           // Initial section variable - FIXED: Check for toast_message instead of old keys
           var initialSection = 'dashboard';
           <?php if (isset($_SESSION['toast_message'])): ?>
           initialSection = 'profile';
           <?php endif; ?>
           document.addEventListener('DOMContentLoaded', function() {
               const sidebar = document.getElementById('sidebar');
               const sidebarToggle = document.getElementById('sidebarToggle');
               const closeSidebar = document.getElementById('closeSidebar');
               const sidebarOverlay = document.getElementById('sidebarOverlay');
               const navLinks = document.querySelectorAll('.nav-link');
               const pageTitle = document.getElementById('pageTitle');
               // Sidebar toggle
               if (sidebarToggle) {
                   sidebarToggle.addEventListener('click', () => {
                       if (sidebar) sidebar.classList.remove('-translate-x-full');
                       if (sidebarOverlay) sidebarOverlay.classList.remove('hidden');
                   });
               }
               if (closeSidebar) {
                   closeSidebar.addEventListener('click', () => {
                       if (sidebar) sidebar.classList.add('-translate-x-full');
                       if (sidebarOverlay) sidebarOverlay.classList.add('hidden');
                   });
               }
               if (sidebarOverlay) {
                   sidebarOverlay.addEventListener('click', () => {
                       if (sidebar) sidebar.classList.add('-translate-x-full');
                       if (sidebarOverlay) sidebarOverlay.classList.add('hidden');
                   });
               }
               // Navigation event listeners
               navLinks.forEach(link => {
                   link.addEventListener('click', async (e) => {
                       e.preventDefault();
                       await showSection(link.dataset.section);
                   });
               });
               // Window resize
               window.addEventListener('resize', () => {
                   if (window.innerWidth > 1024) {
                       if (sidebar) sidebar.classList.remove('-translate-x-full');
                       if (sidebarOverlay) sidebarOverlay.classList.add('hidden');
                   } else {
                       if (sidebar) sidebar.classList.add('-translate-x-full');
                   }
               });
               // Initial loads
               loadServices();
               loadDashboardData();
               // Show initial section AFTER everything is set up
               setTimeout(async () => {
                   await showSection(initialSection);
               }, 0);
               // Refresh button for prices
               const refreshBtn = document.getElementById('refreshPrices');
               if (refreshBtn) {
                   refreshBtn.addEventListener('click', async () => {
                       await refreshPrices();
                       // Rotate the icon
                       const icon = refreshBtn.querySelector('i');
                       icon.classList.add('fa-spin');
                       setTimeout(() => icon.classList.remove('fa-spin'), 1000);
                       if (document.getElementById('new-order-section') && !document.getElementById('new-order-section').classList.contains('hidden')) {
                           updateSummary();
                       }
                   });
               }
               // Event delegation for edit buttons (new approach)
               document.addEventListener('click', async (e) => {
                   if (e.target.classList.contains('edit-btn')) {
                       console.log('Edit button clicked'); // DEBUG
                       const orderId = e.target.dataset.orderId;
                       if (!orderId) {
                           console.error('No order ID found');
                           return;
                       }
                  
                       const order = await fetchOrderDetails(orderId);
                       if (order) {
                           populateEditModal(order);
                       }
                   }
               });
               // FIXED: Restrict phone input to numbers only
               const phoneInput = document.getElementById('phone');
               if (phoneInput) {
                   phoneInput.addEventListener('input', function(e) {
                       // Allow only digits
                       e.target.value = e.target.value.replace(/[^0-9]/g, '');
                   });
                   phoneInput.addEventListener('keydown', function(e) {
                       // Block non-digit keys (except control keys like backspace, delete, tab, arrows)
                       const allowedKeys = ['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'Home', 'End', 'ArrowLeft', 'ArrowRight'];
                       if (!allowedKeys.includes(e.key) && (e.key.length === 1 && !/[0-9]/.test(e.key))) {
                           e.preventDefault();
                       }
                   });
               }
               // NEW: Client-side validation for profile form
               const profileForm = document.getElementById('profileForm');
               const profileErrors = document.getElementById('profileErrors');
               if (profileForm) {
                   profileForm.addEventListener('submit', function(e) {
                       let errors = [];
                       // Basic fields
                       if (!document.getElementById('first_name').value.trim()) errors.push('First name is required.');
                       if (!document.getElementById('last_name').value.trim()) errors.push('Last name is required.');
                       if (!document.getElementById('email').value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(document.getElementById('email').value.trim())) errors.push('Valid email is required.');
                       // Password logic
                       const currentPass = document.getElementById('current_password').value;
                       const newPass = document.getElementById('new_password').value;
                       const confirmPass = document.getElementById('confirm_password').value;
                       if (currentPass || newPass || confirmPass) {
                           if (!currentPass) errors.push('Current password is required if changing.');
                           if (!newPass) errors.push('New password cannot be empty.');
                           if (newPass !== confirmPass) errors.push('Passwords do not match.');
                           if (newPass && (newPass.length < 8 || !/[A-Z]/.test(newPass) || !/[0-9]/.test(newPass))) {
                               errors.push('New password must be at least 8 characters with 1 uppercase and 1 number.');
                           }
                       }
                       if (errors.length > 0) {
                           e.preventDefault();
                           if (profileErrors) {
                               profileErrors.innerHTML = `<ul class="list-disc list-inside text-sm">${errors.map(err => `<li>${err}</li>`).join('')}</ul>`;
                               profileErrors.classList.remove('hidden');
                           }
                           showToast(errors[0], true);
                           return false;
                       }
                       // Hide errors
                       if (profileErrors) profileErrors.classList.add('hidden');
                   });
               }
           });
           // Load Services (initial load - populates dropdown) - FIXED: Use fetch_pricing.php for display prices
           async function loadServices() {
               try {
                   const ts = new Date().getTime(); // Cache-buster
                   const response = await fetch(`fetch_pricing.php?t=${ts}`);
                   const prices = await response.json();
                   window.prices = prices; // Make global for calculations
                   // Dropdown services (for form) - FIXED: Added Laminating
                   const dropdownServices = [
                       { name: 'Print', price: 0, description: '', iconKey: 'print', unit: 'per page' },
                       { name: 'Photocopy', price: prices.photocopying, description: '', iconKey: 'photocopy', unit: 'per page' },
                       { name: 'Scanning', price: prices.scanning, description: '', iconKey: 'scanning', unit: 'per page' },
                       { name: 'Photo Development', price: prices.photo_development, description: '', iconKey: 'photo development', unit: 'per photo' },
                       { name: 'Laminating', price: prices.laminating, description: '', iconKey: 'laminating', unit: 'per item' }
                   ];
                   // Display services (for price board) - FIXED: Use photocopying for Photocopy
                   const displayServices = [
                       { name: 'Print B&W', price: prices.print_bw, description: 'Black and white printing per page', iconKey: 'print', unit: 'per page' },
                       { name: 'Print Color', price: prices.print_color, description: 'Color printing per page', iconKey: 'print', unit: 'per page' },
                       { name: 'Photocopy', price: prices.photocopying, description: 'Color photocopying per page', iconKey: 'photocopy', unit: 'per page' },
                       { name: 'Scanning', price: prices.scanning, description: 'Document scanning per page', iconKey: 'scanning', unit: 'per page' },
                       { name: 'Photo Development', price: prices.photo_development, description: 'Film photo development per photo', iconKey: 'photo development', unit: 'per photo' },
                       { name: 'Laminating', price: prices.laminating, description: 'Laminating service per item', iconKey: 'laminating', unit: 'per item' }
                   ];
                   window.serviceList = dropdownServices; // for dropdown
                   displayPrices(displayServices);
                   populateServiceDropdown(dropdownServices, prices);
                   populateEditServiceDropdown(dropdownServices);
                   updatePriceElements(prices); // Update lam prices
               } catch (error) {
                   console.error('Error loading services:', error);
                   // Use defaults on error - FIXED: Added photocopying
                   const prices = defaultPrices;
                   window.prices = prices;
                   const dropdownServices = [
                       { name: 'Print', price: 0, description: '', iconKey: 'print', unit: 'per page' },
                       { name: 'Photocopy', price: defaultPrices.photocopying, description: '', iconKey: 'photocopy', unit: 'per page' },
                       { name: 'Scanning', price: defaultPrices.scanning, description: '', iconKey: 'scanning', unit: 'per page' },
                       { name: 'Photo Development', price: defaultPrices.photo_development, description: '', iconKey: 'photo development', unit: 'per photo' },
                       { name: 'Laminating', price: defaultPrices.laminating, description: '', iconKey: 'laminating', unit: 'per item' }
                   ];
                   const displayServices = [
                       { name: 'Print B&W', price: defaultPrices.print_bw, description: 'Black and white printing per page', iconKey: 'print', unit: 'per page' },
                       { name: 'Print Color', price: defaultPrices.print_color, description: 'Color printing per page', iconKey: 'print', unit: 'per page' },
                       { name: 'Photocopy', price: defaultPrices.photocopying, description: 'Color photocopying per page', iconKey: 'photocopy', unit: 'per page' },
                       { name: 'Scanning', price: defaultPrices.scanning, description: 'Document scanning per page', iconKey: 'scanning', unit: 'per page' },
                       { name: 'Photo Development', price: defaultPrices.photo_development, description: 'Film photo development per photo', iconKey: 'photo development', unit: 'per photo' },
                       { name: 'Laminating', price: defaultPrices.laminating, description: 'Laminating service per item', iconKey: 'laminating', unit: 'per item' }
                   ];
                   window.serviceList = dropdownServices;
                   displayPrices(displayServices);
                   populateServiceDropdown(dropdownServices, defaultPrices);
                   populateEditServiceDropdown(dropdownServices);
                   updatePriceElements(defaultPrices);
               }
           }
           // Refresh Prices (for interval/refresh - does NOT repopulate dropdown) - FIXED: Use fetch_pricing.php
           async function refreshPrices() {
               try {
                   const ts = new Date().getTime(); // Cache-buster
                   const response = await fetch(`fetch_pricing.php?t=${ts}`);
                   const prices = await response.json();
                   console.log('Fetched prices from DB:', prices); // DEBUG: Check fetched data
                   window.prices = prices;
                   // Recreate displayServices with new prices (for display only) - FIXED: Use photocopying
                   const displayServices = [
                       { name: 'Print B&W', price: prices.print_bw, description: 'Black and white printing per page', iconKey: 'print', unit: 'per page' },
                       { name: 'Print Color', price: prices.print_color, description: 'Color printing per page', iconKey: 'print', unit: 'per page' },
                       { name: 'Photocopy', price: prices.photocopying, description: 'Color photocopying per page', iconKey: 'photocopy', unit: 'per page' },
                       { name: 'Scanning', price: prices.scanning, description: 'Document scanning per page', iconKey: 'scanning', unit: 'per page' },
                       { name: 'Photo Development', price: prices.photo_development, description: 'Film photo development per photo', iconKey: 'photo development', unit: 'per photo' },
                       { name: 'Laminating', price: prices.laminating, description: 'Laminating service per item', iconKey: 'laminating', unit: 'per item' }
                   ];
                   displayPrices(displayServices);
                   updatePriceElements(prices);
                   // FIXED: Recreate and repopulate dropdown services to update option texts with new prices - FIXED: Use photocopying, Added Laminating
                   const dropdownServices = [
                       { name: 'Print', price: 0, description: '', iconKey: 'print', unit: 'per page' },
                       { name: 'Photocopy', price: prices.photocopying, description: '', iconKey: 'photocopy', unit: 'per page' },
                       { name: 'Scanning', price: prices.scanning, description: '', iconKey: 'scanning', unit: 'per page' },
                       { name: 'Photo Development', price: prices.photo_development, description: '', iconKey: 'photo development', unit: 'per photo' },
                       { name: 'Laminating', price: prices.laminating, description: '', iconKey: 'laminating', unit: 'per item' }
                   ];
                   populateServiceDropdown(dropdownServices, prices);
                   window.serviceList = dropdownServices;
                   // Show update time for user feedback
                   const updateTime = document.getElementById('priceUpdateTime');
                   if (updateTime) updateTime.textContent = new Date().toLocaleTimeString();
               } catch (error) {
                   console.error('Error refreshing prices:', error);
                   showToast('Failed to update prices. Using defaults.', true); // FIXED: User feedback on error
                   // On error, use defaults for display - FIXED: Use photocopying
                   const prices = defaultPrices;
                   window.prices = prices;
                   const displayServices = [
                       { name: 'Print B&W', price: defaultPrices.print_bw, description: 'Black and white printing per page', iconKey: 'print', unit: 'per page' },
                       { name: 'Print Color', price: defaultPrices.print_color, description: 'Color printing per page', iconKey: 'print', unit: 'per page' },
                       { name: 'Photocopy', price: defaultPrices.photocopying, description: 'Color photocopying per page', iconKey: 'photocopy', unit: 'per page' },
                       { name: 'Scanning', price: defaultPrices.scanning, description: 'Document scanning per page', iconKey: 'scanning', unit: 'per page' },
                       { name: 'Photo Development', price: defaultPrices.photo_development, description: 'Film photo development per photo', iconKey: 'photo development', unit: 'per photo' },
                       { name: 'Laminating', price: defaultPrices.laminating, description: 'Laminating service per item', iconKey: 'laminating', unit: 'per item' }
                   ];
                   displayPrices(displayServices);
                   updatePriceElements(defaultPrices);
               }
           }
           // Update price elements (lamination, etc.)
           function updatePriceElements(prices) {
               const lamPriceEl = document.getElementById('lamPrice');
               const editLamPriceEl = document.getElementById('editLamPrice');
               if (lamPriceEl) lamPriceEl.textContent = prices.laminating.toFixed(2);
               if (editLamPriceEl) editLamPriceEl.textContent = prices.laminating.toFixed(2);
           }
           // Display Prices
           function displayPrices(serviceList) {
               const container = document.getElementById('priceItems');
               const updateTime = document.getElementById('priceUpdateTime');
               if (!container) return;
               container.innerHTML = serviceList.map(service => {
                   const iconClass = serviceIcons[service.iconKey] || 'fa-file-alt';
                   const unit = serviceUnits[service.iconKey] || 'per unit';
                   return `
                       <div class="bg-white/20 backdrop-blur-lg rounded-lg p-2 sm:p-3 md:p-4 text-center transition-all duration-300 hover:bg-white/30 hover:-translate-y-1 border border-white/20 price-card">
                           <div class="w-8 h-8 sm:w-10 sm:h-10 md:w-10 md:h-10 bg-white/30 rounded-full flex items-center justify-center mx-auto mb-1 sm:mb-2 price-icon">
                               <i class="fas ${iconClass} text-white text-sm sm:text-sm md:text-sm"></i>
                           </div>
                           <h4 class="font-semibold text-xs sm:text-sm md:text-sm mb-0.5 sm:mb-1 price-name">${service.name}</h4>
                           <div class="text-lg sm:text-xl md:text-2xl font-bold mb-0.5 price-amount">₱${parseFloat(service.price).toFixed(2)}</div>
                           <p class="text-xs opacity-80 price-unit">${unit}</p>
                       </div>
                   `;
               }).join('');
               if (updateTime) updateTime.textContent = new Date().toLocaleTimeString();
           }
           // Populate Service Dropdown - FIXED: Removed prices from option text, Added Laminating
           function populateServiceDropdown(serviceList, prices) {
               const select = document.getElementById('serviceDropdown');
               if (!select) return;
               const currentValue = select.value; // Preserve current selection
               select.innerHTML = '<option value="">-- Select Service --</option>' +
                   serviceList.map(s => {
                       return `<option value="${s.name}" data-price="${s.name === 'Print' ? 0 : s.price}">${s.name}</option>`;
                   }).join('');
               // Restore selection if it was set
               if (currentValue && select.querySelector(`option[value="${currentValue}"]`)) {
                   select.value = currentValue;
               }
           }
           // Populate Edit Service Dropdown
           function populateEditServiceDropdown(serviceList) {
               const select = document.getElementById('editService');
               if (!select) return;
               const currentValue = select.value; // Preserve if editing
               select.innerHTML = '<option value="">-- Select Service --</option>' +
                   serviceList.map(s => `<option value="${s.name}">${s.name}</option>`).join('');
               if (currentValue && select.querySelector(`option[value="${currentValue}"]`)) {
                   select.value = currentValue;
               }
           }
           // Load Dashboard Data
           async function loadDashboardData() {
               try {
                   const response = await fetch(`${API_BASE}?action=getDashboardStats`);
                   const result = await response.json();
                   if (result.success) {
                       const totalOrdersEl = document.getElementById('totalOrders');
                       const pendingOrdersEl = document.getElementById('pendingOrders');
                       const completedOrdersEl = document.getElementById('completedOrders');
                       const totalSpentEl = document.getElementById('totalSpent');
                       if (totalOrdersEl) totalOrdersEl.textContent = result.data.totalOrders;
                       if (pendingOrdersEl) pendingOrdersEl.textContent = result.data.pendingOrders;
                       if (completedOrdersEl) completedOrdersEl.textContent = result.data.completedOrders;
                       if (totalSpentEl) totalSpentEl.textContent = `₱${result.data.totalSpent}`;
                       loadRecentOrders();
                   }
               } catch (error) {
                   console.error('Error loading dashboard:', error);
               }
           }
           // Load Recent Orders
           async function loadRecentOrders() {
               try {
                   const response = await fetch(`${API_BASE}?action=getOrders`);
                   const result = await response.json();
                   const tableBody = document.getElementById('recentOrdersTable');
                   if (result.success && tableBody) {
                       if (result.data.orders.length === 0) {
                           tableBody.innerHTML = '<tr><td colspan="5" class="px-3 sm:px-6 py-8 md:py-12 text-center text-gray-500 text-sm">No orders yet</td></tr>';
                       } else {
                           tableBody.innerHTML = result.data.orders.slice(0, 5).map(order => `
                               <tr>
                                   <td class="px-2 sm:px-3 md:px-6 py-3 whitespace-nowrap text-xs sm:text-sm font-medium text-gray-900 table-td">${order.order_id}</td>
                                   <td class="px-2 sm:px-3 md:px-6 py-3 whitespace-nowrap text-xs sm:text-sm text-gray-500 table-td">${order.service}</td>
                                   <td class="px-2 sm:px-3 md:px-6 py-3 whitespace-nowrap text-xs sm:text-sm text-gray-500 table-td">${new Date(order.created_at).toLocaleDateString()}</td>
                                   <td class="px-2 sm:px-3 md:px-6 py-3 whitespace-nowrap table-td">
                                       <span class="px-1 sm:px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${order.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'}">
                                           ${order.status}
                                       </span>
                                   </td>
                                   <td class="px-2 sm:px-3 md:px-6 py-3 whitespace-nowrap text-xs sm:text-sm font-medium text-gray-900 table-td">₱${parseFloat(order.total_amount || 0).toFixed(2)}</td>
                               </tr>
                           `).join('');
                       }
                   }
               } catch (error) {
                   console.error('Error loading recent orders:', error);
               }
           }
           // New Order Form - Multi-Step
           const newOrderForm = document.getElementById('newOrderForm');
           const deliveryRadios = document.querySelectorAll('input[name="delivery_option"]');
           const deliveryAddressGroup = document.getElementById('deliveryAddressGroup');
           const serviceDropdown = document.getElementById('serviceDropdown');
           const quantityInput = document.getElementById('quantity');
           const fileInput = document.getElementById('orderFiles');
           const fileList = document.getElementById('fileList');
           const paperSizeGroup = document.getElementById('paperSizeGroup');
           const photoSizeGroup = document.getElementById('photoSizeGroup');
           const colorOptionGroup = document.getElementById('colorOptionGroup');
           const laminationGroup = document.getElementById('laminationGroup');
           const paperSizeSelect = document.getElementById('paper_size');
           const photoSizeSelect = document.getElementById('photo_size');
           const specifications = document.getElementById('specifications');
           const colorRadios = document.querySelectorAll('input[name="color_option"]');
           const addLaminationCheckbox = document.getElementById('addLamination');
           // Multi-step navigation
           const steps = document.querySelectorAll('.step');
           let currentStep = 0;
           function showStep(stepIndex) {
               steps.forEach((step, index) => {
                   step.classList.toggle('hidden', index !== stepIndex);
                   step.classList.toggle('active', index === stepIndex);
               });
               currentStep = stepIndex;
               updateSummary();
           }
           function nextStep() {
               if (currentStep < steps.length - 1) {
                   showStep(currentStep + 1);
               }
           }
           function prevStep() {
               if (currentStep > 0) {
                   showStep(currentStep - 1);
               }
           }
           // Next/Previous buttons
           document.getElementById('next-step-1').addEventListener('click', () => {
               if (validateStep(1)) {
                   nextStep();
               }
           });
           document.getElementById('next-step-2').addEventListener('click', () => {
               if (validateStep(2)) {
                   nextStep();
               }
           });
           document.getElementById('prev-step-2').addEventListener('click', prevStep);
           document.getElementById('prev-step-3').addEventListener('click', prevStep);
           // Validate specific step - FIXED: Added Scanning paper check
           function validateStep(stepNum) {
               let valid = true;
               const errors = [];
               // Clear previous errors
               document.querySelectorAll('.error-message').forEach(el => el.remove());
               document.querySelectorAll('.border-red-500').forEach(el => el.classList.remove('border-red-500'));
               if (stepNum === 1) {
                   const service = serviceDropdown ? serviceDropdown.value.trim() : '';
                   const quantityVal = quantityInput ? quantityInput.value.trim() : '';
                   const deliveryChecked = document.querySelector('input[name="delivery_option"]:checked');
                   const deliveryOption = deliveryChecked ? deliveryChecked.value : '';
                   const addressVal = document.getElementById('delivery_address') ? document.getElementById('delivery_address').value.trim() : '';
                   if (!service) {
                       errors.push('Service is required.');
                       if (serviceDropdown) serviceDropdown.classList.add('border-red-500');
                   }
                   if (!quantityVal || parseInt(quantityVal) < 1) {
                       errors.push('Quantity must be at least 1.');
                       if (quantityInput) quantityInput.classList.add('border-red-500');
                   }
                   if (!deliveryOption) {
                       errors.push('Please select a delivery option.');
                   } else if (deliveryOption === 'delivery' && !addressVal) {
                       errors.push('Delivery address is required.');
                       const addressEl = document.getElementById('delivery_address');
                       if (addressEl) addressEl.classList.add('border-red-500');
                   }
                   if (errors.length > 0) {
                       showToast(errors[0], true);
                       valid = false;
                   }
               } else if (stepNum === 2) {
                   const specsVal = specifications ? specifications.value.trim() : '';
                   if (!specsVal) {
                       errors.push('Specifications are required.');
                       if (specifications) specifications.classList.add('border-red-500');
                   }
                   // Service-specific validations - FIXED: Added Scanning
                   const service = serviceDropdown ? serviceDropdown.value : '';
                   if (service) {
                       if (['Print', 'Photocopy', 'Scanning'].includes(service)) {
                           const paperVal = paperSizeSelect ? paperSizeSelect.value.trim() : '';
                           if (!paperVal) {
                               errors.push('Paper size is required.');
                               if (paperSizeSelect) paperSizeSelect.classList.add('border-red-500');
                           }
                           if (service === 'Print' || service === 'Scanning') {
                               const colorChecked = document.querySelector('input[name="color_option"]:checked');
                               if (!colorChecked) {
                                   errors.push('Please select a print/scan type.');
                               }
                           }
                       }
                       if (service === 'Photo Development') {
                           const photoVal = photoSizeSelect ? photoSizeSelect.value.trim() : '';
                           if (!photoVal) {
                               errors.push('Photo size is required.');
                               if (photoSizeSelect) photoSizeSelect.classList.add('border-red-500');
                           }
                       }
                   }
                   if (errors.length > 0) {
                       showToast(errors[0], true);
                       valid = false;
                   }
               }
               return valid;
           }
           // Helper: Show error below input
           function showFieldError(input, message) {
               // Remove previous error
               const existing = input.parentNode.querySelector('.error-message');
               if (existing) existing.remove();
               const error = document.createElement('p');
               error.className = 'error-message text-red-500 text-xs mt-1';
               error.textContent = message;
               input.parentNode.appendChild(error);
           }
           // Validate entire form - FIXED: Added Laminating, Scanning optional files
           function validateForm() {
               let valid = true;
               const errors = [];
          
               // Clear previous errors/styles
               document.querySelectorAll('.error-message').forEach(el => el.remove());
               document.querySelectorAll('.border-red-500').forEach(el => el.classList.remove('border-red-500'));
               const service = serviceDropdown ? serviceDropdown.value.trim() : '';
               const quantityVal = quantityInput ? quantityInput.value.trim() : '';
               const specsVal = specifications ? specifications.value.trim() : '';
               const deliveryChecked = document.querySelector('input[name="delivery_option"]:checked');
               const deliveryOption = deliveryChecked ? deliveryChecked.value : '';
               const addressVal = document.getElementById('delivery_address') ? document.getElementById('delivery_address').value.trim() : '';
               // Debug logs
               console.log('Validation Debug - Service:', service);
               console.log('Validation Debug - Quantity:', quantityVal);
               console.log('Validation Debug - Specs:', specsVal);
               console.log('Validation Debug - Delivery:', deliveryOption);
               console.log('Validation Debug - Files length:', fileInput ? fileInput.files.length : 'N/A');
               console.log('Validation Debug - Color checked:', document.querySelector('input[name="color_option"]:checked')?.value || 'none');
               // Core required fields
               if (!service) {
                   errors.push('Service is required.');
                   if (serviceDropdown) serviceDropdown.classList.add('border-red-500');
               }
               if (!quantityVal || parseInt(quantityVal) < 1) {
                   errors.push('Quantity must be at least 1.');
                   if (quantityInput) quantityInput.classList.add('border-red-500');
               }
               if (!specsVal) {
                   errors.push('Specifications are required.');
                   if (specifications) specifications.classList.add('border-red-500');
               }
               // Delivery
               if (!deliveryOption) {
                   errors.push('Please select a delivery option.');
                   valid = false;
                   // Highlight radios (add red border to container if needed)
               } else if (deliveryOption === 'delivery' && !addressVal) {
                   errors.push('Delivery address is required.');
                   const addressEl = document.getElementById('delivery_address');
                   if (addressEl) addressEl.classList.add('border-red-500');
               }
               // Service-specific - FIXED: Added Laminating (no file), Scanning optional
               if (service) {
                   const servicesRequiringFile = ['Print', 'Photocopy', 'Photo Development'];
                   if (servicesRequiringFile.includes(service)) {
                       if (!fileInput || fileInput.files.length === 0) {
                           errors.push('Please upload at least one file for this service.');
                           if (fileInput) fileInput.classList.add('border-red-500');
                       }
                   }
                   if (['Print', 'Photocopy', 'Scanning'].includes(service)) {
                       const paperVal = paperSizeSelect ? paperSizeSelect.value.trim() : '';
                       if (!paperVal) {
                           errors.push('Paper size is required.');
                           if (paperSizeSelect) paperSizeSelect.classList.add('border-red-500');
                       }
                       // Color for Print/Scanning
                       if (service === 'Print' || service === 'Scanning') {
                           const colorChecked = document.querySelector('input[name="color_option"]:checked');
                           if (!colorChecked) {
                               errors.push('Please select a print/scan type.');
                               valid = false;
                           }
                       }
                   }
                   if (service === 'Photo Development') {
                       const photoVal = photoSizeSelect ? photoSizeSelect.value.trim() : '';
                       if (!photoVal) {
                           errors.push('Photo size is required.');
                           if (photoSizeSelect) photoSizeSelect.classList.add('border-red-500');
                       }
                   }
               }
               console.log('Validation Debug - Errors:', errors);
               console.log('Validation Debug - Overall valid:', errors.length === 0);
               if (errors.length > 0) {
                   valid = false;
                   // Show first error as toast, rest in console
                   showToast(errors[0], true);
                   // Show all under the submit button or a general div (optional)
                   const submitBtn = document.getElementById('submitOrder');
                   if (submitBtn) {
                       // Remove previous error div
                       const existingErrorDiv = submitBtn.parentNode.querySelector('.validation-errors');
                       if (existingErrorDiv) existingErrorDiv.remove();
                       const errorDiv = document.createElement('div');
                       errorDiv.className = 'validation-errors bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mt-4';
                       errorDiv.innerHTML = `<ul class="list-disc list-inside text-sm">${errors.map(err => `<li>${err}</li>`).join('')}</ul>`;
                       submitBtn.parentNode.insertBefore(errorDiv, submitBtn);
                   }
               }
               return valid;
           }
           // Delivery toggle
           deliveryRadios.forEach(radio => {
               radio.addEventListener('change', (e) => {
                   deliveryAddressGroup.classList.toggle('hidden', e.target.value !== 'delivery');
                   updateSummary();
               });
           });
           // Service change handler for showing/hiding options - FIXED: Added Scanning paper, Laminating no extras
           if (serviceDropdown) {
               serviceDropdown.addEventListener('change', (e) => {
                   const serviceName = e.target.value;
                   // Hide all groups
                   if (paperSizeGroup) paperSizeGroup.classList.add('hidden');
                   if (photoSizeGroup) photoSizeGroup.classList.add('hidden');
                   if (colorOptionGroup) colorOptionGroup.classList.add('hidden');
                   if (laminationGroup) laminationGroup.classList.add('hidden');
                   if (paperSizeSelect) paperSizeSelect.value = 'A4';
                   if (photoSizeSelect) photoSizeSelect.value = 'A4';
                   colorRadios.forEach(r => r.checked = false); // Uncheck first
                   if (addLaminationCheckbox) addLaminationCheckbox.checked = false;
              
                   // NEW: Default to B&W if no color option needed/selected
                   document.querySelector('input[name="color_option"][value="bw"]').checked = true;
              
                   if (serviceName === 'Print' || serviceName === 'Photocopy' || serviceName === 'Scanning') {
                       if (paperSizeGroup) paperSizeGroup.classList.remove('hidden');
                       if (laminationGroup) laminationGroup.classList.remove('hidden');
                       if (serviceName === 'Print' || serviceName === 'Scanning') {
                           if (colorOptionGroup) colorOptionGroup.classList.remove('hidden');
                           // For Print/Scanning, default B&W (already set above)
                       } else if (serviceName === 'Photocopy') {
                           // Photocopy: Force color
                           document.querySelector('input[name="color_option"][value="color"]').checked = true;
                           document.querySelector('input[name="color_option"][value="bw"]').checked = false;
                       }
                   } else if (serviceName === 'Photo Development') {
                       if (photoSizeGroup) photoSizeGroup.classList.remove('hidden');
                       if (laminationGroup) laminationGroup.classList.remove('hidden');
                   } else if (serviceName === 'Laminating') {
                       // No extras for Laminating
                   }
                   updateSummary();
               });
           }
           // Color radios change
           colorRadios.forEach(radio => {
               radio.addEventListener('change', updateSummary);
           });
           // Lamination checkbox change
           if (addLaminationCheckbox) {
               addLaminationCheckbox.addEventListener('change', updateSummary);
           }
           // Paper size change
           if (paperSizeSelect) {
               paperSizeSelect.addEventListener('change', updateSummary);
           }
           // Photo size change
           if (photoSizeSelect) {
               photoSizeSelect.addEventListener('change', updateSummary);
           }
           // Update summary - FIXED: Added Scanning multiplier/paper, Laminating case, photocopying
           function updateSummary() {
               const serviceName = serviceDropdown ? serviceDropdown.value : '';
               const serviceLower = serviceName.toLowerCase();
               const quantity = parseInt(quantityInput ? quantityInput.value : 0) || 1;
               const delivery = document.querySelector('input[name="delivery_option"]:checked')?.value || 'pickup';
               const addLamination = addLaminationCheckbox ? addLaminationCheckbox.checked : false;
               let price = 0;
               let extra = '';
               const paper = paperSizeSelect ? paperSizeSelect.value : 'A4';
               const multipliers = { A4: 1, Short: 1, Long: 1.2 };
               const multiplier = multipliers[paper] || 1;
               if (serviceLower === 'print') {
                   const color = document.querySelector('input[name="color_option"]:checked')?.value || 'bw';
                   price = ((color === 'color' ? window.prices?.print_color || defaultPrices.print_color : window.prices?.print_bw || defaultPrices.print_bw) * multiplier);
                   extra = `, ${paper} (${color === 'color' ? 'Color' : 'B&W'})`;
                   document.getElementById('summaryPaper').textContent = paper;
                   document.getElementById('summaryColor').textContent = color === 'color' ? 'Color' : 'B&W';
               } else if (serviceLower === 'photocopy') {
                   // FIXED: Use photocopying
                   price = (window.prices?.photocopying || defaultPrices.photocopying) * multiplier;
                   extra = `, ${paper} (Color)`;
                   document.getElementById('summaryPaper').textContent = paper;
                   document.getElementById('summaryColor').textContent = 'Color';
               } else if (serviceLower === 'scanning') {
                   const color = document.querySelector('input[name="color_option"]:checked')?.value || 'bw';
                   price = (window.prices?.scanning || defaultPrices.scanning) * multiplier;
                   extra = `, ${paper} (${color === 'color' ? 'Color' : 'B&W'})`;
                   document.getElementById('summaryPaper').textContent = paper;
                   document.getElementById('summaryColor').textContent = color === 'color' ? 'Color' : 'B&W';
               } else if (serviceLower === 'photo development') {
                   const photoSize = photoSizeSelect ? photoSizeSelect.value : 'A4';
                   price = window.prices?.photo_development || defaultPrices.photo_development;
                   extra = `, Glossy ${photoSize}`;
                   document.getElementById('summaryPaper').textContent = `Glossy ${photoSize}`;
                   document.getElementById('summaryColor').textContent = '-';
               } else if (serviceLower === 'laminating') {
                   price = window.prices?.laminating || defaultPrices.laminating;
                   extra = '';
                   document.getElementById('summaryPaper').textContent = '-';
                   document.getElementById('summaryColor').textContent = '-';
               } else {
                   const selectedOption = serviceDropdown ? serviceDropdown.options[serviceDropdown.selectedIndex] : null;
                   price = parseFloat(selectedOption ? selectedOption.dataset.price : 0) || 0;
               }
               let total = price * quantity;
               if (addLamination && serviceLower !== 'laminating') {
                   const lamPrice = window.prices?.laminating || defaultPrices.laminating;
                   total += lamPrice * quantity;
                   document.getElementById('summaryLamination').textContent = `Yes (+₱${(lamPrice * quantity).toFixed(2)})`;
               } else {
                   document.getElementById('summaryLamination').textContent = 'No';
               }
               document.getElementById('summaryService').textContent = serviceName ? serviceName + extra : '-';
               document.getElementById('summaryQuantity').textContent = quantity;
               document.getElementById('summaryDelivery').textContent = delivery.charAt(0).toUpperCase() + delivery.slice(1);
               document.getElementById('summaryPrice').textContent = `₱${total.toFixed(2)}`;
           }
           if (quantityInput) quantityInput.addEventListener('input', updateSummary);
           // File list
           if (fileInput && fileList) {
               fileInput.addEventListener('change', () => {
                   fileList.innerHTML = Array.from(fileInput.files).map(file => `<div class="text-sm text-gray-600">${file.name}</div>`).join('');
               });
           }
           // SUBMIT ORDER (unchanged, but now uses improved validateForm)
           if (newOrderForm) {
               newOrderForm.addEventListener('submit', async (e) => {
                   e.preventDefault();
                   if (!validateForm()) { // Now more specific!
                       return; // No generic toast here—handled inside validateForm
                   }
                   const formData = new FormData(newOrderForm);
                   try {
                       const response = await fetch(`${API_BASE}?action=createOrder`, {
                           method: 'POST',
                           body: formData
                       });
                       const result = await response.json();
                       if (result.success) {
                           showToast(`Order placed successfully! Order ID: ${result.data.order_id}`);
                           newOrderForm.reset();
                           fileList.innerHTML = '';
                           // Hide groups
                           [paperSizeGroup, photoSizeGroup, colorOptionGroup, laminationGroup, deliveryAddressGroup].forEach(group => {
                               if (group) group.classList.add('hidden');
                           });
                           // Remove validation errors
                           const errorDiv = document.querySelector('.validation-errors');
                           if (errorDiv) errorDiv.remove();
                           updateSummary();
                           showSection('dashboard');
                           loadDashboardData();
                       } else {
                           showToast(result.message || 'Error placing order', true);
                       }
                   } catch (error) {
                       console.error('Submit error:', error);
                       showToast('Network error: ' + error.message, true);
                   }
               });
           }
           // Orders Section
           const filterStatus = document.getElementById('filterStatus');
           const ordersList = document.getElementById('ordersList');
           async function loadOrders(status = '') {
               const url = status ? `${API_BASE}?action=getOrders&status=${status}` : `${API_BASE}?action=getOrders`;
               try {
                   const response = await fetch(url);
                   const result = await response.json();
                   if (result.success && ordersList) {
                       ordersList.innerHTML = result.data.orders.length === 0
                           ? '<p class="p-6 text-center text-gray-500">No orders found</p>'
                           : result.data.orders.map(order => {
                               const specsPreview = order.specifications.length > 100 ? order.specifications.substring(0, 100) + '...' : order.specifications;
                               const statusClass = order.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800';
                               const editBtn = order.status === 'pending' ? `
                                   <button class="edit-btn mt-2 text-primary-600 hover:text-primary-800 text-sm" data-order-id="${order.order_id}">Edit</button>
                               ` : '';
                               return `
                                   <div class="p-6 border-b last:border-b-0 hover:bg-gray-50">
                                       <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                                           <div class="w-full sm:w-auto">
                                               <h4 class="font-semibold text-gray-900">#${order.order_id} - ${order.service}</h4>
                                               <p class="text-sm text-gray-600 mt-1">${specsPreview}</p>
                                               <div class="text-sm text-gray-500 mt-1">Qty: ${order.quantity} | Total: ₱${parseFloat(order.total_amount || 0).toFixed(2)}</div>
                                           </div>
                                           <div class="text-right w-full sm:w-auto">
                                               <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full ${statusClass}">${order.status}</span>
                                               <p class="text-sm text-gray-500 mt-1">${new Date(order.created_at).toLocaleDateString()}</p>
                                               ${editBtn}
                                           </div>
                                       </div>
                                   </div>
                               `;
                           }).join('');
                   } else if (ordersList) {
                       ordersList.innerHTML = '<p class="p-6 text-center text-red-500">Error loading orders</p>';
                   }
               } catch (error) {
                   console.error('Error:', error);
                   if (ordersList) ordersList.innerHTML = '<p class="p-6 text-center text-red-500">Error loading orders</p>';
               }
           }
           if (filterStatus) filterStatus.addEventListener('change', () => loadOrders(filterStatus.value));
           // ============================================
           // FIXED EDIT ORDER FUNCTION - No duplication, added Laminating/Scanning fixes
           // ============================================
           // New: Fetch single order details
           async function fetchOrderDetails(orderId) {
               try {
                   const response = await fetch(`${API_BASE}?action=getOrder&order_id=${orderId}`);
                   const result = await response.json();
                   if (result.success) {
                       return result.data;
                   } else {
                       throw new Error(result.message || 'Order not found');
                   }
               } catch (error) {
                   console.error('Error fetching order details:', error);
                   showToast('Error loading order details: ' + error.message, true);
                   return null;
               }
           }
           // FIXED: Populate edit modal - Extract user desc only, parse options to avoid duplication
           async function populateEditModal(order) {
               console.log('Populating modal with order:', order); // DEBUG: Check sa console
               const modal = document.getElementById('editOrderModal');
               const form = document.getElementById('editOrderForm');
          
               if (!modal || !form || !order) {
                   console.error('Modal, form, or order data not found');
                   return;
               }
          
               // FIXED: Extract user description only (filter out option lines)
               let userSpecs = '';
               const lines = order.specifications.split('\n');
               const userLines = lines.filter(line => {
                   const trimmed = line.trim();
                   if (trimmed === '') return true; // Keep empty lines
                   return !trimmed.startsWith('Paper Size:') &&
                          !trimmed.startsWith('Print Type:') &&
                          !trimmed.startsWith('Copy Type:') &&
                          !trimmed.startsWith('Scan Type:') &&
                          !trimmed.startsWith('Photo Size:') &&
                          !trimmed.startsWith('Add Lamination: Yes');
               });
               if (userLines.length > 0) {
                   userSpecs = userLines.join('\n').trim();
               }
          
               // Set basic fields
               document.getElementById('editOrderId').value = order.order_id;
               document.getElementById('editService').value = order.service;
               document.getElementById('editQuantity').value = order.quantity;
               document.getElementById('editSpecifications').value = userSpecs || ''; // Only user desc
               document.getElementById('editAddress').value = order.delivery_address || '';
          
               // Set delivery option
               const editDeliveryRadios = document.querySelectorAll('#editOrderForm input[name="delivery_option"]');
               editDeliveryRadios.forEach(r => {
                   r.checked = (r.value === order.delivery_option);
               });
          
               const editDeliveryAddressGroup = document.getElementById('editDeliveryAddressGroup');
               if (editDeliveryAddressGroup) {
                   editDeliveryAddressGroup.classList.toggle('hidden', order.delivery_option !== 'delivery');
               }
          
               // Reset defaults
               const editPaperSelect = document.getElementById('edit_paper_size');
               const editPhotoSelect = document.getElementById('edit_photo_size');
               if (editPaperSelect) editPaperSelect.value = 'A4';
               if (editPhotoSelect) editPhotoSelect.value = 'A4';
          
               const editColorBw = document.querySelector('#editOrderForm input[name="color_option"][value="bw"]');
               if (editColorBw) editColorBw.checked = true;
          
               const editLaminationCheckbox = document.getElementById('editAddLamination');
               if (editLaminationCheckbox) editLaminationCheckbox.checked = false;
          
               // Hide all groups first
               const editGroups = [
                   document.getElementById('editPaperSizeGroup'),
                   document.getElementById('editPhotoSizeGroup'),
                   document.getElementById('editColorOptionGroup'),
                   document.getElementById('editLaminationGroup')
               ];
               editGroups.forEach(group => {
                   if (group) group.classList.add('hidden');
               });
          
               // Show relevant groups based on service - FIXED: Added Scanning paper
               const service = order.service;
               if (service === 'Print' || service === 'Photocopy' || service === 'Scanning') {
                   document.getElementById('editPaperSizeGroup')?.classList.remove('hidden');
                   document.getElementById('editLaminationGroup')?.classList.remove('hidden');
                   if (service === 'Print' || service === 'Scanning') {
                       document.getElementById('editColorOptionGroup')?.classList.remove('hidden');
                   } else if (service === 'Photocopy') {
                       // Photocopy: Force color
                       const editColorColor = document.querySelector('#editOrderForm input[name="color_option"][value="color"]');
                       if (editColorColor) editColorColor.checked = true;
                   }
               } else if (service === 'Photo Development') {
                   document.getElementById('editPhotoSizeGroup')?.classList.remove('hidden');
                   document.getElementById('editLaminationGroup')?.classList.remove('hidden');
               } else if (service === 'Laminating') {
                   // No extras
               }
          
               // Parse specifications to restore options (from full lines)
               console.log('Parsing specs lines:', lines); // DEBUG: Check format ng specs
               for (let line of lines) {
                   line = line.trim();
              
                   if (line.startsWith('Paper Size:')) {
                       const size = line.split(':')[1]?.trim();
                       if (editPaperSelect && ['A4', 'Short', 'Long'].includes(size)) {
                           editPaperSelect.value = size;
                       }
                   } else if (line.startsWith('Print Type:') || line.startsWith('Scan Type:')) {
                       const type = line.split(':')[1]?.trim();
                       const color = type?.includes('Color') ? 'color' : 'bw';
                       const radio = document.querySelector(`#editOrderForm input[name="color_option"][value="${color}"]`);
                       if (radio) radio.checked = true;
                   } else if (line.startsWith('Copy Type:')) {
                       // Photocopy always color
                       const radio = document.querySelector(`#editOrderForm input[name="color_option"][value="color"]`);
                       if (radio) radio.checked = true;
                   } else if (line.startsWith('Photo Size:')) {
                       const size = line.split(':')[1]?.trim().replace('Glossy ', '');
                       if (editPhotoSelect) editPhotoSelect.value = size;
                   } else if (line.startsWith('Add Lamination: Yes')) {
                       if (editLaminationCheckbox) editLaminationCheckbox.checked = true;
                   }
               }
          
               // Show modal
               modal.classList.remove('hidden');
               console.log('Modal opened successfully'); // DEBUG
           };
           // Close edit modal
           const closeEditModal = document.getElementById('closeEditModal');
           if (closeEditModal) {
               closeEditModal.addEventListener('click', () => {
                   const modal = document.getElementById('editOrderModal');
                   if (modal) modal.classList.add('hidden');
               });
           }
           // Edit delivery toggle
           const editDeliveryRadios = document.querySelectorAll('#editOrderForm input[name="delivery_option"]');
           const editDeliveryAddressGroup = document.getElementById('editDeliveryAddressGroup');
           editDeliveryRadios.forEach(radio => {
               radio.addEventListener('change', (e) => {
                   if (editDeliveryAddressGroup) {
                       editDeliveryAddressGroup.classList.toggle('hidden', e.target.value !== 'delivery');
                   }
               });
           });
           // Edit service change - FIXED: Added Scanning paper, Laminating no extras
           const editServiceSelect = document.getElementById('editService');
           if (editServiceSelect) {
               editServiceSelect.addEventListener('change', (e) => {
                   const service = e.target.value;
                   const editPaperGroup = document.getElementById('editPaperSizeGroup');
                   const editPhotoGroup = document.getElementById('editPhotoSizeGroup');
                   const editColorGroup = document.getElementById('editColorOptionGroup');
                   const editLaminationGroup = document.getElementById('editLaminationGroup');
              
                   // Hide all
                   [editPaperGroup, editPhotoGroup, editColorGroup, editLaminationGroup].forEach(group => {
                       if (group) group.classList.add('hidden');
                   });
              
                   // Reset defaults
                   if (document.getElementById('edit_paper_size')) document.getElementById('edit_paper_size').value = 'A4';
                   if (document.getElementById('edit_photo_size')) document.getElementById('edit_photo_size').value = 'A4';
                   const editColorBw = document.querySelector('#editOrderForm input[name="color_option"][value="bw"]');
                   if (editColorBw) editColorBw.checked = true;
                   const editLaminationCheckbox = document.getElementById('editAddLamination');
                   if (editLaminationCheckbox) editLaminationCheckbox.checked = false;
              
                   // Show relevant groups - FIXED: Added Scanning
                   if (service === 'Print' || service === 'Photocopy' || service === 'Scanning') {
                       if (editPaperGroup) editPaperGroup.classList.remove('hidden');
                       if (editLaminationGroup) editLaminationGroup.classList.remove('hidden');
                       if (service === 'Print' || service === 'Scanning') {
                           if (editColorGroup) editColorGroup.classList.remove('hidden');
                       } else if (service === 'Photocopy') {
                           const editColorColor = document.querySelector('#editOrderForm input[name="color_option"][value="color"]');
                           if (editColorColor) editColorColor.checked = true;
                       }
                   } else if (service === 'Photo Development') {
                       if (editPhotoGroup) editPhotoGroup.classList.remove('hidden');
                       if (editLaminationGroup) editLaminationGroup.classList.remove('hidden');
                   } else if (service === 'Laminating') {
                       // No extras
                   }
               });
           }
           // Edit order form submit - FIXED: Better validation
           const editOrderForm = document.getElementById('editOrderForm');
           if (editOrderForm) {
               editOrderForm.addEventListener('submit', async (e) => {
                   e.preventDefault();
              
                   // Basic validation - FIXED: More checks
                   const service = document.getElementById('editService').value;
                   const quantity = parseInt(document.getElementById('editQuantity').value);
                   const specs = document.getElementById('editSpecifications').value.trim();
                   const delivery = document.querySelector('#editOrderForm input[name="delivery_option"]:checked')?.value;
                   const address = document.getElementById('editAddress').value.trim();
              
                   if (!service || !quantity || quantity < 1 || !specs || !delivery) {
                       showToast('Please fill in all required fields', true);
                       return;
                   }
              
                   if (delivery === 'delivery' && !address) {
                       showToast('Delivery address is required', true);
                       return;
                   }
              
                   // Service-specific quick check
                   if (['Print', 'Photocopy', 'Scanning'].includes(service)) {
                       const paper = document.getElementById('edit_paper_size')?.value;
                       if (!paper) {
                           showToast('Paper size is required', true);
                           return;
                       }
                       if ((service === 'Print' || service === 'Scanning') && !document.querySelector('#editOrderForm input[name="color_option"]:checked')) {
                           showToast('Please select a print/scan type', true);
                           return;
                       }
                   }
                   if (service === 'Photo Development') {
                       const photo = document.getElementById('edit_photo_size')?.value;
                       if (!photo) {
                           showToast('Photo size is required', true);
                           return;
                       }
                   }
              
                   const formData = new FormData(e.target);
              
                   try {
                       const response = await fetch(`${API_BASE}?action=updateOrder`, {
                           method: 'POST',
                           body: formData
                       });
                       const result = await response.json();
                  
                       if (result.success) {
                           let msg = 'Order updated successfully!';
                           if (result.data.replaced_files) {
                               msg += ' Files have been replaced.';
                           }
                           showToast(msg);
                           const modal = document.getElementById('editOrderModal');
                           if (modal) modal.classList.add('hidden');
                           loadOrders(document.getElementById('filterStatus')?.value || '');
                           loadDashboardData();
                       } else {
                           showToast(result.message || 'Error updating order', true);
                       }
                   } catch (error) {
                       console.error('Update error:', error);
                       showToast('Error: ' + error.message, true);
                   }
               });
           }
           // Periodic polling for price updates (reduced to 10s for faster sync)
           setInterval(() => {
               refreshPrices();
           }, 10000);
       </script>
       <script>
   // ----- PROFILE UPDATE TOAST (runs on every page load) -----
   document.addEventListener('DOMContentLoaded', function () {
       <?php if (isset($_SESSION['toast_message'])): ?>
           const msg = <?= json_encode($_SESSION['toast_message']) ?>;
           const type = <?= json_encode($_SESSION['toast_type'] ?? 'success') ?>;
           showToast(msg, type === 'error');
           <?php
           unset($_SESSION['toast_message']);
           unset($_SESSION['toast_type']);
           ?>
       <?php endif; ?>
   });
</script>
<script>
   // Toggle password visibility
   function togglePassword(button, inputId) {
       const input = document.getElementById(inputId);
       const icon = button.querySelector('i');
       if (input.type === 'password') {
           input.type = 'text';
           icon.classList.replace('fa-eye', 'fa-eye-slash');
       } else {
           input.type = 'password';
           icon.classList.replace('fa-eye-slash', 'fa-eye');
       }
   }
</script>
   </body>
   </html>