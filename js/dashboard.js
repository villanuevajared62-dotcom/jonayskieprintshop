document.addEventListener('DOMContentLoaded', () => {
  // ============== GLOBAL PRICING STORAGE ==============
 // ============== GLOBAL PRICING STORAGE ==============
let currentPricing = {
  print: 2.00,
  photocopy: 1.50,
  scanning: 3.00,
  'photo-development': 15.00,
  laminating: 5.00
};

let lastPricingTimestamp = 0; // Track last update time

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
  'print': 'Print',
  'photocopy': 'Photocopy',
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
    if (['id', 'last_updated', 'source', 'timestamp'].includes(service)) continue;
    
    const icon = serviceIcons[service] || 'fa-file';
    const name = serviceNames[service] || service;
    const unit = serviceUnits[service] || 'per unit';
    
    html += `
      <div class="price-item price-updating" data-service="${service}">
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
  
  // Remove animation class after animation completes
  setTimeout(() => {
    document.querySelectorAll('.price-item').forEach(item => {
      item.classList.remove('price-updating');
    });
  }, 1000);
}

function updatePriceUpdateTime() {
  const timeElement = document.getElementById('priceUpdateTime');
  if (timeElement) {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { 
      hour: '2-digit', 
      minute: '2-digit',
      second: '2-digit'
    });
    timeElement.textContent = `Updated: ${timeStr}`;
  }
}

// ============== REAL-TIME PRICING UPDATE SYSTEM ==============

// Fetch latest pricing from server
async function fetchLatestPricing() {
  try {
    const response = await fetch('fetch_pricing.php?t=' + Date.now()); // Cache buster
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const data = await response.json();
    
    if (data.error) {
      console.error('Pricing fetch error:', data.message);
      return null;
    }
    
    // Check if pricing has changed
    const newTimestamp = data.timestamp || Date.now();
    const hasChanged = newTimestamp !== lastPricingTimestamp;
    
    if (hasChanged) {
      console.log('✅ Pricing updated from server:', data);
      lastPricingTimestamp = newTimestamp;
      currentPricing = data;
      return { data, changed: true };
    }
    
    return { data, changed: false };
    
  } catch (err) {
    console.error('Error fetching pricing:', err);
    return null;
  }
}

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
        <small>New prices are now in effect</small>
      </div>
    </div>
  `;
  
  notification.style.display = 'block';
  
  setTimeout(() => {
    notification.style.display = 'none';
  }, 5000);
}

// Update all price displays in the UI
function updateAllPriceDisplays() {
  // Update service dropdown if exists
  const serviceDropdown = document.getElementById('serviceDropdown');
  if (serviceDropdown && currentPricing) {
    const selectedValue = serviceDropdown.value;
    serviceDropdown.innerHTML = '<option value="">-- Select Service --</option>';
    
    for (const [service, price] of Object.entries(currentPricing)) {
      if (['id', 'last_updated', 'source', 'timestamp'].includes(service)) continue;
      
      const name = serviceNames[service] || service;
      const option = document.createElement('option');
      option.value = service;
      option.dataset.price = price;
      option.textContent = `${name} - ₱${parseFloat(price).toFixed(2)}`;
      if (service === selectedValue) option.selected = true;
      serviceDropdown.appendChild(option);
    }
  }
  
  // Update order summary
  updateSummary();
}

// Update order summary with current pricing
function updateSummary() {
  const form = document.getElementById('newOrderForm');
  if (!form) return;
  
  const service = form.service?.value || '';
  const quantity = parseInt(form.quantity?.value) || 0;
  const deliveryOption = form.delivery_option?.value || 'pickup';

  document.getElementById('summaryService')?.textContent = serviceNames[service] || service || '-';
  document.getElementById('summaryQuantity')?.textContent = quantity || '-';
  document.getElementById('summaryDelivery')?.textContent = deliveryOption.charAt(0).toUpperCase() + deliveryOption.slice(1);

  // Use current pricing from server
  const pricePerUnit = currentPricing[service] || 0;
  const totalPrice = pricePerUnit * quantity;
  
  const summaryPriceEl = document.getElementById('summaryPrice');
  if (summaryPriceEl) {
    summaryPriceEl.textContent = '₱' + totalPrice.toFixed(2);
  }
}

// Initialize pricing system
async function initializePricing() {
  console.log('🔄 Initializing pricing system...');
  
  const result = await fetchLatestPricing();
  if (result && result.data) {
    currentPricing = result.data;
    renderPriceBoard(currentPricing);
    updatePriceUpdateTime();
    updateAllPriceDisplays();
    console.log('✅ Initial pricing loaded:', currentPricing);
  }
}

// Poll for pricing updates every 3 seconds (more frequent)
let pricingInterval;

function startPricingPolling() {
  if (pricingInterval) {
    clearInterval(pricingInterval);
  }
  
  pricingInterval = setInterval(async () => {
    const result = await fetchLatestPricing();
    
    if (result && result.changed) {
      console.log('🔔 Pricing changed! Updating UI...');
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
    }
  }, 3000); // Check every 3 seconds
}

// Listen for pricing updates via localStorage (cross-tab communication)
window.addEventListener('storage', (e) => {
  if (e.key === 'pricing_updated') {
    console.log('🔔 Pricing update signal received from admin!');
    
    // Immediately fetch new pricing
    fetchLatestPricing().then(result => {
      if (result && result.data) {
        currentPricing = result.data;
        renderPriceBoard(currentPricing);
        updatePriceUpdateTime();
        updateAllPriceDisplays();
        showPricingUpdateNotification();
      }
    });
  }
});

// Manual refresh button
const refreshBtn = document.getElementById('refreshPrices');
if (refreshBtn) {
  refreshBtn.addEventListener('click', async () => {
    const icon = refreshBtn.querySelector('i');
    if (icon) {
      icon.classList.add('fa-spin');
    }
    
    const result = await fetchLatestPricing();
    if (result && result.data) {
      currentPricing = result.data;
      renderPriceBoard(currentPricing);
      updatePriceUpdateTime();
      updateAllPriceDisplays();
    }
    
    setTimeout(() => {
      if (icon) {
        icon.classList.remove('fa-spin');
      }
    }, 1000);
  });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  initializePricing();
  startPricingPolling();
  
  console.log('✅ Pricing system initialized and polling started');
});

// Stop polling when page is hidden (save resources)
document.addEventListener('visibilitychange', () => {
  if (document.hidden) {
    if (pricingInterval) {
      clearInterval(pricingInterval);
      console.log('⏸️ Pricing polling paused (page hidden)');
    }
  } else {
    startPricingPolling();
    fetchLatestPricing(); // Immediate refresh when page becomes visible
    console.log('▶️ Pricing polling resumed (page visible)');
  }
});

// Export for use in other scripts
window.pricingAPI = {
  getCurrentPricing: () => currentPricing,
  refreshPricing: fetchLatestPricing,
  updateDisplays: updateAllPriceDisplays
};

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

  // ============= ORDERS LIST - UPDATED =============
const filterStatus = document.getElementById('filterStatus');
const ordersList = document.getElementById('ordersList');

function loadOrders() {
  const status = filterStatus ? filterStatus.value : '';
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
                <th>Actions</th>
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
              <td>
                <button class="edit-btn"
                  data-id="${order.order_id}"
                  data-service="${order.service}"
                  data-quantity="${order.quantity}"
                  data-specifications="${order.specifications || ''}"
                  data-delivery="${order.delivery_option}"
                  data-status="${order.status}"
                  data-payment="${order.payment_method}">Edit</button>
              </td>
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

// ✅ UPDATED: Event delegation for Edit buttons with status check
ordersList.addEventListener('click', e => {
  if (!e.target.classList.contains('edit-btn')) return;

  const btn = e.target;
  const orderId = btn.dataset.id;
  const currentStatus = btn.dataset.status;
  
  // ✅ CHECK IF ORDER IS COMPLETED
  if (currentStatus === 'completed') {
    alert('❌ Cannot edit completed orders. This order has already been completed.');
    return;
  }

  // ✅ IF NOT COMPLETED, SHOW EDIT MODAL
  showEditModal(btn);
});

// ✅ NEW: Show Edit Modal Function
function showEditModal(btn) {
  const orderId = btn.dataset.id;
  const currentService = btn.dataset.service;
  const currentQuantity = btn.dataset.quantity;
  const currentSpecs = btn.dataset.specifications || '';
  const currentDelivery = btn.dataset.delivery;

  // Create modal HTML
  const modalHTML = `
    <div id="editOrderModal" style="
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.7);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    ">
      <div style="
        background: white;
        padding: 30px;
        border-radius: 15px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
      ">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2 style="margin: 0; color: #2c3e50;">Edit Order #${orderId}</h2>
          <button id="closeEditModal" style="
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #7f8c8d;
          ">&times;</button>
        </div>
        
        <form id="editOrderForm">
          <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #34495e;">Service:</label>
            <select id="editService" style="
              width: 100%;
              padding: 10px;
              border: 2px solid #dfe6e9;
              border-radius: 8px;
              font-size: 14px;
            ">
              <option value="print" ${currentService === 'print' ? 'selected' : ''}>Print</option>
              <option value="photocopy" ${currentService === 'photocopy' ? 'selected' : ''}>Photocopy</option>
              <option value="laminating" ${currentService === 'laminating' ? 'selected' : ''}>Laminating</option>
              <option value="scanning" ${currentService === 'scanning' ? 'selected' : ''}>Scanning</option>
              <option value="photo-development" ${currentService === 'photo-development' ? 'selected' : ''}>Photo Development</option>
            </select>
          </div>
          
          <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #34495e;">Quantity:</label>
            <input type="number" id="editQuantity" value="${currentQuantity}" min="1" style="
              width: 100%;
              padding: 10px;
              border: 2px solid #dfe6e9;
              border-radius: 8px;
              font-size: 14px;
            " required>
          </div>
          
          <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #34495e;">Specifications:</label>
            <textarea id="editSpecifications" rows="3" style="
              width: 100%;
              padding: 10px;
              border: 2px solid #dfe6e9;
              border-radius: 8px;
              font-size: 14px;
              resize: vertical;
            " required>${currentSpecs}</textarea>
          </div>
          
          <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #34495e;">Delivery Option:</label>
            <select id="editDelivery" style="
              width: 100%;
              padding: 10px;
              border: 2px solid #dfe6e9;
              border-radius: 8px;
              font-size: 14px;
            ">
              <option value="pickup" ${currentDelivery === 'pickup' ? 'selected' : ''}>Pickup</option>
              <option value="delivery" ${currentDelivery === 'delivery' ? 'selected' : ''}>Delivery</option>
            </select>
          </div>
          
          <div style="display: flex; gap: 10px;">
            <button type="submit" style="
              flex: 1;
              padding: 12px;
              background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
              color: white;
              border: none;
              border-radius: 8px;
              font-weight: 600;
              cursor: pointer;
              transition: transform 0.2s;
            " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
              Save Changes
            </button>
            <button type="button" id="cancelEdit" style="
              flex: 1;
              padding: 12px;
              background: #95a5a6;
              color: white;
              border: none;
              border-radius: 8px;
              font-weight: 600;
              cursor: pointer;
              transition: transform 0.2s;
            " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  `;

  // Add modal to page
  document.body.insertAdjacentHTML('beforeend', modalHTML);

  // Close modal handlers
  document.getElementById('closeEditModal').addEventListener('click', closeEditModal);
  document.getElementById('cancelEdit').addEventListener('click', closeEditModal);

  // Submit form handler
  document.getElementById('editOrderForm').addEventListener('submit', (e) => {
    e.preventDefault();
    
    const newService = document.getElementById('editService').value;
    const newQuantity = document.getElementById('editQuantity').value;
    const newSpecs = document.getElementById('editSpecifications').value;
    const newDelivery = document.getElementById('editDelivery').value;

    if (!newQuantity || newQuantity < 1) {
      alert('Please enter a valid quantity.');
      return;
    }

    if (!newSpecs.trim()) {
      alert('Specifications are required.');
      return;
    }

    // Send update request
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Updating...';

    fetch('dashboard.php?action=updateOrder', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `order_id=${orderId}&service=${encodeURIComponent(newService)}&quantity=${encodeURIComponent(newQuantity)}&specifications=${encodeURIComponent(newSpecs)}&delivery_option=${encodeURIComponent(newDelivery)}`
    })
    .then(res => res.json())
    .then(result => {
      if (result.success) {
        alert('✅ Order updated successfully!');
        closeEditModal();
        loadOrders(); // Reload orders
      } else {
        alert('❌ Update failed: ' + result.message);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Changes';
      }
    })
    .catch(() => {
      alert('❌ Error updating order.');
      submitBtn.disabled = false;
      submitBtn.textContent = 'Save Changes';
    });
  });
}

// ✅ NEW: Close modal function
function closeEditModal() {
  const modal = document.getElementById('editOrderModal');
  if (modal) {
    modal.remove();
  }
}

// Filter change
if (filterStatus) {
  filterStatus.addEventListener('change', loadOrders);
}

// Initial load
loadOrders();
// Validate step - FIXED: Make file upload optional
function validateStep(step) {
    let valid = true;
    const stepEl = formSteps[step - 1];
    if (!stepEl) return false;
    
    const requiredInputs = stepEl.querySelectorAll('[required]');
    requiredInputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('border-red-500');
            valid = false;
        } else {
            input.classList.remove('border-red-500');
        }
    });
    
    // REMOVED: File upload is now optional
    if (step === 3 && fileInput && fileInput.files.length === 0) {
        alert('Please upload at least one file.');
        valid = false;
    }
    
    return valid;
}

// Form submit - IMPROVED: Better error handling and logging
if (newOrderForm) {
    newOrderForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        console.log('Form submission started'); // Debug log
        
        if (!validateStep(currentStep)) {
            console.log('Validation failed'); // Debug log
            return;
        }
        
        const formData = new FormData(newOrderForm);
        
        // Debug: Log form data
        console.log('Form data being sent:');
        for (let [key, value] of formData.entries()) {
            console.log(key, value);
        }
        
        // Disable submit button to prevent double submission
        if (submitOrderBtn) {
            submitOrderBtn.disabled = true;
            submitOrderBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        }
        
        try {
            const response = await fetch(`${API_BASE}?action=createOrder`, {
                method: 'POST',
                body: formData
            });
            
            console.log('Response status:', response.status); // Debug log
            
            const result = await response.json();
            console.log('Response data:', result); // Debug log
            
            if (result.success) {
                alert(`Order placed successfully! Order ID: ${result.data.order_id}`);
                newOrderForm.reset();
                if (fileList) fileList.innerHTML = '';
                currentStep = 1;
                formSteps.forEach((step, index) => {
                    step.classList.toggle('hidden', index !== 0);
                });
                nextStepBtn.style.display = 'block';
                if (submitOrderBtn) {
                    submitOrderBtn.style.display = 'none';
                    submitOrderBtn.disabled = false;
                    submitOrderBtn.innerHTML = 'Place Order';
                } if (nextStepBtn) {
    nextStepBtn.addEventListener('click', () => {
        // Clear errors in current step
        formSteps[currentStep - 1].querySelectorAll('.error-message').forEach(el => el.remove());
        formSteps[currentStep - 1].querySelectorAll('[required]').forEach(input => input.classList.remove('border-red-500'));

        if (validateStep(currentStep)) {
            formSteps[currentStep - 1].classList.add('hidden');
            currentStep++;
            formSteps[currentStep - 1].classList.remove('hidden');
            prevStepBtn.disabled = currentStep === 1;
            nextStepBtn.style.display = currentStep === totalSteps ? 'none' : 'block';
            submitOrderBtn.style.display = currentStep === totalSteps ? 'block' : 'none';
            updateSummary();
        } else {
            // HINDI NA BABALIK SA FIRST STEP - MAG-ALERT LANG
            showToast('Please complete all required fields in this step.', true);
        }
    });
}
            }
        } catch (error) {
            console.error('Submission error:', error); // Debug log
            alert('Error: ' + error.message);
            // Re-enable button
            if (submitOrderBtn) {
                submitOrderBtn.disabled = false;
                submitOrderBtn.innerHTML = 'Place Order';
            }
        }
    });
}



});

