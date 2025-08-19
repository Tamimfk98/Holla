<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

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

$fullName = sanitizeInput($_POST['full_name'] ?? '');
$phone = sanitizeInput($_POST['phone'] ?? '');

if (empty($fullName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Full name is required']);
    exit;
}

try {
    $auth = new Auth($pdo);
    $result = $auth->updateProfile($_SESSION['user_id'], [
        'full_name' => $fullName,
        'phone' => $phone
    ]);
    
    if ($result['success']) {
        // Update session data
        $_SESSION['full_name'] = $fullName;
        
        echo json_encode([
            'success' => true,
            'message' => $result['message']
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $result['message']]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
