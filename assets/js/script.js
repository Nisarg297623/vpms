// Wait for DOM to be loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all interactive elements
    initializeApp();
});

// Main initialization function
function initializeApp() {
    // Initialize forms validation
    initializeForms();
    
    // Initialize vehicle entry/exit form calculation
    initializeParkingCalculations();
    
    // Set active navigation link
    setActiveNavLink();
    
    // Initialize dashboard charts if on dashboard page
    if (document.getElementById('parking-availability-chart')) {
        initializeDashboardCharts();
    }
    
    // Initialize dropdown toggles
    initializeDropdowns();
    
    // Initialize alerts to auto-close
    initializeAlerts();
}

// Form validation
function initializeForms() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    
                    // Create or update error message
                    let errorMsg = field.nextElementSibling;
                    if (!errorMsg || !errorMsg.classList.contains('error-message')) {
                        errorMsg = document.createElement('div');
                        errorMsg.classList.add('error-message');
                        errorMsg.style.color = 'red';
                        errorMsg.style.fontSize = '0.8rem';
                        errorMsg.style.marginTop = '0.25rem';
                        field.parentNode.insertBefore(errorMsg, field.nextSibling);
                    }
                    errorMsg.textContent = 'This field is required';
                } else {
                    field.classList.remove('is-invalid');
                    const errorMsg = field.nextElementSibling;
                    if (errorMsg && errorMsg.classList.contains('error-message')) {
                        errorMsg.textContent = '';
                    }
                }
            });
            
            // Validate email format if exists
            const emailField = form.querySelector('input[type="email"]');
            if (emailField && emailField.value.trim() && !isValidEmail(emailField.value.trim())) {
                isValid = false;
                emailField.classList.add('is-invalid');
                
                let errorMsg = emailField.nextElementSibling;
                if (!errorMsg || !errorMsg.classList.contains('error-message')) {
                    errorMsg = document.createElement('div');
                    errorMsg.classList.add('error-message');
                    errorMsg.style.color = 'red';
                    errorMsg.style.fontSize = '0.8rem';
                    errorMsg.style.marginTop = '0.25rem';
                    emailField.parentNode.insertBefore(errorMsg, emailField.nextSibling);
                }
                errorMsg.textContent = 'Please enter a valid email address';
            }
            
            // Validate password match if it's a registration form
            const passwordField = form.querySelector('input[name="password"]');
            const confirmPasswordField = form.querySelector('input[name="confirm_password"]');
            
            if (passwordField && confirmPasswordField) {
                if (passwordField.value !== confirmPasswordField.value) {
                    isValid = false;
                    confirmPasswordField.classList.add('is-invalid');
                    
                    let errorMsg = confirmPasswordField.nextElementSibling;
                    if (!errorMsg || !errorMsg.classList.contains('error-message')) {
                        errorMsg = document.createElement('div');
                        errorMsg.classList.add('error-message');
                        errorMsg.style.color = 'red';
                        errorMsg.style.fontSize = '0.8rem';
                        errorMsg.style.marginTop = '0.25rem';
                        confirmPasswordField.parentNode.insertBefore(errorMsg, confirmPasswordField.nextSibling);
                    }
                    errorMsg.textContent = 'Passwords do not match';
                }
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
}

// Email validation helper
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Initialize parking calculations for entry/exit forms
function initializeParkingCalculations() {
    // Vehicle type selection affects pricing display
    const vehicleTypeSelect = document.getElementById('vehicle_type');
    const pricingInfo = document.getElementById('pricing_info');
    
    if (vehicleTypeSelect && pricingInfo) {
        vehicleTypeSelect.addEventListener('change', function() {
            updatePricingInfo(this.value);
        });
        
        // Initial update
        if (vehicleTypeSelect.value) {
            updatePricingInfo(vehicleTypeSelect.value);
        }
    }
    
    // Calculate estimated cost based on expected duration
    const durationSelect = document.getElementById('expected_duration');
    const estimatedCostDisplay = document.getElementById('estimated_cost');
    
    if (durationSelect && estimatedCostDisplay && vehicleTypeSelect) {
        durationSelect.addEventListener('change', function() {
            calculateEstimatedCost();
        });
        
        vehicleTypeSelect.addEventListener('change', function() {
            calculateEstimatedCost();
        });
    }
    
    // For exit form - calculate actual duration and cost
    const entryTimeInput = document.getElementById('entry_time');
    const calculateBtn = document.getElementById('calculate_fee');
    
    if (entryTimeInput && calculateBtn) {
        calculateBtn.addEventListener('click', function(e) {
            e.preventDefault();
            calculateActualFee();
        });
    }
}

// Update pricing information based on vehicle type
function updatePricingInfo(vehicleType) {
    const pricingInfo = document.getElementById('pricing_info');
    
    if (!pricingInfo) return;
    
    let rates = {
        '2-wheeler': { hourly: '₹10', daily: '₹50', weekly: '₹300', monthly: '₹1000' },
        '4-wheeler': { hourly: '₹30', daily: '₹150', weekly: '₹800', monthly: '₹2500' },
        'commercial': { hourly: '₹50', daily: '₹250', weekly: '₹1500', monthly: '₹5000' }
    };
    
    if (!vehicleType || !rates[vehicleType]) {
        pricingInfo.innerHTML = '<p>Please select a vehicle type to see pricing</p>';
        return;
    }
    
    const rate = rates[vehicleType];
    
    pricingInfo.innerHTML = `
        <div class="pricing-table">
            <p><strong>Hourly Rate:</strong> ${rate.hourly}</p>
            <p><strong>Daily Rate:</strong> ${rate.daily}</p>
            <p><strong>Weekly Rate:</strong> ${rate.weekly}</p>
            <p><strong>Monthly Rate:</strong> ${rate.monthly}</p>
        </div>
    `;
}

// Calculate estimated cost based on duration and vehicle type
function calculateEstimatedCost() {
    const durationSelect = document.getElementById('expected_duration');
    const vehicleTypeSelect = document.getElementById('vehicle_type');
    const estimatedCostDisplay = document.getElementById('estimated_cost');
    
    if (!durationSelect || !vehicleTypeSelect || !estimatedCostDisplay) return;
    
    const duration = durationSelect.value;
    const vehicleType = vehicleTypeSelect.value;
    
    if (!duration || !vehicleType) {
        estimatedCostDisplay.textContent = '₹0.00';
        return;
    }
    
    let rates = {
        '2-wheeler': { hourly: 10, daily: 50, weekly: 300, monthly: 1000 },
        '4-wheeler': { hourly: 30, daily: 150, weekly: 800, monthly: 2500 },
        'commercial': { hourly: 50, daily: 250, weekly: 1500, monthly: 5000 }
    };
    
    let cost = 0;
    const rate = rates[vehicleType];
    
    if (duration === '1hr') cost = rate.hourly;
    else if (duration === '2hr') cost = rate.hourly * 2;
    else if (duration === '4hr') cost = rate.hourly * 4;
    else if (duration === '1day') cost = rate.daily;
    else if (duration === '1week') cost = rate.weekly;
    else if (duration === '1month') cost = rate.monthly;
    
    estimatedCostDisplay.textContent = '₹' + cost.toFixed(2);
}

// Calculate actual fee for exit
function calculateActualFee() {
    const entryTimeInput = document.getElementById('entry_time');
    const vehicleTypeInput = document.getElementById('vehicle_type');
    const actualFeeDisplay = document.getElementById('actual_fee');
    const durationDisplay = document.getElementById('actual_duration');
    
    if (!entryTimeInput || !vehicleTypeInput || !actualFeeDisplay || !durationDisplay) return;
    
    const entryTime = new Date(entryTimeInput.value);
    const currentTime = new Date();
    const vehicleType = vehicleTypeInput.value;
    
    if (isNaN(entryTime.getTime())) {
        actualFeeDisplay.textContent = 'Invalid entry time';
        return;
    }
    
    // Calculate duration in hours
    const durationMs = currentTime - entryTime;
    const durationHours = durationMs / (1000 * 60 * 60);
    
    let rates = {
        '2-wheeler': { hourly: 10, daily: 50, weekly: 300, monthly: 1000 },
        '4-wheeler': { hourly: 30, daily: 150, weekly: 800, monthly: 2500 },
        'commercial': { hourly: 50, daily: 250, weekly: 1500, monthly: 5000 }
    };
    
    const rate = rates[vehicleType];
    let fee = 0;
    
    // Calculate fee based on duration
    if (durationHours <= 24) {
        // For less than a day, charge hourly rate (ceiling to nearest hour)
        const hours = Math.ceil(durationHours);
        fee = hours * rate.hourly;
        
        // Cap at daily rate
        if (fee > rate.daily) {
            fee = rate.daily;
        }
    } else if (durationHours <= 168) { // 7 days
        // Between 1-7 days
        const days = Math.ceil(durationHours / 24);
        fee = days * rate.daily;
        
        // Cap at weekly rate
        if (fee > rate.weekly) {
            fee = rate.weekly;
        }
    } else {
        // More than 7 days
        const weeks = Math.ceil(durationHours / 168); // 168 hours in a week
        fee = weeks * rate.weekly;
        
        // Cap at monthly rate for 4 weeks
        if (weeks >= 4) {
            const months = Math.ceil(durationHours / (168 * 4)); // Approximate month
            fee = months * rate.monthly;
        }
    }
    
    // Format duration for display
    let durationText = '';
    if (durationHours < 1) {
        const minutes = Math.floor(durationHours * 60);
        durationText = `${minutes} minute(s)`;
    } else if (durationHours < 24) {
        const hours = Math.floor(durationHours);
        const minutes = Math.floor((durationHours - hours) * 60);
        durationText = `${hours} hour(s) ${minutes} minute(s)`;
    } else {
        const days = Math.floor(durationHours / 24);
        const remainingHours = Math.floor(durationHours % 24);
        durationText = `${days} day(s) ${remainingHours} hour(s)`;
    }
    
    durationDisplay.textContent = durationText;
    actualFeeDisplay.textContent = '₹' + fee.toFixed(2);
    
    // Also update the hidden input field for the form submission
    const feeInput = document.getElementById('calculated_fee');
    if (feeInput) {
        feeInput.value = fee.toFixed(2);
    }
}

// Set active navigation link based on current page
function setActiveNavLink() {
    const currentUrl = window.location.pathname;
    const navLinks = document.querySelectorAll('.navbar a');
    
    navLinks.forEach(link => {
        if (currentUrl.includes(link.getAttribute('href'))) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
}

// Initialize dashboard charts
function initializeDashboardCharts() {
    // Only load if we're on the dashboard page
    const parkingChart = document.getElementById('parking-availability-chart');
    const transactionChart = document.getElementById('transaction-history-chart');
    
    if (parkingChart) {
        renderParkingAvailabilityChart();
    }
    
    if (transactionChart) {
        renderTransactionHistoryChart();
    }
}

// Render the parking availability chart
function renderParkingAvailabilityChart() {
    const ctx = document.getElementById('parking-availability-chart').getContext('2d');
    
    // This would ideally use data from your backend
    // For now, we'll use sample data
    const parkingData = {
        labels: ['A Block', 'B Block', 'C Block'],
        datasets: [
            {
                label: '2-Wheeler Slots',
                data: [80, 60, 100],
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            },
            {
                label: '4-Wheeler Slots',
                data: [40, 30, 50],
                backgroundColor: 'rgba(255, 99, 132, 0.5)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            },
            {
                label: 'Commercial Slots',
                data: [15, 10, 20],
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }
        ]
    };
    
    // Check if Chart.js is loaded
    if (typeof Chart !== 'undefined') {
        new Chart(ctx, {
            type: 'bar',
            data: parkingData,
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    } else {
        console.error('Chart.js is not loaded. Please include the Chart.js library.');
    }
}

// Render the transaction history chart
function renderTransactionHistoryChart() {
    const ctx = document.getElementById('transaction-history-chart').getContext('2d');
    
    // Sample data for transaction history
    const transactionData = {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [{
            label: 'Transaction Amount (₹)',
            data: [12000, 19000, 15000, 21000, 18000, 25000],
            backgroundColor: 'rgba(153, 102, 255, 0.5)',
            borderColor: 'rgba(153, 102, 255, 1)',
            borderWidth: 1
        }]
    };
    
    // Check if Chart.js is loaded
    if (typeof Chart !== 'undefined') {
        new Chart(ctx, {
            type: 'line',
            data: transactionData,
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

// Initialize dropdowns
function initializeDropdowns() {
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdown = this.nextElementSibling;
            
            if (dropdown && dropdown.classList.contains('dropdown-menu')) {
                dropdown.classList.toggle('show');
            }
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.matches('.dropdown-toggle')) {
            const dropdowns = document.querySelectorAll('.dropdown-menu.show');
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        }
    });
}

// Initialize alerts to auto-close
function initializeAlerts() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        // Add close button if not exists
        if (!alert.querySelector('.close')) {
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'close';
            closeBtn.innerHTML = '&times;';
            closeBtn.style.float = 'right';
            closeBtn.style.fontSize = '1.5rem';
            closeBtn.style.fontWeight = '700';
            closeBtn.style.lineHeight = '1';
            closeBtn.style.color = 'inherit';
            closeBtn.style.opacity = '0.5';
            closeBtn.style.background = 'none';
            closeBtn.style.border = '0';
            closeBtn.style.padding = '0';
            
            closeBtn.addEventListener('click', function() {
                alert.style.display = 'none';
            });
            
            alert.insertBefore(closeBtn, alert.firstChild);
        }
        
        // Auto-close after 5 seconds
        setTimeout(() => {
            alert.style.display = 'none';
        }, 5000);
    });
}
