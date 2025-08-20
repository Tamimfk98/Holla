<?php
require_once '../config/config.php';
requireAdminLogin();

$tournamentId = $_GET['id'] ?? null;
$error = '';
$success = '';

if (!$tournamentId) {
    redirect('tournaments.php', 'Tournament not found', 'error');
}

// Get tournament details
$stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->execute([$tournamentId]);
$tournament = $stmt->fetch();

if (!$tournament) {
    redirect('tournaments.php', 'Tournament not found', 'error');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $winnerId = (int)($_POST['winner_id'] ?? 0);
        $runnerUpId = (int)($_POST['runner_up_id'] ?? 0);
        $thirdPlaceId = (int)($_POST['third_place_id'] ?? 0);
        $winnerPrize = (float)($_POST['winner_prize'] ?? 0);
        $runnerUpPrize = (float)($_POST['runner_up_prize'] ?? 0);
        $thirdPlacePrize = (float)($_POST['third_place_prize'] ?? 0);
        
        if ($winnerId) {
            try {
                $pdo->beginTransaction();
                
                // Update tournament with results (runner-up and 3rd place are optional)
                $stmt = $pdo->prepare("
                    UPDATE tournaments 
                    SET winner_id = ?, runner_up_id = ?, third_place_id = ?, 
                        winner_prize = ?, runner_up_prize = ?, third_place_prize = ?,
                        status = 'completed', updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$winnerId, $runnerUpId ?: null, $thirdPlaceId ?: null, $winnerPrize, $runnerUpPrize, $thirdPlacePrize, $tournamentId]);
                
                // Add prize money to winner's wallet
                if ($winnerPrize > 0) {
                    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $stmt->execute([$winnerPrize, $winnerId]);
                    
                    // Create notification for winner
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$winnerId, 'Congratulations! You Won!', 
                        "You won the tournament '{$tournament['name']}' and earned $" . number_format($winnerPrize, 2) . "! The amount has been added to your wallet.", 'success']);
                }
                
                // Add prize money to runner-up's wallet (only if runner-up selected)
                if ($runnerUpId && $runnerUpPrize > 0) {
                    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $stmt->execute([$runnerUpPrize, $runnerUpId]);
                    
                    // Create notification for runner-up
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$runnerUpId, 'Great Job! You\'re Runner-up!', 
                        "You came 2nd in the tournament '{$tournament['name']}' and earned $" . number_format($runnerUpPrize, 2) . "! The amount has been added to your wallet.", 'success']);
                }
                
                // Add prize money to third place's wallet (only if third place selected)
                if ($thirdPlaceId && $thirdPlacePrize > 0) {
                    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $stmt->execute([$thirdPlacePrize, $thirdPlaceId]);
                    
                    // Create notification for third place
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$thirdPlaceId, 'Excellent Performance! Third Place!', 
                        "You came 3rd in the tournament '{$tournament['name']}' and earned $" . number_format($thirdPlacePrize, 2) . "! The amount has been added to your wallet.", 'success']);
                }
                
                $pdo->commit();
                $success = 'Tournament results published successfully! Prize money has been distributed.';
                
                // Refresh tournament data
                $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
                $stmt->execute([$tournamentId]);
                $tournament = $stmt->fetch();
                
            } catch (PDOException $e) {
                $pdo->rollback();
                $error = 'Database error: ' . $e->getMessage();
            }
        } else {
            $error = 'Please select at least the tournament champion (1st place)';
        }
    }
}

// Get registered users for this tournament with match statistics
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.full_name, tr.team_name,
           COUNT(CASE WHEN m.winner_id = u.id THEN 1 END) as matches_won,
           COUNT(CASE WHEN (m.team1_id = u.id OR m.team2_id = u.id) AND m.status = 'completed' THEN 1 END) as total_matches,
           COALESCE(SUM(CASE WHEN m.team1_id = u.id THEN m.score1 WHEN m.team2_id = u.id THEN m.score2 END), 0) as total_score
    FROM users u 
    JOIN tournament_registrations tr ON u.id = tr.user_id 
    LEFT JOIN matches m ON (m.team1_id = u.id OR m.team2_id = u.id) AND m.tournament_id = ?
    WHERE tr.tournament_id = ? AND tr.status = 'approved'
    GROUP BY u.id, u.username, u.full_name, tr.team_name
    ORDER BY matches_won DESC, total_score DESC, u.username
");
$stmt->execute([$tournamentId, $tournamentId]);
$registeredUsers = $stmt->fetchAll();

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
    <title>Tournament Results - <?= htmlspecialchars($tournament['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-page">
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="text-accent">
                        <i class="fas fa-trophy"></i> Tournament Results
                    </h1>
                    <a href="tournaments.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Tournaments
                    </a>
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
                
                <div class="row">
                    <div class="col-lg-6">
                        <div class="gaming-card">
                            <h4 class="text-accent mb-3">
                                <i class="fas fa-info-circle"></i> Tournament Details
                            </h4>
                            <div class="mb-2"><strong>Name:</strong> <?= htmlspecialchars($tournament['name']) ?></div>
                            <div class="mb-2"><strong>Game:</strong> <?= htmlspecialchars($tournament['game_type']) ?></div>
                            <div class="mb-2"><strong>Status:</strong> 
                                <span class="badge bg-<?= $tournament['status'] === 'completed' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($tournament['status']) ?>
                                </span>
                            </div>
                            <div class="mb-2"><strong>Prize Pool:</strong> $<?= number_format($tournament['prize_pool'], 2) ?></div>
                            <div class="mb-2"><strong>Registered Teams:</strong> <?= count($registeredUsers) ?></div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <?php if ($tournament['status'] === 'completed'): ?>
                            <div class="gaming-card">
                                <h4 class="text-accent mb-3">
                                    <i class="fas fa-crown"></i> Final Results
                                </h4>
                                <?php 
                                // Get winner details
                                if ($tournament['winner_id']) {
                                    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
                                    $stmt->execute([$tournament['winner_id']]);
                                    $winner = $stmt->fetch();
                                }
                                if ($tournament['runner_up_id']) {
                                    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
                                    $stmt->execute([$tournament['runner_up_id']]);
                                    $runnerUp = $stmt->fetch();
                                }
                                if ($tournament['third_place_id']) {
                                    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
                                    $stmt->execute([$tournament['third_place_id']]);
                                    $thirdPlace = $stmt->fetch();
                                }
                                ?>
                                
                                <div class="mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-trophy text-warning me-2" style="font-size: 1.5rem;"></i>
                                        <div>
                                            <strong>Winner:</strong> <?= isset($winner) ? htmlspecialchars($winner['full_name']) . ' (@' . htmlspecialchars($winner['username']) . ')' : 'Not set' ?><br>
                                            <small class="text-success">Prize: $<?= number_format($tournament['winner_prize'], 2) ?></small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-medal text-info me-2" style="font-size: 1.5rem;"></i>
                                        <div>
                                            <strong>Runner-up:</strong> <?= isset($runnerUp) ? htmlspecialchars($runnerUp['full_name']) . ' (@' . htmlspecialchars($runnerUp['username']) . ')' : 'Not set' ?><br>
                                            <small class="text-success">Prize: $<?= number_format($tournament['runner_up_prize'], 2) ?></small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-award text-secondary me-2" style="font-size: 1.5rem;"></i>
                                        <div>
                                            <strong>Third Place:</strong> <?= isset($thirdPlace) ? htmlspecialchars($thirdPlace['full_name']) . ' (@' . htmlspecialchars($thirdPlace['username']) . ')' : 'Not set' ?><br>
                                            <small class="text-success">Prize: $<?= number_format($tournament['third_place_prize'], 2) ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($tournament['status'] !== 'completed'): ?>
                <div class="gaming-card mt-4">
                    <h4 class="text-accent mb-3">
                        <i class="fas fa-clipboard-list"></i> Publish Final Tournament Results
                    </h4>
                    
                    <div class="alert alert-warning mb-4">
                        <h6 class="text-warning"><i class="fas fa-exclamation-triangle"></i> Important:</h6>
                        <p class="mb-2">This page is for selecting the <strong>overall tournament winners</strong> - the final tournament ranking.</p>
                        <p class="mb-2"><strong>Champion (1st place) is required.</strong> Runner-up and 3rd place are optional - you can leave them blank if you only want to award the champion.</p>
                        <p class="mb-0">For individual match results and screenshot reviews, use the <a href="results.php" class="text-accent">Match Results</a> page.</p>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="winner_id" class="form-label text-light">
                                        <i class="fas fa-trophy text-warning"></i> Champion (1st Place) *
                                    </label>
                                    <select class="form-select gaming-input" id="winner_id" name="winner_id" required>
                                        <option value="">Select Champion</option>
                                        <?php foreach ($registeredUsers as $user): ?>
                                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?> (@<?= htmlspecialchars($user['username']) ?>) - <?= htmlspecialchars($user['team_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="winner_prize" class="form-label text-light">
                                        <i class="fas fa-coins"></i> Champion Prize Amount ($)
                                    </label>
                                    <input type="number" class="form-control gaming-input" id="winner_prize" 
                                           name="winner_prize" step="0.01" min="0" 
                                           value="<?= $tournament['winner_prize'] ?: '' ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="runner_up_id" class="form-label text-light">
                                        <i class="fas fa-medal text-info"></i> Runner-up (2nd Place) <small class="text-light-50">(Optional)</small>
                                    </label>
                                    <select class="form-select gaming-input" id="runner_up_id" name="runner_up_id">
                                        <option value="">Select Runner-up (Optional)</option>
                                        <?php foreach ($registeredUsers as $user): ?>
                                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?> (@<?= htmlspecialchars($user['username']) ?>) - <?= htmlspecialchars($user['team_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="runner_up_prize" class="form-label text-light">
                                        <i class="fas fa-coins"></i> Runner-up Prize Amount ($)
                                    </label>
                                    <input type="number" class="form-control gaming-input" id="runner_up_prize" 
                                           name="runner_up_prize" step="0.01" min="0" 
                                           value="<?= $tournament['runner_up_prize'] ?: '' ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="third_place_id" class="form-label text-light">
                                        <i class="fas fa-award text-secondary"></i> Third Place <small class="text-light-50">(Optional)</small>
                                    </label>
                                    <select class="form-select gaming-input" id="third_place_id" name="third_place_id">
                                        <option value="">Select Third Place (Optional)</option>
                                        <?php foreach ($registeredUsers as $user): ?>
                                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?> (@<?= htmlspecialchars($user['username']) ?>) - <?= htmlspecialchars($user['team_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="third_place_prize" class="form-label text-light">
                                        <i class="fas fa-coins"></i> Third Place Prize Amount ($)
                                    </label>
                                    <input type="number" class="form-control gaming-input" id="third_place_prize" 
                                           name="third_place_prize" step="0.01" min="0" 
                                           value="<?= $tournament['third_place_prize'] ?: '' ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-accent btn-lg">
                                <i class="fas fa-trophy"></i> Publish Results & Distribute Prizes
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- Tournament Performance Graph -->
                <div class="gaming-card mt-4">
                    <h4 class="text-accent mb-3">
                        <i class="fas fa-chart-bar"></i> Tournament Performance Graph
                    </h4>
                    
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Player</th>
                                    <th>Team Name</th>
                                    <th>Matches Won</th>
                                    <th>Total Matches</th>
                                    <th>Win Rate</th>
                                    <th>Total Score</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registeredUsers as $index => $user): ?>
                                    <?php 
                                    $winRate = $user['total_matches'] > 0 ? ($user['matches_won'] / $user['total_matches']) * 100 : 0;
                                    $isWinner = isset($tournament['winner_id']) && $tournament['winner_id'] == $user['id'];
                                    $isRunnerUp = isset($tournament['runner_up_id']) && $tournament['runner_up_id'] == $user['id'];
                                    $isThirdPlace = isset($tournament['third_place_id']) && $tournament['third_place_id'] == $user['id'];
                                    ?>
                                    <tr class="<?= $isWinner ? 'table-warning' : ($isRunnerUp ? 'table-info' : ($isThirdPlace ? 'table-secondary' : '')) ?>">
                                        <td>
                                            <span class="badge bg-<?= $isWinner ? 'warning' : ($isRunnerUp ? 'info' : ($isThirdPlace ? 'secondary' : 'dark')) ?>">
                                                #<?= $index + 1 ?>
                                            </span>
                                            <?php if ($isWinner): ?>
                                                <i class="fas fa-crown text-warning ms-1"></i>
                                            <?php elseif ($isRunnerUp): ?>
                                                <i class="fas fa-medal text-info ms-1"></i>
                                            <?php elseif ($isThirdPlace): ?>
                                                <i class="fas fa-award text-secondary ms-1"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($user['full_name']) ?></strong><br>
                                            <small class="text-light-50">@<?= htmlspecialchars($user['username']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($user['team_name']) ?></td>
                                        <td>
                                            <span class="badge bg-success"><?= $user['matches_won'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $user['total_matches'] ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-fill me-2" style="height: 20px;">
                                                    <div class="progress-bar bg-<?= $winRate >= 70 ? 'success' : ($winRate >= 40 ? 'warning' : 'danger') ?>" 
                                                         style="width: <?= $winRate ?>%"></div>
                                                </div>
                                                <small><?= round($winRate, 1) ?>%</small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning"><?= $user['total_score'] ?></span>
                                        </td>
                                        <td>
                                            <?php if ($winRate >= 80): ?>
                                                <span class="badge bg-success">Excellent</span>
                                            <?php elseif ($winRate >= 60): ?>
                                                <span class="badge bg-warning">Good</span>
                                            <?php elseif ($winRate >= 40): ?>
                                                <span class="badge bg-info">Average</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Needs Improvement</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (empty($registeredUsers)): ?>
                        <div class="text-center text-light-50 py-4">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <h5>No Registered Players</h5>
                            <p>No players have registered for this tournament yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent selecting the same user for multiple positions
        document.querySelectorAll('select[name$="_id"]').forEach(select => {
            select.addEventListener('change', function() {
                const selectedValues = Array.from(document.querySelectorAll('select[name$="_id"]'))
                    .map(s => s.value)
                    .filter(v => v !== '');
                
                document.querySelectorAll('select[name$="_id"]').forEach(otherSelect => {
                    if (otherSelect !== this) {
                        Array.from(otherSelect.options).forEach(option => {
                            if (option.value && selectedValues.includes(option.value) && option.value !== otherSelect.value) {
                                option.disabled = true;
                            } else {
                                option.disabled = false;
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>