<?php
require_once '../config/config.php';
requireAdminLogin();

$action = $_GET['action'] ?? 'list';
$matchId = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle match result updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $winnerId = (int)($_POST['winner_id'] ?? 0);
        $score1 = (int)($_POST['score1'] ?? 0);
        $score2 = (int)($_POST['score2'] ?? 0);
        $notes = sanitizeInput($_POST['notes'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? '');
        
        if ($winnerId && in_array($status, ['completed', 'pending_review'])) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE matches 
                    SET winner_id = ?, score1 = ?, score2 = ?, notes = ?, status = ?, completed_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$winnerId, $score1, $score2, $notes, $status, $matchId]);
                
                // Create notifications for both teams
                $stmt = $pdo->prepare("
                    SELECT team1_id, team2_id, tournament_id 
                    FROM matches 
                    WHERE id = ?
                ");
                $stmt->execute([$matchId]);
                $match = $stmt->fetch();
                
                if ($match) {
                    $winnerMessage = "Congratulations! You won your match.";
                    $loserMessage = "Your match result has been updated. Better luck next time!";
                    
                    // Notify winner
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type, created_at)
                        VALUES (?, 'Match Result', ?, 'success', CURRENT_TIMESTAMP)
                    ");
                    $stmt->execute([$winnerId, $winnerMessage]);
                    
                    // Notify loser
                    $loserId = $winnerId == $match['team1_id'] ? $match['team2_id'] : $match['team1_id'];
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type, created_at)
                        VALUES (?, 'Match Result', ?, 'info', CURRENT_TIMESTAMP)
                    ");
                    $stmt->execute([$loserId, $loserMessage]);
                }
                
                $success = 'Match result updated successfully!';
                $action = 'list';
            } catch (PDOException $e) {
                $error = 'Failed to update match result: ' . $e->getMessage();
            }
        } else {
            $error = 'Please select a winner and valid status';
        }
    }
}

// Get match details for editing
$match = null;
if ($action === 'update' && $matchId) {
    $stmt = $pdo->prepare("
        SELECT m.*, t.name as tournament_name,
               u1.username as team1_name, u1.full_name as team1_fullname,
               u2.username as team2_name, u2.full_name as team2_fullname,
               ms1.screenshot_url as team1_screenshot, ms1.uploaded_at as team1_upload_time,
               ms2.screenshot_url as team2_screenshot, ms2.uploaded_at as team2_upload_time
        FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        JOIN users u1 ON m.team1_id = u1.id
        JOIN users u2 ON m.team2_id = u2.id
        LEFT JOIN match_screenshots ms1 ON m.id = ms1.match_id AND ms1.team_id = u1.id
        LEFT JOIN match_screenshots ms2 ON m.id = ms2.match_id AND ms2.team_id = u2.id
        WHERE m.id = ?
    ");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    
    if (!$match) {
        $error = 'Match not found';
        $action = 'list';
    }
}

// Get matches list
if ($action === 'list') {
    $status = sanitizeInput($_GET['status'] ?? '');
    $tournament = sanitizeInput($_GET['tournament'] ?? '');
    
    $whereClause = 'WHERE 1=1';
    $params = [];
    
    if ($status) {
        $whereClause .= ' AND m.status = ?';
        $params[] = $status;
    }
    
    if ($tournament) {
        $whereClause .= ' AND m.tournament_id = ?';
        $params[] = $tournament;
    }
    
    $stmt = $pdo->prepare("
        SELECT m.*, t.name as tournament_name,
               u1.username as team1_name, u1.full_name as team1_fullname,
               u2.username as team2_name, u2.full_name as team2_fullname,
               w.username as winner_name
        FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        JOIN users u1 ON m.team1_id = u1.id
        JOIN users u2 ON m.team2_id = u2.id
        LEFT JOIN users w ON m.winner_id = w.id
        $whereClause
        ORDER BY m.scheduled_date DESC, m.created_at DESC
    ");
    $stmt->execute($params);
    $matches = $stmt->fetchAll();
    
    // Get tournaments for filter
    $stmt = $pdo->query("SELECT id, name FROM tournaments ORDER BY created_at DESC");
    $tournaments = $stmt->fetchAll();
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
    <title>Match Results - Admin</title>
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
            
            <div class="col-lg-9 col-xl-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-light">
                        <i class="fas fa-trophy text-accent"></i> Match Results Management
                    </h2>
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
                                    <option value="">All Status</option>
                                    <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                    <option value="live" <?= $status === 'live' ? 'selected' : '' ?>>Live</option>
                                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="pending_review" <?= $status === 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-control gaming-input" name="tournament">
                                    <option value="">All Tournaments</option>
                                    <?php foreach ($tournaments as $t): ?>
                                        <option value="<?= $t['id'] ?>" <?= $tournament == $t['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-accent w-100">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Matches Table -->
                    <div class="gaming-card">
                        <?php if (empty($matches)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-gamepad fa-4x text-accent mb-3"></i>
                                <h4>No Matches Found</h4>
                                <p class="text-light-50">No matches match your current filters</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tournament</th>
                                            <th>Teams</th>
                                            <th>Score</th>
                                            <th>Winner</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($matches as $m): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($m['tournament_name']) ?></strong><br>
                                                    <?php if ($m['round']): ?>
                                                        <small class="text-light-50"><?= htmlspecialchars($m['round']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span><?= htmlspecialchars($m['team1_name']) ?></span>
                                                        <small class="text-accent">vs</small>
                                                        <span><?= htmlspecialchars($m['team2_name']) ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($m['score1'] !== null && $m['score2'] !== null): ?>
                                                        <span class="fw-bold"><?= $m['score1'] ?> - <?= $m['score2'] ?></span>
                                                    <?php else: ?>
                                                        <span class="text-light-50">Not set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($m['winner_name']): ?>
                                                        <span class="text-accent fw-bold"><?= htmlspecialchars($m['winner_name']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-light-50">TBD</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $m['status'] === 'completed' ? 'success' : ($m['status'] === 'live' ? 'warning' : ($m['status'] === 'pending_review' ? 'info' : 'secondary')) ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $m['status'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($m['scheduled_date']): ?>
                                                        <?= formatDate($m['scheduled_date']) ?>
                                                    <?php else: ?>
                                                        <span class="text-light-50">Not scheduled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (in_array($m['status'], ['scheduled', 'live', 'pending_review'])): ?>
                                                        <a href="results.php?action=update&id=<?= $m['id'] ?>" class="btn btn-sm btn-accent">
                                                            <i class="fas fa-edit"></i> Update Result
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-success">Completed</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                
                <?php elseif ($action === 'update' && $match): ?>
                    <!-- Update Result Form -->
                    <div class="gaming-card">
                        <h4 class="text-accent mb-4">
                            <i class="fas fa-trophy"></i> Update Match Result
                        </h4>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-light">Match Information</h6>
                                <p><strong>Tournament:</strong> <?= htmlspecialchars($match['tournament_name']) ?></p>
                                <?php if ($match['round']): ?>
                                    <p><strong>Round:</strong> <?= htmlspecialchars($match['round']) ?></p>
                                <?php endif; ?>
                                <?php if ($match['scheduled_date']): ?>
                                    <p><strong>Scheduled:</strong> <?= formatDate($match['scheduled_date']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-light">Current Status</h6>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?= $match['status'] === 'completed' ? 'success' : ($match['status'] === 'live' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst(str_replace('_', ' ', $match['status'])) ?>
                                    </span>
                                </p>
                                <?php if ($match['winner_id']): ?>
                                    <p><strong>Current Winner:</strong> <?= htmlspecialchars($match['winner_name'] ?? 'Unknown') ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="gaming-card bg-secondary">
                                    <h6 class="text-accent">Team 1</h6>
                                    <p class="mb-0"><strong><?= htmlspecialchars($match['team1_fullname']) ?></strong></p>
                                    <small class="text-light-50">@<?= htmlspecialchars($match['team1_name']) ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="gaming-card bg-secondary">
                                    <h6 class="text-accent">Team 2</h6>
                                    <p class="mb-0"><strong><?= htmlspecialchars($match['team2_fullname']) ?></strong></p>
                                    <small class="text-light-50">@<?= htmlspecialchars($match['team2_name']) ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Screenshots Section -->
                        <?php if ($match['team1_screenshot'] || $match['team2_screenshot']): ?>
                        <div class="gaming-card mb-4">
                            <h6 class="text-accent mb-3">
                                <i class="fas fa-images"></i> Uploaded Screenshots
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-light">Team 1: <?= htmlspecialchars($match['team1_name']) ?></h6>
                                    <?php if ($match['team1_screenshot']): ?>
                                        <div class="screenshot-container mb-2">
                                            <img src="../<?= htmlspecialchars($match['team1_screenshot']) ?>" 
                                                 alt="Team 1 Screenshot" 
                                                 class="img-fluid rounded border" 
                                                 style="max-height: 300px; cursor: pointer;" 
                                                 onclick="openScreenshotModal(this.src, 'Team 1 Screenshot')">
                                        </div>
                                        <small class="text-light-50">
                                            <i class="fas fa-clock"></i> Uploaded: <?= formatDate($match['team1_upload_time']) ?>
                                        </small>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i> No screenshot uploaded
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-light">Team 2: <?= htmlspecialchars($match['team2_name']) ?></h6>
                                    <?php if ($match['team2_screenshot']): ?>
                                        <div class="screenshot-container mb-2">
                                            <img src="../<?= htmlspecialchars($match['team2_screenshot']) ?>" 
                                                 alt="Team 2 Screenshot" 
                                                 class="img-fluid rounded border" 
                                                 style="max-height: 300px; cursor: pointer;" 
                                                 onclick="openScreenshotModal(this.src, 'Team 2 Screenshot')">
                                        </div>
                                        <small class="text-light-50">
                                            <i class="fas fa-clock"></i> Uploaded: <?= formatDate($match['team2_upload_time']) ?>
                                        </small>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i> No screenshot uploaded
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Team 1 Score</label>
                                    <input type="number" class="form-control gaming-input" name="score1" 
                                           value="<?= $match['score1'] ?? 0 ?>" min="0" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Team 2 Score</label>
                                    <input type="number" class="form-control gaming-input" name="score2" 
                                           value="<?= $match['score2'] ?? 0 ?>" min="0" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Winner</label>
                                    <select class="form-control gaming-input" name="winner_id" required>
                                        <option value="">Select Winner</option>
                                        <option value="<?= $match['team1_id'] ?>" <?= $match['winner_id'] == $match['team1_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($match['team1_name']) ?>
                                        </option>
                                        <option value="<?= $match['team2_id'] ?>" <?= $match['winner_id'] == $match['team2_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($match['team2_name']) ?>
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Match Status</label>
                                    <select class="form-control gaming-input" name="status" required>
                                        <option value="completed" <?= $match['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="pending_review" <?= $match['status'] === 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Match Notes (Optional)</label>
                                <textarea class="form-control gaming-input" name="notes" rows="3" 
                                          placeholder="Any additional notes about the match result"><?= htmlspecialchars($match['notes'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6 class="text-info"><i class="fas fa-info-circle"></i> Important Note:</h6>
                                <p class="mb-0">This sets the winner for this <strong>individual match</strong> only. 
                                To set the overall tournament winners (Champion, Runner-up, 3rd Place), go to 
                                <a href="tournament_results.php" class="text-accent">Tournament Results</a> page.</p>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-accent">
                                    <i class="fas fa-save"></i> Update Match Result
                                </button>
                                <a href="results.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to List
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Screenshot Modal -->
    <div class="modal fade" id="screenshotModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-light" id="screenshotModalLabel">Screenshot</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="screenshotModalImg" src="" alt="Screenshot" class="img-fluid rounded">
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function openScreenshotModal(src, title) {
            document.getElementById('screenshotModalImg').src = src;
            document.getElementById('screenshotModalLabel').textContent = title;
            new bootstrap.Modal(document.getElementById('screenshotModal')).show();
        }
    </script>
</body>
</html>