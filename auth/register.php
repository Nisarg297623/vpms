<?php
require_once '../config/db_connect.php';

// Check if user is already logged in
if (isLoggedIn()) {
    redirect('../index.php');
}

// Define variables and initialize with empty values
$username = $password = $confirm_password = $full_name = $email = $contact_no = $address = $city = $state = $society_name = "";
$username_err = $password_err = $confirm_password_err = $full_name_err = $email_err = $contact_no_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        // Prepare a select statement
        $sql = "SELECT user_id FROM users WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            
            // Set parameters
            $param_username = trim($_POST["username"]);
            
            // Attempt to execute the prepared statement
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $username_err = "This username is already taken.";
            } else {
                $username = trim($_POST["username"]);
            }
            
            $stmt->close();
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Validate full name
    if (empty(trim($_POST["full_name"]))) {
        $full_name_err = "Please enter your full name.";
    } else {
        $full_name = trim($_POST["full_name"]);
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Check if email already exists
        $sql = "SELECT user_id FROM users WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST["email"]);
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $email_err = "This email is already registered.";
            } else {
                $email = trim($_POST["email"]);
            }
            
            $stmt->close();
        }
    }
    
    // Validate contact number
    if (empty(trim($_POST["contact_no"]))) {
        $contact_no_err = "Please enter your contact number.";
    } else {
        $contact_no = trim($_POST["contact_no"]);
    }
    
    // Optional fields
    $address = trim($_POST["address"] ?? "");
    $city = trim($_POST["city"] ?? "");
    $state = trim($_POST["state"] ?? "");
    $society_name = trim($_POST["society_name"] ?? "");
    
    // Check input errors before inserting in database
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($full_name_err) && empty($email_err) && empty($contact_no_err)) {
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (username, password, full_name, email, contact_no, address, city, state, society_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
         
        if ($stmt = $conn->prepare($sql)) {
            // Hash the password
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Bind parameters
            $stmt->bind_param("sssssssss", 
                $username,
                $param_password,
                $_POST['full_name'],
                $_POST['email'],
                $_POST['contact_no'],
                $_POST['address'],
                $_POST['city'],
                $_POST['state'],
                $_POST['society_name']
            );
            
            // Attempt to execute
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Registration successful! Please login.";
                redirect('login.php');
            } else {
                echo "Something went wrong. Please try again later.";
            }

            $stmt->close();
        }
    }
    
    // No need to close connection with PDO
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="auth-container">
        <h2 class="text-center mb-4">Register</h2>
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
                        <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $password; ?>">
                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $confirm_password; ?>">
                        <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $full_name; ?>">
                        <span class="invalid-feedback"><?php echo $full_name_err; ?></span>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_no" class="form-control <?php echo (!empty($contact_no_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $contact_no; ?>">
                        <span class="invalid-feedback"><?php echo $contact_no_err; ?></span>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control"><?php echo $address; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control" value="<?php echo $city; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">State</label>
                                <input type="text" name="state" class="form-control" value="<?php echo $state; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Society/Company Name</label>
                        <input type="text" name="society_name" class="form-control" value="<?php echo $society_name; ?>">
                    </div>
                    
                    <div class="form-group mb-3">
                        <button type="submit" class="btn btn-primary btn-block w-100">Register</button>
                    </div>
                    
                    <p class="text-center">Already have an account? <a href="login.php">Login here</a>.</p>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
