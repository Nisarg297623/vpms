<?php
require_once '../config/db_connect.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['error_message'] = "You must be logged in as an administrator to view this page.";
    redirect('../auth/login.php');
}

// Get total registered users
$query = "SELECT COUNT(*) as total_users FROM users WHERE user_type != 'admin'";
$result = $conn->query($query);
$total_users = $result->fetch_assoc()['total_users'];

// Get total vehicles
$query = "SELECT COUNT(*) as total_vehicles FROM vehicles";
$result = $conn->query($query);
$total_vehicles = $result->fetch_assoc()['total_vehicles'];

// Get active parking transactions
$query = "SELECT COUNT(*) as active_transactions FROM parking_transactions WHERE status = 'in'";
$result = $conn->query($query);
$active_transactions = $result->fetch_assoc()['active_transactions'];

// Get total revenue
$query = "SELECT SUM(amount) as total_revenue FROM payments WHERE payment_status = 'completed'";
$result = $conn->query($query);
$total_revenue = $result->fetch_assoc()['total_revenue'] ?? 0;

// Get recent transactions
$query = "SELECT pt.transaction_id, v.vehicle_number, v.vehicle_type, u.username, pt.entry_time, pt.exit_time, pt.status, pt.bill_amount 
          FROM parking_transactions pt
          JOIN vehicles v ON pt.vehicle_id = v.vehicle_id
          JOIN users u ON v.user_id = u.user_id
          ORDER BY pt.entry_time DESC LIMIT 10";
$recent_transactions = $conn->query($query);

// Get parking space statistics
$query = "SELECT * FROM parking_areas";
$parking_areas = $conn->query($query);

// Get vehicle type distribution
$query = "SELECT vehicle_type, COUNT(*) as count FROM vehicles GROUP BY vehicle_type";
$vehicle_distribution = $conn->query($query);
$vehicle_types = [];
$vehicle_counts = [];

while ($row = $vehicle_distribution->fetch_assoc()) {
    $vehicle_types[] = $row['vehicle_type'];
    $vehicle_counts[] = $row['count'];
}

// Get monthly revenue for the chart
$query = "SELECT MONTH(payment_date) as month, SUM(amount) as revenue
          FROM payments 
          WHERE payment_status = 'completed' AND YEAR(payment_date) = YEAR(CURRENT_DATE)
          GROUP BY MONTH(payment_date)
          ORDER BY month";
$monthly_revenue = $conn->query($query);
$months = [];
$revenues = [];

while ($row = $monthly_revenue->fetch_assoc()) {
    $month_name = date('M', mktime(0, 0, 0, $row['month'], 10));
    $months[] = $month_name;
    $revenues[] = $row['revenue'];
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-4">
    <h2>Admin Dashboard</h2>
    
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

    <div class="dashboard-stats">
        <div class="stat-card">
            <i class="fas fa-users fa-3x text-primary"></i>
            <div class="stat-card-value"><?php echo $total_users; ?></div>
            <div class="stat-card-label">Registered Users</div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-car fa-3x text-success"></i>
            <div class="stat-card-value"><?php echo $total_vehicles; ?></div>
            <div class="stat-card-label">Registered Vehicles</div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-parking fa-3x text-warning"></i>
            <div class="stat-card-value"><?php echo $active_transactions; ?></div>
            <div class="stat-card-label">Active Parking</div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-money-bill-wave fa-3x text-info"></i>
            <div class="stat-card-value">₹<?php echo number_format($total_revenue, 2); ?></div>
            <div class="stat-card-label">Total Revenue</div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Parking Availability</h5>
                </div>
                <div class="card-body">
                    <canvas id="parking-availability-chart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Monthly Revenue</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenue-chart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5>Recent Transactions</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Vehicle</th>
                            <th>Type</th>
                            <th>User</th>
                            <th>Entry Time</th>
                            <th>Exit Time</th>
                            <th>Status</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_transactions->num_rows > 0): ?>
                            <?php while ($row = $recent_transactions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['transaction_id']; ?></td>
                                    <td><?php echo $row['vehicle_number']; ?></td>
                                    <td><?php echo $row['vehicle_type']; ?></td>
                                    <td><?php echo $row['username']; ?></td>
                                    <td><?php echo date('d M Y, h:i A', strtotime($row['entry_time'])); ?></td>
                                    <td><?php echo $row['exit_time'] ? date('d M Y, h:i A', strtotime($row['exit_time'])) : '-'; ?></td>
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
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No transactions found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Parking Availability Chart
    const parkingCtx = document.getElementById('parking-availability-chart').getContext('2d');
    
    const parkingData = {
        labels: [
            <?php 
            $areas = [];
            $conn->data_seek($parking_areas, 0);
            while ($row = $parking_areas->fetch_assoc()) {
                echo "'".$row['area_name']."',";
                $areas[] = $row;
            }
            ?>
        ],
        datasets: [
            {
                label: '2-Wheeler Available',
                data: [
                    <?php 
                    foreach ($areas as $area) {
                        echo ($area['total_2_wheeler_slots'] - $area['occupied_2_wheeler_slots']).",";
                    }
                    ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            },
            {
                label: '4-Wheeler Available',
                data: [
                    <?php 
                    foreach ($areas as $area) {
                        echo ($area['total_4_wheeler_slots'] - $area['occupied_4_wheeler_slots']).",";
                    }
                    ?>
                ],
                backgroundColor: 'rgba(255, 99, 132, 0.5)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            },
            {
                label: 'Commercial Available',
                data: [
                    <?php 
                    foreach ($areas as $area) {
                        echo ($area['total_commercial_slots'] - $area['occupied_commercial_slots']).",";
                    }
                    ?>
                ],
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }
        ]
    };
    
    new Chart(parkingCtx, {
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
    
    // Revenue Chart
    const revenueCtx = document.getElementById('revenue-chart').getContext('2d');
    
    const revenueData = {
        labels: [<?php echo "'".implode("','", $months)."'"; ?>],
        datasets: [{
            label: 'Monthly Revenue (₹)',
            data: [<?php echo implode(",", $revenues); ?>],
            backgroundColor: 'rgba(153, 102, 255, 0.5)',
            borderColor: 'rgba(153, 102, 255, 1)',
            borderWidth: 1
        }]
    };
    
    new Chart(revenueCtx, {
        type: 'line',
        data: revenueData,
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
