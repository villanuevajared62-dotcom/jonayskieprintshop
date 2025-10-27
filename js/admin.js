// admin.js - ENHANCED VERSION WITH REAL-TIME UPDATES, IMPROVED DESIGN, ICON ENHANCEMENTS, AND FIXED DELETE BUTTON VISIBILITY

const style = document.createElement('style');
style.textContent = `
    @keyframes highlightRow {
        0%, 100% { background-color: transparent; }
        50% { background-color: #d4edda; }
    }
    .notification-container {
        padding: 15px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-top: 20px;
    }
    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .notification-header h3 {
        margin: 0;
        font-size: 1.2em;
        color: #333;
    }
    .notification-list {
        max-height: 250px;
        overflow-y: auto;
        list-style: none;
        padding: 0;
    }
    .notification-item {
        display: flex;
        justify-content: space-between;
        padding: 10px;
        margin-bottom: 8px;
        border-radius: 4px;
        background: #f8f9fa;
        transition: all 0.3s ease;
    }
    .notification-item.success {
        border-left: 4px solid #28a745;
        background: #e8f5e9;
    }
    .notification-item.warning {
        border-left: 4px solid #ffc107;
        background: #fff3e0;
    }
    .notification-item .content {
        flex-grow: 1;
    }
    .notification-item .timestamp {
        font-size: 0.8em;
        color: #666;
        margin-left: 10px;
    }
    .notification-item i {
        margin-right: 8px;
        color: #007bff;
        font-size: 1.1em;
    }
    .notification-item.success i {
        color: #28a745;
    }
    .notification-item.warning i {
        color: #ffc107;
    }
    .confirm-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }
    .confirm-dialog {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        max-width: 400px;
        text-align: center;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .confirm-dialog p {
        margin: 0 0 15px 0;
        font-size: 14px;
        color: #333;
        line-height: 1.4;
    }
    .confirm-buttons {
        display: flex;
        justify-content: center;
        gap: 10px;
    }
    .confirm-btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: background-color 0.2s;
    }
    .ok-btn {
        background: #007aff;
        color: white;
    }
    .ok-btn:hover {
        background: #0056cc;
    }
    .cancel-btn {
        background: transparent;
        color: #007aff;
        border: 1px solid #007aff;
    }
    .cancel-btn:hover {
        background: #f2f2f7;
    }
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }
    .toast {
        padding: 12px 20px;
        margin-bottom: 10px;
        border-radius: 4px;
        color: white;
        font-size: 14px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        animation: slideInRight 0.3s ease-out;
        max-width: 300px;
        word-wrap: break-word;
    }
    .toast.success {
        background: #28a745;
    }
    .toast.error {
        background: #dc3545;
    }
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    /* FIXED: Enhanced Sticky Action Column for Desktop Visibility */
    .orders-table {
        --sticky-bg: white;
        position: relative; /* Ensure container for sticky children */
    }

    .orders-table th.action-col,
    .orders-table td.action-col {
        position: sticky;
        right: 0;
        background: var(--sticky-bg);
        z-index: 20; /* Increased z-index for reliability */
        border-left: 2px solid #e5e7eb;
        min-width: 140px; /* Slightly wider for button + select */
        max-width: 140px;
        white-space: nowrap; /* Prevent wrapping */
    }

    .orders-table thead th.action-col {
        background: #f9fafb; /* Match thead bg */
        --sticky-bg: #f9fafb;
    }

    /* Hint for scrollable content on desktop (fade on right edge) */
    @media (min-width: 769px) {
        .orders-table::after {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 40px;
            height: 100%;
            background: linear-gradient(to left, rgba(255,255,255,0.8), transparent);
            pointer-events: none;
            z-index: 5;
        }
    }

    /* Ensure button is always tappable/visible */
    .delete-btn {
        min-height: 32px; /* Ensures it's tappable on mobile */
        width: 100%;
        display: block; /* Force full width */
    }

    /* Fixes for table responsiveness - smaller to fit screen */
    .orders-table th,
    .orders-table td {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
    }
    .files-cell {
        max-width: 80px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .specs-cell {
        max-width: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .address-cell {
        max-width: 80px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .customer-cell {
        max-width: 100px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .service-cell {
        max-width: 60px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    /* Make the orders table responsive on mobile */
    @media (max-width: 768px) {
    .orders-table table,
    .orders-table thead,
    .orders-table tbody,
    .orders-table th,
    .orders-table td,
    .orders-table tr {
        display: block;
        width: 100%;
    }

    .orders-table thead tr {
        display: none; /* hide header */
    }

    .orders-table tr {
        margin-bottom: 1rem;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 0.5rem;
        padding: 0.5rem;
    }

    .orders-table td {
        display: flex;
        justify-content: space-between;
        text-align: right;
        padding: 8px;
        font-size: 0.9rem;
        border: none;
        border-bottom: 1px solid #eee;
    }

    .orders-table td::before {
        content: attr(data-label);
        font-weight: 600;
        text-transform: capitalize;
        text-align: left;
        color: #555;
        flex: 1;
    }

    .orders-table td:last-child {
        border-bottom: none;
    }

    /* For mobile card view, ensure action is prominent */
    .orders-table td.action-col::before {
        content: 'Action: ';
        font-weight: 600;
        color: #dc2626;
    }
    
    .orders-table td.action-col {
        justify-content: flex-start !important;
        flex-direction: column;
        gap: 0.5rem;
        border-bottom: 2px solid #fee2e2 !important;
        background: #fef2f2;
        padding: 1rem !important;
        border-radius: 0 0 0.5rem 0.5rem;
    }
    
    .orders-table .delete-btn {
        align-self: stretch;
        font-weight: 600;
    }
    }
`;
document.head.appendChild(style);

let ordersChart = null;
let reportChart = null;

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

window.showToast = function(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = createToastContainer();
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 3000);
};

window.showConfirmDialog = function(message, onConfirm, onCancel) {
    const modal = document.createElement('div');
    modal.className = 'confirm-modal';
    modal.innerHTML = `
        <div class="confirm-dialog">
            <p>${message}</p>
            <div class="confirm-buttons">
                <button class="confirm-btn ok-btn" id="confirmOk">OK</button>
                <button class="confirm-btn cancel-btn" id="confirmCancel">Cancel</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    const okBtn = modal.querySelector('#confirmOk');
    const cancelBtn = modal.querySelector('#confirmCancel');
    const removeModal = () => {
        document.body.removeChild(modal);
    };

    okBtn.addEventListener('click', () => {
        removeModal();
        if (onConfirm) onConfirm();
    });

    cancelBtn.addEventListener('click', () => {
        removeModal();
        if (onCancel) onCancel();
    });

    // Close on escape
    const handleEscape = (e) => {
        if (e.key === 'Escape') {
            removeModal();
            if (onCancel) onCancel();
            document.removeEventListener('keydown', handleEscape);
        }
    };
    document.addEventListener('keydown', handleEscape);

    // Focus on OK button
    okBtn.focus();
};

window.deleteRow = function(button, orderId) {
    showConfirmDialog('Are you sure you want to delete this order? This will archive it permanently.', 
        () => {
            const originalText = button.textContent;
            button.textContent = 'Deleting...';
            button.disabled = true;

            fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete_order&order_id=${orderId}`
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const row = button.closest('tr');
                    if (row) {
                        row.remove();
                        showToast(data.message || 'Order deleted and archived successfully!', 'success');
                        fetchOrders();
                        loadDashboardStats();
                        updateNotifications();
                        updateOrdersChart();
                        const deletedSection = document.getElementById('deleted-transactions-section');
                        if (deletedSection && deletedSection.classList.contains('active')) {
                            fetchDeletedOrders();
                        }
                    } else {
                        console.warn('Row not found for removal');
                        fetchOrders();
                    }
                } else {
                    showToast('Failed to delete order: ' + (data.error || 'Unknown error'), 'error');
                }
                button.textContent = originalText;
                button.disabled = false;
            })
            .catch(err => {
                console.error('Error deleting order:', err);
                showToast('Error deleting order: ' + err.message, 'error');
                button.textContent = originalText;
                button.disabled = false;
            });
        },
        () => {
            // Do nothing on cancel
        }
    );
};

function fetchOrders() {
    const ordersTable = document.getElementById('adminOrdersTable');
    const statusFilter = document.getElementById('adminStatusFilter');
    const dateFilter = document.getElementById('dateFilter');
    if (!ordersTable) {
        console.error('Orders table not found');
        return;
    }

    ordersTable.innerHTML = '<tr><td colspan="11" class="no-data">Loading orders...</td></tr>';

    let url = 'admin.php?action=fetch_orders';
    const status = statusFilter?.value || '';
    const date = dateFilter?.value || '';
    
    if (status) url += '&status=' + encodeURIComponent(status);
    if (date) url += '&date=' + encodeURIComponent(date);

    fetch(url)
        .then(res => {
            if (!res.ok) throw new Error('Network response not ok');
            return res.json();
        })
        .then(data => {
            console.log('Fetched orders data:', data);
            ordersTable.innerHTML = '';

            if (!data.success || !data.orders || data.orders.length === 0) {
                ordersTable.innerHTML = '<tr><td colspan="11" class="no-data">No orders found</td></tr>';
                return;
            }

            let lastOrderData = JSON.parse(localStorage.getItem('lastOrderData')) || [];
            data.orders.forEach(order => {
                const wasUpdated = lastOrderData.find(old => 
                    old.order_id === order.order_id && 
                    (old.status !== order.status)
                );

                const files = (order.files || '')
                    .split(',')
                    .filter(f => f.trim() !== '')
                    .map(f => `<a href="${f}" target="_blank" style="color: #3498db; text-decoration: underline;">View</a>`)
                    .join(', ') || 'No files';

                const row = ordersTable.insertRow();
                row.innerHTML = `
                    <td><i class="fas fa-hashtag text-blue-500 mr-1"></i>#${order.order_id}</td>
                    <td><i class="fas fa-user text-green-500 mr-1"></i>${order.customer_name || 'N/A'}</td>
                    <td><i class="fas fa-cogs text-purple-500 mr-1"></i>${order.service || 'N/A'}</td>
                    <td><i class="fas fa-layer-group text-orange-500 mr-1"></i>${order.quantity || '—'}</td>
                    <td class="specs-cell"><i class="fas fa-file-alt text-gray-500 mr-1"></i>${order.specifications || 'N/A'}</td>
                    <td class="files-cell"><i class="fas fa-paperclip text-indigo-500 mr-1"></i>${files}</td>
                    <td><span class="status-badge">${order.status || 'N/A'}</span></td>
                    <!-- MOVED: Action column earlier for better visibility -->
                    <td class="action-col">
                        <select class="status-select" data-order-id="${order.order_id}" style="padding: 5px; border-radius: 4px; width: 100%;">
                            <option value="pending" ${order.status==='pending'?'selected':''}>Pending</option>
                            <option value="in-progress" ${order.status==='in-progress'?'selected':''}>In Progress</option>
                            <option value="completed" ${order.status==='completed'?'selected':''}>Completed</option>
                            <option value="cancelled" ${order.status==='cancelled'?'selected':''}>Cancelled</option>
                        </select>
                        <button class="delete-btn" onclick="deleteRow(this, ${order.order_id})" style="margin-top: 5px; width: 100%;"><i class="fas fa-trash-alt text-red-500 mr-1"></i>Delete</button>
                    </td>
                    <td><i class="fas fa-calendar text-teal-500 mr-1"></i>${order.order_date || 'N/A'}</td>
                    <td style="font-weight: bold; color: #2ecc71;"><i class="fas fa-peso-sign text-green-600 mr-1"></i>₱${parseFloat(order.amount || 0).toFixed(2)}</td>
                    <td class="address-cell"><i class="fas fa-map-marker-alt text-red-500 mr-1"></i>${order.address || 'N/A'}</td>
                `;

                if (wasUpdated) {
                    row.style.animation = 'highlightRow 2s ease-in-out';
                }
            });

            lastOrderData = data.orders;
            localStorage.setItem('lastOrderData', JSON.stringify(lastOrderData));

            ordersTable.querySelectorAll('.status-select').forEach(select => {
                select.addEventListener('change', (e) => {
                    console.log(`Status changed for order ${select.dataset.orderId} to ${e.target.value}`);
                    updateOrderStatus(select.dataset.orderId, e.target.value);
                });
            });

            // Refresh dashboard stats after fetching orders to ensure accuracy
            loadDashboardStats();
        })
        .catch(err => {
            console.error('Error fetching orders:', err);
            ordersTable.innerHTML = '<tr><td colspan="11" class="no-data" style="color: #e74c3c;">Error loading orders. Please try again.</td></tr>';
        });
}

function updateOrderStatus(orderId, status) {
    console.log(`Updating status for order ${orderId} to ${status}`);
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=update_order_status&order_id=${orderId}&status=${status}`
    })
    .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        return res.json();
    })
    .then(data => {
        console.log('Update status response:', data);
        if (data.success) {
            showToast('Order status updated successfully!', 'success');
            fetchOrders();
            loadDashboardStats();
            updateNotifications();
            updateOrdersChart();
        } else {
            showToast('Failed to update status: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(err => {
        console.error('Error updating status:', err);
        showToast('Error updating order status: ' + err.message, 'error');
    });
}

function fetchDeletedOrders() {
    const deletedTable = document.getElementById('deletedTransactionsTable');
    if (!deletedTable) return;

    deletedTable.innerHTML = '<tr><td colspan="6" class="no-data">Loading deleted transactions...</td></tr>';

    fetch('admin.php?action=fetch_deleted_orders')
        .then(res => {
            if (!res.ok) throw new Error('Network response not ok');
            return res.json();
        })
        .then(data => {
            console.log('Fetched deleted orders data:', data);
            deletedTable.innerHTML = '';

            if (!data.success || !data.orders || data.orders.length === 0) {
                deletedTable.innerHTML = '<tr><td colspan="6" class="no-data">No deleted transactions found</td></tr>';
                return;
            }

            data.orders.forEach(order => {
                const row = deletedTable.insertRow();
                row.innerHTML = `
                    <td><i class="fas fa-hashtag text-gray-500 mr-1"></i>#${order.order_id}</td>
                    <td><i class="fas fa-user-slash text-red-500 mr-1"></i>${order.customer_name || 'N/A'}</td>
                    <td><i class="fas fa-cogs text-gray-500 mr-1"></i>${order.service || 'N/A'}</td>
                    <td><i class="fas fa-layer-group text-gray-500 mr-1"></i>${order.quantity || '—'}</td>
                    <td><i class="fas fa-exclamation-triangle text-yellow-500 mr-1"></i>${order.status || '—'}</td>
                    <td><i class="fas fa-clock text-gray-500 mr-1"></i>${order.deleted_at || '—'}</td>
                `;
            });
        })
        .catch(err => {
            console.error('Error loading deleted transactions:', err);
            deletedTable.innerHTML = '<tr><td colspan="6" class="no-data">Error loading deleted transactions</td></tr>';
        });
}

function loadDashboardStats() {
    console.log('Loading dashboard stats...');
    return fetch('admin.php?action=dashboard_stats')
        .then(res => {
            if (!res.ok) throw new Error('Network response not ok');
            return res.json();
        })
        .then(data => {
            console.log('Dashboard stats data:', data);
            if (!data.error) {
                document.getElementById('totalOrdersAdmin').textContent = data.totalOrders || 0;
                document.getElementById('pendingOrdersAdmin').textContent = data.pendingOrders || 0;
                document.getElementById('totalCustomers').textContent = data.totalCustomers || 0;
                document.getElementById('totalRevenue').textContent = '₱' + (data.totalRevenue || '0.00');
                return data;
            } else {
                console.error('Dashboard stats error:', data.error);
                return null;
            }
        })
        .catch(err => {
            console.error('Error loading dashboard stats:', err);
            return null;
        });
}

function updateOrdersChart() {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded. Please include the Chart.js library.');
        return;
    }

    fetch('admin.php?action=fetch_order_status_counts')
        .then(res => {
            if (!res.ok) throw new Error('Network response not ok');
            return res.json();
        })
        .then(data => {
            if (data.success) {
                const ctx = document.getElementById('ordersChart');
                if (ctx) {
                    if (ordersChart) ordersChart.destroy();
                    ordersChart = new Chart(ctx.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: data.labels.map(l => l.replace('-', ' ')),
                            datasets: [{
                                label: 'Orders',
                                data: data.data,
                                backgroundColor: ['#f39c12', '#3498db', '#2ecc71', '#e74c3c']
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }
            }
        })
        .catch(err => console.error('Error updating orders chart:', err));
}

function updateNotifications() {
    console.log('Updating notifications...');
    const notificationsDiv = document.getElementById('notifications');
    if (!notificationsDiv) return;

    loadDashboardStats().then(data => {
        if (data && !data.error) {
            const totalOrders = data.totalOrders || 0;
            const pendingOrders = data.pendingOrders || 0;
            const now = new Date().toLocaleTimeString();

            let html = `
                <ul class="notification-list">
                    <li class="notification-item ${totalOrders > 0 ? 'success' : 'warning'}">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="content"><strong>Total Orders:</strong> ${totalOrders}</span>
                        <span class="timestamp">${now}</span>
                    </li>
                    <li class="notification-item ${pendingOrders > 0 ? 'warning' : 'success'}">
                        <i class="fas fa-hourglass-half"></i>
                        <span class="content"><strong>Pending Orders:</strong> ${pendingOrders}</span>
                        <span class="timestamp">${now}</span>
                    </li>
                </ul>
            `;
            if (totalOrders === 0) {
                html = `
                    <ul class="notification-list">
                        <li class="notification-item warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span class="content">No orders found.</span>
                            <span class="timestamp">${now}</span>
                        </li>
                    </ul>
                `;
            }
            notificationsDiv.innerHTML = html;
        } else {
            notificationsDiv.innerHTML = `
                <ul class="notification-list">
                    <li class="notification-item warning">
                        <i class="fas fa-exclamation-circle"></i>
                        <span class="content">Error loading notifications.</span>
                        <span class="timestamp">${new Date().toLocaleTimeString()}</span>
                    </li>
                </ul>
            `;
        }
    }).catch(err => {
        console.error('Error updating notifications:', err);
        notificationsDiv.innerHTML = `
            <ul class="notification-list">
                <li class="notification-item warning">
                    <i class="fas fa-exclamation-circle"></i>
                    <span class="content">Error loading notifications.</span>
                    <span class="timestamp">${new Date().toLocaleTimeString()}</span>
                </li>
            </ul>
        `;
    });
}

document.addEventListener('DOMContentLoaded', () => {

    const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
    const sections = {
        'dashboard': document.getElementById('dashboard-section'),
        'orders': document.getElementById('orders-section'),
        'customers': document.getElementById('customers-section'),
        'reports': document.getElementById('reports-section'),
        'settings': document.getElementById('settings-section'),
        'deleted-transactions-section': document.getElementById('deleted-transactions-section'),
    };
    const pageTitle = document.getElementById('pageTitle');

    let orderRefreshInterval;

    function startOrderAutoRefresh() {
        if (orderRefreshInterval) clearInterval(orderRefreshInterval);
        orderRefreshInterval = setInterval(() => {
            const ordersSection = document.getElementById('orders-section');
            if (ordersSection && ordersSection.classList.contains('active')) {
                console.log('🔄 Auto-refreshing orders...');
                fetchOrders();
            }
        }, 10000);
    }

    function stopOrderAutoRefresh() {
        if (orderRefreshInterval) {
            clearInterval(orderRefreshInterval);
            orderRefreshInterval = null;
        }
    }

    function showSection(sectionName) {
        console.log('Navigating to section:', sectionName);

        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });

        navLinks.forEach(link => {
            link.classList.remove('active');
        });

        const targetSection = sections[sectionName];
        if (targetSection) targetSection.classList.add('active');
        else console.error(`Section ${sectionName} not found`);

        const activeLink = document.querySelector(`[data-section="${sectionName}"]`);
        if (activeLink) activeLink.classList.add('active');

        const titles = {
            'dashboard': 'Admin Dashboard',
            'orders': 'Manage Orders',
            'customers': 'Customer Management',
            'reports': 'Reports & Analytics',
            'settings': 'System Settings',
            'deleted-transactions-section': 'Deleted Transactions'
        };
        if (pageTitle && titles[sectionName]) pageTitle.textContent = titles[sectionName];

        if (sectionName !== 'orders') stopOrderAutoRefresh();

        if (sectionName === 'dashboard') {
            loadDashboardStats().then(() => {
                updateNotifications();
                updateOrdersChart();
            });
        } else if (sectionName === 'orders') {
            fetchOrders();
            startOrderAutoRefresh();
        } else if (sectionName === 'customers') {
            fetchCustomers();
        } else if (sectionName === 'settings') {
            loadCurrentPricing();
        } else if (sectionName === 'reports') {
            const reportDateInput = document.getElementById('reportDate');
            if (reportDateInput && !reportDateInput.value) {
                reportDateInput.value = new Date().toISOString().split('T')[0];
            }
            setTimeout(generateReport, 300);
        } else if (sectionName === 'deleted-transactions-section') {
            fetchDeletedOrders();
        }
    }

    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const sectionName = link.getAttribute('data-section');
            if (sectionName) showSection(sectionName);
        });
    });

    showSection('dashboard');

    const statusFilter = document.getElementById('adminStatusFilter');
    const dateFilter = document.getElementById('dateFilter');
    if (statusFilter) statusFilter.addEventListener('change', fetchOrders);
    if (dateFilter) dateFilter.addEventListener('change', fetchOrders);

    const customersTable = document.getElementById('customersTable');
    const customerSearch = document.getElementById('customerSearch');

    function fetchCustomers() {
        if (!customersTable) return;

        customersTable.innerHTML = '<tr><td colspan="6" class="no-data">Loading customers...</td></tr>';

        fetch('admin.php?action=fetch_customers')
            .then(res => {
                if (!res.ok) throw new Error('Network response not ok');
                return res.json();
            })
            .then(data => {
                customersTable.innerHTML = '';
                if (!data || data.length === 0) {
                    customersTable.innerHTML = '<tr><td colspan="6" class="no-data">No customers found</td></tr>';
                    return;
                }
                data.forEach(c => {
                    const row = customersTable.insertRow();
                    row.innerHTML = `
                        <td><i class="fas fa-hashtag text-blue-500 mr-1"></i>#${c.customer_id}</td>
                        <td><i class="fas fa-user text-green-500 mr-1"></i>${c.first_name} ${c.last_name}</td>
                        <td><i class="fas fa-envelope text-orange-500 mr-1"></i>${c.email}</td>
                        <td><i class="fas fa-phone text-teal-500 mr-1"></i>${c.phone || 'N/A'}</td>
                        <td><i class="fas fa-shopping-bag text-purple-500 mr-1"></i>${c.total_orders || 0}</td>
                        <td><i class="fas fa-calendar-check text-indigo-500 mr-1"></i>${c.join_date}</td>
                    `;
                });
            })
            .catch(err => {
                console.error('Error fetching customers:', err);
                customersTable.innerHTML = '<tr><td colspan="6" class="no-data">Error loading customers</td></tr>';
            });
    }

    if (customerSearch) {
        customerSearch.addEventListener('input', () => {
            const query = customerSearch.value.toLowerCase();
            const rows = customersTable.querySelectorAll('tr');
            rows.forEach(row => {
                const name = row.cells[1]?.textContent.toLowerCase() || '';
                row.style.display = name.includes(query) ? '' : 'none';
            });
        });
    }

    function loadCurrentPricing() {
        fetch('fetch_pricing.php')
            .then(res => {
                if (!res.ok) throw new Error('Network response not ok');
                return res.json();
            })
            .then(data => {
                if (!data.error) {
                    currentPricing = data;
                    updatePricingForm(data);
                }
            })
            .catch(err => console.error('Error loading pricing:', err));
    }

    let currentPricing = {};

    function updatePricingForm(pricing) {
        const form = document.getElementById('pricingForm');
        if (form) {
            form.querySelector('[name="print"]').value = pricing.print || 2.00;
            form.querySelector('[name="photocopy"]').value = pricing.photocopy || 1.50;
            form.querySelector('[name="scanning"]').value = pricing.scanning || 3.00;
            form.querySelector('[name="photo-development"]').value = pricing['photo-development'] || 15.00;
            form.querySelector('[name="laminating"]').value = pricing.laminating || 5.00;
        }
    }

    const pricingForm = document.getElementById('pricingForm');
    if (pricingForm) {
        pricingForm.addEventListener('submit', e => {
            e.preventDefault();
            const formData = new FormData(pricingForm);
            formData.append('action', 'update_pricing');

            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // showToast('Pricing updated successfully!', 'success');
                    localStorage.setItem('pricing_updated', Date.now().toString());
                } else {
                    showToast('Error: ' + (data.error || 'Update failed'), 'error');
                }
            })
            .catch(err => {
                console.error('Error updating pricing:', err);
                showToast('Error updating pricing: ' + err.message, 'error');
            });
        });
    }

    const reportType = document.getElementById('reportType');
    const reportDate = document.getElementById('reportDate');
    const generateReportBtn = document.getElementById('generateReport');

    if (generateReportBtn) {
        generateReportBtn.addEventListener('click', generateReport);
    }

    function generateReport() {
        const type = reportType?.value || 'daily';
        const date = reportDate?.value || new Date().toISOString().split('T')[0];

        fetch(`admin.php?action=fetch_report_stats&report_type=${type}&report_date=${date}`)
            .then(res => {
                if (!res.ok) throw new Error('Network response not ok');
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    document.getElementById('reportTotalOrders').textContent = data.totalOrders || 0;
                    document.getElementById('reportTotalRevenue').textContent = '₱' + (data.totalRevenue || '0.00');
                    document.getElementById('reportNewCustomers').textContent = data.newCustomers || 0;
                    document.getElementById('reportCompletionRate').textContent = (data.completionRate || 0) + '%';
                }
            })
            .catch(err => console.error('Error fetching report stats:', err));

        fetch(`admin.php?action=fetch_chart_data&report_type=${type}&report_date=${date}`)
            .then(res => {
                if (!res.ok) throw new Error('Network response not ok');
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    updateReportChart(data.labels, data.revenueData, data.ordersData);
                }
            })
            .catch(err => console.error('Error fetching chart data:', err));
    }

    function updateReportChart(labels, revenueData, ordersData) {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js is not loaded. Please include the Chart.js library.');
            return;
        }

        const ctx = document.getElementById('reportChart');
        if (!ctx) {
            console.error('reportChart canvas not found!');
            return;
        }

        console.log('Updating report chart with data:', { labels, revenueData, ordersData });

        if (reportChart) reportChart.destroy();

        reportChart = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Revenue (₱)',
                        data: revenueData,
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        yAxisID: 'y',
                    },
                    {
                        label: 'Orders',
                        data: ordersData,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { type: 'linear', display: true, position: 'left' },
                    y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false } },
                }
            }
        });
    }

});