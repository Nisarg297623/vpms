<nav class="navbar">
    <div class="container">
        <ul>
            <li><a href="/index.php" <?php echo ($current_page == 'index.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-home"></i> Home
            </a></li>
            
            <?php if (!isset($_SESSION['user_id'])): ?>
                <li><a href="/auth/login.php" <?php echo ($current_page == 'login.php') ? 'class="active"' : ''; ?>>
                    <i class="fas fa-sign-in-alt"></i> Login
                </a></li>
                <li><a href="/auth/register.php" <?php echo ($current_page == 'register.php') ? 'class="active"' : ''; ?>>
                    <i class="fas fa-user-plus"></i> Register
                </a></li>
            <?php else: ?>
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                    <li><a href="/admin/dashboard.php" <?php echo ($current_page == 'dashboard.php') ? 'class="active"' : ''; ?>>
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a></li>
                    <li><a href="/admin/manage_users.php" <?php echo ($current_page == 'manage_users.php') ? 'class="active"' : ''; ?>>
                        <i class="fas fa-users"></i> Manage Users
                    </a></li>
                    <li><a href="/admin/manage_parking.php" <?php echo ($current_page == 'manage_parking.php') ? 'class="active"' : ''; ?>>
                        <i class="fas fa-parking"></i> Manage Parking
                    </a></li>
                    <li><a href="/admin/manage_payments.php" <?php echo ($current_page == 'manage_payments.php') ? 'class="active"' : ''; ?>>
                        <i class="fas fa-money-bill-wave"></i> Payments
                    </a></li>
                    <li><a href="/admin/reports.php" <?php echo ($current_page == 'reports.php') ? 'class="active"' : ''; ?>>
                        <i class="fas fa-chart-bar"></i> Reports
                    </a></li>
                <?php else: ?>
                    <li><a href="/parking/entry.php" <?php echo ($current_page == 'entry.php') ? 'class="active"' : ''; ?>>
                        <i class="fas fa-car"></i> Vehicle Entry
                    </a></li>
                    <li><a href="/parking/exit.php" <?php echo ($current_page == 'exit.php') ? 'class="active"' : ''; ?>>
                        <i class="fas fa-sign-out-alt"></i> Vehicle Exit
                    </a></li>
                    <li><a href="/parking/view_status.php" <?php echo ($current_page == 'view_status.php') ? 'class="active"' : ''; ?>>
                        <i class="fas fa-info-circle"></i> View Status
                    </a></li>
                <?php endif; ?>
            <?php endif; ?>
            
            <li><a href="/about.php" <?php echo ($current_page == 'about.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-info-circle"></i> About
            </a></li>
            <li><a href="/contact.php" <?php echo ($current_page == 'contact.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-envelope"></i> Contact
            </a></li>
        </ul>
    </div>
</nav>
