<?php
require_once '../config/db_connect.php';

// Check if user is already logged in
if (isLoggedIn()) {
    redirect('../index.php');
}

$username = $password = "";
$username_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT user_id, username, password, user_type FROM users WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            
            // Set parameters
            $param_username = $username;
            
            // Execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                // Check if username exists
                if ($stmt->num_rows == 1) {
                    // Bind result variables
                    $stmt->bind_result($id, $username, $hashed_password, $user_type);
                    $stmt->fetch();
                    
                    if (password_verify($password, $hashed_password)) {
                        // Password is correct, start a new session
                        session_start();
                        
                        // Store data in session variables
                        $_SESSION["user_id"] = $id;
                        $_SESSION["username"] = $username;
                        $_SESSION["user_type"] = $user_type;
                        
                        // Update last login time
                        $update_sql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $update_stmt->bind_param("i", $id);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }
                        
                        // Redirect user to appropriate page
                        if ($user_type == "admin") {
                            header("location: ../admin/dashboard.php");
                        } else {
                            header("location: ../index.php");
                        }
                    } else {
                        $login_err = "Invalid username or password.";
                    }
                } else {
                    $login_err = "Invalid username or password.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
        }
    }
    
    // No need to close connection with PDO
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="auth-container">
        <h2 class="text-center mb-4">Login</h2>
        
        <?php 
        if (!empty($login_err)) {
            echo '<div class="alert alert-danger">' . $login_err . '</div>';
        }        
        ?>

        <div class="card">
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="form-group mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                        <span class="invalid-feedback"><?php echo $username_err; ?></span>
                    </div>    
                    <div class="form-group mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                    </div>
                    <div class="form-group mb-3">
                        <button type="submit" class="btn btn-primary btn-block w-100">Login</button>
                    </div>
                    <p class="text-center">Don't have an account? <a href="register.php">Sign up now</a>.</p>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
