<?php
// Minimal endpoint - sätter bara språket i sessionen, returnerar JSON
session_start();
if (isset($_GET['lang']) && in_array($_GET['lang'], ['sv', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
    echo json_encode(['ok' => true, 'lang' => $_GET['lang']]);
} else {
    http_response_code(400);
    echo json_encode(['ok' => false]);
}
