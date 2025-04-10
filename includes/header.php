<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get current page for highlighting active navigation link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Parking Management System</title>
    <!-- Bootstrap CSS from CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <a href="/" class="logo">
                <i class="fas fa-parking"></i> ParkEase
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="/auth/logout.php" class="btn btn-sm btn-light ms-2">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </header>
