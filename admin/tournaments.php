<?php
require_once '../config/config.php';
requireAdminLogin();

$action = $_GET['action'] ?? 'list';
$tournamentId = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        switch ($action) {
            case 'create':
            case 'edit':
                $name = sanitizeInput($_POST['name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $gameType = sanitizeInput($_POST['game_type'] ?? '');
                $maxTeams = (int)($_POST['max_teams'] ?? 0);
                $entryFee = (float)($_POST['entry_fee'] ?? 0);
                $prizePool = (float)($_POST['prize_pool'] ?? 0);
                $startDate = $_POST['start_date'] ?? '';
                $endDate = $_POST['end_date'] ?? '';
                $status = sanitizeInput($_POST['status'] ?? 'upcoming');
                $thumbnailPath = '';
                
                // Handle thumbnail upload
                if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../assets/images/tournaments/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileInfo = pathinfo($_FILES['thumbnail']['name']);
                    $fileName = 'tournament_' . time() . '_' . uniqid() . '.' . $fileInfo['extension'];
                    $uploadPath = $uploadDir . $fileName;
                    $thumbnailPath = 'assets/images/tournaments/' . $fileName;
                    
                    // Validate file
                    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (in_array(strtolower($fileInfo['extension']), $allowedTypes) && $_FILES['thumbnail']['size'] <= 5242880) {
                        if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadPath)) {
                            // File uploaded successfully
                        } else {
                            $error = 'Failed to upload thumbnail';
                        }
                    } else {
                        $error = 'Invalid file type or size too large (max 5MB)';
                    }
                }
                
                if (empty($name) || empty($gameType) || $maxTeams <= 0) {
                    $error = 'Please fill all required fields';
                } else {
                    try {
                        if ($action === 'create') {
                            $stmt = $pdo->prepare("
                                INSERT INTO tournaments (name, description, game_type, max_teams, 
                                                       entry_fee, prize_pool, start_date, end_date, status, thumbnail, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                            ");
                            $stmt->execute([$name, $description, $gameType, $maxTeams, $entryFee, $prizePool, $startDate, $endDate, $status, $thumbnailPath]);
                            $success = 'Tournament created successfully';
                        } else {
                            if ($thumbnailPath) {
                                $stmt = $pdo->prepare("
                                    UPDATE tournaments 
                                    SET name = ?, description = ?, game_type = ?, max_teams = ?, 
                                        entry_fee = ?, prize_pool = ?, start_date = ?, end_date = ?, status = ?, thumbnail = ?
                                    WHERE id = ?
                                ");
                                $stmt->execute([$name, $description, $gameType, $maxTeams, $entryFee, $prizePool, $startDate, $endDate, $status, $thumbnailPath, $tournamentId]);
                            } else {
                                $stmt = $pdo->prepare("
                                    UPDATE tournaments 
                                    SET name = ?, description = ?, game_type = ?, max_teams = ?, 
                                        entry_fee = ?, prize_pool = ?, start_date = ?, end_date = ?, status = ?
                                    WHERE id = ?
                                ");
                                $stmt->execute([$name, $description, $gameType, $maxTeams, $entryFee, $prizePool, $startDate, $endDate, $status, $tournamentId]);
                            }
                            $success = 'Tournament updated successfully';
                        }
                        $action = 'list';
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete':
                if ($tournamentId) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM tournaments WHERE id = ?");
                        $stmt->execute([$tournamentId]);
                        $success = 'Tournament deleted successfully';
                        $action = 'list';
                    } catch (PDOException $e) {
                        $error = 'Cannot delete tournament: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get tournament data for editing
$tournament = null;
if (($action === 'edit' || $action === 'view') && $tournamentId) {
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$tournamentId]);
    $tournament = $stmt->fetch();
    if (!$tournament) {
        $error = 'Tournament not found';
        $action = 'list';
    }
}

// Get tournaments list
if ($action === 'list') {
    $stmt = $pdo->query("
        SELECT t.*, 
               COUNT(tr.id) as registered_teams,
               (SELECT COUNT(*) FROM matches WHERE tournament_id = t.id) as total_matches
        FROM tournaments t
        LEFT JOIN tournament_registrations tr ON t.id = tr.tournament_id AND tr.status = 'approved'
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
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
    <title>Tournament Management - eSports Tournament</title>
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
                        <i class="fas fa-trophy text-accent"></i> Tournament Management
                    </h2>
                    <?php if ($action === 'list'): ?>
                        <a href="?action=create" class="btn btn-accent">
                            <i class="fas fa-plus"></i> Create Tournament
                        </a>
                    <?php else: ?>
                        <a href="?" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left"></i> Back to List
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
                    <!-- Tournament List -->
                    <div class="gaming-card">
                        <?php if (empty($tournaments)): ?>
                            <div class="text-center text-light-50 py-5">
                                <i class="fas fa-trophy fa-4x mb-3"></i>
                                <h4>No Tournaments Yet</h4>
                                <p>Create your first tournament to get started</p>
                                <a href="?action=create" class="btn btn-accent">
                                    <i class="fas fa-plus"></i> Create Tournament
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover">
                                    <thead>
                                        <tr>
                                            <th>Thumbnail</th>
                                            <th>Name</th>
                                            <th>Game</th>
                                            <th>Teams</th>
                                            <th>Entry Fee</th>
                                            <th>Prize Pool</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tournaments as $t): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($t['thumbnail'])): ?>
                                                        <img src="../<?= htmlspecialchars($t['thumbnail']) ?>" alt="Tournament thumbnail" 
                                                             class="img-thumbnail" style="max-width: 60px; max-height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <span class="text-light-50"><i class="fas fa-image"></i> No image</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($t['name']) ?></strong>
                                                    <br><small class="text-light-50"><?= formatDate($t['start_date']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($t['game_type']) ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?= $t['registered_teams'] ?>/<?= $t['max_teams'] ?></span>
                                                </td>
                                                <td><?= formatTaka($t['entry_fee']) ?></td>
                                                <td class="text-warning fw-bold"><?= formatTaka($t['prize_pool']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $t['status'] === 'active' ? 'success' : ($t['status'] === 'upcoming' ? 'info' : 'secondary') ?>">
                                                        <?= ucfirst($t['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="?action=view&id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="?action=edit&id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-warning">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="tournament_results.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-success">
                                                            <i class="fas fa-trophy"></i>
                                                        </a>
                                                        <a href="?action=delete&id=<?= $t['id'] ?>" 
                                                           class="btn btn-sm btn-outline-danger"
                                                           onclick="return confirm('Are you sure you want to delete this tournament?')">
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
                    
                <?php elseif ($action === 'create' || $action === 'edit'): ?>
                    <!-- Tournament Form -->
                    <div class="gaming-card">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label text-light">Tournament Name *</label>
                                    <input type="text" class="form-control gaming-input" id="name" name="name" 
                                           value="<?= htmlspecialchars($tournament['name'] ?? '') ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="game_type" class="form-label text-light">Game Type *</label>
                                    <select class="form-control gaming-input" id="game_type" name="game_type" required>
                                        <option value="">Select Game</option>
                                        <option value="PUBG Mobile" <?= ($tournament['game_type'] ?? '') === 'PUBG Mobile' ? 'selected' : '' ?>>PUBG Mobile</option>
                                        <option value="Free Fire" <?= ($tournament['game_type'] ?? '') === 'Free Fire' ? 'selected' : '' ?>>Free Fire</option>
                                        <option value="Call of Duty Mobile" <?= ($tournament['game_type'] ?? '') === 'Call of Duty Mobile' ? 'selected' : '' ?>>Call of Duty Mobile</option>
                                        <option value="Valorant" <?= ($tournament['game_type'] ?? '') === 'Valorant' ? 'selected' : '' ?>>Valorant</option>
                                        <option value="CS:GO" <?= ($tournament['game_type'] ?? '') === 'CS:GO' ? 'selected' : '' ?>>CS:GO</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label text-light">Description</label>
                                <textarea class="form-control gaming-input" id="description" name="description" rows="4"><?= htmlspecialchars($tournament['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="thumbnail" class="form-label text-light">Tournament Thumbnail</label>
                                <?php if (!empty($tournament['thumbnail'])): ?>
                                    <div class="mb-2">
                                        <img src="../<?= htmlspecialchars($tournament['thumbnail']) ?>" alt="Current thumbnail" 
                                             class="img-thumbnail" style="max-height: 100px;">
                                        <p class="text-light-50 small mb-0">Current thumbnail</p>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control gaming-input" id="thumbnail" name="thumbnail" 
                                       accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                                <div class="form-text text-light-50">Upload an image for the tournament (max 5MB). Supported formats: JPG, PNG, GIF, WebP</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="max_teams" class="form-label text-light">Max Teams *</label>
                                    <input type="number" class="form-control gaming-input" id="max_teams" name="max_teams" 
                                           value="<?= $tournament['max_teams'] ?? '' ?>" min="2" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="entry_fee" class="form-label text-light">Entry Fee (৳)</label>
                                    <input type="number" class="form-control gaming-input" id="entry_fee" name="entry_fee" 
                                           value="<?= $tournament['entry_fee'] ?? '' ?>" min="0" step="0.01">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="prize_pool" class="form-label text-light">Total Prize Pool (৳)</label>
                                    <input type="number" class="form-control gaming-input" id="prize_pool" name="prize_pool" 
                                           value="<?= $tournament['prize_pool'] ?? '' ?>" min="0" step="0.01">
                                </div>
                            </div>
                            
                            <!-- Prize Distribution Section -->
                            <div class="gaming-card mb-4">
                                <h5 class="text-accent mb-3">
                                    <i class="fas fa-trophy"></i> Prize Distribution
                                </h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="winner_prize" class="form-label text-light">1st Place Prize (৳)</label>
                                        <input type="number" class="form-control gaming-input" id="winner_prize" name="winner_prize" 
                                               value="<?= $tournament['winner_prize'] ?? '' ?>" min="0" step="0.01"
                                               placeholder="Champion prize amount">
                                        <div class="form-text text-light-50">Prize money for tournament champion</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="runner_up_prize" class="form-label text-light">2nd Place Prize (৳)</label>
                                        <input type="number" class="form-control gaming-input" id="runner_up_prize" name="runner_up_prize" 
                                               value="<?= $tournament['runner_up_prize'] ?? '' ?>" min="0" step="0.01"
                                               placeholder="Runner-up prize amount">
                                        <div class="form-text text-light-50">Prize money for runner-up</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="third_place_prize" class="form-label text-light">3rd Place Prize (৳)</label>
                                        <input type="number" class="form-control gaming-input" id="third_place_prize" name="third_place_prize" 
                                               value="<?= $tournament['third_place_prize'] ?? '' ?>" min="0" step="0.01"
                                               placeholder="Third place prize amount">
                                        <div class="form-text text-light-50">Prize money for third place</div>
                                    </div>
                                </div>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> <strong>Note:</strong> Prize money will be automatically added to winners' wallets when tournament results are published. Players can then withdraw their earnings.
                                </div>
                            </div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="third_place_prize" class="form-label text-light">3rd Place Prize (৳)</label>
                                        <input type="number" class="form-control gaming-input" id="third_place_prize" name="third_place_prize" 
                                               value="<?= $tournament['third_place_prize'] ?? '' ?>" min="0" step="0.01"
                                               placeholder="Third place prize amount">
                                        <div class="form-text text-light-50">Prize money for third place</div>
                                    </div>
                                </div>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> <strong>Note:</strong> Prize money will be automatically added to winners' wallets when tournament results are published. Players can then withdraw their earnings.
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="start_date" class="form-label text-light">Start Date</label>
                                    <input type="datetime-local" class="form-control gaming-input" id="start_date" name="start_date" 
                                           value="<?= isset($tournament['start_date']) ? date('Y-m-d\TH:i', strtotime($tournament['start_date'])) : '' ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="end_date" class="form-label text-light">End Date</label>
                                    <input type="datetime-local" class="form-control gaming-input" id="end_date" name="end_date" 
                                           value="<?= isset($tournament['end_date']) ? date('Y-m-d\TH:i', strtotime($tournament['end_date'])) : '' ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="status" class="form-label text-light">Status</label>
                                    <select class="form-control gaming-input" id="status" name="status">
                                        <option value="upcoming" <?= ($tournament['status'] ?? 'upcoming') === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                        <option value="active" <?= ($tournament['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="completed" <?= ($tournament['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="cancelled" <?= ($tournament['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-accent">
                                    <i class="fas fa-save"></i> <?= $action === 'create' ? 'Create' : 'Update' ?> Tournament
                                </button>
                            </div>
                        </form>
                    </div>
                    
                <?php elseif ($action === 'view'): ?>
                    <!-- Tournament Details -->
                    <div class="gaming-card">
                        <div class="row">
                            <div class="col-md-8">
                                <h3 class="text-accent"><?= htmlspecialchars($tournament['name']) ?></h3>
                                <p class="text-light-50"><?= htmlspecialchars($tournament['description']) ?></p>
                                
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <h5 class="text-light">Tournament Details</h5>
                                        <ul class="list-unstyled text-light-50">
                                            <li><strong>Game:</strong> <?= htmlspecialchars($tournament['game_type']) ?></li>
                                            <li><strong>Max Teams:</strong> <?= $tournament['max_teams'] ?></li>
                                            <li><strong>Entry Fee:</strong> <?= formatTaka($tournament['entry_fee']) ?></li>
                                            <li><strong>Prize Pool:</strong> <?= formatTaka($tournament['prize_pool']) ?></li>
                                            <li><strong>Start Date:</strong> <?= formatDate($tournament['start_date']) ?></li>
                                            <li><strong>End Date:</strong> <?= formatDate($tournament['end_date']) ?></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="text-center">
                                    <span class="badge bg-<?= $tournament['status'] === 'active' ? 'success' : ($tournament['status'] === 'upcoming' ? 'info' : 'secondary') ?> fs-6">
                                        <?= ucfirst($tournament['status']) ?>
                                    </span>
                                </div>
                                
                                <div class="mt-4">
                                    <a href="?action=edit&id=<?= $tournament['id'] ?>" class="btn btn-warning w-100 mb-2">
                                        <i class="fas fa-edit"></i> Edit Tournament
                                    </a>
                                    <a href="matches.php?tournament_id=<?= $tournament['id'] ?>" class="btn btn-info w-100">
                                        <i class="fas fa-gamepad"></i> Manage Matches
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
