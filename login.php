<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
set_exception_handler(function($e) {
    file_put_contents(__DIR__ . '/php_errors.log', date('Y-m-d H:i:s') . ' EXCEPTION: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND);
    echo '<pre>EXCEPTION: ' . htmlspecialchars($e->getMessage()) . ' in ' . $e->getFile() . ':' . $e->getLine() . '</pre>';
});
require_once 'config.php';

// Om redan inloggad, gå till startsidan (eller redirect)
if (isLoggedIn()) {
    $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
    if ($redirect && strpos($redirect, '/') !== 0 && strpos($redirect, 'http') !== 0) {
        header("Location: " . $redirect);
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = '';

// Hantera login-formulär
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = t('login_error_empty');
    } else {
        if (loginUser($username, $password)) {
            $redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? '';
            $dest = ($redirect && strpos($redirect, '/') !== 0 && strpos($redirect, 'http') !== 0)
                ? SITE_URL . '/' . $redirect
                : SITE_URL . '/home.php';
            header("Location: " . $dest);
            exit;
        } else {
            $error = t('login_error');
        }
    }
}

includeHeader();
?>

<div style="max-width: 400px; margin: 50px auto;">
    <div style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h2 style="text-align: center; margin-top: 0;"><?php echo t('login_title'); ?></h2>
        
        <?php if ($error): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect'] ?? $_POST['redirect'] ?? ''); ?>">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">
                    <?php echo t('login_username'); ?>:
                </label>
                <input type="text" 
                       name="username" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       style="width: 100%; padding: 10px; font-size: 1em; border: 1px solid #ddd; border-radius: 4px;"
                       required
                       autofocus>
            </div>
            
            <div style="margin-bottom: 25px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">
                    <?php echo t('login_password'); ?>:
                </label>
                <input type="password" 
                       name="password" 
                       style="width: 100%; padding: 10px; font-size: 1em; border: 1px solid #ddd; border-radius: 4px;"
                       required>
            </div>
            
            <button type="submit" class="btn" style="width: 100%; padding: 12px; font-size: 1.1em;">
                Logga in
            </button>
        </form>
        
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666;">
            <p style="font-size: 0.9em;">
                <?php echo t('login_forgot_password_prefix'); ?>
                <a href="forgot-password.php" style="color:#005B99;"><?php echo t('login_forgot_password_link'); ?></a>
            </p>
        </div>
    </div>
</div>

<?php includeFooter(); ?>
