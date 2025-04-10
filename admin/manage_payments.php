<?php
require_once '../config/db_connect.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['error_message'] = "You must be logged in as an administrator to view this page.";
    redirect('../auth/login.php');
}

// Handle updating payment status
if (isset($_GET['update_status']) && !empty($_GET['update_status']) && isset($_GET['status'])) {
    $payment_id = $_GET['update_status'];
    $new_status = $_GET['status'];
    
    if (in_array($new_status, ['pending', 'completed', 'failed'])) {
        $update_query = "UPDATE payments SET payment_status = ? WHERE payment_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ss", $new_status, $payment_id);
        
        if ($stmt->execute()) {
            // If payment is marked as completed, also update the related transaction
            if ($new_status == 'completed') {
                $query = "UPDATE parking_transactions SET bill_status = 'paid' 
                         WHERE transaction_id = (SELECT transaction_id FROM payments WHERE payment_id = ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $payment_id);
                $stmt->execute();
            }
            
            $_SESSION['success_message'] = "Payment status updated successfully.";
        } else {
            $_SESSION['error_message'] = "Error updating payment status: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = "Invalid payment status.";
    }
    
    redirect('manage_payments.php');
}

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = '';
$params = [];
$param_types = '';

if (!empty($search)) {
    $search_condition = " WHERE (p.payment_id LIKE ? OR p.transaction_id LIKE ? OR v.vehicle_number LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
    $param_types = "sss";
}

// Filter by payment status
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
if (!empty($status_filter)) {
    if (empty($search_condition)) {
        $search_condition = " WHERE p.payment_status = ?";
    } else {
        $search_condition .= " AND p.payment_status = ?";
    }
    $params[] = $status_filter;
    $param_types .= "s";
}

// Get total number of payments for pagination
$count_query = "SELECT COUNT(*) as total FROM payments p";
if (!empty($search_condition)) {
    $count_query .= " LEFT JOIN parking_transactions pt ON p.transaction_id = pt.transaction_id 
                      LEFT JOIN vehicles v ON pt.vehicle_id = v.vehicle_id" . $search_condition;
}

if (!empty($params)) {
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $count_result = $stmt->get_result();
} else {
    $count_result = $conn->query($count_query);
}

$row = $count_result->fetch_assoc();
$total_payments = $row['total'];
$total_pages = ceil($total_payments / $records_per_page);

// Get payments with pagination
$query = "SELECT p.*, pt.vehicle_id, v.vehicle_number, v.vehicle_type, u.username, u.full_name
          FROM payments p
          LEFT JOIN parking_transactions pt ON p.transaction_id = pt.transaction_id
          LEFT JOIN vehicles v ON pt.vehicle_id = v.vehicle_id
          LEFT JOIN users u ON v.user_id = u.user_id";

if (!empty($search_condition)) {
    $query .= $search_condition;
}

$query .= " ORDER BY p.payment_date DESC LIMIT ?, ?";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $params[] = $offset;
    $params[] = $records_per_page;
    $param_types .= "ii";
    $stmt->bind_param($param_types, ...$params);
} else {
    $stmt->bind_param("ii", $offset, $records_per_page);
}

$stmt->execute();
$result = $stmt->get_result();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-4">
    <h2>Manage Payments</h2>
    
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
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0">Payment Records</h5>
                </div>
                <div class="col-md-6">
                    <form class="d-flex" method="GET" action="manage_payments.php">
                        <input type="text" name="search" class="form-control me-2" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                        <select name="status" class="form-select me-2" style="width: auto;">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['payment_id']; ?></td>
                                    <td><?php echo $row['transaction_id']; ?></td>
                                    <td>
                                        <?php if ($row['vehicle_number']): ?>
                                            <?php echo $row['vehicle_number']; ?> 
                                            <span class="badge bg-secondary"><?php echo $row['vehicle_type']; ?></span>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $row['full_name'] ?? 'N/A'; ?></td>
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
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                                Action
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                <?php if ($row['payment_status'] != 'completed'): ?>
                                                    <li><a class="dropdown-item" href="?update_status=<?php echo $row['payment_id']; ?>&status=completed">Mark as Completed</a></li>
                                                <?php endif; ?>
                                                <?php if ($row['payment_status'] != 'pending'): ?>
                                                    <li><a class="dropdown-item" href="?update_status=<?php echo $row['payment_id']; ?>&status=pending">Mark as Pending</a></li>
                                                <?php endif; ?>
                                                <?php if ($row['payment_status'] != 'failed'): ?>
                                                    <li><a class="dropdown-item" href="?update_status=<?php echo $row['payment_id']; ?>&status=failed">Mark as Failed</a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">No payment records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status='.urlencode($status_filter) : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status='.urlencode($status_filter) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status='.urlencode($status_filter) : ''; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Payment Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <?php
                                // Get payment statistics
                                $stats_query = "SELECT 
                                    SUM(amount) as total,
                                    COUNT(*) as count,
                                    SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as completed_amount,
                                    COUNT(CASE WHEN payment_status = 'completed' THEN 1 END) as completed_count,
                                    SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                                    COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count,
                                    SUM(CASE WHEN payment_status = 'failed' THEN amount ELSE 0 END) as failed_amount,
                                    COUNT(CASE WHEN payment_status = 'failed' THEN 1 END) as failed_count,
                                    SUM(CASE WHEN payment_type = 'cash' THEN amount ELSE 0 END) as cash_amount,
                                    COUNT(CASE WHEN payment_type = 'cash' THEN 1 END) as cash_count,
                                    SUM(CASE WHEN payment_type = 'online' THEN amount ELSE 0 END) as online_amount,
                                    COUNT(CASE WHEN payment_type = 'online' THEN 1 END) as online_count
                                FROM payments";
                                $stats_result = $conn->query($stats_query);
                                $stats = $stats_result->fetch_assoc();
                                ?>
                                <tr>
                                    <th>Total Payments</th>
                                    <td><?php echo $stats['count']; ?> (₹<?php echo number_format($stats['total'] ?? 0, 2); ?>)</td>
                                </tr>
                                <tr>
                                    <th>Completed Payments</th>
                                    <td><?php echo $stats['completed_count']; ?> (₹<?php echo number_format($stats['completed_amount'] ?? 0, 2); ?>)</td>
                                </tr>
                                <tr>
                                    <th>Pending Payments</th>
                                    <td><?php echo $stats['pending_count']; ?> (₹<?php echo number_format($stats['pending_amount'] ?? 0, 2); ?>)</td>
                                </tr>
                                <tr>
                                    <th>Failed Payments</th>
                                    <td><?php echo $stats['failed_count']; ?> (₹<?php echo number_format($stats['failed_amount'] ?? 0, 2); ?>)</td>
                                </tr>
                                <tr>
                                    <th>Cash Payments</th>
                                    <td><?php echo $stats['cash_count']; ?> (₹<?php echo number_format($stats['cash_amount'] ?? 0, 2); ?>)</td>
                                </tr>
                                <tr>
                                    <th>Online Payments</th>
                                    <td><?php echo $stats['online_count']; ?> (₹<?php echo number_format($stats['online_amount'] ?? 0, 2); ?>)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Payment Method Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="payment-method-chart" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Payment Method Chart
    const ctx = document.getElementById('payment-method-chart').getContext('2d');
    
    const data = {
        labels: ['Cash', 'Online'],
        datasets: [{
            label: 'Payment Methods',
            data: [
                <?php echo $stats['cash_count']; ?>, 
                <?php echo $stats['online_count']; ?>
            ],
            backgroundColor: [
                'rgba(255, 99, 132, 0.5)',
                'rgba(54, 162, 235, 0.5)'
            ],
            borderColor: [
                'rgba(255, 99, 132, 1)',
                'rgba(54, 162, 235, 1)'
            ],
            borderWidth: 1
        }]
    };
    
    new Chart(ctx, {
        type: 'doughnut',
        data: data,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
