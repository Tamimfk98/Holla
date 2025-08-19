<?php
require_once '../config/config.php';
requireAdminLogin();

$action = $_GET['action'] ?? 'list';
$paymentId = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle payment approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'approve' || $action === 'reject')) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $status = $action === 'approve' ? 'completed' : 'failed';
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        try {
            $stmt = $pdo->prepare("
                UPDATE payments 
                SET status = ?, admin_notes = ?, processed_at = NOW(), processed_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $notes, $_SESSION['admin_id'], $paymentId]);
            
            if ($action === 'approve') {
                // Update user registration status
                $stmt = $pdo->prepare("
                    UPDATE tournament_registrations tr
                    JOIN payments p ON tr.id = p.registration_id
                    SET tr.status = 'approved'
                    WHERE p.id = ?
                ");
                $stmt->execute([$paymentId]);
            }
            
            $success = 'Payment ' . $action . 'd successfully';
            $action = 'list';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get payments list
if ($action === 'list') {
    $status = $_GET['status'] ?? '';
    $whereClause = '';
    $params = [];
    
    if ($status) {
        $whereClause = 'WHERE p.status = ?';
        $params[] = $status;
    }
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.email, t.name as tournament_name,
               tr.team_name, a.username as processed_by_name
        FROM payments p
        JOIN tournament_registrations tr ON p.registration_id = tr.id
        JOIN users u ON tr.user_id = u.id
        JOIN tournaments t ON tr.tournament_id = t.id
        LEFT JOIN admins a ON p.processed_by = a.id
        $whereClause
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    
    // Get status counts
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM payments 
        GROUP BY status
    ");
    $statusCounts = [];
    while ($row = $stmt->fetch()) {
        $statusCounts[$row['status']] = $row['count'];
    }
}

// Get payment details for approval
if (($action === 'approve' || $action === 'reject' || $action === 'view') && $paymentId) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.email, u.phone, t.name as tournament_name,
               tr.team_name, tr.created_at as registration_date
        FROM payments p
        JOIN tournament_registrations tr ON p.registration_id = tr.id
        JOIN users u ON tr.user_id = u.id
        JOIN tournaments t ON tr.tournament_id = t.id
        WHERE p.id = ?
    ");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        $error = 'Payment not found';
        $action = 'list';
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
    <title>Payment Management - eSports Tournament</title>
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
                        <i class="fas fa-credit-card text-accent"></i> Payment Management
                    </h2>
                    <?php if ($action !== 'list'): ?>
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
                    <!-- Payment Statistics -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="?" class="text-decoration-none">
                                <div class="gaming-card stat-card <?= !$_GET['status'] ?? '' ? 'border-accent' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h4 class="text-light mb-0"><?= array_sum($statusCounts) ?></h4>
                                            <p class="text-light-50 mb-0">Total Payments</p>
                                        </div>
                                        <i class="fas fa-credit-card fa-2x text-light"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="?status=pending" class="text-decoration-none">
                                <div class="gaming-card stat-card <?= ($_GET['status'] ?? '') === 'pending' ? 'border-warning' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h4 class="text-warning mb-0"><?= $statusCounts['pending'] ?? 0 ?></h4>
                                            <p class="text-light-50 mb-0">Pending</p>
                                        </div>
                                        <i class="fas fa-clock fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="?status=completed" class="text-decoration-none">
                                <div class="gaming-card stat-card <?= ($_GET['status'] ?? '') === 'completed' ? 'border-success' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h4 class="text-success mb-0"><?= $statusCounts['completed'] ?? 0 ?></h4>
                                            <p class="text-light-50 mb-0">Completed</p>
                                        </div>
                                        <i class="fas fa-check-circle fa-2x text-success"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="?status=failed" class="text-decoration-none">
                                <div class="gaming-card stat-card <?= ($_GET['status'] ?? '') === 'failed' ? 'border-danger' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h4 class="text-danger mb-0"><?= $statusCounts['failed'] ?? 0 ?></h4>
                                            <p class="text-light-50 mb-0">Failed</p>
                                        </div>
                                        <i class="fas fa-times-circle fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Payment List -->
                    <div class="gaming-card">
                        <?php if (empty($payments)): ?>
                            <div class="text-center text-light-50 py-5">
                                <i class="fas fa-credit-card fa-4x mb-3"></i>
                                <h4>No Payments Found</h4>
                                <p>No payment records match your criteria</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover">
                                    <thead>
                                        <tr>
                                            <th>Transaction ID</th>
                                            <th>User</th>
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
                                                    <strong><?= htmlspecialchars($payment['username']) ?></strong>
                                                    <br><small class="text-light-50"><?= htmlspecialchars($payment['email']) ?></small>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($payment['tournament_name']) ?>
                                                    <br><small class="text-light-50">Team: <?= htmlspecialchars($payment['team_name']) ?></small>
                                                </td>
                                                <td class="fw-bold"><?= formatTaka($payment['amount']) ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?= strtoupper($payment['method']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                        <?= ucfirst($payment['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= formatDate($payment['created_at']) ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="?action=view&id=<?= $payment['id'] ?>" class="btn btn-sm btn-outline-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($payment['status'] === 'pending'): ?>
                                                            <a href="?action=approve&id=<?= $payment['id'] ?>" class="btn btn-sm btn-success">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                            <a href="?action=reject&id=<?= $payment['id'] ?>" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                <?php elseif (($action === 'approve' || $action === 'reject') && isset($payment)): ?>
                    <!-- Payment Approval Form -->
                    <div class="gaming-card">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="text-<?= $action === 'approve' ? 'success' : 'danger' ?>">
                                    <i class="fas fa-<?= $action === 'approve' ? 'check' : 'times' ?>"></i>
                                    <?= ucfirst($action) ?> Payment
                                </h4>
                                
                                <table class="table table-dark">
                                    <tr>
                                        <th>Transaction ID:</th>
                                        <td><code class="text-accent"><?= htmlspecialchars($payment['transaction_id']) ?></code></td>
                                    </tr>
                                    <tr>
                                        <th>User:</th>
                                        <td><?= htmlspecialchars($payment['username']) ?> (<?= htmlspecialchars($payment['email']) ?>)</td>
                                    </tr>
                                    <tr>
                                        <th>Tournament:</th>
                                        <td><?= htmlspecialchars($payment['tournament_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Team:</th>
                                        <td><?= htmlspecialchars($payment['team_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Amount:</th>
                                        <td class="fw-bold"><?= formatTaka($payment['amount']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Method:</th>
                                        <td><span class="badge bg-info"><?= strtoupper($payment['method']) ?></span></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <span class="badge bg-warning">Pending</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Date:</th>
                                        <td><?= formatDate($payment['created_at']) ?></td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="col-md-6">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    
                                    <div class="mb-3">
                                        <label for="notes" class="form-label text-light">Admin Notes</label>
                                        <textarea class="form-control gaming-input" id="notes" name="notes" rows="5" 
                                                  placeholder="Enter notes about this payment..."></textarea>
                                    </div>
                                    
                                    <div class="alert alert-<?= $action === 'approve' ? 'success' : 'warning' ?>">
                                        <i class="fas fa-info-circle"></i>
                                        <?php if ($action === 'approve'): ?>
                                            Approving this payment will automatically approve the tournament registration.
                                        <?php else: ?>
                                            Rejecting this payment will mark the registration as failed. The user will need to pay again.
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-<?= $action === 'approve' ? 'success' : 'danger' ?>">
                                            <i class="fas fa-<?= $action === 'approve' ? 'check' : 'times' ?>"></i>
                                            <?= ucfirst($action) ?> Payment
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($action === 'view' && isset($payment)): ?>
                    <!-- Payment Details View -->
                    <div class="gaming-card">
                        <h4 class="text-accent mb-4">
                            <i class="fas fa-credit-card"></i> Payment Details
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="text-light mb-3">Payment Information</h5>
                                <table class="table table-dark">
                                    <tr>
                                        <th>Transaction ID:</th>
                                        <td><code class="text-accent"><?= htmlspecialchars($payment['transaction_id']) ?></code></td>
                                    </tr>
                                    <tr>
                                        <th>Amount:</th>
                                        <td class="fw-bold"><?= formatTaka($payment['amount']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Method:</th>
                                        <td><span class="badge bg-info"><?= strtoupper($payment['method']) ?></span></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <span class="badge bg-<?= $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                <?= ucfirst($payment['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Date:</th>
                                        <td><?= formatDate($payment['created_at']) ?></td>
                                    </tr>
                                    <?php if ($payment['processed_at']): ?>
                                        <tr>
                                            <th>Processed:</th>
                                            <td><?= formatDate($payment['processed_at']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="text-light mb-3">User & Tournament Information</h5>
                                <table class="table table-dark">
                                    <tr>
                                        <th>User:</th>
                                        <td><?= htmlspecialchars($payment['username']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td><?= htmlspecialchars($payment['email']) ?></td>
                                    </tr>
                                    <?php if ($payment['phone']): ?>
                                        <tr>
                                            <th>Phone:</th>
                                            <td><?= htmlspecialchars($payment['phone']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>Tournament:</th>
                                        <td><?= htmlspecialchars($payment['tournament_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Team Name:</th>
                                        <td><?= htmlspecialchars($payment['team_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Registration Date:</th>
                                        <td><?= formatDate($payment['registration_date']) ?></td>
                                    </tr>
                                </table>
                                
                                <?php if ($payment['admin_notes']): ?>
                                    <div class="mt-3">
                                        <h6 class="text-light">Admin Notes:</h6>
                                        <div class="alert alert-info">
                                            <?= nl2br(htmlspecialchars($payment['admin_notes'])) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($payment['status'] === 'pending'): ?>
                                    <div class="mt-3">
                                        <a href="?action=approve&id=<?= $payment['id'] ?>" class="btn btn-success me-2">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                        <a href="?action=reject&id=<?= $payment['id'] ?>" class="btn btn-danger">
                                            <i class="fas fa-times"></i> Reject
                                        </a>
                                    </div>
                                <?php endif; ?>
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
