<?php
require_once '../config/db_connect.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "You must be logged in to access this page.";
    redirect('../auth/login.php');
    exit;
}

// Get user info and verify user exists
$user_id = $_SESSION['user_id'];
$check_user = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
$check_user->bind_param("i", $user_id);
$check_user->execute();
$result = $check_user->get_result();

if (!$result->fetch_assoc()) {
    $_SESSION['error_message'] = "Invalid user session. Please login again.";
    redirect('../auth/logout.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $vehicle_id = null;
        $vehicle_type = null;

        // Start transaction
        if ($conn->begin_transaction()) {
            
            if ($_POST['vehicle_choice'] === 'existing') {
                if (!isset($_POST['vehicle_id'])) {
                    throw new Exception("No vehicle selected");
                }
                $vehicle_id = $_POST['vehicle_id'];

                // Get vehicle type
                $stmt = $conn->prepare("SELECT vehicle_type FROM vehicles WHERE vehicle_id = ? AND user_id = ?");
                $stmt->bind_param("si", $vehicle_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $vehicle = $result->fetch_assoc();
                if (!$vehicle) {
                    throw new Exception("Invalid vehicle selected");
                }
                $vehicle_type = $vehicle['vehicle_type'];
            } else {
                // Register new vehicle
                if (!isset($_POST['vehicle_type'], $_POST['vehicle_number'])) {
                    throw new Exception("Missing required vehicle information");
                }

                $vehicle_type = sanitize_input($conn, $_POST['vehicle_type']);
                $vehicle_number = sanitize_input($conn, $_POST['vehicle_number']);
                $vehicle_make = sanitize_input($conn, $_POST['vehicle_make'] ?? '');
                $vehicle_model = sanitize_input($conn, $_POST['vehicle_model'] ?? '');
                $vehicle_color = sanitize_input($conn, $_POST['vehicle_color'] ?? '');

                // Check if vehicle number already exists
                $check_stmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_number = ?");
                $check_stmt->bind_param("s", $vehicle_number);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                if ($result->fetch_assoc()) {
                    throw new Exception("Vehicle with this number already exists");
                }

                $vehicle_id = generateVehicleID($conn);
                $stmt = $conn->prepare("INSERT INTO vehicles (vehicle_id, user_id, vehicle_type, vehicle_number, vehicle_make, vehicle_model, vehicle_color) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sisssss", $vehicle_id, $user_id, $vehicle_type, $vehicle_number, $vehicle_make, $vehicle_model, $vehicle_color);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to register vehicle");
                }
            }

            // Create parking transaction
            $area_id = $_POST['area_id'];
            $transaction_id = uniqid('TRX');

            $stmt = $conn->prepare("INSERT INTO parking_transactions (transaction_id, vehicle_id, area_id, entry_time, status) VALUES (?, ?, ?, NOW(), 'in')");
            $stmt->bind_param("ssi", $transaction_id, $vehicle_id, $area_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to create parking transaction");
            }

            // Update parking area occupancy
            $column_occupied = "";
            switch ($vehicle_type) {
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

            $update_query = "UPDATE parking_areas SET $column_occupied = $column_occupied + 1 WHERE area_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("i", $area_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update parking area occupancy");
            }

            // Commit transaction
            $conn->commit();
            $_SESSION['success_message'] = "Vehicle parked successfully!";
            redirect('view_status.php');

        } else {
            throw new Exception("Could not start transaction");
        }

    } catch (Exception $e) {
        if ($conn->connect_errno) {
            $conn->rollback();
        }
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Get user's vehicles
$query = "SELECT * FROM vehicles WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$vehicles = [];
while ($row = $result->fetch_assoc()) {
    $vehicles[] = $row;
}

// Get available parking areas
$query = "SELECT * FROM parking_areas";
$result = $conn->query($query);
$parking_areas = [];
while ($row = $result->fetch_assoc()) {
    $parking_areas[] = $row;
}

// Get parking rates
$query = "SELECT * FROM parking_rates";
$result = $conn->query($query);
$rates = [];
while ($row = $result->fetch_assoc()) {
    $rates[$row['vehicle_type']] = $row;
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-4">
    <h2>Vehicle Entry</h2>

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
                    <h5>Park a Vehicle</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="entry.php" id="entryForm">
                        <div class="mb-3">
                            <label class="form-label">Choose Vehicle</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="vehicle_choice" id="existingVehicle" value="existing" <?php echo (count($vehicles) > 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="existingVehicle">
                                    Use existing vehicle
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="vehicle_choice" id="newVehicle" value="new" <?php echo (count($vehicles) == 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="newVehicle">
                                    Register a new vehicle
                                </label>
                            </div>
                        </div>

                        <!-- Existing Vehicle Selection -->
                        <div id="existingVehicleSection" style="display: <?php echo (count($vehicles) > 0) ? 'block' : 'none'; ?>;">
                            <div class="mb-3">
                                <label for="vehicle_id" class="form-label">Select Your Vehicle</label>
                                <select class="form-select" id="vehicle_id" name="vehicle_id">
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <option value="<?php echo $vehicle['vehicle_id']; ?>" data-type="<?php echo $vehicle['vehicle_type']; ?>">
                                            <?php echo $vehicle['vehicle_number']; ?> (<?php echo ucfirst($vehicle['vehicle_type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- New Vehicle Registration -->
                        <div id="newVehicleSection" style="display: <?php echo (count($vehicles) == 0) ? 'block' : 'none'; ?>;">
                            <div class="mb-3">
                                <label for="vehicle_type" class="form-label">Vehicle Type</label>
                                <select class="form-select" id="vehicle_type" name="vehicle_type" required>
                                    <option value="" disabled selected>Select vehicle type</option>
                                    <option value="2-wheeler">2-Wheeler</option>
                                    <option value="4-wheeler">4-Wheeler</option>
                                    <option value="commercial">Commercial</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="vehicle_number" class="form-label">Vehicle Number</label>
                                <input type="text" class="form-control" id="vehicle_number" name="vehicle_number" placeholder="e.g., MH01AB1234" required>
                            </div>

                            <div class="mb-3">
                                <label for="vehicle_make" class="form-label">Make</label>
                                <input type="text" class="form-control" id="vehicle_make" name="vehicle_make" placeholder="e.g., Honda, Toyota">
                            </div>

                            <div class="mb-3">
                                <label for="vehicle_model" class="form-label">Model</label>
                                <input type="text" class="form-control" id="vehicle_model" name="vehicle_model" placeholder="e.g., Civic, Corolla">
                            </div>

                            <div class="mb-3">
                                <label for="vehicle_color" class="form-label">Color</label>
                                <input type="text" class="form-control" id="vehicle_color" name="vehicle_color" placeholder="e.g., Red, Blue">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="area_id" class="form-label">Parking Area</label>
                            <select class="form-select" id="area_id" name="area_id" required>
                                <option value="" disabled selected>Select parking area</option>
                                <?php foreach ($parking_areas as $area): ?>
                                    <option value="<?php echo $area['area_id']; ?>" 
                                            data-2wheeler="<?php echo $area['total_2_wheeler_slots'] - $area['occupied_2_wheeler_slots']; ?>"
                                            data-4wheeler="<?php echo $area['total_4_wheeler_slots'] - $area['occupied_4_wheeler_slots']; ?>"
                                            data-commercial="<?php echo $area['total_commercial_slots'] - $area['occupied_commercial_slots']; ?>">
                                        <?php echo $area['area_name']; ?> 
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary" id="submitBtn">Park Vehicle</button>
                            <a href="../index.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Parking Rates</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Vehicle Type</th>
                                    <th>Hourly</th>
                                    <th>Daily</th>
                                    <th>Weekly</th>
                                    <th>Monthly</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rates as $type => $rate): ?>
                                    <tr>
                                        <td><?php echo ucfirst($type); ?></td>
                                        <td>₹<?php echo $rate['hourly_rate']; ?></td>
                                        <td>₹<?php echo $rate['daily_rate']; ?></td>
                                        <td>₹<?php echo $rate['weekly_rate']; ?></td>
                                        <td>₹<?php echo $rate['monthly_rate']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const existingVehicleRadio = document.getElementById('existingVehicle');
    const newVehicleRadio = document.getElementById('newVehicle');
    const existingVehicleSection = document.getElementById('existingVehicleSection');
    const newVehicleSection = document.getElementById('newVehicleSection');
    const vehicleIdSelect = document.getElementById('vehicle_id');
    const vehicleTypeSelect = document.getElementById('vehicle_type');
    const areaSelect = document.getElementById('area_id');
    const submitBtn = document.getElementById('submitBtn');
    const entryForm = document.getElementById('entryForm');

    // Radio button event listeners
    existingVehicleRadio.addEventListener('change', function() {
        if (this.checked) {
            existingVehicleSection.style.display = 'block';
            newVehicleSection.style.display = 'none';
        }
    });

    newVehicleRadio.addEventListener('change', function() {
        if (this.checked) {
            existingVehicleSection.style.display = 'none';
            newVehicleSection.style.display = 'block';
        }
    });

    // Form validation
    entryForm.addEventListener('submit', function(e) {
        let valid = true;

        if (existingVehicleRadio.checked) {
            if (!vehicleIdSelect || !vehicleIdSelect.value) {
                alert('Please select a vehicle');
                valid = false;
            }
        } else {
            if (!vehicleTypeSelect || !vehicleTypeSelect.value) {
                alert('Please select a vehicle type');
                valid = false;
            }

            const vehicleNumber = document.getElementById('vehicle_number');
            if (!vehicleNumber || !vehicleNumber.value.trim()) {
                alert('Please enter a vehicle number');
                valid = false;
            }
        }

        if (!areaSelect || !areaSelect.value) {
            alert('Please select a parking area');
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>