<?php
require_once 'config.php';
header('Content-Type: application/json');

if (isset($_POST['order_id'])) {
    $orderId = $_POST['order_id'];

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE orders SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$orderId]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
}

?>
