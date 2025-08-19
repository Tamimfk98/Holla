<?php
require_once '../config/config.php';
requireAdminLogin();

$action = $_GET['action'] ?? 'list';
$withdrawalId = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle withdrawal approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        if ($action === 'process' && $withdrawalId) {
            $status = sanitizeInput($_POST['status'] ?? '');
            $adminNotes = sanitizeInput($_POST['admin_notes'] ?? '');
            
            if (in_array($status, ['approved', 'rejected'])) {
                try {
                    $pdo->beginTransaction();
                    
                    // Get withdrawal details
                    $stmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE id = ?");
                    $stmt->execute([$withdrawalId]);
                    $withdrawal = $stmt->fetch();
                    
                    if ($withdrawal && $withdrawal['status'] === 'pending') {
                        // Update withdrawal status
                        $stmt = $pdo->prepare("
                            UPDATE withdrawal_requests 
                            SET status = ?, admin_notes = ?, processed_at = CURRENT_TIMESTAMP, processed_by = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$status, $adminNotes, $_SESSION['admin_id'], $withdrawalId]);
                        
                        // If rejected, return money to user's wallet
                        if ($status === 'rejected') {
                            $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                            $stmt->execute([$withdrawal['amount'], $withdrawal['user_id']]);
                            
                            // Create notification for rejection
                            $stmt = $pdo->prepare("
                                INSERT INTO notifications (user_id, title, message, type) 
                                VALUES (?, ?, ?, ?)
                            ");
                            $message = "Your withdrawal request of $" . number_format($withdrawal['amount'], 2) . " has been rejected.";
                            if ($adminNotes) {
                                $message .= " Reason: " . $adminNotes;
                            }
                            $message .= " The amount has been returned to your wallet.";
                            $stmt->execute([$withdrawal['user_id'], 'Withdrawal Request Rejected', $message, 'warning']);
                        } else {
                            // Create notification for approval
                            $stmt = $pdo->prepare("
                                INSERT INTO notifications (user_id, title, message, type) 
                                VALUES (?, ?, ?, ?)
                            ");
                            $message = "Your withdrawal request of $" . number_format($withdrawal['amount'], 2) . " has been approved! You will receive the money via " . ucfirst($withdrawal['payment_method']) . " shortly.";
                            if ($adminNotes) {
                                $message .= " Note: " . $adminNotes;
                            }
                            $stmt->execute([$withdrawal['user_id'], 'Withdrawal Request Approved', $message, 'success']);
                        }
                        
                        $pdo->commit();
                        $success = 'Withdrawal request ' . $status . ' successfully';
                        $action = 'list';
                    } else {
                        $error = 'Withdrawal request not found or already processed';
                    }
                } catch (PDOException $e) {
                    $pdo->rollback();
                    $error = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error = 'Invalid status';
            }
        }
    }
}

// Get withdrawal requests based on action
if ($action === 'view' && $withdrawalId) {
    $stmt = $pdo->prepare("
        SELECT wr.*, u.username, u.full_name, u.email, admin.username as processed_by_name
        FROM withdrawal_requests wr 
        JOIN users u ON wr.user_id = u.id 
        LEFT JOIN users admin ON wr.processed_by = admin.id
        WHERE wr.id = ?
    ");
    $stmt->execute([$withdrawalId]);
    $withdrawal = $stmt->fetch();
    
    if (!$withdrawal) {
        redirect('withdrawals.php', 'Withdrawal request not found', 'error');
    }
} else {
    // Get all withdrawal requests
    $status = $_GET['status'] ?? 'all';
    $whereClause = '';
    $params = [];
    
    if ($status !== 'all') {
        $whereClause = ' WHERE wr.status = ?';
        $params[] = $status;
    }
    
    $stmt = $pdo->prepare("
        SELECT wr.*, u.username, u.full_name, u.email 
        FROM withdrawal_requests wr 
        JOIN users u ON wr.user_id = u.id 
        $whereClause
        ORDER BY wr.requested_at DESC
    ");
    $stmt->execute($params);
    $withdrawals = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_approved_amount
        FROM withdrawal_requests
    ");
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
    <title>Withdrawal Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-page">
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="text-accent">
                        <i class="fas fa-money-bill-wave"></i> Withdrawal Management
                    </h1>
                    <?php if ($action === 'view'): ?>
                        <a href="withdrawals.php" class="btn btn-outline-secondary">
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
                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="gaming-card text-center">
                                <h3 class="text-accent"><?= $stats['total'] ?></h3>
                                <p class="mb-0">Total Requests</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="gaming-card text-center">
                                <h3 class="text-warning"><?= $stats['pending'] ?></h3>
                                <p class="mb-0">Pending</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="gaming-card text-center">
                                <h3 class="text-success"><?= $stats['approved'] ?></h3>
                                <p class="mb-0">Approved</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="gaming-card text-center">
                                <h3 class="text-accent">$<?= number_format($stats['total_approved_amount'], 2) ?></h3>
                                <p class="mb-0">Total Paid</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Tabs -->
                    <div class="gaming-card mb-4">
                        <ul class="nav nav-tabs nav-fill">
                            <li class="nav-item">
                                <a class="nav-link <?= $status === 'all' ? 'active' : '' ?>" href="?status=all">
                                    All Requests
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $status === 'pending' ? 'active' : '' ?>" href="?status=pending">
                                    Pending (<?= $stats['pending'] ?>)
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $status === 'approved' ? 'active' : '' ?>" href="?status=approved">
                                    Approved
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $status === 'rejected' ? 'active' : '' ?>" href="?status=rejected">
                                    Rejected
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Withdrawal Requests List -->
                    <div class="gaming-card">
                        <h4 class="text-accent mb-3">Withdrawal Requests</h4>
                        
                        <?php if (empty($withdrawals)): ?>
                            <div class="text-center text-light-50 py-4">
                                <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                                <p>No withdrawal requests found</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Account</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($withdrawals as $wr): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($wr['full_name']) ?></strong><br>
                                                        <small class="text-light-50">@<?= htmlspecialchars($wr['username']) ?></small>
                                                    </div>
                                                </td>
                                                <td class="fw-bold">$<?= number_format($wr['amount'], 2) ?></td>
                                                <td><?= ucfirst($wr['payment_method']) ?></td>
                                                <td><?= htmlspecialchars($wr['account_number']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $wr['status'] === 'approved' ? 'success' : 
                                                        ($wr['status'] === 'rejected' ? 'danger' : 'warning') 
                                                    ?>">
                                                        <?= ucfirst($wr['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= formatDate($wr['requested_at'], 'M d, Y H:i') ?></td>
                                                <td>
                                                    <a href="?action=view&id=<?= $wr['id'] ?>" class="btn btn-sm btn-outline-info">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                
                <?php elseif ($action === 'view'): ?>
                    <!-- View Withdrawal Details -->
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="gaming-card">
                                <h4 class="text-accent mb-3">
                                    <i class="fas fa-receipt"></i> Withdrawal Details
                                </h4>
                                
                                <div class="mb-3">
                                    <strong>Request ID:</strong> #<?= $withdrawal['id'] ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Amount:</strong> 
                                    <span class="text-accent fs-5">$<?= number_format($withdrawal['amount'], 2) ?></span>
                                </div>
                                <div class="mb-3">
                                    <strong>Payment Method:</strong> <?= ucfirst($withdrawal['payment_method']) ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Account Number:</strong> <?= htmlspecialchars($withdrawal['account_number']) ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Status:</strong> 
                                    <span class="badge bg-<?= 
                                        $withdrawal['status'] === 'approved' ? 'success' : 
                                        ($withdrawal['status'] === 'rejected' ? 'danger' : 'warning') 
                                    ?>">
                                        <?= ucfirst($withdrawal['status']) ?>
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <strong>Requested At:</strong> <?= formatDate($withdrawal['requested_at'], 'M d, Y H:i') ?>
                                </div>
                                <?php if ($withdrawal['processed_at']): ?>
                                    <div class="mb-3">
                                        <strong>Processed At:</strong> <?= formatDate($withdrawal['processed_at'], 'M d, Y H:i') ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Processed By:</strong> <?= htmlspecialchars($withdrawal['processed_by_name']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($withdrawal['admin_notes']): ?>
                                    <div class="mb-3">
                                        <strong>Admin Notes:</strong><br>
                                        <div class="alert alert-info mt-2">
                                            <?= nl2br(htmlspecialchars($withdrawal['admin_notes'])) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="gaming-card">
                                <h4 class="text-accent mb-3">
                                    <i class="fas fa-user"></i> User Information
                                </h4>
                                
                                <div class="mb-3">
                                    <strong>Name:</strong> <?= htmlspecialchars($withdrawal['full_name']) ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Username:</strong> @<?= htmlspecialchars($withdrawal['username']) ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Email:</strong> <?= htmlspecialchars($withdrawal['email']) ?>
                                </div>
                                
                                <?php if ($withdrawal['status'] === 'pending'): ?>
                                    <hr>
                                    <h5 class="text-accent mb-3">Process Request</h5>
                                    
                                    <form method="POST" action="?action=process&id=<?= $withdrawal['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        
                                        <div class="mb-3">
                                            <label for="status" class="form-label text-light">Decision *</label>
                                            <select class="form-select gaming-input" id="status" name="status" required>
                                                <option value="">Select Decision</option>
                                                <option value="approved">Approve</option>
                                                <option value="rejected">Reject</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="admin_notes" class="form-label text-light">Notes (Optional)</label>
                                            <textarea class="form-control gaming-input" id="admin_notes" name="admin_notes" 
                                                      rows="3" placeholder="Add any notes for the user..."></textarea>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-accent">
                                                <i class="fas fa-check"></i> Process Request
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>