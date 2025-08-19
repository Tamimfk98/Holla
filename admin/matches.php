<?php
require_once '../config/config.php';
requireAdminLogin();

$action = $_GET['action'] ?? 'list';
$matchId = $_GET['id'] ?? null;
$tournamentId = $_GET['tournament_id'] ?? null;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        switch ($action) {
            case 'create':
                $tournament = (int)($_POST['tournament_id'] ?? 0);
                $team1 = (int)($_POST['team1_id'] ?? 0);
                $team2 = (int)($_POST['team2_id'] ?? 0);
                $scheduledDate = $_POST['scheduled_date'] ?? '';
                $round = sanitizeInput($_POST['round'] ?? '');
                
                if ($tournament && $team1 && $team2 && $team1 !== $team2) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO matches (tournament_id, team1_id, team2_id, scheduled_date, round, status, created_at)
                            VALUES (?, ?, ?, ?, ?, 'scheduled', NOW())
                        ");
                        $stmt->execute([$tournament, $team1, $team2, $scheduledDate, $round]);
                        $success = 'Match created successfully';
                        $action = 'list';
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Please fill all required fields and select different teams';
                }
                break;
                
            case 'update_result':
                $winnerId = (int)($_POST['winner_id'] ?? 0);
                $score1 = (int)($_POST['score1'] ?? 0);
                $score2 = (int)($_POST['score2'] ?? 0);
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                if ($matchId && $winnerId) {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE matches 
                            SET winner_id = ?, score1 = ?, score2 = ?, notes = ?, status = 'completed', completed_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$winnerId, $score1, $score2, $notes, $matchId]);
                        $success = 'Match result updated successfully';
                        $action = 'list';
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Please select a winner';
                }
                break;
        }
    }
}

// Get matches based on action
if ($action === 'list') {
    $whereClause = '';
    $params = [];
    
    if ($tournamentId) {
        $whereClause = 'WHERE m.tournament_id = ?';
        $params[] = $tournamentId;
    }
    
    $stmt = $pdo->prepare("
        SELECT m.*, t.name as tournament_name,
               u1.username as team1_name, u2.username as team2_name,
               uw.username as winner_name
        FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        JOIN users u1 ON m.team1_id = u1.id
        JOIN users u2 ON m.team2_id = u2.id
        LEFT JOIN users uw ON m.winner_id = uw.id
        $whereClause
        ORDER BY m.scheduled_date DESC, m.created_at DESC
    ");
    $stmt->execute($params);
    $matches = $stmt->fetchAll();
}

// Get tournament options for create form
if ($action === 'create') {
    $stmt = $pdo->query("SELECT id, name FROM tournaments WHERE status IN ('active', 'upcoming') ORDER BY name");
    $tournaments = $stmt->fetchAll();
}

// Get match details for result update
if ($action === 'result' && $matchId) {
    $stmt = $pdo->prepare("
        SELECT m.*, t.name as tournament_name,
               u1.username as team1_name, u2.username as team2_name
        FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        JOIN users u1 ON m.team1_id = u1.id
        JOIN users u2 ON m.team2_id = u2.id
        WHERE m.id = ?
    ");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    
    if (!$match) {
        $error = 'Match not found';
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
    <title>Match Management - eSports Tournament</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <div class="col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-light">
                        <i class="fas fa-gamepad text-accent"></i> Match Management
                        <?php if ($tournamentId): ?>
                            <small class="text-light-50">- Tournament Filter Active</small>
                        <?php endif; ?>
                    </h2>
                    <div>
                        <?php if ($action === 'list'): ?>
                            <a href="?action=create<?= $tournamentId ? '&tournament_id=' . $tournamentId : '' ?>" class="btn btn-accent">
                                <i class="fas fa-plus"></i> Create Match
                            </a>
                            <?php if ($tournamentId): ?>
                                <a href="?" class="btn btn-outline-light ms-2">
                                    <i class="fas fa-times"></i> Clear Filter
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="?<?= $tournamentId ? 'tournament_id=' . $tournamentId : '' ?>" class="btn btn-outline-light">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                        <?php endif; ?>
                    </div>
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
                    <!-- Match List -->
                    <div class="gaming-card">
                        <?php if (empty($matches)): ?>
                            <div class="text-center text-light-50 py-5">
                                <i class="fas fa-gamepad fa-4x mb-3"></i>
                                <h4>No Matches Found</h4>
                                <p>Create your first match to get started</p>
                                <a href="?action=create<?= $tournamentId ? '&tournament_id=' . $tournamentId : '' ?>" class="btn btn-accent">
                                    <i class="fas fa-plus"></i> Create Match
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tournament</th>
                                            <th>Teams</th>
                                            <th>Scheduled</th>
                                            <th>Round</th>
                                            <th>Status</th>
                                            <th>Result</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($matches as $match): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($match['tournament_name']) ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge bg-info me-2"><?= htmlspecialchars($match['team1_name']) ?></span>
                                                        <i class="fas fa-vs text-accent mx-2"></i>
                                                        <span class="badge bg-warning"><?= htmlspecialchars($match['team2_name']) ?></span>
                                                    </div>
                                                </td>
                                                <td><?= formatDate($match['scheduled_date']) ?></td>
                                                <td><?= htmlspecialchars($match['round']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $match['status'] === 'completed' ? 'success' : ($match['status'] === 'live' ? 'danger' : 'info') ?>">
                                                        <?= ucfirst($match['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($match['status'] === 'completed'): ?>
                                                        <span class="text-success fw-bold">
                                                            <?= htmlspecialchars($match['winner_name']) ?> Wins
                                                        </span>
                                                        <br><small class="text-light-50"><?= $match['score1'] ?> - <?= $match['score2'] ?></small>
                                                    <?php else: ?>
                                                        <span class="text-light-50">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <?php if ($match['status'] !== 'completed'): ?>
                                                            <a href="?action=result&id=<?= $match['id'] ?>" class="btn btn-sm btn-success">
                                                                <i class="fas fa-trophy"></i> Result
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="?action=delete&id=<?= $match['id'] ?>" 
                                                           class="btn btn-sm btn-outline-danger"
                                                           onclick="return confirm('Are you sure you want to delete this match?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                <?php elseif ($action === 'create'): ?>
                    <!-- Create Match Form -->
                    <div class="gaming-card">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="tournament_id" class="form-label text-light">Tournament *</label>
                                    <select class="form-control gaming-input" id="tournament_id" name="tournament_id" required onchange="loadTeams(this.value)">
                                        <option value="">Select Tournament</option>
                                        <?php foreach ($tournaments as $tournament): ?>
                                            <option value="<?= $tournament['id'] ?>" 
                                                    <?= $tournamentId == $tournament['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($tournament['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="round" class="form-label text-light">Round</label>
                                    <input type="text" class="form-control gaming-input" id="round" name="round" 
                                           placeholder="e.g., Quarter Final, Semi Final">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="team1_id" class="form-label text-light">Team 1 *</label>
                                    <select class="form-control gaming-input" id="team1_id" name="team1_id" required>
                                        <option value="">Select Team 1</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="team2_id" class="form-label text-light">Team 2 *</label>
                                    <select class="form-control gaming-input" id="team2_id" name="team2_id" required>
                                        <option value="">Select Team 2</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="scheduled_date" class="form-label text-light">Scheduled Date</label>
                                <input type="datetime-local" class="form-control gaming-input" id="scheduled_date" name="scheduled_date">
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-accent">
                                    <i class="fas fa-save"></i> Create Match
                                </button>
                            </div>
                        </form>
                    </div>
                    
                <?php elseif ($action === 'result' && isset($match)): ?>
                    <!-- Update Result Form -->
                    <div class="gaming-card">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h4 class="text-accent">Update Match Result</h4>
                                <p class="text-light-50">
                                    <?= htmlspecialchars($match['tournament_name']) ?> - <?= htmlspecialchars($match['round']) ?>
                                </p>
                                <div class="d-flex align-items-center justify-content-center mb-3">
                                    <span class="badge bg-info fs-6 me-3"><?= htmlspecialchars($match['team1_name']) ?></span>
                                    <i class="fas fa-vs text-accent fa-2x mx-3"></i>
                                    <span class="badge bg-warning fs-6 ms-3"><?= htmlspecialchars($match['team2_name']) ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="winner_id" class="form-label text-light">Winner *</label>
                                    <select class="form-control gaming-input" id="winner_id" name="winner_id" required>
                                        <option value="">Select Winner</option>
                                        <option value="<?= $match['team1_id'] ?>"><?= htmlspecialchars($match['team1_name']) ?></option>
                                        <option value="<?= $match['team2_id'] ?>"><?= htmlspecialchars($match['team2_name']) ?></option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="score1" class="form-label text-light"><?= htmlspecialchars($match['team1_name']) ?> Score</label>
                                    <input type="number" class="form-control gaming-input" id="score1" name="score1" min="0">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="score2" class="form-label text-light"><?= htmlspecialchars($match['team2_name']) ?> Score</label>
                                    <input type="number" class="form-control gaming-input" id="score2" name="score2" min="0">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label text-light">Match Notes</label>
                                <textarea class="form-control gaming-input" id="notes" name="notes" rows="3" 
                                          placeholder="Any additional notes about the match result..."></textarea>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-trophy"></i> Update Result
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function loadTeams(tournamentId) {
            if (!tournamentId) {
                document.getElementById('team1_id').innerHTML = '<option value="">Select Team 1</option>';
                document.getElementById('team2_id').innerHTML = '<option value="">Select Team 2</option>';
                return;
            }
            
            fetch(`../api/get_teams.php?tournament_id=${tournamentId}`)
                .then(response => response.json())
                .then(data => {
                    const team1Select = document.getElementById('team1_id');
                    const team2Select = document.getElementById('team2_id');
                    
                    team1Select.innerHTML = '<option value="">Select Team 1</option>';
                    team2Select.innerHTML = '<option value="">Select Team 2</option>';
                    
                    data.teams.forEach(team => {
                        team1Select.innerHTML += `<option value="${team.id}">${team.name}</option>`;
                        team2Select.innerHTML += `<option value="${team.id}">${team.name}</option>`;
                    });
                })
                .catch(error => console.error('Error loading teams:', error));
        }
        
        // Load teams if tournament is pre-selected
        document.addEventListener('DOMContentLoaded', function() {
            const tournamentSelect = document.getElementById('tournament_id');
            if (tournamentSelect && tournamentSelect.value) {
                loadTeams(tournamentSelect.value);
            }
        });
    </script>
</body>
</html>
