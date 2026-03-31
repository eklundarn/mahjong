<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

$show_confirmation = !isset($_POST['confirm']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // Logga ut användaren
    session_destroy();
    redirect('home.php');
}

includeHeader();
?>

<div style="max-width: 500px; margin: 50px auto; text-align: center;">
    <h2><?php echo t('logout_heading'); ?></h2>
    
    <p style="margin: 30px 0; font-size: 1.1em;">
        <?php echo t('logout_question'); ?>
    </p>
    
    <form method="POST" action="logout.php">
        <input type="hidden" name="confirm" value="1">
        <button type="submit" class="btn"><?php echo t('logout_confirm'); ?></button>
        <a href="home.php" class="btn btn-secondary" style="margin-left: 10px;"><?php echo t('logout_cancel'); ?></a>
    </form>
</div>

<?php includeFooter(); ?>