<?php
session_start();
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eSports Tournament Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-trophy text-accent"></i> eSports Hub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="user/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="user/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="user/login.php">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="user/register.php">
                                <i class="fas fa-user-plus"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin/login.php">
                            <i class="fas fa-cog"></i> Admin
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold text-light mb-4">
                        Compete in Epic <span class="text-accent">eSports</span> Tournaments
                    </h1>
                    <p class="lead text-light-50 mb-4">
                        Join the ultimate gaming battlefield. Register for tournaments, compete with the best, and claim your victory.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="user/register.php" class="btn btn-accent btn-lg">
                            <i class="fas fa-gamepad"></i> Start Gaming
                        </a>
                        <a href="user/tournaments.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-trophy"></i> Browse Tournaments
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="gaming-card">
                        <h3 class="text-accent mb-4"><i class="fas fa-bolt"></i> Featured Tournaments</h3>
                        <?php
                        try {
                            $stmt = $pdo->prepare("
                                SELECT t.*, COUNT(tr.id) as registered_teams 
                                FROM tournaments t 
                                LEFT JOIN tournament_registrations tr ON t.id = tr.tournament_id AND tr.status = 'approved'
                                WHERE t.status = 'upcoming'
                                GROUP BY t.id, t.name, t.description, t.game_type, t.max_teams, t.entry_fee, t.prize_pool, t.start_date, t.end_date, t.status, t.created_at, t.updated_at, t.thumbnail
                                ORDER BY t.prize_pool DESC 
                                LIMIT 2
                            ");
                            $stmt->execute();
                            $featuredTournaments = $stmt->fetchAll();
                            
                            if ($featuredTournaments):
                                foreach ($featuredTournaments as $tournament):
                                    $percentage = ($tournament['registered_teams'] / $tournament['max_teams']) * 100;
                        ?>
                        <div class="tournament-preview mb-3">
                            <div class="row align-items-center">
                                <div class="col-3">
                                    <img src="<?= $tournament['thumbnail'] ?>" alt="<?= htmlspecialchars($tournament['game_type']) ?>" 
                                         class="img-fluid rounded" style="max-height: 60px; object-fit: cover;">
                                </div>
                                <div class="col-9">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-success">Upcoming</span>
                                        <span class="text-accent fw-bold">৳<?= number_format($tournament['prize_pool']) ?> Prize</span>
                                    </div>
                                    <h6 class="text-light mb-1"><?= htmlspecialchars($tournament['name']) ?></h6>
                                    <p class="text-light-50 small mb-2"><?= htmlspecialchars($tournament['game_type']) ?> • <?= $tournament['max_teams'] ?> teams</p>
                                    <div class="progress mb-2" style="height: 6px;">
                                        <div class="progress-bar bg-accent" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                    <p class="small text-light-50 mb-0"><?= $tournament['registered_teams'] ?>/<?= $tournament['max_teams'] ?> teams</p>
                                </div>
                            </div>
                        </div>
                        <?php 
                                endforeach;
                            else:
                        ?>
                        <div class="tournament-preview">
                            <p class="text-light-50 text-center">No upcoming tournaments at the moment.</p>
                        </div>
                        <?php endif; ?>
                        <?php } catch (Exception $e) { /* Ignore database errors on homepage */ } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
