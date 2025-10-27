<?php
/**
 * fetch_pricing.php - Real-time Pricing API Endpoint
 * Returns current pricing for all services from the database
 * Used by user dashboard for automatic price updates
 */

// Set headers for JSON response and prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *'); // Allow cross-origin if needed

require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Fetch current pricing from database
    $stmt = $pdo->prepare("
        SELECT 
            print_bw,
            print_color,
            photocopying,
            scanning,
            photo_development,
            laminating,
            last_updated
        FROM pricing 
        WHERE id = 1
        LIMIT 1
    ");
    
    $stmt->execute();
    $pricing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pricing) {
        // Return default values if no pricing row exists
        echo json_encode([
            'print_bw' => 1.00,
            'print_color' => 2.00,
            'photocopying' => 2.00,
            'scanning' => 5.00,
            'photo_development' => 15.00,
            'laminating' => 20.00,
            'last_updated' => date('Y-m-d H:i:s'),
            'source' => 'defaults'
        ]);
        exit;
    }
    
    // Convert all prices to floats for consistency
    $pricing['print_bw'] = (float)$pricing['print_bw'];
    $pricing['print_color'] = (float)$pricing['print_color'];
    $pricing['photocopying'] = (float)$pricing['photocopying'];
    $pricing['scanning'] = (float)$pricing['scanning'];
    $pricing['photo_development'] = (float)$pricing['photo_development'];
    $pricing['laminating'] = (float)$pricing['laminating'];
    $pricing['source'] = 'database';
    
    // Return pricing data
    echo json_encode($pricing, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>