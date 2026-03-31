<?php
/**
 * about-club.php
 * Om klubben / About the club
 *
 * Upload: www/about-club.php
 */
require_once 'config.php';

$page_title = t('nav_about_club');
include 'includes/header.php';
?>

<h2><?php echo t('nav_about_club'); ?></h2>

<div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 30px; line-height: 1.8;">

    <h3 style="color: var(--blue-dark); margin-top: 0;"><?php echo t('about_club_heading'); ?></h3>
    <p><?php echo t('about_club_text'); ?></p>

    <h3 style="color: var(--blue-dark);"><?php echo t('about_play_heading'); ?></h3>
    <p><?php echo t('about_play_text'); ?></p>

    <h3 style="color: var(--blue-dark);"><?php echo t('about_training_heading'); ?></h3>
    <p><?php echo t('about_training_text'); ?></p>

    <h3 style="color: var(--blue-dark);"><?php echo t('about_contact_heading'); ?></h3>
    <div style="background: #f5f7fa; border-radius: 8px; padding: 20px; border-left: 4px solid #005B99;">
        <p style="font-weight: 600; font-size: 1.05em; margin-top: 0;">Ingrid-Maria Erlandsson</p>
        <p style="color: #555; margin-top: -8px;"><?php echo t('nav_about_club'); ?></p>
        <p style="margin-bottom: 0;">
            📞 <a href="tel:+46721864396" style="color: #005B99; text-decoration: none;">+46 721 864 396</a><br>
            ✉️ <a href="mailto:ingrid-maria@varbergmahjong.se" style="color: #005B99; text-decoration: none;">ingrid-maria@varbergmahjong.se</a>
        </p>
    </div>

    <h3 style="color: var(--blue-dark);"><?php echo t('about_social_heading'); ?></h3>
    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 12px;">
        <a href="https://www.facebook.com/groups/202875706199342/" target="_blank"
           style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: #1877F2; color: white; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 1em;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            Facebook
        </a>
        <a href="https://www.instagram.com/varbergsmahjongsallskap/" target="_blank"
           style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); color: white; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 1em;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
            Instagram
        </a>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
