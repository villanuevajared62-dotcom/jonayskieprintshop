  <?php
  // admin.php - SECURE ADMIN DASHBOARD WITH ENHANCED SECURITY

  session_name('admin_session'); // Unique name for admin
  if (session_status() === PHP_SESSION_NONE) {
      session_start();
  }


 // ✅ CORRECTED: Fetch deleted orders (put this after session validation)
if (isset($_GET['action']) && $_GET['action'] === 'fetch_deleted_orders') {
    try {
        require_once 'config.php';
        $pdo = getDBConnection();
        
        // Option A: Fetch from orders table (soft delete)
        $stmt = $pdo->prepare("
            SELECT 
                o.id AS order_id,
                CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
                o.service,
                o.quantity,
                o.status,
                o.created_at AS order_date,
                o.deleted_at
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.is_deleted = 1
            ORDER BY o.deleted_at DESC
        ");
        
        // Option B: If using deleted_orders archive table, use this instead:
        /*
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
        */
        
        $stmt->execute();
        $deletedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendJson(['success' => true, 'orders' => $deletedOrders]);
    } catch (PDOException $e) {
        sendJson(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

  // ============================================
  // 🔒 STRICT ADMIN SESSION VALIDATION
  // ============================================
  if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
      // NO VALID SESSION - COMPLETE CLEANUP
      if (session_status() == PHP_SESSION_ACTIVE) {
          session_destroy();
      }
      setcookie(session_name(), '', time() - 3600, '/');
      header('Location:login_admin.php?reason=no_session');
      exit;
  }

  // STRICT ADMIN CHECK ONLY
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

  // REGENERATE SESSION ID FOR SECURITY (every 30 mins)
  if (!isset($_SESSION['last_regen']) || (time() - $_SESSION['last_regen'] > 3600)) { // 1 HOUR instead of 30 mins
      session_regenerate_id(true);
      $_SESSION['last_regen'] = time();
  }

  // 🚨 PREVENT BACK BUTTON CACHING
  header('Cache-Control: no-cache, no-store, must-revalidate');
  header('Pragma: no-cache');
  header('Expires: 0');


  // Show errors for debugging (remove on production)
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);

  require_once 'config.php';
  $pdo = getDBConnection();

  if (!$pdo) {
      die("Database connection failed. Please check your configuration.");
  }

  // SYNC ADMIN SESSION DATA
  if (!isset($_SESSION['user_name'])) {
      $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ? AND role = 'admin'");
      $stmt->execute([$_SESSION['user_id']]);
      $admin = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($admin) {
          $_SESSION['user_name'] = trim($admin['first_name'] . ' ' . $admin['last_name']);
          $_SESSION['user_email'] = $admin['email'];
      }
  }

  // ------------------ AJAX HANDLERS ------------------

  // Helper function to return JSON
  function sendJson($data) {
      header('Content-Type: application/json');
      echo json_encode($data);
      exit;
  }

  // ✅ NEW: ADMIN SESSION VERIFICATION ENDPOINT
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

  // Fetch dashboard stats (YOUR ORIGINAL CODE - UNCHANGED)
  if (isset($_GET['action']) && $_GET['action'] === 'dashboard_stats') {
      try {
          $totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
          $pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
          $totalCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();

          $todayRevenue = $pdo->query("
              SELECT SUM(
                  CASE 
                      WHEN service='print' THEN quantity*2.00
                      WHEN service='photocopy' THEN quantity*1.50
                      WHEN service='scanning' THEN quantity*3.00
                      WHEN service='photo-development' THEN quantity*15.00
                      WHEN service='laminating' THEN quantity*5.00
                      ELSE 0
                  END
              ) FROM orders WHERE DATE(created_at) = CURDATE()
          ")->fetchColumn() ?? 0;

          sendJson([
              'totalOrders' => (int)$totalOrders,
              'pendingOrders' => (int)$pendingOrders,
              'totalCustomers' => (int)$totalCustomers,
              'todayRevenue' => number_format($todayRevenue, 2)
          ]);
      } catch (PDOException $e) {
          sendJson(['error' => $e->getMessage()]);
      }
  }

  // Fetch orders (YOUR ORIGINAL CODE - UNCHANGED)
  // ✅ FETCH ORDERS WITH SPECIFICATIONS + FILES
  // admin.php – fetch orders endpoint

  if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
      header('HTTP/1.1 403 Forbidden');
      exit('Access denied');
  }



  if (isset($_GET['action']) && $_GET['action'] === 'fetch_orders') {
      try {
          require_once 'config.php';
          $pdo = getDBConnection();

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
                  o.delivery_address AS address,
    
                
                  (CASE 
                      WHEN o.service='print' THEN o.quantity*2.00
                      WHEN o.service='photocopy' THEN o.quantity*1.50
                      WHEN o.service='scanning' THEN o.quantity*3.00
                      WHEN o.service='photo-development' THEN o.quantity*15.00
                      WHEN o.service='laminating' THEN o.quantity*5.00
                      ELSE 0
                  END) AS amount,
                  GROUP_CONCAT(f.filepath) AS files
              FROM orders o
              LEFT JOIN users u ON o.user_id = u.id
              LEFT JOIN order_files f ON o.id = f.order_id
              WHERE 1=1
              
              
          ";

          if ($status) $query .= " AND o.status = :status";
          if ($date) $query .= " AND DATE(o.created_at) = :date";

          $query .= " GROUP BY o.id ORDER BY o.created_at DESC";

          $stmt = $pdo->prepare($query);

          if ($status) $stmt->bindParam(':status', $status);
          if ($date) $stmt->bindParam(':date', $date);

          $stmt->execute();
          $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

          sendJson(['success' => true, 'orders' => $orders]);
      } catch (PDOException $e) {
          sendJson(['success' => false, 'error' => $e->getMessage()]);
      }
  }

  // Update pricing (YOUR ORIGINAL CODE - UNCHANGED)
  // Fetch customers (CUSTOMERS SECTION)
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
              LEFT JOIN orders o ON u.id = o.user_id
              WHERE u.role = 'customer'
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

  // Update order status (ORDERS SECTION)
  if (isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
      try {
          $order_id = $_POST['order_id'] ?? 0;
          $status = $_POST['status'] ?? '';
          
          if (!$order_id || !$status) {
              sendJson(['success' => false, 'error' => 'Missing order ID or status']);
          }
          
          $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
          $stmt->execute([$status, $order_id]);
          
          sendJson(['success' => true, 'message' => 'Order status updated successfully']);
      } catch (PDOException $e) {
          sendJson(['success' => false, 'error' => $e->getMessage()]);
      }
  }

  // Delete order (ORDERS SECTION - optional)
  if (isset($_POST['action']) && $_POST['action'] === 'delete_order') {
      try {
          $order_id = $_POST['order_id'] ?? 0;
          
          if (!$order_id) {
              sendJson(['success' => false, 'error' => 'Missing order ID']);
          }
          
          // Delete order files first
          $stmt = $pdo->prepare("DELETE FROM order_files WHERE order_id = ?");
          $stmt->execute([$order_id]);
          
          // Delete order
          $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
          $stmt->execute([$order_id]);
          
          sendJson(['success' => true, 'message' => 'Order deleted successfully']);
      } catch (PDOException $e) {
          sendJson(['success' => false, 'error' => $e->getMessage()]);
      }
  }
    if (isset($_POST['action']) && $_POST['action'] === 'update_pricing') {
      try {
          $print = $_POST['print'] ?? 0;
          $photocopy = $_POST['photocopy'] ?? 0;
          $scanning = $_POST['scanning'] ?? 0;
          $photoDev = $_POST['photo-development'] ?? 0;
          $laminating = $_POST['laminating'] ?? 0;

          // Update pricing in database
          $stmt = $pdo->prepare("
              UPDATE pricing 
              SET 
                  print = :print, 
                  photocopy = :photocopy, 
                  scanning = :scanning,
                  `photo-development` = :photoDev, 
                  laminating = :laminating,
                  last_updated = NOW()
              WHERE id = 1
          ");
          
          $stmt->execute([
              ':print' => $print,
              ':photocopy' => $photocopy,
              ':scanning' => $scanning,
              ':photoDev' => $photoDev,
              ':laminating' => $laminating
          ]);

          // ✅ SUCCESS RESPONSE
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

  // ============== REPORTS HANDLERS (YOUR ORIGINAL CODE - UNCHANGED) ==============

  // Fetch report stats
  if (isset($_GET['action']) && $_GET['action'] === 'fetch_report_stats') {
      try {
          $reportType = $_GET['report_type'] ?? 'daily';
          $reportDate = $_GET['report_date'] ?? date('Y-m-d');
          
          $dateCondition = '';
          $params = [':date' => $reportDate];
          
          switch($reportType) {
              case 'daily':
                  $dateCondition = "DATE(created_at) = :date";
                  break;
              case 'weekly':
                  $dateCondition = "YEARWEEK(created_at, 1) = YEARWEEK(:date, 1)";
                  break;
              case 'monthly':
                  $dateCondition = "YEAR(created_at) = YEAR(:date) AND MONTH(created_at) = MONTH(:date)";
                  break;
              default:
                  $dateCondition = "DATE(created_at) = :date";
          }
          
          $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $dateCondition");
          $stmt->execute($params);
          $totalOrders = $stmt->fetchColumn();
          
          $stmt = $pdo->prepare("
              SELECT SUM(
                  CASE 
                      WHEN service='print' THEN quantity*2.00
                      WHEN service='photocopy' THEN quantity*1.50
                      WHEN service='scanning' THEN quantity*3.00
                      WHEN service='photo-development' THEN quantity*15.00
                      WHEN service='laminating' THEN quantity*5.00
                      ELSE 0
                  END
              ) FROM orders WHERE $dateCondition
          ");
          $stmt->execute($params);
          $totalRevenue = $stmt->fetchColumn() ?? 0;
          
          $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='customer' AND $dateCondition");
          $stmt->execute($params);
          $newCustomers = $stmt->fetchColumn();
          
          $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status='completed' AND $dateCondition");
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

  // Fetch chart data for reports (YOUR ORIGINAL CODE - UNCHANGED)
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
                      SELECT SUM(
                          CASE 
                              WHEN service='print' THEN quantity*2.00
                              WHEN service='photocopy' THEN quantity*1.50
                              WHEN service='scanning' THEN quantity*3.00
                              WHEN service='photo-development' THEN quantity*15.00
                              WHEN service='laminating' THEN quantity*5.00
                              ELSE 0
                          END
                      ), COUNT(*)
                      FROM orders 
                      WHERE DATE(created_at) = :date AND HOUR(created_at) = :hour
                  ");
                  $stmt->execute([':date' => $reportDate, ':hour' => $hour]);
                  $result = $stmt->fetch(PDO::FETCH_NUM);
                  
                  $revenueData[] = (float)($result[0] ?? 0);
                  $ordersData[] = (int)($result[1] ?? 0);
              }
          } elseif ($reportType === 'weekly') {
              $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
              $labels = $days;
              
              for ($i = 0; $i < 7; $i++) {
                  $dayOfWeek = ($i + 2) % 7;
                  if ($dayOfWeek === 0) $dayOfWeek = 7;
                  
                  $stmt = $pdo->prepare("
                      SELECT SUM(
                          CASE 
                              WHEN service='print' THEN quantity*2.00
                              WHEN service='photocopy' THEN quantity*1.50
                              WHEN service='scanning' THEN quantity*3.00
                              WHEN service='photo-development' THEN quantity*15.00
                              WHEN service='laminating' THEN quantity*5.00
                              ELSE 0
                          END
                      ), COUNT(*)
                      FROM orders 
                      WHERE YEARWEEK(created_at, 1) = YEARWEEK(:date, 1)
                      AND DAYOFWEEK(created_at) = :dayofweek
                  ");
                  $stmt->execute([':date' => $reportDate, ':dayofweek' => $dayOfWeek]);
                  $result = $stmt->fetch(PDO::FETCH_NUM);
                  
                  $revenueData[] = (float)($result[0] ?? 0);
                  $ordersData[] = (int)($result[1] ?? 0);
              }
          } elseif ($reportType === 'monthly') {
              $daysInMonth = date('t', strtotime($reportDate));
              $year = date('Y', strtotime($reportDate));
              $month = date('m', strtotime($reportDate));
              
              for ($day = 1; $day <= $daysInMonth; $day++) {
                  $labels[] = (string)$day;
                  
                  $currentDate = sprintf('%s-%s-%02d', $year, $month, $day);
                  $stmt = $pdo->prepare("
                      SELECT SUM(
                          CASE 
                              WHEN service='print' THEN quantity*2.00
                              WHEN service='photocopy' THEN quantity*1.50
                              WHEN service='scanning' THEN quantity*3.00
                              WHEN service='photo-development' THEN quantity*15.00
                              WHEN service='laminating' THEN quantity*5.00
                              ELSE 0
                          END
                      ), COUNT(*)
                      FROM orders 
                      WHERE DATE(created_at) = :date
                  ");
                  $stmt->execute([':date' => $currentDate]);
                  $result = $stmt->fetch(PDO::FETCH_NUM);
                  
                  $revenueData[] = (float)($result[0] ?? 0);
                  $ordersData[] = (int)($result[1] ?? 0);
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
    
    <!-- 🚨 PREVENT BACK BUTTON CACHING -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <title>Admin Dashboard - Jonayskie Prints</title>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Your CSS -->
    <link rel="stylesheet" href="./css/style.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- ✅ CLIENT-SIDE ADMIN VERIFICATION -->
    <script>
      // CHECK ADMIN SESSION ON PAGE LOAD
      document.addEventListener('DOMContentLoaded', async () => {
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
              
              // PERIODIC SESSION CHECK EVERY 5 MINS
              setInterval(async () => {
                  try {
                      const check = await fetch('admin.php?action=verify_admin');
                      const checkData = await check.json();
                      if (!checkData.isAdmin) {
                          alert('Admin session expired. Please login again.');
                          localStorage.clear();
                          sessionStorage.clear();
                          window.location.href = 'login.php';
                      }
                  } catch (e) {
                      console.error('Admin session check failed:', e);
                  }
              }, 300000); // 5 minutes
              
          } catch (error) {
              console.error('❌ Admin verification failed:', error);
              localStorage.clear();
              sessionStorage.clear();
              window.location.href = 'login.php';
          }
      });

      
    // FETCH DELETED ORDERS

<?php
// Deleted Orders Endpoint
if (isset($_GET['action']) && $_GET['action'] === 'fetch_deleted_orders') {
    require_once 'config.php';
    $pdo = getDBConnection();

    header('Content-Type: application/json'); 

    try {
        $stmt = $pdo->prepare("
            SELECT 
                d.order_id,
                CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
                d.service,
                d.quantity,
                d.status,
                d.deleted_at
            FROM deleted_orders d
            LEFT JOIN users u ON d.user_id = u.id
            ORDER BY d.deleted_at DESC
        ");
        $stmt->execute();
        $deletedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'orders' => $deletedOrders]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit; // important: stop loading the rest of the page
}

?>

      
    </script>
  </head>
  <body>
    <div class="dashboard-container">
      <!-- Sidebar (YOUR ORIGINAL HTML - UNCHANGED) -->
      <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
          <div class="logo">
            <i class="fas fa-print"></i>
            <span>Admin Panel</span>
          </div>
          <button class="close-sidebar-btn" id="closeSidebar">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <nav class="sidebar-nav">
          <ul>
            <li>
              <a href="#dashboard" class="nav-link active" data-section="dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
              </a>
            </li>
            <li>
              <a href="#orders" class="nav-link" data-section="orders">
                <i class="fas fa-list-alt"></i>
                <span>Manage Orders</span>
              </a>
            </li>
            <li>
              <a href="#customers" class="nav-link" data-section="customers">
                <i class="fas fa-users"></i>
                <span>Customers</span>
              </a>
            </li>
            <li>
              <a href="#reports" class="nav-link" data-section="reports">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
              </a>
            </li>
            <li>
              <a href="#settings" class="nav-link" data-section="settings">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
              </a>
            </li>
            <li>
   <a href="#deleted-transactions-section" class="nav-link" data-section="deleted-transactions-section">
  <i class="fas fa-trash-alt"></i>
  <span>Deleted Transactions</span>
</a>

  </li>
          </ul>
        </nav>
        
        <div class="sidebar-footer">
          <a href="login_admin.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </div>
      </aside>

      <!-- Main Content (YOUR ORIGINAL HTML - UNCHANGED) -->
      <main class="main-content">
        <header class="dashboard-header">
          <div class="header-left">
            <!-- <button class="sidebar-toggle" id="sidebarToggle">
              <i class="fas fa-bars"></i>
            </button> -->
            <h1 id="pageTitle">Admin Dashboard</h1>
          </div>
          <div class="header-right">
            <div class="user-info">
              <span class="user-name">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
              <div class="user-avatar">
                <i class="fas fa-user-shield"></i>
              </div>
            </div>
          </div>
        </header>

        <div class="dashboard-content">
          <!-- Dashboard Section (YOUR ORIGINAL HTML - UNCHANGED) -->
          <section id="dashboard-section" class="content-section active">
            <div class="stats-grid">
              <div class="stat-card">
                <div class="stat-icon">
                  <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-info">
                  <h3 id="totalOrdersAdmin">0</h3>
                  <p>Total Orders</p>
                </div>
              </div>
              <div class="stat-card">
                <div class="stat-icon">
                  <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                  <h3 id="pendingOrdersAdmin">0</h3>
                  <p>Pending Orders</p>
                </div>
              </div>
              <div class="stat-card">
                <div class="stat-icon">
                  <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                  <h3 id="totalCustomers">0</h3>
                  <p>Total Customers</p>
                </div>
              </div>
              <div class="stat-card">
                <div class="stat-icon">
                  <i class="fas fa-peso-sign"></i>
                </div>
                <div class="stat-info">
                  <h3 id="todayRevenue">₱0.00</h3>
                  <p>Today's Revenue</p>
                </div>
              </div>
            </div>

            <div class="dashboard-charts">
              <div class="chart-container">
                <h3>Orders Overview</h3>
                <canvas id="ordersChart"></canvas>
              </div>
              <div class="chart-container">
                <h3>Revenue Trend</h3>
                
                <canvas id="revenueChart"></canvas>
              </div>
            </div>
          </section>

          

          <!-- Orders Section (YOUR ORIGINAL HTML - UNCHANGED) -->
          <section id="orders-section" class="content-section">
            <div class="orders-header">
              <h2>Manage Orders</h2>
              <div class="orders-filters">
                <select id="adminStatusFilter">
                  <option value="">All Status</option>
                  <option value="pending">Pending</option>
                  <option value="in-progress">In Progress</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
                <input type="date" id="dateFilter">
              </div>
            </div>
            <div class="orders-table">
              <table>
                <thead>
                  <tr>
        <th>Order ID</th>
        <th>Customer</th>
        <th>Service</th>
        <th>Quantity</th>
        <th>Specifications</th> <!-- ✅ Added -->
        <th>Files</th>          <!-- ✅ Added -->
        <th>Status</th>
        <th>Date</th>
        <th>Amount</th>
        <th>Address</th>
        <!-- <th>Address</th> -->
        
        
      </tr>
                </thead>
                <tbody id="adminOrdersTable">
                  <tr>
                    <td colspan="7" class="no-data">Loading orders...</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <!-- Customers Section (YOUR ORIGINAL HTML - UNCHANGED) -->
          <section id="customers-section" class="content-section">
            <div class="customers-header">
              <h2>Customer Management</h2>
              <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="customerSearch" placeholder="Search customers...">
              </div>
            </div>
            <div class="customers-table">
              <table>
                <thead>
                  <tr>
                    <th>Customer ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Total Orders</th>
                    <th>Join Date</th>
                  </tr>
                </thead>
                <tbody id="customersTable">
                  <tr>
                    <td colspan="6" class="no-data">Loading customers...</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <!-- Reports Section (YOUR ORIGINAL HTML - UNCHANGED) -->
          <section id="reports-section" class="content-section">
            <div class="reports-header">
              <h2>Reports & Analytics</h2>
              <div class="report-filters">
                <select id="reportType">
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                </select>
                <input type="date" id="reportDate" value="<?php echo date('Y-m-d'); ?>">
                <button class="btn btn-primary" id="generateReport">Generate Report</button>
              </div>
            </div>
            <div class="report-content" id="reportContent">
              <div class="report-summary">
                <div class="summary-card">
                  <h4>Total Orders</h4>
                  <span id="reportTotalOrders">0</span>
                </div>
                <div class="summary-card">
                  <h4>Total Revenue</h4>
                  <span id="reportTotalRevenue">₱0</span>
                </div>
                <div class="summary-card">
                  <h4>New Customers</h4>
                  <span id="reportNewCustomers">0</span>
                </div>
                <div class="summary-card">
                  <h4>Completion Rate</h4>
                  <span id="reportCompletionRate">0%</span>
                </div>
              </div>
              <div class="report-chart">
                <canvas id="reportChart"></canvas>
              </div>
            </div>
          </section>

          <!-- Settings Section (YOUR ORIGINAL HTML - UNCHANGED) -->
          <section id="settings-section" class="content-section">
            <div class="settings-container">
              <h2>System Settings</h2>
              <div class="settings-section">
                <h3>Pricing Configuration</h3>
                <form id="pricingForm" class="settings-form">
                  <div class="form-row">
                    <div class="form-group">
                      <label>Print (per page)</label>
                      <input type="number" name="print" step="0.01" value="2.00">
                    </div>
                    <div class="form-group">
                      <label>Photocopying (per page)</label>
                      <input type="number" name="photocopy" step="0.01" value="1.50">
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label>Scanning (per page)</label>
                      <input type="number" name="scanning" step="0.01" value="3.00">
                    </div>
                    <div class="form-group">
                      <label>Photo Development (per photo)</label>
                      <input type="number" name="photo-development" step="0.01" value="15.00">
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label>Laminating (per page)</label>
                      <input type="number" name="laminating" step="0.01" value="5.00">
                    </div>
                  </div>
                  <button type="submit" class="btn btn-primary">Update Pricing</button>
                </form>
              </div>

              <div class="settings-section">
                <h3>Business Information</h3>
                <form id="businessForm" class="settings-form">
                  <div class="form-group">
                    <label>Business Name</label>
                    <input type="text" name="business_name" value="Jonayskie Prints">
                  </div>
                  <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="3">Brgy. San Joseph, Santa Rosa, Nueva Ecija</textarea>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label>Phone</label>
                      <input type="tel" name="phone" value="+639350336938">
                    </div>
                    <div class="form-group">
                      <label>Email</label>
                      <input type="email" name="email" value="info@jonayskieprints.com">
                    </div>
                  </div>
                  <button type="submit" class="btn btn-primary">Update Information</button>
                </form>
              </div>
            </div>
          </section>
          <!-- Deleted Transactions Section -->
<section id="deleted-transactions-section" class="content-section">
  <div class="deleted-header">
    <h2>Deleted Transactions</h2>
  </div>
  <div class="deleted-table">
    <table>
      <thead>
        <tr>
          <th>Transaction ID</th>
          <th>Customer Name</th>
          <th>Service</th>
          <th>Quantity</th>
          <th>Status</th>
          <th>Deleted At</th>
        </tr>
      </thead>
      <tbody id="deletedTransactionsTable">
        <tr>
          <td colspan="6">Loading deleted transactions...</td>
        </tr>
      </tbody>
    </table>
  </div>
</section>



          
        </div>
      </main>
    </div>

    <!-- Admin JavaScript -->
    <script src="./js/admin.js"></script>
    
    <!-- Responsive Navigation (YOUR ORIGINAL JS - UNCHANGED) -->
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const closeSidebarBtn = document.getElementById('closeSidebar');
        
        if (sidebarToggle) {
          sidebarToggle.addEventListener('click', function() {
            sidebar.classList.add('active');
          });
        }
        
        if (closeSidebarBtn) {
          closeSidebarBtn.addEventListener('click', function() {
            sidebar.classList.remove('active');
          });
        }
        
        const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
        sidebarLinks.forEach(link => {
          link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
              sidebar.classList.remove('active');
            }
          });
        });
        
        window.addEventListener('resize', function() {
          if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
          }
        });
      });
    </script>
  </body>
  </html>