<?php
// Simple script to test the fetch_orders API directly
require_once 'config.php';

// Get database connection
$pdo = getDBConnection();

if (!$pdo) {
    die("Database connection failed!");
}

try {
    // Create orders table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        service VARCHAR(255) NOT NULL,
        order_date DATE NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00
    )");
    
    // Check if orders table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Insert sample data if table is empty
    if ($count == 0) {
        $pdo->exec("INSERT INTO orders (customer_id, service, order_date, status, total_amount) VALUES 
            (1, 'Printing', '2023-10-01', 'completed', 150.00),
            (2, 'Banner', '2023-10-02', 'pending', 300.00),
            (3, 'Business Cards', '2023-10-03', 'processing', 75.50)");
        echo "<p>Sample orders added to database.</p>";
    }
    
    // Simulate the fetch_orders API call
    $query = "SELECT * FROM orders";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Display results
    echo "<h2>API Test Results:</h2>";
    echo "<h3>Orders from Database:</h3>";
    echo "<pre>";
    print_r($orders);
    echo "</pre>";
    
    // Output as JSON (like the API would)
    echo "<h3>JSON Response:</h3>";
    echo "<pre>";
    echo json_encode($orders, JSON_PRETTY_PRINT);
    echo "</pre>";
    
    // Provide a link to test the actual API
    echo "<p><a href='admin.php?action=fetch_orders' target='_blank'>Test actual API endpoint</a></p>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>