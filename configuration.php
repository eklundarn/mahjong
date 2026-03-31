<?php
require_once 'config.php';

// Kräv admin
if (($_SESSION['role'] ?? '') !== 'mainadmin') {
    showError('Endast huvudadmin har tillgång till konfigurationssidan.');
}

$conn = getDbConnection();
$success = '';
$error = '';

// Hantera formulärinlämning
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['save_config'])) {
        // Spara konfiguration
        $new_config = [];
        
        // Databas
        $new_config['DB_HOST'] = trim($_POST['db_host'] ?? '');
        $new_config['DB_NAME'] = trim($_POST['db_name'] ?? '');
        $new_config['DB_USER'] = trim($_POST['db_user'] ?? '');
        $new_config['DB_PASS'] = $_POST['db_pass'] ?? '';
        $new_config['DB_CHARSET'] = trim($_POST['db_charset'] ?? 'utf8mb4');
        
        // SMTP
        $new_config['SMTP_HOST'] = trim($_POST['smtp_host'] ?? '');
        $new_config['SMTP_PORT'] = trim($_POST['smtp_port'] ?? '587');
        $new_config['SMTP_SECURE'] = trim($_POST['smtp_secure'] ?? 'tls');
        $new_config['SMTP_USERNAME'] = trim($_POST['smtp_username'] ?? '');
        $new_config['SMTP_PASSWORD'] = $_POST['smtp_password'] ?? '';
        
        // E-post
        $new_config['MAIL_FROM_ADDRESS'] = trim($_POST['mail_from_address'] ?? '');
        $new_config['MAIL_FROM_NAME'] = trim($_POST['mail_from_name'] ?? '');
        $new_config['MAIL_REPLY_TO'] = trim($_POST['mail_reply_to'] ?? '');
        
        // Webbplats
        $new_config['SITE_TITLE_EN'] = trim($_POST['site_title_en'] ?? '');
        $new_config['SITE_URL'] = rtrim(trim($_POST['site_url'] ?? ''), '/');
        $new_config['SITE_TITLE'] = trim($_POST['site_title'] ?? '');
        $new_config['TAB_TITLE'] = trim($_POST['tab_title'] ?? '');
        $new_config['LOGO_FILE'] = trim($_POST['logo_file'] ?? 'img/logo.png');
        $new_config['LOGO_SIZE'] = (int)($_POST['logo_size'] ?? 200);
        
        // System
        $new_config['SYSTEM_ADMIN_ID'] = trim($_POST['system_admin_id'] ?? 1);
        $new_config['SYSTEM_READ_ID'] = trim($_POST['system_read_id'] ?? 2);
        
        // Miljö
        $new_config['ENVIRONMENT'] = trim($_POST['environment'] ?? 'production');
        $new_config['DEBUG_MODE'] = isset($_POST['debug_mode']) ? 'true' : 'false';
        $new_config['DISPLAY_ERRORS'] = isset($_POST['display_errors']) ? 'true' : 'false';
        
        // Session
        $new_config['SESSION_NAME'] = trim($_POST['session_name'] ?? 'vms_session');
        $new_config['SESSION_LIFETIME'] = (int)($_POST['session_lifetime'] ?? 86400);
        $new_config['SESSION_TIMEOUT'] = (int)($_POST['session_timeout'] ?? 900);
        $new_config['REGISTRATION_SESSION_TIMEOUT'] = (int)($_POST['registration_session_timeout'] ?? 1800);
        
        // Säkerhet
        $new_config['PASSWORD_COST'] = (int)($_POST['password_cost'] ?? 12);
        $new_config['MAX_LOGIN_ATTEMPTS'] = (int)($_POST['max_login_attempts'] ?? 5);
        $new_config['LOCKOUT_TIME'] = (int)($_POST['lockout_time'] ?? 15);
        
        // År
        $new_config['FIRST_YEAR'] = (int)($_POST['first_year'] ?? 2024);
        
        // Funktioner
        $new_config['REQUIRE_PLAYER_CONFIRMATION'] = isset($_POST['require_player_confirmation']) ? 'true' : 'false';
        
        // Generera ny config.local.php
        $config_content = "<?php
/**
 * LOKAL KONFIGURATION
 * 
 * Denna fil genererades av konfigurationssidan.
 * Senast uppdaterad: " . date('Y-m-d H:i:s') . "
 * COMMIT ALDRIG DENNA FIL TILL GIT!
 */

// ==============================================
// DATABASINSTÄLLNINGAR
// ==============================================
define('DB_HOST', " . var_export($new_config['DB_HOST'], true) . ");
define('DB_NAME', " . var_export($new_config['DB_NAME'], true) . ");
define('DB_USER', " . var_export($new_config['DB_USER'], true) . ");
define('DB_PASS', " . var_export($new_config['DB_PASS'], true) . ");
define('DB_CHARSET', " . var_export($new_config['DB_CHARSET'], true) . ");

// ==============================================
// SMTP-INSTÄLLNINGAR
// ==============================================
define('SMTP_HOST', " . var_export($new_config['SMTP_HOST'], true) . ");
define('SMTP_PORT', " . var_export($new_config['SMTP_PORT'], true) . ");
define('SMTP_SECURE', " . var_export($new_config['SMTP_SECURE'], true) . ");
define('SMTP_USERNAME', " . var_export($new_config['SMTP_USERNAME'], true) . ");
define('SMTP_PASSWORD', " . var_export($new_config['SMTP_PASSWORD'], true) . ");

// ==============================================
// E-POST AVSÄNDARE
// ==============================================
define('MAIL_FROM_ADDRESS', " . var_export($new_config['MAIL_FROM_ADDRESS'], true) . ");
define('MAIL_FROM_NAME', " . var_export($new_config['MAIL_FROM_NAME'], true) . ");
define('MAIL_REPLY_TO', " . var_export($new_config['MAIL_REPLY_TO'], true) . ");

// ==============================================
// WEBBPLATSINSTÄLLNINGAR
// ==============================================
define('SITE_URL', " . var_export($new_config['SITE_URL'], true) . ");
define('SITE_TITLE', " . var_export($new_config['SITE_TITLE'], true) . ");
define('SITE_TITLE_EN', " . var_export($new_config['SITE_TITLE_EN'], true) . ");
define('TAB_TITLE', " . var_export($new_config['TAB_TITLE'], true) . ");
define('LOGO_FILE', " . var_export($new_config['LOGO_FILE'], true) . ");
define('LOGO_SIZE', " . var_export($new_config['LOGO_SIZE'], true) . ");

// ==============================================
// SYSTEMANVÄNDARE
// ==============================================
define('SYSTEM_ADMIN_ID', " . var_export($new_config['SYSTEM_ADMIN_ID'], true) . ");
define('SYSTEM_READ_ID', " . var_export($new_config['SYSTEM_READ_ID'], true) . ");

// ==============================================
// MILJÖ & DEBUG
// ==============================================
define('ENVIRONMENT', " . var_export($new_config['ENVIRONMENT'], true) . ");
define('DEBUG_MODE', " . $new_config['DEBUG_MODE'] . ");
define('DISPLAY_ERRORS', " . $new_config['DISPLAY_ERRORS'] . ");

// ==============================================
// SESSION
// ==============================================
define('SESSION_NAME', " . var_export($new_config['SESSION_NAME'], true) . ");
define('SESSION_LIFETIME', " . var_export($new_config['SESSION_LIFETIME'], true) . ");
define('SESSION_TIMEOUT', " . var_export($new_config['SESSION_TIMEOUT'], true) . "); // Inaktivitetstimeout vanliga sidor: " . round($new_config['SESSION_TIMEOUT']/60) . " minuter
define('REGISTRATION_SESSION_TIMEOUT', " . var_export($new_config['REGISTRATION_SESSION_TIMEOUT'], true) . "); // Inaktivitetstimeout registreringssidor: " . round($new_config['REGISTRATION_SESSION_TIMEOUT']/60) . " minuter

// ==============================================
// SÄKERHET
// ==============================================
define('PASSWORD_COST', " . var_export($new_config['PASSWORD_COST'], true) . ");
define('MAX_LOGIN_ATTEMPTS', " . var_export($new_config['MAX_LOGIN_ATTEMPTS'], true) . ");
define('LOCKOUT_TIME', " . var_export($new_config['LOCKOUT_TIME'], true) . ");

// ==============================================
// ÅR
// ==============================================
define('FIRST_YEAR', " . var_export($new_config['FIRST_YEAR'], true) . ");

// ==============================================
// FUNKTIONER
// ==============================================
define('REQUIRE_PLAYER_CONFIRMATION', " . $new_config['REQUIRE_PLAYER_CONFIRMATION'] . ");

date_default_timezone_set('Europe/Stockholm');
?>";

        // Skriv till fil
        if (file_put_contents(__DIR__ . '/config.local.php', $config_content)) {
            $success = "✅ Konfigurationen har sparats! Ladda om sidan för att ändringarna ska träda i kraft.";
        } else {
            $error = "❌ Kunde inte spara konfigurationen. Kontrollera filrättigheter.";
        }
    }
    
    if (isset($_POST['export_config'])) {
        // Exportera konfiguration
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="config_backup_' . date('Y-m-d_His') . '.php"');
        readfile(__DIR__ . '/config.local.php');
        exit;
    }
    
    if (isset($_POST['restore_defaults'])) {
        // Återställ till standardvärden (från config.example.php)
        if (file_exists(__DIR__ . '/config.example.php')) {
            copy(__DIR__ . '/config.example.php', __DIR__ . '/config.local.php');
            $success = "✅ Konfigurationen har återställts till standardvärden.";
        } else {
            $error = "❌ Kunde inte hitta config.example.php";
        }
    }
}

includeHeader();
?>

<h1>⚙️ Konfiguration</h1>

<?php if ($success): ?>
    <div class="message success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="message error"><?php echo $error; ?></div>
<?php endif; ?>

<div style="margin-bottom: 20px;">
    <div class="config-warning" style="background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">
        <?php echo t('config_warning'); ?>
    </div>
</div>

<form method="POST" id="configForm">

<!-- WEBBPLATS -->
<fieldset style="border: 2px solid #4CAF50; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <legend style="font-weight: bold; font-size: 1.2em;"><?php echo t('config_section_web'); ?></legend>
    
    <div class="form-group">
        <label for="site_url"><?php echo t('config_label_site_url'); ?>:</label>
        <input type="url" id="site_url" name="site_url" 
               value="<?php echo htmlspecialchars(defined('SITE_URL') ? SITE_URL : ''); ?>" required>
        <small style="color: #666;">
            Exempel: https://varbergmahjong.se
        </small>
    </div>
    
    <div class="form-group">
        <label for="site_title"><?php echo t('config_label_site_title'); ?>:</label>
        <input type="text" id="site_title" name="site_title" 
               value="<?php echo htmlspecialchars(defined('SITE_TITLE') ? SITE_TITLE : ''); ?>" required>
    </div>
    
    <div class="form-group">
        <label for="site_title_en"><?php echo t('config_label_site_title_en'); ?>:</label>
        <input type="text" id="site_title_en" name="site_title_en" 
               value="<?php echo htmlspecialchars(defined('SITE_TITLE_EN') ? SITE_TITLE_EN : ''); ?>">
    </div>
    
    <div class="form-group">
        <label for="tab_title"><?php echo t('config_label_tab_title'); ?>:</label>
        <input type="text" id="tab_title" name="tab_title" 
               value="<?php echo htmlspecialchars(defined('TAB_TITLE') ? TAB_TITLE : ''); ?>">
        <small style="color: #666;"><?php echo t('config_hint_tab_title'); ?></small>
    </div>
    
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <div class="form-group">
            <label for="logo_file"><?php echo t('config_label_logo_file'); ?>:</label>
            <input type="text" id="logo_file" name="logo_file" 
                   value="<?php echo htmlspecialchars(defined('LOGO_FILE') ? LOGO_FILE : 'img/logo.png'); ?>">
            <small style="color: #666;"><?php echo t('config_hint_logo_file'); ?></small>
        </div>
        
        <div class="form-group">
            <label for="logo_size"><?php echo t('config_label_logo_size'); ?>:</label>
            <input type="number" id="logo_size" name="logo_size" 
                   value="<?php echo htmlspecialchars(defined('LOGO_SIZE') ? LOGO_SIZE : '200'); ?>">
        </div>
    </div>
</fieldset>


<!-- SYSTEM -->
<fieldset style="border: 2px solid #f44336; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <legend style="font-weight: bold; font-size: 1.2em;"><?php echo t('config_section_system'); ?></legend>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="form-group">
            <label for="system_admin_id">Huvudadmin Spelar-ID:</label>
            <input type="text" id="system_admin_id" name="system_admin_id" 
                   value="<?php echo htmlspecialchars(defined('SYSTEM_ADMIN_ID') ? SYSTEM_ADMIN_ID : 1); ?>">
        </div>
        
        <div class="form-group">
            <label for="system_read_id">Läsåtkomst Spelar-ID:</label>
            <input type="text" id="system_read_id" name="system_read_id" 
                   value="<?php echo htmlspecialchars(defined('SYSTEM_READ_ID') ? SYSTEM_READ_ID : 2); ?>">
        </div>
    </div>
    
    <div class="form-group">
        <label for="first_year">Första spelade året:</label>
        <input type="number" id="first_year" name="first_year" 
               value="<?php echo htmlspecialchars(defined('FIRST_YEAR') ? FIRST_YEAR : '2024'); ?>">
    </div>
</fieldset>


<!-- FUNKTIONER -->
<fieldset style="border: 2px solid #9c27b0; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <legend style="font-weight: bold; font-size: 1.2em;">🔧 Funktioner</legend>
    
    <div class="form-group">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
            <input type="checkbox" id="require_player_confirmation" name="require_player_confirmation" 
                   <?php echo (defined('REQUIRE_PLAYER_CONFIRMATION') && REQUIRE_PLAYER_CONFIRMATION) ? 'checked' : ''; ?>>
            <span>Kräv spelargodkännande av matcher</span>
        </label>
        <div style="font-size:0.85em;color:#666;margin-top:5px;margin-left:26px;">
            När aktiverat: sparade matcher kräver OK från alla 4 spelare innan admin kan fastställa. 
            Spelare får e-post med länk för att granska och godkänna.
        </div>
    </div>
</fieldset>


<!-- MILJÖ & SÄKERHET -->
<fieldset style="border: 2px solid #607D8B; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <legend style="font-weight: bold; font-size: 1.2em;"><?php echo t('config_section_env'); ?></legend>
    
    <div class="form-group">
        <label for="environment">Miljö:</label>
        <select id="environment" name="environment">
            <option value="production" <?php echo (defined('ENVIRONMENT') && ENVIRONMENT === 'production') ? 'selected' : ''; ?>>Production</option>
            <option value="development" <?php echo (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? 'selected' : ''; ?>>Development</option>
        </select>
    </div>
    
    <div class="form-group">
        <label>
            <input type="checkbox" name="debug_mode" value="1" 
                   <?php echo (defined('DEBUG_MODE') && DEBUG_MODE) ? 'checked' : ''; ?>>
            Debug-läge (logga fel)
        </label>
    </div>
    
    <div class="form-group">
        <label>
            <input type="checkbox" name="display_errors" value="1" 
                   <?php echo (defined('DISPLAY_ERRORS') && DISPLAY_ERRORS) ? 'checked' : ''; ?>>
            Visa felmeddelanden (endast för utveckling!)
        </label>
    </div>
    
    <hr style="margin: 20px 0;">
    
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
        <div class="form-group">
            <label for="password_cost">Lösenordsstyrka (cost):</label>
            <input type="number" id="password_cost" name="password_cost" min="10" max="15"
                   value="<?php echo htmlspecialchars(defined('PASSWORD_COST') ? PASSWORD_COST : '12'); ?>">
            <small style="color: #666;">10-12 rekommenderas</small>
        </div>
        
        <div class="form-group">
            <label for="max_login_attempts">Max inloggningsförsök:</label>
            <input type="number" id="max_login_attempts" name="max_login_attempts" 
                   value="<?php echo htmlspecialchars(defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : '5'); ?>">
        </div>
        
        <div class="form-group">
            <label for="lockout_time">Lockout-tid (min):</label>
            <input type="number" id="lockout_time" name="lockout_time" 
                   value="<?php echo htmlspecialchars(defined('LOCKOUT_TIME') ? LOCKOUT_TIME : '15'); ?>">
        </div>
    </div>
    
    <hr style="margin: 20px 0;">
    
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
        <div class="form-group">
            <label for="session_name">Session-namn:</label>
            <input type="text" id="session_name" name="session_name" 
                   value="<?php echo htmlspecialchars(defined('SESSION_NAME') ? SESSION_NAME : 'vms_session'); ?>">
        </div>
        
        <div class="form-group">
            <label for="session_lifetime">Session cookie-livslängd:</label>
            <input type="number" id="session_lifetime" name="session_lifetime" 
                   value="<?php echo htmlspecialchars(defined('SESSION_LIFETIME') ? SESSION_LIFETIME : '86400'); ?>">
            <small style="color: #666;">86400 = 24 timmar</small>
        </div>
        
        <div class="form-group">
            <label for="session_timeout">Inaktivitetstimeout – vanliga sidor (sekunder):</label>
            <input type="number" id="session_timeout" name="session_timeout" 
                   value="<?php echo htmlspecialchars(defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : '900'); ?>">
            <small style="color: #666;">900 = 15 min &nbsp;|&nbsp; Gäller alla sidor utom registreringssidorna</small>
        </div>
        
        <div class="form-group">
            <label for="registration_session_timeout">Inaktivitetstimeout – registreringssidor (sekunder):</label>
            <input type="number" id="registration_session_timeout" name="registration_session_timeout" 
                   value="<?php echo htmlspecialchars(defined('REGISTRATION_SESSION_TIMEOUT') ? REGISTRATION_SESSION_TIMEOUT : '1800'); ?>">
            <small style="color: #666;">1800 = 30 min &nbsp;|&nbsp; Gäller newgame.php och mobile-game.php</small>
        </div>
    </div>
</fieldset>

<!-- DATABAS -->
<fieldset style="border: 2px solid #2196F3; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <legend style="font-weight: bold; font-size: 1.2em;"><?php echo t('config_section_db'); ?></legend>
    
    <div class="form-group">
        <label for="db_host">Databas Host:</label>
        <input type="text" id="db_host" name="db_host" 
               value="<?php echo htmlspecialchars(defined('DB_HOST') ? DB_HOST : 'localhost'); ?>" required>
        <small style="color: #666;">Exempel: localhost eller database-xxx.webspace-host.com</small>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="form-group">
            <label for="db_name">Databasnamn:</label>
            <input type="text" id="db_name" name="db_name" 
                   value="<?php echo htmlspecialchars(defined('DB_NAME') ? DB_NAME : ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="db_charset">Teckentabell:</label>
            <input type="text" id="db_charset" name="db_charset" 
                   value="<?php echo htmlspecialchars(defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'); ?>">
        </div>
    </div>
    
    <div class="form-group">
        <label for="db_user">Databasanvändare:</label>
        <input type="text" id="db_user" name="db_user" 
               value="<?php echo htmlspecialchars(defined('DB_USER') ? DB_USER : ''); ?>" required>
    </div>
    
    <div class="form-group">
        <label for="db_pass">Databaslösenord:</label>
        <div style="position: relative;">
            <input type="password" id="db_pass" name="db_pass" 
                   value="<?php echo htmlspecialchars(defined('DB_PASS') ? DB_PASS : ''); ?>" 
                   style="padding-right: 50px;">
            <button type="button" onclick="togglePassword('db_pass')" 
                    style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.2em;">
                👁️
            </button>
        </div>
    </div>
</fieldset>


<!-- E-POST AVSÄNDARE -->
<fieldset style="border: 2px solid #9C27B0; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <legend style="font-weight: bold; font-size: 1.2em;"><?php echo t('config_section_email'); ?></legend>
    
    <div class="form-group">
        <label for="mail_from_address">Avsändaradress:</label>
        <input type="email" id="mail_from_address" name="mail_from_address" 
               value="<?php echo htmlspecialchars(defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : ''); ?>">
    </div>
    
    <div class="form-group">
        <label for="mail_from_name">Avsändarnamn:</label>
        <input type="text" id="mail_from_name" name="mail_from_name" 
               value="<?php echo htmlspecialchars(defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : ''); ?>">
    </div>
    
    <div class="form-group">
        <label for="mail_reply_to">Svar-till adress:</label>
        <input type="email" id="mail_reply_to" name="mail_reply_to" 
               value="<?php echo htmlspecialchars(defined('MAIL_REPLY_TO') ? MAIL_REPLY_TO : ''); ?>">
    </div>
</fieldset>


<!-- SMTP -->
<fieldset style="border: 2px solid #FF9800; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <legend style="font-weight: bold; font-size: 1.2em;"><?php echo t('config_section_smtp'); ?></legend>
    
    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px;">
        <div class="form-group">
            <label for="smtp_host">SMTP-server:</label>
            <input type="text" id="smtp_host" name="smtp_host" 
                   value="<?php echo htmlspecialchars(defined('SMTP_HOST') ? SMTP_HOST : ''); ?>">
            <small style="color: #666;">Exempel: smtp.gmail.com eller smtp.strato.com</small>
        </div>
        
        <div class="form-group">
            <label for="smtp_port">Port:</label>
            <input type="number" id="smtp_port" name="smtp_port" 
                   value="<?php echo htmlspecialchars(defined('SMTP_PORT') ? SMTP_PORT : '587'); ?>">
        </div>
        
        <div class="form-group">
            <label for="smtp_secure">Säkerhet:</label>
            <select id="smtp_secure" name="smtp_secure">
                <option value="tls" <?php echo (defined('SMTP_SECURE') && SMTP_SECURE === 'tls') ? 'selected' : ''; ?>>TLS</option>
                <option value="ssl" <?php echo (defined('SMTP_SECURE') && SMTP_SECURE === 'ssl') ? 'selected' : ''; ?>>SSL</option>
            </select>
        </div>
    </div>
    
    <div class="form-group">
        <label for="smtp_username">SMTP-användarnamn:</label>
        <input type="text" id="smtp_username" name="smtp_username" 
               value="<?php echo htmlspecialchars(defined('SMTP_USERNAME') ? SMTP_USERNAME : ''); ?>">
    </div>
    
    <div class="form-group">
        <label for="smtp_password">SMTP-lösenord:</label>
        <div style="position: relative;">
            <input type="password" id="smtp_password" name="smtp_password" 
                   value="<?php echo htmlspecialchars(defined('SMTP_PASSWORD') ? SMTP_PASSWORD : ''); ?>" 
                   style="padding-right: 50px;">
            <button type="button" onclick="togglePassword('smtp_password')" 
                    style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.2em;">
                👁️
            </button>
        </div>
    </div>
</fieldset>



<!-- KNAPPAR -->
<div style="margin: 30px 0; display: flex; gap: 10px; flex-wrap: wrap;">
    <button type="submit" name="save_config" class="btn" 
            onclick="return confirm('<?php echo t('config_confirm_save'); ?>');">
        💾 Spara konfiguration
    </button>
    
    <button type="submit" name="export_config" class="btn" 
            style="background: #2196F3;">
        📥 Exportera konfiguration
    </button>
    
    <button type="submit" name="restore_defaults" class="btn btn-danger" 
            onclick="return confirm('<?php echo t('config_confirm_restore'); ?>');">
        🔄 Återställ standardvärden
    </button>
</div>

</form>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    if (field.type === 'password') {
        field.type = 'text';
    } else {
        field.type = 'password';
    }
}
</script>
