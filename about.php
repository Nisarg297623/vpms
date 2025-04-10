<?php
require_once 'config/db_connect.php';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-info-circle"></i> About ParkEase</h2>
                </div>
                <div class="card-body">
                    <h4>Welcome to ParkEase - Smart Parking Management System</h4>
                    <p class="lead">Making parking management simple, efficient, and hassle-free.</p>
                    
                    <hr>
                    
                    <h5>Our Features</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> Real-time parking slot availability</li>
                        <li><i class="fas fa-check text-success"></i> Multiple vehicle type support</li>
                        <li><i class="fas fa-check text-success"></i> Secure online payments</li>
                        <li><i class="fas fa-check text-success"></i> Digital receipts and history</li>
                        <li><i class="fas fa-check text-success"></i> User-friendly interface</li>
                    </ul>

                    <hr>

                    <h5>Why Choose Us?</h5>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6><i class="fas fa-shield-alt"></i> Secure</h6>
                                <p>State-of-the-art security measures to protect your vehicle</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6><i class="fas fa-clock"></i> 24/7 Service</h6>
                                <p>Round-the-clock parking facility and support</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>