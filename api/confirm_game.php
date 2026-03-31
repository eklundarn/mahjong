<?php
/**
 * api/confirm_game.php
 * AJAX endpoint for player confirmation (OK) of games
 * Also handles admin approve (fastställ)
 *
 * Upload: www/api/confirm_game.php
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit;
}

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$conn = getDbConnection();
$game_id = (int)($_POST['game_id'] ?? 0);
$action = $_POST['action'] ?? '';
$current_user_id = $_SESSION['user_id'] ?? '';

if ($game_id <= 0) {
    echo json_encode(['error' => 'Invalid game ID']);
    exit;
}

// Fetch game
$stmt = $conn->prepare("SELECT * FROM stat_games WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$game_id]);
$game = $stmt->fetch();

if (!$game) {
    echo json_encode(['error' => 'Game not found']);
    exit;
}

// Check if player confirmation feature is enabled
$require_confirmation = defined('REQUIRE_PLAYER_CONFIRMATION') && REQUIRE_PLAYER_CONFIRMATION;

if ($action === 'confirm') {
    // Player confirms (OK) their participation
    // Find which slot this player is in
    $slot = null;
    foreach (['a', 'b', 'c', 'd'] as $s) {
        if ($game['player_' . $s . '_vms'] === $current_user_id) {
            $slot = $s;
            break;
        }
    }
    
    if (!$slot) {
        echo json_encode(['error' => 'You are not a player in this game']);
        exit;
    }
    
    // Check if already confirmed
    if ($game['player_' . $slot . '_confirmed_at']) {
        echo json_encode(['error' => 'Already confirmed']);
        exit;
    }
    
    // Check if game is already approved (no changes allowed)
    if ($game['approved']) {
        echo json_encode(['error' => 'Game already approved']);
        exit;
    }
    
    // Set confirmation
    $stmt = $conn->prepare("UPDATE stat_games SET player_{$slot}_confirmed_at = NOW(), player_{$slot}_confirmed_by = ? WHERE id = ?");
    $stmt->execute([$current_user_id, $game_id]);
    
    // Count confirmations
    $confirmed = 0;
    foreach (['a', 'b', 'c', 'd'] as $s) {
        if ($s === $slot || $game['player_' . $s . '_confirmed_at']) {
            $confirmed++;
        }
    }
    
    echo json_encode([
        'success' => true, 
        'slot' => $slot,
        'confirmed_count' => $confirmed,
        'all_confirmed' => ($confirmed >= 4)
    ]);
    
} elseif ($action === 'approve') {
    // Admin approves (fastställer) the game
    if (!canApproveGames()) {
        echo json_encode(['error' => 'Not authorized']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE stat_games SET approved = 1, approved_by_id = ?, approved_at = NOW() WHERE id = ?");
    $stmt->execute([$current_user_id, $game_id]);
    
    echo json_encode(['success' => true, 'approved' => true]);
    
} else {
    echo json_encode(['error' => 'Invalid action']);
}
