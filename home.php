<?php
require_once 'config.php';

/**
 * Översätt rollnamn till användarvänlig benämning
 */
function getRoleDisplayName($role) {
    $role_names = [
        'mainadmin' => t('role_mainadmin_long'),
        'admin' => t('role_admin_long'),
        'superuser' => t('role_superuser_long'),
        'player' => t('role_registrator_long'),
        'reader' => t('role_readaccess_long')
    ];
    
    return $role_names[$role] ?? ucfirst($role);
}

includeHeader();
?>

<h2><?php echo t('home_welcome'); ?></h2>

<div style="margin: 30px 0;">
    <?php if (isLoggedIn()): ?>
        <?php $user = getCurrentUser(); ?>
        <div class="message info">
            <p><?php echo t('home_logged_in_as'); ?> <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></p>
            <p><?php echo t('home_role'); ?>: <strong><?php echo htmlspecialchars(getRoleDisplayName($user['role'])); ?></strong></p>
        </div>
        
        <h3><?php echo t('home_what_to_do'); ?></h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0;">
            
            <div class="home-stat-box" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #4CAF50;">
                <h4>📊 <?php echo t('home_card_stats_title'); ?></h4>
                <p><?php echo t('home_card_stats_desc'); ?></p>
                <a href="ranking.php" class="btn" style="margin-top: 10px;"><?php echo t('home_card_stats_btn'); ?></a>
            </div>
            
            <?php if (hasRole('player')): ?>
            <div class="login-info-box" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #2196F3;">
                <h4>🀄 <?php echo t('home_card_newgame_title'); ?></h4>
                <p><?php echo t('home_card_newgame_desc'); ?></p>
                <a href="newgame.php" class="btn" style="margin-top: 10px; background: #2196F3;"><?php echo t('home_card_newgame_btn'); ?></a>
            </div>
            
            <div class="home-stat-box" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #FF9800;">
                <h4>📋 <?php echo t('home_card_games_title'); ?></h4>
                <p><?php echo t('home_card_games_desc'); ?></p>
                <a href="games.php" class="btn" style="margin-top: 10px; background: #FF9800;"><?php echo t('home_card_games_btn'); ?></a>
            </div>
            <?php endif; ?>
            
            <div class="home-stat-box" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #9C27B0;">
                <h4>👥 <?php echo t('home_card_players_title'); ?></h4>
                <p><?php echo t('home_card_players_desc'); ?></p>
                <a href="players.php" class="btn" style="margin-top: 10px; background: #9C27B0;"><?php echo t('home_card_players_btn'); ?></a>
            </div>
            
            <div class="home-stat-box" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #3F51B5;">
                <h4>🏠 <?php echo t('home_card_mypage_title'); ?></h4>
                <p><?php echo t('home_card_mypage_desc'); ?></p>
                <a href="my-page.php" class="btn" style="margin-top: 10px; background: #3F51B5;"><?php echo t('home_card_mypage_btn'); ?></a>
            </div>
            
            <?php if (hasRole('superuser')): ?>
            <div class="home-stat-box" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #E91E63;">
                <h4>⚙️ <?php echo t('home_card_users_title'); ?></h4>
                <p><?php echo t('home_card_users_desc'); ?></p>
                <a href="users.php" class="btn" style="margin-top: 10px; background: #E91E63;"><?php echo t('home_card_users_btn'); ?></a>
            </div>
            <?php endif; ?>
            
            <?php if (hasRole('admin')): ?>
            <div class="home-stat-box" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #f44336;">
                <h4>🔧 <?php echo t('home_card_admin_title'); ?></h4>
                <p><?php echo t('home_card_admin_desc'); ?></p>
                <a href="administration.php" class="btn btn-danger" style="margin-top: 10px;"><?php echo t('home_card_admin_btn'); ?></a>
            </div>
            <?php endif; ?>
            
        </div>
        
    <?php else: ?>
        <div style="text-align: center; padding: 50px 20px;">
            <p style="font-size: 1.2em; margin-bottom: 30px;">
                <?php echo t('home_intro1'); ?>
            </p>
            <p style="font-size: 1.2em; margin-bottom: 30px;">
                <?php echo t('home_intro2'); ?>
            </p>
            <a href="login.php" class="btn" style="font-size: 1.1em; padding: 15px 40px;">
                <?php echo t('home_login_btn'); ?>
            </a>
        </div>

    <?php endif; ?>
</div>

<?php
// Visa lite systemstatistik
try {
    $conn = getDbConnection();
    $current_year = getCurrentYear();
    
    // Räkna spelare
    $stmt = $conn->query("SELECT COUNT(*) as count FROM stat_players WHERE id NOT IN (1, 2)");
    $total_players = $stmt->fetch()['count'];
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM stat_players WHERE visible_in_stats = 0 AND id NOT IN (1, 2)");
    $hidden_players = $stmt->fetch()['count'];
    
    // Räkna matcher detta år (endast godkända)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM stat_games WHERE game_year = ? AND approved = 1");
    $stmt->execute([$current_year]);
    $game_count = $stmt->fetch()['count'];
    
    if ($total_players > 0 || $game_count > 0) {
        echo '<div class="home-stats-section" style="margin-top: 50px; padding: 20px; background: #e8f5e9; border-radius: 8px;">';
        echo '<h3>' . t('home_stats_heading') . ' ' . $current_year . '</h3>';
        echo '<p>' . t('home_total_players') . ': <strong>' . $total_players . '</strong>';
        if ($hidden_players > 0) {
            echo ' (' . t('home_of_which') . ' ' . $hidden_players . ' ' . t('home_hidden_stats') . ')';
        }
        echo '</p>';
        echo '<p>' . t('home_total_games') . ': <strong>' . $game_count . '</strong></p>';
        echo '</div>';
    }
} catch (PDOException $e) {
    // Visa inget fel här, bara hoppa över statistiken
}
?>

<?php includeFooter(); ?>