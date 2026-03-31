<?php
/**
 * API för att bekräfta lösenordsåterställning
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

// Kontrollera att det är en POST-förfrågan
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Endast POST-förfrågningar tillåtna']);
    exit;
}

// Läs JSON-data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['token']) || !isset($data['new_password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Token och nytt lösenord krävs']);
    exit;
}

$token = cleanInput($data['token']);
$new_password = $data['new_password']; // Validera men rensa inte lösenordet

// Validera lösenord (bara längd, inga andra krav)
if (strlen($new_password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Lösenordet måste vara minst 8 tecken långt']);
    exit;
}

try {
    $conn = getDbConnection();
    
    // Kolla om token finns och är giltig
    $stmt = $conn->prepare("
        SELECT email, expires_at, used_at 
        FROM password_resets 
        WHERE token = ?
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        http_response_code(400);
        echo json_encode(['error' => 'Ogiltig återställningslänk']);
        exit;
    }
    
    // Kolla om token redan använts
    if ($reset['used_at'] !== null) {
        http_response_code(400);
        echo json_encode(['error' => 'Denna återställningslänk har redan använts']);
        exit;
    }
    
    // Kolla om token har gått ut
    if (strtotime($reset['expires_at']) < time()) {
        http_response_code(400);
        echo json_encode(['error' => 'Återställningslänken har gått ut. Begär en ny.']);
        exit;
    }
    
    // Hitta användaren med denna e-postadress
    $stmt = $conn->prepare("SELECT id FROM players WHERE email = ?");
    $stmt->execute([$reset['email']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(400);
        echo json_encode(['error' => 'Användare hittades inte']);
        exit;
    }
    
    // Uppdatera lösenordet
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("
        UPDATE players 
        SET password_hash = ? 
        WHERE id = ?
    ");
    $stmt->execute([$hashed_password, $user['id']]);
    
    // Markera token som använd
    $stmt = $conn->prepare("
        UPDATE password_resets 
        SET used_at = NOW() 
        WHERE token = ?
    ");
    $stmt->execute([$token]);
    
    // Skicka bekräftelse-e-post (valfritt)
    try {
        require_once __DIR__ . '/../includes/EmailHelper.php';
        $emailHelper = new EmailHelper();
        
        // Hämta användarnamn
        $stmt = $conn->prepare("SELECT first_name FROM players WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userInfo = $stmt->fetch();
        
        if ($userInfo) {
            $emailHelper->sendPasswordChangedEmail($reset['email'], $userInfo['first_name']);
        }
    } catch (Exception $e) {
        // Logga fel men fortsätt ändå - lösenordet är redan ändrat
        error_log("Kunde inte skicka bekräftelse-e-post: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Lösenordet har återställts! Du kan nu logga in med ditt nya lösenord.'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ett tekniskt fel uppstod']);
    error_log("Password reset error: " . $e->getMessage());
}
?>
