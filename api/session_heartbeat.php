<?php
/**
 * Session Heartbeat API
 * 
 * Anropas regelbundet från JavaScript för att hålla sessionen vid liv.
 * Timeout gäller BARA admin-roller (hanteras i config.php).
 * Vanliga spelare har ingen timeout.
 *
 * Upload: www/api/session_heartbeat.php
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Om config.php redan förstörde sessionen (timeout) har vi inget user_id
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Session har gått ut',
        'session_active' => false,
        'session_expired' => true
    ]);
    exit;
}

// Check if user is admin role (same logic as config.php)
$_admin_roles_hb = ['superuser', 'vms_superuser', 'admin', 'mainadmin'];
$_user_role_hb = $_SESSION['role'] ?? '';
$_is_admin_hb = in_array($_user_role_hb, $_admin_roles_hb);

if ($_is_admin_hb && isset($_effective_timeout)) {
    // Admin — beräkna kvarvarande tid
    $remaining = $_effective_timeout - (time() - ($_SESSION['last_activity'] ?? time()));
    echo json_encode([
        'success' => true,
        'session_active' => true,
        'time_remaining' => max(0, $remaining),
        'timeout_setting' => $_effective_timeout,
        'message' => 'Session uppdaterad'
    ]);
} else {
    // Vanlig spelare — ingen timeout, rapportera gott om tid
    echo json_encode([
        'success' => true,
        'session_active' => true,
        'time_remaining' => 99999,
        'timeout_setting' => 0,
        'message' => 'Session uppdaterad (ingen timeout)'
    ]);
}
