<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

// Kräv inloggning
if (!isLoggedIn()) {
    redirect('login.php');
}

$conn = getDbConnection();
$player_id = $_SESSION['user_id'];

// Hämta spelarinfo
$stmt = $conn->prepare("SELECT first_name, last_name, visible_in_stats FROM stat_players WHERE id = ?");
$stmt->execute([$player_id]);
$player = $stmt->fetch();

// Kolla om systemanvändare
$is_system_user = in_array($id, [1, 2]);

// Hämta aktuellt år
$current_year = getCurrentYear();

// Hämta filtrering
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;

// Parse period filter
$filter_period = isset($_GET['period']) ? cleanInput($_GET['period']) : 'all';
$period_where_extra = '';
$period_params = [];

if ($filter_period !== 'all') {
    $parts = explode('_', $filter_period);
    $period_type = $parts[0];
    $period_num = (int)$parts[1];
    
    switch ($period_type) {
        case 'month':
            $period_where_extra = ' AND MONTH(g.game_date) = ?';
            $period_params[] = $period_num;
            break;
        case 'quarter':
            $start_month = ($period_num - 1) * 3 + 1;
            $end_month = $period_num * 3;
            $period_where_extra = ' AND MONTH(g.game_date) BETWEEN ? AND ?';
            $period_params[] = $start_month;
            $period_params[] = $end_month;
            break;
        case 'half':
            $start_month = ($period_num === 1) ? 1 : 7;
            $end_month = ($period_num === 1) ? 6 : 12;
            $period_where_extra = ' AND MONTH(g.game_date) BETWEEN ? AND ?';
            $period_params[] = $start_month;
            $period_params[] = $end_month;
            break;
    }
}

/**
 * Få period-label för statistikboxar
 */
function getPeriodLabel($filter_period, $filter_year) {
    if ($filter_period === 'all') {
        return $filter_year;
    }
    
    $parts = explode('_', $filter_period);
    $period_type = $parts[0];
    $period_num = (int)$parts[1];
    
    $months_sv = [
        1 => t('month_jan'), 2 => t('month_feb'), 3 => t('month_mar'), 4 => t('month_apr'),
        5 => t('month_may'), 6 => t('month_jun'), 7 => t('month_jul'), 8 => t('month_aug'),
        9 => t('month_sep'), 10 => t('month_oct'), 11 => t('month_nov'), 12 => t('month_dec')
    ];
    
    switch ($period_type) {
        case 'month':
            return $months_sv[$period_num] . ' ' . $filter_year;
        case 'quarter':
            return 'Q' . $period_num . ' ' . $filter_year;
        case 'half':
            return 'H' . $period_num . ' ' . $filter_year;
        default:
            return $filter_year;
    }
}

includeHeader();
?>
<style>
body[data-theme="dark"] { --sticky-bg: #252B38; }
</style>

<h1>📊 <?php echo t('mystat_heading'); ?></h1>

<?php if ($is_system_user): ?>
    <div class="message info">
        <p>Systemanvändare har inga registrerade matcher.</p>
    </div>
<?php else: 

// Räkna antal matcher spelaren deltagit i
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM stat_games g
    WHERE g.game_year = ? AND g.approved = 1 AND g.deleted_at IS NULL" . $period_where_extra . "
        AND (g.player_a_id = ? OR g.player_b_id = ? OR g.player_c_id = ? OR g.player_d_id = ?)
");
$params = array_merge([$filter_year], $period_params, [$id, $id, $id, $player_id]);
$stmt->execute($params);
$my_total_games = $stmt->fetch()['count'];

if ($my_total_games === 0): ?>
    <div class="message info">
        <p>Du har inga matcher registrerade för <?php echo getPeriodLabel($filter_period, $filter_year); ?>.</p>
        <p><a href="?year=<?php echo $current_year; ?>&period=all">Visa hela året <?php echo $current_year; ?></a></p>
    </div>
<?php else:

// Hämta min statistik
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN g.player_a_id = ? THEN g.player_a_tablepoints
                 WHEN g.player_b_id = ? THEN g.player_b_tablepoints
                 WHEN g.player_c_id = ? THEN g.player_c_tablepoints
                 WHEN g.player_d_id = ? THEN g.player_d_tablepoints END) as total_tablepoints,
        AVG(CASE WHEN g.player_a_id = ? THEN g.player_a_tablepoints
                 WHEN g.player_b_id = ? THEN g.player_b_tablepoints
                 WHEN g.player_c_id = ? THEN g.player_c_tablepoints
                 WHEN g.player_d_id = ? THEN g.player_d_tablepoints END) as avg_tablepoints,
        SUM(CASE WHEN g.player_a_id = ? THEN g.player_a_hu
                 WHEN g.player_b_id = ? THEN g.player_b_hu
                 WHEN g.player_c_id = ? THEN g.player_c_hu
                 WHEN g.player_d_id = ? THEN g.player_d_hu END) as total_hu,
        SUM(CASE WHEN g.player_a_id = ? THEN g.player_a_selfdrawn
                 WHEN g.player_b_id = ? THEN g.player_b_selfdrawn
                 WHEN g.player_c_id = ? THEN g.player_c_selfdrawn
                 WHEN g.player_d_id = ? THEN g.player_d_selfdrawn END) as total_selfdrawn,
        SUM(CASE WHEN g.player_a_id = ? THEN g.player_a_thrown_hu
                 WHEN g.player_b_id = ? THEN g.player_b_thrown_hu
                 WHEN g.player_c_id = ? THEN g.player_c_thrown_hu
                 WHEN g.player_d_id = ? THEN g.player_d_thrown_hu END) as total_thrown,
        MAX(CASE WHEN g.player_a_id = ? THEN g.player_a_hu
                 WHEN g.player_b_id = ? THEN g.player_b_hu
                 WHEN g.player_c_id = ? THEN g.player_c_hu
                 WHEN g.player_d_id = ? THEN g.player_d_hu END) as max_hu_in_game,
        MAX(CASE WHEN g.player_a_id = ? THEN g.player_a_selfdrawn
                 WHEN g.player_b_id = ? THEN g.player_b_selfdrawn
                 WHEN g.player_c_id = ? THEN g.player_c_selfdrawn
                 WHEN g.player_d_id = ? THEN g.player_d_selfdrawn END) as max_selfdrawn_in_game
    FROM stat_games g
    WHERE g.game_year = ? AND g.approved = 1 AND g.deleted_at IS NULL" . $period_where_extra . "
        AND (g.player_a_id = ? OR g.player_b_id = ? OR g.player_c_id = ? OR g.player_d_id = ?)
");
$params = array_merge(
    [$id, $id, $id, $player_id], // total_tablepoints
    [$id, $id, $id, $player_id], // avg_tablepoints
    [$id, $id, $id, $player_id], // total_hu
    [$id, $id, $id, $player_id], // total_selfdrawn
    [$id, $id, $id, $player_id], // total_thrown
    [$id, $id, $id, $player_id], // max_hu
    [$id, $id, $id, $player_id], // max_selfdrawn
    [$filter_year], 
    $period_params,
    [$id, $id, $id, $player_id]
);
$stmt->execute($params);
$my_stats = $stmt->fetch();

// Hämta min ranking position (baserat på avg tablepoints, min 1 match)
$stmt = $conn->prepare("
    SELECT 
        p.id,
        p.first_name,
        p.last_name,
        COUNT(DISTINCT g.id) as games_played,
        AVG(
            CASE 
                WHEN g.player_a_id = p.id THEN g.player_a_tablepoints
                WHEN g.player_b_id = p.id THEN g.player_b_tablepoints
                WHEN g.player_c_id = p.id THEN g.player_c_tablepoints
                WHEN g.player_d_id = p.id THEN g.player_d_tablepoints
            END
        ) as avg_tablepoints
    FROM stat_players p
    INNER JOIN stat_games g ON (
        g.player_a_id = p.id OR 
        g.player_b_id = p.id OR 
        g.player_c_id = p.id OR 
        g.player_d_id = p.id
    )
    WHERE g.game_year = ?" . $period_where_extra . "
        AND g.approved = 1 AND g.deleted_at IS NULL
        AND p.visible_in_stats = 1
        AND p.id NOT IN (1, 2)
    GROUP BY p.id, p.first_name, p.last_name
    HAVING games_played >= 1
    ORDER BY avg_tablepoints DESC
");
$params = array_merge([$filter_year], $period_params);
$stmt->execute($params);
$all_rankings = $stmt->fetchAll();

$my_position = 0;
$total_players = count($all_rankings);
foreach ($all_rankings as $index => $rank_player) {
    if ($rank_player['id'] === $player_id) {
        $my_position = $index + 1;
        break;
    }
}

?>

<!-- FILTER -->
<div class="filter-box" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px;">
    <h3 style="margin-top: 0;">🔍 <?php echo t('mystat_filter_heading'); ?></h3>
    <form method="GET" style="display: flex; gap: 20px; align-items: end; flex-wrap: wrap;">
        <div>
            <label class="period-label" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php echo t('mystat_filter_period'); ?></label>
            <select name="period" style="padding: 8px; font-size: 1em; min-width: 180px; color: #003D6B; background: white;">
                <option value="all" ><?php echo t('mystat_full_year'); ?></option><?php //  <?php echo $filter_period === 'all' ? 'selected' : ''; ?>>Hela året</option>
                <option value="month_1" <?php echo $filter_period === 'month_1' ? 'selected' : ''; ?>>Januari</option>
                <option value="month_2" <?php echo $filter_period === 'month_2' ? 'selected' : ''; ?>>Februari</option>
                <option value="month_3" <?php echo $filter_period === 'month_3' ? 'selected' : ''; ?>>Mars</option>
                <option value="quarter_1" <?php echo $filter_period === 'quarter_1' ? 'selected' : ''; ?>>Kvartal 1</option>
                <option value="month_4" <?php echo $filter_period === 'month_4' ? 'selected' : ''; ?>>April</option>
                <option value="month_5" <?php echo $filter_period === 'month_5' ? 'selected' : ''; ?>>Maj</option>
                <option value="month_6" <?php echo $filter_period === 'month_6' ? 'selected' : ''; ?>>Juni</option>
                <option value="quarter_2" <?php echo $filter_period === 'quarter_2' ? 'selected' : ''; ?>>Kvartal 2</option>
                <option value="half_1" <?php echo $filter_period === 'half_1' ? 'selected' : ''; ?>>Halvår 1 (jan-jun)</option>
                <option value="month_7" <?php echo $filter_period === 'month_7' ? 'selected' : ''; ?>>Juli</option>
                <option value="month_8" <?php echo $filter_period === 'month_8' ? 'selected' : ''; ?>>Augusti</option>
                <option value="month_9" <?php echo $filter_period === 'month_9' ? 'selected' : ''; ?>>September</option>
                <option value="quarter_3" <?php echo $filter_period === 'quarter_3' ? 'selected' : ''; ?>>Kvartal 3</option>
                <option value="month_10" <?php echo $filter_period === 'month_10' ? 'selected' : ''; ?>>Oktober</option>
                <option value="month_11" <?php echo $filter_period === 'month_11' ? 'selected' : ''; ?>>November</option>
                <option value="month_12" <?php echo $filter_period === 'month_12' ? 'selected' : ''; ?>>December</option>
                <option value="quarter_4" <?php echo $filter_period === 'quarter_4' ? 'selected' : ''; ?>>Kvartal 4</option>
                <option value="half_2" <?php echo $filter_period === 'half_2' ? 'selected' : ''; ?>>Halvår 2 (jul-dec)</option>
            </select>
        </div>
        
        <div>
            <label class="year-label" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php echo t('mystat_filter_year'); ?></label>
            <select name="year" style="padding: 8px; font-size: 1em; min-width: 100px; color: #003D6B; background: white;">
                <?php
                $current_year_val = (int)date('Y');
                for ($y = $current_year_val; $y >= 2024; $y--) {
                    $selected = ($y == $filter_year) ? 'selected' : '';
                    echo "<option value='$y' $selected>$y</option>";
                }
                ?>
            </select>
        </div>
        
        <button type="submit" class="btn" style="padding: 8px 20px;"><?php echo t('mystat_filter_btn'); ?></button>
    </form>
</div>

<!-- MIN RANKING POSITION -->
<?php if ($my_position > 0): ?>
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center; margin-bottom: 30px;">
    <h2 style="color: white; margin-top: 0;">🏅 <?php echo t('mystat_ranking_heading'); ?></h2>
    <div style="font-size: 3em; font-weight: bold; margin: 20px 0;">#<?php echo $my_position; ?></div>
    <div style="font-size: 1.2em; opacity: 0.9;"><?php echo t('mystat_of_players'); ?> <?php echo $total_players; ?> <?php echo t('mystat_players'); ?></div>
    <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    <div style="font-size: 1.1em; margin-top: 20px;"><?php echo t('mystat_avg_per_match'); ?>: <?php echo number_format($my_stats['avg_tablepoints'], 2); ?> BP/match</div>
</div>
<?php endif; ?>

<!-- STATISTIKBOXAR -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    
    <!-- ANTAL MATCHER -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">🎲</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 10px;"><?php echo t('mystat_my_games'); ?></div>
        <div style="font-size: 2.5em; font-weight: bold;"><?php echo $my_total_games; ?></div>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    
    <!-- GENOMSNITTLIGA BORDPOÄNG -->
    <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 25px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">📊</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 10px;"><?php echo t('mystat_avg_bp'); ?></div>
        <div style="font-size: 2.5em; font-weight: bold;"><?php echo number_format($my_stats['avg_tablepoints'], 2); ?></div>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    
    <!-- TOTALA BORDPOÄNG -->
    <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 25px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">🎯</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 10px;"><?php echo t('mystat_total_bp'); ?></div>
        <div style="font-size: 2.5em; font-weight: bold;"><?php echo number_format($my_stats['total_tablepoints'], 1); ?></div>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    
    <!-- TOTALA HU -->
    <div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); padding: 25px; border-radius: 10px; color: #333; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">🎯</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 10px;"><?php echo t('mystat_total_hu'); ?></div>
        <div style="font-size: 2.5em; font-weight: bold;"><?php echo $my_stats['total_hu'] ?? 0; ?></div>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    
    <!-- FLEST HU I EN MATCH -->
    <div style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); padding: 25px; border-radius: 10px; color: #333; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">🔥</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 10px;"><?php echo t('mystat_max_hu'); ?></div>
        <div style="font-size: 2.5em; font-weight: bold;"><?php echo $my_stats['max_hu_in_game'] ?? 0; ?></div>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    
    <!-- TOTALA SELFDRAWN -->
    <div style="background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); padding: 25px; border-radius: 10px; color: #333; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">🎲</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 10px;"><?php echo t('mystat_total_selfdrawn'); ?></div>
        <div style="font-size: 2.5em; font-weight: bold;"><?php echo $my_stats['total_selfdrawn'] ?? 0; ?></div>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    
    <!-- FLEST SELFDRAWN I EN MATCH -->
    <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); padding: 25px; border-radius: 10px; color: #333; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">⭐</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 10px;"><?php echo t('mystat_max_selfdrawn'); ?></div>
        <div style="font-size: 2.5em; font-weight: bold;"><?php echo $my_stats['max_selfdrawn_in_game'] ?? 0; ?></div>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    
    <!-- TOTALA KASTADE HU -->
    <div style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); padding: 25px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">💥</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 10px;"><?php echo t('mystat_total_thrown'); ?></div>
        <div style="font-size: 2.5em; font-weight: bold;"><?php echo $my_stats['total_thrown'] ?? 0; ?></div>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    
</div>

<?php endif; ?>

<?php endif; ?>

<?php includeFooter(); ?>
