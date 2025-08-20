<?php
require_once '../config/config.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$tournamentId = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle tournament registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'register') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $teamName = sanitizeInput($_POST['team_name'] ?? '');
        $teamMembers = sanitizeInput($_POST['team_members'] ?? '');
        
        if (empty($teamName)) {
            $error = 'Team name is required';
        } else {
            try {
                // Check if already registered
                $stmt = $pdo->prepare("
                    SELECT id FROM tournament_registrations 
                    WHERE user_id = ? AND tournament_id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $tournamentId]);
                
                if ($stmt->rowCount() > 0) {
                    $error = 'You are already registered for this tournament';
                } else {
                    // Register for tournament
                    $stmt = $pdo->prepare("
                        INSERT INTO tournament_registrations (user_id, tournament_id, team_name, team_members, status, created_at)
                        VALUES (?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $tournamentId, $teamName, $teamMembers]);
                    $registrationId = $pdo->lastInsertId();
                    
                    // Get tournament details for payment
                    $stmt = $pdo->prepare("SELECT entry_fee FROM tournaments WHERE id = ?");
                    $stmt->execute([$tournamentId]);
                    $entryFee = $stmt->fetchColumn();
                    
                    if ($entryFee > 0) {
                        // Redirect to payment
                        redirect("payments.php?action=pay&registration_id=$registrationId", 'Registration successful! Please complete payment.', 'success');
                    } else {
                        // Free tournament - auto approve
                        $stmt = $pdo->prepare("UPDATE tournament_registrations SET status = 'approved' WHERE id = ?");
                        $stmt->execute([$registrationId]);
                        $success = 'Successfully registered for the tournament!';
                        $action = 'list';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}

// Get tournament details for registration
$tournament = null;
if ($action === 'register' && $tournamentId) {
    $stmt = $pdo->prepare("
        SELECT *, 
               (SELECT COUNT(*) FROM tournament_registrations WHERE tournament_id = ? AND status = 'approved') as registered_teams
        FROM tournaments 
        WHERE id = ? AND status IN ('upcoming', 'active')
    ");
    $stmt->execute([$tournamentId, $tournamentId]);
    $tournament = $stmt->fetch();
    
    if (!$tournament) {
        $error = 'Tournament not found or registration closed';
        $action = 'list';
    } elseif ($tournament['registered_teams'] >= $tournament['max_teams']) {
        $error = 'Tournament is full';
        $action = 'list';
    }
}

// Get tournaments list
if ($action === 'list') {
    $status = sanitizeInput($_GET['status'] ?? '');
    $game = sanitizeInput($_GET['game'] ?? '');
    
    $whereClause = 'WHERE 1=1';
    $params = [];
    
    if ($status) {
        $whereClause .= ' AND t.status = ?';
        $params[] = $status;
    }
    
    if ($game) {
        $whereClause .= ' AND t.game_type = ?';
        $params[] = $game;
    }
    
    $stmt = $pdo->prepare("
        SELECT t.*, 
               COUNT(tr.id) as registered_teams,
               MAX(CASE WHEN utr.id IS NOT NULL THEN 1 ELSE 0 END) as is_registered
        FROM tournaments t
        LEFT JOIN tournament_registrations tr ON t.id = tr.tournament_id AND tr.status = 'approved'
        LEFT JOIN tournament_registrations utr ON t.id = utr.tournament_id AND utr.user_id = ?
        $whereClause
        GROUP BY t.id, t.name, t.description, t.game_type, t.max_teams, t.entry_fee, t.prize_pool, t.start_date, t.end_date, t.status, t.thumbnail, t.created_at, t.updated_at
        ORDER BY t.start_date ASC, t.created_at DESC
    ");
    $params = array_merge([$_SESSION['user_id']], $params);
    $stmt->execute($params);
    $tournaments = $stmt->fetchAll();
    
    // Get available games
    $stmt = $pdo->query("SELECT DISTINCT game_type FROM tournaments ORDER BY game_type");
    $games = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$flash = getFlashMessage();
if ($flash) {
    if ($flash['type'] === 'success') $success = $flash['message'];
    else $error = $flash['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournaments - eSports Tournament</title>
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
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="tournaments.php">
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
                            <li><a class="dropdown-item" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-light">
                <i class="fas fa-trophy text-accent"></i> 
                <?= $action === 'register' ? 'Tournament Registration' : 'Available Tournaments' ?>
            </h2>
            <?php if ($action === 'register'): ?>
                <a href="?" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back to Tournaments
                </a>
            <?php endif; ?>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($action === 'list'): ?>
            <!-- Filters -->
            <div class="gaming-card mb-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <select class="form-control gaming-input" name="status">
                            <option value="">All Tournaments</option>
                            <option value="upcoming" <?= ($_GET['status'] ?? '') === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                            <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="completed" <?= ($_GET['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-control gaming-input" name="game">
                            <option value="">All Games</option>
                            <?php foreach ($games as $gameType): ?>
                                <option value="<?= htmlspecialchars($gameType) ?>" 
                                        <?= ($_GET['game'] ?? '') === $gameType ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($gameType) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-accent w-100">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Tournaments Grid -->
            <?php if (empty($tournaments)): ?>
                <div class="gaming-card">
                    <div class="text-center text-light-50 py-5">
                        <i class="fas fa-trophy fa-4x mb-3"></i>
                        <h4>No Tournaments Available</h4>
                        <p>Check back later for new tournaments or adjust your filters</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($tournaments as $t): ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="gaming-card tournament-card h-100">
                                <?php if ($t['thumbnail']): ?>
                                <div class="tournament-thumbnail mb-3">
                                    <img src="<?= htmlspecialchars($t['thumbnail']) ?>" alt="<?= htmlspecialchars($t['game_type']) ?>" 
                                         class="img-fluid rounded" style="width: 100%; height: 150px; object-fit: cover;">
                                </div>
                                <?php endif; ?>
                                
                                <div class="tournament-header">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <span class="badge bg-<?= $t['status'] === 'active' ? 'success' : ($t['status'] === 'upcoming' ? 'info' : 'secondary') ?>">
                                            <?= ucfirst($t['status']) ?>
                                        </span>
                                        <span class="badge bg-primary"><?= htmlspecialchars($t['game_type']) ?></span>
                                    </div>
                                    
                                    <h5 class="text-accent mb-2"><?= htmlspecialchars($t['name']) ?></h5>
                                    <p class="text-light-50 small mb-3"><?= htmlspecialchars($t['description']) ?></p>
                                </div>
                                
                                <div class="tournament-details mb-3">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="text-warning fw-bold"><?= formatTaka($t['prize_pool']) ?></div>
                                            <small class="text-light-50">Prize Pool</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-info fw-bold"><?= $t['registered_teams'] ?>/<?= $t['max_teams'] ?></div>
                                            <small class="text-light-50">Teams</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-accent fw-bold"><?= formatTaka($t['entry_fee']) ?></div>
                                            <small class="text-light-50">Entry Fee</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="tournament-dates mb-3">
                                    <div class="d-flex justify-content-between text-light-50 small">
                                        <span><i class="fas fa-calendar"></i> <?= formatDate($t['start_date']) ?></span>
                                        <span><i class="fas fa-clock"></i> <?= formatDate($t['end_date']) ?></span>
                                    </div>
                                </div>
                                
                                <div class="tournament-progress mb-3">
                                    <?php 
                                    $progress = ($t['registered_teams'] / $t['max_teams']) * 100;
                                    ?>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-accent" style="width: <?= $progress ?>%"></div>
                                    </div>
                                    <small class="text-light-50"><?= round($progress) ?>% filled</small>
                                </div>
                                
                                <div class="tournament-actions mt-auto">
                                    <?php if ($t['is_registered']): ?>
                                        <button class="btn btn-success w-100" disabled>
                                            <i class="fas fa-check"></i> Registered
                                        </button>
                                    <?php elseif ($t['status'] === 'completed'): ?>
                                        <button class="btn btn-secondary w-100" disabled>
                                            <i class="fas fa-flag-checkered"></i> Completed
                                        </button>
                                    <?php elseif ($t['registered_teams'] >= $t['max_teams']): ?>
                                        <button class="btn btn-warning w-100" disabled>
                                            <i class="fas fa-users"></i> Tournament Full
                                        </button>
                                    <?php elseif ($t['status'] === 'active' || $t['status'] === 'upcoming'): ?>
                                        <a href="?action=register&id=<?= $t['id'] ?>" class="btn btn-accent w-100">
                                            <i class="fas fa-plus"></i> Register Now
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary w-100" disabled>
                                            <i class="fas fa-lock"></i> Registration Closed
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        <?php elseif ($action === 'register' && $tournament): ?>
            <!-- Registration Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="gaming-card">
                        <h4 class="text-accent mb-4">
                            <i class="fas fa-user-plus"></i> Tournament Registration
                        </h4>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-3">
                                <label for="team_name" class="form-label text-light">Team Name *</label>
                                <input type="text" class="form-control gaming-input" id="team_name" name="team_name" 
                                       required maxlength="50" placeholder="Enter your team name">
                                <small class="text-light-50">This will be your display name in the tournament</small>
                            </div>
                            
                            <div class="mb-4">
                                <label for="team_members" class="form-label text-light">Team Members</label>
                                <textarea class="form-control gaming-input" id="team_members" name="team_members" 
                                          rows="4" placeholder="List your team members (optional)..."></textarea>
                                <small class="text-light-50">You can add team member details here</small>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6 class="text-info"><i class="fas fa-info-circle"></i> Registration Details:</h6>
                                <ul class="mb-0">
                                    <li>Entry Fee: <strong><?= formatTaka($tournament['entry_fee']) ?></strong></li>
                                    <?php if ($tournament['entry_fee'] > 0): ?>
                                        <li>Payment will be required after registration</li>
                                        <li>Registration will be pending until payment is confirmed</li>
                                    <?php else: ?>
                                        <li>This is a free tournament - registration will be auto-approved</li>
                                    <?php endif; ?>
                                    <li>Make sure to read tournament rules carefully</li>
                                </ul>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-accent">
                                    <i class="fas fa-user-plus"></i> Register for Tournament
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="gaming-card">
                        <h5 class="text-accent mb-3">Tournament Details</h5>
                        
                        <table class="table table-dark table-sm">
                            <tr>
                                <th>Name:</th>
                                <td><?= htmlspecialchars($tournament['name']) ?></td>
                            </tr>
                            <tr>
                                <th>Game:</th>
                                <td><?= htmlspecialchars($tournament['game_type']) ?></td>
                            </tr>
                            <tr>
                                <th>Max Teams:</th>
                                <td><?= $tournament['max_teams'] ?></td>
                            </tr>
                            <tr>
                                <th>Registered:</th>
                                <td><?= $tournament['registered_teams'] ?></td>
                            </tr>
                            <tr>
                                <th>Entry Fee:</th>
                                <td class="text-accent fw-bold"><?= formatTaka($tournament['entry_fee']) ?></td>
                            </tr>
                            <tr>
                                <th>Prize Pool:</th>
                                <td class="text-warning fw-bold"><?= formatTaka($tournament['prize_pool']) ?></td>
                            </tr>
                            <tr>
                                <th>Start Date:</th>
                                <td><?= formatDate($tournament['start_date']) ?></td>
                            </tr>
                            <tr>
                                <th>End Date:</th>
                                <td><?= formatDate($tournament['end_date']) ?></td>
                            </tr>
                        </table>
                        
                        <?php if ($tournament['description']): ?>
                            <div class="mt-3">
                                <h6 class="text-light">Description:</h6>
                                <p class="text-light-50 small"><?= nl2br(htmlspecialchars($tournament['description'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
