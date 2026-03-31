<?php
/**
 * install.php
 * Web-based installer for Mahjong Club Manager.
 * Collects configuration, creates database tables, generates config.local.php.
 *
 * DELETE THIS FILE AFTER INSTALLATION!
 *
 * Upload: www/install.php
 */

// Prevent running if already installed
if (file_exists(__DIR__ . '/config.local.php')) {
    $already_installed = true;
}

$step = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$errors = [];
$success = '';

// ============================================================
// STEP 3: Process installation
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3 && empty($already_installed)) {
    
    // Collect all values
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';
    
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = (int)($_POST['smtp_port'] ?? 587);
    $smtp_secure = $_POST['smtp_secure'] ?? 'tls';
    $smtp_user = trim($_POST['smtp_user'] ?? '');
    $smtp_pass = $_POST['smtp_pass'] ?? '';
    $mail_from = trim($_POST['mail_from'] ?? '');
    $mail_from_name = trim($_POST['mail_from_name'] ?? '');
    $mail_reply = trim($_POST['mail_reply'] ?? '');
    
    $site_url = rtrim(trim($_POST['site_url'] ?? ''), '/');
    $site_title = trim($_POST['site_title'] ?? '');
    $site_title_en = trim($_POST['site_title_en'] ?? '');
    $tab_title = trim($_POST['tab_title'] ?? '');
    $logo_file = trim($_POST['logo_file'] ?? 'img/logo.png');
    $timezone = trim($_POST['timezone'] ?? 'Europe/Stockholm');
    $first_year = (int)($_POST['first_year'] ?? date('Y'));
    
    $admin_first = trim($_POST['admin_first'] ?? '');
    $admin_last = trim($_POST['admin_last'] ?? '');
    $admin_username = trim($_POST['admin_username'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';
    
    // Validate
    if (!$db_name) $errors[] = 'Database name is required.';
    if (!$db_user) $errors[] = 'Database user is required.';
    if (!$site_url) $errors[] = 'Site URL is required.';
    if (!$site_title) $errors[] = 'Club name (Swedish) is required.';
    if (!$admin_first || !$admin_last) $errors[] = 'Admin first and last name are required.';
    if (!$admin_username) $errors[] = 'Admin username is required.';
    if (strlen($admin_password) < 8) $errors[] = 'Admin password must be at least 8 characters.';
    
    // Test DB connection
    if (empty($errors)) {
        try {
            $pdo = new PDO("mysql:host={$db_host};charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$db_name}`");
        } catch (PDOException $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    }
    
    // Create tables + config
    if (empty($errors)) {
        try {
            // ── stat_players (main auth/user table) ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `stat_players` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(100) DEFAULT NULL,
                `ema_number` VARCHAR(20) DEFAULT NULL,
                `first_name` VARCHAR(100) NOT NULL,
                `last_name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(200) DEFAULT NULL,
                `phone` VARCHAR(50) DEFAULT NULL,
                `city` VARCHAR(100) DEFAULT NULL,
                `club` VARCHAR(100) DEFAULT NULL,
                `country_code` VARCHAR(5) DEFAULT 'SE',
                `role` ENUM('reader','player','superuser','admin','mainadmin') DEFAULT 'reader',
                `password_hash` VARCHAR(255) DEFAULT NULL,
                `visible_in_stats` TINYINT(1) DEFAULT 1,
                `club_member` TINYINT(1) DEFAULT 0,
                `tournament_player` TINYINT(1) DEFAULT 0,
                `active` TINYINT(1) DEFAULT 1,
                `last_login_at` DATETIME DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `created_by` INT DEFAULT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `last_edited_by` INT DEFAULT NULL,
                UNIQUE KEY `uq_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── club_users (extended user table for tournaments) ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `club_users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(100) DEFAULT NULL,
                `ema_number` VARCHAR(20) DEFAULT NULL,
                `first_name` VARCHAR(100) NOT NULL,
                `last_name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(200) DEFAULT NULL,
                `phone` VARCHAR(50) DEFAULT NULL,
                `city` VARCHAR(100) DEFAULT NULL,
                `club` VARCHAR(100) DEFAULT NULL,
                `country_code` VARCHAR(5) DEFAULT 'SE',
                `role` ENUM('reader','player','superuser','admin','mainadmin') DEFAULT 'reader',
                `password_hash` VARCHAR(255) DEFAULT NULL,
                `visible_in_stats` TINYINT(1) DEFAULT 1,
                `club_member` TINYINT(1) DEFAULT 0,
                `tournament_player` TINYINT(1) DEFAULT 0,
                `active` TINYINT(1) DEFAULT 1,
                `last_login_at` DATETIME DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `created_by` INT DEFAULT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `last_edited_by` INT DEFAULT NULL,
                UNIQUE KEY `uq_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── stat_games ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `stat_games` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `game_number` INT DEFAULT NULL,
                `game_name` VARCHAR(200) DEFAULT NULL,
                `last_edited_by` INT DEFAULT NULL,
                `game_date` DATE DEFAULT NULL,
                `game_year` INT DEFAULT NULL,
                `game_label` VARCHAR(50) DEFAULT NULL,
                `biggest_hand_points` INT DEFAULT 0,
                `biggest_hand_player_id` INT DEFAULT NULL,
                `hands_played` INT DEFAULT 0,
                `zero_rounds` INT DEFAULT 0,
                `detailed_entry` TINYINT(1) DEFAULT 0,
                `approved` TINYINT(1) DEFAULT 0,
                `created_by_id` INT DEFAULT NULL,
                `approved_by_id` INT DEFAULT NULL,
                `approved_at` DATETIME DEFAULT NULL,
                `player_a_id` INT DEFAULT NULL,
                `player_a_minipoints` INT DEFAULT 0,
                `player_a_hu` INT DEFAULT 0,
                `player_a_selfdrawn` INT DEFAULT 0,
                `player_a_thrown_hu` INT DEFAULT 0,
                `player_a_tablepoints` DECIMAL(5,2) DEFAULT 0,
                `player_a_penalties` INT DEFAULT 0,
                `player_a_false_hu` INT DEFAULT 0,
                `player_a_hu_received` INT DEFAULT 0,
                `player_a_hu_given` INT DEFAULT 0,
                `player_a_self_drawn` INT DEFAULT 0,
                `player_b_id` INT DEFAULT NULL,
                `player_b_minipoints` INT DEFAULT 0,
                `player_b_hu` INT DEFAULT 0,
                `player_b_selfdrawn` INT DEFAULT 0,
                `player_b_thrown_hu` INT DEFAULT 0,
                `player_b_tablepoints` DECIMAL(5,2) DEFAULT 0,
                `player_b_penalties` INT DEFAULT 0,
                `player_b_false_hu` INT DEFAULT 0,
                `player_b_hu_received` INT DEFAULT 0,
                `player_b_hu_given` INT DEFAULT 0,
                `player_b_self_drawn` INT DEFAULT 0,
                `player_c_id` INT DEFAULT NULL,
                `player_c_minipoints` INT DEFAULT 0,
                `player_c_hu` INT DEFAULT 0,
                `player_c_selfdrawn` INT DEFAULT 0,
                `player_c_thrown_hu` INT DEFAULT 0,
                `player_c_tablepoints` DECIMAL(5,2) DEFAULT 0,
                `player_c_penalties` INT DEFAULT 0,
                `player_c_false_hu` INT DEFAULT 0,
                `player_c_hu_received` INT DEFAULT 0,
                `player_c_hu_given` INT DEFAULT 0,
                `player_c_self_drawn` INT DEFAULT 0,
                `player_d_id` INT DEFAULT NULL,
                `player_d_minipoints` INT DEFAULT 0,
                `player_d_hu` INT DEFAULT 0,
                `player_d_selfdrawn` INT DEFAULT 0,
                `player_d_thrown_hu` INT DEFAULT 0,
                `player_d_tablepoints` DECIMAL(5,2) DEFAULT 0,
                `player_d_penalties` INT DEFAULT 0,
                `player_d_false_hu` INT DEFAULT 0,
                `player_d_hu_received` INT DEFAULT 0,
                `player_d_hu_given` INT DEFAULT 0,
                `player_d_self_drawn` INT DEFAULT 0,
                `player_a_confirmed_at` DATETIME DEFAULT NULL,
                `player_a_confirmed_by` INT DEFAULT NULL,
                `player_b_confirmed_at` DATETIME DEFAULT NULL,
                `player_b_confirmed_by` INT DEFAULT NULL,
                `player_c_confirmed_at` DATETIME DEFAULT NULL,
                `player_c_confirmed_by` INT DEFAULT NULL,
                `player_d_confirmed_at` DATETIME DEFAULT NULL,
                `player_d_confirmed_by` INT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `deleted_at` DATETIME DEFAULT NULL,
                `deleted_by_id` INT DEFAULT NULL,
                `updated_by_id` INT DEFAULT NULL,
                KEY `idx_player_a` (`player_a_id`),
                KEY `idx_player_b` (`player_b_id`),
                KEY `idx_player_c` (`player_c_id`),
                KEY `idx_player_d` (`player_d_id`),
                KEY `idx_game_year` (`game_year`),
                KEY `idx_deleted` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── stat_game_hands ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `stat_game_hands` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `game_id` INT NOT NULL,
                `hand_number` INT NOT NULL,
                `hu_points` INT DEFAULT 0,
                `winning_player` VARCHAR(20) DEFAULT NULL,
                `from_player` VARCHAR(20) DEFAULT NULL,
                `player_a_points` INT DEFAULT 0,
                `player_b_points` INT DEFAULT 0,
                `player_c_points` INT DEFAULT 0,
                `player_d_points` INT DEFAULT 0,
                KEY `idx_game` (`game_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── stat_live_sessions (draft games) ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `stat_live_sessions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `draft_id` VARCHAR(64) NOT NULL,
                `created_by_id` INT DEFAULT NULL,
                `players_json` TEXT,
                `game_date` DATE DEFAULT NULL,
                `game_name` VARCHAR(200) DEFAULT NULL,
                `status` ENUM('active','saved','discarded') DEFAULT 'active',
                `taken_over_by_id` INT DEFAULT NULL,
                `timer_json` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_draft` (`draft_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── stat_live_hands ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `stat_live_hands` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `draft_id` VARCHAR(64) NOT NULL,
                `hand_number` INT NOT NULL,
                `points` INT DEFAULT 0,
                `winner` INT DEFAULT NULL,
                `from_player` INT DEFAULT NULL,
                KEY `idx_draft` (`draft_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── stat_live_penalties ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `stat_live_penalties` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `draft_id` VARCHAR(64) NOT NULL,
                `penalty_index` INT NOT NULL,
                `player_idx` INT NOT NULL,
                `amount` INT NOT NULL DEFAULT 0,
                `distribute` TINYINT(1) DEFAULT 1,
                KEY `idx_draft` (`draft_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── stat_external_tournaments ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `stat_external_tournaments` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(200) NOT NULL,
                `city` VARCHAR(100) DEFAULT NULL,
                `country_code` VARCHAR(5) DEFAULT NULL,
                `date_start` DATE DEFAULT NULL,
                `date_end` DATE DEFAULT NULL,
                `total_participants` INT DEFAULT NULL,
                `club_participants` INT DEFAULT NULL,
                `best_club_placement` INT DEFAULT NULL,
                `website_url` VARCHAR(500) DEFAULT NULL,
                `best_club_ema` VARCHAR(20) DEFAULT NULL,
                `referees_ema` VARCHAR(200) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── stat_login_log ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `stat_login_log` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `attempted_username` VARCHAR(100) DEFAULT NULL,
                `player_id` INT DEFAULT NULL,
                `success` TINYINT(1) DEFAULT 0,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── stat_dev_tasks ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `stat_dev_tasks` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(300) NOT NULL,
                `description` TEXT,
                `status` ENUM('todo','in_progress','done','cancelled') DEFAULT 'todo',
                `priority` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── stat_archived_years ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `stat_archived_years` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `year` INT NOT NULL,
                `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_year` (`year`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── permission_types ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `permission_types` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(50) NOT NULL,
                `label_sv` VARCHAR(100) NOT NULL,
                `label_en` VARCHAR(100) NOT NULL,
                `category` VARCHAR(50) DEFAULT 'system',
                `sort_order` INT DEFAULT 0,
                UNIQUE KEY `uq_key` (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── player_permissions ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `player_permissions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `player_id` INT NOT NULL,
                `permission_key` VARCHAR(50) NOT NULL,
                `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_player_permission` (`player_id`, `permission_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── password_reset_tokens ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `player_id` INT NOT NULL,
                `token` VARCHAR(64) NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `used` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_token` (`token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── Seed default permission types ──
            $pdo->exec("INSERT IGNORE INTO `permission_types` (`key`, `label_sv`, `label_en`, `category`, `sort_order`) VALUES
                ('tournament_admin', 'Turneringsadmin', 'Tournament admin', 'tournament', 1),
                ('approve_games', 'Godkänn matcher', 'Approve games', 'club', 10),
                ('register_games', 'Registrera matcher', 'Register games', 'club', 11)
            ");

            // ── Create system users ──
            $sys_hash = password_hash('system-no-login-' . bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->exec("INSERT IGNORE INTO `stat_players` (`username`, `first_name`, `last_name`, `role`, `active`, `visible_in_stats`, `password_hash`)
                VALUES ('system_admin', 'System', 'Admin', 'mainadmin', 1, 0, '{$sys_hash}')");
            $pdo->exec("INSERT IGNORE INTO `stat_players` (`username`, `first_name`, `last_name`, `role`, `active`, `visible_in_stats`, `password_hash`)
                VALUES ('system_read', 'System', 'Read', 'reader', 1, 0, '{$sys_hash}')");

            // ── Create admin user ──
            $admin_hash = password_hash($admin_password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("INSERT INTO `stat_players` (`username`, `first_name`, `last_name`, `email`, `role`, `password_hash`, `active`, `visible_in_stats`, `club_member`)
                VALUES (?, ?, ?, ?, 'mainadmin', ?, 1, 1, 1)")
                ->execute([$admin_username, $admin_first, $admin_last, $admin_email, $admin_hash]);
            
            // Also insert into club_users
            $pdo->prepare("INSERT INTO `club_users` (`username`, `first_name`, `last_name`, `email`, `role`, `password_hash`, `active`, `visible_in_stats`, `club_member`)
                VALUES (?, ?, ?, ?, 'mainadmin', ?, 1, 1, 1)")
                ->execute([$admin_username, $admin_first, $admin_last, $admin_email, $admin_hash]);

            // ── Generate config.local.php ──
            $config_content = '<?php
/**
 * LOCAL CONFIGURATION
 * Generated by install.php on ' . date('Y-m-d H:i:s') . '
 * DO NOT COMMIT THIS FILE TO GIT!
 */

// Database
define(\'DB_HOST\', ' . var_export($db_host, true) . ');
define(\'DB_NAME\', ' . var_export($db_name, true) . ');
define(\'DB_USER\', ' . var_export($db_user, true) . ');
define(\'DB_PASS\', ' . var_export($db_pass, true) . ');
define(\'DB_CHARSET\', \'utf8mb4\');

// SMTP
define(\'SMTP_HOST\', ' . var_export($smtp_host, true) . ');
define(\'SMTP_PORT\', ' . var_export((string)$smtp_port, true) . ');
define(\'SMTP_SECURE\', ' . var_export($smtp_secure, true) . ');
define(\'SMTP_USERNAME\', ' . var_export($smtp_user, true) . ');
define(\'SMTP_PASSWORD\', ' . var_export($smtp_pass, true) . ');

// Email sender
define(\'MAIL_FROM_ADDRESS\', ' . var_export($mail_from ?: $smtp_user, true) . ');
define(\'MAIL_FROM_NAME\', ' . var_export($mail_from_name ?: $site_title, true) . ');
define(\'MAIL_REPLY_TO\', ' . var_export($mail_reply ?: $mail_from ?: $smtp_user, true) . ');

// Site
define(\'SITE_URL\', ' . var_export($site_url, true) . ');
define(\'SITE_TITLE\', ' . var_export($site_title, true) . ');
define(\'SITE_TITLE_EN\', ' . var_export($site_title_en ?: $site_title, true) . ');
define(\'TAB_TITLE\', ' . var_export($tab_title ?: $site_title, true) . ');
define(\'LOGO_FILE\', ' . var_export($logo_file, true) . ');
define(\'LOGO_SIZE\', 200);

// System users
define(\'SYSTEM_ADMIN_ID\', 1);
define(\'SYSTEM_READ_ID\', 2);

// Environment
define(\'ENVIRONMENT\', \'production\');
define(\'DEBUG_MODE\', false);
define(\'DISPLAY_ERRORS\', false);

// Session
define(\'SESSION_NAME\', \'mcm_session\');
define(\'SESSION_LIFETIME\', 86400);
define(\'SESSION_TIMEOUT\', 1800);
define(\'REGISTRATION_SESSION_TIMEOUT\', 14400);

// Security
define(\'PASSWORD_COST\', 12);
define(\'MAX_LOGIN_ATTEMPTS\', 5);
define(\'LOCKOUT_TIME\', 15);

// Year
define(\'FIRST_YEAR\', ' . $first_year . ');

// Features
define(\'REQUIRE_PLAYER_CONFIRMATION\', true);

date_default_timezone_set(' . var_export($timezone, true) . ');
';
            
            if (file_put_contents(__DIR__ . '/config.local.php', $config_content) === false) {
                $errors[] = 'Could not write config.local.php. Check file permissions on the www/ directory.';
            } else {
                $success = 'Installation complete!';
                $step = 4;
            }
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
    
    if (!empty($errors)) $step = 2;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Install — Mahjong Club Manager</title>
<style>
* { box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; background: #f5f7fa; color: #333; }
.container { max-width: 640px; margin: 0 auto; }
h1 { color: #005B99; margin-bottom: 4px; }
h2 { color: #005B99; font-size: 1.1em; margin: 24px 0 10px; border-bottom: 2px solid #005B99; padding-bottom: 4px; }
.subtitle { color: #666; margin-bottom: 24px; }
.card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px; }
label { display: block; font-size: 0.88em; font-weight: 600; color: #005B99; margin-bottom: 3px; margin-top: 10px; }
input[type=text], input[type=password], input[type=email], input[type=number], select {
    width: 100%; padding: 9px 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 0.95em; font-family: inherit;
}
input:focus, select:focus { border-color: #005B99; outline: none; }
.row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
.btn { display: inline-block; padding: 12px 28px; background: #005B99; color: white; border: none; border-radius: 8px; font-size: 1.05em; font-weight: 700; cursor: pointer; }
.btn:hover { background: #004a7a; }
.btn-danger { background: #c62828; }
.error { background: #ffebee; border: 1px solid #f44336; color: #c62828; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
.success { background: #e8f5e9; border: 1px solid #4CAF50; color: #2e7d32; padding: 16px; border-radius: 8px; margin-bottom: 16px; font-weight: 600; }
.warning { background: #fff3e0; border: 1px solid #ff9800; color: #e65100; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
.hint { font-size: 0.82em; color: #888; margin-top: 2px; }
.req { color: #c62828; }
.steps { display: flex; gap: 4px; margin-bottom: 20px; }
.step-dot { width: 40px; height: 6px; border-radius: 3px; background: #ddd; }
.step-dot.active { background: #005B99; }
.step-dot.done { background: #2e7d32; }
</style>
</head>
<body>
<div class="container">
    <h1>🀄 Mahjong Club Manager</h1>
    <p class="subtitle">Installation wizard</p>

<?php if (!empty($already_installed) && $step < 4): ?>
    <div class="warning">
        <strong>⚠ config.local.php already exists.</strong> The system appears to be installed.
        If you want to reinstall, delete config.local.php first.
    </div>
    <a href="index.php" class="btn">Go to site →</a>

<?php elseif ($step === 1): ?>
    <div class="steps">
        <div class="step-dot active"></div><div class="step-dot"></div><div class="step-dot"></div>
    </div>
    
    <div class="card">
        <h2>Prerequisites</h2>
        <p>Before you begin, make sure you have:</p>
        <ul>
            <li>PHP 7.4+ with PDO MySQL extension</li>
            <li>MySQL 5.7+ or MariaDB 10.3+</li>
            <li>A database created (or a user with CREATE DATABASE permission)</li>
            <li>SMTP credentials for sending emails (optional but recommended)</li>
        </ul>
        
        <?php
        $checks = [];
        $checks['PHP version ≥ 7.4'] = version_compare(PHP_VERSION, '7.4.0', '>=');
        $checks['PDO MySQL extension'] = extension_loaded('pdo_mysql');
        $checks['JSON extension'] = extension_loaded('json');
        $checks['Mbstring extension'] = extension_loaded('mbstring');
        $checks['config.local.php writable'] = is_writable(__DIR__);
        ?>
        
        <h2>System check</h2>
        <table style="width:100%;font-size:0.95em;">
        <?php foreach ($checks as $label => $ok): ?>
            <tr>
                <td style="padding:4px 8px;"><?php echo $label; ?></td>
                <td style="padding:4px 8px;text-align:right;font-weight:600;color:<?php echo $ok ? '#2e7d32' : '#c62828'; ?>;">
                    <?php echo $ok ? '✅ OK' : '❌ Missing'; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </table>
        
        <?php $all_ok = !in_array(false, $checks, true); ?>
        
        <?php if ($all_ok): ?>
        <div style="margin-top:20px;text-align:center;">
            <a href="?step=2" class="btn">Continue →</a>
        </div>
        <?php else: ?>
        <div class="error" style="margin-top:16px;">Please fix the issues above before continuing.</div>
        <?php endif; ?>
    </div>

<?php elseif ($step === 2): ?>
    <div class="steps">
        <div class="step-dot done"></div><div class="step-dot active"></div><div class="step-dot"></div>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="error"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="?step=3">
        <input type="hidden" name="step" value="3">
        
        <div class="card">
            <h2>Database</h2>
            <div class="row2">
                <div><label>Host</label><input type="text" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? ''); ?>" placeholder="localhost"></div>
                <div><label>Database name <span class="req">*</span></label><input type="text" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>" required></div>
            </div>
            <div class="row2">
                <div><label>Database username <span class="req">*</span></label><input type="text" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>" placeholder="dbu00001" required></div>
                <div><label>Database password</label><input type="password" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>"></div>
            </div>
        </div>
        
        <div class="card">
            <h2>Your club</h2>
            <label>Club name (original language) <span class="req">*</span></label>
            <input type="text" name="site_title" value="<?php echo htmlspecialchars($_POST['site_title'] ?? ''); ?>" placeholder="Ortens Mahjongsällskap" required>
            <label>Club name (English)</label>
            <input type="text" name="site_title_en" value="<?php echo htmlspecialchars($_POST['site_title_en'] ?? ''); ?>" placeholder="Ortens Mahjong Society">
            <label>Tab title (browser tab)</label>
            <input type="text" name="tab_title" value="<?php echo htmlspecialchars($_POST['tab_title'] ?? ''); ?>" placeholder="Mahjong">
            <div class="row2">
                <div><label>Site URL <span class="req">*</span></label><input type="text" name="site_url" value="<?php echo htmlspecialchars($_POST['site_url'] ?? ''); ?>" placeholder="https://example.com/www/" required><small style="color:#888;">Base URL only — no filename</small></div>
                <div><label>Logo file</label><input type="text" name="logo_file" value="<?php echo htmlspecialchars($_POST['logo_file'] ?? 'img/logo.png'); ?>"></div>
            </div>
            <div class="row2">
                <div><label>Timezone</label><input type="text" name="timezone" value="<?php echo htmlspecialchars($_POST['timezone'] ?? 'Europe/Stockholm'); ?>"></div>
                <div><label>First year of club</label><input type="number" name="first_year" value="<?php echo htmlspecialchars($_POST['first_year'] ?? date('Y')); ?>"></div>
            </div>
        </div>
        
        <div class="card">
            <h2>Email (SMTP) — optional</h2>
            <p class="hint">Used for password resets and notifications. Can be configured later in config.local.php.</p>
            <div class="row3">
                <div><label>SMTP host</label><input type="text" name="smtp_host" value="<?php echo htmlspecialchars($_POST['smtp_host'] ?? ''); ?>" placeholder="smtp.example.com"></div>
                <div><label>Port</label><input type="number" name="smtp_port" value="<?php echo htmlspecialchars($_POST['smtp_port'] ?? '587'); ?>"></div>
                <div><label>Encryption</label>
                    <select name="smtp_secure">
                        <option value="tls" <?php echo ($_POST['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (port 587)</option>
                        <option value="ssl" <?php echo ($_POST['smtp_secure'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL (port 465)</option>
                    </select>
                </div>
            </div>
            <div class="row2">
                <div><label>SMTP username</label><input type="text" name="smtp_user" value="<?php echo htmlspecialchars($_POST['smtp_user'] ?? ''); ?>"></div>
                <div><label>SMTP password</label><input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($_POST['smtp_pass'] ?? ''); ?>"></div>
            </div>
            <div class="row2">
                <div><label>From address</label><input type="email" name="mail_from" value="<?php echo htmlspecialchars($_POST['mail_from'] ?? ''); ?>"></div>
                <div><label>Reply-to</label><input type="email" name="mail_reply" value="<?php echo htmlspecialchars($_POST['mail_reply'] ?? ''); ?>"></div>
            </div>
            <label>From name</label>
            <input type="text" name="mail_from_name" value="<?php echo htmlspecialchars($_POST['mail_from_name'] ?? ''); ?>">
        </div>
        
        <div class="card">
            <h2>Admin account</h2>
            <p class="hint">This will be the main administrator (mainadmin). You'll receive user ID #3 (first admin account).</p>
            <div class="row2">
                <div><label>First name <span class="req">*</span></label><input type="text" name="admin_first" value="<?php echo htmlspecialchars($_POST['admin_first'] ?? ''); ?>" required></div>
                <div><label>Last name <span class="req">*</span></label><input type="text" name="admin_last" value="<?php echo htmlspecialchars($_POST['admin_last'] ?? ''); ?>" required></div>
            </div>
            <div class="row2">
                <div><label>Username <span class="req">*</span></label><input type="text" name="admin_username" value="<?php echo htmlspecialchars($_POST['admin_username'] ?? ''); ?>" placeholder="admin" required></div>
                <div><label>Email</label><input type="email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>"></div>
            </div>
            <label>Password <span class="req">*</span> (min 8 characters)</label>
            <input type="password" name="admin_password" required minlength="8">
        </div>
        
        <div style="text-align:center;margin:20px 0;">
            <button type="submit" class="btn">🚀 Install</button>
        </div>
    </form>

<?php elseif ($step === 4): ?>
    <div class="steps">
        <div class="step-dot done"></div><div class="step-dot done"></div><div class="step-dot done"></div>
    </div>
    
    <div class="success">✅ Installation complete! Your Mahjong Club Manager is ready.</div>
    
    <div class="card">
        <h2>What was created</h2>
        <ul>
            <li>✅ 14 database tables</li>
            <li>✅ System users (system_admin, system_read)</li>
            <li>✅ Your admin account</li>
            <li>✅ Default permission types</li>
            <li>✅ config.local.php</li>
        </ul>
        
        <h2>⚠ Important: Delete install.php!</h2>
        <p>For security, <strong>delete this file now</strong>. Anyone who can access install.php could overwrite your configuration.</p>
        
        <h2>Next steps</h2>
        <ol>
            <li><strong>Delete install.php</strong> from your server</li>
            <li><a href="login.php">Log in</a> with the admin account you just created</li>
            <li>Go to <strong>Admin → Users</strong> to add more members</li>
            <li>Upload your club logo to the <code>img/</code> folder</li>
        </ol>
        
        <div style="text-align:center;margin-top:20px;">
            <a href="login.php" class="btn">Log in →</a>
        </div>
    </div>
<?php endif; ?>
</div>
</body>
</html>
