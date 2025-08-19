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
                        <h3 class="text-accent mb-4"><i class="fas fa-bolt"></i> Live Tournaments</h3>
                        <div class="tournament-preview">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-success">Active</span>
                                <span class="text-accent fw-bold">৳50,000 Prize Pool</span>
                            </div>
                            <h5 class="text-light">Championship Series</h5>
                            <p class="text-light-50 small">Multiple games • 64 teams • 3 days</p>
                            <div class="progress mb-3" style="height: 8px;">
                                <div class="progress-bar bg-accent" style="width: 75%"></div>
                            </div>
                            <p class="small text-light-50 mb-0">48/64 teams registered</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
