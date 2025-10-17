<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request');
    }

    $orderId = $_POST['order_id'] ?? null;
    $quantity = $_POST['quantity'] ?? null;

    if (!$orderId || !$quantity) {
        throw new Exception('Missing data');
    }

    // Update order in database
    $stmt = $pdo->prepare("UPDATE orders SET quantity = :quantity WHERE order_id = :order_id");
    $stmt->execute([
        ':quantity' => $quantity,
        ':order_id' => $orderId
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
