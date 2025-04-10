<?php
require_once '../config/db_connect.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['error_message'] = "You must be logged in as an administrator to view this page.";
    redirect('../auth/login.php');
}

// Handle form submission to add/update parking area
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // If updating existing area
    if (isset($_POST['area_id']) && !empty($_POST['area_id'])) {
        $area_id = $_POST['area_id'];
        $area_name = sanitize_input($conn, $_POST['area_name']);
        $total_2_wheeler = sanitize_input($conn, $_POST['total_2_wheeler']);
        $total_4_wheeler = sanitize_input($conn, $_POST['total_4_wheeler']);
        $total_commercial = sanitize_input($conn, $_POST['total_commercial']);
        
        // Update area
        $query = "UPDATE parking_areas SET 
                 area_name = ?,
                 total_2_wheeler_slots = ?,
                 total_4_wheeler_slots = ?,
                 total_commercial_slots = ?
                 WHERE area_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("siiii", $area_name, $total_2_wheeler, $total_4_wheeler, $total_commercial, $area_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Parking area updated successfully.";
        } else {
            $_SESSION['error_message'] = "Error updating parking area: " . $conn->error;
        }
    } 
    // If adding new area
    else {
        $area_name = sanitize_input($conn, $_POST['area_name']);
        $total_2_wheeler = sanitize_input($conn, $_POST['total_2_wheeler']);
        $total_4_wheeler = sanitize_input($conn, $_POST['total_4_wheeler']);
        $total_commercial = sanitize_input($conn, $_POST['total_commercial']);
        
        // Insert new area
        $query = "INSERT INTO parking_areas (area_name, total_2_wheeler_slots, total_4_wheeler_slots, total_commercial_slots) 
                 VALUES (?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("siii", $area_name, $total_2_wheeler, $total_4_wheeler, $total_commercial);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "New parking area added successfully.";
        } else {
            $_SESSION['error_message'] = "Error adding parking area: " . $conn->error;
        }
    }
    
    redirect('manage_parking.php');
}

// Handle parking area deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $area_id = $_GET['delete'];
    
    // Check if area has active parking
    $check_query = "SELECT COUNT(*) as active FROM parking_transactions pt
                    JOIN vehicles v ON pt.vehicle_id = v.vehicle_id
                    WHERE pt.area_id = ? AND pt.status = 'in'";
    
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $area_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $active = $result->fetch_assoc()['active'];
    
    if ($active > 0) {
        $_SESSION['error_message'] = "Cannot delete this area because it has active parking transactions.";
    } else {
        // Delete the parking area
        $delete_query = "DELETE FROM parking_areas WHERE area_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $area_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Parking area deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting parking area: " . $conn->error;
        }
    }
    
    redirect('manage_parking.php');
}

// Handle parking rate updates
if (isset($_POST['update_rates'])) {
    $vehicle_types = ['2-wheeler', '4-wheeler', 'commercial'];
    $rate_types = ['hourly_rate', 'daily_rate', 'weekly_rate', 'monthly_rate'];
    
    foreach ($vehicle_types as $type) {
        $rate_id = $_POST['rate_id_'.$type];
        
        $update_query = "UPDATE parking_rates SET ";
        $params = [];
        $param_types = "";
        
        foreach ($rate_types as $rate_type) {
            $value = sanitize_input($conn, $_POST[$rate_type.'_'.$type]);
            $update_query .= "$rate_type = ?, ";
            $params[] = $value;
            $param_types .= "d"; // d for double/decimal
        }
        
        // Remove the trailing comma and space
        $update_query = rtrim($update_query, ", ");
        
        $update_query .= " WHERE rate_id = ?";
        $params[] = $rate_id;
        $param_types .= "i"; // i for integer
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param($param_types, ...$params);
        
        if (!$stmt->execute()) {
            $_SESSION['error_message'] = "Error updating parking rates: " . $conn->error;
            redirect('manage_parking.php');
            exit;
        }
    }
    
    $_SESSION['success_message'] = "Parking rates updated successfully.";
    redirect('manage_parking.php');
}

// Get all parking areas
$query = "SELECT * FROM parking_areas ORDER BY area_name";
$parking_areas = $conn->query($query);

// Get current parking rates
$query = "SELECT * FROM parking_rates";
$parking_rates = $conn->query($query);
$rates = [];

while ($row = $parking_rates->fetch_assoc()) {
    $rates[$row['vehicle_type']] = $row;
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-4">
    <h2>Manage Parking</h2>
    
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
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Parking Areas</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAreaModal">
                        <i class="fas fa-plus"></i> Add New Area
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Area Name</th>
                                    <th>2-Wheeler Slots</th>
                                    <th>4-Wheeler Slots</th>
                                    <th>Commercial Slots</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($parking_areas->num_rows > 0): ?>
                                    <?php while ($row = $parking_areas->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['area_id']; ?></td>
                                            <td><?php echo htmlspecialchars($row['area_name']); ?></td>
                                            <td>
                                                <?php echo $row['occupied_2_wheeler_slots']; ?> / <?php echo $row['total_2_wheeler_slots']; ?>
                                            </td>
                                            <td>
                                                <?php echo $row['occupied_4_wheeler_slots']; ?> / <?php echo $row['total_4_wheeler_slots']; ?>
                                            </td>
                                            <td>
                                                <?php echo $row['occupied_commercial_slots']; ?> / <?php echo $row['total_commercial_slots']; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info edit-area-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editAreaModal"
                                                        data-id="<?php echo $row['area_id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($row['area_name']); ?>"
                                                        data-2wheeler="<?php echo $row['total_2_wheeler_slots']; ?>"
                                                        data-4wheeler="<?php echo $row['total_4_wheeler_slots']; ?>"
                                                        data-commercial="<?php echo $row['total_commercial_slots']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?delete=<?php echo $row['area_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this parking area?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No parking areas found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Parking Rates</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="manage_parking.php">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Vehicle Type</th>
                                        <th>Hourly Rate (₹)</th>
                                        <th>Daily Rate (₹)</th>
                                        <th>Weekly Rate (₹)</th>
                                        <th>Monthly Rate (₹)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (['2-wheeler', '4-wheeler', 'commercial'] as $type): ?>
                                        <tr>
                                            <td><?php echo ucfirst($type); ?></td>
                                            <td>
                                                <input type="hidden" name="rate_id_<?php echo $type; ?>" value="<?php echo $rates[$type]['rate_id']; ?>">
                                                <input type="number" name="hourly_rate_<?php echo $type; ?>" class="form-control form-control-sm" value="<?php echo $rates[$type]['hourly_rate']; ?>" step="0.01" min="0" required>
                                            </td>
                                            <td>
                                                <input type="number" name="daily_rate_<?php echo $type; ?>" class="form-control form-control-sm" value="<?php echo $rates[$type]['daily_rate']; ?>" step="0.01" min="0" required>
                                            </td>
                                            <td>
                                                <input type="number" name="weekly_rate_<?php echo $type; ?>" class="form-control form-control-sm" value="<?php echo $rates[$type]['weekly_rate']; ?>" step="0.01" min="0" required>
                                            </td>
                                            <td>
                                                <input type="number" name="monthly_rate_<?php echo $type; ?>" class="form-control form-control-sm" value="<?php echo $rates[$type]['monthly_rate']; ?>" step="0.01" min="0" required>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" name="update_rates" class="btn btn-primary">Update Rates</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5>Current Parking Status</h5>
                </div>
                <div class="card-body">
                    <canvas id="parking-status-chart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Parking Area Modal -->
<div class="modal fade" id="addAreaModal" tabindex="-1" aria-labelledby="addAreaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAreaModalLabel">Add New Parking Area</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="manage_parking.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="area_name" class="form-label">Area Name</label>
                        <input type="text" class="form-control" id="area_name" name="area_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="total_2_wheeler" class="form-label">Total 2-Wheeler Slots</label>
                        <input type="number" class="form-control" id="total_2_wheeler" name="total_2_wheeler" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="total_4_wheeler" class="form-label">Total 4-Wheeler Slots</label>
                        <input type="number" class="form-control" id="total_4_wheeler" name="total_4_wheeler" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="total_commercial" class="form-label">Total Commercial Slots</label>
                        <input type="number" class="form-control" id="total_commercial" name="total_commercial" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Area</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Parking Area Modal -->
<div class="modal fade" id="editAreaModal" tabindex="-1" aria-labelledby="editAreaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAreaModalLabel">Edit Parking Area</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="manage_parking.php">
                <div class="modal-body">
                    <input type="hidden" id="edit_area_id" name="area_id">
                    <div class="mb-3">
                        <label for="edit_area_name" class="form-label">Area Name</label>
                        <input type="text" class="form-control" id="edit_area_name" name="area_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_total_2_wheeler" class="form-label">Total 2-Wheeler Slots</label>
                        <input type="number" class="form-control" id="edit_total_2_wheeler" name="total_2_wheeler" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_total_4_wheeler" class="form-label">Total 4-Wheeler Slots</label>
                        <input type="number" class="form-control" id="edit_total_4_wheeler" name="total_4_wheeler" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_total_commercial" class="form-label">Total Commercial Slots</label>
                        <input type="number" class="form-control" id="edit_total_commercial" name="total_commercial" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Area</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set up edit modal data
    const editButtons = document.querySelectorAll('.edit-area-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const twoWheeler = this.getAttribute('data-2wheeler');
            const fourWheeler = this.getAttribute('data-4wheeler');
            const commercial = this.getAttribute('data-commercial');
            
            document.getElementById('edit_area_id').value = id;
            document.getElementById('edit_area_name').value = name;
            document.getElementById('edit_total_2_wheeler').value = twoWheeler;
            document.getElementById('edit_total_4_wheeler').value = fourWheeler;
            document.getElementById('edit_total_commercial').value = commercial;
        });
    });
    
    // Parking Status Chart
    const ctx = document.getElementById('parking-status-chart').getContext('2d');
    
    // Get data from PHP
    const parkingData = {
        labels: ["2-Wheeler", "4-Wheeler", "Commercial"],
        datasets: [
            {
                label: 'Occupied',
                data: [
                    <?php 
                    $total_occupied_2 = 0;
                    $total_occupied_4 = 0;
                    $total_occupied_c = 0;
                    $total_all_2 = 0;
                    $total_all_4 = 0;
                    $total_all_c = 0;
                    
                    $conn->data_seek($parking_areas, 0);
                    while ($row = $parking_areas->fetch_assoc()) {
                        $total_occupied_2 += $row['occupied_2_wheeler_slots'];
                        $total_occupied_4 += $row['occupied_4_wheeler_slots'];
                        $total_occupied_c += $row['occupied_commercial_slots'];
                        $total_all_2 += $row['total_2_wheeler_slots'];
                        $total_all_4 += $row['total_4_wheeler_slots'];
                        $total_all_c += $row['total_commercial_slots'];
                    }
                    
                    echo "$total_occupied_2, $total_occupied_4, $total_occupied_c";
                    ?>
                ],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(75, 192, 192, 0.5)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(75, 192, 192, 1)'
                ],
                borderWidth: 1
            },
            {
                label: 'Available',
                data: [
                    <?php 
                    echo ($total_all_2 - $total_occupied_2) . ", " . 
                         ($total_all_4 - $total_occupied_4) . ", " .
                         ($total_all_c - $total_occupied_c);
                    ?>
                ],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(75, 192, 192, 0.2)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(75, 192, 192, 1)'
                ],
                borderWidth: 1
            }
        ]
    };
    
    new Chart(ctx, {
        type: 'bar',
        data: parkingData,
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    stacked: true
                },
                x: {
                    stacked: true
                }
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
