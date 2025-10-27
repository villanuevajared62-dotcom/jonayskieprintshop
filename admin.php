    <?php
    // admin.php - SECURE ADMIN DASHBOARD WITH ENHANCED SECURITY AND DATA ACCURACY
    session_name('admin_session');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_GET['action']) && $_GET['action'] === 'fetch_deleted_orders') {
        try {
            require_once 'config.php';
            $pdo = getDBConnection();

            $stmt = $pdo->prepare("
                SELECT
                    d.order_id,
                    CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
                    d.service,
                    d.quantity,
                    d.status,
                    d.created_at AS order_date,
                    d.deleted_at
                FROM deleted_orders d
                LEFT JOIN users u ON d.user_id = u.id
                ORDER BY d.deleted_at DESC
            ");

            $stmt->execute();
            $deletedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJson(['success' => true, 'orders' => $deletedOrders]);
        } catch (PDOException $e) {
            sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        setcookie(session_name(), '', time() - 3600, '/');
        header('Location: login_admin.php?reason=no_session');
        exit;
    }
    if ($_SESSION['user_role'] !== 'admin') {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        setcookie(session_name(), '', time() - 3600, '/');
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer') {
            header('Location: dashboard.php');
        } else {
            header('Location: login.php?reason=not_admin');
        }
        exit;
    }
    if (!isset($_SESSION['last_regen']) || (time() - $_SESSION['last_regen'] > 3600)) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    require_once 'config.php';
    $pdo = getDBConnection();
    if (!$pdo) {
        die("Database connection failed. Please check your configuration.");
    }
    if (!isset($_SESSION['user_name'])) {
        $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ? AND role = 'admin'");
        $stmt->execute([$_SESSION['user_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            $_SESSION['user_name'] = trim($admin['first_name'] . ' ' . $admin['last_name']);
            $_SESSION['user_email'] = $admin['email'];
        }
    }
    function sendJson($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    if (isset($_GET['action']) && $_GET['action'] === 'verify_admin') {
        sendJson([
            'isAdmin' => ($_SESSION['user_role'] === 'admin'),
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'] ?? 'Admin',
                'email' => $_SESSION['user_email'] ?? '',
                'login_time' => $_SESSION['login_time'] ?? 0
            ]
        ]);
    }
    if (isset($_GET['action']) && $_GET['action'] === 'fetch_notifications') {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    o.id AS order_id,
                    CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
                    o.service,
                    o.quantity,
                    o.created_at AS notification_time,
                    o.specifications,
                    o.status
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.is_deleted = 0
                ORDER BY o.created_at DESC
                LIMIT 10
            ");
            $stmt->execute();
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $unreadCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE LOWER(status) = 'pending' AND is_deleted = 0")->fetchColumn();

            foreach ($notifications as &$notif) {
                $notif['message'] = "New order from {$notif['customer_name']}: {$notif['quantity']} x {$notif['service']} - " . date('M j, Y g:i A', strtotime($notif['notification_time']));
            }
            unset($notif);

            sendJson([
                'success' => true,
                'notifications' => $notifications,
                'unreadCount' => (int)$unreadCount
            ]);
        } catch (PDOException $e) {
            sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    if (isset($_GET['action']) && $_GET['action'] === 'fetch_order_status_counts') {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    LOWER(status) as status,
                    COUNT(*) as count
                FROM orders
                WHERE is_deleted = 0
                GROUP BY LOWER(status)
            ");
            $stmt->execute();
            $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $statuses = ['pending', 'in-progress', 'completed', 'cancelled'];
            $data = [];
            foreach($statuses as $s) {
                $data[] = (int)($counts[$s] ?? 0);
            }
            sendJson([
                'success' => true,
                'labels' => $statuses,
                'data' => $data
            ]);
        } catch (PDOException $e) {
            sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    if (isset($_GET['action']) && $_GET['action'] === 'dashboard_stats') {
        try {
            $totalRevenueStmt = $pdo->prepare("
                SELECT COALESCE(SUM(
                    CASE
                        WHEN LOWER(o.service) = 'print' AND (LOWER(o.specifications) LIKE '%black and white%' OR LOWER(o.specifications) LIKE '%bw%' OR LOWER(o.specifications) LIKE '%monochrome%' OR LOWER(o.specifications) LIKE '%black & white%') THEN o.quantity * p.print_bw
                        WHEN LOWER(o.service) = 'print' AND (LOWER(o.specifications) LIKE '%color%' OR LOWER(o.specifications) LIKE '%coloured%') THEN o.quantity * p.print_color
                        WHEN LOWER(o.service) = 'print' THEN o.quantity * COALESCE(p.print_bw, p.document_printing)
                        WHEN LOWER(o.service) = 'photocopy' THEN o.quantity * p.photocopying
                        WHEN LOWER(o.service) = 'scanning' THEN o.quantity * p.scanning
                        WHEN LOWER(o.service) = 'photo-development' THEN o.quantity * p.photo_development
                        WHEN LOWER(o.service) = 'laminating' THEN o.quantity * p.laminating
                        ELSE 0
                    END), 0) AS total_revenue
                FROM orders o
                LEFT JOIN pricing p ON p.id = 1
                WHERE LOWER(o.status) = 'completed' AND o.is_deleted = 0
            ");
            $totalRevenueStmt->execute();
            $totalRevenue = $totalRevenueStmt->fetchColumn();

            $todayRevenueStmt = $pdo->prepare("
                SELECT COALESCE(SUM(
                    CASE
                        WHEN LOWER(o.service) = 'print' AND (LOWER(o.specifications) LIKE '%black and white%' OR LOWER(o.specifications) LIKE '%bw%' OR LOWER(o.specifications) LIKE '%monochrome%' OR LOWER(o.specifications) LIKE '%black & white%') THEN o.quantity * p.print_bw
                        WHEN LOWER(o.service) = 'print' AND (LOWER(o.specifications) LIKE '%color%' OR LOWER(o.specifications) LIKE '%coloured%') THEN o.quantity * p.print_color
                        WHEN LOWER(o.service) = 'print' THEN o.quantity * COALESCE(p.print_bw, p.document_printing)
                        WHEN LOWER(o.service) = 'photocopy' THEN o.quantity * p.photocopying
                        WHEN LOWER(o.service) = 'scanning' THEN o.quantity * p.scanning
                        WHEN LOWER(o.service) = 'photo-development' THEN o.quantity * p.photo_development
                        WHEN LOWER(o.service) = 'laminating' THEN o.quantity * p.laminating
                        ELSE 0
                    END), 0) AS today_revenue
                FROM orders o
                LEFT JOIN pricing p ON p.id = 1
                WHERE LOWER(o.status) = 'completed' AND DATE(o.created_at) = CURDATE() AND o.is_deleted = 0
            ");
            $todayRevenueStmt->execute();
            $todayRevenue = $todayRevenueStmt->fetchColumn();

            $totalOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE is_deleted = 0")->fetchColumn();
            $pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE LOWER(status) = 'pending' AND is_deleted = 0")->fetchColumn();
            $totalCustomers = $pdo->query("SELECT COUNT(DISTINCT id) FROM users WHERE LOWER(role) = 'customer'")->fetchColumn();

            sendJson([
                'totalOrders' => (int)$totalOrders,
                'pendingOrders' => (int)$pendingOrders,
                'totalCustomers' => (int)$totalCustomers,
                'totalRevenue' => number_format($totalRevenue, 2),
                'todayRevenue' => number_format($todayRevenue, 2)
            ]);
        } catch (PDOException $e) {
            sendJson(['error' => $e->getMessage()]);
        }
    }
    if (isset($_GET['action']) && $_GET['action'] === 'fetch_orders') {
        try {
            $status = $_GET['status'] ?? '';
            $date = $_GET['date'] ?? '';
            $query = "
                SELECT
                    o.id AS order_id,
                    CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
                    o.service,
                    o.quantity,
                    o.status,
                    o.specifications,
                    o.created_at AS order_date,
                    CASE 
                        WHEN o.delivery_address IS NULL OR o.delivery_address = '' OR TRIM(o.delivery_address) = '' 
                        THEN 'PICKUP' 
                        ELSE o.delivery_address 
                    END AS address,
                    COALESCE(
                        CASE
                            WHEN LOWER(o.service) = 'print' AND (LOWER(o.specifications) LIKE '%black and white%' OR LOWER(o.specifications) LIKE '%bw%' OR LOWER(o.specifications) LIKE '%monochrome%' OR LOWER(o.specifications) LIKE '%black & white%') THEN o.quantity * p.print_bw
                            WHEN LOWER(o.service) = 'print' AND (LOWER(o.specifications) LIKE '%color%' OR LOWER(o.specifications) LIKE '%coloured%') THEN o.quantity * p.print_color
                            WHEN LOWER(o.service) = 'print' THEN o.quantity * COALESCE(p.print_bw, p.document_printing)
                            WHEN LOWER(o.service) = 'photocopy' THEN o.quantity * p.photocopying
                            WHEN LOWER(o.service) = 'scanning' THEN o.quantity * p.scanning
                            WHEN LOWER(o.service) = 'photo-development' THEN o.quantity * p.photo_development
                            WHEN LOWER(o.service) = 'laminating' THEN o.quantity * p.laminating
                            ELSE 0
                        END, 0) AS amount,
                    GROUP_CONCAT(f.filepath) AS files
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN order_files f ON o.id = f.order_id
                LEFT JOIN pricing p ON p.id = 1
                WHERE o.is_deleted = 0
            ";
            if ($status) $query .= " AND LOWER(o.status) = :status";
            if ($date) $query .= " AND DATE(o.created_at) = :date";
            $query .= " GROUP BY o.id ORDER BY o.created_at DESC";
            $stmt = $pdo->prepare($query);
            if ($status) $stmt->bindParam(':status', $status);
            if ($date) $stmt->bindParam(':date', $date);
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($orders as &$order) {
                if (!$order['customer_name']) $order['customer_name'] = 'Unknown';
                if (!$order['quantity']) $order['quantity'] = 0;
                if (empty($order['files'])) $order['files'] = 'No files';
            }
            unset($order);
            sendJson(['success' => true, 'orders' => $orders]);
        } catch (PDOException $e) {
            sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    if (isset($_GET['action']) && $_GET['action'] === 'fetch_customers') {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    u.id AS customer_id,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.phone,
                    COUNT(o.id) AS total_orders,
                    DATE(u.created_at) AS join_date
                FROM users u
                LEFT JOIN orders o ON u.id = o.user_id AND o.is_deleted = 0
                WHERE LOWER(u.role) = 'customer'
                GROUP BY u.id
                ORDER BY u.created_at DESC
            ");
            $stmt->execute();
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJson($customers);
        } catch (PDOException $e) {
            sendJson(['error' => $e->getMessage()]);
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
        try {
            $order_id = $_POST['order_id'] ?? 0;
            $status = $_POST['status'] ?? '';
            if (!$order_id || !$status || !in_array($status, ['pending', 'in-progress', 'completed', 'cancelled'])) {
                sendJson(['success' => false, 'error' => 'Invalid order ID or status']);
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$status, $order_id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception('No active order found to update');
            }
            $pdo->commit();
            sendJson(['success' => true, 'message' => 'Order status updated successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'delete_order') {
        try {
            $order_id = (int)($_POST['order_id'] ?? 0);
            
            error_log("🗑️ Archive attempt received: order_id = " . $order_id . " (from POST: " . json_encode($_POST) . ")");

            if (!$order_id) {
                error_log("❌ Archive failed: Invalid order_id (0 or missing)");
                sendJson(['success' => false, 'error' => 'Missing or invalid order ID']);
            }

            $checkStmt = $pdo->prepare("SELECT id, is_deleted, user_id, service, quantity, status, created_at FROM orders WHERE id = ?");
            $checkStmt->execute([$order_id]);
            $existingOrder = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existingOrder || $existingOrder['is_deleted'] != 0) {
                error_log("❌ Archive failed: Order {$order_id} not found or already deleted");
                sendJson(['success' => false, 'error' => 'Order not found or already deleted']);
            }

            error_log("✅ Proceeding to archive order {$order_id}");

            $pdo->beginTransaction();

            $insertStmt = $pdo->prepare("
                INSERT INTO deleted_orders (order_id, user_id, service, quantity, status, created_at, deleted_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $insertStmt->execute([
                $existingOrder['id'],
                $existingOrder['user_id'],
                $existingOrder['service'],
                $existingOrder['quantity'],
                $existingOrder['status'],
                $existingOrder['created_at']
            ]);

            $stmt = $pdo->prepare("DELETE FROM order_files WHERE order_id = ?");
            $stmt->execute([$order_id]);

            $uploadDir = __DIR__ . "/uploads/orders/{$order_id}/";
            if (is_dir($uploadDir)) {
                $files = glob($uploadDir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        if (unlink($file)) {
                            error_log("Deleted file: " . $file);
                        } else {
                            error_log("Failed to delete file: " . $file);
                        }
                    }
                }
                if (rmdir($uploadDir)) {
                    error_log("Deleted directory: " . $uploadDir);
                } else {
                    error_log("Failed to delete directory: " . $uploadDir);
                }
            } else {
                error_log("Directory not found (normal if no files): " . $uploadDir);
            }

            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$order_id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception('No active order found to delete (possible race condition)');
            }

            $pdo->commit();
            error_log("✅ Order {$order_id} archived to deleted_orders successfully");
            sendJson(['success' => true, 'message' => 'Order moved to deleted transactions successfully']);
        } catch (Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            error_log("❌ Archive error for order {$order_id}: " . $e->getMessage());
            sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'update_pricing') {
        try {
            $printBw = filter_var($_POST['print_bw'] ?? 0, FILTER_VALIDATE_FLOAT) ?: 0;
            $printColor = filter_var($_POST['print_color'] ?? 0, FILTER_VALIDATE_FLOAT) ?: 0;
            $photocopying = filter_var($_POST['photocopy'] ?? 0, FILTER_VALIDATE_FLOAT) ?: 0;
            $scanning = filter_var($_POST['scanning'] ?? 0, FILTER_VALIDATE_FLOAT) ?: 0;
            $photoDev = filter_var($_POST['photo-development'] ?? 0, FILTER_VALIDATE_FLOAT) ?: 0;
            $laminating = filter_var($_POST['laminating'] ?? 0, FILTER_VALIDATE_FLOAT) ?: 0;
            $stmt = $pdo->prepare("
                UPDATE pricing
                SET
                    print_bw = :print_bw,
                    print_color = :print_color,
                    photocopying = :photocopying,
                    scanning = :scanning,
                    photo_development = :photo_dev,
                    laminating = :laminating,
                    last_updated = NOW()
                WHERE id = 1
            ");

            $stmt->execute([
                ':print_bw' => $printBw,
                ':print_color' => $printColor,
                ':photocopying' => $photocopying,
                ':scanning' => $scanning,
                ':photo_dev' => $photoDev,
                ':laminating' => $laminating
            ]);
            sendJson([
                'success' => true,
                'message' => 'Pricing updated successfully!',
                'timestamp' => time()
            ]);
        } catch (PDOException $e) {
            sendJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    if (isset($_GET['action']) && $_GET['action'] === 'load_pricing') {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    print_bw AS `print_bw`,
                    print_color AS `print_color`,
                    photocopying AS photocopy,
                    scanning AS scanning,
                    photo_development AS `photo-development`,
                    laminating AS laminating
                FROM pricing 
                WHERE id = 1
            ");
            $stmt->execute();
            $pricing = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'print_bw' => 1.00,
                'print_color' => 2.50,
                'photocopy' => 1.50,
                'scanning' => 3.00,
                'photo-development' => 15.00,
                'laminating' => 5.00
            ];
            sendJson(['success' => true, 'pricing' => $pricing]);
        } catch (PDOException $e) {
            sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    if (isset($_GET['action']) && $_GET['action'] === 'fetch_report_stats') {
        try {
            $reportType = $_GET['report_type'] ?? 'daily';
            $reportDate = $_GET['report_date'] ?? date('Y-m-d');

            $dateCondition = '';
            $params = [':date' => $reportDate];

            switch($reportType) {
                case 'daily':
                    $dateCondition = "DATE(created_at) = :date AND is_deleted = 0";
                    break;
                case 'weekly':
                    $dateCondition = "YEARWEEK(created_at, 1) = YEARWEEK(:date, 1) AND is_deleted = 0";
                    break;
                case 'monthly':
                    $dateCondition = "YEAR(created_at) = YEAR(:date) AND MONTH(created_at) = MONTH(:date) AND is_deleted = 0";
                    break;
                default:
                    $dateCondition = "DATE(created_at) = :date AND is_deleted = 0";
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $dateCondition");
            $stmt->execute($params);
            $totalOrders = $stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(
                    CASE
                        WHEN LOWER(o.service)='print' AND (LOWER(o.specifications) LIKE '%black and white%' OR LOWER(o.specifications) LIKE '%bw%' OR LOWER(o.specifications) LIKE '%monochrome%' OR LOWER(o.specifications) LIKE '%black & white%') THEN o.quantity * p.print_bw
                        WHEN LOWER(o.service)='print' AND (LOWER(o.specifications) LIKE '%color%' OR LOWER(o.specifications) LIKE '%coloured%') THEN o.quantity * p.print_color
                        WHEN LOWER(o.service)='print' THEN o.quantity * COALESCE(p.print_bw, p.document_printing)
                        WHEN LOWER(o.service)='photocopy' THEN o.quantity * p.photocopying
                        WHEN LOWER(o.service)='scanning' THEN o.quantity * p.scanning
                        WHEN LOWER(o.service)='photo-development' THEN o.quantity * p.photo_development
                        WHEN LOWER(o.service)='laminating' THEN o.quantity * p.laminating
                        ELSE 0
                    END), 0) AS total_revenue
                FROM orders o
                LEFT JOIN pricing p ON p.id = 1
                WHERE LOWER(o.status) = 'completed' AND $dateCondition
            ");
            $stmt->execute($params);
            $totalRevenue = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(role)='customer' AND DATE(created_at) = :date");
            $stmt->execute($params);
            $newCustomers = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE LOWER(status)='completed' AND $dateCondition");
            $stmt->execute($params);
            $completedOrders = $stmt->fetchColumn();

            $completionRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 0;

            sendJson([
                'success' => true,
                'totalOrders' => (int)$totalOrders,
                'totalRevenue' => number_format($totalRevenue, 2),
                'newCustomers' => (int)$newCustomers,
                'completionRate' => $completionRate
            ]);
        } catch (PDOException $e) {
            sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    if (isset($_GET['action']) && $_GET['action'] === 'fetch_chart_data') {
        try {
            $reportType = $_GET['report_type'] ?? 'daily';
            $reportDate = $_GET['report_date'] ?? date('Y-m-d');

            $labels = [];
            $revenueData = [];
            $ordersData = [];

            if ($reportType === 'daily') {
                for ($hour = 0; $hour < 24; $hour++) {
                    $labels[] = sprintf('%02d:00', $hour);

                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(
                            CASE
                                WHEN LOWER(o.service)='print' AND (LOWER(o.specifications) LIKE '%black and white%' OR LOWER(o.specifications) LIKE '%bw%' OR LOWER(o.specifications) LIKE '%monochrome%' OR LOWER(o.specifications) LIKE '%black & white%') THEN o.quantity * p.print_bw
                                WHEN LOWER(o.service)='print' AND (LOWER(o.specifications) LIKE '%color%' OR LOWER(o.specifications) LIKE '%coloured%') THEN o.quantity * p.print_color
                                WHEN LOWER(o.service)='print' THEN o.quantity * COALESCE(p.print_bw, p.document_printing)
                                WHEN LOWER(o.service)='photocopy' THEN o.quantity * p.photocopying
                                WHEN LOWER(o.service)='scanning' THEN o.quantity * p.scanning
                                WHEN LOWER(o.service)='photo-development' THEN o.quantity * p.photo_development
                                WHEN LOWER(o.service)='laminating' THEN o.quantity * p.laminating
                                ELSE 0
                            END), 0)
                        FROM orders o
                        LEFT JOIN pricing p ON p.id = 1
                        WHERE DATE(o.created_at) = :date AND HOUR(o.created_at) = :hour AND LOWER(o.status) = 'completed' AND o.is_deleted = 0
                    ");
                    $stmt->execute([':date' => $reportDate, ':hour' => $hour]);
                    $revenueResult = $stmt->fetchColumn();
                    $revenueData[] = (float)($revenueResult ?? 0);

                    $stmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM orders
                        WHERE DATE(created_at) = :date AND HOUR(created_at) = :hour AND is_deleted = 0
                    ");
                    $stmt->execute([':date' => $reportDate, ':hour' => $hour]);
                    $ordersData[] = (int)$stmt->fetchColumn();
                }
            } elseif ($reportType === 'weekly') {
                $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                $labels = $days;

                for ($i = 0; $i < 7; $i++) {
                    $dayOfWeek = ($i + 2) % 7;
                    if ($dayOfWeek === 0) $dayOfWeek = 7;

                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(
                            CASE
                                WHEN LOWER(o.service)='print' AND (LOWER(o.specifications) LIKE '%black and white%' OR LOWER(o.specifications) LIKE '%bw%' OR LOWER(o.specifications) LIKE '%monochrome%' OR LOWER(o.specifications) LIKE '%black & white%') THEN o.quantity * p.print_bw
                                WHEN LOWER(o.service)='print' AND (LOWER(o.specifications) LIKE '%color%' OR LOWER(o.specifications) LIKE '%coloured%') THEN o.quantity * p.print_color
                                WHEN LOWER(o.service)='print' THEN o.quantity * COALESCE(p.print_bw, p.document_printing)
                                WHEN LOWER(o.service)='photocopy' THEN o.quantity * p.photocopying
                                WHEN LOWER(o.service)='scanning' THEN o.quantity * p.scanning
                                WHEN LOWER(o.service)='photo-development' THEN o.quantity * p.photo_development
                                WHEN LOWER(o.service)='laminating' THEN o.quantity * p.laminating
                                ELSE 0
                            END), 0)
                        FROM orders o
                        LEFT JOIN pricing p ON p.id = 1
                        WHERE YEARWEEK(o.created_at, 1) = YEARWEEK(:date, 1)
                        AND DAYOFWEEK(o.created_at) = :dayofweek AND LOWER(o.status) = 'completed' AND o.is_deleted = 0
                    ");
                    $stmt->execute([':date' => $reportDate, ':dayofweek' => $dayOfWeek]);
                    $revenueResult = $stmt->fetchColumn();
                    $revenueData[] = (float)($revenueResult ?? 0);

                    $stmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM orders
                        WHERE YEARWEEK(created_at, 1) = YEARWEEK(:date, 1)
                        AND DAYOFWEEK(created_at) = :dayofweek AND is_deleted = 0
                    ");
                    $stmt->execute([':date' => $reportDate, ':dayofweek' => $dayOfWeek]);
                    $ordersData[] = (int)$stmt->fetchColumn();
                }
            } elseif ($reportType === 'monthly') {
                $daysInMonth = date('t', strtotime($reportDate));
                $year = date('Y', strtotime($reportDate));
                $month = date('m', strtotime($reportDate));

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $labels[] = (string)$day;

                    $currentDate = sprintf('%s-%s-%02d', $year, $month, $day);

                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(
                            CASE
                                WHEN LOWER(o.service)='print' AND (LOWER(o.specifications) LIKE '%black and white%' OR LOWER(o.specifications) LIKE '%bw%' OR LOWER(o.specifications) LIKE '%monochrome%' OR LOWER(o.specifications) LIKE '%black & white%') THEN o.quantity * p.print_bw
                                WHEN LOWER(o.service)='print' AND (LOWER(o.specifications) LIKE '%color%' OR LOWER(o.specifications) LIKE '%coloured%') THEN o.quantity * p.print_color
                                WHEN LOWER(o.service)='print' THEN o.quantity * COALESCE(p.print_bw, p.document_printing)
                                WHEN LOWER(o.service)='photocopy' THEN o.quantity * p.photocopying
                                WHEN LOWER(o.service)='scanning' THEN o.quantity * p.scanning
                                WHEN LOWER(o.service)='photo-development' THEN o.quantity * p.photo_development
                                WHEN LOWER(o.service)='laminating' THEN o.quantity * p.laminating
                                ELSE 0
                            END), 0)
                        FROM orders o
                        LEFT JOIN pricing p ON p.id = 1
                        WHERE DATE(o.created_at) = :date AND LOWER(o.status) = 'completed' AND o.is_deleted = 0
                    ");
                    $stmt->execute([':date' => $currentDate]);
                    $revenueResult = $stmt->fetchColumn();
                    $revenueData[] = (float)($revenueResult ?? 0);

                    $stmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM orders
                        WHERE DATE(created_at) = :date AND is_deleted = 0
                    ");
                    $stmt->execute([':date' => $currentDate]);
                    $ordersData[] = (int)$stmt->fetchColumn();
                }
            }

            sendJson([
                'success' => true,
                'labels' => $labels,
                'revenueData' => $revenueData,
                'ordersData' => $ordersData
            ]);
        } catch (PDOException $e) {
            sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
        <meta http-equiv="Pragma" content="no-cache">
        <meta http-equiv="Expires" content="0">

        <title>Admin Dashboard - Jonayskie Prints</title>

        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            primary: '#007bff',
                        }
                    }
                }
            }
        </script>
        <style>
            /* Price Board Styles */
            .price-board {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 15px;
                padding: 25px;
                margin-bottom: 30px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                color: white;
            }

            .price-board-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                border-bottom: 2px solid rgba(255,255,255,0.3);
                padding-bottom: 15px;
            }

            .price-board-header h2 {
                margin: 0;
                font-size: 24px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .price-board-header i {
                font-size: 28px;
            }

            .price-update-badge {
                background: rgba(255,255,255,0.2);
                padding: 5px 12px;
                border-radius: 20px;
                font-size: 12px;
                display: flex;
                align-items: center;
                gap: 5px;
            }

            .price-items {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }

            .price-item {
                background: rgba(255,255,255,0.15);
                backdrop-filter: blur(10px);
                border-radius: 10px;
                padding: 15px;
                transition: transform 0.3s ease, background 0.3s ease;
                border: 1px solid rgba(255,255,255,0.2);
            }

            .price-item:hover {
                transform: translateY(-5px);
                background: rgba(255,255,255,0.25);
            }

            .price-item-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 10px;
            }

            .price-item-icon {
                width: 40px;
                height: 40px;
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
            }

            .price-item-name {
                font-weight: 600;
                font-size: 16px;
            }

            .price-item-price {
                font-size: 28px;
                font-weight: bold;
                margin: 5px 0;
            }

            .price-item-unit {
                font-size: 12px;
                opacity: 0.8;
            }

            .price-loading {
                text-align: center;
                padding: 20px;
                opacity: 0.8;
            }

            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.6; }
            }

            .price-updating {
                animation: pulse 1.5s ease-in-out infinite;
            }

            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }

            .stat-card {
                background: rgba(255,255,255,0.15);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255,255,255,0.2);
                transition: transform 0.3s ease;
            }

            .stat-card:hover {
                transform: translateY(-5px);
                background: rgba(255,255,255,0.25);
            }

            .stat-icon {
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
            }

            @media (max-width: 768px) {
                .price-items {
                    grid-template-columns: 1fr;
                }

                .price-board-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 10px;
                }
            }

            .dashboard-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                position: sticky;
                top: 0;
                z-index: 1000;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                transition: background 0.3s ease, box-shadow 0.3s ease;
            }

            .dashboard-header.scrolled {
                background: #667eea;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            }

            .sidebar {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }

            .nav-link:hover {
                background: rgba(255,255,255,0.1);
            }

            .nav-link.active {
                background: rgba(255,255,255,0.2);
            }

            .orders-table th,
            .orders-table td {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                padding: 0.5rem 0.75rem;
                font-size: 0.75rem;
            }

            .files-cell {
                max-width: 80px;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .specs-cell {
                max-width: 120px;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .address-cell {
                max-width: 80px;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .customer-cell {
                max-width: 100px;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .service-cell {
                max-width: 60px;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .notification-dropdown {
                position: absolute;
                top: 100%;
                right: 0;
                mt-2;
                w-80;
                bg-white;
                border: border-gray-200;
                rounded-xl;
                shadow-xl;
                z-50;
                max-h-96;
                overflow-y-auto;
            }

            .action-btn {
                font-size: 0.7rem;
                padding: 0.25rem 0.5rem;
            }

            @media (max-width: 768px) {
                .orders-table table,
                .orders-table thead,
                .orders-table tbody,
                .orders-table th,
                .orders-table td,
                .orders-table tr {
                    display: block;
                    width: 100%;
                }

                .orders-table thead tr {
                    display: none;
                }

                .orders-table tr {
                    margin-bottom: 1rem;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 0.5rem;
                    padding: 0.5rem;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }

                .orders-table td {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    text-align: right;
                    padding: 0.75rem 0.5rem;
                    font-size: 0.875rem;
                    border: none;
                    border-bottom: 1px solid #eee;
                    position: relative;
                }

                .orders-table td::before {
                    content: attr(data-label) ": ";
                    font-weight: 600;
                    text-transform: capitalize;
                    text-align: left;
                    color: #555;
                    flex: 1;
                    min-width: 0;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                .orders-table td:last-child {
                    border-bottom: none;
                }

                .orders-table .status-cell {
                    justify-content: flex-start;
                }

                .orders-table .status-cell::before {
                    flex: none;
                    margin-right: 0.5rem;
                }

                .orders-table .action-cell {
                    flex-direction: column;
                    gap: 0.5rem;
                    align-items: stretch;
                }

                .orders-table .action-cell::before {
                    display: none;
                }

                .orders-table .delete-cell {
                    background: #fee2e2;
                    border-radius: 0 0 0.5rem 0.5rem;
                    padding: 1rem;
                    margin-top: 0.5rem;
                }

                .orders-table .delete-cell::before {
                    display: block;
                    font-weight: bold;
                    color: #dc2626;
                    margin-bottom: 0.5rem;
                }

                .orders-table .view-specs-btn,
                .orders-table .status-select,
                .orders-table .delete-btn {
                    width: 100%;
                    text-align: center;
                }
            }

            .view-specs-btn {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
                border: 1px solid #007bff;
                color: #007bff;
                background: white;
                border-radius: 0.25rem;
                cursor: pointer;
                transition: all 0.2s;
            }

            .view-specs-btn:hover {
                background: #007bff;
                color: white;
            }

            #specsModal .modal-content {
                max-height: 80vh;
                overflow-y: auto;
            }

            @media (max-width: 768px) {
                #specsModal .grid-cols-3 {
                    grid-template-columns: 1fr;
                }
            }

            #customers-section {
                animation: fadeIn 0.4s ease-in-out;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            #customersTable td {
                padding: 0.75rem 1rem;
            }

            #customersTable tr:hover {
                background-color: #f9fafb;
                transition: background 0.3s ease;
            }

            @media (max-width: 768px) {
                .customers-table thead {
                    display: none;
                }

                #customersTable {
                    border-collapse: separate;
                    border-spacing: 0 1rem;
                }

                #customersTable tr {
                    display: block;
                    background: #ffffff;
                    border-radius: 1rem;
                    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
                    padding: 1rem;
                    margin-bottom: 1rem;
                    border-left: 5px solid #3b82f6;
                }

                #customersTable td {
                    display: flex;
                    justify-content: space-between;
                    padding: 0.4rem 0;
                    font-size: 0.9rem;
                    color: #374151;
                }

                #customersTable td::before {
                    content: attr(data-label);
                    font-weight: 600;
                    color: #2563eb;
                    display: inline-block;
                }

                #customersTable tr {
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                }

                #customersTable tr:hover {
                    transform: scale(1.01);
                    box-shadow: 0 6px 14px rgba(59, 130, 246, 0.15);
                }
            }

            @media (max-width: 768px) {
                .deleted-table table,
                .deleted-table thead,
                .deleted-table tbody,
                .deleted-table th,
                .deleted-table td,
                .deleted-table tr {
                    display: block;
                    width: 100%;
                }

                .deleted-table thead tr {
                    display: none;
                }

                .deleted-table tr {
                    margin-bottom: 1rem;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 0.5rem;
                    padding: 0.75rem;
                }

                .deleted-table td {
                    display: flex;
                    justify-content: space-between;
                    text-align: right;
                    padding: 0.5rem 0;
                    border: none;
                    border-bottom: 1px solid #eee;
                    font-size: 0.875rem;
                }

                .deleted-table td::before {
                    content: attr(data-label) ": ";
                    font-weight: 600;
                    text-transform: capitalize;
                    text-align: left;
                    color: #555;
                    flex: 1;
                }

                .deleted-table td:last-child {
                    border-bottom: none;
                }

                .deleted-table .bg-yellow-100,
                .deleted-table .bg-green-100 {
                    margin-left: auto;
                    white-space: nowrap;
                }
            }

            #specsModal {
                backdrop-filter: blur(8px);
            }

            #specsModal .modal-content {
                background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                border-radius: 20px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                border: 1px solid rgba(255, 255, 255, 0.2);
                max-width: 500px;
                animation: modalSlideIn 0.3s ease-out;
            }

            @keyframes modalSlideIn {
                from {
                    opacity: 0;
                    transform: translateY(-20px) scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }

            #specsModal h3 {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                font-weight: 700;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .specs-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 16px;
                margin-top: 20px;
            }

            .spec-card {
                background: white;
                border-radius: 12px;
                padding: 16px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
                border-left: 4px solid;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .spec-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            }

            .spec-card .spec-icon {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
                margin-bottom: 8px;
            }

            .spec-card.size { border-left-color: #3b82f6; }
            .spec-card.size .spec-icon { background: #dbeafe; color: #3b82f6; }
            .spec-card.type { border-left-color: #10b981; }
            .spec-card.type .spec-icon { background: #d1fae5; color: #10b981; }
            .spec-card.laminating { border-left-color: #8b5cf6; }
            .spec-card.laminating .spec-icon { background: #ede9fe; color: #8b5cf6; }

            .spec-card .spec-label {
                font-size: 12px;
                font-weight: 600;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 4px;
            }

            .spec-card .spec-value {
                font-size: 18px;
                font-weight: 700;
                color: #1f2937;
            }

            .spec-card .spec-unknown {
                color: #9ca3af;
                font-style: italic;
            }

            @media (max-width: 480px) {
                .specs-grid {
                    grid-template-columns: 1fr;
                }
            }

            .delete-btn {
                min-height: 32px;
            }

            .orders-table {
                --sticky-bg: white;
            }

            .orders-table th:last-child,
            .orders-table td:last-child {
                position: sticky;
                right: 0;
                background: var(--sticky-bg);
                z-index: 10;
                border-left: 2px solid #e5e7eb;
                min-width: 140px;
                width: 140px;
            }

            .orders-table thead th:last-child {
                background: #f9fafb;
                --sticky-bg: #f9fafb;
            }

            @media (max-width: 768px) {
                .orders-table td:last-child::before {
                    content: 'Action: ';
                    font-weight: 600;
                    color: #dc2626;
                }
                
                .orders-table td:last-child {
                    justify-content: flex-start !important;
                    flex-direction: column;
                    gap: 0.5rem;
                    border-bottom: 2px solid #fee2e2 !important;
                    background: #fef2f2;
                    padding: 1rem !important;
                    border-radius: 0 0 0.5rem 0.5rem;
                }
                
                .orders-table .delete-btn {
                    align-self: stretch;
                    font-weight: 600;
                }
            }
        </style>
    </head>
    <body class="bg-gray-100">
        <div class="flex h-screen">
            <aside class="sidebar fixed inset-y-0 left-0 z-50 w-64 bg-gradient-to-b from-[#667eea] to-[#764ba2] shadow-xl transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out md:static md:inset-auto" id="sidebar">
                <div class="sidebar-header flex items-center justify-between p-4 bg-white/10 border-b rounded-t-lg">
                    <div class="logo flex items-center space-x-2">
                        <i class="fas fa-print text-white"></i>
                        <span class="font-bold text-lg text-white">Admin Panel</span>
                    </div>
                    <button class="close-sidebar-btn md:hidden p-1 text-white hover:text-gray-200" id="closeSidebar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <nav class="sidebar-nav flex-1 overflow-y-auto">
                    <ul class="p-4 space-y-2">
                        <li>
                            <a href="#dashboard" class="nav-link flex items-center space-x-3 p-3 rounded-xl text-white hover:bg-white/10 active:bg-white/20 active:text-white transition-all duration-200" data-section="dashboard">
                                <i class="fas fa-tachometer-alt w-5"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="#orders" class="nav-link flex items-center space-x-3 p-3 rounded-xl text-white hover:bg-white/10" data-section="orders">
                                <i class="fas fa-list-alt w-5"></i>
                                <span>Manage Orders</span>
                            </a>
                        </li>
                        <li>
                            <a href="#customers" class="nav-link flex items-center space-x-3 p-3 rounded-xl text-white hover:bg-white/10" data-section="customers">
                                <i class="fas fa-users w-5"></i>
                                <span>Customers</span>
                            </a>
                        </li>
                        <li>
                            <a href="#reports" class="nav-link flex items-center space-x-3 p-3 rounded-xl text-white hover:bg-white/10" data-section="reports">
                                <i class="fas fa-chart-bar w-5"></i>
                                <span>Reports</span>
                            </a>
                        </li>
                        <li>
                            <a href="#settings" class="nav-link flex items-center space-x-3 p-3 rounded-xl text-white hover:bg-white/10" data-section="settings">
                                <i class="fas fa-cog w-5"></i>
                                <span>Settings</span>
                            </a>
                        </li>
                        <li>
                            <a href="#deleted-transactions-section" class="nav-link flex items-center space-x-3 p-3 rounded-xl text-white hover:bg-white/10" data-section="deleted-transactions-section">
                                <i class="fas fa-trash-alt w-5"></i>
                                <span>Deleted Transactions</span>
                            </a>
                        </li>
                    </ul>
                </nav>

                <div class="sidebar-footer p-4 border-t rounded-b-lg">
                    <a href="login_admin.php" class="logout-btn flex items-center space-x-3 w-full p-3 rounded-xl text-white hover:bg-white/10 transition-all duration-200">
                        <i class="fas fa-sign-out-alt w-5"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </aside>
            <div class="flex-1 flex flex-col overflow-hidden md:ml-0">
                <main class="main-content flex-1 overflow-y-auto">
                    <header class="dashboard-header shadow-lg border-b flex items-center justify-between px-4 py-3 md:px-6 text-white">
                        <div class="header-left flex items-center space-x-4">
                            <button class="md:hidden p-2 text-white hover:text-gray-200" id="toggleSidebarMobile">
                                <i class="fas fa-bars text-xl"></i>
                            </button>
                            <h1 id="pageTitle" class="text-xl font-semibold">Admin Dashboard</h1>
                        </div>
                        <div class="header-right flex items-center space-x-4">
                            <div class="notifications relative" id="notificationBell" onclick="toggleNotifications()">
                                <i class="fas fa-bell text-xl text-white cursor-pointer relative"></i>
                                <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center min-w-[18px] hidden"></span>
                                <div class="notification-dropdown absolute top-full right-0 mt-2 w-80 bg-white border border-gray-200 rounded-xl shadow-xl z-50 max-h-96 overflow-y-auto hidden" id="notificationDropdown">
                                    <div class="notification-header flex justify-between items-center p-3 border-b bg-gray-50 rounded-t-xl">
                                        <strong class="text-sm font-medium text-gray-900">Notifications</strong>
                                        <button class="mark-read-btn bg-primary text-white px-3 py-1.5 text-xs rounded-md hover:bg-blue-700 transition-colors" onclick="markAllAsRead()">Mark All Read</button>
                                    </div>
                                    <div id="notificationList" class="p-0"></div>
                                </div>
                            </div>
                            <div class="user-info flex items-center space-x-2">
                                <span class="user-name text-sm text-white hidden sm:block">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                                <div class="user-avatar p-2 bg-white/20 rounded-full">
                                    <i class="fas fa-user-shield text-white text-sm"></i>
                                </div>
                            </div>
                        </div>
                    </header>
                    <div class="dashboard-content flex-1 p-4 md:p-6 space-y-6">
                        <section id="dashboard-section" class="content-section active">
                            <div class="price-board p-4 rounded-2xl shadow-lg">
                                <div class="price-board-header flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4">
                                    <h2 class="text-white text-lg sm:text-2xl font-semibold flex items-center gap-2">
                                        <i class="fas fa-chart-line"></i>
                                        Dashboard Overview
                                    </h2>
                                </div>

                                <div class="stats-grid grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
                                    <div class="stat-card p-3 rounded-xl shadow-md border border-white/20 flex flex-col items-center justify-center hover:shadow-lg transition duration-300">
                                        <div class="stat-icon p-2 bg-white/20 rounded-lg mb-1">
                                            <i class="fas fa-shopping-cart text-white text-lg"></i>
                                        </div>
                                        <h3 id="totalOrdersAdmin" class="text-2xl font-bold text-white leading-tight">0</h3>
                                        <p class="text-white/80 text-xs text-center">Total Orders</p>
                                    </div>

                                    <div class="stat-card p-3 rounded-xl shadow-md border border-white/20 flex flex-col items-center justify-center hover:shadow-lg transition duration-300">
                                        <div class="stat-icon p-2 bg-white/20 rounded-lg mb-1">
                                            <i class="fas fa-clock text-white text-lg"></i>
                                        </div>
                                        <h3 id="pendingOrdersAdmin" class="text-2xl font-bold text-white leading-tight">0</h3>
                                        <p class="text-white/80 text-xs text-center">Pending Orders</p>
                                    </div>

                                    <div class="stat-card p-3 rounded-xl shadow-md border border-white/20 flex flex-col items-center justify-center hover:shadow-lg transition duration-300">
                                        <div class="stat-icon p-2 bg-white/20 rounded-lg mb-1">
                                            <i class="fas fa-users text-white text-lg"></i>
                                        </div>
                                        <h3 id="totalCustomers" class="text-2xl font-bold text-white leading-tight">0</h3>
                                        <p class="text-white/80 text-xs text-center">Total Customers</p>
                                    </div>

                                    <div class="stat-card p-3 rounded-xl shadow-md border border-white/20 flex flex-col items-center justify-center hover:shadow-lg transition duration-300">
                                        <div class="stat-icon p-2 bg-white/20 rounded-lg mb-1">
                                            <i class="fas fa-peso-sign text-white text-lg"></i>
                                        </div>
                                        <h3 id="totalRevenue" class="text-2xl font-bold text-white leading-tight">₱0.00</h3>
                                        <p class="text-white/80 text-xs text-center">Total Revenue</p>
                                    </div>
                                </div>
                            </div>

                            <div class="dashboard-charts">
                                <div class="chart-container bg-white p-6 rounded-xl shadow-md border border-gray-200">
                                    <h3 class="text-lg font-semibold mb-4 text-gray-900">Orders Overview</h3>
                                    <div class="w-full max-w-md mx-auto" style="height: 300px;">
                                        <canvas id="ordersChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section id="orders-section" class="content-section hidden">
                            <div class="orders-header flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 space-y-2 sm:space-y-0">
                                <h2 class="text-xl font-semibold text-gray-900">Manage Your Orders</h2>
                                <div class="orders-filters flex flex-col sm:flex-row space-x-0 sm:space-x-4 space-y-2 sm:space-y-0">
                                    <select id="adminStatusFilter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="">All Status</option>
                                        <option value="pending">Pending</option>
                                        <option value="in-progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                    <input type="date" id="dateFilter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                            </div>

                            <div class="orders-table overflow-x-auto">
                                <table class="min-w-full bg-white rounded-xl shadow-md border border-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Files</th>
                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address</th>
                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delete Order</th>
                                        </tr>
                                    </thead>
                                    <tbody id="adminOrdersTable" class="divide-y divide-gray-200">
                                        <tr>
                                            <td colspan="12" class="px-4 py-8 text-center text-gray-500">Loading orders...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section id="customers-section" class="content-section hidden">
                            <div class="customers-header flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 space-y-3 sm:space-y-0">
                                <h2 class="text-2xl font-semibold text-gray-800 flex items-center gap-2">
                                    <i class="fas fa-users text-blue-500"></i>
                                    Customer Management
                                </h2>

                                <div class="search-box relative flex-1 max-w-md">
                                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                    <input
                                        type="text"
                                        id="customerSearch"
                                        placeholder="Search customers..."
                                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                    />
                                </div>
                            </div>

                            <div class="customers-table overflow-x-auto">
                                <table class="w-full bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold">
                                        <tr>
                                            <th class="px-5 py-3 text-left"><i class="fas fa-id-card text-blue-500 mr-1"></i>Customer ID</th>
                                            <th class="px-5 py-3 text-left"><i class="fas fa-user text-blue-500 mr-1"></i>Name</th>
                                            <th class="px-5 py-3 text-left"><i class="fas fa-envelope text-blue-500 mr-1"></i>Email</th>
                                            <th class="px-5 py-3 text-left"><i class="fas fa-phone text-blue-500 mr-1"></i>Phone</th>
                                            <th class="px-5 py-3 text-left"><i class="fas fa-shopping-cart text-blue-500 mr-1"></i>Total Orders</th>
                                            <th class="px-5 py-3 text-left"><i class="fas fa-calendar-alt text-blue-500 mr-1"></i>Join Date</th>
                                        </tr>
                                    </thead>

                                    <tbody id="customersTable" class="divide-y divide-gray-100">
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">Loading customers...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <!-- Font Awesome -->
                        <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

                        <section id="reports-section" class="content-section hidden px-3 sm:px-6">
                            <div class="reports-header flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
                                <h2 class="text-lg sm:text-xl font-semibold text-gray-900 text-center sm:text-left w-full sm:w-auto">
                                    Reports & Analytics
                                </h2>

                                <div class="report-filters flex flex-wrap w-full sm:w-auto gap-2 justify-center sm:justify-end">
                                    <select
                                        id="reportType"
                                        class="w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                                    >
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>

                                    <input
                                        type="date"
                                        id="reportDate"
                                        value="<?php echo date('Y-m-d'); ?>"
                                        class="w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                                    />

                                    <button
                                        class="w-full sm:w-auto bg-primary text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors"
                                        id="generateReport"
                                    >
                                        Generate Report
                                    </button>
                                </div>
                            </div>

                            <div class="report-content space-y-6">
                                <!-- Summary Cards -->
                                <div class="report-summary grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6">
                                    <div
                                        class="summary-card bg-white p-4 sm:p-6 rounded-xl shadow-md border border-gray-200 hover:shadow-lg transition-shadow duration-300"
                                    >
                                        <h4 class="text-sm font-medium text-gray-500">Total Orders</h4>
                                        <span id="reportTotalOrders" class="text-2xl sm:text-3xl font-bold text-gray-900">0</span>
                                    </div>

                                    <div
                                        class="summary-card bg-white p-4 sm:p-6 rounded-xl shadow-md border border-gray-200 hover:shadow-lg transition-shadow duration-300"
                                    >
                                        <h4 class="text-sm font-medium text-gray-500">Total Revenue</h4>
                                        <span id="reportTotalRevenue" class="text-2xl sm:text-3xl font-bold text-gray-900">₱0</span>
                                    </div>

                                    <div
                                        class="summary-card bg-white p-4 sm:p-6 rounded-xl shadow-md border border-gray-200 hover:shadow-lg transition-shadow duration-300"
                                    >
                                        <h4 class="text-sm font-medium text-gray-500">New Customers</h4>
                                        <span id="reportNewCustomers" class="text-2xl sm:text-3xl font-bold text-gray-900">0</span>
                                    </div>

                                    <div
                                        class="summary-card bg-white p-4 sm:p-6 rounded-xl shadow-md border border-gray-200 hover:shadow-lg transition-shadow duration-300"
                                    >
                                        <h4 class="text-sm font-medium text-gray-500">Completion Rate</h4>
                                        <span id="reportCompletionRate" class="text-2xl sm:text-3xl font-bold text-gray-900">0%</span>
                                    </div>
                                </div>

                                <!-- Chart -->
                                <div class="report-chart bg-white p-4 sm:p-6 rounded-xl shadow-md border border-gray-200">
                                    <canvas id="reportChart" class="w-full h-48 sm:h-64"></canvas>
                                </div>
                            </div>
                        </section>

                        <section id="settings-section" class="content-section hidden">
                            <div class="settings-container">
                                <h2 class="text-xl font-semibold mb-6 text-gray-900">System Settings</h2>
                                <div class="settings-section bg-white p-6 rounded-xl shadow-md border border-gray-200">
                                    <h3 class="text-lg font-semibold mb-4 text-gray-900">Pricing Configuration</h3>
                                    <form id="pricingForm" class="space-y-4">
                                        <div class="form-row grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="form-group">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Print Black & White (per page)</label>
                                                <input type="number" name="print_bw" step="0.01" value="" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                            </div>
                                            <div class="form-group">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Print Colored (per page)</label>
                                                <input type="number" name="print_color" step="0.01" value="" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                            </div>
                                        </div>
                                        <div class="form-row grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="form-group">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Photocopying (per page)</label>
                                                <input type="number" name="photocopy" step="0.01" value="" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                            </div>
                                            <div class="form-group">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Scanning (per page)</label>
                                                <input type="number" name="scanning" step="0.01" value="" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                            </div>
                                        </div>
                                        <div class="form-row grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="form-group">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Photo Development (per photo)</label>
                                                <input type="number" name="photo-development" step="0.01" value="" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                            </div>
                                            <div class="form-group">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Laminating (per page)</label>
                                                <input type="number" name="laminating" step="0.01" value="" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                            </div>
                                        </div>
                                        <button type="submit" class="bg-primary text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors">Update Pricing</button>
                                    </form>
                                </div>
                            </div>
                        </section>
                        <section id="deleted-transactions-section" class="content-section hidden px-2 sm:px-4 py-4">
                            <!-- Header + Search -->
                            <div class="deleted-header flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                                <h2 class="text-xl font-semibold text-gray-900">Deleted Transactions</h2>

                                <!-- Search Bar -->
                                <div class="relative w-full sm:w-64">
                                    <input
                                        type="text"
                                        id="searchDeleted"
                                        placeholder="Search transaction..."
                                        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                </div>
                            </div>

                            <!-- Table -->
                            <div class="deleted-table w-full">
                                <table
                                    class="w-full bg-white rounded-xl shadow-md border border-gray-200 table-fixed text-sm"
                                >
                                    <thead class="bg-gray-100 text-gray-600 uppercase tracking-wide">
                                        <tr>
                                            <th class="px-2 py-2 text-left font-medium w-1/6">Order. ID</th>
                                            <th class="px-2 py-2 text-left font-medium w-1/6">Customer</th>
                                            <th class="px-2 py-2 text-left font-medium w-1/6">Service</th>
                                            <th class="px-2 py-2 text-center font-medium w-1/6">Qty</th>
                                            <th class="px-2 py-2 text-center font-medium w-1/6">Status</th>
                                            <th class="px-2 py-2 text-center font-medium w-1/6">Deleted At</th>
                                        </tr>
                                    </thead>
                                    <tbody id="deletedTransactionsTable" class="divide-y divide-gray-200">
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">Loading deleted transactions...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                    </div>
                </main>
            </div>
        </div>

        <!-- Toast Container -->
        <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

        <!-- Status Update Modal -->
        <div id="statusModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">Update Order Status</h3>
                <select id="statusSelect" class="w-full p-2 border border-gray-300 rounded-md mb-4">
                    <option value="pending">Pending</option>
                    <option value="in-progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <div class="flex justify-end space-x-2">
                    <button id="cancelStatus" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                    <button id="confirmStatus" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">Update</button>
                </div>
            </div>
        </div>

        <!-- Delete Confirm Modal -->
        <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full mx-4">
                <h3 class="text-lg font-semibold mb-4 text-red-600">Confirm Move to Deleted Transactions</h3>
                <p class="mb-4">Are you sure you want to move this order to deleted transactions? This action cannot be undone and will remove it from active orders but keep a record in deleted transactions.</p>
                <div class="flex justify-end space-x-2">
                    <button id="cancelDelete" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                    <button id="confirmDelete" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">Move to Deleted</button>
                </div>
            </div>
        </div>

        <!-- Improved Specifications Modal -->
        <div id="specsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
            <div class="modal-content bg-white p-0 rounded-2xl shadow-2xl max-w-md w-full mx-4 max-h-[85vh] overflow-hidden">
                <div class="p-6 pb-4">
                    <h3 class="text-xl font-bold mb-2 flex items-center gap-3" id="specsModalTitle">
                        <i class="fas fa-info-circle text-blue-500"></i>
                        <span>Order Specifications</span>
                    </h3>
                    <div id="specsContent" class="mb-4 text-sm leading-relaxed bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <!-- Dynamic content here -->
                    </div>
                </div>
                <div class="flex justify-end p-6 pt-0 border-t border-gray-100 bg-gray-50">
                    <button id="closeSpecs" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors font-medium">
                        <i class="fas fa-times mr-2"></i>Close
                    </button>
                </div>
            </div>
        </div>

        <script>
            let currentOrdersChart = null;
            let currentReportChart = null;
            let currentOrderId = null;

            // Toast Notification Function
            function showToast(message, type = 'info') {
                const toast = document.createElement('div');
                toast.className = `p-4 rounded-lg shadow-lg text-white max-w-sm mx-2 transform transition-all duration-300 ease-in-out opacity-0 translate-x-full ${
                    type === 'success' ? 'bg-green-500' :
                    type === 'error' ? 'bg-red-500' :
                    'bg-blue-500'
                }`;
                toast.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas fa-${
                                type === 'success' ? 'check-circle' :
                                type === 'error' ? 'exclamation-circle' :
                                'info-circle'
                            } mr-2"></i>
                            <span>${message}</span>
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                const container = document.getElementById('toast-container');
                container.appendChild(toast);

                // Slide in animation
                setTimeout(() => {
                    toast.classList.remove('translate-x-full', 'opacity-0');
                    toast.classList.add('translate-x-0', 'opacity-100');
                }, 100);

                // Auto remove after 4 seconds
                setTimeout(() => {
                    toast.classList.add('translate-x-full', 'opacity-0');
                    setTimeout(() => toast.remove(), 300);
                }, 4000);
            }

            // Modal Functions
            function openStatusModal(orderId) {
                currentOrderId = orderId;
                document.getElementById('statusModal').classList.remove('hidden');
                document.getElementById('statusSelect').value = '';
            }

            function closeStatusModal() {
                document.getElementById('statusModal').classList.add('hidden');
                currentOrderId = null;
            }

            function openDeleteModal(orderId) {
                currentOrderId = orderId;
                document.getElementById('deleteModal').classList.remove('hidden');
            }

            function closeDeleteModal() {
                document.getElementById('deleteModal').classList.add('hidden');
                currentOrderId = null;
            }

            function openSpecsModal(event) {
                const btn = event.currentTarget;
                const orderId = btn.dataset.orderId;
                const service = btn.dataset.service || 'Unknown';
                const encodedSpecs = btn.dataset.specs;
                const specs = decodeURIComponent(encodedSpecs);

                // Parse lines for dynamic extraction
                const lines = specs.split('\n').map(l => l.trim()).filter(l => l);
                let size = 'Unknown';
                let type = 'Unknown';
                let lamination = 'No';
                let sizeLabel = 'Size';
                let typeLabel = 'Type';

                for (let line of lines) {
                    if (line.startsWith('Paper Size:')) {
                        size = line.split(':')[1].trim();
                        sizeLabel = 'Paper Size';
                    } else if (line.startsWith('Photo Size:')) {
                        size = line.split(':')[1].trim();
                        sizeLabel = 'Photo Size';
                    } else if (line.startsWith('Print Type:')) {
                        type = line.split(':')[1].trim();
                        typeLabel = 'Print Type';
                    } else if (line.startsWith('Copy Type:')) {
                        type = line.split(':')[1].trim();
                        typeLabel = 'Copy Type';
                    } else if (line.startsWith('Scan Type:')) {
                        type = line.split(':')[1].trim();
                        typeLabel = 'Scan Type';
                    } else if (line.includes('Add Lamination: Yes')) {
                        lamination = 'Yes';
                    }
                }

                // Update modal title with service
                document.getElementById('specsModalTitle').innerHTML = `
                    <span>${service} - Order #${orderId}</span>
                   
                `;

                // Dynamic structured content
                const modalContent = `
                    <div class="space-y-4">
                        <div>
                            <h6 class="font-semibold text-gray-800 mb-2 flex items-center gap-2">
                                <i class="fas fa-file-alt text-gray-500"></i>
                                Full Specifications
                            </h6>
                            <p class="text-sm text-gray-600 italic">${specs || 'No details provided'}</p>
                        </div>
                        <div class="specs-grid">
                            <div class="spec-card size">
                                <div class="spec-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="spec-label">${sizeLabel}</div>
                                <div class="spec-value ${size === 'Unknown' ? 'spec-unknown' : ''}">${size}</div>
                            </div>
                            <div class="spec-card type">
                                <div class="spec-icon">
                                    <i class="fas fa-palette"></i>
                                </div>
                                <div class="spec-label">${typeLabel}</div>
                                <div class="spec-value ${type === 'Unknown' ? 'spec-unknown' : ''}">${type}</div>
                            </div>
                            <div class="spec-card laminating">
                                <div class="spec-icon">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                                <div class="spec-label">Laminating</div>
                                <div class="spec-value ${lamination === 'No' ? 'spec-unknown' : ''}">${lamination}</div>
                            </div>
                        </div>
                    </div>
                `;

                document.getElementById('specsContent').innerHTML = modalContent;
                document.getElementById('specsModal').classList.remove('hidden');

                // Close on outside click
                document.getElementById('specsModal').addEventListener('click', (e) => {
                    if (e.target.id === 'specsModal') closeSpecsModal();
                }, { once: true });
            }

            function closeSpecsModal() {
                document.getElementById('specsModal').classList.add('hidden');
            }

            // FIXED: Enhanced setupModals with validation
            function setupModals() {
                // Status Modal
                document.getElementById('cancelStatus').addEventListener('click', closeStatusModal);
                document.getElementById('confirmStatus').addEventListener('click', async () => {
                    const status = document.getElementById('statusSelect').value;
                    if (!status) {
                        showToast('Please select a status', 'error');
                        return;
                    }
                    // FIXED: Una ang action, tapos close modal
                    await updateOrderStatus(currentOrderId, status);
                    closeStatusModal();
                });

                // Delete Modal
                document.getElementById('cancelDelete').addEventListener('click', closeDeleteModal);
                document.getElementById('confirmDelete').addEventListener('click', async () => {
                    if (!currentOrderId) {
                        showToast('Error: No order selected for deletion', 'error');
                        return;
                    }
                    // FIXED: Una ang action, tapos close modal
                    await deleteOrder(currentOrderId);
                    closeDeleteModal();
                });

                // Specs Modal
                document.getElementById('closeSpecs').addEventListener('click', closeSpecsModal);
                document.getElementById('specsModal').addEventListener('click', (e) => {
                    if (e.target.id === 'specsModal') closeSpecsModal();
                });

                // Close modals on backdrop click
                document.getElementById('statusModal').addEventListener('click', (e) => {
                    if (e.target.id === 'statusModal') closeStatusModal();
                });
                document.getElementById('deleteModal').addEventListener('click', (e) => {
                    if (e.target.id === 'deleteModal') closeDeleteModal();
                });
            }

            // FIXED: Enhanced deleteOrder with validation and logging
            async function deleteOrder(orderId) {
                console.log('🗑️ deleteOrder called with ID:', orderId);  // ← Extra log for debug
                if (!orderId || isNaN(parseInt(orderId))) {
                    console.error('Invalid order ID passed to deleteOrder:', orderId);
                    showToast('Invalid order ID. Please try again.', 'error');
                    return;
                }

                orderId = parseInt(orderId, 10);  // Ensure it's a number
                console.log('🗑️ Attempting to archive order ID:', orderId);

                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_order');
                    formData.append('order_id', orderId);

                    const response = await fetch('admin.php', { 
                        method: 'POST', 
                        body: formData 
                    });

                    if (!response.ok) {
                        throw new Error(`Server error: ${response.status} - ${response.statusText}`);
                    }

                    const data = await response.json();
                    console.log('Archive response:', data);

                    if (data.success) {
                        showToast(data.message, 'success');
                        await loadOrders(); // Reload only on success
                    } else {
                        console.error('Archive failed:', data.error);
                        showToast(data.error || 'Archive failed. Check console.', 'error');
                    }
                } catch (error) {
                    console.error('Archive network/JSON error:', error);
                    showToast('Network error during archive. Check connection.', 'error');
                }
            }

            document.addEventListener('DOMContentLoaded', async () => {
                const searchInput = document.getElementById("searchDeleted");
                const table = document.getElementById("deletedTransactionsTable");

                if (!searchInput || !table) return;

                searchInput.addEventListener("input", () => {
                    const filter = searchInput.value.toLowerCase().trim();
                    const rows = table.getElementsByTagName("tr");

                    for (let i = 0; i < rows.length; i++) {
                        const rowText = rows[i].innerText.toLowerCase();
                        rows[i].style.display = rowText.includes(filter) ? "" : "none";
                    }
                });
                try {
                    const response = await fetch('admin.php?action=verify_admin');
                    const data = await response.json();

                    if (!data.isAdmin) {
                        console.warn('❌ Admin session invalid, redirecting to login...');
                        localStorage.clear();
                        sessionStorage.clear();
                        window.location.href = 'login.php?session_expired=1';
                        return;
                    }

                    console.log('✅ Admin session verified:', data.user);

                    setInterval(async () => {
                        try {
                            const check = await fetch('admin.php?action=verify_admin');
                            const checkData = await check.json();
                            if (!checkData.isAdmin) {
                                showToast('Admin session expired. Please login again.', 'error');
                                setTimeout(() => {
                                    localStorage.clear();
                                    sessionStorage.clear();
                                    window.location.href = 'login.php';
                                }, 1500);
                            }
                        } catch (e) {
                            console.error('Admin session check failed:', e);
                        }
                    }, 300000);
                    // Load notifications
                    loadNotifications();

                    // Poll for new notifications every 30 seconds
                    setInterval(loadNotifications, 30000);
                    // Load dashboard stats on init
                    await loadDashboardStats();
                    // Load pie chart on init
                    await loadOrdersChart();
                    // Setup filters for orders
                    setupOrdersFilters();
                    // Setup customer search
                    setupCustomerSearch();
                    // Setup reports
                    setupReports();
                    // Setup pricing form
                    setupPricingForm();
                    // Setup sidebar navigation
                    setupSidebarNavigation();
                    // Setup mobile toggle
                    setupMobileToggle();
                    // Setup modals
                    setupModals();
                } catch (error) {
                    console.error('❌ Admin verification failed:', error);
                    localStorage.clear();
                    sessionStorage.clear();
                    window.location.href = 'login.php';
                }
            });

            function setupMobileToggle() {
                const toggleBtn = document.getElementById('toggleSidebarMobile');
                const sidebar = document.getElementById('sidebar');
                toggleBtn.addEventListener('click', () => {
                    sidebar.classList.toggle('-translate-x-full');
                });
            }
            function setupSidebarNavigation() {
                const navLinks = document.querySelectorAll('.nav-link');
                const sections = document.querySelectorAll('.content-section');
                const pageTitle = document.getElementById('pageTitle');
                const sidebar = document.getElementById('sidebar');
                const titleMap = {
                    'dashboard': 'Admin Dashboard',
                    'orders': 'Manage Orders',
                    'customers': 'Customer Management',
                    'reports': 'Reports & Analytics',
                    'settings': 'System Settings',
                    'deleted-transactions-section': 'Deleted Transactions'
                };
                navLinks.forEach(link => {
                    link.addEventListener('click', async function(e) {
                        e.preventDefault();
                        const targetSection = this.getAttribute('data-section');

                        // Update active link
                        navLinks.forEach(l => {
                            l.classList.remove('active:bg-white/20', 'active:text-white', 'bg-white/20', 'text-white');
                        });
                        this.classList.add('bg-white/20', 'text-white');

                        // Update page title
                        pageTitle.textContent = titleMap[targetSection] || 'Admin Dashboard';

                        // Show/hide sections
                        sections.forEach(section => {
                            section.classList.add('hidden');
                            section.classList.remove('active');
                            if ((targetSection === 'dashboard' && section.id === 'dashboard-section') ||
                                (targetSection === 'orders' && section.id === 'orders-section') ||
                                (targetSection === 'customers' && section.id === 'customers-section') ||
                                (targetSection === 'reports' && section.id === 'reports-section') ||
                                (targetSection === 'settings' && section.id === 'settings-section') ||
                                (targetSection === 'deleted-transactions-section' && section.id === 'deleted-transactions-section')) {
                                section.classList.remove('hidden');
                                section.classList.add('active');
                            }
                        });

                        // Close sidebar on mobile
                        if (window.innerWidth <= 768) {
                            sidebar.classList.add('-translate-x-full');
                        }

                        // Load section-specific content
                        switch (targetSection) {
                            case 'dashboard':
                                await loadDashboardStats();
                                await loadOrdersChart();
                                break;
                            case 'orders':
                                await loadOrders();
                                break;
                            case 'customers':
                                await loadCustomers();
                                break;
                            case 'reports':
                                generateReport();
                                break;
                            case 'settings':
                                await loadPricing();  // Load dynamic pricing
                                break;
                            case 'deleted-transactions-section':
                                await loadDeletedTransactions();
                                break;
                        }
                    });
                });
                // Close sidebar on mobile
                const closeBtn = document.getElementById('closeSidebar');
                if (closeBtn) {
                    closeBtn.addEventListener('click', () => {
                        sidebar.classList.add('-translate-x-full');
                    });
                }
                window.addEventListener('resize', () => {
                    if (window.innerWidth > 768) {
                        sidebar.classList.remove('-translate-x-full');
                    }
                });
            }
            async function loadDashboardStats() {
                try {
                    const response = await fetch('admin.php?action=dashboard_stats');
                    const data = await response.json();

                    if (data.totalOrders !== undefined) {
                        document.getElementById('totalOrdersAdmin').textContent = data.totalOrders;
                        document.getElementById('pendingOrdersAdmin').textContent = data.pendingOrders;
                        document.getElementById('totalCustomers').textContent = data.totalCustomers;
                        document.getElementById('totalRevenue').innerHTML = '₱' + data.totalRevenue;
                    }
                } catch (error) {
                    console.error('Failed to load dashboard stats:', error);
                }
            }
            async function loadOrdersChart() {
                try {
                    const response = await fetch('admin.php?action=fetch_order_status_counts');
                    const data = await response.json();

                    if (data.success) {
                        const canvas = document.getElementById('ordersChart');
                        const ctx = canvas.getContext('2d');
                        if (currentOrdersChart) {
                            currentOrdersChart.destroy();
                        }
                        currentOrdersChart = new Chart(ctx, {
                            type: 'doughnut', // CHANGED: From 'pie' to 'doughnut' for modern ring design
                            data: {
                                labels: data.labels,
                                datasets: [{
                                    data: data.data,
                                    backgroundColor: [
                                        '#e0be00ff', // Pending: Violet (matches theme)
                                        '#3089f5ff', // In Progress: Light Blue
                                        '#16eb07ff', // Completed: Emerald Green
                                        '#fb2427ff'  // Cancelled: Amber Yellow
                                    ],
                                    borderWidth: 3, // Increased for better definition
                                    borderColor: '#ffffff',
                                    hoverBorderWidth: 4,
                                    hoverBorderColor: '#667eea' // Theme color on hover
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '60%', // CHANGED: For doughnut hole
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: {
                                            padding: 25,
                                            usePointStyle: true, // CHANGED: Icon-style legend
                                            font: {
                                                size: 12
                                            }
                                        }
                                    },
                                    tooltip: { // ENHANCED: Better tooltips
                                        backgroundColor: 'rgba(0,0,0,0.8)',
                                        titleColor: 'white',
                                        bodyColor: 'white',
                                        cornerRadius: 8,
                                        displayColors: true
                                    }
                                },
                                animation: { // ENHANCED: Smooth animations
                                    animateRotate: true,
                                    animateScale: true,
                                    duration: 1500,
                                    easing: 'easeOutQuart'
                                },
                                hover: {
                                    animationDuration: 500
                                }
                            }
                        });
                    }
                } catch (error) {
                    console.error('Failed to load orders chart:', error);
                }
            }
            function setupOrdersFilters() {
                const statusFilter = document.getElementById('adminStatusFilter');
                const dateFilter = document.getElementById('dateFilter');
                statusFilter.addEventListener('change', () => loadOrders(statusFilter.value, dateFilter.value));
                dateFilter.addEventListener('change', () => loadOrders(statusFilter.value, dateFilter.value));
            }
            // FIXED: Enhanced loadOrders with delete button validation and data-label attributes for mobile responsiveness
            async function loadOrders(status = '', date = '') {
                const params = new URLSearchParams({ action: 'fetch_orders' });
                if (status) params.append('status', status);
                if (date) params.append('date', date);
                try {
                    const response = await fetch(`admin.php?${params}`);
                    const data = await response.json();

                    if (data.success) {
                        const tbody = document.getElementById('adminOrdersTable');
                        tbody.innerHTML = ''; // Clear existing rows

                        if (data.orders.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="12" class="px-4 py-8 text-center text-gray-500">No orders found</td></tr>';
                            return;
                        }

                        data.orders.forEach(order => {
                            // Handle defaults
                            if (!order.customer_name) order.customer_name = 'Unknown';
                            if (!order.quantity) order.quantity = 0;
                            const files = order.files === 'No files' ? 'No files' : order.files.split(',').map(f => `<a href="${f.trim()}" target="_blank" class="text-blue-500 underline">View</a>`).join(', ');

                            // Status badge class
                            const statusClass = order.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                            order.status === 'in-progress' ? 'bg-blue-100 text-blue-800' :
                                            order.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';

                            const row = tbody.insertRow();
                            row.innerHTML = `
                                <td data-label="Order ID" class="px-2 py-2"><span class="font-medium">#${order.order_id}</span></td>
                                <td data-label="Customer" class="px-2 py-2 customer-cell">${order.customer_name}</td>
                                <td data-label="Service" class="px-2 py-2 service-cell">${order.service}</td>
                                <td data-label="Quantity" class="px-2 py-2">${order.quantity}</td>
                                <td data-label="Details" class="px-2 py-2 specs-cell">
                                    <button class="view-specs-btn" 
                                            data-order-id="${order.order_id}" 
                                            data-service="${order.service}"
                                            data-specs="${encodeURIComponent(order.specifications || 'No specifications provided')}">
                                        <i class="fas fa-eye mr-1"></i>View Details
                                    </button>
                                </td>
                                <td data-label="Files" class="px-2 py-2 files-cell">${files}</td>
                                <td data-label="Status" class="px-2 py-2 status-cell"><span class="px-2 py-1 text-xs font-semibold rounded-full ${statusClass}">${order.status}</span></td>
                                <td data-label="Date" class="px-2 py-2">${new Date(order.order_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                                <td data-label="Amount" class="px-2 py-2 font-bold text-green-600">₱${parseFloat(order.amount || 0).toFixed(2)}</td>
                                <td data-label="Address" class="px-2 py-2 address-cell">${order.address}</td>
                                <td data-label="Action" class="px-2 py-2 action-cell">
                                    <select class="status-select w-full text-xs p-1 border rounded mb-1" data-order-id="${order.order_id}">
                                        <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                                        <option value="in-progress" ${order.status === 'in-progress' ? 'selected' : ''}>In Progress</option>
                                        <option value="completed" ${order.status === 'completed' ? 'selected' : ''}>Completed</option>
                                        <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                    </select>
                                </td>
                                <td data-label="Delete Order" class="delete-cell">
                                <button class="delete-btn w-full text-xs bg-red-500 text-white py-1 px-2 rounded hover:bg-red-600 transition-colors" data-order-id="${order.order_id}">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </button>
                                    </td>
                            `;

                            // Event listeners for new row elements
                            const statusSelect = row.querySelector('.status-select');
                            if (statusSelect) {
                                statusSelect.addEventListener('change', (e) => updateOrderStatus(e.target.dataset.orderId, e.target.value));
                            }

                            const viewBtn = row.querySelector('.view-specs-btn');
                            if (viewBtn) {
                                viewBtn.addEventListener('click', openSpecsModal);
                            }

                            // FIXED: Enhanced delete button listener with validation
                            const deleteBtn = row.querySelector('.delete-btn');
                            if (deleteBtn) {
                                deleteBtn.addEventListener('click', (e) => {
                                    let orderId = e.currentTarget.dataset.orderId;
                                    console.log('Delete button clicked, raw dataset.orderId:', orderId);

                                    if (!orderId) {
                                        console.error('❌ Order ID missing from button dataset');
                                        showToast('Error: Order ID not found on this row', 'error');
                                        return;
                                    }

                                    orderId = parseInt(orderId, 10);
                                    if (isNaN(orderId) || orderId <= 0) {
                                        console.error('❌ Invalid Order ID parsed:', orderId);
                                        showToast('Error: Invalid Order ID on this row', 'error');
                                        return;
                                    }

                                    console.log('✅ Valid order ID for delete modal:', orderId);
                                    openDeleteModal(orderId);
                                    currentOrderId = orderId;
                                });
                            }
                        });
                    } else {
                        document.getElementById('adminOrdersTable').innerHTML = '<tr><td colspan="12" class="px-4 py-8 text-center text-red-500">Error loading orders</td></tr>';
                    }
                } catch (error) {
                    console.error('Failed to load orders:', error);
                    document.getElementById('adminOrdersTable').innerHTML = '<tr><td colspan="12" class="px-4 py-8 text-center text-red-500">Error loading orders</td></tr>';
                }
            }
            function getStatusClass(status) {
                const classes = {
                    'pending': 'bg-yellow-100 text-yellow-800',
                    'in-progress': 'bg-blue-100 text-blue-800',
                    'completed': 'bg-green-100 text-green-800',
                    'cancelled': 'bg-red-100 text-red-800'
                };
                return classes[status] || 'bg-gray-100 text-gray-800';
            }
            async function updateOrderStatus(orderId, status) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'update_order_status');
                    formData.append('order_id', orderId);
                    formData.append('status', status);
                    const response = await fetch('admin.php', { method: 'POST', body: formData });
                    const data = await response.json();
                    if (data.success) {
                        showToast(data.message, 'success');
                        await loadOrders(); // Reload
                    } else {
                        showToast(data.error, 'error');
                    }
                } catch (error) {
                    console.error('Failed to update status:', error);
                    showToast('Failed to update status. Please try again.', 'error');
                }
            }
            function setupCustomerSearch() {
                const searchInput = document.getElementById('customerSearch');
                searchInput.addEventListener('input', (e) => {
                    const term = e.target.value.toLowerCase();
                    const rows = document.querySelectorAll('#customersTable tr');
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(term) ? '' : 'none';
                    });
                });
            }
            async function loadCustomers() {
                try {
                    const response = await fetch('admin.php?action=fetch_customers');
                    const customers = await response.json();

                    const tbody = document.getElementById('customersTable');
                    if (Array.isArray(customers)) {
                        tbody.innerHTML = customers.map(customer => `
                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                <td data-label="Customer ID" class="px-4 py-3 font-medium">${customer.customer_id}</td>
                                <td data-label="Name" class="px-4 py-3">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-2">
                                            <i class="fas fa-user text-green-600 text-xs"></i>
                                        </div>
                                        ${customer.first_name || ''} ${customer.last_name || ''}
                                    </div>
                                </td>
                                <td data-label="Email" class="px-4 py-3">${customer.email || 'N/A'}</td>
                                <td data-label="Phone" class="px-4 py-3">${customer.phone || 'N/A'}</td>
                                <td data-label="Total Orders" class="px-4 py-3 font-bold text-blue-600">${customer.total_orders || 0}</td>
                                <td data-label="Join Date" class="px-4 py-3">${customer.join_date || 'N/A'}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No customers found</td></tr>';
                    } else {
                        console.error('Error loading customers:', customers.error || 'Unknown error');
                        tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-red-500">Error loading customers. Please try again.</td></tr>';
                        showToast('Failed to load customers. Please refresh the page.', 'error');
                    }
                } catch (error) {
                    console.error('Failed to load customers:', error);
                    const tbody = document.getElementById('customersTable');
                    tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-red-500">Error loading customers. Please try again.</td></tr>';
                    showToast('Failed to load customers. Please refresh the page.', 'error');
                }
            }
            function setupReports() {
                const generateBtn = document.getElementById('generateReport');
                generateBtn.addEventListener('click', generateReport);
            }
            async function generateReport() {
                const reportType = document.getElementById('reportType').value;
                const reportDate = document.getElementById('reportDate').value;
                try {
                    // Load stats
                    const statsResponse = await fetch(`admin.php?action=fetch_report_stats&report_type=${reportType}&report_date=${reportDate}`);
                    const statsData = await statsResponse.json();

                    if (statsData.success) {
                        document.getElementById('reportTotalOrders').textContent = statsData.totalOrders;
                        document.getElementById('reportTotalRevenue').innerHTML = '₱' + statsData.totalRevenue;
                        document.getElementById('reportNewCustomers').textContent = statsData.newCustomers;
                        document.getElementById('reportCompletionRate').textContent = statsData.completionRate + '%';
                    }
                    // Load chart data
                    const chartResponse = await fetch(`admin.php?action=fetch_chart_data&report_type=${reportType}&report_date=${reportDate}`);
                    const chartData = await chartResponse.json();

                    if (chartData.success) {
                        const canvas = document.getElementById('reportChart');
                        const ctx = canvas.getContext('2d');
                        if (currentReportChart) {
                            currentReportChart.destroy();
                        }
                        currentReportChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: chartData.labels,
                                datasets: [
                                    {
                                        label: 'Revenue',
                                        data: chartData.revenueData,
                                        borderColor: '#36A2EB',
                                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                        tension: 0.1
                                    },
                                    {
                                        label: 'Orders',
                                        data: chartData.ordersData,
                                        borderColor: '#FF6384',
                                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                        tension: 0.1,
                                        yAxisID: 'y1'
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        type: 'linear',
                                        display: true,
                                        position: 'left',
                                    },
                                    y1: {
                                        type: 'linear',
                                        display: true,
                                        position: 'right',
                                        grid: {
                                            drawOnChartArea: false,
                                        },
                                    },
                                }
                            }
                        });
                    }
                } catch (error) {
                    console.error('Failed to generate report:', error);
                }
            }
            async function loadPricing() {
                try {
                    const response = await fetch('admin.php?action=load_pricing');
                    const data = await response.json();
                    if (data.success) {
                        const form = document.getElementById('pricingForm');
                        form.querySelector('input[name="print_bw"]').value = data.pricing.print_bw || 1.00;
                        form.querySelector('input[name="print_color"]').value = data.pricing.print_color || 2.50;
                        form.querySelector('input[name="photocopy"]').value = data.pricing.photocopy || 1.50;
                        form.querySelector('input[name="scanning"]').value = data.pricing.scanning || 3.00;
                        form.querySelector('input[name="photo-development"]').value = data.pricing['photo-development'] || 15.00;
                        form.querySelector('input[name="laminating"]').value = data.pricing.laminating || 5.00;
                    }
                } catch (error) {
                    console.error('Failed to load pricing:', error);
                }
            }
            function setupPricingForm() {
                const form = document.getElementById('pricingForm');
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(form);
                    formData.append('action', 'update_pricing');
                    try {
                        const response = await fetch('admin.php', { method: 'POST', body: formData });
                        const data = await response.json();
                        if (data.success) {
                            showToast(data.message, 'success');
                            // Reload to confirm
                            await loadPricing();
                        } else {
                            showToast(data.error, 'error');
                        }
                    } catch (error) {
                        console.error('Failed to update pricing:', error);
                        showToast('Failed to update pricing. Please try again.', 'error');
                    }
                });
            }
            async function loadDeletedTransactions() {
                try {
                    const response = await fetch('admin.php?action=fetch_deleted_orders');
                    const data = await response.json();

                    if (data.success) {
                        const tbody = document.getElementById('deletedTransactionsTable');
                        tbody.innerHTML = data.orders.map(order => `
                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                <td data-label="Order ID" class="px-2 py-2 font-medium text-left">${order.order_id}</td>
                                <td data-label="Customer" class="px-2 py-2 text-left break-words">${order.customer_name || 'Unknown'}</td>
                                <td data-label="Service" class="px-2 py-2 text-left break-words">${order.service}</td>
                                <td data-label="Quantity" class="px-2 py-2 font-bold text-orange-600 text-center">${order.quantity}</td>
                                <td data-label="Status" class="px-2 py-2 text-center">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full ${getStatusClass(order.status)}">${order.status}</span>
                                </td>
                                <td data-label="Deleted At" class="px-2 py-2 text-center">${new Date(order.deleted_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' })}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No deleted transactions</td></tr>';
                    }
                } catch (error) {
                    console.error('Failed to load deleted transactions:', error);
                }
            }
            async function loadNotifications() {
                try {
                    const response = await fetch('admin.php?action=fetch_notifications');
                    const data = await response.json();

                    if (data.success) {
                        const badge = document.getElementById('notificationBadge');
                        const dropdown = document.getElementById('notificationDropdown');
                        const list = document.getElementById('notificationList');

                        badge.textContent = data.unreadCount;
                        badge.classList.toggle('hidden', data.unreadCount <= 0);

                        if (data.notifications.length === 0) {
                            list.innerHTML = '<div class="p-5 text-center text-gray-500">No new notifications</div>';
                        } else {
                            list.innerHTML = data.notifications.map(notif => `
                                <div class="notification-item p-3 border-b border-gray-100 cursor-pointer hover:bg-gray-50 transition-colors duration-200 ${notif.status === 'pending' ? 'bg-blue-50 border-blue-200' : ''}" onclick="markAsRead(${notif.order_id})">
                                    <strong class="block text-sm font-medium text-gray-900">${notif.message}</strong>
                                    <small class="text-xs text-gray-500">Order #${notif.order_id} • ${notif.service} (${notif.quantity}) • ${notif.status}</small>
                                </div>
                            `).join('');
                        }
                    }
                } catch (error) {
                    console.error('Failed to load notifications:', error);
                }
            }
            function markAsRead(orderId) {
                console.log(`Marking order ${orderId} as read`);
                // Implement actual mark as read if backend supports
                document.getElementById('notificationDropdown').classList.add('hidden');
                window.location.href = 'admin.php#orders';
            }
            function markAllAsRead() {
                console.log('Marking all as read');
                document.getElementById('notificationList').innerHTML = '<div class="p-5 text-center text-gray-500">All notifications marked as read</div>';
                document.getElementById('notificationBadge').classList.add('hidden');
                document.getElementById('notificationDropdown').classList.add('hidden');
            }
            function toggleNotifications() {
                const dropdown = document.getElementById('notificationDropdown');
                dropdown.classList.toggle('hidden');
            }
            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                const bell = document.getElementById('notificationBell');
                const dropdown = document.getElementById('notificationDropdown');
                if (!bell.contains(e.target) && !dropdown.classList.contains('hidden')) {
                    dropdown.classList.add('hidden');
                }
            });
        </script>
    </body>
    </html>