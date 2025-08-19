<?php
require_once '../config/config.php';
requireAdminLogin();

$action = $_GET['action'] ?? 'list';
$registrationId = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'approve') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $status = sanitizeInput($_POST['status'] ?? '');
        $adminNotes = sanitizeInput($_POST['admin_notes'] ?? '');
        
        if (in_array($status, ['approved', 'rejected'])) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE tournament_registrations 
                    SET status = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$status, $registrationId]);
                
                // Log the action
                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs (admin_id, action, table_name, record_id, new_values, created_at)
                    VALUES (?, 'UPDATE', 'tournament_registrations', ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([
                    $_SESSION['admin_id'], 
                    $registrationId, 
                    json_encode(['status' => $status, 'notes' => $adminNotes])
                ]);
                
                // Create notification for user
                $stmt = $pdo->prepare("
                    SELECT tr.user_id, t.name as tournament_name
                    FROM tournament_registrations tr
                    JOIN tournaments t ON tr.tournament_id = t.id
                    WHERE tr.id = ?
                ");
                $stmt->execute([$registrationId]);
                $reg = $stmt->fetch();
                
                if ($reg) {
                    $message = $status === 'approved' 
                        ? "Your registration for '{$reg['tournament_name']}' has been approved!"
                        : "Your registration for '{$reg['tournament_name']}' has been rejected.";
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type, created_at)
                        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ");
                    $stmt->execute([
                        $reg['user_id'],
                        'Registration ' . ucfirst($status),
                        $message,
                        $status === 'approved' ? 'success' : 'warning'
                    ]);
                }
                
                $success = 'Registration ' . $status . ' successfully!';
                $action = 'list';
            } catch (PDOException $e) {
                $error = 'Failed to update registration: ' . $e->getMessage();
            }
        } else {
            $error = 'Invalid status selected';
        }
    }
}

// Get registration details for approval
$registration = null;
if ($action === 'approve' && $registrationId) {
    $stmt = $pdo->prepare("
        SELECT tr.*, u.username, u.full_name, u.email, t.name as tournament_name, t.entry_fee
        FROM tournament_registrations tr
        JOIN users u ON tr.user_id = u.id
        JOIN tournaments t ON tr.tournament_id = t.id
        WHERE tr.id = ?
    ");
    $stmt->execute([$registrationId]);
    $registration = $stmt->fetch();
    
    if (!$registration) {
        $error = 'Registration not found';
        $action = 'list';
    }
}

// Get registrations list
if ($action === 'list') {
    $status = sanitizeInput($_GET['status'] ?? '');
    $tournament = sanitizeInput($_GET['tournament'] ?? '');
    
    $whereClause = 'WHERE 1=1';
    $params = [];
    
    if ($status) {
        $whereClause .= ' AND tr.status = ?';
        $params[] = $status;
    }
    
    if ($tournament) {
        $whereClause .= ' AND tr.tournament_id = ?';
        $params[] = $tournament;
    }
    
    $stmt = $pdo->prepare("
        SELECT tr.*, u.username, u.full_name, t.name as tournament_name, t.entry_fee,
               p.status as payment_status, p.amount as payment_amount
        FROM tournament_registrations tr
        JOIN users u ON tr.user_id = u.id
        JOIN tournaments t ON tr.tournament_id = t.id
        LEFT JOIN payments p ON tr.id = p.registration_id
        $whereClause
        ORDER BY tr.created_at DESC
    ");
    $stmt->execute($params);
    $registrations = $stmt->fetchAll();
    
    // Get tournaments for filter
    $stmt = $pdo->query("SELECT id, name FROM tournaments ORDER BY created_at DESC");
    $tournaments = $stmt->fetchAll();
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
    <title>Registration Management - Admin</title>
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
            
            <div class="col-lg-9 col-xl-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-light">
                        <i class="fas fa-user-check text-accent"></i> Registration Management
                    </h2>
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
                    <!-- Filters -->
                    <div class="gaming-card mb-4">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <select class="form-control gaming-input" name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-control gaming-input" name="tournament">
                                    <option value="">All Tournaments</option>
                                    <?php foreach ($tournaments as $t): ?>
                                        <option value="<?= $t['id'] ?>" <?= $tournament == $t['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-accent w-100">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Registrations Table -->
                    <div class="gaming-card">
                        <?php if (empty($registrations)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-user-check fa-4x text-accent mb-3"></i>
                                <h4>No Registrations Found</h4>
                                <p class="text-light-50">No registrations match your current filters</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Tournament</th>
                                            <th>Team Name</th>
                                            <th>Entry Fee</th>
                                            <th>Payment Status</th>
                                            <th>Status</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($registrations as $reg): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($reg['full_name']) ?></strong><br>
                                                    <small class="text-light-50">@<?= htmlspecialchars($reg['username']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($reg['tournament_name']) ?></td>
                                                <td><?= htmlspecialchars($reg['team_name']) ?></td>
                                                <td><?= formatTaka($reg['entry_fee']) ?></td>
                                                <td>
                                                    <?php if ($reg['payment_status']): ?>
                                                        <span class="badge bg-<?= $reg['payment_status'] === 'completed' ? 'success' : ($reg['payment_status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                            <?= ucfirst($reg['payment_status']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No Payment</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $reg['status'] === 'approved' ? 'success' : ($reg['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                        <?= ucfirst($reg['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= formatDate($reg['created_at']) ?></td>
                                                <td>
                                                    <?php if ($reg['status'] === 'pending'): ?>
                                                        <a href="registrations.php?action=approve&id=<?= $reg['id'] ?>" class="btn btn-sm btn-accent">
                                                            <i class="fas fa-check"></i> Review
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-light-50">Processed</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                
                <?php elseif ($action === 'approve' && $registration): ?>
                    <!-- Approval Form -->
                    <div class="gaming-card">
                        <h4 class="text-accent mb-4">
                            <i class="fas fa-user-check"></i> Review Registration
                        </h4>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-light">User Information</h6>
                                <p><strong>Name:</strong> <?= htmlspecialchars($registration['full_name']) ?></p>
                                <p><strong>Username:</strong> @<?= htmlspecialchars($registration['username']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($registration['email']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-light">Tournament Information</h6>
                                <p><strong>Tournament:</strong> <?= htmlspecialchars($registration['tournament_name']) ?></p>
                                <p><strong>Team Name:</strong> <?= htmlspecialchars($registration['team_name']) ?></p>
                                <p><strong>Entry Fee:</strong> <?= formatTaka($registration['entry_fee']) ?></p>
                                <?php if ($registration['team_members']): ?>
                                    <p><strong>Team Members:</strong><br><?= nl2br(htmlspecialchars($registration['team_members'])) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Decision</label>
                                    <select class="form-control gaming-input" name="status" required>
                                        <option value="">Select Decision</option>
                                        <option value="approved">Approve Registration</option>
                                        <option value="rejected">Reject Registration</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Admin Notes (Optional)</label>
                                    <textarea class="form-control gaming-input" name="admin_notes" rows="3" placeholder="Any notes or feedback for the user"></textarea>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-accent">
                                    <i class="fas fa-check"></i> Process Registration
                                </button>
                                <a href="registrations.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to List
                                </a>
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