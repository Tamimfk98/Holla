<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'All fields are required';
        } else {
            $auth = new Auth($pdo);
            $result = $auth->loginUser($username, $password);
            
            if ($result['success']) {
                redirect('dashboard.php', $result['message'], 'success');
            } else {
                $error = $result['message'];
            }
        }
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
    <title>Login - eSports Tournament</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">
    <div class="container-fluid h-100">
        <div class="row h-100">
            <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center bg-primary">
                <div class="text-center text-light">
                    <i class="fas fa-gamepad display-1 text-accent mb-4"></i>
                    <h2 class="mb-3">Welcome Back, Gamer!</h2>
                    <p class="lead">Join epic tournaments and claim your victories</p>
                    <div class="mt-4">
                        <div class="row">
                            <div class="col-4">
                                <i class="fas fa-trophy fa-2x text-warning mb-2"></i>
                                <p class="small">Tournaments</p>
                            </div>
                            <div class="col-4">
                                <i class="fas fa-users fa-2x text-success mb-2"></i>
                                <p class="small">Community</p>
                            </div>
                            <div class="col-4">
                                <i class="fas fa-coins fa-2x text-accent mb-2"></i>
                                <p class="small">Prizes</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 d-flex align-items-center justify-content-center">
                <div class="w-100" style="max-width: 400px;">
                    <div class="gaming-card">
                        <div class="text-center mb-4">
                            <h3 class="text-accent">
                                <i class="fas fa-sign-in-alt"></i> Player Login
                            </h3>
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
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label text-light">
                                    <i class="fas fa-user"></i> Username or Email
                                </label>
                                <input type="text" class="form-control gaming-input" id="username" 
                                       name="username" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label text-light">
                                    <i class="fas fa-lock"></i> Password
                                </label>
                                <input type="password" class="form-control gaming-input" id="password" 
                                       name="password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-accent w-100 mb-3">
                                <i class="fas fa-gamepad"></i> Start Gaming
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="text-light-50 mb-2">New to eSports?</p>
                            <a href="register.php" class="text-accent">
                                <i class="fas fa-user-plus"></i> Create Account
                            </a>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="../index.php" class="text-light-50">
                                <i class="fas fa-arrow-left"></i> Back to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
