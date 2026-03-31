<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

// Kräv minst registrator-behörighet
if (!hasRole('player')) {
    showError(t('prev_error_no_access'));
}

$conn = getDbConnection();
$error = '';

// Hämta alla arkiverade år
$stmt = $conn->query("SELECT * FROM stat_archived_years ORDER BY year DESC");
$stat_archived_years = $stmt->fetchAll();

// Välj år att visa
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : null;

// Om inget år valt, välj senaste arkiverade året
if (!$selected_year && !empty($stat_archived_years)) {
    $selected_year = $stat_archived_years[0]['year'];
}

// Kontrollera att året faktiskt är arkiverat
$year_info = null;
if ($selected_year) {
    foreach ($stat_archived_years as $ay) {
        if ($ay['year'] == $selected_year) {
            $year_info = $ay;
            break;
        }
    }
}

// Hämta matcher om ett år är valt
$stat_games = [];
$filter_player = isset($_GET['player']) ? cleanInput($_GET['player']) : '';

if ($year_info) {
    $archive_table = "archived_games_" . $selected_year;
    
    // Kontrollera att tabellen finns
    $stmt = $conn->query("SHOW TABLES LIKE '$archive_table'");
    if (!$stmt->fetch()) {
        $error = t('prev_error_no_table') . ' ' . $selected_year;
    } else {
        // Hämta alla spelare för filter
        $stmt = $conn->query("SELECT id, first_name, last_name FROM stat_players ORDER BY last_name, first_name");
        $all_players = $stmt->fetchAll();
        
        // Bygg query med filter
        $where = [];
        $params = [];
        
        if (!empty($filter_player)) {
            $where[] = "(player_a_id = ? OR player_b_id = ? OR player_c_id = ? OR player_d_id = ?)";
            $params[] = $filter_player;
            $params[] = $filter_player;
            $params[] = $filter_player;
            $params[] = $filter_player;
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Hämta matcher
        $query = "
            SELECT 
                g.*,
                pa.first_name as pa_first, pa.last_name as pa_last,
                pb.first_name as pb_first, pb.last_name as pb_last,
                pc.first_name as pc_first, pc.last_name as pc_last,
                pd.first_name as pd_first, pd.last_name as pd_last,
                bp.first_name as bp_first, bp.last_name as bp_last
            FROM `$archive_table` g
            LEFT JOIN stat_players pa ON g.player_a_id = pa.id
            LEFT JOIN stat_players pb ON g.player_b_id = pb.id
            LEFT JOIN stat_players pc ON g.player_c_id = pc.id
            LEFT JOIN stat_players pd ON g.player_d_id = pd.id
            LEFT JOIN stat_players bp ON g.biggest_hand_player_id = bp.id
            $where_clause
            ORDER BY g.game_number DESC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $stat_games = $stmt->fetchAll();
    }
}

includeHeader();
?>

<h2>📚 <?php echo t('prev_title'); ?></h2>

<?php if ($error): ?>
    <div class="message error">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if (empty($stat_archived_years)): ?>
    <div class="message info">
        <p><?php echo t('prev_no_years'); ?></p>
        <?php if (hasRole('admin')): ?>
            <p><?php echo t('prev_go_admin'); ?></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    
    <!-- VÄLJ ÅR -->
    <div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
        <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
            <div>
                <strong style="font-size: 1.1em;"><?php echo t('prev_select_year'); ?></strong>
            </div>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php foreach ($stat_archived_years as $ay): ?>
                    <a href="previous.php?year=<?php echo $ay['year']; ?>" 
                       class="btn <?php echo ($ay['year'] == $selected_year) ? '' : 'btn-secondary'; ?>"
                       style="font-size: 1.1em; padding: 12px 24px;">
                        <?php echo $ay['year']; ?>
                        <?php if ($ay['year'] == $selected_year): ?>
                            ✓
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <?php if ($year_info): ?>
        <!-- INFORMATION OM ÅRET -->
        <div style="background: #e3f2fd; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #2196F3;">
            <h3 style="margin-top: 0;"><?php echo t('prev_stats_for') . ' ' . $selected_year; ?></h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div>
                    <strong><?php echo t('prev_archive_date'); ?>:</strong><br>
                    <?php echo formatSwedishDate($year_info['archived_date']); ?>
                </div>
                <div>
                    <strong><?php echo t('home_total_games'); ?>:</strong><br>
                    <?php echo $year_info['total_games']; ?>
                </div>
                <div>
                    <strong><?php echo t('home_total_players'); ?>:</strong><br>
                    <?php echo $year_info['total_players']; ?>
                </div>
            </div>
        </div>
        
        <!-- FILTER -->
        <div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <form method="GET" action="previous.php">
                <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                <div style="display: grid; grid-template-columns: 300px auto; gap: 15px; align-items: end;">
                    <div class="form-group" style="margin: 0;">
                        <label for="player"><?php echo t('prev_filter_player'); ?></label>
                        <select id="player" name="player" onchange="this.form.submit()">
                            <option value=""><?php echo t('prev_all_players'); ?></option>
                            <?php foreach ($all_players as $p): ?>
                                <option value="<?php echo htmlspecialchars($p['id']); ?>"
                                        <?php echo ($filter_player === $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['id'] . ' - ' . $p['first_name'] . ' ' . $p['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (!empty($filter_player)): ?>
                    <div>
                        <a href="previous.php?year=<?php echo $selected_year; ?>" class="btn btn-secondary">
                            <?php echo t('prev_clear_filter'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- MATCHLISTA -->
        <?php if (empty($stat_games)): ?>
            <div class="message info">
                <p><?php echo t('prev_no_games') . ' ' . $selected_year . (!empty($filter_player) ? ' ' . t('prev_and_player') : ''); ?>.</p>
            </div>
        <?php else: ?>
            <p style="margin: 20px 0;">
                <strong><?php echo count($stat_games); ?></strong> matcher
                <?php if (!empty($filter_player)): ?>
                    <?php echo t('prev_with'); ?> <?php 
                    $fp = array_filter($all_players, function($p) use ($filter_player) {
                        return $p['id'] === $filter_player;
                    });
                    $fp = reset($fp);
                    echo htmlspecialchars($fp['first_name'] . ' ' . $fp['last_name']);
                    ?>
                <?php endif; ?>
            </p>
            
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 100px;"><?php echo t('prev_th_game'); ?></th>
                            <th style="width: 120px;"><?php echo t('prev_th_date'); ?></th>
                            <th><?php echo t('prev_th_players'); ?></th>
                            <th style="width: 150px;"><?php echo t('prev_th_biggest'); ?></th>
                            <th style="width: 100px;"><?php echo t('prev_th_actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stat_games as $game): ?>
                        <tr>
                            <td>
                                <strong style="font-size: 1.2em;">
                                    <?php echo t('prev_game_label') . ' ' . $game['game_number']; ?>
                                </strong>
                            </td>
                            <td><?php echo formatSwedishDate($game['game_date']); ?></td>
                            <td>
                                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; font-size: 0.9em;">
                                    <?php
                                    $players_in_game = [
                                        ['vms' => $game['player_a_id'], 'name' => $game['pa_first'] . ' ' . $game['pa_last'], 
                                         'mini' => $game['player_a_minipoints'], 'table' => $game['player_a_tablepoints']],
                                        ['vms' => $game['player_b_id'], 'name' => $game['pb_first'] . ' ' . $game['pb_last'], 
                                         'mini' => $game['player_b_minipoints'], 'table' => $game['player_b_tablepoints']],
                                        ['vms' => $game['player_c_id'], 'name' => $game['pc_first'] . ' ' . $game['pc_last'], 
                                         'mini' => $game['player_c_minipoints'], 'table' => $game['player_c_tablepoints']],
                                        ['vms' => $game['player_d_id'], 'name' => $game['pd_first'] . ' ' . $game['pd_last'], 
                                         'mini' => $game['player_d_minipoints'], 'table' => $game['player_d_tablepoints']]
                                    ];
                                    
                                    // Sortera efter bordspoäng
                                    usort($players_in_game, function($a, $b) {
                                        return $b['table'] <=> $a['table'];
                                    });
                                    
                                    $medals = ['🥇', '🥈', '🥉', ''];
                                    
                                    foreach ($players_in_game as $idx => $p):
                                    ?>
                                    <div style="padding: 8px; background: #f9f9f9; border-radius: 5px;">
                                        <div style="font-weight: bold;">
                                            <?php echo $medals[$idx]; ?> <?php echo htmlspecialchars($p['name']); ?>
                                        </div>
                                        <div style="font-size: 0.85em; color: #666;">
                                            <?php echo htmlspecialchars($p['vms']); ?>
                                        </div>
                                        <div style="margin-top: 5px;">
                                            <span style="color: #2196F3; font-weight: bold;">
                                                <?php echo $p['table']; ?> BP
                                            </span>
                                            <span style="color: #666; margin-left: 5px;">
                                                (<?php echo $p['mini'] > 0 ? '+' : ''; ?><?php echo $p['mini']; ?> MP)
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($game['biggest_hand_points'] > 0): ?>
                                    <strong><?php echo $game['biggest_hand_points']; ?></strong> <?php echo t('stats_pub_points'); ?>
                                    <br>
                                    <span style="font-size: 0.9em;">
                                        <?php echo htmlspecialchars($game['bp_first'] . ' ' . $game['bp_last']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="previous-view-game.php?year=<?php echo $selected_year; ?>&id=<?php echo $game['id']; ?>" 
                                   style="color: #2196F3; text-decoration: none;">
                                    👁️ <?php echo t('prev_view'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
                <h3><?php echo t('prev_legend_title'); ?></h3>
                <p><?php echo t('prev_legend_bp'); ?></p>
                <p><?php echo t('prev_legend_mp'); ?></p>
                <p><?php echo t('prev_legend_medals'); ?></p>
            </div>
            
        <?php endif; ?>
    <?php endif; ?>
    
<?php endif; ?>

<div style="margin-top: 30px; text-align: center;">
    <a href="games.php" class="btn btn-secondary">
        📋 <?php echo t('prev_btn_current') . ' (' . getCurrentYear() . ')'; ?>
    </a>
    <?php if (hasRole('admin')): ?>
        <a href="administration.php" class="btn" style="margin-left: 10px; background: #f44336;">
            🔧 <?php echo t('nav_administration'); ?>
        </a>
    <?php endif; ?>
</div>

<?php includeFooter(); ?>