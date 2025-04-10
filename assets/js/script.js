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
    
    // Initialize entry form validation
    initializeEntryFormValidation();
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
    
    if (!pricingInfo || !parking_rates || !parking_rates[vehicleType]) {
        pricingInfo.innerHTML = '<p>Please select a vehicle type to see pricing</p>';
        return;
    }
    
    const rate = parking_rates[vehicleType];
    
    pricingInfo.innerHTML = `
        <div class="pricing-table">
            <p><strong>Hourly Rate:</strong> ₹${rate.hourly.toFixed(2)}</p>
            <p><strong>Daily Rate:</strong> ₹${rate.daily.toFixed(2)}</p>
            <p><strong>Weekly Rate:</strong> ₹${rate.weekly.toFixed(2)}</p>
            <p><strong>Monthly Rate:</strong> ₹${rate.monthly.toFixed(2)}</p>
        </div>
    `;
}

function calculateActualFee() {
    const entryTimeInput = document.getElementById('entry_time');
    const vehicleTypeInput = document.getElementById('vehicle_type');
    const actualFeeDisplay = document.getElementById('actual_fee');
    const durationDisplay = document.getElementById('actual_duration');
    
    if (!entryTimeInput || !vehicleTypeInput || !actualFeeDisplay || !durationDisplay) return;
    
    const entryTime = new Date(entryTimeInput.value);
    const currentTime = new Date();
    const vehicleType = vehicleTypeInput.value;
    
    if (isNaN(entryTime.getTime()) || !parking_rates || !parking_rates[vehicleType]) {
        actualFeeDisplay.textContent = 'Invalid entry time or vehicle type';
        return;
    }
    
    // Calculate duration in hours
    const durationMs = currentTime - entryTime;
    const durationHours = durationMs / (1000 * 60 * 60);
    
    const rate = parking_rates[vehicleType];
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
        const weeks = Math.ceil(durationHours / 168);
        fee = weeks * rate.weekly;
        
        // Cap at monthly rate for 4 weeks
        if (weeks >= 4) {
            const months = Math.ceil(durationHours / (168 * 4));
            fee = months * rate.monthly;
        }
    }
    
    // Update displays
    durationDisplay.textContent = formatDuration(durationHours);
    actualFeeDisplay.textContent = '₹' + fee.toFixed(2);
    
    // Update hidden input
    const feeInput = document.getElementById('calculated_fee');
    if (feeInput) {
        feeInput.value = fee.toFixed(2);
    }
}

// Helper function to format duration
function formatDuration(durationHours) {
    if (durationHours < 1) {
        const minutes = Math.floor(durationHours * 60);
        return `${minutes} minute(s)`;
    } else if (durationHours < 24) {
        const hours = Math.floor(durationHours);
        const minutes = Math.floor((durationHours - hours) * 60);
        return `${hours} hour(s) ${minutes} minute(s)`;
    } else {
        const days = Math.floor(durationHours / 24);
        const remainingHours = Math.floor(durationHours % 24);
        return `${days} day(s) ${remainingHours} hour(s)`;
    }
}

// Calculate estimated cost based on duration and vehicle type
function calculateEstimatedCost() {
    const durationSelect = document.getElementById('expected_duration');
    const vehicleTypeSelect = document.getElementById('vehicle_type');
    const estimatedCostDisplay = document.getElementById('estimated_cost');
    
    if (!durationSelect || !vehicleTypeSelect || !estimatedCostDisplay) return;
    
    const duration = durationSelect.value;
    const vehicleType = vehicleTypeSelect.value;
    
    if (!duration || !vehicleType || !parking_rates || !parking_rates[vehicleType]) {
        estimatedCostDisplay.textContent = '₹0.00';
        return;
    }
    
    const rate = parking_rates[vehicleType];
    let cost = 0;
    
    switch(duration) {
        case '1hr':
            cost = parseFloat(rate.hourly);
            break;
        case '2hr':
            cost = parseFloat(rate.hourly) * 2;
            break;
        case '4hr':
            cost = parseFloat(rate.hourly) * 4;
            break;
        case '1day':
            cost = parseFloat(rate.daily);
            break;
        case '1week':
            cost = parseFloat(rate.weekly);
            break;
        case '1month':
            cost = parseFloat(rate.monthly);
            break;
    }
    
    estimatedCostDisplay.textContent = '₹' + cost.toFixed(2);
    
    // Update hidden input for form submission
    const feeInput = document.getElementById('calculated_fee');
    if (feeInput) {
        feeInput.value = cost.toFixed(2);
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

// Initialize entry form validation
function initializeEntryFormValidation() {
    const existingVehicleRadio = document.getElementById('existingVehicle');
    const newVehicleRadio = document.getElementById('newVehicle');
    const vehicleIdSelect = document.getElementById('vehicle_id');
    const entryForm = document.getElementById('entryForm');

    entryForm.addEventListener('submit', function(e) {
        let valid = true;

        if (existingVehicleRadio.checked) {
            if (!vehicleIdSelect || !vehicleIdSelect.value) {
                alert('Please select a vehicle');
                valid = false;
            }
        } else if (newVehicleRadio.checked) {
            const vehicleTypeSelect = document.getElementById('vehicle_type');
            const vehicleNumber = document.getElementById('vehicle_number');

            if (!vehicleTypeSelect || !vehicleTypeSelect.value) {
                alert('Please select a vehicle type');
                valid = false;
            }

            if (!vehicleNumber || !vehicleNumber.value.trim()) {
                alert('Please enter a vehicle number');
                valid = false;
            }
        }

        if (!valid) {
            e.preventDefault();
        }
    });
}
