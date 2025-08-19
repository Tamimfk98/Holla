<?php
require_once '../config/config.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$matchId = $_GET['id'] ?? null;
$error = '';
$success = '';

// Get user matches
$userId = $_SESSION['user_id'];

if ($action === 'list') {
    $status = sanitizeInput($_GET['status'] ?? '');
    
    $whereClause = 'WHERE (m.team1_id = ? OR m.team2_id = ?)';
    $params = [$userId, $userId];
    
    if ($status) {
        $whereClause .= ' AND m.status = ?';
        $params[] = $status;
    }
    
    $stmt = $pdo->prepare("
        SELECT m.*, t.name as tournament_name, t.game_type,
               u1.username as team1_name, u2.username as team2_name,
               uw.username as winner_name,
               ms1.screenshot_url as team1_screenshot,
               ms2.screenshot_url as team2_screenshot
        FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        JOIN users u1 ON m.team1_id = u1.id
        JOIN users u2 ON m.team2_id = u2.id
        LEFT JOIN users uw ON m.winner_id = uw.id
        LEFT JOIN match_screenshots ms1 ON m.id = ms1.match_id AND ms1.team_id = u1.id
        LEFT JOIN match_screenshots ms2 ON m.id = ms2.match_id AND ms2.team_id = u2.id
        $whereClause
        ORDER BY m.scheduled_date DESC, m.created_at DESC
    ");
    $stmt->execute($params);
    $matches = $stmt->fetchAll();
}

// Get match details
$match = null;
if (($action === 'view' || $action === 'upload') && $matchId) {
    $stmt = $pdo->prepare("
        SELECT m.*, t.name as tournament_name, t.game_type,
               u1.username as team1_name, u2.username as team2_name,
               uw.username as winner_name,
               ms1.screenshot_url as team1_screenshot, ms1.uploaded_at as team1_upload_time,
               ms2.screenshot_url as team2_screenshot, ms2.uploaded_at as team2_upload_time
        FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        JOIN users u1 ON m.team1_id = u1.id
        JOIN users u2 ON m.team2_id = u2.id
        LEFT JOIN users uw ON m.winner_id = uw.id
        LEFT JOIN match_screenshots ms1 ON m.id = ms1.match_id AND ms1.team_id = u1.id
        LEFT JOIN match_screenshots ms2 ON m.id = ms2.match_id AND ms2.team_id = u2.id
        WHERE m.id = ? AND (m.team1_id = ? OR m.team2_id = ?)
    ");
    $stmt->execute([$matchId, $userId, $userId]);
    $match = $stmt->fetch();
    
    if (!$match) {
        $error = 'Match not found or you are not a participant';
        $action = 'list';
    }
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
    <title>My Matches - eSports Tournament</title>
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
                        <a class="nav-link" href="tournaments.php">
                            <i class="fas fa-trophy"></i> Tournaments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="matches.php">
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
                <i class="fas fa-gamepad text-accent"></i> 
                <?= $action === 'view' ? 'Match Details' : ($action === 'upload' ? 'Upload Screenshot' : 'My Matches') ?>
            </h2>
            <?php if ($action !== 'list'): ?>
                <a href="?" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back to Matches
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
            <!-- Match Status Filter -->
            <div class="gaming-card mb-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex gap-2">
                            <select class="form-control gaming-input" name="status" onchange="this.form.submit()">
                                <option value="">All Matches</option>
                                <option value="scheduled" <?= ($_GET['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                <option value="live" <?= ($_GET['status'] ?? '') === 'live' ? 'selected' : '' ?>>Live</option>
                                <option value="completed" <?= ($_GET['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="upload.php" class="btn btn-accent">
                            <i class="fas fa-upload"></i> Upload Screenshot
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Matches List -->
            <?php if (empty($matches)): ?>
                <div class="gaming-card">
                    <div class="text-center text-light-50 py-5">
                        <i class="fas fa-gamepad fa-4x mb-3"></i>
                        <h4>No Matches Found</h4>
                        <p>You don't have any matches yet. Register for tournaments to get matched!</p>
                        <a href="tournaments.php" class="btn btn-accent">
                            <i class="fas fa-trophy"></i> Browse Tournaments
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($matches as $match): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="gaming-card match-card h-100">
                                <div class="match-header mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-light-50"><?= htmlspecialchars($match['tournament_name']) ?></small>
                                        <span class="badge bg-<?= $match['status'] === 'completed' ? 'success' : ($match['status'] === 'live' ? 'danger' : 'info') ?>">
                                            <?= ucfirst($match['status']) ?>
                                        </span>
                                    </div>
                                    <div class="text-center">
                                        <span class="badge bg-info"><?= htmlspecialchars($match['game_type']) ?></span>
                                    </div>
                                </div>
                                
                                <div class="match-teams mb-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="team-info text-center">
                                            <div class="team-name <?= $match['team1_id'] == $userId ? 'text-accent fw-bold' : '' ?>">
                                                <?= htmlspecialchars($match['team1_name']) ?>
                                                <?php if ($match['team1_id'] == $userId): ?>
                                                    <i class="fas fa-user text-accent"></i>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($match['status'] === 'completed' && $match['score1'] !== null): ?>
                                                <div class="team-score text-light fs-4 fw-bold"><?= $match['score1'] ?></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="vs-indicator">
                                            <i class="fas fa-vs text-accent fa-2x"></i>
                                        </div>
                                        
                                        <div class="team-info text-center">
                                            <div class="team-name <?= $match['team2_id'] == $userId ? 'text-accent fw-bold' : '' ?>">
                                                <?= htmlspecialchars($match['team2_name']) ?>
                                                <?php if ($match['team2_id'] == $userId): ?>
                                                    <i class="fas fa-user text-accent"></i>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($match['status'] === 'completed' && $match['score2'] !== null): ?>
                                                <div class="team-score text-light fs-4 fw-bold"><?= $match['score2'] ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($match['status'] === 'completed' && $match['winner_name']): ?>
                                    <div class="match-result mb-3 text-center">
                                        <div class="alert alert-<?= $match['winner_id'] == $userId ? 'success' : 'info' ?> py-2">
                                            <i class="fas fa-trophy"></i> 
                                            <strong><?= htmlspecialchars($match['winner_name']) ?></strong> Won!
                                            <?php if ($match['winner_id'] == $userId): ?>
                                                <i class="fas fa-medal text-warning ms-2"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="match-details mb-3">
                                    <?php if ($match['scheduled_date']): ?>
                                        <div class="d-flex justify-content-between text-light-50 small mb-2">
                                            <span><i class="fas fa-calendar"></i> <?= formatDate($match['scheduled_date']) ?></span>
                                            <?php if ($match['round']): ?>
                                                <span><i class="fas fa-layer-group"></i> <?= htmlspecialchars($match['round']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Screenshot Status -->
                                    <div class="screenshot-status">
                                        <div class="row">
                                            <div class="col-6 text-center">
                                                <?php if ($match['team1_screenshot']): ?>
                                                    <i class="fas fa-check-circle text-success"></i>
                                                    <small class="text-success">Screenshot Uploaded</small>
                                                <?php elseif ($match['team1_id'] == $userId && $match['status'] !== 'scheduled'): ?>
                                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                                    <small class="text-warning">Upload Required</small>
                                                <?php else: ?>
                                                    <i class="fas fa-clock text-light-50"></i>
                                                    <small class="text-light-50">Waiting</small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-6 text-center">
                                                <?php if ($match['team2_screenshot']): ?>
                                                    <i class="fas fa-check-circle text-success"></i>
                                                    <small class="text-success">Screenshot Uploaded</small>
                                                <?php elseif ($match['team2_id'] == $userId && $match['status'] !== 'scheduled'): ?>
                                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                                    <small class="text-warning">Upload Required</small>
                                                <?php else: ?>
                                                    <i class="fas fa-clock text-light-50"></i>
                                                    <small class="text-light-50">Waiting</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="match-actions mt-auto">
                                    <div class="btn-group w-100" role="group">
                                        <a href="?action=view&id=<?= $match['id'] ?>" class="btn btn-outline-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($match['status'] !== 'scheduled' && $match['status'] !== 'completed'): ?>
                                            <?php 
                                            $userScreenshot = ($match['team1_id'] == $userId) ? $match['team1_screenshot'] : $match['team2_screenshot'];
                                            if (!$userScreenshot): ?>
                                                <a href="upload.php?match_id=<?= $match['id'] ?>" class="btn btn-warning">
                                                    <i class="fas fa-upload"></i> Upload
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        <?php elseif ($action === 'view' && $match): ?>
            <!-- Match Details View -->
            <div class="gaming-card">
                <div class="row">
                    <div class="col-md-8">
                        <h4 class="text-accent mb-3">Match Information</h4>
                        
                        <div class="match-details-view mb-4">
                            <table class="table table-dark">
                                <tr>
                                    <th>Tournament:</th>
                                    <td><?= htmlspecialchars($match['tournament_name']) ?></td>
                                </tr>
                                <tr>
                                    <th>Game:</th>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($match['game_type']) ?></span></td>
                                </tr>
                                <tr>
                                    <th>Round:</th>
                                    <td><?= htmlspecialchars($match['round'] ?? 'Not specified') ?></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge bg-<?= $match['status'] === 'completed' ? 'success' : ($match['status'] === 'live' ? 'danger' : 'info') ?>">
                                            <?= ucfirst($match['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Scheduled:</th>
                                    <td><?= $match['scheduled_date'] ? formatDate($match['scheduled_date']) : 'Not scheduled' ?></td>
                                </tr>
                                <?php if ($match['status'] === 'completed'): ?>
                                    <tr>
                                        <th>Completed:</th>
                                        <td><?= formatDate($match['completed_at']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Winner:</th>
                                        <td>
                                            <strong class="text-<?= $match['winner_id'] == $userId ? 'success' : 'info' ?>">
                                                <?= htmlspecialchars($match['winner_name']) ?>
                                                <?php if ($match['winner_id'] == $userId): ?>
                                                    <i class="fas fa-medal text-warning ms-2"></i>
                                                <?php endif; ?>
                                            </strong>
                                        </td>
                                    </tr>
                                    <?php if ($match['score1'] !== null || $match['score2'] !== null): ?>
                                        <tr>
                                            <th>Final Score:</th>
                                            <td>
                                                <span class="badge bg-info"><?= htmlspecialchars($match['team1_name']) ?></span>
                                                <span class="mx-2 fs-5 fw-bold"><?= $match['score1'] ?? 0 ?> - <?= $match['score2'] ?? 0 ?></span>
                                                <span class="badge bg-warning"><?= htmlspecialchars($match['team2_name']) ?></span>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </table>
                        </div>
                        
                        <!-- Match Notes -->
                        <?php if ($match['notes']): ?>
                            <div class="mb-4">
                                <h5 class="text-light">Match Notes:</h5>
                                <div class="alert alert-info">
                                    <?= nl2br(htmlspecialchars($match['notes'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Screenshot Status -->
                        <div class="gaming-card mb-3">
                            <h5 class="text-accent mb-3">Screenshots Status</h5>
                            
                            <div class="screenshot-section mb-3">
                                <h6 class="text-info"><?= htmlspecialchars($match['team1_name']) ?></h6>
                                <?php if ($match['team1_screenshot']): ?>
                                    <div class="alert alert-success py-2">
                                        <i class="fas fa-check-circle"></i> Uploaded
                                        <br><small>on <?= formatDate($match['team1_upload_time']) ?></small>
                                    </div>
                                    <a href="../uploads/screenshots/<?= $match['team1_screenshot'] ?>" 
                                       target="_blank" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-eye"></i> View Screenshot
                                    </a>
                                <?php else: ?>
                                    <div class="alert alert-secondary py-2">
                                        <i class="fas fa-clock"></i> Not uploaded yet
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="screenshot-section">
                                <h6 class="text-warning"><?= htmlspecialchars($match['team2_name']) ?></h6>
                                <?php if ($match['team2_screenshot']): ?>
                                    <div class="alert alert-success py-2">
                                        <i class="fas fa-check-circle"></i> Uploaded
                                        <br><small>on <?= formatDate($match['team2_upload_time']) ?></small>
                                    </div>
                                    <a href="../uploads/screenshots/<?= $match['team2_screenshot'] ?>" 
                                       target="_blank" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-eye"></i> View Screenshot
                                    </a>
                                <?php else: ?>
                                    <div class="alert alert-secondary py-2">
                                        <i class="fas fa-clock"></i> Not uploaded yet
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <?php if ($match['status'] !== 'scheduled' && $match['status'] !== 'completed'): ?>
                            <?php 
                            $userScreenshot = ($match['team1_id'] == $userId) ? $match['team1_screenshot'] : $match['team2_screenshot'];
                            if (!$userScreenshot): ?>
                                <a href="upload.php?match_id=<?= $match['id'] ?>" class="btn btn-warning w-100">
                                    <i class="fas fa-upload"></i> Upload Your Screenshot
                                </a>
                            <?php endif; ?>
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
