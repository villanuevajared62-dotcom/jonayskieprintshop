<?php
// Simple script to check if orders table exists and has data
require_once 'config.php';

// Get database connection
$pdo = getDBConnection();

if (!$pdo) {
    die("Database connection failed!");
}

try {
    // Check if orders table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
    $tableExists = $stmt->rowCount() > 0;
    
    echo "<h2>Database Check Results:</h2>";
    echo "Database connection: <strong>SUCCESS</strong><br>";
    echo "Orders table exists: <strong>" . ($tableExists ? "YES" : "NO") . "</strong><br>";
    
    if ($tableExists) {
        // Count orders
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "Number of orders in database: <strong>{$count}</strong><br>";
        
        // Show sample orders
        if ($count > 0) {
            $stmt = $pdo->query("SELECT * FROM orders LIMIT 5");
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>Sample Orders:</h3>";
            echo "<pre>";
            print_r($orders);
            echo "</pre>";
        } else {
            echo "<p>No orders found in the database.</p>";
        }
        
        // Show table structure
        $stmt = $pdo->query("DESCRIBE orders");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Orders Table Structure:</h3>";
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>