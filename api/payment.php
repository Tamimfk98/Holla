<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

$action = $_POST['action'] ?? '';
$registrationId = (int)($_POST['registration_id'] ?? 0);

try {
    switch ($action) {
        case 'initiate':
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
                http_response_code(404);
                echo json_encode(['error' => 'Registration not found']);
                exit;
            }
            
            // Check if payment already exists
            $stmt = $pdo->prepare("SELECT id FROM payments WHERE registration_id = ?");
            $stmt->execute([$registrationId]);
            
            if ($stmt->rowCount() > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Payment already initiated']);
                exit;
            }
            
            // Create payment session
            $paymentData = [
                'registration_id' => $registrationId,
                'amount' => $registration['entry_fee'],
                'tournament_name' => $registration['tournament_name'],
                'team_name' => $registration['team_name']
            ];
            
            echo json_encode([
                'success' => true,
                'payment_data' => $paymentData,
                'message' => 'Payment session created'
            ]);
            break;
            
        case 'verify':
            $transactionId = sanitizeInput($_POST['transaction_id'] ?? '');
            $method = sanitizeInput($_POST['method'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            
            if (empty($transactionId) || empty($method) || empty($phone)) {
                http_response_code(400);
                echo json_encode(['error' => 'All payment details are required']);
                exit;
            }
            
            // Get registration details
            $stmt = $pdo->prepare("
                SELECT tr.*, t.entry_fee
                FROM tournament_registrations tr
                JOIN tournaments t ON tr.tournament_id = t.id
                WHERE tr.id = ? AND tr.user_id = ?
            ");
            $stmt->execute([$registrationId, $_SESSION['user_id']]);
            $registration = $stmt->fetch();
            
            if (!$registration) {
                http_response_code(404);
                echo json_encode(['error' => 'Registration not found']);
                exit;
            }
            
            // Create payment record
            $stmt = $pdo->prepare("
                INSERT INTO payments (registration_id, amount, method, phone, transaction_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$registrationId, $registration['entry_fee'], $method, $phone, $transactionId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Payment submitted for verification',
                'payment_id' => $pdo->lastInsertId()
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
