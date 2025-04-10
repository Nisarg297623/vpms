<?php
require_once '../config/db_connect.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['error_message'] = "You must be logged in as an administrator to view this page.";
    redirect('../auth/login.php');
}

// Handle user deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    // Don't allow deleting admin accounts
    $check_query = "SELECT user_type FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && $user['user_type'] == 'admin') {
        $_SESSION['error_message'] = "Cannot delete administrator accounts.";
    } else {
        // Delete the user
        $delete_query = "DELETE FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "User deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting user: " . $conn->error;
        }
    }
    
    redirect('manage_users.php');
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
    $search_condition = " WHERE (username LIKE ? OR full_name LIKE ? OR email LIKE ? OR contact_no LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param];
    $param_types = "ssss";
}

// Get total number of users for pagination
$count_query = "SELECT COUNT(*) as total FROM users" . $search_condition;

if (!empty($params)) {
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $count_result = $stmt->get_result();
} else {
    $count_result = $conn->query($count_query);
}

$row = $count_result->fetch_assoc();
$total_users = $row['total'];
$total_pages = ceil($total_users / $records_per_page);

// Get users with pagination
$query = "SELECT user_id, username, full_name, email, contact_no, user_type, registration_date, last_login 
          FROM users" . $search_condition . " 
          ORDER BY registration_date DESC 
          LIMIT ?, ?";

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
    <h2>Manage Users</h2>
    
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
                    <h5 class="mb-0">Users List</h5>
                </div>
                <div class="col-md-6">
                    <form class="d-flex" method="GET" action="manage_users.php">
                        <input type="text" name="search" class="form-control me-2" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>User Type</th>
                            <th>Registered On</th>
                            <th>Last Login</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['user_id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['contact_no']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $row['user_type'] == 'admin' ? 'bg-danger' : 'bg-info'; ?>">
                                            <?php echo ucfirst($row['user_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($row['registration_date'])); ?></td>
                                    <td><?php echo $row['last_login'] ? date('d M Y, h:i A', strtotime($row['last_login'])) : 'Never'; ?></td>
                                    <td>
                                        <?php if ($row['user_type'] != 'admin'): ?>
                                            <a href="?delete=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
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
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
