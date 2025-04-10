<?php
require_once 'config/db_connect.php';

// Check if user is logged in, and if they are an admin, redirect accordingly
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin') {
        header("Location: admin/dashboard.php");
        exit;
    }
}

// Get parking availability stats
$query = "SELECT 
    SUM(total_2_wheeler_slots) AS total_2_wheeler, 
    SUM(total_4_wheeler_slots) AS total_4_wheeler,
    SUM(total_commercial_slots) AS total_commercial,
    SUM(occupied_2_wheeler_slots) AS occupied_2_wheeler,
    SUM(occupied_4_wheeler_slots) AS occupied_4_wheeler,
    SUM(occupied_commercial_slots) AS occupied_commercial
FROM parking_areas";

$result = $conn->query($query);
$parking_stats = $result->fetch_assoc();

// Get latest transactions for display
$query = "SELECT pt.transaction_id, v.vehicle_number, v.vehicle_type, pt.entry_time, pt.status 
          FROM parking_transactions pt
          JOIN vehicles v ON pt.vehicle_id = v.vehicle_id
          ORDER BY pt.entry_time DESC LIMIT 5";
$result = $conn->query($query);
$transactions_data = [];
while ($row = $result->fetch_assoc()) {
    $transactions_data[] = $row;
}

// Include header
include 'includes/header.php';
// Include navbar
include 'includes/navbar.php';
?>

<div class="container mt-4">
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

    <div class="jumbotron bg-light p-5 rounded">
        <h1 class="display-4">Vehicle Parking Management System</h1>
        <p class="lead">Efficiently manage your parking facility with our comprehensive system.</p>
        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="mt-4">
                <a href="auth/login.php" class="btn btn-primary me-2">Login</a>
                <a href="auth/register.php" class="btn btn-secondary">Register</a>
            </div>
        <?php else: ?>
            <div class="mt-4">
                <a href="parking/entry.php" class="btn btn-primary me-2">Vehicle Entry</a>
                <a href="parking/exit.php" class="btn btn-secondary me-2">Vehicle Exit</a>
                <a href="parking/view_status.php" class="btn btn-info">View Status</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="row mt-5">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Current Parking Availability</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Vehicle Type</th>
                                    <th>Available Slots</th>
                                    <th>Total Slots</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>2-Wheeler</td>
                                    <td><?php echo $parking_stats['total_2_wheeler'] - $parking_stats['occupied_2_wheeler']; ?></td>
                                    <td><?php echo $parking_stats['total_2_wheeler']; ?></td>
                                    <td>
                                        <?php if (($parking_stats['total_2_wheeler'] - $parking_stats['occupied_2_wheeler']) > 10): ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php elseif (($parking_stats['total_2_wheeler'] - $parking_stats['occupied_2_wheeler']) > 0): ?>
                                            <span class="badge bg-warning">Limited</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Full</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>4-Wheeler</td>
                                    <td><?php echo $parking_stats['total_4_wheeler'] - $parking_stats['occupied_4_wheeler']; ?></td>
                                    <td><?php echo $parking_stats['total_4_wheeler']; ?></td>
                                    <td>
                                        <?php if (($parking_stats['total_4_wheeler'] - $parking_stats['occupied_4_wheeler']) > 10): ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php elseif (($parking_stats['total_4_wheeler'] - $parking_stats['occupied_4_wheeler']) > 0): ?>
                                            <span class="badge bg-warning">Limited</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Full</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Commercial</td>
                                    <td><?php echo $parking_stats['total_commercial'] - $parking_stats['occupied_commercial']; ?></td>
                                    <td><?php echo $parking_stats['total_commercial']; ?></td>
                                    <td>
                                        <?php if (($parking_stats['total_commercial'] - $parking_stats['occupied_commercial']) > 5): ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php elseif (($parking_stats['total_commercial'] - $parking_stats['occupied_commercial']) > 0): ?>
                                            <span class="badge bg-warning">Limited</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Full</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Latest Transactions</h5>
                </div>
                <div class="card-body">
                    <?php if (count($transactions_data) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Vehicle</th>
                                        <th>Type</th>
                                        <th>Entry Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions_data as $row): ?>
                                        <tr>
                                            <td><?php echo $row['transaction_id']; ?></td>
                                            <td><?php echo $row['vehicle_number']; ?></td>
                                            <td><?php echo $row['vehicle_type']; ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($row['entry_time'])); ?></td>
                                            <td>
                                                <?php if ($row['status'] == 'in'): ?>
                                                    <span class="badge bg-primary">In</span>
                                                <?php elseif ($row['status'] == 'out'): ?>
                                                    <span class="badge bg-secondary">Out</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No transactions recorded yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Our Services</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="text-center">
                                <i class="fas fa-car fa-3x mb-3 text-primary"></i>
                                <h4>Vehicle Management</h4>
                                <p>Easily track entry and exit of all types of vehicles in your parking facility.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="text-center">
                                <i class="fas fa-money-bill-wave fa-3x mb-3 text-success"></i>
                                <h4>Payment Processing</h4>
                                <p>Efficient payment collection for parking with various payment options.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="text-center">
                                <i class="fas fa-chart-line fa-3x mb-3 text-info"></i>
                                <h4>Reports & Analytics</h4>
                                <p>Comprehensive reports to analyze parking usage and revenue generation.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
