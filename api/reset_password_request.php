<?php
// DEBUG-version av reset_password_request.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Inkludera nödvändiga filer
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/EmailHelper.php';
    
    // Kontrollera att det är en POST-förfrågan
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Endast POST-förfrågningar tillåtna']);
        exit;
    }
    
    // Läs JSON-data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!isset($data['email'])) {
        echo json_encode(['error' => 'E-postadress saknas']);
        exit;
    }
    
    $email = cleanInput($data['email']);
    
    // Validera e-postadress
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'Ogiltig e-postadress']);
        exit;
    }
    
    // Anslut till databas
    $conn = getDbConnection();
    
    // Kolla om e-postadressen finns (men avslöja INTE detta för användaren av säkerhetsskäl)
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM players WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Generera token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Spara token i databas
        $stmt = $conn->prepare("
            INSERT INTO password_resets (email, token, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$email, $token, $expires]);
        
        // Skapa återställningslänk
        $reset_link = SITE_URL . '/reset-password.php?token=' . $token;
        
        // Skicka e-post med EmailHelper
        try {
            $emailHelper = new EmailHelper();
            $result = $emailHelper->sendPasswordResetEmail($email, $user['first_name'], $token);
            
            if (!$result) {
                echo json_encode([
                    'error' => 'Kunde inte skicka e-post',
                    'debug' => 'sendPasswordResetEmail returnerade false'
                ]);
                exit;
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Kunde inte skicka e-post']);
            error_log("Email error: " . $e->getMessage());
            exit;
        }
    }
    
    // Returnera alltid samma svar (säkerhet - avslöja inte om e-post finns)
    echo json_encode([
        'success' => true,
        'message' => 'Om e-postadressen finns i vårt system har ett återställningsmejl skickats.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ett tekniskt fel uppstod']);
    error_log("Password reset request error: " . $e->getMessage());
}
?>
