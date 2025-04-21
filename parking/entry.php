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
        if (!isset($_POST['vehicle_choice'])) {
            throw new Exception("Please select whether to use existing vehicle or register new one");
        }

        // Start transaction
        $conn->begin_transaction();

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

        } else if ($_POST['vehicle_choice'] === 'new') {
            if (!isset($_POST['vehicle_type']) || empty($_POST['vehicle_type']) || 
                !isset($_POST['vehicle_number']) || empty($_POST['vehicle_number'])) {
                throw new Exception("Vehicle type and number are required");
            }

            // Create new vehicle
            $vehicle_id = uniqid('VEH');
            $vehicle_type = $_POST['vehicle_type'];
            $vehicle_number = $_POST['vehicle_number'];

            $stmt = $conn->prepare("INSERT INTO vehicles (vehicle_id, user_id, vehicle_type, vehicle_number) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siss", $vehicle_id, $user_id, $vehicle_type, $vehicle_number);
            if (!$stmt->execute()) {
                throw new Exception("Failed to register new vehicle");
            }
        } else {
            throw new Exception("Invalid vehicle choice");
        }

        // Create parking transaction
        if (!isset($_POST['area_id']) || empty($_POST['area_id'])) {
            throw new Exception("Please select a parking area");
        }

        // Get the expected duration and calculated fee
        if (!isset($_POST['expected_duration']) || empty($_POST['expected_duration'])) {
            throw new Exception("Please select expected parking duration");
        }
        if (!isset($_POST['calculated_fee']) || empty($_POST['calculated_fee'])) {
            throw new Exception("Invalid parking fee calculation");
        }

        $expected_duration = $_POST['expected_duration'];
        $calculated_fee = floatval($_POST['calculated_fee']);

        // Create parking transaction with expected duration and fee
        $transaction_id = uniqid('TRX');
        $stmt = $conn->prepare("INSERT INTO parking_transactions (transaction_id, vehicle_id, area_id, entry_time, expected_duration, bill_amount, status) VALUES (?, ?, ?, NOW(), ?, ?, 'in')");
        $stmt->bind_param("ssisd", $transaction_id, $vehicle_id, $_POST['area_id'], $expected_duration, $calculated_fee);
        if (!$stmt->execute()) {
            throw new Exception("Failed to create parking transaction");
        }

        // Update parking area occupancy
        $column_occupied = match ($vehicle_type) {
            '2-wheeler' => 'occupied_2_wheeler_slots',
            '4-wheeler' => 'occupied_4_wheeler_slots',
            'commercial' => 'occupied_commercial_slots',
            default => throw new Exception("Invalid vehicle type")
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

    } catch (Exception $e) {
        if ($conn->connect_errno) {
            $conn->rollback();
        }
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
                            <label class="form-label">Choose Vehicle</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="vehicle_choice" id="existingVehicle" value="existing" <?php echo (count($vehicles) > 0) ? 'checked' : ''; ?> required>
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

                        <div id="existingVehicleSection" class="mb-3" style="display: <?php echo (count($vehicles) > 0) ? 'block' : 'none'; ?>">
                            <label for="vehicle_id" class="form-label">Select Your Vehicle</label>
                            <select class="form-select" id="vehicle_id" name="vehicle_id">
                                <option value="" disabled selected>Select a vehicle</option>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <option value="<?php echo $vehicle['vehicle_id']; ?>" data-type="<?php echo $vehicle['vehicle_type']; ?>">
                                        <?php echo $vehicle['vehicle_number']; ?> (<?php echo ucfirst($vehicle['vehicle_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="newVehicleSection" class="mb-3" style="display: <?php echo (count($vehicles) == 0) ? 'block' : 'none'; ?>">
                            <!-- New vehicle fields -->
                            <div class="mb-3">
                                <label for="vehicle_type" class="form-label">Vehicle Type</label>
                                <select class="form-select" id="vehicle_type" name="vehicle_type">
                                    <option value="" disabled selected>Select vehicle type</option>
                                    <option value="2-wheeler">2 Wheeler</option>
                                    <option value="4-wheeler">4 Wheeler</option>
                                    <option value="commercial">Commercial</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="vehicle_number" class="form-label">Vehicle Number</label>
                                <input type="text" class="form-control" id="vehicle_number" name="vehicle_number" placeholder="Enter vehicle number">
                            </div>
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

                        <div class="mb-3">
                            <label for="expected_duration" class="form-label">Expected Parking Duration</label>
                            <select class="form-select" id="expected_duration" name="expected_duration" required onchange="calculateEstimatedCost()">
                                <option value="" disabled selected>Select duration</option>
                                <option value="1hr">1 Hour</option>
                                <option value="2hr">2 Hours</option>
                                <option value="4hr">4 Hours</option>
                                <option value="1day">1 Day</option>
                                <option value="1week">1 Week</option>
                                <option value="1month">1 Month</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Estimated Cost</label>
                            <div id="estimated_cost" class="form-control-plaintext">₹0.00</div>
                            <input type="hidden" name="calculated_fee" id="calculated_fee" value="0">
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
function calculateEstimatedCost() {
    const durationSelect = document.getElementById('expected_duration');
    const estimatedCostDisplay = document.getElementById('estimated_cost');
    const calculatedFeeInput = document.getElementById('calculated_fee');
    const existingVehicleRadio = document.getElementById('existingVehicle');
    const vehicleIdSelect = document.getElementById('vehicle_id');
    const vehicleTypeSelect = document.getElementById('vehicle_type');
    
    let vehicleType = '';
    
    // Get vehicle type based on selection mode
    if (existingVehicleRadio && existingVehicleRadio.checked) {
        if (vehicleIdSelect && vehicleIdSelect.selectedIndex > 0) {
            const selectedOption = vehicleIdSelect.options[vehicleIdSelect.selectedIndex];
            vehicleType = selectedOption.getAttribute('data-type');
        }
    } else {
        if (vehicleTypeSelect) {
            vehicleType = vehicleTypeSelect.value;
        }
    }
    
    // Check if all required values are present
    if (!durationSelect.value || !vehicleType || !parkingRates || !parkingRates[vehicleType]) {
        estimatedCostDisplay.textContent = '₹0.00';
        calculatedFeeInput.value = '0';
        return;
    }
    
    const rate = parkingRates[vehicleType];
    let cost = 0;
    
    switch(durationSelect.value) {
        case '1hr':
            cost = rate.hourly;
            break;
        case '2hr':
            cost = rate.hourly * 2;
            break;
        case '4hr':
            cost = rate.hourly * 4;
            break;
        case '1day':
            cost = rate.daily;
            break;
        case '1week':
            cost = rate.weekly;
            break;
        case '1month':
            cost = rate.monthly;
            break;
    }
    
    estimatedCostDisplay.textContent = '₹' + cost.toFixed(2);
    calculatedFeeInput.value = cost;
}

document.addEventListener('DOMContentLoaded', function() {
    const existingVehicleRadio = document.getElementById('existingVehicle');
    const newVehicleRadio = document.getElementById('newVehicle');
    const existingVehicleSection = document.getElementById('existingVehicleSection');
    const newVehicleSection = document.getElementById('newVehicleSection');
    const vehicleIdSelect = document.getElementById('vehicle_id');
    const vehicleTypeSelect = document.getElementById('vehicle_type');
    const durationSelect = document.getElementById('expected_duration');
    
    // Add event listeners for form elements that affect cost calculation
    if (vehicleTypeSelect) {
        vehicleTypeSelect.addEventListener('change', calculateEstimatedCost);
    }

    if (vehicleIdSelect) {
        vehicleIdSelect.addEventListener('change', calculateEstimatedCost);
    }

    if (durationSelect) {
        durationSelect.addEventListener('change', calculateEstimatedCost);
    }

    // Toggle sections based on radio selection
    existingVehicleRadio.addEventListener('change', function() {
        existingVehicleSection.style.display = this.checked ? 'block' : 'none';
        newVehicleSection.style.display = this.checked ? 'none' : 'block';
        calculateEstimatedCost();
    });

    newVehicleRadio.addEventListener('change', function() {
        newVehicleSection.style.display = this.checked ? 'block' : 'none';
        existingVehicleSection.style.display = this.checked ? 'none' : 'block';
        calculateEstimatedCost();
    });

    // Initial calculation
    calculateEstimatedCost();
});
</script>

<?php include '../includes/footer.php'; ?>
