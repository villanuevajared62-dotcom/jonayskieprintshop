    
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - Jonayskie Prints</title> 

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Your CSS -->
    <link rel="stylesheet" href="./css/style.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        
        /* Responsive */
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-print"></i>
                    <span>Jonayskie Prints</span>
                </div>
                <button id="closeSidebar" class="close-sidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#dashboard" class="nav-link active" data-section="dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a></li>
                    <li><a href="#new-order" class="nav-link" data-section="new-order">
                        <i class="fas fa-plus-circle"></i>
                        <span>New Order</span>
                    </a></li>
                    <li><a href="#orders" class="nav-link" data-section="orders">
                        <i class="fas fa-list-alt"></i>
                        <span>My Orders</span>
                    </a></li>
                    <li><a href="#profile" class="nav-link" data-section="profile">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a></li>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>
    
        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <button id="sidebarToggle" class="sidebar-toggle"><i class="fas fa-bars"></i></button>
                    <h1 id="pageTitle">Dashboard</h1>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <span>Welcome, <strong id="userName">yuri</strong></span>
                        <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
                    </div>
                </div>
            </header>

            <div class="dashboard-content">
                <!-- Dashboard Section -->
                <section id="dashboard-section" class="content-section active">
                    
                    <!-- PRICE BOARD -->
                    <div class="price-board">
                        <div class="price-board-header">
                            <h2>
                                <i class="fas fa-tags"></i>
                                Current Pricing
                            </h2>
                            <div class="price-update-badge">
                                <i class="fas fa-sync-alt"></i>
                                <span id="priceUpdateTime">Loading...</span>
                            </div>
                        </div>
                        
                        <div class="price-items" id="priceItems">
                            <div class="price-loading">
                                <i class="fas fa-spinner fa-spin"></i> Loading prices...
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                            <div class="stat-info"><h3 id="totalOrders">0</h3><p>Total Orders</p></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-clock"></i></div>
                            <div class="stat-info"><h3 id="pendingOrders">0</h3><p>Pending Orders</p></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="stat-info"><h3 id="completedOrders">0</h3><p>Completed Orders</p></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
                            <div class="stat-info"><h3 id="totalSpent">₱0.00</h3><p>Total Spent</p></div>
                        </div>
                    </div>

                    <div class="recent-orders">
                        <h2>Recent Orders</h2>
                        <div class="orders-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Service</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="recentOrdersTable">
                                    <tr><td colspan="5" class="no-data">No orders yet</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- New Order Section -->
                <section id="new-order-section" class="content-section">
                    <div class="order-form-container">
                        <h2>Create New Order</h2>
                        <form id="newOrderForm" class="order-form" enctype="multipart/form-data">
                            <div class="form-steps">
                                <div class="form-step" data-step="1">
                                    <h3>Select Service</h3>
                                    <div class="service-options" id="serviceOptions">
                                        <div class="loading-services">
                                            <label for="serviceDropdown">Select Service:</label>
                                            <select id="serviceDropdown" name="service" required>
                                                <option value="">-- Select Service --</option>
                                                <option value="print">Print</option>
                                                <option value="photocopy">PhotoCopy</option>
                                                <option value="laminating">Laminating</option>
                                                <option value="scanning">Scanning</option>
                                                <option value="photo-development">Photo Development</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-step" data-step="2">
                                    <h3>Order Details</h3>
                                    <div class="form-group">
                                        <label for="quantity">Quantity</label>
                                        <input type="number" id="quantity" name="quantity" min="1" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="specifications">Specifications</label>
                                        <textarea id="specifications" name="specifications" rows="4" placeholder="Describe your order specifications" required></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Delivery Option</label>
                                        <div class="radio-group">
                                            <label>
                                                <input type="radio" name="delivery_option" value="pickup" checked>
                                                Pickup
                                            </label>
                                            <label>
                                                <input type="radio" name="delivery_option" value="delivery">
                                                Delivery
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group" id="deliveryAddressGroup" style="display: none;">
                                        <label for="delivery_address">Delivery Address</label>
                                        <textarea id="delivery_address" name="delivery_address" rows="3" placeholder="Enter your complete delivery address"></textarea>
                                    </div>
                                </div>
                                
                                <div class="form-step" data-step="3">
                                    <h3>Upload Files & Payment</h3>
                                    <div class="form-group">
                                        <label for="orderFiles">Upload Files</label>
                                        <input type="file" id="orderFiles" name="files[]" multiple>
                                        <div class="file-list" id="fileList"></div>
                                    </div>
                                    <div class="form-group">
                                        <label>TOTAL PAYMENT  </label>
                                        <!-- <div class="radio-group">
                                            <label>
                                                <input type="radio" name="payment_method" value="cash" checked>
                                                Cash on Pickup/Delivery
                                            </label>
                                            <label>
                                                <input type="radio" name="payment_method" value="gcash">
                                                GCash
                                            </label>
                                        </div> -->
                                    </div>
                                    <div class="order-summary" id="orderSummary">
                                        <h4>Order Summary</h4>
                                        <div class="summary-item">
                                            <span>Service:</span>
                                            <span id="summaryService">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <span>Quantity:</span>
                                            <span id="summaryQuantity">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <span>Delivery:</span>
                                            <span id="summaryDelivery">-</span>
                                        </div>
                                        <div class="summary-item total-price">
                                            <span>Total Price:</span>
                                            <span id="summaryPrice">₱0.00</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-navigation">
                                    <button type="button" id="prevStep" disabled>Previous</button>
                                    <button type="button" id="nextStep">Next</button>
                                    <button type="submit" id="submitOrder" style="display:none;">Place Order</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </section>

                <!-- Orders Section -->
                <section id="orders-section" class="content-section">
                    <h2>My Orders</h2>
                    <div class="orders-filter">
                        <label for="filterStatus">Filter by Status:</label>
                        <select id="filterStatus" name="filterStatus">
                            <option value="">All</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="orders-list" id="ordersList">
                        <p>Loading orders...</p>
                    </div>
                </section>

                <!-- Profile Section -->
                <section id="profile-section" class="content-section">
                    <h2>Profile</h2>

                    
                    <form method="POST" action="dashboard.php" class="profile-form">
                        <input type="hidden" name="update_profile" value="1" />

                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="yuri" required />
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="esber" required />
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="yuri5@gmail.com" required />
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" value="09984951569" />
                        </div>

                        <hr>

                        <h3>Change Password (Optional)</h3>

                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" placeholder="Enter current password" />
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Enter new password" />
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" />
                        </div>

                        <button type="submit" class="btn-primary">Update Profile</button>
                    </form>
                </section>

            </div>
        </main>
    </div>

    <script src="./js/dashboard.js"></script>

    <!-- Responsive Navigation JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar on mobile
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
            
            // Close sidebar when clicking on a link (mobile)
            const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('active');
                    }
                });
            });
            
            // Adjust sidebar on window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                }
            });

            // Auto-show profile section if redirected with messages
                    });
    </script>
    <!-- Edit Order Modal -->
<div id="editOrderModal" class="modal" style="display:none;">
  <div class="modal-content">
    <span class="close-modal" id="closeEditModal">&times;</span>
    <h3>Edit Order</h3>
    <form id="editOrderForm">
      <input type="hidden" name="order_id" id="editOrderId">
      
      <label for="editQuantity">Quantity:</label>
      <input type="number" id="editQuantity" name="quantity" required>
      
      <label for="editSpecifications">Specifications:</label>
      <textarea id="editSpecifications" name="specifications" required></textarea>
      
      <label for="editAddress">Delivery Address (if applicable):</label>
      <textarea id="editAddress" name="delivery_address"></textarea>
      
      <button type="submit" class="btn-primary">Save Changes</button>
    </form>
  </div>
</div>

<style>
.modal {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center;
}
.modal-content {
  background: white; padding: 20px; border-radius: 8px; width: 90%; max-width: 400px;
}
.close-modal { float: right; cursor: pointer; font-size: 20px; }
</style>


</body>
</html>