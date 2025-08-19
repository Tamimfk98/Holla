<?php
require_once '../config/config.php';
requireLogin();

$error = '';
$success = '';

// Get user's current wallet balance
$stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$walletBalance = $user['wallet_balance'] ?? 0;

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $amount = (float)($_POST['amount'] ?? 0);
        $paymentMethod = sanitizeInput($_POST['payment_method'] ?? '');
        $accountNumber = sanitizeInput($_POST['account_number'] ?? '');
        
        // Validation
        if ($amount <= 0) {
            $error = 'Please enter a valid amount';
        } elseif ($amount > $walletBalance) {
            $error = 'Insufficient balance. Your current balance is $' . number_format($walletBalance, 2);
        } elseif ($amount < 10) {
            $error = 'Minimum withdrawal amount is $10.00';
        } elseif (empty($paymentMethod)) {
            $error = 'Please select a payment method';
        } elseif (empty($accountNumber)) {
            $error = 'Please enter your account number';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Deduct amount from user's wallet
                $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
                $stmt->execute([$amount, $_SESSION['user_id']]);
                
                // Create withdrawal request
                $stmt = $pdo->prepare("
                    INSERT INTO withdrawal_requests (user_id, amount, payment_method, account_number, status) 
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$_SESSION['user_id'], $amount, $paymentMethod, $accountNumber]);
                
                $pdo->commit();
                $success = 'Withdrawal request submitted successfully! Amount has been deducted from your wallet. You will receive the money once admin approves your request.';
                
                // Update wallet balance
                $walletBalance -= $amount;
                
            } catch (PDOException $e) {
                $pdo->rollback();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get user's withdrawal history
$stmt = $pdo->prepare("
    SELECT wr.*, u.username as processed_by_username 
    FROM withdrawal_requests wr 
    LEFT JOIN users u ON wr.processed_by = u.id 
    WHERE wr.user_id = ? 
    ORDER BY wr.requested_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$withdrawalHistory = $stmt->fetchAll();

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
    <title>Withdraw Money - eSports Tournament</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-page">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="text-accent">
                        <i class="fas fa-money-bill-wave"></i> Withdraw Money
                    </h1>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
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
                                <i class="fas fa-wallet"></i> Current Balance
                            </h4>
                            <div class="text-center mb-4">
                                <h2 class="text-success">$<?= number_format($walletBalance, 2) ?></h2>
                                <p class="text-light-50">Available for withdrawal</p>
                            </div>
                            
                            <h5 class="text-accent mb-3">Request Withdrawal</h5>
                            
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                
                                <div class="mb-3">
                                    <label for="amount" class="form-label text-light">
                                        <i class="fas fa-dollar-sign"></i> Amount ($) *
                                    </label>
                                    <input type="number" class="form-control gaming-input" id="amount" 
                                           name="amount" step="0.01" min="10" max="<?= $walletBalance ?>" required>
                                    <small class="text-light-50">Minimum: $10.00 | Maximum: $<?= number_format($walletBalance, 2) ?></small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label text-light">
                                        <i class="fas fa-credit-card"></i> Payment Method *
                                    </label>
                                    <select class="form-select gaming-input" id="payment_method" name="payment_method" required>
                                        <option value="">Select Payment Method</option>
                                        <option value="bkash">bKash</option>
                                        <option value="nagad">Nagad</option>
                                        <option value="rocket">Rocket</option>
                                        <option value="bank">Bank Transfer</option>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="account_number" class="form-label text-light">
                                        <i class="fas fa-hashtag"></i> Account Number *
                                    </label>
                                    <input type="text" class="form-control gaming-input" id="account_number" 
                                           name="account_number" required placeholder="Enter your account number">
                                    <small class="text-light-50">Enter your mobile number for mobile banking or account number for bank transfer</small>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Important:</strong> The withdrawal amount will be immediately deducted from your wallet. You will receive the money once admin approves your request.
                                </div>
                                
                                <button type="submit" class="btn btn-accent w-100" <?= $walletBalance < 10 ? 'disabled' : '' ?>>
                                    <i class="fas fa-paper-plane"></i> Submit Withdrawal Request
                                </button>
                                
                                <?php if ($walletBalance < 10): ?>
                                    <div class="text-center mt-2">
                                        <small class="text-warning">Minimum balance of $10.00 required for withdrawal</small>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="gaming-card">
                            <h4 class="text-accent mb-3">
                                <i class="fas fa-history"></i> Withdrawal History
                            </h4>
                            
                            <?php if (empty($withdrawalHistory)): ?>
                                <div class="text-center text-light-50 py-4">
                                    <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                                    <p>No withdrawal requests yet</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-dark">
                                        <thead>
                                            <tr>
                                                <th>Amount</th>
                                                <th>Method</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($withdrawalHistory as $withdrawal): ?>
                                                <tr>
                                                    <td>$<?= number_format($withdrawal['amount'], 2) ?></td>
                                                    <td><?= ucfirst($withdrawal['payment_method']) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= 
                                                            $withdrawal['status'] === 'approved' ? 'success' : 
                                                            ($withdrawal['status'] === 'rejected' ? 'danger' : 'warning') 
                                                        ?>">
                                                            <?= ucfirst($withdrawal['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= formatDate($withdrawal['requested_at'], 'M d, Y') ?></td>
                                                </tr>
                                                <?php if ($withdrawal['admin_notes']): ?>
                                                    <tr>
                                                        <td colspan="4">
                                                            <small class="text-light-50">
                                                                <i class="fas fa-comment"></i> <?= htmlspecialchars($withdrawal['admin_notes']) ?>
                                                            </small>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update account number placeholder based on payment method
        document.getElementById('payment_method').addEventListener('change', function() {
            const accountInput = document.getElementById('account_number');
            const method = this.value;
            
            switch(method) {
                case 'bkash':
                case 'nagad':
                case 'rocket':
                    accountInput.placeholder = 'Enter your mobile number (e.g., 01712345678)';
                    break;
                case 'bank':
                    accountInput.placeholder = 'Enter your bank account number';
                    break;
                default:
                    accountInput.placeholder = 'Enter your account number';
            }
        });
    </script>
</body>
</html>