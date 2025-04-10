<?php
require_once '../config/db_connect.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "You must be logged in to access this page.";
    redirect('../auth/login.php');
}

// Get user info
$user_id = $_SESSION['user_id'];

// Check if payment ID or transaction ID is provided
if (isset($_GET['payment_id']) && !empty($_GET['payment_id'])) {
    $payment_id = sanitize_input($conn, $_GET['payment_id']);
    
    // Get payment details
    $query = "SELECT p.*, pt.transaction_id, pt.vehicle_id, pt.bill_amount, 
              v.vehicle_number, v.vehicle_type, u.full_name, u.email, u.contact_no
              FROM payments p
              JOIN parking_transactions pt ON p.transaction_id = pt.transaction_id
              JOIN vehicles v ON pt.vehicle_id = v.vehicle_id
              JOIN users u ON v.user_id = u.user_id
              WHERE p.payment_id = ? AND u.user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $payment_id, $user_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    
    if (!$payment) {
        $_SESSION['error_message'] = "Invalid payment ID or you don't have permission to view this payment.";
        redirect('../parking/view_status.php');
        exit;
    }
    
} elseif (isset($_GET['transaction_id']) && !empty($_GET['transaction_id'])) {
    $transaction_id = sanitize_input($conn, $_GET['transaction_id']);
    
    // Get transaction details
    $query = "SELECT pt.*, v.vehicle_number, v.vehicle_type, u.full_name, u.email, u.contact_no
              FROM parking_transactions pt
              JOIN vehicles v ON pt.vehicle_id = v.vehicle_id
              JOIN users u ON v.user_id = u.user_id
              WHERE pt.transaction_id = ? AND u.user_id = ? AND pt.bill_status = 'pending'";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $transaction_id, $user_id);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();
    
    if (!$transaction) {
        $_SESSION['error_message'] = "Invalid transaction ID or you don't have permission to pay for this transaction.";
        redirect('../parking/view_status.php');
        exit;
    }
    
    // Check if payment record exists
    $query = "SELECT * FROM payments WHERE transaction_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $transaction_id);
    $stmt->execute();
    $existing_payment = $stmt->get_result()->fetch_assoc();
    
    if ($existing_payment) {
        // Use existing payment record
        $payment = $existing_payment;
        $payment['vehicle_number'] = $transaction['vehicle_number'];
        $payment['vehicle_type'] = $transaction['vehicle_type'];
        $payment['full_name'] = $transaction['full_name'];
        $payment['email'] = $transaction['email'];
        $payment['contact_no'] = $transaction['contact_no'];
        $payment['bill_amount'] = $transaction['bill_amount'];
    } else {
        // Create new payment record
        $payment_id = generatePaymentID($conn);
        $amount = $transaction['bill_amount'];
        $payment_type = 'online';
        $payment_status = 'pending';
        
        $insert_query = "INSERT INTO payments (payment_id, transaction_id, amount, payment_type, payment_date, payment_status) 
                        VALUES (?, ?, ?, ?, NOW(), ?)";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ssdss", $payment_id, $transaction_id, $amount, $payment_type, $payment_status);
        
        if (!$stmt->execute()) {
            $_SESSION['error_message'] = "Error creating payment record: " . $conn->error;
            redirect('../parking/view_status.php');
            exit;
        }
        
        // Get the newly created payment
        $query = "SELECT * FROM payments WHERE payment_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $payment_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        
        // Add transaction details to payment array
        $payment['vehicle_number'] = $transaction['vehicle_number'];
        $payment['vehicle_type'] = $transaction['vehicle_type'];
        $payment['full_name'] = $transaction['full_name'];
        $payment['email'] = $transaction['email'];
        $payment['contact_no'] = $transaction['contact_no'];
        $payment['bill_amount'] = $transaction['bill_amount'];
    }
} else {
    $_SESSION['error_message'] = "Missing payment ID or transaction ID.";
    redirect('../parking/view_status.php');
    exit;
}

// Handle payment form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // In a real-world scenario, this would integrate with an actual payment gateway
    // For this demo, we'll simulate a successful payment
    
    $payment_id = sanitize_input($conn, $_POST['payment_id']);
    
    // Start transaction to ensure data consistency
    $conn->begin_transaction();
    
    try {
        // Update payment status
        $update_query = "UPDATE payments SET payment_status = 'completed' WHERE payment_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("s", $payment_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating payment status: " . $conn->error);
        }
        
        // Update transaction bill status
        $transaction_id = $payment['transaction_id'];
        $update_query = "UPDATE parking_transactions SET bill_status = 'paid' WHERE transaction_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("s", $transaction_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating transaction status: " . $conn->error);
        }
        
        // Commit the transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Payment processed successfully! Payment ID: " . $payment_id;
        redirect('../parking/view_status.php');
        
    } catch (Exception $e) {
        // Rollback the transaction if an error occurred
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        redirect('process.php?payment_id=' . $payment_id);
    }
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-4">
    <h2>Payment Processing</h2>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success_message']; 
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Payment Details</h5>
                </div>
                <div class="card-body">
                    <?php if ($payment['payment_status'] == 'completed'): ?>
                        <div class="alert alert-success text-center">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <h4>Payment Already Completed</h4>
                            <p>This payment has been processed successfully.</p>
                            <p>Payment ID: <?php echo $payment['payment_id']; ?></p>
                            <p>Transaction ID: <?php echo $payment['transaction_id']; ?></p>
                            <a href="../parking/view_status.php" class="btn btn-primary mt-2">View Parking Status</a>
                        </div>
                    <?php else: ?>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Transaction Information</h6>
                                <p><strong>Transaction ID:</strong> <?php echo $payment['transaction_id']; ?></p>
                                <p><strong>Vehicle:</strong> <?php echo $payment['vehicle_number']; ?> (<?php echo ucfirst($payment['vehicle_type']); ?>)</p>
                                <p><strong>Amount:</strong> ₹<?php echo number_format($payment['amount'], 2); ?></p>
                                <p><strong>Payment ID:</strong> <?php echo $payment['payment_id']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Customer Information</h6>
                                <p><strong>Name:</strong> <?php echo $payment['full_name']; ?></p>
                                <p><strong>Email:</strong> <?php echo $payment['email']; ?></p>
                                <p><strong>Contact:</strong> <?php echo $payment['contact_no']; ?></p>
                            </div>
                        </div>
                        
                        <form method="post" action="process.php" id="paymentForm">
                            <input type="hidden" name="payment_id" value="<?php echo $payment['payment_id']; ?>">
                            
                            <div class="mb-4">
                                <h6>Payment Method</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="credit_card" value="credit_card" checked>
                                    <label class="form-check-label" for="credit_card">
                                        <i class="far fa-credit-card"></i> Credit/Debit Card
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="net_banking" value="net_banking">
                                    <label class="form-check-label" for="net_banking">
                                        <i class="fas fa-university"></i> Net Banking
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="upi" value="upi">
                                    <label class="form-check-label" for="upi">
                                        <i class="fas fa-mobile-alt"></i> UPI
                                    </label>
                                </div>
                            </div>
                            
                            <div id="credit_card_section">
                                <div class="mb-3">
                                    <label for="card_number" class="form-label">Card Number</label>
                                    <input type="text" class="form-control" id="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="expiry_date" class="form-label">Expiry Date</label>
                                        <input type="text" class="form-control" id="expiry_date" placeholder="MM/YY" maxlength="5">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="cvv" class="form-label">CVV</label>
                                        <input type="password" class="form-control" id="cvv" placeholder="123" maxlength="3">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="card_name" class="form-label">Name on Card</label>
                                    <input type="text" class="form-control" id="card_name" placeholder="John Doe">
                                </div>
                            </div>
                            
                            <div id="net_banking_section" style="display: none;">
                                <div class="mb-3">
                                    <label for="bank_name" class="form-label">Select Bank</label>
                                    <select class="form-select" id="bank_name">
                                        <option value="" selected disabled>Select your bank</option>
                                        <option value="sbi">State Bank of India</option>
                                        <option value="hdfc">HDFC Bank</option>
                                        <option value="icici">ICICI Bank</option>
                                        <option value="axis">Axis Bank</option>
                                        <option value="pnb">Punjab National Bank</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="upi_section" style="display: none;">
                                <div class="mb-3">
                                    <label for="upi_id" class="form-label">UPI ID</label>
                                    <input type="text" class="form-control" id="upi_id" placeholder="yourname@upi">
                                </div>
                            </div>
                            
                            <div class="mb-3 mt-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <span>Amount:</span>
                                            <span>₹<?php echo number_format($payment['amount'], 2); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Convenience Fee:</span>
                                            <span>₹0.00</span>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between">
                                            <strong>Total Amount:</strong>
                                            <strong>₹<?php echo number_format($payment['amount'], 2); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="terms_agreed" required>
                                <label class="form-check-label" for="terms_agreed">
                                    I agree to the terms and conditions
                                </label>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Pay ₹<?php echo number_format($payment['amount'], 2); ?></button>
                                <a href="../parking/view_status.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Payment Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Transaction ID:</span>
                        <span><?php echo $payment['transaction_id']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Payment ID:</span>
                        <span><?php echo $payment['payment_id']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Vehicle:</span>
                        <span><?php echo $payment['vehicle_number']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Vehicle Type:</span>
                        <span><?php echo ucfirst($payment['vehicle_type']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Amount:</span>
                        <span>₹<?php echo number_format($payment['amount'], 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Payment Type:</span>
                        <span><?php echo ucfirst($payment['payment_type']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Status:</span>
                        <span>
                            <?php if ($payment['payment_status'] == 'pending'): ?>
                                <span class="badge bg-warning">Pending</span>
                            <?php elseif ($payment['payment_status'] == 'completed'): ?>
                                <span class="badge bg-success">Completed</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Failed</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5>Payment Instructions</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            For demonstration purposes, any card details entered will be accepted.
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-lock text-primary me-2"></i>
                            Your payment information is secure and encrypted.
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-receipt text-primary me-2"></i>
                            A receipt will be sent to your registered email.
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-history text-primary me-2"></i>
                            You can view your payment history in your account.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle payment method sections
    const creditCardRadio = document.getElementById('credit_card');
    const netBankingRadio = document.getElementById('net_banking');
    const upiRadio = document.getElementById('upi');
    
    const creditCardSection = document.getElementById('credit_card_section');
    const netBankingSection = document.getElementById('net_banking_section');
    const upiSection = document.getElementById('upi_section');
    
    creditCardRadio.addEventListener('change', function() {
        creditCardSection.style.display = 'block';
        netBankingSection.style.display = 'none';
        upiSection.style.display = 'none';
    });
    
    netBankingRadio.addEventListener('change', function() {
        creditCardSection.style.display = 'none';
        netBankingSection.style.display = 'block';
        upiSection.style.display = 'none';
    });
    
    upiRadio.addEventListener('change', function() {
        creditCardSection.style.display = 'none';
        netBankingSection.style.display = 'none';
        upiSection.style.display = 'block';
    });
    
    // Card number formatting (add spaces)
    const cardNumberInput = document.getElementById('card_number');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = '';
            
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            
            e.target.value = formattedValue;
        });
    }
    
    // Expiry date formatting (add slash)
    const expiryDateInput = document.getElementById('expiry_date');
    if (expiryDateInput) {
        expiryDateInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/gi, '');
            
            if (value.length > 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            
            e.target.value = value;
        });
    }
    
    // Form validation
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            let isValid = true;
            let errorMessage = '';
            
            const termsAgreed = document.getElementById('terms_agreed');
            if (!termsAgreed.checked) {
                errorMessage = 'You must agree to the terms and conditions.';
                isValid = false;
            }
            
            if (creditCardRadio.checked) {
                const cardNumber = document.getElementById('card_number').value.replace(/\s+/g, '');
                const expiryDate = document.getElementById('expiry_date').value;
                const cvv = document.getElementById('cvv').value;
                const cardName = document.getElementById('card_name').value;
                
                if (cardNumber.length < 16) {
                    errorMessage = 'Please enter a valid card number.';
                    isValid = false;
                } else if (!expiryDate || expiryDate.length < 5) {
                    errorMessage = 'Please enter a valid expiry date.';
                    isValid = false;
                } else if (!cvv || cvv.length < 3) {
                    errorMessage = 'Please enter a valid CVV.';
                    isValid = false;
                } else if (!cardName.trim()) {
                    errorMessage = 'Please enter the name on card.';
                    isValid = false;
                }
            } else if (netBankingRadio.checked) {
                const bankName = document.getElementById('bank_name').value;
                if (!bankName) {
                    errorMessage = 'Please select your bank.';
                    isValid = false;
                }
            } else if (upiRadio.checked) {
                const upiId = document.getElementById('upi_id').value;
                if (!upiId || !upiId.includes('@')) {
                    errorMessage = 'Please enter a valid UPI ID.';
                    isValid = false;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                alert(errorMessage);
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
