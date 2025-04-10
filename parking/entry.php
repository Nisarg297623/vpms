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
        if ($_POST['vehicle_choice'] === 'existing') {
            if (!isset($_POST['vehicle_id']) || empty($_POST['vehicle_id'])) {
                throw new Exception("No vehicle selected");
            }

            $vehicle_id = $_POST['vehicle_id'];

            // Fetch vehicle type
            $stmt = $conn->prepare("SELECT vehicle_type FROM vehicles WHERE vehicle_id = ? AND user_id = ?");
            $stmt->bind_param("si", $vehicle_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $vehicle = $result->fetch_assoc();

            if (!$vehicle) {
                throw new Exception("Invalid vehicle selected");
            }

            $vehicle_type = $vehicle['vehicle_type'];

            // Proceed with parking transaction
            $transaction_id = uniqid('TRX');
            $stmt = $conn->prepare("INSERT INTO parking_transactions (transaction_id, vehicle_id, area_id, entry_time, status) VALUES (?, ?, ?, NOW(), 'in')");
            $stmt->bind_param("ssi", $transaction_id, $vehicle_id, $_POST['area_id']);
            if (!$stmt->execute()) {
                throw new Exception("Failed to create parking transaction");
            }

            // Update parking area occupancy
            $column_occupied = match ($vehicle_type) {
                '2-wheeler' => 'occupied_2_wheeler_slots',
                '4-wheeler' => 'occupied_4_wheeler_slots',
                'commercial' => 'occupied_commercial_slots',
            };

            $update_query = "UPDATE parking_areas SET $column_occupied = $column_occupied + 1 WHERE area_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("i", $_POST['area_id']);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update parking area occupancy");
            }

            $conn->commit();
            $_SESSION['success_message'] = "Vehicle parked successfully!";
            redirect('view_status.php');
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Get user's vehicles with more details
$query = "SELECT v.*, 
          (SELECT COUNT(*) FROM parking_transactions 
           WHERE vehicle_id = v.vehicle_id AND status = 'in') as is_parked 
          FROM vehicles v 
          WHERE v.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$vehicles = [];
while ($row = $result->fetch_assoc()) {
    if ($row['is_parked'] == 0) { // Only add vehicles that are not currently parked
        $vehicles[] = $row;
    }
}

// Get available parking areas
$query = "SELECT * FROM parking_areas";
$result = $conn->query($query);
$parking_areas = [];
while ($row = $result->fetch_assoc()) {
    $parking_areas[] = $row;
}

// Get parking rates
$rates_query = "SELECT * FROM parking_rates";
$rates_result = $conn->query($rates_query);
$rates = [];
while ($rate = $rates_result->fetch_assoc()) {
    $rates[$rate['vehicle_type']] = $rate;
}

// Format rates for JavaScript
$formatted_rates = [];
foreach ($rates as $type => $rate) {
    $formatted_rates[$type] = [
        'hourly' => floatval($rate['hourly_rate']),
        'daily' => floatval($rate['daily_rate']),
        'weekly' => floatval($rate['weekly_rate']),
        'monthly' => floatval($rate['monthly_rate'])
    ];
}

// Add this before the HTML form
echo "<script>const parkingRates = " . json_encode($formatted_rates) . ";</script>";

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
                            <label for="vehicle_id" class="form-label">Select Your Vehicle</label>
                            <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                                <option value="" disabled selected>Select a vehicle</option>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <option value="<?php echo $vehicle['vehicle_id']; ?>" data-type="<?php echo $vehicle['vehicle_type']; ?>">
                                        <?php echo $vehicle['vehicle_number']; ?> (<?php echo ucfirst($vehicle['vehicle_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="area_id" class="form-label">Parking Area</label>
                            <select class="form-select" id="area_id" name="area_id" required>
                                <option value="" disabled selected>Select parking area</option>
                                <?php foreach ($parking_areas as $area): ?>
                                    <option value="<?php echo $area['area_id']; ?>">
                                        <?php echo $area['area_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Park Vehicle</button>
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
    const entryForm = document.getElementById('entryForm');

    // Toggle sections based on radio selection
    existingVehicleRadio.addEventListener('change', function() {
        existingVehicleSection.style.display = this.checked ? 'block' : 'none';
        newVehicleSection.style.display = this.checked ? 'none' : 'block';
        
        if (this.checked) {
            // Reset new vehicle form fields
            if (vehicleTypeSelect) vehicleTypeSelect.value = '';
            const newVehicleInputs = newVehicleSection.querySelectorAll('input[type="text"]');
            newVehicleInputs.forEach(input => input.value = '');
        }
    });

    newVehicleRadio.addEventListener('change', function() {
        newVehicleSection.style.display = this.checked ? 'block' : 'none';
        existingVehicleSection.style.display = this.checked ? 'none' : 'block';
        
        if (this.checked && vehicleIdSelect) {
            vehicleIdSelect.value = '';
        }
    });

    // Form validation
    entryForm.addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent default submission
        
        let isValid = true;
        let errorMessage = '';

        // Validate vehicle selection
        if (existingVehicleRadio.checked) {
            if (!vehicleIdSelect || !vehicleIdSelect.value) {
                errorMessage = 'Please select a vehicle';
                isValid = false;
            }
        } else if (newVehicleRadio.checked) {
            if (!vehicleTypeSelect || !vehicleTypeSelect.value) {
                errorMessage = 'Please select a vehicle type';
                isValid = false;
            }
            const vehicleNumber = document.getElementById('vehicle_number');
            if (!vehicleNumber || !vehicleNumber.value.trim()) {
                errorMessage = 'Please enter a vehicle number';
                isValid = false;
            }
        }

        // Validate parking area
        if (!areaSelect || !areaSelect.value) {
            errorMessage = 'Please select a parking area';
            isValid = false;
        }

        if (!isValid) {
            alert(errorMessage);
            return;
        }

        // If all validations pass, submit the form
        this.submit();
    });

    // Initialize the form state
    if (existingVehicleRadio.checked) {
        existingVehicleSection.style.display = 'block';
        newVehicleSection.style.display = 'none';
    } else {
        existingVehicleSection.style.display = 'none';
        newVehicleSection.style.display = 'block';
    }
});
</script>

<?php include '../includes/footer.php'; ?>