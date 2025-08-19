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
        $email = sanitizeInput($_POST['email'] ?? '');
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($username) || empty($email) || empty($fullName) || empty($password)) {
            $error = 'All fields are required';
        } elseif (!validateEmail($email)) {
            $error = 'Invalid email address';
        } elseif (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters long';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            $auth = new Auth($pdo);
            $result = $auth->registerUser($username, $email, $password, $fullName);
            
            if ($result['success']) {
                $success = $result['message'] . ' You can now login.';
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
    <title>Register - eSports Tournament</title>
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
                    <i class="fas fa-rocket display-1 text-accent mb-4"></i>
                    <h2 class="mb-3">Join the Arena!</h2>
                    <p class="lead">Start your eSports journey and compete for glory</p>
                    <div class="mt-4">
                        <div class="gaming-card p-3">
                            <h5 class="text-accent mb-3">What awaits you:</h5>
                            <ul class="list-unstyled text-start">
                                <li class="mb-2"><i class="fas fa-trophy text-warning me-2"></i> Epic Tournaments</li>
                                <li class="mb-2"><i class="fas fa-users text-success me-2"></i> Gaming Community</li>
                                <li class="mb-2"><i class="fas fa-coins text-accent me-2"></i> Cash Prizes</li>
                                <li class="mb-2"><i class="fas fa-gamepad text-info me-2"></i> Multiple Games</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 d-flex align-items-center justify-content-center">
                <div class="w-100" style="max-width: 400px;">
                    <div class="gaming-card">
                        <div class="text-center mb-4">
                            <h3 class="text-accent">
                                <i class="fas fa-user-plus"></i> Create Account
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
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-sm btn-outline-success">Login Now</a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label text-light">
                                    <i class="fas fa-user"></i> Username *
                                </label>
                                <input type="text" class="form-control gaming-input" id="username" 
                                       name="username" required minlength="3" 
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                                <small class="text-light-50">Minimum 3 characters</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="full_name" class="form-label text-light">
                                    <i class="fas fa-id-card"></i> Full Name *
                                </label>
                                <input type="text" class="form-control gaming-input" id="full_name" 
                                       name="full_name" required 
                                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label text-light">
                                    <i class="fas fa-envelope"></i> Email *
                                </label>
                                <input type="email" class="form-control gaming-input" id="email" 
                                       name="email" required 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label text-light">
                                    <i class="fas fa-lock"></i> Password *
                                </label>
                                <input type="password" class="form-control gaming-input" id="password" 
                                       name="password" required minlength="6">
                                <small class="text-light-50">Minimum 6 characters</small>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label text-light">
                                    <i class="fas fa-lock"></i> Confirm Password *
                                </label>
                                <input type="password" class="form-control gaming-input" id="confirm_password" 
                                       name="confirm_password" required minlength="6">
                            </div>
                            
                            <button type="submit" class="btn btn-accent w-100 mb-3">
                                <i class="fas fa-rocket"></i> Join the Arena
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="text-light-50 mb-2">Already have an account?</p>
                            <a href="login.php" class="text-accent">
                                <i class="fas fa-sign-in-alt"></i> Login Here
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
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
