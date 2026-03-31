<?php
/**
 * HUVUDKONFIGURATION FÖR MAHJONG CLUB MANAGER
 * 
 * Denna fil läser konfiguration från config.local.php
 * och tillhandahåller gemensamma funktioner.
 */

// Läs lokal konfiguration
if (!file_exists(__DIR__ . '/config.local.php')) {
    // Om config.local.php inte finns, visa installationsmeddelande
    if (basename($_SERVER['PHP_SELF']) !== 'setup.php') {
        header('Location: setup.php');
        exit;
    }
} else {
    require_once __DIR__ . '/config.local.php';
}

// ==============================================
// FELHANTERING
// ==============================================
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log("System Debug Mode Enabled");
}

if (defined('DISPLAY_ERRORS') && DISPLAY_ERRORS) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ==============================================
// SESSION
// ==============================================
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    // Sätt session garbage collection till samma värde som session lifetime
    $session_lifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 86400;
    
    @ini_set('session.gc_maxlifetime', $session_lifetime);
    @ini_set('session.cookie_lifetime', $session_lifetime);
    
    // Egen sessionskatalog för att undvika att delad servers GC rensar våra sessioner
    $custom_session_path = __DIR__ . '/../sessions';
    if (!is_dir($custom_session_path)) {
        @mkdir($custom_session_path, 0700, true);
    }
    if (is_dir($custom_session_path) && is_writable($custom_session_path)) {
        @ini_set('session.save_path', $custom_session_path);
    }
    
    if (defined('SESSION_NAME')) {
        @session_name(SESSION_NAME);
    }
    
    $session_params = session_get_cookie_params();
    @session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => $session_params['path'],
        'domain' => $session_params['domain'],
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

// Definiera session timeout om inte redan definierat
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 900); // 15 minuter standard för admin
}
if (!defined('ADMIN_SESSION_TIMEOUT')) {
    define('ADMIN_SESSION_TIMEOUT', 14400); // 4 timmar för admin-roller
}

// Admin-roller som ska ha timeout
$_admin_roles = ['superuser', 'vms_superuser', 'admin', 'mainadmin'];
$_user_role = $_SESSION['role'] ?? '';
$_is_admin_role = in_array($_user_role, $_admin_roles);

// Kontrollera om sessionen har gått ut — BARA för admin-roller
// Vanliga spelare (vms_player, registrator etc.) får ingen timeout
if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity']) && $_is_admin_role) {
    $inactive_time = time() - $_SESSION['last_activity'];
    $_effective_timeout = ADMIN_SESSION_TIMEOUT;

    if ($inactive_time > $_effective_timeout) {
        session_unset();
        session_destroy();
        session_start();

        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false ||
            strpos($_SERVER['PHP_SELF'], 'session_heartbeat.php') !== false) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'error' => 'Session har gått ut',
                'session_expired' => true,
                'session_active' => false
            ]);
            exit;
        }
    }
}

// Uppdatera senaste aktivitetstid vid varje request (BARA om inloggad)
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}

// ==============================================
// SPRÅKHANTERING
// ==============================================

// Förhindra caching så språkbyten alltid slår igenom
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

// Byt språk om ?lang=xx skickats
if (isset($_GET['lang']) && in_array($_GET['lang'], ['sv', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Standardspråk: svenska, SÅVIDA inte sidan satt $force_lang innan config inkluderades
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = isset($force_lang) ? $force_lang : 'sv';
} elseif (isset($force_lang)) {
    // Tvinga språk (används av turneringssidor)
    $_SESSION['lang'] = $force_lang;
}

// Ladda språkfil
$_lang_code = $_SESSION['lang'] ?? 'sv';
$_lang_file = __DIR__ . '/lang/' . $_lang_code . '.php';
if (!file_exists($_lang_file)) {
    $_lang_file = __DIR__ . '/lang/sv.php';
}
if (file_exists($_lang_file)) {
    require_once $_lang_file;
} else {
    // lang/-mappen finns inte än - definiera tom array så t() inte kraschar
    $lang = [];
}

// Hjälpfunktion för att hämta sträng (returnerar nyckeln om strängen saknas)
if (!function_exists('t')) {
    function t($key, $fallback = null) {
        global $lang;
        return $lang[$key] ?? $fallback ?? $key;
    }
}

// Aktuell språkkod
if (!function_exists('currentLang')) {
    function currentLang() {
        return $_SESSION['lang'] ?? 'sv';
    }
}

// Förnya session ID periodiskt för säkerhet (varje 30 min)
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
} else if (time() - $_SESSION['CREATED'] > 1800) {
    // Förnya session ID men behåll data
    session_regenerate_id(true);
    $_SESSION['CREATED'] = time();
}

// ==============================================
// DATABASANSLUTNING
// ==============================================
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_NAME,
                defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'
            );
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Database connection failed: " . $e->getMessage());
            }
            die("Kunde inte ansluta till databasen. Kontakta administratören.");
        }
    }
    
    return $pdo;
}

// ==============================================
// AUTENTISERING
// ==============================================
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    static $user = null;
    if ($user === null) {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT * FROM stat_players WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    
    return $user;
}

function loginUser($username, $password) {
    $conn = getDbConnection();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    
    // Försök hitta användare med username, EMA-nummer eller e-post
    $stmt = $conn->prepare("
        SELECT * FROM stat_players 
        WHERE (username = ? OR ema_number = ? OR email = ?) 
        AND active = 1
    ");
    $stmt->execute([$username, $username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Logga misslyckat försök — okänd användare
        try {
            $conn->prepare("INSERT INTO stat_login_log (attempted_username, player_id, success, ip_address) VALUES (?, NULL, 0, ?)")
                 ->execute([$username, $ip]);
        } catch (Exception $e) {} // Ignorera om tabellen inte finns ännu
        return false;
    }
    
    // Verifiera lösenord
    if (!verifyPassword($password, $user['password_hash'])) {
        // Logga misslyckat försök — fel lösenord
        try {
            $conn->prepare("INSERT INTO stat_login_log (attempted_username, player_id, success, ip_address) VALUES (?, ?, 0, ?)")
                 ->execute([$username, $user['id'], $ip]);
        } catch (Exception $e) {}
        return false;
    }
    
    // Logga lyckad inloggning
    try {
        $conn->prepare("INSERT INTO stat_login_log (attempted_username, player_id, success, ip_address) VALUES (?, ?, 1, ?)")
             ->execute([$username, $user['id'], $ip]);
        $conn->prepare("UPDATE stat_players SET last_login_at = NOW() WHERE id = ?")
             ->execute([$user['id']]);
    } catch (Exception $e) {}
    
    // Sätt session-variabler
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['last_activity'] = time();
    $_SESSION['CREATED'] = time();
    
    return true;
}

function hasRole($required_role) {
    if (!isLoggedIn()) return false;
    $user = getCurrentUser();
    if (!$user) return false;

    $user_role = $user['role'];

    // Linjär hierarki för systemroller
    $hierarchy = [
        'reader'       => 1,
        'readaccess'   => 1,  // bakåtkompatibel
        'vms_read'     => 1,  // bakåtkompatibel
        'player'       => 2,
        'registrator'  => 2,  // bakåtkompatibel
        'vms_player'   => 2,  // bakåtkompatibel
        'clubplayer'   => 2,  // bakåtkompatibel
        'superuser'    => 3,
        'vms_superuser'=> 3,  // bakåtkompatibel
        'admin'        => 4,
        'mainadmin'    => 5
    ];

    $user_level     = $hierarchy[$user_role] ?? 0;
    $required_level = $hierarchy[$required_role] ?? 0;

    // Om required_role finns i hierarkin, kolla nivå
    if ($required_level > 0) {
        return $user_level >= $required_level;
    }

    // Annars kolla i player_permissions-tabellen
    return hasPermission($required_role);
}

function hasPermission($permission_key) {
    if (!isLoggedIn()) return false;
    $user = getCurrentUser();
    if (!$user) return false;

    // Mainadmin och admin har alltid alla behörigheter
    $hierarchy = ['reader'=>1,'readaccess'=>1,'player'=>2,'registrator'=>2,'superuser'=>3,'admin'=>4,'mainadmin'=>5];
    if (($hierarchy[$user['role']] ?? 0) >= 4) return true;

    // Kolla player_permissions-tabellen
    static $permission_cache = [];
    $player_id = $user['id'];
    if (!isset($permission_cache[$player_id])) {
        try {
            $conn = getDbConnection();
            $stmt = $conn->prepare("SELECT permission_key FROM player_permissions WHERE player_id = ?");
            $stmt->execute([$player_id]);
            $permission_cache[$player_id] = array_column($stmt->fetchAll(), 'permission_key');
        } catch (Exception $e) {
            $permission_cache[$player_id] = [];
        }
    }
    return in_array($permission_key, $permission_cache[$player_id]);
}

function getPlayerPermissions($player_id) {
    try {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT permission_key FROM player_permissions WHERE player_id = ?");
        $stmt->execute([$player_id]);
        return array_column($stmt->fetchAll(), 'permission_key');
    } catch (Exception $e) {
        return [];
    }
}

function canApproveGames() {
    return hasRole('vms_superuser') || hasRole('superuser') || hasRole('admin');
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        showError("Du har inte behörighet att komma åt denna sida.");
    }
}

// ==============================================
// HJÄLPFUNKTIONER
// ==============================================

// Kolla om vi är i embed-läge
function isEmbedMode() {
    return isset($_GET['embed']) && $_GET['embed'] === 'true';
}

// Lägg till embed-parameter till URL
function addEmbedParam($url) {
    if (isEmbedMode()) {
        $separator = strpos($url, '?') !== false ? '&' : '?';
        return $url . $separator . 'embed=true';
    }
    return $url;
}

// Kortare alias
function embedUrl($url) {
    return addEmbedParam($url);
}

// Inkludera rätt header
function includeHeader() {
    if (isEmbedMode()) {
        include 'includes/header-embed.php';
    } else {
        include 'includes/header.php';
    }
}

// Inkludera rätt footer
function includeFooter() {
    if (isEmbedMode()) {
        include 'includes/footer-embed.php';
    } else {
        include 'includes/footer.php';
    }
}

function redirect($url) {
    // Behåll embed-parameter vid redirects
    $url = addEmbedParam($url);
    
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    } else {
        echo "<script>window.location.href='$url';</script>";
        exit;
    }
}

function showError($message, $title = "Fel") {
    // Använd rätt header beroende på embed-läge
    if (isEmbedMode()) {
        include 'includes/header-embed.php';
    } else {
        include 'includes/header.php';
    }
    
    echo '<div class="message error">';
    echo '<h2>' . htmlspecialchars($title) . '</h2>';
    echo '<p>' . $message . '</p>';
    echo '<p><a href="javascript:history.back()" class="btn btn-secondary">← Tillbaka</a></p>';
    echo '</div>';
    
    // Använd rätt footer
    if (isEmbedMode()) {
        include 'includes/footer-embed.php';
    } else {
        include 'includes/footer.php';
    }
    exit;
}

function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

function getCurrentYear() {
    // Hämta aktuellt år från databasen eller använd dagens år
    $conn = getDbConnection();
    $stmt = $conn->query("SELECT MAX(game_year) as max_year FROM stat_games WHERE deleted_at IS NULL");
    $result = $stmt->fetch();
    
    if ($result && $result['max_year']) {
        return (int)$result['max_year'];
    }
    
    return (int)date('Y');
}

function formatSwedishDate($date) {
    if (empty($date)) {
        return '';
    }
    
    $months = [
        1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
        5 => 'maj', 6 => 'jun', 7 => 'jul', 8 => 'aug',
        9 => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'dec'
    ];
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = (int)date('n', $timestamp);
    $year = date('Y', $timestamp);
    
    return $day . ' ' . $months[$month] . ' ' . $year;
}

function getYearOptions($selected_year = null) {
    $current_year = (int)date('Y');
    $first_year = defined('FIRST_YEAR') ? FIRST_YEAR : 2024;
    
    if ($selected_year === null) {
        $selected_year = getCurrentYear();
    }
    
    $options = [];
    for ($year = $current_year; $year >= $first_year; $year--) {
        $selected = ($year == $selected_year) ? 'selected' : '';
        $options[] = "<option value='$year' $selected>$year</option>";
    }
    
    return implode("\n", $options);
}

// ==============================================
// E-POST
// ==============================================
function sendEmail($to, $subject, $body, $altBody = '') {
    if (!defined('SMTP_HOST') || !defined('SMTP_USERNAME')) {
        error_log("E-post konfiguration saknas");
        return false;
    }
    
    // Använd PHPMailer om tillgängligt
    // Hitta PHPMailer oavsett var den ligger
    $phpmailer_base = null;
    if (file_exists(__DIR__ . '/PHPMailer/PHPMailer.php')) {
        $phpmailer_base = __DIR__ . '/PHPMailer/';
    } elseif (file_exists(__DIR__ . '/includes/PHPMailer/PHPMailer.php')) {
        $phpmailer_base = __DIR__ . '/includes/PHPMailer/';
    }
    if ($phpmailer_base !== null) {
        require_once $phpmailer_base . 'PHPMailer.php';
        require_once $phpmailer_base . 'SMTP.php';
        require_once $phpmailer_base . 'Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
            $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
            $mail->CharSet = 'UTF-8';
            
            $mail->setFrom(
                defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : SMTP_USERNAME,
                defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : (defined('SITE_TITLE') ? SITE_TITLE : 'Mahjong Club')
            );
            $mail->addAddress($to);
            
            if (defined('MAIL_REPLY_TO') && MAIL_REPLY_TO) {
                $mail->addReplyTo(MAIL_REPLY_TO);
            }
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = $altBody ?: strip_tags($body);
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("E-post fel: " . $mail->ErrorInfo);
            }
            return false;
        }
    }
    
    // Fallback till vanlig mail() om PHPMailer inte finns
    $headers = "From: " . (defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : SMTP_USERNAME) . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $body, $headers);
}

// ==============================================
// SÄKERHET
// ==============================================
function hashPassword($password) {
    $cost = defined('PASSWORD_COST') ? PASSWORD_COST : 12;
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function checkLoginAttempts($identifier) {
    // TODO: Implementera begränsning av inloggningsförsök
    // För närvarande returnerar vi alltid true
    return true;
}

function recordFailedLogin($identifier) {
    // TODO: Implementera loggning av misslyckade inloggningsförsök
}

function clearLoginAttempts($identifier) {
    // TODO: Implementera rensning av inloggningsförsök vid lyckad inloggning
}

// ==============================================
// SYSTEMINFO
// ==============================================
function getSystemInfo() {
    return [
        'version' => '2.0.0',
        'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'production',
        'debug_mode' => defined('DEBUG_MODE') ? DEBUG_MODE : false,
        'php_version' => PHP_VERSION,
        'database_host' => defined('DB_HOST') ? DB_HOST : 'N/A',
        'site_url' => defined('SITE_URL') ? SITE_URL : 'N/A'
    ];
}

// ==============================================
// BACKUP KOMPATIBILITET MED GAMLA config.php
// ==============================================
// Om några gamla konstanter inte är definierade, sätt defaults
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://' . $_SERVER['HTTP_HOST']);
}

if (!defined('SITE_TITLE')) {
    define('SITE_TITLE', 'Mahjong Club Manager');
}
if (!defined('SITE_TITLE_EN')) {
    define('SITE_TITLE_EN', defined('SITE_TITLE') ? SITE_TITLE : 'Mahjong Club Manager');
}

if (!defined('LOGO_FILE')) {
    define('LOGO_FILE', 'img/logo.png');
}

if (!defined('LOGO_SIZE')) {
    define('LOGO_SIZE', 200);
}

// ─── Landfunktioner ───────────────────────────────────────────────
function getCountryFlag($code) {
    if (!$code) $code = 'SE';
    $code = strtoupper($code);
    return '<span class="fi fi-' . strtolower($code) . '" style="margin-right:3px;"></span>';
}

function getCountryName($code, $lang = null) {
    if (!$code) $code = 'SE';
    $lang = $lang ?: (isset($_SESSION['lang']) ? $_SESSION['lang'] : 'sv');
    $names = [
        'SE' => ['sv' => 'Sverige',       'en' => 'Sweden'],
        'NO' => ['sv' => 'Norge',          'en' => 'Norway'],
        'DK' => ['sv' => 'Danmark',        'en' => 'Denmark'],
        'FI' => ['sv' => 'Finland',        'en' => 'Finland'],
        'DE' => ['sv' => 'Tyskland',       'en' => 'Germany'],
        'FR' => ['sv' => 'Frankrike',      'en' => 'France'],
        'NL' => ['sv' => 'Nederländerna',  'en' => 'Netherlands'],
        'BE' => ['sv' => 'Belgien',        'en' => 'Belgium'],
        'GB' => ['sv' => 'Storbritannien', 'en' => 'United Kingdom'],
        'PL' => ['sv' => 'Polen',          'en' => 'Poland'],
        'CZ' => ['sv' => 'Tjeckien',       'en' => 'Czech Republic'],
        'AT' => ['sv' => 'Österrike',      'en' => 'Austria'],
        'CH' => ['sv' => 'Schweiz',        'en' => 'Switzerland'],
        'IT' => ['sv' => 'Italien',        'en' => 'Italy'],
        'ES' => ['sv' => 'Spanien',        'en' => 'Spain'],
        'PT' => ['sv' => 'Portugal',       'en' => 'Portugal'],
        'RU' => ['sv' => 'Ryssland',       'en' => 'Russia'],
        'CN' => ['sv' => 'Kina',           'en' => 'China'],
        'JP' => ['sv' => 'Japan',          'en' => 'Japan'],
        'KR' => ['sv' => 'Sydkorea',       'en' => 'South Korea'],
        'US' => ['sv' => 'USA',            'en' => 'United States'],
        'CA' => ['sv' => 'Kanada',         'en' => 'Canada'],
        'AU' => ['sv' => 'Australien',     'en' => 'Australia'],
        'HU' => ['sv' => 'Ungern',         'en' => 'Hungary'],
        'SK' => ['sv' => 'Slovakien',      'en' => 'Slovakia'],
        'HR' => ['sv' => 'Kroatien',       'en' => 'Croatia'],
        'RS' => ['sv' => 'Serbien',        'en' => 'Serbia'],
        'RO' => ['sv' => 'Rumänien',       'en' => 'Romania'],
        'BG' => ['sv' => 'Bulgarien',      'en' => 'Bulgaria'],
        'UA' => ['sv' => 'Ukraina',        'en' => 'Ukraine'],
        'IL' => ['sv' => 'Israel',         'en' => 'Israel'],
    ];
    $n = $names[strtoupper($code)] ?? null;
    if (!$n) return strtoupper($code);
    return $n[$lang] ?? $n['en'];
}

function renderCountry($code, $showName = true) {
    if (!$code) $code = 'SE';
    $flag = getCountryFlag($code);
    $name = getCountryName($code);
    if ($showName) {
        return $name . ' (' . strtoupper($code) . ') ' . $flag;
    }
    return $flag . ' ' . strtoupper($code);
}

function getCountriesForSelect() {
    $lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'sv';
    $codes = ['SE','NO','DK','FI','DE','FR','NL','BE','GB','PL','CZ','AT',
              'CH','IT','ES','PT','RU','CN','JP','KR','US','CA','AU',
              'HU','SK','HR','RS','RO','BG','UA','IL'];
    $result = [];
    foreach ($codes as $code) {
        $result[$code] = getCountryName($code, $lang);
    }
    asort($result);
    return $result;
}
// ──────────────────────────────────────────────────────────────────
