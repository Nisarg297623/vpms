<?php
require_once '../config/db_connect.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['error_message'] = "You must be logged in as an administrator to view this page.";
    redirect('../auth/login.php');
}

// Initialize variables for date range filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'transaction';

// Prepare date range condition for SQL queries
$date_condition = "";
$params = [];
$param_types = "";

if (!empty($start_date) && !empty($end_date)) {
    if ($report_type == 'transaction') {
        $date_condition = " WHERE DATE(pt.entry_time) BETWEEN ? AND ?";
    } elseif ($report_type == 'payment') {
        $date_condition = " WHERE DATE(p.payment_date) BETWEEN ? AND ?";
    } elseif ($report_type == 'vehicle') {
        $date_condition = " WHERE DATE(v.registration_date) BETWEEN ? AND ?";
    } elseif ($report_type == 'user') {
        $date_condition = " WHERE DATE(u.registration_date) BETWEEN ? AND ?";
    }
    
    $params = [$start_date, $end_date];
    $param_types = "ss";
}

// Generate the appropriate report based on type
if ($report_type == 'transaction') {
    // Transaction Report
    $query = "SELECT pt.transaction_id, v.vehicle_number, v.vehicle_type, u.username, 
              pt.entry_time, pt.exit_time, 
              TIMESTAMPDIFF(HOUR, pt.entry_time, IFNULL(pt.exit_time, NOW())) as duration_hours,
              pt.bill_amount, pt.status, pt.bill_status, pt.feedback
              FROM parking_transactions pt
              JOIN vehicles v ON pt.vehicle_id = v.vehicle_id
              JOIN users u ON v.user_id = u.user_id" . $date_condition . "
              ORDER BY pt.entry_time DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $transactions = $stmt->get_result();
    
    // Get summary statistics
    $summary_query = "SELECT 
                      COUNT(*) as total_transactions,
                      SUM(CASE WHEN status = 'in' THEN 1 ELSE 0 END) as active_transactions,
                      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_transactions,
                      SUM(bill_amount) as total_amount,
                      SUM(CASE WHEN bill_status = 'paid' THEN bill_amount ELSE 0 END) as paid_amount,
                      SUM(CASE WHEN bill_status = 'pending' THEN bill_amount ELSE 0 END) as pending_amount,
                      AVG(TIMESTAMPDIFF(HOUR, entry_time, IFNULL(exit_time, NOW()))) as avg_duration
                      FROM parking_transactions pt" . $date_condition;
    
    $stmt = $conn->prepare($summary_query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();
    
} elseif ($report_type == 'payment') {
    // Payment Report
    $query = "SELECT p.payment_id, p.transaction_id, v.vehicle_number, v.vehicle_type, 
              u.username, p.amount, p.payment_type, p.payment_date, p.payment_status
              FROM payments p
              JOIN parking_transactions pt ON p.transaction_id = pt.transaction_id
              JOIN vehicles v ON pt.vehicle_id = v.vehicle_id
              JOIN users u ON v.user_id = u.user_id" . $date_condition . "
              ORDER BY p.payment_date DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $payments = $stmt->get_result();
    
    // Get payment summary
    $summary_query = "SELECT 
                     COUNT(*) as total_payments,
                     SUM(amount) as total_amount,
                     SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as completed_amount,
                     SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                     SUM(CASE WHEN payment_status = 'failed' THEN amount ELSE 0 END) as failed_amount,
                     SUM(CASE WHEN payment_type = 'cash' THEN amount ELSE 0 END) as cash_amount,
                     SUM(CASE WHEN payment_type = 'online' THEN amount ELSE 0 END) as online_amount
                     FROM payments p" . $date_condition;
    
    $stmt = $conn->prepare($summary_query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();
    
} elseif ($report_type == 'vehicle') {
    // Vehicle Type Report
    $query = "SELECT v.vehicle_id, v.vehicle_number, v.vehicle_type, v.vehicle_make, v.vehicle_model,
              u.username, u.full_name, COUNT(pt.transaction_id) as total_parking,
              SUM(pt.bill_amount) as total_amount
              FROM vehicles v
              JOIN users u ON v.user_id = u.user_id
              LEFT JOIN parking_transactions pt ON v.vehicle_id = pt.vehicle_id
              GROUP BY v.vehicle_id
              ORDER BY total_parking DESC";
    
    $result = $conn->query($query);
    $vehicles = $result;
    
    // Get vehicle type distribution
    $type_query = "SELECT vehicle_type, COUNT(*) as count, 
                  (COUNT(*) / (SELECT COUNT(*) FROM vehicles)) * 100 as percentage
                  FROM vehicles
                  GROUP BY vehicle_type";
    
    $vehicle_types = $conn->query($type_query);
    
} elseif ($report_type == 'user') {
    // User Activity Report
    $query = "SELECT u.user_id, u.username, u.full_name, u.email, u.contact_no,
              COUNT(v.vehicle_id) as total_vehicles,
              COUNT(pt.transaction_id) as total_transactions,
              SUM(pt.bill_amount) as total_amount,
              MAX(pt.entry_time) as last_activity
              FROM users u
              LEFT JOIN vehicles v ON u.user_id = v.user_id
              LEFT JOIN parking_transactions pt ON v.vehicle_id = pt.vehicle_id
              WHERE u.user_type = 'user'
              GROUP BY u.user_id
              ORDER BY total_transactions DESC";
    
    $result = $conn->query($query);
    $users = $result;
    
    // Get user registration summary by month
    $month_query = "SELECT DATE_FORMAT(registration_date, '%Y-%m') as month, 
                   COUNT(*) as count
                   FROM users
                   WHERE user_type = 'user'
                   GROUP BY month
                   ORDER BY month DESC
                   LIMIT 12";
    
    $user_registrations = $conn->query($month_query);
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-4">
    <h2>Reports</h2>
    
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

    <div class="card mb-4">
        <div class="card-header">
            <h5>Report Options</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="reports.php" class="row g-3">
                <div class="col-md-3">
                    <label for="report_type" class="form-label">Report Type</label>
                    <select name="report_type" id="report_type" class="form-select">
                        <option value="transaction" <?php echo $report_type == 'transaction' ? 'selected' : ''; ?>>Transaction Report</option>
                        <option value="payment" <?php echo $report_type == 'payment' ? 'selected' : ''; ?>>Payment Report</option>
                        <option value="vehicle" <?php echo $report_type == 'vehicle' ? 'selected' : ''; ?>>Vehicle Report</option>
                        <option value="user" <?php echo $report_type == 'user' ? 'selected' : ''; ?>>User Activity Report</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                    <?php if (isset($transactions) || isset($payments) || isset($vehicles) || isset($users)): ?>
                        <button type="button" id="printReport" class="btn btn-secondary ms-2">
                            <i class="fas fa-print"></i> Print
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div id="reportContent">
        <?php if ($report_type == 'transaction' && isset($transactions)): ?>
            <!-- Transaction Report -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Transaction Report (<?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>)</h5>
                </div>
                <div class="card-body">
                    <!-- Summary Stats -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3><?php echo $summary['total_transactions']; ?></h3>
                                    <p class="mb-0">Total Transactions</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3><?php echo $summary['active_transactions']; ?></h3>
                                    <p class="mb-0">Active Parking</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3>₹<?php echo number_format($summary['total_amount'] ?? 0, 2); ?></h3>
                                    <p class="mb-0">Total Amount</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3><?php echo round($summary['avg_duration'] ?? 0, 1); ?> hrs</h3>
                                    <p class="mb-0">Avg. Duration</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Vehicle</th>
                                    <th>User</th>
                                    <th>Entry Time</th>
                                    <th>Exit Time</th>
                                    <th>Duration</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment</th>
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
                                            <td><?php echo $row['username']; ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($row['entry_time'])); ?></td>
                                            <td><?php echo $row['exit_time'] ? date('d M Y, h:i A', strtotime($row['exit_time'])) : '-'; ?></td>
                                            <td>
                                                <?php 
                                                $hours = $row['duration_hours'];
                                                if ($hours < 1) {
                                                    echo round($hours * 60) . " mins";
                                                } elseif ($hours < 24) {
                                                    echo floor($hours) . " hrs " . round(($hours - floor($hours)) * 60) . " mins";
                                                } else {
                                                    $days = floor($hours / 24);
                                                    $remaining_hours = $hours % 24;
                                                    echo $days . " days " . round($remaining_hours) . " hrs";
                                                }
                                                ?>
                                            </td>
                                            <td>₹<?php echo number_format($row['bill_amount'], 2); ?></td>
                                            <td>
                                                <?php if ($row['status'] == 'in'): ?>
                                                    <span class="badge bg-primary">In</span>
                                                <?php elseif ($row['status'] == 'out'): ?>
                                                    <span class="badge bg-secondary">Out</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['bill_status'] == 'paid'): ?>
                                                    <span class="badge bg-success">Paid</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No transactions found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Transaction Analytics -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Vehicle Type Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="vehicle-type-chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Payment Status</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="payment-status-chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($report_type == 'payment' && isset($payments)): ?>
            <!-- Payment Report -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Payment Report (<?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>)</h5>
                </div>
                <div class="card-body">
                    <!-- Summary Stats -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3><?php echo $summary['total_payments']; ?></h3>
                                    <p class="mb-0">Total Payments</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3>₹<?php echo number_format($summary['total_amount'] ?? 0, 2); ?></h3>
                                    <p class="mb-0">Total Amount</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3>₹<?php echo number_format($summary['completed_amount'] ?? 0, 2); ?></h3>
                                    <p class="mb-0">Completed Payments</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3>₹<?php echo number_format($summary['pending_amount'] ?? 0, 2); ?></h3>
                                    <p class="mb-0">Pending Payments</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Transaction ID</th>
                                    <th>Vehicle</th>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Payment Type</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($payments->num_rows > 0): ?>
                                    <?php while ($row = $payments->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['payment_id']; ?></td>
                                            <td><?php echo $row['transaction_id']; ?></td>
                                            <td>
                                                <?php echo $row['vehicle_number']; ?>
                                                <span class="badge bg-secondary"><?php echo $row['vehicle_type']; ?></span>
                                            </td>
                                            <td><?php echo $row['username']; ?></td>
                                            <td>₹<?php echo number_format($row['amount'], 2); ?></td>
                                            <td><?php echo ucfirst($row['payment_type']); ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($row['payment_date'])); ?></td>
                                            <td>
                                                <?php if ($row['payment_status'] == 'pending'): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php elseif ($row['payment_status'] == 'completed'): ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Failed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No payments found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Payment Analytics -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Payment Method Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="payment-method-chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Payment Status Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="payment-status-distribution-chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($report_type == 'vehicle' && isset($vehicles)): ?>
            <!-- Vehicle Report -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Vehicle Report</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <h6>Vehicle Type Distribution</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Vehicle Type</th>
                                            <th>Count</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $vehicle_types->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo ucfirst($row['vehicle_type']); ?></td>
                                                <td><?php echo $row['count']; ?></td>
                                                <td><?php echo round($row['percentage'], 2); ?>%</td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <canvas id="vehicle-distribution-chart" height="200"></canvas>
                        </div>
                    </div>
                    
                    <h6>Vehicle Details</h6>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Vehicle ID</th>
                                    <th>Number</th>
                                    <th>Type</th>
                                    <th>Make & Model</th>
                                    <th>Owner</th>
                                    <th>Total Parking</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($vehicles->num_rows > 0): ?>
                                    <?php while ($row = $vehicles->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['vehicle_id']; ?></td>
                                            <td><?php echo $row['vehicle_number']; ?></td>
                                            <td><?php echo ucfirst($row['vehicle_type']); ?></td>
                                            <td><?php echo $row['vehicle_make'] . ' ' . $row['vehicle_model']; ?></td>
                                            <td><?php echo $row['full_name']; ?></td>
                                            <td><?php echo $row['total_parking']; ?></td>
                                            <td>₹<?php echo number_format($row['total_amount'] ?? 0, 2); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No vehicles found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($report_type == 'user' && isset($users)): ?>
            <!-- User Activity Report -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>User Activity Report</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <h6>Monthly User Registrations</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>New Users</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $user_registrations->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                                                <td><?php echo $row['count']; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <canvas id="user-registration-chart" height="200"></canvas>
                        </div>
                    </div>
                    
                    <h6>User Activity Details</h6>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Vehicles</th>
                                    <th>Transactions</th>
                                    <th>Total Amount</th>
                                    <th>Last Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users->num_rows > 0): ?>
                                    <?php while ($row = $users->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['user_id']; ?></td>
                                            <td><?php echo $row['username']; ?></td>
                                            <td><?php echo $row['full_name']; ?></td>
                                            <td><?php echo $row['email']; ?></td>
                                            <td><?php echo $row['contact_no']; ?></td>
                                            <td><?php echo $row['total_vehicles']; ?></td>
                                            <td><?php echo $row['total_transactions']; ?></td>
                                            <td>₹<?php echo number_format($row['total_amount'] ?? 0, 2); ?></td>
                                            <td><?php echo $row['last_activity'] ? date('d M Y, h:i A', strtotime($row['last_activity'])) : 'Never'; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No users found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($report_type == 'transaction' && isset($transactions)): ?>
        // Vehicle Type Chart
        const vehicleTypeCtx = document.getElementById('vehicle-type-chart').getContext('2d');
        
        // Get vehicle type distribution
        <?php
        $conn->data_seek($transactions, 0);
        $vehicle_type_data = [
            '2-wheeler' => 0,
            '4-wheeler' => 0,
            'commercial' => 0
        ];
        
        while ($row = $transactions->fetch_assoc()) {
            $vehicle_type_data[$row['vehicle_type']]++;
        }
        ?>
        
        new Chart(vehicleTypeCtx, {
            type: 'pie',
            data: {
                labels: ['2-Wheeler', '4-Wheeler', 'Commercial'],
                datasets: [{
                    data: [
                        <?php echo $vehicle_type_data['2-wheeler']; ?>,
                        <?php echo $vehicle_type_data['4-wheeler']; ?>,
                        <?php echo $vehicle_type_data['commercial']; ?>
                    ],
                    backgroundColor: ['#4CAF50', '#2196F3', '#FFC107']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Payment Status Chart
        const paymentStatusCtx = document.getElementById('payment-status-chart').getContext('2d');
        
        new Chart(paymentStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Pending'],
                datasets: [{
                    data: [
                        <?php echo $summary['paid_amount'] ?? 0; ?>,
                        <?php echo $summary['pending_amount'] ?? 0; ?>
                    ],
                    backgroundColor: ['#4CAF50', '#FFC107']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
    <?php elseif ($report_type == 'payment' && isset($payments)): ?>
        // Payment Method Chart
        const methodCtx = document.getElementById('payment-method-chart').getContext('2d');
        
        new Chart(methodCtx, {
            type: 'pie',
            data: {
                labels: ['Cash', 'Online'],
                datasets: [{
                    data: [
                        <?php echo $summary['cash_amount'] ?? 0; ?>,
                        <?php echo $summary['online_amount'] ?? 0; ?>
                    ],
                    backgroundColor: ['#4CAF50', '#2196F3']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Payment Status Distribution Chart
        const statusCtx = document.getElementById('payment-status-distribution-chart').getContext('2d');
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending', 'Failed'],
                datasets: [{
                    data: [
                        <?php echo $summary['completed_amount'] ?? 0; ?>,
                        <?php echo $summary['pending_amount'] ?? 0; ?>,
                        <?php echo $summary['failed_amount'] ?? 0; ?>
                    ],
                    backgroundColor: ['#4CAF50', '#FFC107', '#F44336']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
    <?php elseif ($report_type == 'vehicle' && isset($vehicle_types)): ?>
        // Vehicle Distribution Chart
        const vehicleDistCtx = document.getElementById('vehicle-distribution-chart').getContext('2d');
        
        <?php
        $conn->data_seek($vehicle_types, 0);
        $vehicle_labels = [];
        $vehicle_counts = [];
        
        while ($row = $vehicle_types->fetch_assoc()) {
            $vehicle_labels[] = ucfirst($row['vehicle_type']);
            $vehicle_counts[] = $row['count'];
        }
        ?>
        
        new Chart(vehicleDistCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($vehicle_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($vehicle_counts); ?>,
                    backgroundColor: ['#4CAF50', '#2196F3', '#FFC107']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
    <?php elseif ($report_type == 'user' && isset($user_registrations)): ?>
        // User Registration Chart
        const userRegCtx = document.getElementById('user-registration-chart').getContext('2d');
        
        <?php
        $conn->data_seek($user_registrations, 0);
        $reg_labels = [];
        $reg_counts = [];
        
        while ($row = $user_registrations->fetch_assoc()) {
            $reg_labels[] = date('M Y', strtotime($row['month'] . '-01'));
            $reg_counts[] = $row['count'];
        }
        // Reverse arrays to show chronological order
        $reg_labels = array_reverse($reg_labels);
        $reg_counts = array_reverse($reg_counts);
        ?>
        
        new Chart(userRegCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($reg_labels); ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?php echo json_encode($reg_counts); ?>,
                    backgroundColor: '#2196F3'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    <?php endif; ?>

    // Print functionality
    document.getElementById('printReport').addEventListener('click', function() {
        const printContents = document.getElementById('reportContent').innerHTML;
        const originalContents = document.body.innerHTML;
        
        document.body.innerHTML = `
            <div style="padding: 20px;">
                <h1 style="text-align: center;">Vehicle Parking Management System</h1>
                <h2 style="text-align: center;">${document.querySelector('.card-header h5').textContent}</h2>
                ${printContents}
            </div>
        `;
        
        window.print();
        document.body.innerHTML = originalContents;
        location.reload();
    });
});
</script>

<?php include '../includes/footer.php'; ?>
