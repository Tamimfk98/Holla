<?php
// Admin Sidebar
?>
<div class="col-lg-2 sidebar bg-secondary p-0">
    <div class="sidebar-content">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" 
                   href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'tournaments.php' ? 'active' : '' ?>" 
                   href="tournaments.php">
                    <i class="fas fa-trophy"></i> Tournaments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'matches.php' ? 'active' : '' ?>" 
                   href="matches.php">
                    <i class="fas fa-gamepad"></i> Matches
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : '' ?>" 
                   href="payments.php">
                    <i class="fas fa-credit-card"></i> Payments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>" 
                   href="users.php">
                    <i class="fas fa-users"></i> Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'withdrawals.php' ? 'active' : '' ?>" 
                   href="withdrawals.php">
                    <i class="fas fa-money-bill-wave"></i> Withdrawals
                </a>
            </li>
        </ul>
    </div>
</div>