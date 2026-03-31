<?php
require_once 'config.php';

$sv = (currentLang() === 'sv');

if (isLoggedIn()) {
    header("Location: " . SITE_URL . "/home.php");
    exit;
}

$step    = $_GET['step'] ?? 'request';
$token   = $_GET['token'] ?? '';
$message = '';
$error   = '';

// ── STEG 1: Ta emot e-post och skicka återställningslänk ──
if ($step === 'request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || strlen(trim($email)) < 2) {
        $error = t('forgot_error_invalid_email');
    } else {
        $conn = getDbConnection();
        $stmt = $conn->prepare("
            SELECT id, first_name, username, email FROM stat_players 
            WHERE email = ? OR username = ? OR ema_number = ?
            LIMIT 1
        ");
        $stmt->execute([$email, $email, $email]);
        $user = $stmt->fetch();
        // Använd användarens riktiga e-post för att skicka mejlet
        if ($user) $email = $user['email'];

        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);

            $conn->prepare("CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )")->execute();

            $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?")->execute([$user['id']]);
            $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")->execute([$user['id'], $token, $expires]);

            $resetUrl  = SITE_URL . "/forgot-password.php?step=reset&token=" . $token;
            $firstName = $user['first_name'] ?: $user['username'];

            if ($sv) {
                $subject = "Återställ ditt lösenord";
                $body = "<p>Hej $firstName,</p>
                <p>Vi har tagit emot en begäran om att återställa lösenordet för ditt konto.</p>
                <p style='margin:24px 0;'>
                    <a href='$resetUrl' style='background:#005B99;color:white;padding:12px 24px;border-radius:5px;text-decoration:none;font-weight:bold;'>
                        Återställ lösenord
                    </a>
                </p>
                <p style='color:#666;font-size:0.9em;'>Länken är giltig i 1 timme. Om du inte begärt detta kan du ignorera detta mejl.</p>
                <p style='color:#999;font-size:0.85em;'>' . SITE_TITLE . '</p>";
            } else {
                $subject = "Reset your password";
                $body = "<p>Hi $firstName,</p>
                <p>We received a request to reset the password for your account.</p>
                <p style='margin:24px 0;'>
                    <a href='$resetUrl' style='background:#005B99;color:white;padding:12px 24px;border-radius:5px;text-decoration:none;font-weight:bold;'>
                        Reset password
                    </a>
                </p>
                <p style='color:#666;font-size:0.9em;'>The link is valid for 1 hour. If you did not request this, you can ignore this e-mail.</p>
                <p style='color:#999;font-size:0.85em;'>' . (defined('SITE_TITLE_EN') ? SITE_TITLE_EN : SITE_TITLE) . '</p>";
            }

            $sent = sendEmail($email, $subject, $body);
            $message = t('forgot_sent');
        } else {
            $message = t('forgot_sent'); // Säkerhetsreason – avslöja inte om e-post finns
        }
    }
}

// ── STEG 2: Visa formulär för nytt lösenord ──
$tokenRow = null;
if ($step === 'reset') {
    if (!$token) {
        $error = t('forgot_error_invalid_token');
        $step  = 'request';
    } else {
        $conn = getDbConnection();
        $stmt = $conn->prepare("
            SELECT t.*, p.username FROM password_reset_tokens t
            JOIN stat_players p ON p.id = t.user_id
            WHERE t.token = ? AND t.used = 0 AND t.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $tokenRow = $stmt->fetch();
        if (!$tokenRow) {
            $error = t('forgot_error_expired');
            $step  = 'request';
        }
    }
}

// ── STEG 3: Spara nytt lösenord ──
if ($step === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass     = $_POST['password'] ?? '';
    $confirmPass = $_POST['password_confirm'] ?? '';
    $postToken   = $_POST['token'] ?? '';

    if (strlen($newPass) < 8) {
        $error = t('forgot_error_too_short');
    } elseif ($newPass !== $confirmPass) {
        $error = t('forgot_error_mismatch');
    } else {
        $conn = getDbConnection();
        $stmt = $conn->prepare("
            SELECT t.*, p.id as player_id FROM password_reset_tokens t
            JOIN stat_players p ON p.id = t.user_id
            WHERE t.token = ? AND t.used = 0 AND t.expires_at > NOW()
        ");
        $stmt->execute([$postToken]);
        $tokenRow = $stmt->fetch();
        if (!$tokenRow) {
            $error = t('forgot_error_expired');
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $conn->prepare("UPDATE stat_players SET password_hash = ? WHERE id = ?")->execute([$hash, $tokenRow['player_id']]);
            $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?")->execute([$postToken]);
            $message = t('forgot_success');
            $step    = 'done';
        }
    }
}

includeHeader();
?>

<div style="max-width:420px;margin:50px auto;">
  <div style="background:white;padding:40px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">

    <?php if ($step === 'done'): ?>
      <h2 style="text-align:center;margin-top:0;">✅ <?php echo t('forgot_done_title'); ?></h2>
      <p style="text-align:center;color:#333;"><?php echo $message; ?></p>
      <a href="login.php" class="btn" style="display:block;text-align:center;margin-top:20px;"><?php echo t('login_title'); ?></a>

    <?php elseif ($message): ?>
      <h2 style="text-align:center;margin-top:0;">📧 <?php echo t('forgot_check_email_title'); ?></h2>
      <p style="text-align:center;color:#333;"><?php echo $message; ?></p>
      <a href="login.php" style="display:block;text-align:center;margin-top:20px;color:#005B99;"><?php echo t('login_title'); ?></a>

    <?php elseif ($step === 'reset' && $tokenRow): ?>
      <h2 style="text-align:center;margin-top:0;"><?php echo t('forgot_new_password_title'); ?></h2>
      <?php if ($error): ?>
        <div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:5px;margin-bottom:16px;"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <div style="margin-bottom:16px;">
          <label style="display:block;margin-bottom:5px;font-weight:bold;"><?php echo t('forgot_new_password'); ?>:</label>
          <input type="password" name="password" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;font-size:1em;" required minlength="8">
        </div>
        <div style="margin-bottom:24px;">
          <label style="display:block;margin-bottom:5px;font-weight:bold;"><?php echo t('forgot_confirm_password'); ?>:</label>
          <input type="password" name="password_confirm" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;font-size:1em;" required minlength="8">
        </div>
        <button type="submit" class="btn" style="width:100%;padding:12px;font-size:1.1em;"><?php echo t('forgot_save_password'); ?></button>
      </form>

    <?php else: ?>
      <h2 style="text-align:center;margin-top:0;"><?php echo t('forgot_title'); ?></h2>
      <?php if ($error): ?>
        <div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:5px;margin-bottom:16px;"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <p style="color:#666;margin-bottom:20px;font-size:0.95em;"><?php echo t('forgot_description_extended'); ?></p>
      <form method="POST">
        <div style="margin-bottom:20px;">
          <label style="display:block;margin-bottom:5px;font-weight:bold;"><?php echo t('forgot_identifier_label'); ?>:</label>
          <input type="text" name="email" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;font-size:1em;" required autofocus>
        </div>
        <button type="submit" class="btn" style="width:100%;padding:12px;font-size:1.1em;"><?php echo t('forgot_send_button'); ?></button>
      </form>
      <div style="text-align:center;margin-top:20px;">
        <a href="login.php" style="color:#005B99;font-size:0.9em;"><?php echo t('login_title'); ?></a>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php includeFooter(); ?>
