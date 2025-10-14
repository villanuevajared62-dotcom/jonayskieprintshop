document.addEventListener('DOMContentLoaded', () => {
  // ============== GLOBAL PRICING STORAGE ==============
  let currentPricing = {
    print: 2.00,
    photocopy: 1.50,
    scanning: 3.00,
    'photo-development': 15.00,
    laminating: 5.00
  };

  // Service icons mapping
  const serviceIcons = {
    'print': 'fa-print',
    'photocopy': 'fa-copy',
    'scanning': 'fa-scanner',
    'photo-development': 'fa-camera',
    'laminating': 'fa-id-card'
  };

  // Service display names
  const serviceNames = {
    'print': 'Printing',
    'photocopy': 'Photocopying',
    'scanning': 'Scanning',
    'photo-development': 'Photo Development',
    'laminating': 'Laminating'
  };

  // Service units
  const serviceUnits = {
    'print': 'per page',
    'photocopy': 'per page',
    'scanning': 'per page',
    'photo-development': 'per photo',
    'laminating': 'per page'
  };

  // ============== PRICE BOARD DISPLAY ==============
  
  function renderPriceBoard(pricing) {
    const priceItems = document.getElementById('priceItems');
    if (!priceItems) return;

    let html = '';
    
    for (const [service, price] of Object.entries(pricing)) {
      // Skip non-service fields
      if (service === 'id' || service === 'last_updated') continue;
      
      const icon = serviceIcons[service] || 'fa-file';
      const name = serviceNames[service] || service;
      const unit = serviceUnits[service] || 'per unit';
      
      html += `
        <div class="price-item" data-service="${service}">
          <div class="price-item-header">
            <div class="price-item-icon">
              <i class="fas ${icon}"></i>
            </div>
            <div class="price-item-name">${name}</div>
          </div>
          <div class="price-item-price">₱${parseFloat(price).toFixed(2)}</div>
          <div class="price-item-unit">${unit}</div>
        </div>
      `;
    }
    
    priceItems.innerHTML = html;
  }

  function updatePriceUpdateTime() {
    const timeElement = document.getElementById('priceUpdateTime');
    if (timeElement) {
      const now = new Date();
      const timeStr = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit' 
      });
      timeElement.innerHTML = `<i class="fas fa-clock"></i> Updated at ${timeStr}`;
    }
  }

  // ============== REAL-TIME PRICING UPDATE SYSTEM ==============
  
  // Fetch latest pricing from server
  function fetchLatestPricing() {
    return fetch('fetch_pricing.php')
      .then(res => res.json())
      .then(data => {
        if (!data.error) {
          currentPricing = data;
          return data;
        }
        return null;
      })
      .catch(err => {
        console.error('Error fetching pricing:', err);
        return null;
      });
  }

  // Initial pricing load
  fetchLatestPricing().then(() => {
    renderPriceBoard(currentPricing);
    updatePriceUpdateTime();
    updateAllPriceDisplays();
  });

  // Poll for pricing updates every 5 seconds
  setInterval(() => {
    fetchLatestPricing().then((pricing) => {
      if (pricing) {
        renderPriceBoard(pricing);
        updatePriceUpdateTime();
        updateAllPriceDisplays();
      }
    });
  }, 5000);

  // Listen for pricing updates via localStorage (cross-tab communication)
  window.addEventListener('storage', (e) => {
    if (e.key === 'pricing_updated') {
      console.log('Pricing update detected from admin!');
      fetchLatestPricing().then(() => {
        renderPriceBoard(currentPricing);
        updatePriceUpdateTime();
        updateAllPriceDisplays();
        showPricingUpdateNotification();
        
        // Add pulse animation to price board
        const priceBoard = document.querySelector('.price-board');
        if (priceBoard) {
          priceBoard.style.animation = 'none';
          setTimeout(() => {
            priceBoard.style.animation = 'pulse 0.5s ease-in-out';
          }, 10);
        }
      });
    }
  });

  // Show notification when pricing is updated
  function showPricingUpdateNotification() {
    let notification = document.getElementById('pricing-notification');
    if (!notification) {
      notification = document.createElement('div');
      notification.id = 'pricing-notification';
      notification.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        z-index: 1000;
        display: none;
        animation: slideIn 0.3s ease-out;
      `;
      document.body.appendChild(notification);
    }
    
    notification.innerHTML = `
      <div style="display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-bell" style="font-size: 20px;"></i>
        <div>
          <strong>Pricing Updated!</strong><br>
          <small>New prices are now effective</small>
        </div>
      </div>
    `;
    notification.style.display = 'block';
    
    setTimeout(() => {
      notification.style.display = 'none';
    }, 4000);
  }

  // Update all price displays in the UI
  function updateAllPriceDisplays() {
    // Update order summary if visible
    updateSummary();
  }

  // ============== SIDEBAR NAVIGATION ==============
  const navLinks = document.querySelectorAll('.nav-link');
  const sections = {
    dashboard: document.getElementById('dashboard-section'),
    'new-order': document.getElementById('new-order-section'),
    orders: document.getElementById('orders-section'),
    profile: document.getElementById('profile-section'),
  };
  const pageTitle = document.getElementById('pageTitle');

  function showSection(name) {
    for (const key in sections) {
      sections[key].classList.toggle('active', key === name);
    }
    navLinks.forEach(link => {
      link.classList.toggle('active', link.dataset.section === name);
    });
    pageTitle.textContent = name.charAt(0).toUpperCase() + name.slice(1).replace('-', ' ');

    if (name === 'dashboard') {
      loadStatsAndRecentOrders();
      // Refresh price board
      fetchLatestPricing().then(() => {
        renderPriceBoard(currentPricing);
        updatePriceUpdateTime();
      });
    } else if (name === 'orders') {
      loadOrders();
    } else if (name === 'new-order') {
      // Refresh pricing when new order section is opened
      fetchLatestPricing().then(() => {
        updateAllPriceDisplays();
      });
    }
  }

  navLinks.forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      showSection(link.dataset.section);
    });
  });

  showSection('dashboard');

  // ============== DASHBOARD STATS ==============
  function loadStatsAndRecentOrders() {
    fetch('dashboard.php?action=getStats')
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          document.getElementById('totalOrders').textContent = data.data.total;
          document.getElementById('pendingOrders').textContent = data.data.pending;
          document.getElementById('completedOrders').textContent = data.data.completed;
          document.getElementById('totalSpent').textContent = '₱' + parseFloat(data.data.totalSpent || 0).toFixed(2);
        } else {
          console.error('Stats load failed:', data.message || 'Unknown error');
        }
      })
      .catch(err => {
        console.error('Error loading stats:', err);
      });

    fetch('dashboard.php?action=getOrders')
      .then(res => res.json())
      .then(data => {
        const tbody = document.getElementById('recentOrdersTable');
        if (data.success && data.data.orders.length > 0) {
          tbody.innerHTML = '';
          data.data.orders.slice(0, 5).forEach(order => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${order.order_id}</td>
              <td>${capitalize(order.service)}</td>
              <td>${new Date(order.created_at).toLocaleDateString()}</td>
              <td>${capitalize(order.status)}</td>
              <td>₱${(order.total_amount || 0).toFixed(2)}</td>
            `;
            tbody.appendChild(tr);
          });
        } else {
          tbody.innerHTML = '<tr><td colspan="5" class="no-data">No orders yet</td></tr>';
        }
      })
      .catch(err => {
        console.error('Error loading recent orders:', err);
      });
  }

  function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  // ============== NEW ORDER FORM ==============
  const form = document.getElementById('newOrderForm');
  if (form) {
    const steps = form.querySelectorAll('.form-step');
    const prevBtn = document.getElementById('prevStep');
    const nextBtn = document.getElementById('nextStep');
    const submitBtn = document.getElementById('submitOrder');
    let currentStep = 0;

    function updateStep() {
      steps.forEach((step, i) => {
        step.style.display = i === currentStep ? 'block' : 'none';
      });
      prevBtn.disabled = currentStep === 0;
      nextBtn.style.display = currentStep === steps.length - 1 ? 'none' : 'inline-block';
      submitBtn.style.display = currentStep === steps.length - 1 ? 'inline-block' : 'none';
      updateSummary();
    }

    prevBtn.addEventListener('click', () => {
      if (currentStep > 0) {
        currentStep--;
        updateStep();
      }
    });

    nextBtn.addEventListener('click', () => {
      if (validateStep(currentStep)) {
        currentStep++;
        updateStep();
      }
    });

    // Delivery option handling
    const deliveryRadios = form.querySelectorAll('input[name="delivery_option"]');
    const deliveryAddressGroup = document.getElementById('deliveryAddressGroup');
    deliveryRadios.forEach(radio => {
      radio.addEventListener('change', () => {
        if (radio.value === 'delivery') {
          deliveryAddressGroup.style.display = 'block';
        } else {
          deliveryAddressGroup.style.display = 'none';
          form.delivery_address.value = '';
        }
        updateSummary();
      });
    });

    // Service and quantity change listeners
    const serviceDropdown = document.getElementById('serviceDropdown');
    const quantityInput = document.getElementById('quantity');
    
    if (serviceDropdown) {
      serviceDropdown.addEventListener('change', updateSummary);
    }
    if (quantityInput) {
      quantityInput.addEventListener('input', updateSummary);
    }

    function validateStep(step) {
      if (step === 0) {
        const service = form.service.value;
        if (!service) {
          alert('Please select a service.');
          return false;
        }
      } else if (step === 1) {
        const quantity = parseInt(form.quantity.value);
        const specs = form.specifications.value.trim();
        const deliveryOption = form.delivery_option.value;

        if (!quantity || quantity < 1) {
          alert('Please enter a valid quantity.');
          return false;
        }
        if (!specs) {
          alert('Please enter order specifications.');
          return false;
        }
        if (deliveryOption === 'delivery') {
          const address = form.delivery_address.value.trim();
          if (!address) {
            alert('Please enter delivery address.');
            return false;
          }
        }
      } else if (step === 2) {
        // Validate file upload on the final step
        const fileInput = document.getElementById('orderFiles');
        if (!fileInput.files || fileInput.files.length === 0) {
          alert('Please upload at least one file for your order.');
          return false;
        }
      }
      return true;
    }

    // Update order summary with REAL-TIME PRICING
    function updateSummary() {
      const service = form.service?.value || '';
      const quantity = parseInt(form.quantity?.value) || 0;
      const deliveryOption = form.delivery_option?.value || 'pickup';

      document.getElementById('summaryService').textContent = capitalize(service) || '-';
      document.getElementById('summaryQuantity').textContent = quantity || '-';
      document.getElementById('summaryDelivery').textContent = capitalize(deliveryOption);

      // Use current pricing from server
      const pricePerUnit = currentPricing[service] || 0;
      const totalPrice = pricePerUnit * quantity;
      
      document.getElementById('summaryPrice').textContent = '₱' + totalPrice.toFixed(2);
    }

    updateStep();

    // File input display
    const fileInput = document.getElementById('orderFiles');
    const fileList = document.getElementById('fileList');
    if (fileInput && fileList) {
      fileInput.addEventListener('change', () => {
        fileList.innerHTML = '';
        [...fileInput.files].forEach(file => {
          const div = document.createElement('div');
          div.textContent = file.name;
          fileList.appendChild(div);
        });
      });
    }

    // Submit order form
    form.addEventListener('submit', e => {
      e.preventDefault();

      if (!validateStep(currentStep)) return;

      const formData = new FormData(form);
      formData.append('action', 'createOrder');

      submitBtn.disabled = true;
      submitBtn.textContent = 'Placing Order...';

      fetch('dashboard.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Place Order';

        if (data.success) {
          alert('Order placed successfully! Order ID: ' + data.data.order_id);
          form.reset();
          if (fileList) fileList.innerHTML = '';
          currentStep = 0;
          updateStep();
          showSection('dashboard');
          loadStatsAndRecentOrders();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(err => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Place Order';
        alert('Error submitting order: ' + err);
      });
    });
  }

  // ============== ORDERS LIST ==============
  const filterStatus = document.getElementById('filterStatus');
  const ordersList = document.getElementById('ordersList');

  function loadOrders() {
    const status = filterStatus ? filterStatus.value : '';

    if (!ordersList) return;

    ordersList.innerHTML = '<p>Loading orders...</p>';

    fetch('dashboard.php?action=getOrders&status=' + encodeURIComponent(status))
      .then(res => res.json())
      .then(data => {
        if (data.success && data.data.orders.length > 0) {
          let html = `
            <table>
              <thead>
                <tr>
                  <th>Order ID</th>
                  <th>Service</th>
                  <th>Quantity</th>
                  <th>Status</th>
                  <th>Delivery</th>
                  <th>Payment</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
          `;
          data.data.orders.forEach(order => {
            html += `
              <tr>
                <td>${order.order_id}</td>
                <td>${capitalize(order.service)}</td>
                <td>${order.quantity}</td>
                <td>${capitalize(order.status)}</td>
                <td>${capitalize(order.delivery_option)}</td>
                <td>${capitalize(order.payment_method)}</td>
                <td>${new Date(order.created_at).toLocaleDateString()}</td>
              </tr>
            `;
          });
          html += '</tbody></table>';
          ordersList.innerHTML = html;
        } else {
          ordersList.innerHTML = '<p>No orders found.</p>';
        }
      })
      .catch(() => {
        ordersList.innerHTML = '<p>Error loading orders.</p>';
      });
  }

  if (filterStatus) {
    filterStatus.addEventListener('change', loadOrders);
  }
});