<?php
require_once '../config/config.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$registrationId = $_GET['registration_id'] ?? null;
$error = '';
$success = '';

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'pay') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $method = sanitizeInput($_POST['method'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $transactionId = sanitizeInput($_POST['transaction_id'] ?? '');
        
        if (empty($method) || empty($phone) || empty($transactionId)) {
            $error = 'All payment fields are required';
        } else {
            try {
                // Verify registration belongs to user
                $stmt = $pdo->prepare("
                    SELECT tr.*, t.entry_fee, t.name as tournament_name
                    FROM tournament_registrations tr
                    JOIN tournaments t ON tr.tournament_id = t.id
                    WHERE tr.id = ? AND tr.user_id = ?
                ");
                $stmt->execute([$registrationId, $_SESSION['user_id']]);
                $registration = $stmt->fetch();
                
                if (!$registration) {
                    $error = 'Registration not found';
                } else {
                    // Check if payment already exists
                    $stmt = $pdo->prepare("SELECT id FROM payments WHERE registration_id = ?");
                    $stmt->execute([$registrationId]);
                    
                    if ($stmt->rowCount() > 0) {
                        $error = 'Payment already submitted for this registration';
                    } else {
                        // Create payment record
                        $stmt = $pdo->prepare("
                            INSERT INTO payments (registration_id, amount, method, phone, transaction_id, status, created_at)
                            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                        ");
                        $stmt->execute([$registrationId, $registration['entry_fee'], $method, $phone, $transactionId]);
                        
                        $success = 'Payment submitted successfully! Please wait for admin approval.';
                        $action = 'list';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get registration details for payment
$registration = null;
if ($action === 'pay' && $registrationId) {
    $stmt = $pdo->prepare("
        SELECT tr.*, t.name as tournament_name, t.entry_fee, t.game_type,
               p.id as payment_id, p.status as payment_status
        FROM tournament_registrations tr
        JOIN tournaments t ON tr.tournament_id = t.id
        LEFT JOIN payments p ON tr.id = p.registration_id
        WHERE tr.id = ? AND tr.user_id = ?
    ");
    $stmt->execute([$registrationId, $_SESSION['user_id']]);
    $registration = $stmt->fetch();
    
    if (!$registration) {
        $error = 'Registration not found';
        $action = 'list';
    } elseif ($registration['payment_id']) {
        $error = 'Payment already submitted for this registration';
        $action = 'list';
    }
}

// Get user payments list
if ($action === 'list') {
    $stmt = $pdo->prepare("
        SELECT p.*, tr.team_name, t.name as tournament_name, t.game_type
        FROM payments p
        JOIN tournament_registrations tr ON p.registration_id = tr.id
        JOIN tournaments t ON tr.tournament_id = t.id
        WHERE tr.user_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $payments = $stmt->fetchAll();
    
    // Get payment statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_payments,
            COUNT(CASE WHEN p.status = 'pending' THEN 1 END) as pending_payments,
            COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_payments,
            SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as total_spent
        FROM payments p
        JOIN tournament_registrations tr ON p.registration_id = tr.id
        WHERE tr.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
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
    <title>Payments - eSports Tournament</title>
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
                        <a class="nav-link active" href="payments.php">
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
                <i class="fas fa-credit-card text-accent"></i> 
                <?= $action === 'pay' ? 'Make Payment' : 'My Payments' ?>
            </h2>
            <?php if ($action === 'pay'): ?>
                <a href="?" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back to Payments
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
            <!-- Payment Statistics -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="gaming-card stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="text-accent mb-0"><?= $stats['total_payments'] ?></h3>
                                <p class="text-light-50 mb-0">Total Payments</p>
                            </div>
                            <i class="fas fa-credit-card fa-2x text-accent"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="gaming-card stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="text-warning mb-0"><?= $stats['pending_payments'] ?></h3>
                                <p class="text-light-50 mb-0">Pending</p>
                            </div>
                            <i class="fas fa-clock fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="gaming-card stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="text-success mb-0"><?= $stats['completed_payments'] ?></h3>
                                <p class="text-light-50 mb-0">Completed</p>
                            </div>
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="gaming-card stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="text-warning mb-0"><?= formatTaka($stats['total_spent']) ?></h3>
                                <p class="text-light-50 mb-0">Total Spent</p>
                            </div>
                            <i class="fas fa-coins fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payments List -->
            <div class="gaming-card">
                <?php if (empty($payments)): ?>
                    <div class="text-center text-light-50 py-5">
                        <i class="fas fa-credit-card fa-4x mb-3"></i>
                        <h4>No Payments Yet</h4>
                        <p>Your payment history will appear here once you make tournament payments</p>
                        <a href="tournaments.php" class="btn btn-accent">
                            <i class="fas fa-trophy"></i> Browse Tournaments
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Tournament</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td>
                                            <code class="text-accent"><?= htmlspecialchars($payment['transaction_id']) ?></code>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($payment['tournament_name']) ?></strong>
                                            <br><small class="text-light-50">
                                                <span class="badge bg-info"><?= htmlspecialchars($payment['game_type']) ?></span>
                                                Team: <?= htmlspecialchars($payment['team_name']) ?>
                                            </small>
                                        </td>
                                        <td class="fw-bold text-warning"><?= formatTaka($payment['amount']) ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= strtoupper($payment['method']) ?></span>
                                            <br><small class="text-light-50"><?= htmlspecialchars($payment['phone']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                <?= ucfirst($payment['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= formatDate($payment['created_at']) ?></td>
                                        <td>
                                            <?php if ($payment['status'] === 'completed'): ?>
                                                <button class="btn btn-sm btn-outline-success" onclick="generateReceipt(<?= $payment['id'] ?>)">
                                                    <i class="fas fa-download"></i> Receipt
                                                </button>
                                            <?php else: ?>
                                                <span class="text-light-50 small">Pending approval</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php elseif ($action === 'pay' && $registration): ?>
            <!-- Payment Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="gaming-card">
                        <h4 class="text-accent mb-4">
                            <i class="fas fa-credit-card"></i> Tournament Payment
                        </h4>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="alert alert-info mb-4">
                                <h6 class="text-info"><i class="fas fa-info-circle"></i> Payment Instructions:</h6>
                                <ol class="mb-0">
                                    <li>Send money to the merchant number using bKash/Nagad</li>
                                    <li>Note down the transaction ID from your SMS</li>
                                    <li>Fill out the form below with exact details</li>
                                    <li>Wait for admin approval (usually within 24 hours)</li>
                                </ol>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="method" class="form-label text-light">Payment Method *</label>
                                    <select class="form-control gaming-input" id="method" name="method" required>
                                        <option value="">Select Method</option>
                                        <option value="bkash">bKash</option>
                                        <option value="nagad">Nagad</option>
                                        <option value="rocket">Rocket</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label text-light">Your Phone Number *</label>
                                    <input type="tel" class="form-control gaming-input" id="phone" name="phone" 
                                           required placeholder="01XXXXXXXXX">
                                    <small class="text-light-50">Phone number used for payment</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="transaction_id" class="form-label text-light">Transaction ID *</label>
                                <input type="text" class="form-control gaming-input" id="transaction_id" 
                                       name="transaction_id" required placeholder="Enter transaction ID from SMS">
                                <small class="text-light-50">Exact transaction ID as received in SMS</small>
                            </div>
                            
                            <div class="mb-4">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Important:</strong> Make sure to send exactly 
                                    <strong><?= formatTaka($registration['entry_fee']) ?></strong> 
                                    to avoid payment delays.
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-accent">
                                    <i class="fas fa-credit-card"></i> Submit Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="gaming-card">
                        <h5 class="text-accent mb-3">Payment Details</h5>
                        
                        <table class="table table-dark table-sm">
                            <tr>
                                <th>Tournament:</th>
                                <td><?= htmlspecialchars($registration['tournament_name']) ?></td>
                            </tr>
                            <tr>
                                <th>Game:</th>
                                <td><?= htmlspecialchars($registration['game_type']) ?></td>
                            </tr>
                            <tr>
                                <th>Team:</th>
                                <td><?= htmlspecialchars($registration['team_name']) ?></td>
                            </tr>
                            <tr>
                                <th>Entry Fee:</th>
                                <td class="text-accent fw-bold fs-5"><?= formatTaka($registration['entry_fee']) ?></td>
                            </tr>
                        </table>
                        
                        <div class="mt-4">
                            <h6 class="text-warning">Merchant Numbers:</h6>
                            <div class="payment-methods">
                                <div class="method-item mb-2">
                                    <i class="fab fa-bitcoin text-warning"></i>
                                    <strong>bKash:</strong> 01XXXXXXXXX
                                </div>
                                <div class="method-item mb-2">
                                    <i class="fas fa-mobile-alt text-info"></i>
                                    <strong>Nagad:</strong> 01XXXXXXXXX
                                </div>
                                <div class="method-item mb-2">
                                    <i class="fas fa-rocket text-danger"></i>
                                    <strong>Rocket:</strong> 01XXXXXXXXX
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="alert alert-info">
                                <i class="fas fa-clock"></i>
                                Payment approval usually takes 24 hours. You'll be notified via email.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function generateReceipt(paymentId) {
            window.open(`../api/receipt.php?payment_id=${paymentId}`, '_blank');
        }
    </script>
</body>
</html>
