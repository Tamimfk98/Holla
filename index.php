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

    <!-- Hero Section -->
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
                                    <?php if ($tournament['thumbnail']): ?>
                                        <img src="<?= htmlspecialchars($tournament['thumbnail']) ?>" alt="<?= htmlspecialchars($tournament['game_type']) ?>" 
                                             class="img-fluid rounded" style="max-height: 60px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary rounded d-flex align-items-center justify-content-center" style="height: 60px;">
                                            <i class="fas fa-gamepad text-light-50"></i>
                                        </div>
                                    <?php endif; ?>
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

    <!-- All Tournaments Section -->
    <section class="py-5 bg-dark">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h2 class="text-center text-accent mb-5">
                        <i class="fas fa-trophy"></i> All Tournaments
                    </h2>
                </div>
            </div>
            <div class="row">
                <?php
                try {
                    $stmt = $pdo->prepare("
                        SELECT t.*, COUNT(tr.id) as registered_teams 
                        FROM tournaments t 
                        LEFT JOIN tournament_registrations tr ON t.id = tr.tournament_id AND tr.status = 'approved'
                        GROUP BY t.id, t.name, t.description, t.game_type, t.max_teams, t.entry_fee, t.prize_pool, t.start_date, t.end_date, t.status, t.created_at, t.updated_at, t.thumbnail
                        ORDER BY t.created_at DESC 
                        LIMIT 6
                    ");
                    $stmt->execute();
                    $allTournaments = $stmt->fetchAll();
                    
                    if ($allTournaments):
                        foreach ($allTournaments as $tournament):
                            $percentage = ($tournament['registered_teams'] / $tournament['max_teams']) * 100;
                            $statusColor = $tournament['status'] === 'upcoming' ? 'info' : ($tournament['status'] === 'active' ? 'success' : 'secondary');
                ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="gaming-card h-100">
                        <?php if ($tournament['thumbnail']): ?>
                            <img src="<?= htmlspecialchars($tournament['thumbnail']) ?>" 
                                 class="card-img-top" style="height: 200px; object-fit: cover;" 
                                 alt="<?= htmlspecialchars($tournament['name']) ?>">
                        <?php else: ?>
                            <div class="bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                                <i class="fas fa-gamepad fa-4x text-light-50"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($tournament['status']) ?></span>
                                <span class="text-accent fw-bold">৳<?= number_format($tournament['prize_pool']) ?></span>
                            </div>
                            
                            <h5 class="text-light mb-2"><?= htmlspecialchars($tournament['name']) ?></h5>
                            <p class="text-light-50 small mb-3"><?= htmlspecialchars($tournament['description']) ?></p>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between text-light-50 small mb-1">
                                    <span><i class="fas fa-gamepad"></i> <?= htmlspecialchars($tournament['game_type']) ?></span>
                                    <span><i class="fas fa-users"></i> <?= $tournament['registered_teams'] ?>/<?= $tournament['max_teams'] ?></span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-accent" style="width: <?= $percentage ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between text-light-50 small mb-2">
                                    <span><i class="fas fa-coins"></i> Entry: ৳<?= number_format($tournament['entry_fee']) ?></span>
                                    <span><i class="fas fa-calendar"></i> <?= date('M j', strtotime($tournament['start_date'])) ?></span>
                                </div>
                                <a href="user/tournaments.php?id=<?= $tournament['id'] ?>" class="btn btn-accent w-100">
                                    <i class="fas fa-sign-in-alt"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php 
                        endforeach;
                    else:
                ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-trophy fa-4x text-light-50 mb-3"></i>
                        <h4 class="text-light-50">No tournaments available</h4>
                        <p class="text-light-50">Check back soon for exciting tournaments!</p>
                    </div>
                </div>
                <?php endif; ?>
                <?php } catch (Exception $e) { /* Ignore database errors */ } ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="user/tournaments.php" class="btn btn-outline-accent btn-lg">
                    <i class="fas fa-trophy"></i> View All Tournaments
                </a>
            </div>
        </div>
    </section>

    <!-- How Our Website Works Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h2 class="text-center text-accent mb-5">
                        <i class="fas fa-cogs"></i> How Our Website Works
                    </h2>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="gaming-card text-center h-100">
                        <div class="mb-3">
                            <i class="fas fa-user-plus fa-3x text-accent"></i>
                        </div>
                        <h5 class="text-light mb-3">1. Register</h5>
                        <p class="text-light-50">Create your gaming account and complete your profile to get started.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="gaming-card text-center h-100">
                        <div class="mb-3">
                            <i class="fas fa-search fa-3x text-accent"></i>
                        </div>
                        <h5 class="text-light mb-3">2. Browse</h5>
                        <p class="text-light-50">Explore available tournaments and find the perfect competition for your skill level.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="gaming-card text-center h-100">
                        <div class="mb-3">
                            <i class="fas fa-credit-card fa-3x text-accent"></i>
                        </div>
                        <h5 class="text-light mb-3">3. Pay & Join</h5>
                        <p class="text-light-50">Register for tournaments with secure payment through bKash or Nagad.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="gaming-card text-center h-100">
                        <div class="mb-3">
                            <i class="fas fa-trophy fa-3x text-accent"></i>
                        </div>
                        <h5 class="text-light mb-3">4. Compete & Win</h5>
                        <p class="text-light-50">Play your matches, upload screenshots, and claim your prizes!</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gaming Community Section -->
    <section class="py-5 bg-dark">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h2 class="text-center text-accent mb-5">
                        <i class="fas fa-users"></i> Join Our Gaming Community
                    </h2>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <a href="https://facebook.com/yourgamingpage" target="_blank" class="btn btn-outline-light w-100 py-3">
                                <i class="fab fa-facebook-f fa-2x mb-2"></i>
                                <div>
                                    <h6 class="mb-0">Facebook</h6>
                                    <small class="text-light-50">Follow for updates</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="https://t.me/yourgamingchannel" target="_blank" class="btn btn-outline-light w-100 py-3">
                                <i class="fab fa-telegram-plane fa-2x mb-2"></i>
                                <div>
                                    <h6 class="mb-0">Telegram</h6>
                                    <small class="text-light-50">Join our channel</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="https://instagram.com/yourgamingpage" target="_blank" class="btn btn-outline-light w-100 py-3">
                                <i class="fab fa-instagram fa-2x mb-2"></i>
                                <div>
                                    <h6 class="mb-0">Instagram</h6>
                                    <small class="text-light-50">See our highlights</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Recent Winners Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h2 class="text-center text-accent mb-5">
                        <i class="fas fa-medal"></i> Recent Winners
                    </h2>
                </div>
            </div>
            <div class="row">
                <?php
                try {
                    $stmt = $pdo->prepare("
                        SELECT 
                            m.id,
                            t.name as tournament_name,
                            t.game_type,
                            t.prize_pool,
                            u.username as winner_name,
                            u.full_name,
                            m.completed_at
                        FROM matches m
                        JOIN tournaments t ON m.tournament_id = t.id
                        JOIN users u ON m.winner_id = u.id
                        WHERE m.status = 'completed' AND m.winner_id IS NOT NULL
                        ORDER BY m.completed_at DESC
                        LIMIT 6
                    ");
                    $stmt->execute();
                    $recentWinners = $stmt->fetchAll();
                    
                    if ($recentWinners):
                        foreach ($recentWinners as $winner):
                ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="gaming-card text-center">
                        <div class="mb-3">
                            <i class="fas fa-crown fa-3x text-warning"></i>
                        </div>
                        <h5 class="text-accent mb-2"><?= htmlspecialchars($winner['winner_name']) ?></h5>
                        <p class="text-light mb-1"><?= htmlspecialchars($winner['full_name']) ?></p>
                        <p class="text-light-50 small mb-2">Winner of <?= htmlspecialchars($winner['tournament_name']) ?></p>
                        <div class="mb-2">
                            <span class="badge bg-info"><?= htmlspecialchars($winner['game_type']) ?></span>
                        </div>
                        <div class="text-warning fw-bold mb-2">
                            <i class="fas fa-trophy"></i> ৳<?= number_format($winner['prize_pool']) ?> Prize
                        </div>
                        <small class="text-light-50">
                            <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($winner['completed_at'])) ?>
                        </small>
                    </div>
                </div>
                <?php 
                        endforeach;
                    else:
                ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-medal fa-4x text-light-50 mb-3"></i>
                        <h4 class="text-light-50">No winners yet</h4>
                        <p class="text-light-50">Be the first to win a tournament!</p>
                    </div>
                </div>
                <?php endif; ?>
                <?php } catch (Exception $e) { /* Ignore database errors */ } ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark border-top border-secondary py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="text-accent mb-3">eSports Tournament</h5>
                    <p class="text-light-50">The ultimate gaming battlefield where champions are made. Join tournaments, compete with the best, and claim your victory.</p>
                    <div class="social-links">
                        <a href="https://facebook.com/yourgamingpage" target="_blank" class="text-light me-3">
                            <i class="fab fa-facebook-f fa-lg"></i>
                        </a>
                        <a href="https://t.me/yourgamingchannel" target="_blank" class="text-light me-3">
                            <i class="fab fa-telegram-plane fa-lg"></i>
                        </a>
                        <a href="https://instagram.com/yourgamingpage" target="_blank" class="text-light me-3">
                            <i class="fab fa-instagram fa-lg"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="text-light mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="user/tournaments.php" class="text-light-50 text-decoration-none">Tournaments</a></li>
                        <li class="mb-2"><a href="user/register.php" class="text-light-50 text-decoration-none">Register</a></li>
                        <li class="mb-2"><a href="user/login.php" class="text-light-50 text-decoration-none">Login</a></li>
                        <li class="mb-2"><a href="user/matches.php" class="text-light-50 text-decoration-none">My Matches</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="text-light mb-3">Support</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-light-50 text-decoration-none">Help Center</a></li>
                        <li class="mb-2"><a href="#" class="text-light-50 text-decoration-none">Contact Us</a></li>
                        <li class="mb-2"><a href="#" class="text-light-50 text-decoration-none">Terms of Service</a></li>
                        <li class="mb-2"><a href="#" class="text-light-50 text-decoration-none">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="text-light mb-3">Games</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><span class="text-light-50">PUBG Mobile</span></li>
                        <li class="mb-2"><span class="text-light-50">Free Fire</span></li>
                        <li class="mb-2"><span class="text-light-50">Call of Duty Mobile</span></li>
                        <li class="mb-2"><span class="text-light-50">Valorant</span></li>
                        <li class="mb-2"><span class="text-light-50">CS:GO</span></li>
                    </ul>
                </div>
            </div>
            <hr class="border-secondary my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-light-50 mb-0">&copy; 2025 eSports Tournament. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-light-50 mb-0">Made with <i class="fas fa-heart text-danger"></i> for gamers</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
