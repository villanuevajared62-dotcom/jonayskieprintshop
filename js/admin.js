// admin.js - FIXED VERSION

// Load Deleted Transactions
async function loadDeletedTransactions() {
    const tbody = document.getElementById('deletedTransactionsTable');
    tbody.innerHTML = `<tr><td colspan="6">Loading deleted transactions...</td></tr>`;

    try {
        const response = await fetch('admin.php?action=fetch_deleted_orders');
        const data = await response.json();

        if (data.success && data.orders.length > 0) {
            tbody.innerHTML = '';
            data.orders.forEach(order => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${order.order_id}</td>
                    <td>${order.customer_name}</td>
                    <td>${order.service}</td>
                    <td>${order.quantity}</td>
                    <td>${order.status}</td>
                    <td>${order.deleted_at}</td>

                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="6">No deleted transactions found.</td></tr>`;
        }
    } catch (error) {
        console.error('Error loading deleted transactions:', error);
        tbody.innerHTML = `<tr><td colspan="6">Error loading deleted transactions.</td></tr>`;
    }
}

// Call on page load
document.addEventListener('DOMContentLoaded', loadDeletedTransactions);

document.addEventListener('DOMContentLoaded', () => {
    // Make deleteRow globally accessible
window.deleteRow = function(button, orderId) {
    if (!confirm('Are you sure you want to delete this order?')) return;

    fetch('delete_order.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `order_id=${orderId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = button.closest('tr');
            row.remove();
        } else {
            alert('Failed to delete order.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error deleting order.');
    });
}

    // Ilagay sa labas ng fetchOrders
function deleteRow(button, orderId) {
    if (!confirm('Are you sure you want to delete this order?')) return;

    fetch('delete_order.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `order_id=${orderId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = button.closest('tr');
            row.remove();
        } else {
            alert('Failed to delete order.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error deleting order.');
    });
}

    
    // ============= NAVIGATION FUNCTIONALITY =============
    const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
    const sections = {
        'dashboard':    document.getElementById('dashboard-section'),
        'orders': document.getElementById('orders-section'),
        'customers': document.getElementById('customers-section'),
        'reports': document.getElementById('reports-section'),
        'settings': document.getElementById('settings-section'),
        'deleted-transactions-section': document.getElementById('deleted-transactions-section'),// ✅ add this
    };
    const pageTitle = document.getElementById('pageTitle');

    // Function to show specific section
    function showSection(sectionName) {
        // Hide all sections
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });

        // Remove active class from all nav links
        navLinks.forEach(link => {
            link.classList.remove('active');
        });

        // Show selected section
        const targetSection = sections[sectionName];
        if (targetSection) {
            targetSection.classList.add('active');
        }

        // Add active class to clicked nav link
        const activeLink = document.querySelector(`[data-section="${sectionName}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
        if (sectionName === 'deleted-transactions-section') {
  fetchDeletedOrders();
}


        // Update page title
        const titles = {
            'dashboard': 'Admin Dashboard',
            'orders': 'Manage Orders',
            'customers': 'Customer Management',
            'reports': 'Reports & Analytics',
            'settings': 'System Settings'
        };
        if (pageTitle && titles[sectionName]) {
            pageTitle.textContent = titles[sectionName];
        }

        // Execute section-specific actions
        if (sectionName === 'orders') {
            fetchOrders();
        } else if (sectionName === 'customers') {
            fetchCustomers();
        } else if (sectionName === 'settings') {
            loadCurrentPricing();
        } else if (sectionName === 'reports') {
            const reportDateInput = document.getElementById('reportDate');
            if (reportDateInput && !reportDateInput.value) {
                reportDateInput.value = new Date().toISOString().split('T')[0];
            }
            // Auto-generate report when opening Reports section
            setTimeout(() => {
                generateReport();
            }, 300);
        } else if (sectionName === 'dashboard') {
            loadDashboardStats();
        }else if (sectionName === 'deleted-transactions-section') {
    fetchDeletedOrders();
}
    }

    // Add click events to all navigation links
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const sectionName = link.getAttribute('data-section');
            if (sectionName) {
                showSection(sectionName);
            }
        });
    });

    // ✅ ADD THIS TO admin.js - Real-time order updates

// Auto-refresh orders every 10 seconds when on Orders section
let orderRefreshInterval;

function startOrderAutoRefresh() {
    // Clear existing interval
    if (orderRefreshInterval) {
        clearInterval(orderRefreshInterval);
    }
    
    // Refresh every 10 seconds
    orderRefreshInterval = setInterval(() => {
        const ordersSection = document.getElementById('orders-section');
        if (ordersSection && ordersSection.classList.contains('active')) {
            console.log('🔄 Auto-refreshing orders...');
            fetchOrders();
        }
    }, 10000); // 10 seconds
}

// Stop auto-refresh when leaving Orders section
function stopOrderAutoRefresh() {
    if (orderRefreshInterval) {
        clearInterval(orderRefreshInterval);
        orderRefreshInterval = null;
    }
}

// ✅ UPDATE YOUR showSection FUNCTION
function showSection(sectionName) {
    // ... your existing code ...
    
    // Stop auto-refresh when leaving orders section
    if (sectionName !== 'orders') {
        stopOrderAutoRefresh();
    }
    
    if (sectionName === 'orders') {
        fetchOrders();
        startOrderAutoRefresh(); // ✅ Start auto-refresh
    } else if (sectionName === 'dashboard') {
        loadDashboardStats();
    } else if (sectionName === 'customers') {
        fetchCustomers();
    } else if (sectionName === 'settings') {
        loadCurrentPricing();
    } else if (sectionName === 'reports') {
        const reportDateInput = document.getElementById('reportDate');
        if (reportDateInput && !reportDateInput.value) {
            reportDateInput.value = new Date().toISOString().split('T')[0];
        }
        setTimeout(() => {
            generateReport();
        }, 300);
    }
}

// ✅ ADD VISUAL INDICATOR FOR UPDATED ORDERS
let lastOrderData = [];

function fetchOrders() {
    if (!ordersTable) {
        console.error('Orders table not found');
        return;
    }

    let url = 'admin.php?action=fetch_orders';
    const status = statusFilter?.value || '';
    const date = dateFilter?.value || '';
    
    if (status) url += '&status=' + encodeURIComponent(status);
    if (date) url += '&date=' + encodeURIComponent(date);

    fetch(url)
        .then(res => res.json())
        .then(data => {
            ordersTable.innerHTML = '';

            if (!data.success || !data.orders || data.orders.length === 0) {
                ordersTable.innerHTML = '<tr><td colspan="10" class="no-data">No orders found</td></tr>';
                return;
            }

            data.orders.forEach(order => {
                // Check if this order was recently updated
                const wasUpdated = lastOrderData.find(old => 
                    old.order_id === order.order_id && 
                    (old.service !== order.service || 
                     old.quantity !== order.quantity || 
                     old.delivery_option !== order.delivery_option)
                );

                const files = (order.files || '')
                    .split(',')
                    .filter(f => f.trim() !== '')
                    .map(f => `<a href="${f}" target="_blank" style="color: #3498db; text-decoration: underline;">View</a>`)
                    .join(', ') || 'No files';

                const row = ordersTable.insertRow();
                
                // ✅ Highlight recently updated rows
                if (wasUpdated) {
                    row.style.animation = 'highlightRow 2s ease-in-out';
                    row.style.backgroundColor = '#6027e3ff';
                }
                
                row.innerHTML = `
                    <td>#${order.order_id}</td>
                    <td>${order.customer_name || 'N/A'}</td>
                    <td>${order.service || 'N/A'}</td>
                    <td>${order.quantity || '—'}</td>
                    <td>${order.specifications || 'N/A'}</td>
                    <td>${files}</td>
                    <td>
                        <select class="status-select" data-order-id="${order.order_id}" style="padding: 5px; border-radius: 4px;">
                            <option value="pending" ${order.status==='pending'?'selected':''}>Pending</option>
                            <option value="in-progress" ${order.status==='in-progress'?'selected':''}>In Progress</option>
                            <option value="completed" ${order.status==='completed'?'selected':''}>Completed</option>
                            <option value="cancelled" ${order.status==='cancelled'?'selected':''}>Cancelled</option>
                        </select>
                    </td>   
                    <td>${order.order_date || 'N/A'}</td>
                    <td style="font-weight: bold; color: #2ecc71;">₱${parseFloat(order.amount || 0).toFixed(2)}</td>
                    <td>${order.address || 'N/A'}</td>
                `;
            });

            // Save current order data for next comparison
            lastOrderData = data.orders;

            // Add event listeners for status changes
            ordersTable.querySelectorAll('.status-select').forEach(select => {
                select.addEventListener('change', () => {
                    updateOrderStatus(select.dataset.orderId, select.value);
                });
            });
        })
        .catch(err => {
            console.error('Error fetching orders:', err);
            ordersTable.innerHTML = '<tr><td colspan="10" class="no-data" style="color: #e74c3c;">Error loading orders. Please try again.</td></tr>';
        });
}

// ✅ ADD CSS ANIMATION (add this to your CSS file or in a <style> tag)
const style = document.createElement('style');
style.textContent = `
    @keyframes highlightRow {
        0%, 100% { background-color: transparent; }
        50% { background-color: #d4edda; }
    }
`;
document.head.appendChild(style);   

    // Show dashboard by default on page load
    showSection('dashboard');

    // ============= FETCH ORDERS - FIXED =============
    const ordersTable = document.getElementById('adminOrdersTable');
    const statusFilter = document.getElementById('adminStatusFilter');
    const dateFilter = document.getElementById('dateFilter');

    function fetchOrders() {
        if (!ordersTable) {
            console.error('Orders table not found');
            return;
        }

        // Show loading state
        ordersTable.innerHTML = '<tr><td colspan="9" class="no-data">Loading orders...</td></tr>';

        let url = 'admin.php?action=fetch_orders';
        const status = statusFilter?.value || '';
        const date = dateFilter?.value || '';
        
        if (status) url += '&status=' + encodeURIComponent(status);
        if (date) url += '&date=' + encodeURIComponent(date);

        console.log('Fetching orders from:', url);

        fetch(url)
            .then(res => {
                if (!res.ok) {
                    throw new Error('Network response was not ok');
                }
                return res.json();
            })
            .then(data => {
                console.log('Orders data received:', data);
                
                ordersTable.innerHTML = '';

                // Check if we have valid data
                if (!data.success || !data.orders || data.orders.length === 0) {
                    ordersTable.innerHTML = '<tr><td colspan="9" class="no-data">No orders found</td></tr>';
                    return;
                }

                // Display each order
              data.orders.forEach(order => {
    // Handle files
    const files = (order.files || '')
        .split(',')
        .filter(f => f.trim() !== '')
        .map(f => `<a href="${f}" target="_blank" style="color: #3498db; text-decoration: underline;">View</a>`)
        .join(', ') || 'No files';

    const row = ordersTable.insertRow();
    row.innerHTML = `
       <td>#${order.order_id}</td>
<td>${order.customer_name || 'N/A'}</td>
<td>${order.service || 'N/A'}</td>
<td>${order.quantity || '—'}</td>
<td>${order.specifications || 'N/A'}</td>
<td>${files}</td>
<td>
    <select class="status-select" data-order-id="${order.order_id}" style="padding: 5px; border-radius: 4px;">
        <option value="pending" ${order.status==='pending'?'selected':''}>Pending</option>
        <option value="in-progress" ${order.status==='in-progress'?'selected':''}>In Progress</option>
        <option value="completed" ${order.status==='completed'?'selected':''}>Completed</option>
        <option value="cancelled" ${order.status==='cancelled'?'selected':''}>Cancelled</option>
    </select>
</td>   
<td>${order.order_date || 'N/A'}</td>
<td style="font-weight: bold; color: #2ecc71;">₱${parseFloat(order.amount || 0).toFixed(2)}</td>
<td>
 <td>${order.address || 'N/A'}</td>
    <button class="delete-btn" onclick="deleteRow(this, ${order.order_id})">Delete</button>
</td>

    `;
});
   


                // Add event listeners for status changes
                ordersTable.querySelectorAll('.status-select').forEach(select => {
                    select.addEventListener('change', () => {
                        updateOrderStatus(select.dataset.orderId, select.value);
                    });
                });
            })
            .catch(err => {
                console.error('Error fetching orders:', err);
                ordersTable.innerHTML = '<tr><td colspan="9" class="no-data" style="color: #e74c3c;">Error loading orders. Please try again.</td></tr>';
            });
    }

    function updateOrderStatus(orderId, status) {
        fetch('admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=update_order_status&order_id=${orderId}&status=${status}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Order status updated successfully!');
                fetchOrders();
            } else {
                alert('Failed to update status: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Error updating status:', err);
            alert('Error updating order status');
        });
    }

    // Filter event listeners
    if (statusFilter) {
        statusFilter.addEventListener('change', fetchOrders);
    }
    if (dateFilter) {
        dateFilter.addEventListener('change', fetchOrders);
    }

    // ============= FETCH CUSTOMERS =============
    const customersTable = document.getElementById('customersTable');
    const customerSearch = document.getElementById('customerSearch');

    function fetchCustomers() {
        if (!customersTable) return;
        
        customersTable.innerHTML = '<tr><td colspan="6" class="no-data">Loading customers...</td></tr>';

        fetch('admin.php?action=fetch_customers')
            .then(res => res.json())
            .then(data => {
                customersTable.innerHTML = '';
                if (!data || data.length === 0) {
                    customersTable.innerHTML = '<tr><td colspan="6" class="no-data">No customers found</td></tr>';
                    return;
                }
                data.forEach(c => {
                    const row = customersTable.insertRow();
                    row.innerHTML = `
                        <td>#${c.customer_id}</td>
                        <td>${c.first_name} ${c.last_name}</td>
                        <td>${c.email}</td>
                        <td>${c.phone || 'N/A'}</td>
                        <td>${c.total_orders || 0}</td>
                        <td>${c.join_date}</td>
                    `;
                });
            })
            .catch(err => {
                console.error('Error fetching customers:', err);
                customersTable.innerHTML = '<tr><td colspan="6" class="no-data">Error loading customers</td></tr>';
            });
    }
    

        // ============= FETCH DELETED TRANSACTIONS =============
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
            console.log('Deleted orders data:', data); // Debug log
            deletedTable.innerHTML = '';
            
            if (!data.success || !data.orders || data.orders.length === 0) {
                deletedTable.innerHTML = '<tr><td colspan="6" class="no-data">No deleted transactions found.</td></tr>';
                return;
            }

            data.orders.forEach(order => {
                const row = deletedTable.insertRow();
                row.innerHTML = `
                    <td>#${order.order_id}</td>
                    <td>${order.customer_name || 'N/A'}</td>
                    <td>${order.service || 'N/A'}</td>
                    <td>${order.quantity || '—'}</td>
                    <td>${order.status || '—'}</td>
                    <td>${order.deleted_at || order.order_date || '—'}</td>     
                `;
            });
        })
        .catch(err => {
            console.error('Error loading deleted transactions:', err);
            deletedTable.innerHTML = '<tr><td colspan="6" class="no-data">Error loading deleted transactions.</td></tr>';
        });
}

    // Customer search
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

    // ============= DASHBOARD STATS =============
    function loadDashboardStats() {
        fetch('admin.php?action=dashboard_stats')
            .then(res => res.json())
            .then(data => {
                if (!data.error) {
                    document.getElementById('totalOrdersAdmin').textContent = data.totalOrders || 0;
                    document.getElementById('pendingOrdersAdmin').textContent = data.pendingOrders || 0;
                    document.getElementById('totalCustomers').textContent = data.totalCustomers || 0;
                    document.getElementById('todayRevenue').textContent = '₱' + (data.todayRevenue || '0.00');
                }
            })
            .catch(err => console.error('Error loading dashboard stats:', err));
    }

    // ============= PRICING MANAGEMENT =============
    let currentPricing = {};

    function loadCurrentPricing() {
        fetch('fetch_pricing.php')
            .then(res => res.json())
            .then(data => {
                if (!data.error) {
                    currentPricing = data;
                    updatePricingForm(data);
                }
            })
            .catch(err => console.error('Error loading pricing:', err));
    }

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
                    alert('Pricing updated successfully!');
                    localStorage.setItem('pricing_updated', Date.now().toString());
                } else {
                    alert('Error: ' + (data.error || 'Update failed'));
                }
            })
            .catch(err => console.error('Error updating pricing:', err));
        });
    }

    // ============= REPORTS =============
    const reportType = document.getElementById('reportType');
    const reportDate = document.getElementById('reportDate');
    const generateReportBtn = document.getElementById('generateReport');
    let reportChart = null;

    if (generateReportBtn) {
        generateReportBtn.addEventListener('click', generateReport);
    }

    function generateReport() {
        const type = reportType?.value || 'daily';
        const date = reportDate?.value || new Date().toISOString().split('T')[0];

        fetch(`admin.php?action=fetch_report_stats&report_type=${type}&report_date=${date}`)
            .then(res => res.json())
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
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateReportChart(data.labels, data.revenueData, data.ordersData);
                }
            })
            .catch(err => console.error('Error fetching chart data:', err));
    }

    function updateReportChart(labels, revenueData, ordersData) {
        const ctx = document.getElementById('reportChart');
        if (!ctx) {
            console.error('reportChart canvas not found!');
            return;
        }

        console.log('Updating report chart with data:', { labels, revenueData, ordersData });

        if (reportChart) {
            reportChart.destroy();
        }

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
                plugins: {
                    legend: { position: 'bottom' }
                },
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                    },
                }
            }
        });
    }

    // ============= DASHBOARD CHARTS =============
    const ordersCtx = document.getElementById('ordersChart');
    if (ordersCtx) {
        new Chart(ordersCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Completed', 'Cancelled'],
                datasets: [{
                    label: 'Orders',
                    data: [10, 5, 15, 2],
                    backgroundColor: ['#f39c12', '#3498db', '#2ecc71', '#e74c3c']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Revenue ₱',
                    data: [500, 700, 600, 800, 400, 900, 750],
                    borderColor: '#2ecc71',
                    backgroundColor: 'rgba(46, 204, 113, 0.2)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true }
                }
            }
        });
    }
});
