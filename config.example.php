<?php
/**
 * EXEMPEL PÅ KONFIGURATIONSFIL
 * 
 * Kopiera denna fil till config.local.php och fyll i dina egna värden.
 * COMMIT ALDRIG config.local.php TILL GIT!
 */

// ==============================================
// DATABASINSTÄLLNINGAR
// ==============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'din_databas');
define('DB_USER', 'din_användare');
define('DB_PASS', 'ditt_lösenord');
define('DB_CHARSET', 'utf8mb4');

// ==============================================
// SMTP-INSTÄLLNINGAR
// ==============================================
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // 'ssl' för port 465, 'tls' för port 587
define('SMTP_USERNAME', 'info@example.com');
define('SMTP_PASSWORD', 'ditt_smtp_lösenord');

// ==============================================
// E-POST AVSÄNDARE
// ==============================================
define('MAIL_FROM_ADDRESS', 'statistik@example.com');
define('MAIL_FROM_NAME', 'Mahjong Club');
define('MAIL_REPLY_TO', 'info@example.com');

// ==============================================
// WEBBPLATSINSTÄLLNINGAR
// ==============================================
define('SITE_URL', 'https://example.com/statistik');
define('SITE_TITLE', 'STATISTIK VARBERGS MAHJONGSÄLLSKAP');
define('TAB_TITLE', 'Statistik');
define('LOGO_FILE', 'vms.png');
define('LOGO_SIZE', 200);

// ==============================================
// SYSTEMANVÄNDARE
// ==============================================
define('SYSTEM_ADMIN_ID', 1);
define('SYSTEM_READ_ID', 2);

// ==============================================
// MILJÖ & DEBUG
// ==============================================
define('ENVIRONMENT', 'production'); // 'development' eller 'production'
define('DEBUG_MODE', false);
define('DISPLAY_ERRORS', false);

// ==============================================
// SESSION
// ==============================================
define('SESSION_NAME', 'vms_session');
define('SESSION_LIFETIME', 86400); // 24 timmar i sekunder
define('SESSION_TIMEOUT', 1200); // Inaktivitetstimeout: 20 minuter (1200 sekunder)

// ==============================================
// SÄKERHET
// ==============================================
define('PASSWORD_COST', 12); // Bcrypt cost (10-12 rekommenderas)
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 15); // minuter

// ==============================================
// ÅR
// ==============================================
define('FIRST_YEAR', 2024);

// ==============================================
// TIDSZON
// ==============================================
date_default_timezone_set('Europe/Stockholm');
