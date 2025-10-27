<?php
require_once 'config.php';
header('Content-Type: application/json');

session_name('admin_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Quick session check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

if (isset($_POST['order_id'])) {
    $orderId = (int)$_POST['order_id'];

    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        // Fetch order data for archiving (using only columns present in deleted_orders)
        $stmt = $pdo->prepare("
            SELECT id, user_id, service, quantity, specifications, created_at 
            FROM orders WHERE id = ? AND is_deleted = 0
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Order not found or already deleted']);
            exit;
        }

        // Archive to deleted_orders
        $stmt = $pdo->prepare("
            INSERT INTO deleted_orders (order_id, user_id, service, quantity, specifications, created_at, deleted_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $order['id'],
            $order['user_id'],
            $order['service'],
            $order['quantity'],
            $order['specifications'],
            $order['created_at']
        ]);

        // Mark as deleted in orders table
        $stmt = $pdo->prepare("UPDATE orders SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$orderId]);

        // Delete associated files (optional, based on your needs)
        $stmt = $pdo->prepare("DELETE FROM order_files WHERE order_id = ?");
        $stmt->execute([$orderId]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Order archived and marked as deleted']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
}
?>