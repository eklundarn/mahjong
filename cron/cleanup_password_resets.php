<?php
/**
 * Cron-jobb för att rensa gamla lösenordsåterställningar
 * 
 * Kör detta skript regelbundet (t.ex. en gång per dag) för att:
 * 1. Radera utgångna tokens
 * 2. Radera använda tokens äldre än 7 dagar
 * 
 * Lägg till i crontab:
 * 0 2 * * * /usr/bin/php /path/to/cleanup_password_resets.php
 * (Detta kör skriptet kl 02:00 varje natt)
 */

require_once __DIR__ . '/../config/database.php';

try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Radera utgångna tokens
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE expires_at < NOW()");
    $stmt->execute();
    $expiredCount = $stmt->rowCount();
    
    // Radera använda tokens äldre än 7 dagar
    $stmt = $conn->prepare("
        DELETE FROM password_resets 
        WHERE used = 1 
        AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $usedCount = $stmt->rowCount();
    
    $totalDeleted = $expiredCount + $usedCount;
    
    // Logga resultat
    $logMessage = date('Y-m-d H:i:s') . " - Rensade {$totalDeleted} tokens ({$expiredCount} utgångna, {$usedCount} använda)\n";
    error_log($logMessage);
    
    echo $logMessage;
    
} catch (PDOException $e) {
    $errorMessage = date('Y-m-d H:i:s') . " - Fel vid rensning av tokens: " . $e->getMessage() . "\n";
    error_log($errorMessage);
    echo $errorMessage;
    exit(1);
}
?>
