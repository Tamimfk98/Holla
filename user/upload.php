<?php
require_once '../config/config.php';
requireLogin();

$matchId = $_GET['match_id'] ?? null;
$error = '';
$success = '';

// Handle screenshot upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $selectedMatchId = (int)($_POST['match_id'] ?? 0);
        
        if (!$selectedMatchId) {
            $error = 'Please select a match';
        } elseif (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please select a valid screenshot file';
        } else {
            try {
                // Verify user is participant in this match
                $stmt = $pdo->prepare("
                    SELECT * FROM matches 
                    WHERE id = ? AND (team1_id = ? OR team2_id = ?) 
                    AND status IN ('live', 'active')
                ");
                $stmt->execute([$selectedMatchId, $_SESSION['user_id'], $_SESSION['user_id']]);
                $match = $stmt->fetch();
                
                if (!$match) {
                    $error = 'Match not found or you are not a participant, or match is not active';
                } else {
                    // Check if screenshot already uploaded
                    $stmt = $pdo->prepare("
                        SELECT id FROM match_screenshots 
                        WHERE match_id = ? AND team_id = ?
                    ");
                    $stmt->execute([$selectedMatchId, $_SESSION['user_id']]);
                    
                    if ($stmt->rowCount() > 0) {
                        $error = 'You have already uploaded a screenshot for this match';
                    } else {
                        // Upload screenshot
                        $uploadResult = uploadFile($_FILES['screenshot'], 'screenshots/');
                        
                        if ($uploadResult['success']) {
                            // Save screenshot record
                            $stmt = $pdo->prepare("
                                INSERT INTO match_screenshots (match_id, team_id, screenshot_url, uploaded_at)
                                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                            ");
                            $stmt->execute([$selectedMatchId, $_SESSION['user_id'], $uploadResult['filename']]);
                            
                            $success = 'Screenshot uploaded successfully!';
                            
                            // Check if both teams have uploaded screenshots
                            $stmt = $pdo->prepare("
                                SELECT COUNT(*) as upload_count
                                FROM match_screenshots 
                                WHERE match_id = ?
                            ");
                            $stmt->execute([$selectedMatchId]);
                            $uploadCount = $stmt->fetchColumn();
                            
                            if ($uploadCount >= 2) {
                                // Both teams uploaded, match ready for review
                                $stmt = $pdo->prepare("
                                    UPDATE matches 
                                    SET status = 'pending_review' 
                                    WHERE id = ? AND status = 'live'
                                ");
                                $stmt->execute([$selectedMatchId]);
                            }
                        } else {
                            $error = $uploadResult['message'];
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get user's active matches for dropdown
$stmt = $pdo->prepare("
    SELECT m.id, m.scheduled_date, t.name as tournament_name, t.game_type,
           CASE 
               WHEN m.team1_id = ? THEN u2.username 
               ELSE u1.username 
           END as opponent_name,
           ms.id as has_screenshot
    FROM matches m
    JOIN tournaments t ON m.tournament_id = t.id
    JOIN users u1 ON m.team1_id = u1.id
    JOIN users u2 ON m.team2_id = u2.id
    LEFT JOIN match_screenshots ms ON m.id = ms.match_id AND ms.team_id = ?
    WHERE (m.team1_id = ? OR m.team2_id = ?) 
    AND m.status IN ('live', 'active', 'scheduled')
    AND ms.id IS NULL
    ORDER BY m.scheduled_date ASC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$availableMatches = $stmt->fetchAll();

// Get match details if match_id is provided
$selectedMatch = null;
if ($matchId) {
    $stmt = $pdo->prepare("
        SELECT m.*, t.name as tournament_name, t.game_type,
               u1.username as team1_name, u2.username as team2_name,
               CASE 
                   WHEN m.team1_id = ? THEN u2.username 
                   ELSE u1.username 
               END as opponent_name
        FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        JOIN users u1 ON m.team1_id = u1.id
        JOIN users u2 ON m.team2_id = u2.id
        WHERE m.id = ? AND (m.team1_id = ? OR m.team2_id = ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $matchId, $_SESSION['user_id'], $_SESSION['user_id']]);
    $selectedMatch = $stmt->fetch();
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
    <title>Upload Screenshot - eSports Tournament</title>
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
                <i class="fas fa-upload text-accent"></i> Upload Match Screenshot
            </h2>
            <a href="matches.php" class="btn btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Matches
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
                <div class="mt-2">
                    <a href="matches.php" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-gamepad"></i> View My Matches
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($availableMatches) && !$selectedMatch): ?>
            <div class="gaming-card">
                <div class="text-center text-light-50 py-5">
                    <i class="fas fa-camera fa-4x mb-3"></i>
                    <h4>No Active Matches</h4>
                    <p>You don't have any active matches that require screenshot uploads</p>
                    <a href="matches.php" class="btn btn-accent">
                        <i class="fas fa-gamepad"></i> View All Matches
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="gaming-card">
                        <h4 class="text-accent mb-4">
                            <i class="fas fa-camera"></i> Upload Match Result Screenshot
                        </h4>
                        
                        <div class="alert alert-info mb-4">
                            <h6 class="text-info"><i class="fas fa-info-circle"></i> Screenshot Guidelines:</h6>
                            <ul class="mb-0">
                                <li>Upload a clear screenshot showing the match result</li>
                                <li>Screenshot should show final scores/standings</li>
                                <li>Maximum file size: 5MB</li>
                                <li>Supported formats: JPG, PNG, GIF, WEBP</li>
                                <li>Both teams must upload screenshots for match verification</li>
                            </ul>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-3">
                                <label for="match_id" class="form-label text-light">Select Match *</label>
                                <select class="form-control gaming-input" id="match_id" name="match_id" required onchange="updateMatchDetails()">
                                    <option value="">Choose a match to upload screenshot for</option>
                                    <?php foreach ($availableMatches as $match): ?>
                                        <option value="<?= $match['id'] ?>" 
                                                <?= $matchId == $match['id'] ? 'selected' : '' ?>
                                                data-tournament="<?= htmlspecialchars($match['tournament_name']) ?>"
                                                data-game="<?= htmlspecialchars($match['game_type']) ?>"
                                                data-opponent="<?= htmlspecialchars($match['opponent_name']) ?>"
                                                data-date="<?= formatDate($match['scheduled_date']) ?>">
                                            <?= htmlspecialchars($match['tournament_name']) ?> - vs <?= htmlspecialchars($match['opponent_name']) ?>
                                            <?php if ($match['scheduled_date']): ?>
                                                (<?= formatDate($match['scheduled_date']) ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="screenshot" class="form-label text-light">Screenshot File *</label>
                                <input type="file" class="form-control gaming-input" id="screenshot" name="screenshot" 
                                       accept="image/*" required onchange="previewImage(this)">
                                <small class="text-light-50">Maximum 5MB, supported formats: JPG, PNG, GIF, WEBP</small>
                            </div>
                            
                            <!-- Image Preview -->
                            <div id="imagePreview" class="mb-4" style="display: none;">
                                <label class="form-label text-light">Preview:</label>
                                <div class="screenshot-preview">
                                    <img id="previewImg" class="img-fluid rounded" style="max-height: 300px;">
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-accent" id="submitBtn">
                                    <i class="fas fa-upload"></i> Upload Screenshot
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="gaming-card" id="matchDetails" style="display: none;">
                        <h5 class="text-accent mb-3">Match Details</h5>
                        <table class="table table-dark table-sm">
                            <tr>
                                <th>Tournament:</th>
                                <td id="detailTournament">-</td>
                            </tr>
                            <tr>
                                <th>Game:</th>
                                <td id="detailGame">-</td>
                            </tr>
                            <tr>
                                <th>Opponent:</th>
                                <td id="detailOpponent">-</td>
                            </tr>
                            <tr>
                                <th>Scheduled:</th>
                                <td id="detailDate">-</td>
                            </tr>
                        </table>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle"></i>
                            Make sure your screenshot clearly shows the match result before uploading.
                        </div>
                    </div>
                    
                    <!-- Upload History -->
                    <div class="gaming-card mt-4">
                        <h5 class="text-accent mb-3">Recent Uploads</h5>
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT ms.*, t.name as tournament_name, 
                                   CASE 
                                       WHEN m.team1_id = ? THEN u2.username 
                                       ELSE u1.username 
                                   END as opponent_name
                            FROM match_screenshots ms
                            JOIN matches m ON ms.match_id = m.id
                            JOIN tournaments t ON m.tournament_id = t.id
                            JOIN users u1 ON m.team1_id = u1.id
                            JOIN users u2 ON m.team2_id = u2.id
                            WHERE ms.team_id = ?
                            ORDER BY ms.uploaded_at DESC
                            LIMIT 5
                        ");
                        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
                        $recentUploads = $stmt->fetchAll();
                        ?>
                        
                        <?php if (empty($recentUploads)): ?>
                            <div class="text-center text-light-50 py-3">
                                <i class="fas fa-camera fa-2x mb-2"></i>
                                <p class="small mb-0">No uploads yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentUploads as $upload): ?>
                                <div class="upload-item mb-2 pb-2 border-bottom border-secondary">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-light"><?= htmlspecialchars($upload['tournament_name']) ?></small>
                                            <br><small class="text-light-50">vs <?= htmlspecialchars($upload['opponent_name']) ?></small>
                                        </div>
                                        <small class="text-success">
                                            <i class="fas fa-check-circle"></i> 
                                            <?= timeAgo($upload['uploaded_at']) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function updateMatchDetails() {
            const select = document.getElementById('match_id');
            const details = document.getElementById('matchDetails');
            const option = select.options[select.selectedIndex];
            
            if (option.value) {
                document.getElementById('detailTournament').textContent = option.dataset.tournament || '-';
                document.getElementById('detailGame').textContent = option.dataset.game || '-';
                document.getElementById('detailOpponent').textContent = option.dataset.opponent || '-';
                document.getElementById('detailDate').textContent = option.dataset.date || '-';
                details.style.display = 'block';
            } else {
                details.style.display = 'none';
            }
        }
        
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const img = document.getElementById('previewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Initialize if match is pre-selected
        document.addEventListener('DOMContentLoaded', function() {
            updateMatchDetails();
        });
        
        // Form submission handling
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>
