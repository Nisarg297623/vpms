<?php
require_once '../config/db_connect.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "You must be logged in to access this page.";
    redirect('../auth/login.php');
}

// Get user info
$user_id = $_SESSION['user_id'];

// Get user's active parking transactions
$query = "SELECT pt.*, v.vehicle_number, v.vehicle_type, pa.area_name
          FROM parking_transactions pt
          JOIN vehicles v ON pt.vehicle_id = v.vehicle_id
          JOIN parking_areas pa ON pt.area_id = pa.area_id
          WHERE v.user_id = ? AND pt.status = 'in'
          ORDER BY pt.entry_time DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_transactions = $stmt->get_result();
$has_active_transactions = $active_transactions->num_rows > 0;

// Handle exit form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['transaction_id'])) {
    try {
        $transaction_id = sanitize_input($conn, $_POST['transaction_id']);
        $calculated_fee = sanitize_input($conn, $_POST['calculated_fee']);
        $payment_type = sanitize_input($conn, $_POST['payment_type']);

        // Start transaction
        $conn->begin_transaction();

        // Get transaction details
        $query = "SELECT pt.*, v.vehicle_type, pa.area_id 
                 FROM parking_transactions pt
                 JOIN vehicles v ON pt.vehicle_id = v.vehicle_id
                 JOIN parking_areas pa ON pt.area_id = pa.area_id
                 WHERE pt.transaction_id = ? AND pt.status = 'in'";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $transaction_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaction = $result->fetch_assoc();

        if (!$transaction) {
            throw new Exception("Invalid transaction or vehicle already exited.");
        }

        // Update transaction status
        $update_query = "UPDATE parking_transactions SET 
                        exit_time = NOW(), 
                        status = 'completed', 
                        bill_amount = ?
                        WHERE transaction_id = ?";

        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ds", $calculated_fee, $transaction_id);
        if (!$stmt->execute()) {
            throw new Exception("Error updating transaction.");
        }

        // Update parking area occupancy
        $column_occupied = "";
        switch ($transaction['vehicle_type']) {
            case '2-wheeler':
                $column_occupied = "occupied_2_wheeler_slots";
                break;
            case '4-wheeler':
                $column_occupied = "occupied_4_wheeler_slots";
                break;
            case 'commercial':
                $column_occupied = "occupied_commercial_slots";
                break;
        }

        $update_query = "UPDATE parking_areas SET $column_occupied = $column_occupied - 1 WHERE area_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $transaction['area_id']);
        if (!$stmt->execute()) {
            throw new Exception("Error updating parking area.");
        }

        // Create payment record
        $payment_id = generatePaymentID($conn);
        $payment_status = 'pending';

        $insert_query = "INSERT INTO payments (payment_id, transaction_id, amount, payment_type, payment_date, payment_status) 
                        VALUES (?, ?, ?, ?, NOW(), ?)";

        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ssdss", $payment_id, $transaction_id, $calculated_fee, $payment_type, $payment_status);
        if (!$stmt->execute()) {
            throw new Exception("Error creating payment record.");
        }

        $conn->commit();
        $_SESSION['success_message'] = "Vehicle exit processed successfully. Payment ID: " . $payment_id;

        if ($payment_type == 'online') {
            redirect('../payment/process.php?payment_id=' . $payment_id);
        } else {
            redirect('view_status.php');
        }

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        redirect('exit.php');
    }
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-4">
    <h2>Vehicle Exit</h2>

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
                    <h5>Process Vehicle Exit</h5>
                </div>
                <div class="card-body">
                    <?php if ($has_active_transactions): ?>
                        <form method="post" action="exit.php" id="exitForm">
                            <div class="mb-3">
                                <label for="transaction_select" class="form-label">Select Parked Vehicle</label>
                                <select class="form-select" id="transaction_select" name="transaction_id" required>
                                    <option value="" disabled selected>Select a vehicle to exit</option>
                                    <?php while ($transaction = $active_transactions->fetch_assoc()): ?>
                                        <option value="<?php echo $transaction['transaction_id']; ?>">
                                            <?php echo $transaction['vehicle_number']; ?> - 
                                            <?php echo ucfirst($transaction['vehicle_type']); ?> - 
                                            Parked since: <?php echo date('d M Y, h:i A', strtotime($transaction['entry_time'])); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_type" id="payment_cash" value="cash" checked>
                                    <label class="form-check-label" for="payment_cash">Cash</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_type" id="payment_online" value="online">
                                    <label class="form-check-label" for="payment_online">Online Payment</label>
                                </div>
                            </div>

                            <input type="hidden" name="calculated_fee" id="calculated_fee" value="0">

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">Process Exit & Payment</button>
                                <a href="../index.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> You don't have any active parking transactions.
                        </div>
                        <a href="entry.php" class="btn btn-primary">Park a Vehicle</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const exitForm = document.getElementById('exitForm');
    const transactionSelect = document.getElementById('transaction_select');

    if (exitForm) {
        exitForm.addEventListener('submit', function(e) {
            if (!transactionSelect || !transactionSelect.value) {
                e.preventDefault();
                alert('Please select a vehicle to exit');
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>