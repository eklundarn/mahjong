<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

// Kräv inloggning
if (!isLoggedIn()) {
    redirect('login.php');
}

$conn = getDbConnection();
$current_year = getCurrentYear();
$user = getCurrentUser();
$player_id = $user['id'];

// Hämta spelarens fullständiga information
$stmt = $conn->prepare("
    SELECT id, id, first_name, last_name, email, city, club, ema_number, role
    FROM stat_players
    WHERE id = ?
");
$stmt->execute([$player_id]);
$player_info = $stmt->fetch();

// Kolla om användaren har Spelar-ID (spelar matcher)
$vms = $player_info['id'];
$is_player = !empty($vms);

// Kolla om systemanvändare
$is_system_user = in_array($vms, [1, 2]);

// Initialisera variabler
$stats = ['matches_played' => 0, 'total_mp' => 0, 'avg_mp' => 0, 'total_tp' => 0, 'avg_tp' => 0, 'biggest_hand' => 0];
$rank = '-';
$stat_games = [];

// Hämta statistik och matcher bara om användaren har Spelar-ID
if ($is_player) {
    // Hämta spelarens statistik för aktuellt år
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as matches_played,
            SUM(mp) as total_mp,
            ROUND(AVG(mp), 2) as avg_mp,
            SUM(tp) as total_tp,
            ROUND(AVG(tp), 2) as avg_tp,
            MAX(biggest) as biggest_hand
        FROM (
            SELECT 
                CASE 
                    WHEN player_a_id = ? THEN player_a_minipoints
                    WHEN player_b_id = ? THEN player_b_minipoints
                    WHEN player_c_id = ? THEN player_c_minipoints
                    WHEN player_d_id = ? THEN player_d_minipoints
                END as mp,
                CASE 
                    WHEN player_a_id = ? THEN player_a_tablepoints
                    WHEN player_b_id = ? THEN player_b_tablepoints
                    WHEN player_c_id = ? THEN player_c_tablepoints
                    WHEN player_d_id = ? THEN player_d_tablepoints
                END as tp,
                CASE 
                    WHEN biggest_hand_player_id LIKE CONCAT('%', ?, '%') THEN biggest_hand_points
                    ELSE 0
                END as biggest
            FROM stat_games
            WHERE game_year = ?
            AND approved = 1 AND deleted_at IS NULL
            AND (player_a_id = ? OR player_b_id = ? OR player_c_id = ? OR player_d_id = ?)
        ) as player_games
    ");
    $stmt->execute([$vms, $vms, $vms, $vms, $vms, $vms, $vms, $vms, $vms, $current_year, $vms, $vms, $vms, $vms]);
    $stats = $stmt->fetch();

    // Hämta spelarens ranking för året – samma logik som ranking.php
    // Sorterar på avg bordspoäng DESC, avg minipoäng DESC, total bordspoäng DESC
    $stmt = $conn->prepare("
        SELECT ranking
        FROM (
            SELECT id,
                ROW_NUMBER() OVER (
                    ORDER BY avg_tablepoints DESC, avg_minipoints DESC, total_tablepoints DESC, games_played DESC
                ) AS ranking
            FROM (
                SELECT
                    p.id,
                    COUNT(DISTINCT g.id) AS games_played,
                    ROUND(SUM(CASE
                        WHEN g.player_a_id = p.id THEN g.player_a_tablepoints
                        WHEN g.player_b_id = p.id THEN g.player_b_tablepoints
                        WHEN g.player_c_id = p.id THEN g.player_c_tablepoints
                        WHEN g.player_d_id = p.id THEN g.player_d_tablepoints
                    END) / NULLIF(COUNT(DISTINCT g.id), 0), 2) AS avg_tablepoints,
                    SUM(CASE
                        WHEN g.player_a_id = p.id THEN g.player_a_tablepoints
                        WHEN g.player_b_id = p.id THEN g.player_b_tablepoints
                        WHEN g.player_c_id = p.id THEN g.player_c_tablepoints
                        WHEN g.player_d_id = p.id THEN g.player_d_tablepoints
                    END) AS total_tablepoints,
                    ROUND(SUM(CASE
                        WHEN g.player_a_id = p.id THEN g.player_a_minipoints
                        WHEN g.player_b_id = p.id THEN g.player_b_minipoints
                        WHEN g.player_c_id = p.id THEN g.player_c_minipoints
                        WHEN g.player_d_id = p.id THEN g.player_d_minipoints
                    END) / NULLIF(COUNT(DISTINCT g.id), 0), 2) AS avg_minipoints
                FROM stat_players p
                INNER JOIN stat_games g ON (
                    (g.player_a_id = p.id OR g.player_b_id = p.id OR
                     g.player_c_id = p.id OR g.player_d_id = p.id)
                    AND g.approved = 1 AND g.deleted_at IS NULL AND g.game_year = ?
                )
                WHERE p.visible_in_stats = 1
                AND p.id NOT IN (1, 2)
                GROUP BY p.id
                HAVING games_played >= 1
            ) ranked_players
        ) rankings
        WHERE id = ?
    ");
    $stmt->execute([$current_year, $vms]);
    $rank_result = $stmt->fetch();
    $rank = $rank_result ? $rank_result['ranking'] : '-';

    // Hämta spelarens matcher
    $stmt = $conn->prepare("
        SELECT 
            g.id,
            g.game_number,
            g.game_date,
            g.player_a_id, pa.first_name as pa_first, pa.last_name as pa_last, 
            g.player_a_minipoints, g.player_a_tablepoints,
            g.player_b_id, pb.first_name as pb_first, pb.last_name as pb_last, 
            g.player_b_minipoints, g.player_b_tablepoints,
            g.player_c_id, pc.first_name as pc_first, pc.last_name as pc_last, 
            g.player_c_minipoints, g.player_c_tablepoints,
            g.player_d_id, pd.first_name as pd_first, pd.last_name as pd_last, 
            g.player_d_minipoints, g.player_d_tablepoints,
            g.biggest_hand_points,
            g.hands_played
        FROM stat_games g
        LEFT JOIN stat_players pa ON g.player_a_id = pa.id
        LEFT JOIN stat_players pb ON g.player_b_id = pb.id
        LEFT JOIN stat_players pc ON g.player_c_id = pc.id
        LEFT JOIN stat_players pd ON g.player_d_id = pd.id
        WHERE g.game_year = ?
        AND g.approved = 1 AND g.deleted_at IS NULL
        AND (g.player_a_id = ? OR g.player_b_id = ? OR g.player_c_id = ? OR g.player_d_id = ?)
        ORDER BY g.game_date DESC, g.game_number DESC
    ");
    $stmt->execute([$current_year, $vms, $vms, $vms, $vms]);
    $stat_games = $stmt->fetchAll();
}

includeHeader();
?>
<style>
body[data-theme="dark"] { --sticky-bg: #252B38; }
</style>

<h1>🏠 <?php echo t('mypage_heading'); ?></h1>

<?php 
// Pending game confirmations
$require_conf = defined('REQUIRE_PLAYER_CONFIRMATION') && REQUIRE_PLAYER_CONFIRMATION;
if ($require_conf && $is_player):
    $pending_sql = "SELECT g.id, g.game_number, g.game_date, g.game_name,
            pa.first_name as pa_first, pa.last_name as pa_last,
            pb.first_name as pb_first, pb.last_name as pb_last,
            pc.first_name as pc_first, pc.last_name as pc_last,
            pd.first_name as pd_first, pd.last_name as pd_last
        FROM stat_games g
        LEFT JOIN stat_players pa ON g.player_a_id = pa.id
        LEFT JOIN stat_players pb ON g.player_b_id = pb.id
        LEFT JOIN stat_players pc ON g.player_c_id = pc.id
        LEFT JOIN stat_players pd ON g.player_d_id = pd.id
        WHERE g.deleted_at IS NULL AND g.approved = 0
        AND (
            (g.player_a_id = ? AND g.player_a_confirmed_at IS NULL) OR
            (g.player_b_id = ? AND g.player_b_confirmed_at IS NULL) OR
            (g.player_c_id = ? AND g.player_c_confirmed_at IS NULL) OR
            (g.player_d_id = ? AND g.player_d_confirmed_at IS NULL)
        )
        ORDER BY g.game_date DESC";
    $pstmt = $conn->prepare($pending_sql);
    $pstmt->execute([$vms, $vms, $vms, $vms]);
    $pending_games = $pstmt->fetchAll();
    
    if (!empty($pending_games)):
?>
<div style="background:#fff3cd;border:2px solid #ff9800;border-radius:8px;padding:20px;margin-bottom:24px;">
    <h2 style="margin-top:0;color:#e65100;">⏳ <?php echo t('confirm_pending_title'); ?> (<?php echo count($pending_games); ?>)</h2>
    <?php foreach ($pending_games as $pg): 
        $names = [];
        foreach (['pa','pb','pc','pd'] as $pf) {
            if ($pg[$pf.'_first']) $names[] = $pg[$pf.'_first'] . ' ' . substr($pg[$pf.'_last'],0,1) . '.';
        }
    ?>
    <div style="background:white;border-radius:6px;padding:12px 16px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
        <div>
            <strong><?php echo t('confirm_match'); ?> #<?php echo $pg['game_number']; ?></strong>
            <span style="color:#666;font-size:0.9em;margin-left:8px;"><?php echo $pg['game_date']; ?></span>
            <span style="color:#888;font-size:0.85em;margin-left:8px;"><?php echo implode(', ', $names); ?></span>
        </div>
        <a href="view-game.php?id=<?php echo $pg['id']; ?>" class="btn" style="background:#ff9800;color:white;padding:6px 16px;font-size:0.9em;">
            <?php echo t('confirm_review_ok'); ?> →
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php 
    endif;
endif; 
?>

<!-- PERSONUPPGIFTER -->
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px; margin-bottom: 30px;">
    <h2 style="margin-top: 0; color: white;"><?php echo t('mypage_personal_info'); ?></h2>
    
    <?php if ($is_player): ?>
        <!-- Fullständig info för spelare -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div>
                <strong style="opacity: 0.9;"><?php echo t('mypage_name'); ?></strong><br>
                <span style="font-size: 1.2em;"><?php echo htmlspecialchars($player_info['first_name'] . ' ' . $player_info['last_name']); ?></span>
            </div>
            <div>
                <strong style="opacity: 0.9;"><?php echo t('mypage_vms'); ?></strong><br>
                <span style="font-size: 1.2em;"><?php echo htmlspecialchars($player_info['id']); ?></span>
            </div>
            <div>
                <strong style="opacity: 0.9;"><?php echo t('mypage_club'); ?></strong><br>
                <span style="font-size: 1.2em;"><?php echo htmlspecialchars($player_info['club'] ?? '-'); ?></span>
            </div>
            <div>
                <strong style="opacity: 0.9;"><?php echo t('mypage_email'); ?></strong><br>
                <span style="font-size: 1.2em;"><?php echo htmlspecialchars($player_info['email'] ?? '-'); ?></span>
            </div>
            <div>
                <strong style="opacity: 0.9;"><?php echo t('mypage_role'); ?></strong><br>
                <span style="font-size: 1.2em;">
                    <?php 
                    $role_labels = [
                        'mainadmin' => t('role_mainadmin'),
                        'admin' => t('role_admin'),
                        'superuser' => t('role_superuser'),
                        'player' => t('role_registrator'),
                        'reader' => t('role_readaccess')
                    ];
                    echo $role_labels[$player_info['role']] ?? $player_info['role'];
                    ?>
                </span>
            </div>
        </div>
    <?php else: ?>
        <!-- Förenklad info för icke-spelare (admins utan Spelar-ID) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div>
                <strong style="opacity: 0.9;"><?php echo t('mypage_name'); ?></strong><br>
                <span style="font-size: 1.3em;"><?php echo htmlspecialchars($player_info['first_name'] . ' ' . $player_info['last_name']); ?></span>
            </div>
            <div>
                <strong style="opacity: 0.9;"><?php echo t('mypage_role'); ?></strong><br>
                <span style="font-size: 1.3em;">
                    <?php 
                    $role_labels = [
                        'mainadmin' => t('role_mainadmin'),
                        'admin' => t('role_admin'),
                        'superuser' => t('role_superuser'),
                        'player' => t('role_registrator'),
                        'reader' => t('role_readaccess')
                    ];
                    echo $role_labels[$player_info['role']] ?? $player_info['role'];
                    ?>
                </span>
            </div>
        </div>
        
        <div style="margin-top: 25px; padding: 20px; background: rgba(255,255,255,0.1); border-radius: 5px; border-left: 4px solid white;">
            <?php if (isset($player_info['username']) && $player_info['username'] == 'vmsadmin' || (in_array($player_info['role'], ['mainadmin', 'admin']) && empty($vms))): ?>
                <p style="margin: 0; font-size: 1.1em;">
                    <strong>ℹ️ <?php echo t('mypage_systemuser_title'); ?></strong><br>
                    <span style="opacity: 0.9;">
                        <?php echo t('mypage_systemuser_desc'); ?>
                    </span>
                </p>
            <?php elseif (isset($player_info['username']) && $player_info['username'] == 'vmsread'): ?>
                <p style="margin: 0; font-size: 1.1em;">
                    <strong>ℹ️ <?php echo t('mypage_readaccess_title'); ?></strong><br>
                    <span style="opacity: 0.9;">
                        <?php echo t('mypage_readaccess_desc'); ?>
                    </span>
                </p>
            <?php else: ?>
                <p style="margin: 0; font-size: 1.1em;">
                    <strong>ℹ️ <?php echo t('mypage_novms_title'); ?></strong><br>
                    <span style="opacity: 0.9;">
                        <?php echo t('mypage_novms_desc'); ?>
                    </span>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($is_player): ?>
<!-- STATISTIK FÖR ÅRET (endast för spelare) -->
<div class="filter-box" style="background: #f8f9fa; padding: 25px; border-radius: 8px; margin-bottom: 30px;">
    <h2 style="margin-top: 0;">📊 <?php echo t('mypage_stats_heading'); ?> <?php echo $current_year; ?></h2>
    
    <?php if ($is_system_user): ?>
        <div class="message info">
            <p><?php echo t('mypage_no_stats_system'); ?></p>
        </div>
    <?php elseif ($stats['matches_played'] > 0): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; text-align: center;">
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="font-size: 0.9em; color: #666; margin-bottom: 5px;"><?php echo t('mypage_ranking'); ?></div>
                <div style="font-size: 2em; font-weight: bold; color: #4CAF50;">#<?php echo $rank; ?></div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="font-size: 0.9em; color: #666; margin-bottom: 5px;"><?php echo t('mygames_heading'); ?></div>
                <div style="font-size: 2em; font-weight: bold; color: #2c5f2d;"><?php echo $stats['matches_played']; ?></div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="font-size: 0.9em; color: #666; margin-bottom: 5px;"><?php echo currentLang() === 'sv' ? 'Totalt TP' : 'Total TP'; ?></div>
                <div style="font-size: 2em; font-weight: bold; color: #2c5f2d;"><?php echo number_format($stats['total_tp'], 0, ',', ' '); ?></div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="font-size: 0.9em; color: #666; margin-bottom: 5px;"><?php echo currentLang() === 'sv' ? 'Snitt TP/match' : 'Avg TP/game'; ?></div>
                <div style="font-size: 2em; font-weight: bold; color: #4CAF50;"><?php echo number_format($stats['avg_tp'], 2, ',', ' '); ?></div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="font-size: 0.9em; color: #666; margin-bottom: 5px;"><?php echo currentLang() === 'sv' ? 'Totalt MP' : 'Total MP'; ?></div>
                <div style="font-size: 2em; font-weight: bold; color: #2c5f2d;"><?php echo number_format($stats['total_mp'], 0, ',', ' '); ?></div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="font-size: 0.9em; color: #666; margin-bottom: 5px;"><?php echo currentLang() === 'sv' ? 'Snitt MP/match' : 'Avg MP/game'; ?></div>
                <div style="font-size: 2em; font-weight: bold; color: #4CAF50;"><?php echo number_format($stats['avg_mp'], 2, ',', ' '); ?></div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="font-size: 0.9em; color: #666; margin-bottom: 5px;"><?php echo t('mypage_col_bighand'); ?></div>
                <div style="font-size: 2em; font-weight: bold; color: #2c5f2d;"><?php echo $stats['biggest_hand'] ?: '—'; ?></div>
            </div>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <p style="font-size: 1.2em;"><?php echo t('mypage_no_games_yet'); ?></p>
            <p><?php echo t('mypage_no_games_soon'); ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- MINA MATCHER -->
<div style="margin-bottom: 30px;">
    <h2>🎮 <?php echo t('mypage_games_heading'); ?> <?php echo $current_year; ?></h2>
    
    <?php if ($is_system_user): ?>
        <div class="message info">
            <p><?php echo t('mypage_no_stats_system'); ?></p>
        </div>
    <?php elseif (count($stat_games) > 0): ?>
        <p style="color: #666; margin-bottom: 15px;">
            Du har spelat <strong><?php echo count($stat_games); ?></strong> <?php echo count($stat_games) == 1 ? 'match' : 'matcher'; ?> hittills i år.
        </p>
        
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th><?php echo t('mypage_col_game'); ?></th>
                        <th><?php echo t('mypage_col_date'); ?></th>
                        <th><?php echo t('mypage_col_players'); ?></th>
                        <th><?php echo t('mypage_col_bighand'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stat_games as $game): ?>
                    <tr>
                        <td><strong><?php echo $game['game_number']; ?></strong></td>
                        <td><?php echo date('Y-m-d', strtotime($game['game_date'])); ?></td>
                        <td>
                            <?php
                            // Visa alla spelare med poäng
                            $stat_players = [
                                ['vms' => $game['player_a_id'], 'name' => $game['pa_first'] . ' ' . $game['pa_last'], 'bp' => $game['player_a_minipoints'], 'mp' => $game['player_a_tablepoints']],
                                ['vms' => $game['player_b_id'], 'name' => $game['pb_first'] . ' ' . $game['pb_last'], 'bp' => $game['player_b_minipoints'], 'mp' => $game['player_b_tablepoints']],
                                ['vms' => $game['player_c_id'], 'name' => $game['pc_first'] . ' ' . $game['pc_last'], 'bp' => $game['player_c_minipoints'], 'mp' => $game['player_c_tablepoints']],
                                ['vms' => $game['player_d_id'], 'name' => $game['pd_first'] . ' ' . $game['pd_last'], 'bp' => $game['player_d_minipoints'], 'mp' => $game['player_d_tablepoints']]
                            ];
                            
                            foreach ($stat_players as $p) {
                                if ($p['vms']) {
                                    $is_me = ($p['vms'] == $vms);
                                    $style = $is_me ? 'font-weight: bold; padding: 2px 5px; border-radius: 3px;' . (isset($_COOKIE['vms_theme']) && $_COOKIE['vms_theme'] === 'dark' ? '' : ' background: #fff3cd;') : '';
                                    echo '<div class="' . $class . '" style="' . $style . '">';
                                    echo htmlspecialchars($p['name']) . ': ';
                                    echo '<strong>' . number_format($p['bp'], 0, ',', ' ') . '</strong> BP';
                                    echo ' (' . number_format($p['mp'], 1, ',', ' ') . ' MP)';
                                    echo '</div>';
                                }
                            }
                            ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($game['biggest_hand_points']): ?>
                                <strong><?php echo $game['biggest_hand_points']; ?></strong> BP
                                <?php if ($game['hands_played']): ?>
                                    <br><small style="color: #666;">(<?php echo $game['hands_played']; ?> spelade)</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin-top: 15px;">
            <p style="margin: 0;">
                <strong><?php echo t('mypage_no_game_yet'); ?></strong>
            </p>
            <p style="margin: 10px 0 0 0; color: #666;"><?php echo t('mypage_no_game_soon'); ?></p>
        </div>
    <?php endif; ?>
</div>

<?php endif; // Slut på is_player check ?>

<?php includeFooter(); ?>
