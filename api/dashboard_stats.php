<?php
header('Content-Type: application/json');
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $user_id = $_SESSION['user_id'];
    
    // Get user statistics
    $stats = [];
    
    // Total tournaments registered
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM tournament_registrations WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['tournaments_registered'] = $stmt->fetch()['count'];
    
    // Pending payments
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM payments p 
        JOIN tournament_registrations tr ON p.registration_id = tr.id 
        WHERE tr.user_id = ? AND p.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $stats['pending_payments'] = $stmt->fetch()['count'];
    
    // Total matches
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM matches 
        WHERE team1_id = ? OR team2_id = ?
    ");
    $stmt->execute([$user_id, $user_id]);
    $stats['total_matches'] = $stmt->fetch()['count'];
    
    // Matches won
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM matches 
        WHERE winner_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $stats['matches_won'] = $stmt->fetch()['count'];
    
    // Wallet balance
    $stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $stats['wallet_balance'] = $stmt->fetch()['wallet_balance'];
    
    echo json_encode($stats);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>