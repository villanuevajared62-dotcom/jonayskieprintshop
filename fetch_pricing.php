<?php
require_once 'config.php';
header('Content-Type: application/json');

if (isset($_POST['order_id'])) {
    $orderId = $_POST['order_id'];

    try {
        $pdo = getDBConnection();
        
        // Start transaction
        $pdo->beginTransaction();
        
        // 1. Mark order as deleted in orders table
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET is_deleted = 1, deleted_at = NOW() 
            WHERE id = ? AND is_deleted = 0
        ");
        $result = $stmt->execute([$orderId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Order not found or already deleted');
        }
        
        // 2. Optionally copy to deleted_orders table for archive
        $stmt = $pdo->prepare("
            INSERT INTO deleted_orders (
                order_id, user_id, service, quantity, status, specifications, 
                amount, created_at, deleted_at
            )
            SELECT 
                id, user_id, service, quantity, status, specifications,
                amount, created_at, NOW()
            FROM orders 
            WHERE id = ?
        ");
        $stmt->execute([$orderId]);
        
        $pdo->commit();
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
}
?>