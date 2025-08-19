<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tournamentId = (int)($_GET['tournament_id'] ?? 0);

if (!$tournamentId) {
    http_response_code(400);
    echo json_encode(['error' => 'Tournament ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username as name, tr.team_name
        FROM tournament_registrations tr
        JOIN users u ON tr.user_id = u.id
        WHERE tr.tournament_id = ? AND tr.status = 'approved'
        ORDER BY u.username
    ");
    $stmt->execute([$tournamentId]);
    $teams = $stmt->fetchAll();
    
    // Format response
    $formattedTeams = [];
    foreach ($teams as $team) {
        $formattedTeams[] = [
            'id' => $team['id'],
            'name' => $team['team_name'] ? $team['name'] . ' (' . $team['team_name'] . ')' : $team['name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'teams' => $formattedTeams
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
