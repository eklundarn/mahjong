<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$conn = getDbConnection();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = t('changepw_err_empty');
    } elseif ($new_password !== $confirm_password) {
        $error = t('changepw_err_mismatch');
    } elseif (strlen($new_password) < 8) {
        $error = t('changepw_err_short');
    } else {
        $stmt = $conn->prepare("SELECT password_hash FROM stat_players WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!password_verify($current_password, $user['password_hash'])) {
            $error = t('changepw_err_wrong');
        } else {
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE stat_players SET password_hash = ? WHERE id = ?");
            $stmt->execute([$new_hash, $_SESSION['user_id']]);
            $success = t('changepw_success');
        }
    }
}

includeHeader();
?>
<style>
.pw-wrapper { position: relative; }
.pw-wrapper input { padding-right: 40px !important; }
.pw-eye {
    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    cursor: pointer; font-size: 1.1em; color: #333; background: none; border: none;
    padding: 0; line-height: 1;
}
body[data-theme="dark"] .pw-eye { color: white; }
</style>

<div style="max-width: 500px; margin: 50px auto;">
    <div style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h2 class="changepw-heading" style="margin-top: 0; color: #005B99;">🔑 <?php echo t('changepw_heading'); ?></h2>

        <?php if ($success): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            ✅ <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
            ❌ <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">
                    <?php echo t('changepw_current'); ?>
                </label>
                <div class="pw-wrapper">
                <input type="password"
                       name="current_password"
                       style="width: 100%; padding: 10px; font-size: 1em; border: 2px solid #333; border-radius: 4px; background: white; color: #333;"
                       required autofocus>
                <button type="button" class="pw-eye" onclick="togglePw('current_password')" title="Visa/dölj lösenord">👁</button>
            </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">
                    <?php echo t('changepw_new'); ?>
                </label>
                <div class="pw-wrapper">
                <input type="password"
                       name="new_password"
                       style="width: 100%; padding: 10px; font-size: 1em; border: 2px solid #333; border-radius: 4px; background: white; color: #333;"
                       minlength="8" required>
                <button type="button" class="pw-eye" onclick="togglePw('new_password')" title="Visa/dölj lösenord">👁</button>
            </div>
                <small style="color: #333;"><?php echo t('changepw_new_hint'); ?></small>
            </div>

            <div style="margin-bottom: 25px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">
                    <?php echo t('changepw_confirm'); ?>
                </label>
                <div class="pw-wrapper">
                <input type="password"
                       name="confirm_password"
                       style="width: 100%; padding: 10px; font-size: 1em; border: 2px solid #333; border-radius: 4px; background: white; color: #333;"
                       minlength="8" required>
                <button type="button" class="pw-eye" onclick="togglePw('confirm_password')" title="Visa/dölj lösenord">👁</button>
            </div>
            </div>

            <button type="submit" class="btn" style="width: 100%; padding: 12px; font-size: 1.1em;">
                <?php echo t('changepw_btn'); ?>
            </button>
        </form>

        <div style="margin-top: 20px; text-align: center;">
            <a href="home.php" style="color: #005B99;"><?php echo t('changepw_back'); ?></a>
        </div>
    </div>
</div>


<script>
function togglePw(name) {
    var inp = document.querySelector('[name="' + name + '"]');
    var btn = inp ? inp.parentElement.querySelector('.pw-eye') : null;
    if (!inp) return;
    if (inp.type === 'password') {
        inp.type = 'text';
        if (btn) btn.textContent = '🙈';
    } else {
        inp.type = 'password';
        if (btn) btn.textContent = '👁';
    }
}
</script>
<?php include 'includes/footer.php'; ?>
