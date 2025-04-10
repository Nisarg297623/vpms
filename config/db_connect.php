<?php
// Database connection configuration for XAMPP MySQL
$host = 'localhost';
$dbname = 'parking_db';
$username = 'root';
$password = '';

// Create connection using MySQLi
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Function to sanitize inputs to prevent SQL injection
function sanitize_input($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Function to generate a unique vehicle ID
function generateVehicleID($conn) {
    $prefix = "V";
    try {
        $query = "SELECT MAX(CAST(SUBSTRING(vehicle_id, 2) AS SIGNED)) as max_id FROM vehicles";
        $result = $conn->query($query);
        $row = $result->fetch_assoc();
        $next_id = ($row['max_id'] ?? 0) + 1;
        return $prefix . str_pad($next_id, 9, "0", STR_PAD_LEFT);
    } catch (Exception $e) {
        return $prefix . "000000001"; // Fallback if table doesn't exist yet
    }
}

// Function to generate a unique transaction ID
function generateTransactionID($conn) {
    $prefix = "T";
    try {
        $query = "SELECT MAX(CAST(SUBSTRING(transaction_id, 2) AS SIGNED)) as max_id FROM parking_transactions";
        $result = $conn->query($query);
        $row = $result->fetch_assoc();
        $next_id = ($row['max_id'] ?? 0) + 1;
        return $prefix . str_pad($next_id, 9, "0", STR_PAD_LEFT);
    } catch (Exception $e) {
        return $prefix . "000000001";
    }
}

// Function to generate a unique payment ID
function generatePaymentID($conn) {
    $prefix = "P";
    try {
        $query = "SELECT MAX(CAST(SUBSTRING(payment_id, 2) AS SIGNED)) as max_id FROM payments";
        $result = $conn->query($query);
        $row = $result->fetch_assoc();
        $next_id = ($row['max_id'] ?? 0) + 1;
        return $prefix . str_pad($next_id, 9, "0", STR_PAD_LEFT);
    } catch (Exception $e) {
        return $prefix . "000000001";
    }
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin';
}

// Function to redirect
function redirect($path) {
    header("Location: $path");
    exit;
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
