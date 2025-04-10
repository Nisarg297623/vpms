<?php
require_once '../config/db_connect.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "You must be logged in to access this page.";
    redirect('../auth/login.php');
}

// Get user info
$user_id = $_SESSION['user_id'];

// Get user's current and past parking transactions
$query = "SELECT pt.*, v.vehicle_number, v.vehicle_type, pa.area_name,
          (SELECT COUNT(*) FROM payments WHERE transaction_id = pt.transaction_id AND payment_status = 'completed') as payment_completed
          FROM parking_transactions pt
          JOIN vehicles v ON pt.vehicle_id = v.vehicle_id
          JOIN parking_areas pa ON pt.area_id = pa.area_id
          WHERE v.user_id = ?
          ORDER BY 
            CASE pt.status 
              WHEN 'in' THEN 1 
              WHEN 'out' THEN 2 
              WHEN 'completed' THEN 3 
              ELSE 4 
            END,
            pt.entry_time DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result(); // Changed from $result to $transactions

// Get user's vehicles
$vehicles_query = "SELECT * FROM vehicles WHERE user_id = ?";
$stmt = $conn->prepare($vehicles_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vehicles = $stmt->get_result(); // Changed from $vehicles_result to $vehicles

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-4">
    <h2>Parking Status</h2>
    
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
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Current & Past Parking Transactions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Vehicle</th>
                                    <th>Parking Area</th>
                                    <th>Entry Time</th>
                                    <th>Exit Time</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($transactions->num_rows > 0): ?>
                                    <?php while ($row = $transactions->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['transaction_id']; ?></td>
                                            <td>
                                                <?php echo $row['vehicle_number']; ?>
                                                <span class="badge bg-secondary"><?php echo $row['vehicle_type']; ?></span>
                                            </td>
                                            <td><?php echo $row['area_name']; ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($row['entry_time'])); ?></td>
                                            <td>
                                                <?php echo $row['exit_time'] ? date('d M Y, h:i A', strtotime($row['exit_time'])) : '-'; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['status'] == 'in'): ?>
                                                    <span class="badge bg-primary">In</span>
                                                <?php elseif ($row['status'] == 'out'): ?>
                                                    <span class="badge bg-secondary">Out</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>₹<?php echo number_format($row['bill_amount'], 2); ?></td>
                                            <td>
                                                <?php if ($row['bill_amount'] > 0): ?>
                                                    <?php if ($row['bill_status'] == 'paid' || $row['payment_completed'] > 0): ?>
                                                        <span class="badge bg-success">Paid</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['status'] == 'in'): ?>
                                                    <a href="exit.php" class="btn btn-sm btn-primary">Exit</a>
                                                <?php elseif ($row['bill_status'] == 'pending' && $row['payment_completed'] == 0): ?>
                                                    <a href="../payment/process.php?transaction_id=<?php echo $row['transaction_id']; ?>" class="btn btn-sm btn-warning">Pay</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No parking transactions found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5>Your Vehicles</h5>
                        <a href="entry.php" class="btn btn-sm btn-primary">Register New</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($vehicles->num_rows > 0): ?>
                        <div class="list-group">
                            <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo $vehicle['vehicle_number']; ?></h6>
                                        <small class="text-muted"><?php echo ucfirst($vehicle['vehicle_type']); ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <?php if (!empty($vehicle['vehicle_make']) || !empty($vehicle['vehicle_model'])): ?>
                                            <?php echo $vehicle['vehicle_make'] . ' ' . $vehicle['vehicle_model']; ?>
                                        <?php endif; ?>
                                        <?php if (!empty($vehicle['vehicle_color'])): ?>
                                            <span class="text-muted">(<?php echo $vehicle['vehicle_color']; ?>)</span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <?php
                                    // Check if vehicle is currently parked
                                    $check_query = "SELECT transaction_id FROM parking_transactions 
                                                   WHERE vehicle_id = ? AND status = 'in'";
                                    $stmt = $conn->prepare($check_query);
                                    $stmt->bind_param("s", $vehicle['vehicle_id']);
                                    $stmt->execute();
                                    $is_parked = $stmt->get_result()->num_rows > 0;
                                    ?>
                                    
                                    <?php if ($is_parked): ?>
                                        <span class="badge bg-primary">Currently Parked</span>
                                    <?php else: ?>
                                        <a href="entry.php" class="btn btn-sm btn-outline-primary mt-2">Park Now</a>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center">
                            <p class="text-muted">You don't have any registered vehicles.</p>
                            <a href="entry.php" class="btn btn-primary">Register a Vehicle</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5>Parking Summary</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get parking summary
                    $summary_query = "SELECT 
                                     COUNT(*) as total_transactions,
                                     SUM(CASE WHEN status = 'in' THEN 1 ELSE 0 END) as active_parking,
                                     SUM(bill_amount) as total_spent,
                                     SUM(CASE WHEN MONTH(entry_time) = MONTH(CURRENT_DATE) AND YEAR(entry_time) = YEAR(CURRENT_DATE) THEN 1 ELSE 0 END) as this_month
                                     FROM parking_transactions pt
                                     JOIN vehicles v ON pt.vehicle_id = v.vehicle_id
                                     WHERE v.user_id = ?";
                    $stmt = $conn->prepare($summary_query);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $summary = $stmt->get_result()->fetch_assoc();
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h3><?php echo $summary['total_transactions']; ?></h3>
                            <p class="text-muted mb-0">Total Parkings</p>
                        </div>
                        <div class="col-6 mb-3">
                            <h3><?php echo $summary['active_parking']; ?></h3>
                            <p class="text-muted mb-0">Active Now</p>
                        </div>
                        <div class="col-6">
                            <h3><?php echo $summary['this_month']; ?></h3>
                            <p class="text-muted mb-0">This Month</p>
                        </div>
                        <div class="col-6">
                            <h3>₹<?php echo number_format($summary['total_spent'], 2); ?></h3>
                            <p class="text-muted mb-0">Total Spent</p>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="entry.php" class="btn btn-primary w-100">Park a Vehicle</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any interactive elements if needed
});
</script>

<?php include '../includes/footer.php'; ?>
