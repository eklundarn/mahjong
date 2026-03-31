<?php
/**
 * Session Heartbeat API
 * 
 * Anropas regelbundet från JavaScript för att hålla sessionen vid liv
 * under pågående matcher eller annan aktivitet.
 * 
 * Timeout-hantering sker i config.php baserat på HTTP_REFERER.
 * Denna fil behöver bara returnera status.
 */

// config.php hanterar session start, timeout-check och last_activity-uppdatering
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

// Sessionen lever — last_activity uppdaterades redan av config.php
$remaining = $_effective_timeout - (time() - ($_SESSION['last_activity'] ?? time()));

echo json_encode([
    'success' => true,
    'session_active' => true,
    'time_remaining' => max(0, $remaining),
    'timeout_setting' => $_effective_timeout,
    'message' => 'Session uppdaterad'
]);
