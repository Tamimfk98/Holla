<div class="col-lg-3 col-xl-2 d-none d-lg-block">
    <div class="gaming-card">
        <h6 class="text-accent mb-3"><i class="fas fa-bars"></i> Admin Menu</h6>
        <nav class="nav flex-column">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'tournaments.php' ? 'active' : '' ?>" href="tournaments.php">
                <i class="fas fa-trophy"></i> Tournaments
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'registrations.php' ? 'active' : '' ?>" href="registrations.php">
                <i class="fas fa-user-check"></i> Registrations
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>" href="users.php">
                <i class="fas fa-users"></i> Users
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'matches.php' ? 'active' : '' ?>" href="matches.php">
                <i class="fas fa-gamepad"></i> Matches
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'results.php' ? 'active' : '' ?>" href="results.php">
                <i class="fas fa-trophy-star"></i> Results
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : '' ?>" href="payments.php">
                <i class="fas fa-credit-card"></i> Payments
            </a>
        </nav>
        
        <hr class="my-3">
        
        <div class="text-center">
            <h6 class="text-light-50 small mb-2">Quick Stats</h6>
            <div class="row text-center">
                <div class="col-6">
                    <div class="text-accent fw-bold" id="quick-tournaments">-</div>
                    <small class="text-light-50">Tournaments</small>
                </div>
                <div class="col-6">
                    <div class="text-success fw-bold" id="quick-users">-</div>
                    <small class="text-light-50">Users</small>
                </div>
            </div>
        </div>
    </div>
</div>