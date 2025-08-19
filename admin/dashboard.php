<?php
require_once '../config/config.php';
requireAdminLogin();

// Get dashboard statistics
try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $totalUsers = $stmt->fetch()['total'];
    
    // Total tournaments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tournaments");
    $totalTournaments = $stmt->fetch()['total'];
    
    // Active tournaments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tournaments WHERE status = 'active'");
    $activeTournaments = $stmt->fetch()['total'];
    
    // Total prize pool
    $stmt = $pdo->query("SELECT SUM(prize_pool) as total FROM tournaments WHERE status IN ('active', 'upcoming')");
    $totalPrizePool = $stmt->fetch()['total'] ?? 0;
    
    // Recent registrations
    $stmt = $pdo->query("
        SELECT tr.*, u.username, t.name as tournament_name 
        FROM tournament_registrations tr
        JOIN users u ON tr.user_id = u.id
        JOIN tournaments t ON tr.tournament_id = t.id
        ORDER BY tr.created_at DESC 
        LIMIT 10
    ");
    $recentRegistrations = $stmt->fetchAll();
    
    // Pending payments
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM payments 
        WHERE status = 'pending'
    ");
    $pendingPayments = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $totalUsers = $totalTournaments = $activeTournaments = $totalPrizePool = $pendingPayments = 0;
    $recentRegistrations = [];
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - eSports Tournament</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <div class="col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-light">
                        <i class="fas fa-tachometer-alt text-accent"></i> Dashboard
                    </h2>
                    <div class="text-light-50">
                        Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?>
                    </div>
                </div>
                
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="gaming-card stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="text-accent mb-0"><?= number_format($totalUsers) ?></h3>
                                    <p class="text-light-50 mb-0">Total Users</p>
                                </div>
                                <i class="fas fa-users fa-2x text-accent"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="gaming-card stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="text-accent mb-0"><?= number_format($totalTournaments) ?></h3>
                                    <p class="text-light-50 mb-0">Total Tournaments</p>
                                </div>
                                <i class="fas fa-trophy fa-2x text-accent"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="gaming-card stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="text-success mb-0"><?= number_format($activeTournaments) ?></h3>
                                    <p class="text-light-50 mb-0">Active Tournaments</p>
                                </div>
                                <i class="fas fa-play-circle fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="gaming-card stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="text-warning mb-0"><?= formatTaka($totalPrizePool) ?></h3>
                                    <p class="text-light-50 mb-0">Total Prize Pool</p>
                                </div>
                                <i class="fas fa-coins fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Recent Registrations -->
                    <div class="col-lg-8 mb-4">
                        <div class="gaming-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="text-accent mb-0">
                                    <i class="fas fa-user-plus"></i> Recent Registrations
                                </h4>
                                <a href="tournaments.php" class="btn btn-sm btn-outline-accent">
                                    View All
                                </a>
                            </div>
                            
                            <?php if (empty($recentRegistrations)): ?>
                                <div class="text-center text-light-50 py-4">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p>No recent registrations</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Tournament</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentRegistrations as $reg): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($reg['username']) ?></td>
                                                    <td><?= htmlspecialchars($reg['tournament_name']) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $reg['status'] === 'approved' ? 'success' : ($reg['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                            <?= ucfirst($reg['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= timeAgo($reg['created_at']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="col-lg-4 mb-4">
                        <div class="gaming-card">
                            <h4 class="text-accent mb-3">
                                <i class="fas fa-bolt"></i> Quick Actions
                            </h4>
                            
                            <div class="d-grid gap-2">
                                <a href="tournaments.php?action=create" class="btn btn-accent">
                                    <i class="fas fa-plus"></i> Create Tournament
                                </a>
                                <a href="payments.php" class="btn btn-warning">
                                    <i class="fas fa-credit-card"></i> Review Payments
                                    <?php if ($pendingPayments > 0): ?>
                                        <span class="badge bg-danger"><?= $pendingPayments ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="users.php" class="btn btn-info">
                                    <i class="fas fa-users"></i> Manage Users
                                </a>
                                <a href="matches.php" class="btn btn-success">
                                    <i class="fas fa-gamepad"></i> Manage Matches
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>

<?php
// Create includes directory and files if they don't exist
$includesDir = __DIR__ . '/includes';
if (!is_dir($includesDir)) {
    mkdir($includesDir, 0755, true);
}

// Create navbar.php
$navbarContent = '<?php
// Admin Navigation Bar
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="fas fa-shield-alt text-accent"></i> eSports Admin
        </a>
        
        <div class="ms-auto">
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION[\'admin_username\']) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-dark">
                    <li><a class="dropdown-item" href="../index.php"><i class="fas fa-home"></i> View Site</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>';

file_put_contents($includesDir . '/navbar.php', $navbarContent);

// Create sidebar.php
$sidebarContent = '<?php
// Admin Sidebar
?>
<div class="col-lg-2 sidebar bg-secondary p-0">
    <div class="sidebar-content">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER[\'PHP_SELF\']) === \'dashboard.php\' ? \'active\' : \'\' ?>" 
                   href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER[\'PHP_SELF\']) === \'tournaments.php\' ? \'active\' : \'\' ?>" 
                   href="tournaments.php">
                    <i class="fas fa-trophy"></i> Tournaments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER[\'PHP_SELF\']) === \'matches.php\' ? \'active\' : \'\' ?>" 
                   href="matches.php">
                    <i class="fas fa-gamepad"></i> Matches
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER[\'PHP_SELF\']) === \'payments.php\' ? \'active\' : \'\' ?>" 
                   href="payments.php">
                    <i class="fas fa-credit-card"></i> Payments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER[\'PHP_SELF\']) === \'users.php\' ? \'active\' : \'\' ?>" 
                   href="users.php">
                    <i class="fas fa-users"></i> Users
                </a>
            </li>
        </ul>
    </div>
</div>';

file_put_contents($includesDir . '/sidebar.php', $sidebarContent);
?>
