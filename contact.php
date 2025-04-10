<?php
require_once 'config/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = sanitize_input($conn, $_POST['name']);
    $email = sanitize_input($conn, $_POST['email']);
    $subject = sanitize_input($conn, $_POST['subject']);
    $message = sanitize_input($conn, $_POST['message']);
    
    // Insert into database
    $query = "INSERT INTO contact_messages (name, email, subject, message, submission_date) 
              VALUES (?, ?, ?, ?, NOW())";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssss", $name, $email, $subject, $message);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Thank you for your message. We'll get back to you soon!";
        } else {
            throw new Exception("Error sending message.");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Sorry, there was an error sending your message. Please try again.";
    }
    
    redirect('contact.php');
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-envelope"></i> Contact Us</h2>
                </div>
                <div class="card-body">
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

                    <form method="post" action="contact.php" id="contactForm">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>

                    <hr>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h5><i class="fas fa-map-marker-alt"></i> Address</h5>
                            <p>123 Parking Plaza, City Center</p>
                            
                            <h5><i class="fas fa-phone"></i> Phone</h5>
                            <p>+1 234 567 8900</p>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-envelope"></i> Email</h5>
                            <p>info@parkease.com</p>
                            
                            <h5><i class="fas fa-clock"></i> Business Hours</h5>
                            <p>24/7 Operation</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>