<?php
require_once '../config/config.php';
requireLogin();

// Get user statistics
try {
    $userId = $_SESSION['user_id'];
    
    // Get user profile
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userProfile = $stmt->fetch();
    
    // Get tournament registrations
    $stmt = $pdo->prepare("
        SELECT tr.*, t.name as tournament_name, t.status as tournament_status, 
               t.start_date, t.entry_fee, t.prize_pool
        FROM tournament_registrations tr
        JOIN tournaments t ON tr.tournament_id = t.id
        WHERE tr.user_id = ?
        ORDER BY tr.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentRegistrations = $stmt->fetchAll();
    
    // Get upcoming matches
    $stmt = $pdo->prepare("
        SELECT m.*, t.name as tournament_name,
               u1.username as team1_name, u2.username as team2_name
        FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        JOIN users u1 ON m.team1_id = u1.id
        JOIN users u2 ON m.team2_id = u2.id
        WHERE (m.team1_id = ? OR m.team2_id = ?) 
        AND m.status IN ('scheduled', 'live')
        ORDER BY m.scheduled_date ASC
        LIMIT 5
    ");
    $stmt->execute([$userId, $userId]);
    $upcomingMatches = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT tr.id) as total_registrations,
            COUNT(DISTINCT CASE WHEN tr.status = 'approved' THEN tr.id END) as approved_registrations,
            COUNT(DISTINCT m.id) as total_matches,
            COUNT(DISTINCT CASE WHEN m.winner_id = ? THEN m.id END) as won_matches,
            SUM(DISTINCT CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as total_spent
        FROM tournament_registrations tr
        LEFT JOIN payments p ON tr.id = p.registration_id
        LEFT JOIN matches m ON (m.team1_id = ? OR m.team2_id = ?) AND m.status = 'completed'
        WHERE tr.user_id = ?
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $stats = $stmt->fetch();
    
    // Get pending payments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_payments
        FROM payments p
        JOIN tournament_registrations tr ON p.registration_id = tr.id
        WHERE tr.user_id = ? AND p.status = 'pending'
    ");
    $stmt->execute([$userId]);
    $pendingPayments = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $userProfile = null;
    $recentRegistrations = [];
    $upcomingMatches = [];
    $stats = ['total_registrations' => 0, 'approved_registrations' => 0, 'total_matches' => 0, 'won_matches' => 0, 'total_spent' => 0];
    $pendingPayments = 0;
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - eSports Tournament</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-trophy text-accent"></i> eSports Hub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tournaments.php">
                            <i class="fas fa-trophy"></i> Tournaments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="matches.php">
                            <i class="fas fa-gamepad"></i> Matches
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payments.php">
                            <i class="fas fa-credit-card"></i> Payments
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                                <i class="fas fa-user-edit"></i> Profile
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container my-4">
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>
        
        <!-- Welcome Section -->
        <div class="gaming-card mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="text-accent mb-2">
                        Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!
                    </h2>
                    <p class="text-light-50 mb-0">
                        Ready for your next gaming challenge? Check out the latest tournaments and matches.
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="wallet-display">
                        <div class="text-light-50">Wallet Balance</div>
                        <div class="text-success fs-4 fw-bold">
                            <?= formatTaka($userProfile['wallet_balance'] ?? 0) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="gaming-card stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-accent mb-0"><?= $stats['total_registrations'] ?></h3>
                            <p class="text-light-50 mb-0">Total Registrations</p>
                        </div>
                        <i class="fas fa-user-plus fa-2x text-accent"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="gaming-card stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-success mb-0"><?= $stats['approved_registrations'] ?></h3>
                            <p class="text-light-50 mb-0">Active Tournaments</p>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-success"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="gaming-card stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-info mb-0"><?= $stats['won_matches'] ?>/<?= $stats['total_matches'] ?></h3>
                            <p class="text-light-50 mb-0">Matches Won</p>
                        </div>
                        <i class="fas fa-trophy fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="gaming-card stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-warning mb-0"><?= formatTaka($stats['total_spent']) ?></h3>
                            <p class="text-light-50 mb-0">Total Spent</p>
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
                            <i class="fas fa-trophy"></i> Recent Tournament Registrations
                        </h4>
                        <a href="tournaments.php" class="btn btn-sm btn-outline-accent">
                            View All
                        </a>
                    </div>
                    
                    <?php if (empty($recentRegistrations)): ?>
                        <div class="text-center text-light-50 py-4">
                            <i class="fas fa-trophy fa-3x mb-3"></i>
                            <h5>No Tournament Registrations Yet</h5>
                            <p>Join your first tournament to get started!</p>
                            <a href="tournaments.php" class="btn btn-accent">
                                <i class="fas fa-plus"></i> Browse Tournaments
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover">
                                <thead>
                                    <tr>
                                        <th>Tournament</th>
                                        <th>Status</th>
                                        <th>Entry Fee</th>
                                        <th>Prize Pool</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentRegistrations as $reg): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($reg['tournament_name']) ?></strong>
                                                <br><small class="text-light-50">Team: <?= htmlspecialchars($reg['team_name']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $reg['status'] === 'approved' ? 'success' : ($reg['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                    <?= ucfirst($reg['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= formatTaka($reg['entry_fee']) ?></td>
                                            <td class="text-warning fw-bold"><?= formatTaka($reg['prize_pool']) ?></td>
                                            <td><?= timeAgo($reg['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions & Upcoming Matches -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="gaming-card mb-4">
                    <h5 class="text-accent mb-3">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </h5>
                    
                    <div class="d-grid gap-2">
                        <a href="tournaments.php" class="btn btn-accent">
                            <i class="fas fa-trophy"></i> Join Tournament
                        </a>
                        
                        <?php if ($pendingPayments > 0): ?>
                            <a href="payments.php" class="btn btn-warning">
                                <i class="fas fa-exclamation-triangle"></i> Pending Payments
                                <span class="badge bg-danger"><?= $pendingPayments ?></span>
                            </a>
                        <?php endif; ?>
                        
                        <a href="upload.php" class="btn btn-info">
                            <i class="fas fa-upload"></i> Upload Match Screenshot
                        </a>
                        
                        <a href="matches.php" class="btn btn-success">
                            <i class="fas fa-gamepad"></i> My Matches
                        </a>
                    </div>
                </div>
                
                <!-- Upcoming Matches -->
                <div class="gaming-card">
                    <h5 class="text-accent mb-3">
                        <i class="fas fa-clock"></i> Upcoming Matches
                    </h5>
                    
                    <?php if (empty($upcomingMatches)): ?>
                        <div class="text-center text-light-50 py-3">
                            <i class="fas fa-gamepad fa-2x mb-2"></i>
                            <p class="small mb-0">No upcoming matches</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcomingMatches as $match): ?>
                            <div class="match-card mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-light-50"><?= htmlspecialchars($match['tournament_name']) ?></small>
                                    <span class="badge bg-<?= $match['status'] === 'live' ? 'danger' : 'info' ?>">
                                        <?= ucfirst($match['status']) ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="badge bg-info"><?= htmlspecialchars($match['team1_name']) ?></span>
                                    <i class="fas fa-vs text-accent mx-2"></i>
                                    <span class="badge bg-warning"><?= htmlspecialchars($match['team2_name']) ?></span>
                                </div>
                                <div class="text-center mt-2">
                                    <small class="text-light-50">
                                        <?= formatDate($match['scheduled_date']) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-secondary">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-accent">
                        <i class="fas fa-user-edit"></i> Update Profile
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="profileForm" action="../api/update_profile.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label text-light">Full Name</label>
                            <input type="text" class="form-control gaming-input" id="full_name" name="full_name" 
                                   value="<?= htmlspecialchars($userProfile['full_name'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label text-light">Phone Number</label>
                            <input type="tel" class="form-control gaming-input" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($userProfile['phone'] ?? '') ?>">
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-accent">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
