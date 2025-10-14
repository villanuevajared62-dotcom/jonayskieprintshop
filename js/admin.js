// I-add ito sa SIMULA ng admin.js file mo (before ng existing code)
document.addEventListener('DOMContentLoaded', () => {
    
    
    // ============= NAVIGATION FUNCTIONALITY =============
    const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
    const sections = {
        'dashboard': document.getElementById('dashboard-section'),
        'orders': document.getElementById('orders-section'),
        'customers': document.getElementById('customers-section'),
        'reports': document.getElementById('reports-section'),
        'settings': document.getElementById('settings-section')
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
            // Set default date to today
            const reportDateInput = document.getElementById('reportDate');
            if (reportDateInput && !reportDateInput.value) {
                reportDateInput.value = new Date().toISOString().split('T')[0];
            }
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

    // Show dashboard by default on page load
    showSection('dashboard');

    // ============= REST OF YOUR EXISTING CODE =============
    const ordersTable = document.getElementById('adminOrdersTable');
    const customersTable = document.getElementById('customersTable');
    const customerSearch = document.getElementById('customerSearch');
    const statusFilter = document.getElementById('adminStatusFilter');
    const dateFilter = document.getElementById('dateFilter');

    // ---------------- Fetch Orders ----------------
    function fetchOrders() {
        if (!ordersTable) return;
        let url = 'admin.php?action=fetch_orders';
        const status = statusFilter?.value;
        const date = dateFilter?.value;
        if (status) url += '&status=' + encodeURIComponent(status);
        if (date) url += '&date=' + encodeURIComponent(date);

        fetch(url)
            .then(res => res.json())
            .then(data => {
                ordersTable.innerHTML = '';
                if (!data || data.length === 0 || data.error) {
                    ordersTable.innerHTML = '<tr><td colspan="7" class="no-data">No orders found</td></tr>';
                    return;
                }
                data.forEach(order => {
                    const row = ordersTable.insertRow();
                    row.innerHTML = `
                        <td>${order.order_id}</td>
                        <td>${order.customer_name}</td>
                        <td>${order.service}</td>
                        <td>${order.order_date}</td>
                        <td>
                            <select class="status-select" data-order-id="${order.order_id}">
                                <option value="pending" ${order.status==='pending'?'selected':''}>Pending</option>
                                <option value="in-progress" ${order.status==='in-progress'?'selected':''}>In Progress</option>
                                <option value="completed" ${order.status==='completed'?'selected':''}>Completed</option>
                                <option value="cancelled" ${order.status==='cancelled'?'selected':''}>Cancelled</option>
                            </select>
                        </td>
                        <td>₱${order.amount}</td>
                        <td><button class="btn-delete" data-order-id="${order.order_id}">Delete</button></td>
                    `;
                });

                ordersTable.querySelectorAll('.status-select').forEach(select => {
                    select.addEventListener('change', () => {
                        updateOrderStatus(select.dataset.orderId, select.value);
                    });
                });
                ordersTable.querySelectorAll('.btn-delete').forEach(btn => {
                    btn.addEventListener('click', () => deleteOrder(btn.dataset.orderId));
                });
            })
            .catch(err => console.error(err));
    }

    function updateOrderStatus(orderId, status) {
        fetch('admin.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`action=update_order_status&order_id=${orderId}&status=${status}`
        })
        .then(res=>res.json())
        .then(data=>{
            alert(data.message || 'Updated');
            fetchOrders();
        });
    }

    function deleteOrder(orderId) {
        if(!confirm('Delete this order?')) return;
        fetch('admin.php',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`action=delete_order&order_id=${orderId}`
        })
        .then(res=>res.json())
        .then(data=>{
            alert(data.message || 'Deleted');
            fetchOrders();
        });
    }

    // ---------------- Fetch Customers ----------------
    function fetchCustomers() {
        if(!customersTable) return;
        fetch('admin.php?action=fetch_customers')
            .then(res=>res.json())
            .then(data=>{
                customersTable.innerHTML='';
                if(!data || data.length===0) {
                    customersTable.innerHTML='<tr><td colspan="6" class="no-data">No customers</td></tr>';
                    return;
                }
                data.forEach(c=>{
                    const row = customersTable.insertRow();
                    row.innerHTML=`
                        <td>${c.customer_id}</td>
                        <td>${c.first_name} ${c.last_name}</td>
                        <td>${c.email}</td>
                        <td>${c.phone}</td>
                        <td>${c.total_orders}</td>
                        <td>${c.join_date}</td>
                    `;
                });
            })
            .catch(err=>console.error(err));
    }

    // ---------------- Pricing Management ----------------
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
    
    function showPricingUpdateNotification() {
        let notification = document.getElementById('pricing-notification');
        if (!notification) {
            notification = document.createElement('div');
            notification.id = 'pricing-notification';
            notification.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                background: #2ecc71;
                color: white;
                padding: 15px 20px;
                border-radius: 5px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                z-index: 1000;
                display: none;
            `;
            document.body.appendChild(notification);
        }
        
        notification.textContent = '✓ Pricing updated successfully!';
        notification.style.display = 'block';
        
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
    }

    const pricingForm = document.getElementById('pricingForm');
    if(pricingForm){
        pricingForm.addEventListener('submit', e=>{
            e.preventDefault();
            const formData = new FormData(pricingForm);
            formData.append('action','update_pricing');
            
            fetch('admin.php',{
                method:'POST',
                body:formData
            })
            .then(res=>res.json())
            .then(data=>{
                if (data.success) {
                    alert(data.message || 'Pricing updated successfully!');
                    showPricingUpdateNotification();
                    loadCurrentPricing();
                    localStorage.setItem('pricing_updated', Date.now().toString());
                } else {
                    alert('Error: ' + (data.error || 'Update failed'));
                }
            })
            .catch(err=>console.error(err));
        });
    }

    // ---------------- Customer Search ----------------
    if(customerSearch){
        customerSearch.addEventListener('input', ()=>{
            const query = customerSearch.value.toLowerCase();
            const rows = customersTable.querySelectorAll('tr');
            rows.forEach(row=>{
                const name=row.cells[1]?.textContent.toLowerCase()||'';
                row.style.display=name.includes(query)?'':'none';
            });
        });
    }

    if(statusFilter) statusFilter.addEventListener('change', fetchOrders);
    if(dateFilter) dateFilter.addEventListener('change', fetchOrders);

    // ---------------- Reports ----------------
    const reportType = document.getElementById('reportType');
    const reportDate = document.getElementById('reportDate');
    const generateReportBtn = document.getElementById('generateReport');
    let reportChart = null;

    if (generateReportBtn) {
        generateReportBtn.addEventListener('click', generateReport);
    }

    function generateReport() {
        const type = reportType.value;
        const date = reportDate.value || new Date().toISOString().split('T')[0];

        // Fetch report stats
        fetch(`admin.php?action=fetch_report_stats&report_type=${type}&report_date=${date}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('reportTotalOrders').textContent = data.totalOrders;
                    document.getElementById('reportTotalRevenue').textContent = '₱' + data.totalRevenue;
                    document.getElementById('reportNewCustomers').textContent = data.newCustomers;
                    document.getElementById('reportCompletionRate').textContent = data.completionRate + '%';
                }
            });

        // Fetch chart data
        fetch(`admin.php?action=fetch_chart_data&report_type=${type}&report_date=${date}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateReportChart(data.labels, data.revenueData, data.ordersData);
                }
            });
    }

    function updateReportChart(labels, revenueData, ordersData) {
        const ctx = document.getElementById('reportChart');
        if (!ctx) return;

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
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                }
            }
        });
    }

    // ---------------- Dashboard Charts ----------------
    const revenueData = {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
            label: 'Revenue ₱',
            data: [500, 700, 600, 800, 400, 900, 750],
            borderColor: '#2ecc71',
            backgroundColor: 'rgba(46, 204, 113, 0.2)',
            fill: true,
            tension: 0.3
        }]
    };

    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx.getContext('2d'), {
            type: 'line',
            data: revenueData,
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true }
                }
            }
        });
    }

    const ordersData = {
        labels: ['Pending', 'In Progress', 'Completed', 'Cancelled'],
        datasets: [{
            label: 'Orders',
            data: [10, 5, 15, 2],
            backgroundColor: ['#f39c12', '#3498db', '#2ecc71', '#e74c3c']
        }]
    };

    const ordersCtx = document.getElementById('ordersChart');
    if (ordersCtx) {
        const ordersChart = new Chart(ordersCtx.getContext('2d'), {
            type: 'doughnut',
            data: ordersData,
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        fetch('admin.php?action=dashboard_stats')
            .then(res => res.json())
            .then(data => {
                ordersChart.data.datasets[0].data = [
                    data.pendingOrders,
                    data.totalOrders - data.pendingOrders,
                    0,
                    0
                ];
                ordersChart.update();
                
                document.getElementById('totalOrdersAdmin').textContent = data.totalOrders;
                document.getElementById('pendingOrdersAdmin').textContent = data.pendingOrders;
                document.getElementById('totalCustomers').textContent = data.totalCustomers;
                document.getElementById('todayRevenue').textContent = '₱' + data.todayRevenue;
            });
    }

});
// ---------------- Reports (Fixed & Complete) ----------------
const reportType = document.getElementById('reportType');
const reportDate = document.getElementById('reportDate');
const generateReportBtn = document.getElementById('generateReport');
let reportChart = null;

// Auto-set today's date kung wala pa
if (reportDate && !reportDate.value) {
    reportDate.value = new Date().toISOString().split('T')[0];
}

// Kapag kinlick ang Generate Report button
if (generateReportBtn) {
    generateReportBtn.addEventListener('click', generateReport);
}

function generateReport() {
    const type = reportType ? reportType.value : 'daily';
    const date = reportDate?.value || new Date().toISOString().split('T')[0];

    // 1️⃣ Fetch report summary (orders, revenue, etc.)
    fetch(`admin.php?action=fetch_report_stats&report_type=${type}&report_date=${date}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('reportTotalOrders').textContent = data.totalOrders ?? 0;
                document.getElementById('reportTotalRevenue').textContent = '₱' + (data.totalRevenue ?? 0);
                document.getElementById('reportNewCustomers').textContent = data.newCustomers ?? 0;
                document.getElementById('reportCompletionRate').textContent = (data.completionRate ?? 0) + '%';
            } else {
                console.warn('No report data received');
            }
        })
        .catch(err => console.error('Error fetching report stats:', err));

    // 2️⃣ Fetch chart data
    fetch(`admin.php?action=fetch_chart_data&report_type=${type}&report_date=${date}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.labels) {
                updateReportChart(data.labels, data.revenueData, data.ordersData);
            }
        })
        .catch(err => console.error('Error fetching chart data:', err));
}

function updateReportChart(labels, revenueData, ordersData) {
    const ctx = document.getElementById('reportChart');
    if (!ctx) {
        console.warn('reportChart canvas not found');
        return;
    }

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
                legend: { position: 'bottom' },
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

// 🔥 Auto-load default report kapag binuksan ang Reports section
const reportsNavLink = document.querySelector('[data-section="reports"]');
if (reportsNavLink) {
    reportsNavLink.addEventListener('click', () => {
        setTimeout(() => {
            if (reportDate && !reportDate.value) {
                reportDate.value = new Date().toISOString().split('T')[0];
            }
            generateReport(); // auto-load when entering Reports
        }, 300);
    });
}
