// Authentication JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Password toggle functionality
    window.togglePassword = function(inputId) {
        const input = document.getElementById(inputId);
        const icon = event.currentTarget.querySelector('i'); // gamitin yung button na na-click
        
        if (!input) {
            console.error("Password input not found:", inputId);
            return;
        }

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        } else {
            input.type = 'password';
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    };

    // Password strength checker
    const passwordInput = document.getElementById('password');
    const strengthIndicator = document.getElementById('passwordStrength');
    
    if (passwordInput && strengthIndicator) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            
            strengthIndicator.className = `password-strength ${strength.class}`;
            strengthIndicator.textContent = strength.text;
        });
    }
    
    function checkPasswordStrength(password) {
        if (password.length < 6) {
            return { class: 'weak', text: 'Weak - At least 6 characters' };
        }
        
        let score = 0;
        if (password.length >= 8) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;
        
        if (score < 3) {
            return { class: 'weak', text: 'Weak' };
        } else if (score < 4) {
            return { class: 'medium', text: 'Medium' };
        } else {
            return { class: 'strong', text: 'Strong' };
        }
    }
    
    // Form validation
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleLogin(this);
        });
    }
    
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleRegister(this);
        });
    }
    
    function handleLogin(form) {
        const formData = new FormData(form);
        const email = formData.get('email');
        const password = formData.get('password');
        
        // Basic validation
        if (!email || !password) {
            showNotification('Please fill in all fields', 'error');
            return;
        }
        
        if (!validateEmail(email)) {
            showNotification('Please enter a valid email address', 'error');
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const hideLoading = showLoading(submitBtn);
        
        // API call
        fetch('php/login.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            hideLoading();
            
            if (data.success) {
                showNotification('Login successful! Redirecting...', 'success');
                // Redirect immediately after successful login
                window.location.href = data.redirect || 'supabase/dashboard.html';
            } else {
                showNotification(data.message || 'Login failed', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Login error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        });
    }
    
    function handleRegister(form) {
        const formData = new FormData(form);
        const firstName = formData.get('firstName');
        const lastName = formData.get('lastName');
        const email = formData.get('email');
        const phone = formData.get('phone');
        const password = formData.get('password');
        const confirmPassword = formData.get('confirmPassword');
        const terms = formData.get('terms');
        
        // Validation
        if (!firstName || !lastName || !email || !phone || !password || !confirmPassword) {
            showNotification('Please fill in all fields', 'error');
            return;
        }
        
        if (!validateEmail(email)) {
            showNotification('Please enter a valid email address', 'error');
            return;
        }
        
        if (!validatePhone(phone)) {
            showNotification('Please enter a valid phone number', 'error');
            return;
        }
        
        if (password !== confirmPassword) {
            showNotification('Passwords do not match', 'error');
            return;
        }
        
        if (password.length < 6) {
            showNotification('Password must be at least 6 characters long', 'error');
            return;
        }
        
        if (!terms) {
            showNotification('Please accept the terms of service', 'error');
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const hideLoading = showLoading(submitBtn);
        
        // API call
        fetch('php/register.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            hideLoading();
            
            if (data.success) {
                showNotification('Registration successful! Redirecting...', 'success');
                // Redirect to dashboard or specified page after successful registration
                setTimeout(() => {
                    window.location.href = data.redirect || 'supabase/dashboard.html';
                }, 1500);
            } else {
                showNotification(data.message || 'Registration failed', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Registration error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        });
    }
    
    // Real-time validation feedback
    const inputs = document.querySelectorAll('input[required]');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateInput(this);
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateInput(this);
            }
        });
    });
    
    function validateInput(input) {
        const value = input.value.trim();
        let isValid = true;
        let message = '';
        
        if (!value) {
            isValid = false;
            message = 'This field is required';
        } else {
            switch (input.type) {
                case 'email':
                    if (!validateEmail(value)) {
                        isValid = false;
                        message = 'Please enter a valid email address';
                    }
                    break;
                case 'tel':
                    if (!validatePhone(value)) {
                        isValid = false;
                        message = 'Please enter a valid phone number';
                    }
                    break;
                case 'password':
                    if (value.length < 6) {
                        isValid = false;
                        message = 'Password must be at least 6 characters';
                    }
                    break;
            }
        }
        
        // Check password confirmation
        if (input.name === 'confirmPassword') {
            const passwordInput = document.getElementById('password');
            if (passwordInput && value !== passwordInput.value) {
                isValid = false;
                message = 'Passwords do not match';
            }
        }
        
        // Update UI
        if (isValid) {
            input.classList.remove('error');
            removeErrorMessage(input);
        } else {
            input.classList.add('error');
            showErrorMessage(input, message);
        }
        
        return isValid;
    }
    
    function showErrorMessage(input, message) {
        removeErrorMessage(input);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        errorDiv.style.cssText = `
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        `;
        
        input.parentNode.parentNode.appendChild(errorDiv);
    }
    
    function removeErrorMessage(input) {
        const errorMessage = input.parentNode.parentNode.querySelector('.error-message');
        if (errorMessage) {
            errorMessage.remove();
        }
    }
    
    // Utility functions
    function validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function validatePhone(phone) {
        // Philippine phone number validation
        const phoneRegex = /^(\+63|0)?9\d{9}$/;
        return phoneRegex.test(phone.replace(/\s/g, ''));
    }
    
    function showLoading(button) {
        const originalText = button.textContent;
        button.textContent = 'Loading...';
        button.disabled = true;
        
        return function hideLoading() {
            button.textContent = originalText;
            button.disabled = false;
        };
    }
    
    function showNotification(message, type) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        
        // Style the notification
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            max-width: 400px;
            word-wrap: break-word;
        `;
        
        // Set colors based on type
        if (type === 'success') {
            notification.style.backgroundColor = '#10b981';
        } else if (type === 'error') {
            notification.style.backgroundColor = '#ef4444';
        } else {
            notification.style.backgroundColor = '#3b82f6';
        }
        
        // Add to page
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 5000);
        
        // Allow manual close on click
        notification.addEventListener('click', () => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        });
    }
    
    // Add error styles
    const style = document.createElement('style');
    style.textContent = `
        .input-group input.error {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
        }
        
        .notification {
            cursor: pointer;
        }
        
        .notification:hover {
            opacity: 0.9;
        }
    `;
    document.head.appendChild(style);
});