<?php
// fetch_pricing.php - API endpoint for fetching current pricing
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Fetch latest pricing from database
    $stmt = $pdo->query("SELECT * FROM pricing WHERE id = 1");
    $pricing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pricing) {
        // Return default pricing if no data found
        $pricing = [
            'print' => 2.00,
            'photocopy' => 1.50,
            'scanning' => 3.00,
            'photo-development' => 15.00,
            'laminating' => 5.00,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    } else {
        // Add timestamp for cache-busting
        $pricing['last_updated'] = date('Y-m-d H:i:s');
    }
    
    echo json_encode($pricing);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Failed to fetch pricing'
    ]);
}
?>