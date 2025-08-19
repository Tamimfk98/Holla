<?php
require_once '../config/config.php';
requireAdminLogin();

$action = $_GET['action'] ?? 'list';
$userId = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        switch ($action) {
            case 'update_status':
                $status = sanitizeInput($_POST['status'] ?? '');
                
                if ($userId && in_array($status, ['active', 'suspended', 'banned'])) {
                    try {
                        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                        $stmt->execute([$status, $userId]);
                        $success = 'User status updated successfully';
                        $action = 'list';
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Invalid status selected';
                }
                break;
                
            case 'update_wallet':
                $amount = (float)($_POST['amount'] ?? 0);
                $operation = $_POST['operation'] ?? 'add';
                
                if ($userId && $amount > 0) {
                    try {
                        $pdo->beginTransaction();
                        
                        $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $currentBalance = $stmt->fetchColumn();
                        
                        $newBalance = $operation === 'add' 
                            ? $currentBalance + $amount 
                            : max(0, $currentBalance - $amount);
                        
                        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
                        $stmt->execute([$newBalance, $userId]);
                        
                        // Log wallet transaction
                        $stmt = $pdo->prepare("
                            INSERT INTO wallet_transactions (user_id, type, amount, balance_after, description, created_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $description = $operation === 'add' ? 'Admin credit' : 'Admin debit';
                        $stmt->execute([$userId, $operation, $amount, $newBalance, $description, $_SESSION['admin_id']]);
                        
                        $pdo->commit();
                        $success = 'Wallet balance updated successfully';
                        $action = 'view';
                    } catch (PDOException $e) {
                        $pdo->rollback();
                        $error = 'Database error: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Please enter a valid amount';
                }
                break;
        }
    }
}

// Get user data for viewing/editing
$user = null;
if (($action === 'view' || $action === 'edit' || $action === 'update_status') && $userId) {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT tr.id) as total_registrations,
               COUNT(DISTINCT p.id) as total_payments,
               SUM(p.amount) as total_spent
        FROM users u
        LEFT JOIN tournament_registrations tr ON u.id = tr.user_id
        LEFT JOIN payments p ON tr.id = p.registration_id AND p.status = 'completed'
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = 'User not found';
        $action = 'list';
    }
}

// Get users list
if ($action === 'list') {
    $search = sanitizeInput($_GET['search'] ?? '');
    $status = sanitizeInput($_GET['status'] ?? '');
    
    $whereClause = 'WHERE 1=1';
    $params = [];
    
    if ($search) {
        $whereClause .= ' AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)';
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($status) {
        $whereClause .= ' AND u.status = ?';
        $params[] = $status;
    }
    
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT tr.id) as registrations,
               SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as total_spent
        FROM users u
        LEFT JOIN tournament_registrations tr ON u.id = tr.user_id
        LEFT JOIN payments p ON tr.id = p.registration_id
        $whereClause
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Get status counts
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM users 
        GROUP BY status
    ");
    $statusCounts = [];
    while ($row = $stmt->fetch()) {
        $statusCounts[$row['status']] = $row['count'];
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
    <title>User Management - eSports Tournament</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <div class="col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-light">
                        <i class="fas fa-users text-accent"></i> User Management
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
                    <!-- User Statistics -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="?" class="text-decoration-none">
                                <div class="gaming-card stat-card <?= !$_GET['status'] ?? '' ? 'border-accent' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h4 class="text-light mb-0"><?= array_sum($statusCounts) ?></h4>
                                            <p class="text-light-50 mb-0">Total Users</p>
                                        </div>
                                        <i class="fas fa-users fa-2x text-light"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="?status=active" class="text-decoration-none">
                                <div class="gaming-card stat-card <?= ($_GET['status'] ?? '') === 'active' ? 'border-success' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h4 class="text-success mb-0"><?= $statusCounts['active'] ?? 0 ?></h4>
                                            <p class="text-light-50 mb-0">Active</p>
                                        </div>
                                        <i class="fas fa-user-check fa-2x text-success"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="?status=suspended" class="text-decoration-none">
                                <div class="gaming-card stat-card <?= ($_GET['status'] ?? '') === 'suspended' ? 'border-warning' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h4 class="text-warning mb-0"><?= $statusCounts['suspended'] ?? 0 ?></h4>
                                            <p class="text-light-50 mb-0">Suspended</p>
                                        </div>
                                        <i class="fas fa-user-clock fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="?status=banned" class="text-decoration-none">
                                <div class="gaming-card stat-card <?= ($_GET['status'] ?? '') === 'banned' ? 'border-danger' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h4 class="text-danger mb-0"><?= $statusCounts['banned'] ?? 0 ?></h4>
                                            <p class="text-light-50 mb-0">Banned</p>
                                        </div>
                                        <i class="fas fa-user-slash fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Search and Filters -->
                    <div class="gaming-card mb-4">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" class="form-control gaming-input" name="search" 
                                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                                       placeholder="Search users by username, email or name...">
                            </div>
                            <div class="col-md-3">
                                <select class="form-control gaming-input" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="suspended" <?= ($_GET['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                    <option value="banned" <?= ($_GET['status'] ?? '') === 'banned' ? 'selected' : '' ?>>Banned</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-accent w-100">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- User List -->
                    <div class="gaming-card">
                        <?php if (empty($users)): ?>
                            <div class="text-center text-light-50 py-5">
                                <i class="fas fa-users fa-4x mb-3"></i>
                                <h4>No Users Found</h4>
                                <p>No users match your search criteria</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Registrations</th>
                                            <th>Total Spent</th>
                                            <th>Joined</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $u): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm bg-accent text-center rounded me-2">
                                                            <i class="fas fa-user text-dark"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?= htmlspecialchars($u['username']) ?></strong>
                                                            <br><small class="text-light-50"><?= htmlspecialchars($u['full_name']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($u['email']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : ($u['status'] === 'suspended' ? 'warning' : 'danger') ?>">
                                                        <?= ucfirst($u['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= $u['registrations'] ?></span>
                                                </td>
                                                <td class="text-warning fw-bold"><?= formatTaka($u['total_spent'] ?? 0) ?></td>
                                                <td><?= formatDate($u['created_at']) ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="?action=view&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                                    type="button" data-bs-toggle="dropdown">
                                                                <i class="fas fa-cog"></i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-dark">
                                                                <li><a class="dropdown-item" href="?action=update_status&id=<?= $u['id'] ?>">
                                                                    <i class="fas fa-user-edit"></i> Update Status
                                                                </a></li>
                                                                <li><a class="dropdown-item" href="?action=update_wallet&id=<?= $u['id'] ?>">
                                                                    <i class="fas fa-wallet"></i> Manage Wallet
                                                                </a></li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                <?php elseif ($action === 'view' && $user): ?>
                    <!-- User Details -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="gaming-card mb-4">
                                <h4 class="text-accent mb-3">
                                    <i class="fas fa-user"></i> User Profile
                                </h4>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-dark">
                                            <tr>
                                                <th>Username:</th>
                                                <td><?= htmlspecialchars($user['username']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Full Name:</th>
                                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Email:</th>
                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Phone:</th>
                                                <td><?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></td>
                                            </tr>
                                            <tr>
                                                <th>Status:</th>
                                                <td>
                                                    <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : ($user['status'] === 'suspended' ? 'warning' : 'danger') ?>">
                                                        <?= ucfirst($user['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <table class="table table-dark">
                                            <tr>
                                                <th>Wallet Balance:</th>
                                                <td class="text-success fw-bold"><?= formatTaka($user['wallet_balance']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Total Registrations:</th>
                                                <td><span class="badge bg-info"><?= $user['total_registrations'] ?></span></td>
                                            </tr>
                                            <tr>
                                                <th>Total Payments:</th>
                                                <td><span class="badge bg-warning"><?= $user['total_payments'] ?></span></td>
                                            </tr>
                                            <tr>
                                                <th>Total Spent:</th>
                                                <td class="text-warning fw-bold"><?= formatTaka($user['total_spent'] ?? 0) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Joined:</th>
                                                <td><?= formatDate($user['created_at']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Last Login:</th>
                                                <td><?= $user['last_login'] ? formatDate($user['last_login']) : 'Never' ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="gaming-card mb-4">
                                <h5 class="text-accent mb-3">Quick Actions</h5>
                                <div class="d-grid gap-2">
                                    <a href="?action=update_status&id=<?= $user['id'] ?>" class="btn btn-warning">
                                        <i class="fas fa-user-edit"></i> Update Status
                                    </a>
                                    <a href="?action=update_wallet&id=<?= $user['id'] ?>" class="btn btn-success">
                                        <i class="fas fa-wallet"></i> Manage Wallet
                                    </a>
                                    <a href="payments.php?user_id=<?= $user['id'] ?>" class="btn btn-info">
                                        <i class="fas fa-credit-card"></i> Payment History
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($action === 'update_status' && $user): ?>
                    <!-- Update Status Form -->
                    <div class="gaming-card">
                        <h4 class="text-accent mb-3">Update User Status</h4>
                        <p class="text-light-50 mb-4">User: <strong><?= htmlspecialchars($user['username']) ?></strong></p>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-3">
                                <label for="status" class="form-label text-light">Status</label>
                                <select class="form-control gaming-input" id="status" name="status" required>
                                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="suspended" <?= $user['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                    <option value="banned" <?= $user['status'] === 'banned' ? 'selected' : '' ?>>Banned</option>
                                </select>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-accent">
                                    <i class="fas fa-save"></i> Update Status
                                </button>
                            </div>
                        </form>
                    </div>
                    
                <?php elseif ($action === 'update_wallet' && $user): ?>
                    <!-- Update Wallet Form -->
                    <div class="gaming-card">
                        <h4 class="text-accent mb-3">Manage Wallet Balance</h4>
                        <p class="text-light-50 mb-4">
                            User: <strong><?= htmlspecialchars($user['username']) ?></strong><br>
                            Current Balance: <strong class="text-success"><?= formatTaka($user['wallet_balance']) ?></strong>
                        </p>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="operation" class="form-label text-light">Operation</label>
                                    <select class="form-control gaming-input" id="operation" name="operation" required>
                                        <option value="add">Add Money</option>
                                        <option value="subtract">Subtract Money</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="amount" class="form-label text-light">Amount (৳)</label>
                                    <input type="number" class="form-control gaming-input" id="amount" name="amount" 
                                           min="0.01" step="0.01" required>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-wallet"></i> Update Wallet
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
