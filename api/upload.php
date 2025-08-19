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

$matchId = (int)($_POST['match_id'] ?? 0);

if (!$matchId) {
    http_response_code(400);
    echo json_encode(['error' => 'Match ID is required']);
    exit;
}

if (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid screenshot file is required']);
    exit;
}

try {
    // Verify user is participant in this match
    $stmt = $pdo->prepare("
        SELECT m.*, t.name as tournament_name
        FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        WHERE m.id = ? AND (m.team1_id = ? OR m.team2_id = ?) 
        AND m.status IN ('live', 'active')
    ");
    $stmt->execute([$matchId, $_SESSION['user_id'], $_SESSION['user_id']]);
    $match = $stmt->fetch();
    
    if (!$match) {
        http_response_code(404);
        echo json_encode(['error' => 'Match not found or not accessible']);
        exit;
    }
    
    // Check if screenshot already uploaded
    $stmt = $pdo->prepare("
        SELECT id FROM match_screenshots 
        WHERE match_id = ? AND team_id = ?
    ");
    $stmt->execute([$matchId, $_SESSION['user_id']]);
    
    if ($stmt->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Screenshot already uploaded for this match']);
        exit;
    }
    
    // Upload screenshot
    $uploadResult = uploadFile($_FILES['screenshot'], 'screenshots/');
    
    if (!$uploadResult['success']) {
        http_response_code(400);
        echo json_encode(['error' => $uploadResult['message']]);
        exit;
    }
    
    // Save screenshot record
    $stmt = $pdo->prepare("
        INSERT INTO match_screenshots (match_id, team_id, screenshot_url, uploaded_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$matchId, $_SESSION['user_id'], $uploadResult['filename']]);
    
    $screenshotId = $pdo->lastInsertId();
    
    // Check if both teams have uploaded screenshots
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as upload_count
        FROM match_screenshots 
        WHERE match_id = ?
    ");
    $stmt->execute([$matchId]);
    $uploadCount = $stmt->fetchColumn();
    
    $matchStatus = 'active';
    if ($uploadCount >= 2) {
        // Both teams uploaded, match ready for review
        $stmt = $pdo->prepare("
            UPDATE matches 
            SET status = 'pending_review' 
            WHERE id = ? AND status IN ('live', 'active')
        ");
        $stmt->execute([$matchId]);
        $matchStatus = 'pending_review';
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Screenshot uploaded successfully',
        'screenshot_id' => $screenshotId,
        'filename' => $uploadResult['filename'],
        'match_status' => $matchStatus,
        'both_uploaded' => $uploadCount >= 2
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
