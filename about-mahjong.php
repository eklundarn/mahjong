<?php
/**
 * about-mahjong.php
 * Om Mahjong / About Mahjong
 *
 * Upload: www/about-mahjong.php
 */
require_once 'config.php';

$page_title = t('nav_about_mahjong');
include 'includes/header.php';
?>

<h2><?php echo t('nav_about_mahjong'); ?></h2>

<div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 30px; line-height: 1.8;">

    <h3 style="color: var(--blue-dark); margin-top: 0;"><?php echo t('about_mj_intro_heading'); ?></h3>
    <p><?php echo t('about_mj_intro_text1'); ?></p>
    <p><?php echo t('about_mj_intro_text2'); ?></p>
    <p><?php echo sprintf(t('about_mj_rules_text'), '<a href="rules.php" style="color: var(--blue); text-decoration: underline;">' . t('about_mj_rules_link') . '</a>'); ?></p>
    <p><?php echo t('about_mj_competition_text'); ?></p>

</div>

<?php include 'includes/footer.php'; ?>
