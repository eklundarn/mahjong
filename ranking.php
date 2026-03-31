<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

requireLogin();

$conn = getDbConnection();

// Hämta aktuellt år
$current_year = getCurrentYear();

// Hämta filtrering
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;
$filter_club = isset($_GET['club']) ? cleanInput($_GET['club']) : '';
$min_games = isset($_GET['min_games']) ? (int)$_GET['min_games'] : 1;

// Parse period filter
$filter_period = isset($_GET['period']) ? cleanInput($_GET['period']) : 'all';
$period_where_extra = '';
$period_params = [];

if ($filter_period !== 'all') {
    $parts = explode('_', $filter_period);
    $period_type = $parts[0]; // 'month', 'quarter', 'half'
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

/* ============================================
 * HJÄLPFUNKTIONER - Inkludera från separat fil
 * ============================================ */

// TODO: Flytta dessa till en separat include-fil senare
// För nu, inkludera dem direkt här

/**
 * Bestäm om en period är avslutad
 */
function isPeriodCompleted($year, $month = null, $quarter = null, $half = null) {
    $current_date = new DateTime();
    $current_year = (int)$current_date->format('Y');
    $current_month = (int)$current_date->format('n');
    
    if ($month === null && $quarter === null && $half === null) {
        return $year < $current_year;
    }
    
    if ($half !== null) {
        if ($year < $current_year) return true;
        if ($year > $current_year) return false;
        $half_end_month = ($half === 1) ? 6 : 12;
        return $current_month > $half_end_month;
    }
    
    if ($quarter !== null) {
        if ($year < $current_year) return true;
        if ($year > $current_year) return false;
        $quarter_end_month = $quarter * 3;
        return $current_month > $quarter_end_month;
    }
    
    if ($month !== null) {
        if ($year < $current_year) return true;
        if ($year > $current_year) return false;
        return $current_month > $month;
    }
    
    return false;
}

function shouldShowAward($year, $type, $number = null) {
    $current_date = new DateTime();
    $current_year = (int)$current_date->format('Y');
    $current_month = (int)$current_date->format('n');
    
    if ($year != $current_year) {
        return false;
    }
    
    switch ($type) {
        case 'month':
            return $current_month > $number; // Visa först när månaden är avslutad
        case 'quarter':
            $min_month = ($number - 1) * 3 + 2;
            return $current_month >= $min_month;
        case 'half':
            $min_month = ($number === 1) ? 4 : 10;
            return $current_month >= $min_month;
        case 'year':
            return $current_month >= 7;
        default:
            return false;
    }
}

function getPeriodWhereClause($year, $type, $number = null) {
    $where = "g.game_year = ?";
    $params = [$year];
    
    switch ($type) {
        case 'month':
            $where .= " AND MONTH(g.game_date) = ?";
            $params[] = $number;
            break;
        case 'quarter':
            $start_month = ($number - 1) * 3 + 1;
            $end_month = $number * 3;
            $where .= " AND MONTH(g.game_date) BETWEEN ? AND ?";
            $params[] = $start_month;
            $params[] = $end_month;
            break;
        case 'half':
            $start_month = ($number === 1) ? 1 : 7;
            $end_month = ($number === 1) ? 6 : 12;
            $where .= " AND MONTH(g.game_date) BETWEEN ? AND ?";
            $params[] = $start_month;
            $params[] = $end_month;
            break;
        case 'year':
            break;
    }
    
    return ['where' => $where, 'params' => $params];
}

function getAwardTitle($year, $type, $number = null, $is_completed = false) {
    $months = [
        1 => t('month_jan'), 2 => t('month_feb'), 3 => t('month_mar'), 4 => t('month_apr'),
        5 => t('month_may'), 6 => t('month_jun'), 7 => t('month_jul'), 8 => t('month_aug'),
        9 => t('month_sep'), 10 => t('month_oct'), 11 => t('month_nov'), 12 => t('month_dec')
    ];
    
    $prefix = $is_completed ? t('award_prefix_winner') : t('award_prefix_leader');
    
    switch ($type) {
        case 'month':
            return "🎖️ $prefix {$months[$number]} $year";
        case 'quarter':
            return "🏅 $prefix " . t('award_quarter') . " $number $year";
        case 'half':
            $half_text = ($number === 1) ? t('award_half1_short') : t('award_half2_short');
            return "🎖️ $prefix " . t('award_half') . " $half_text";
        case 'year':
            return "👑 $prefix $year";
        default:
            return t('award_generic');
    }
}

function getMinimumMatches($type) {
    switch ($type) {
        case 'month':
            return (int)getSetting('awards_min_month', 5);
        case 'quarter':
            return (int)getSetting('awards_min_quarter', 10);
        case 'half':
            return (int)getSetting('awards_min_half', 25);
        case 'year':
            return (int)getSetting('awards_min_year', 50);
        default:
            return 1;
    }
}

function getLeaderForPeriod($conn, $year, $type, $number = null) {
    $period = getPeriodWhereClause($year, $type, $number);
    $where = $period['where'];
    $params = $period['params'];
    $min_matches = getMinimumMatches($type);
    
    $sql = "
        SELECT 
            p.id,
            p.first_name,
            p.last_name,
            p.club,
            COALESCE(SUM(CASE 
                WHEN g.player_a_id = p.id THEN g.player_a_tablepoints
                WHEN g.player_b_id = p.id THEN g.player_b_tablepoints
                WHEN g.player_c_id = p.id THEN g.player_c_tablepoints
                WHEN g.player_d_id = p.id THEN g.player_d_tablepoints
            END), 0) AS total_tablepoints,
            COUNT(DISTINCT g.id) AS games_played,
            ROUND(COALESCE(SUM(CASE 
                WHEN g.player_a_id = p.id THEN g.player_a_tablepoints
                WHEN g.player_b_id = p.id THEN g.player_b_tablepoints
                WHEN g.player_c_id = p.id THEN g.player_c_tablepoints
                WHEN g.player_d_id = p.id THEN g.player_d_tablepoints
            END), 0) / NULLIF(COUNT(DISTINCT g.id), 0), 2) AS avg_tablepoints,
            ROUND(COALESCE(SUM(CASE 
                WHEN g.player_a_id = p.id THEN g.player_a_minipoints
                WHEN g.player_b_id = p.id THEN g.player_b_minipoints
                WHEN g.player_c_id = p.id THEN g.player_c_minipoints
                WHEN g.player_d_id = p.id THEN g.player_d_minipoints
            END), 0) / NULLIF(COUNT(DISTINCT g.id), 0), 2) AS avg_minipoints
        FROM stat_players p
        INNER JOIN stat_games g ON (
            (g.player_a_id = p.id OR 
            g.player_b_id = p.id OR 
            g.player_c_id = p.id OR 
            g.player_d_id = p.id)
            AND g.approved = 1 AND g.deleted_at IS NULL
        )
        WHERE $where
        AND p.visible_in_stats = 1
        AND p.id NOT IN (1, 2)
        GROUP BY p.id, p.first_name, p.last_name, p.club
        HAVING games_played >= ?
        ORDER BY avg_tablepoints DESC, avg_minipoints DESC, total_tablepoints DESC, games_played DESC
        LIMIT 1
    ";
    
    $params[] = $min_matches;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $leader = $stmt->fetch();
    
    return $leader ?: null;
}

function renderAwardBox($leader, $title, $min_matches, $gradient = '135deg, #667eea 0%, #764ba2 100%') {
    if (!$leader) {
        return;
    }
    
    $avg = number_format($leader['avg_tablepoints'], 2, '.', '');
    
    echo '<div style="background: linear-gradient(' . $gradient . '); padding: 25px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">';
    echo '    <h4 style="color: white; margin-top: 0;">' . htmlspecialchars($title) . '</h4>';
    echo '    <p style="font-size: 0.9em; opacity: 0.9; margin: 5px 0;">(' . t("award_min_matches") . ' ' . $min_matches . ' ' . t("awards_games") . ')</p>';
    echo '    <p style="font-size: 1.8em; font-weight: bold; margin: 15px 0 5px 0;">';
    echo htmlspecialchars($leader['first_name'] . ' ' . $leader['last_name']);
    echo '    </p>';
    echo '    <p style="font-size: 1.1em; margin: 5px 0;">' . htmlspecialchars($leader['club']) . '</p>';
    echo '    <p style="font-size: 1.3em; margin: 15px 0 5px 0;">';
    echo number_format($leader['total_tablepoints'], 1) . ' BP ' . t("award_on") . ' ' . $leader['games_played'] . ' ' . t("awards_games");
    echo '    </p>';
    echo '    <p style="font-size: 0.9em; opacity: 0.9;">' . t("stats_pub_avg") . ': ' . $avg . ' BP/' . t("stats_pub_game") . '</p>';
    echo '</div>';
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

/* ============================================
 * HÄMTA DATA FÖR STATISTIKSIDAN
 * ============================================ */

// Antal matcher (endast godkända) - med periodfilter
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM stat_games g WHERE g.game_year = ? AND g.approved = 1 AND g.deleted_at IS NULL" . $period_where_extra);
$params = array_merge([$filter_year], $period_params);
$stmt->execute($params);
$total_games = $stmt->fetch()['count'];

// Aktiva spelare (endast godkända matcher) - med periodfilter
$stmt_active = $conn->prepare("
    SELECT COUNT(DISTINCT p.id) as active_count
    FROM stat_players p
    INNER JOIN stat_games g ON (
        g.player_a_id = p.id OR 
        g.player_b_id = p.id OR 
        g.player_c_id = p.id OR 
        g.player_d_id = p.id
    )
    WHERE g.game_year = ? AND g.approved = 1 AND g.deleted_at IS NULL" . $period_where_extra . "
");
$params = array_merge([$filter_year], $period_params);
$stmt_active->execute($params);
$active_players_count = $stmt_active->fetch()['active_count'];

// Dolda spelare (endast godkända matcher, exkludera systemkonton) - med periodfilter
$stmt_hidden = $conn->prepare("
    SELECT COUNT(DISTINCT p.id) as hidden_count
    FROM stat_players p
    INNER JOIN stat_games g ON (
        g.player_a_id = p.id OR 
        g.player_b_id = p.id OR 
        g.player_c_id = p.id OR 
        g.player_d_id = p.id
    )
    WHERE g.game_year = ? 
        AND p.visible_in_stats = 0 
        AND p.id NOT IN (1, 2)
        AND g.approved = 1 AND g.deleted_at IS NULL" . $period_where_extra . "
");
$params = array_merge([$filter_year], $period_params);
$stmt_hidden->execute($params);
$hidden_players_count = $stmt_hidden->fetch()['hidden_count'];

// Största hand (endast godkända matcher) - med periodfilter
$stmt = $conn->prepare("
    SELECT biggest_hand_points, biggest_hand_player_id, game_date
    FROM stat_games g
    WHERE g.game_year = ? AND g.biggest_hand_points > 0 AND g.approved = 1 AND g.deleted_at IS NULL" . $period_where_extra . "
    ORDER BY g.biggest_hand_points DESC, g.game_date DESC
    LIMIT 1
");
$params = array_merge([$filter_year], $period_params);
$stmt->execute($params);
$biggest_hand = $stmt->fetch();

// Flest hu i en match (endast godkända matcher) - med periodfilter
$stmt = $conn->prepare("
    SELECT 
        GREATEST(player_a_hu, player_b_hu, player_c_hu, player_d_hu) as max_hu,
        CASE 
            WHEN player_a_hu = GREATEST(player_a_hu, player_b_hu, player_c_hu, player_d_hu) THEN player_a_id
            WHEN player_b_hu = GREATEST(player_a_hu, player_b_hu, player_c_hu, player_d_hu) THEN player_b_id
            WHEN player_c_hu = GREATEST(player_a_hu, player_b_hu, player_c_hu, player_d_hu) THEN player_c_id
            WHEN player_d_hu = GREATEST(player_a_hu, player_b_hu, player_c_hu, player_d_hu) THEN player_d_id
        END as player_id_ref
    FROM stat_games g
    WHERE g.game_year = ? AND g.approved = 1 AND g.deleted_at IS NULL" . $period_where_extra . "
    ORDER BY max_hu DESC
    LIMIT 1
");
$params = array_merge([$filter_year], $period_params);
$stmt->execute($params);
$most_hu = $stmt->fetch();

// Flest selfdrawn i en match (endast godkända matcher) - med periodfilter
$stmt = $conn->prepare("
    SELECT 
        GREATEST(player_a_selfdrawn, player_b_selfdrawn, player_c_selfdrawn, player_d_selfdrawn) as max_selfdrawn,
        CASE 
            WHEN player_a_selfdrawn = GREATEST(player_a_selfdrawn, player_b_selfdrawn, player_c_selfdrawn, player_d_selfdrawn) THEN player_a_id
            WHEN player_b_selfdrawn = GREATEST(player_a_selfdrawn, player_b_selfdrawn, player_c_selfdrawn, player_d_selfdrawn) THEN player_b_id
            WHEN player_c_selfdrawn = GREATEST(player_a_selfdrawn, player_b_selfdrawn, player_c_selfdrawn, player_d_selfdrawn) THEN player_c_id
            WHEN player_d_selfdrawn = GREATEST(player_a_selfdrawn, player_b_selfdrawn, player_c_selfdrawn, player_d_selfdrawn) THEN player_d_id
        END as player_id_ref
    FROM stat_games g
    WHERE g.game_year = ? AND g.approved = 1 AND g.deleted_at IS NULL" . $period_where_extra . "
    ORDER BY max_selfdrawn DESC
    LIMIT 1
");
$params = array_merge([$filter_year], $period_params);
$stmt->execute($params);
$most_selfdrawn = $stmt->fetch();

// Flest kastade hu i en match (endast godkända matcher) - med periodfilter
$stmt = $conn->prepare("
    SELECT 
        GREATEST(player_a_thrown_hu, player_b_thrown_hu, player_c_thrown_hu, player_d_thrown_hu) as max_thrown,
        CASE 
            WHEN player_a_thrown_hu = GREATEST(player_a_thrown_hu, player_b_thrown_hu, player_c_thrown_hu, player_d_thrown_hu) THEN player_a_id
            WHEN player_b_thrown_hu = GREATEST(player_a_thrown_hu, player_b_thrown_hu, player_c_thrown_hu, player_d_thrown_hu) THEN player_b_id
            WHEN player_c_thrown_hu = GREATEST(player_a_thrown_hu, player_b_thrown_hu, player_c_thrown_hu, player_d_thrown_hu) THEN player_c_id
            WHEN player_d_thrown_hu = GREATEST(player_a_thrown_hu, player_b_thrown_hu, player_c_thrown_hu, player_d_thrown_hu) THEN player_d_id
        END as player_id_ref
    FROM stat_games g
    WHERE g.game_year = ? AND g.approved = 1 AND g.deleted_at IS NULL" . $period_where_extra . "
    ORDER BY max_thrown DESC
    LIMIT 1
");
$params = array_merge([$filter_year], $period_params);
$stmt->execute($params);
$most_thrown = $stmt->fetch();

// Händer-statistik för cirkeldiagram (endast godkända matcher) - med periodfilter
$stmt_hands = $conn->prepare("
    SELECT hands_played, COUNT(*) as antal_matcher
    FROM stat_games g
    WHERE g.game_year = ? AND g.hands_played > 0 AND g.approved = 1 AND g.deleted_at IS NULL" . $period_where_extra . "
    GROUP BY hands_played
    ORDER BY hands_played DESC
");
$params = array_merge([$filter_year], $period_params);
$stmt_hands->execute($params);
$hands_stats = $stmt_hands->fetchAll();

// Hämta alla klubbar för filter
$stmt = $conn->query("SELECT DISTINCT club FROM stat_players WHERE club IS NOT NULL AND club != '' ORDER BY club");
$all_clubs = $stmt->fetchAll(PDO::FETCH_COLUMN);

includeHeader();
?>

<h2>📊 <?php echo t('nav_stats_pub') . ' ' . $filter_year; ?></h2>
<!-- FILTER -->
<div class="filter-box" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
    <form method="GET" style="display: flex; gap: 20px; align-items: end; flex-wrap: wrap;">
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Period:</label>
            <select name="period" style="padding: 8px; font-size: 1em; min-width: 180px;">
                <option value="all" <?php echo $filter_period === 'all' ? 'selected' : ''; ?>><?php echo t('stats_full_year'); ?></option>
                <option value="month_1" <?php echo $filter_period === 'month_1' ? 'selected' : ''; ?>><?php echo t('month_jan'); ?></option>
                <option value="month_2" <?php echo $filter_period === 'month_2' ? 'selected' : ''; ?>><?php echo t('month_feb'); ?></option>
                <option value="month_3" <?php echo $filter_period === 'month_3' ? 'selected' : ''; ?>><?php echo t('month_mar'); ?></option>
                <option value="quarter_1" <?php echo $filter_period === 'quarter_1' ? 'selected' : ''; ?>><?php echo t('stats_quarter') . ' 1'; ?></option>
                <option value="month_4" <?php echo $filter_period === 'month_4' ? 'selected' : ''; ?>><?php echo t('month_apr'); ?></option>
                <option value="month_5" <?php echo $filter_period === 'month_5' ? 'selected' : ''; ?>><?php echo t('month_may'); ?></option>
                <option value="month_6" <?php echo $filter_period === 'month_6' ? 'selected' : ''; ?>><?php echo t('month_jun'); ?></option>
                <option value="quarter_2" <?php echo $filter_period === 'quarter_2' ? 'selected' : ''; ?>><?php echo t('stats_quarter') . ' 2'; ?></option>
                <option value="half_1" <?php echo $filter_period === 'half_1' ? 'selected' : ''; ?>><?php echo t('stats_half1'); ?></option>
                <option value="month_7" <?php echo $filter_period === 'month_7' ? 'selected' : ''; ?>><?php echo t('month_jul'); ?></option>
                <option value="month_8" <?php echo $filter_period === 'month_8' ? 'selected' : ''; ?>><?php echo t('month_aug'); ?></option>
                <option value="month_9" <?php echo $filter_period === 'month_9' ? 'selected' : ''; ?>><?php echo t('month_sep'); ?></option>
                <option value="quarter_3" <?php echo $filter_period === 'quarter_3' ? 'selected' : ''; ?>><?php echo t('stats_quarter') . ' 3'; ?></option>
                <option value="month_10" <?php echo $filter_period === 'month_10' ? 'selected' : ''; ?>><?php echo t('month_oct'); ?></option>
                <option value="month_11" <?php echo $filter_period === 'month_11' ? 'selected' : ''; ?>><?php echo t('month_nov'); ?></option>
                <option value="month_12" <?php echo $filter_period === 'month_12' ? 'selected' : ''; ?>><?php echo t('month_dec'); ?></option>
                <option value="quarter_4" <?php echo $filter_period === 'quarter_4' ? 'selected' : ''; ?>><?php echo t('stats_quarter') . ' 4'; ?></option>
                <option value="half_2" <?php echo $filter_period === 'half_2' ? 'selected' : ''; ?>><?php echo t('stats_half2'); ?></option>
            </select>
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php echo t('stats_year_label'); ?>:</label>
            <select name="year" style="padding: 8px; font-size: 1em; min-width: 100px;">
                <?php
                $current_year_val = (int)date('Y');
                for ($y = $current_year_val; $y >= 2024; $y--) {
                    $selected = ($y == $filter_year) ? 'selected' : '';
                    echo "<option value='$y' $selected>$y</option>";
                }
                ?>
            </select>
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php echo t('stats_min_games'); ?></label>
            <input type="number" name="min_games" min="1" max="100" value="<?php echo $min_games; ?>" 
                   style="padding: 8px; font-size: 1em; width: 80px;">
        </div>
        
        <button type="submit" class="btn" style="padding: 8px 20px;"><?php echo t('btn_filter'); ?></button>
    </form>
</div>

<!-- INFO-BOXAR -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    
    <!-- ANTAL MATCHER -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">🎲</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 10px;"><?php echo t('stats_played_games'); ?></div>
        <div style="font-size: 2.5em; font-weight: bold;"><?php echo $total_games; ?></div>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    
    <!-- AKTIVA SPELARE -->
    <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 25px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">👥</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 5px;"><?php echo t('stats_active_players'); ?>
            <?php if ($hidden_players_count > 0): ?>
                - <?php echo t('stats_of_which') . ' ' . $hidden_players_count . ' ' . ($hidden_players_count == 1 ? t('stats_hidden_one') : t('stats_hidden_many')); ?>
            <?php endif; ?>
        </div>
        <div style="font-size: 2.5em; font-weight: bold;"><?php echo $active_players_count; ?></div>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    
    <!-- STÖRSTA HAND -->
    <?php if ($biggest_hand): 
        $stmt = $conn->prepare("SELECT first_name, last_name FROM stat_players WHERE id = ?");
        $stmt->execute([$biggest_hand['biggest_hand_player_id']]);
        $player = $stmt->fetch();
    ?>
    <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 25px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">🏆</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 5px;"><?php echo t('stats_biggest_hand'); ?></div>
        <div style="font-size: 2.5em; font-weight: bold; margin-bottom: 5px;"><?php echo $biggest_hand['biggest_hand_points'] . ' ' . t('stats_pub_points'); ?></div>
        <?php if ($player): ?>
        <div style="font-size: 0.9em; opacity: 0.9;">
            <?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?>
        </div>
        <?php endif; ?>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    <?php else: ?>
    <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 25px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">🏆</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 5px;"><?php echo t('stats_biggest_hand'); ?></div>
        <div style="font-size: 1.5em; opacity: 0.9;"><?php echo t('stats_none_registered'); ?></div>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    <?php endif; ?>
    
    <!-- FLEST HU -->
    <?php if ($most_hu && $most_hu['max_hu'] > 0): 
        $stmt = $conn->prepare("SELECT first_name, last_name FROM stat_players WHERE id = ?");
        $stmt->execute([$most_hu['player_id_ref']]);
        $player = $stmt->fetch();
    ?>
    <div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); padding: 25px; border-radius: 10px; color: #333; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">🎯</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 5px;"><?php echo t('stats_most_hu'); ?></div>
        <div style="font-size: 2.5em; font-weight: bold; margin-bottom: 5px;"><?php echo $most_hu['max_hu'] . ' ' . t('stats_hu'); ?></div>
        <?php if ($player): ?>
        <div style="font-size: 0.9em; opacity: 0.9;">
            <?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?>
        </div>
        <?php endif; ?>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    <?php else: ?>
    <div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); padding: 25px; border-radius: 10px; color: #333; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">🎯</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 5px;"><?php echo t('stats_most_hu'); ?></div>
        <div style="font-size: 1.5em; opacity: 0.9;"><?php echo t('stats_none_registered'); ?></div>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    <?php endif; ?>
    
    
    <!-- FLEST SELFDRAWN -->
    <?php if ($most_selfdrawn && $most_selfdrawn['max_selfdrawn'] > 0): 
        $stmt = $conn->prepare("SELECT first_name, last_name FROM stat_players WHERE id = ?");
        $stmt->execute([$most_selfdrawn['player_id_ref']]);
        $player = $stmt->fetch();
    ?>
    <div style="background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); padding: 25px; border-radius: 10px; color: #333; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">🎲</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 5px;"><?php echo t('stats_most_selfdrawn'); ?></div>
        <div style="font-size: 2.5em; font-weight: bold; margin-bottom: 5px;"><?php echo $most_selfdrawn['max_selfdrawn']; ?></div>
        <?php if ($player): ?>
        <div style="font-size: 0.9em; opacity: 0.9;">
            <?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?>
        </div>
        <?php endif; ?>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    <?php else: ?>
    <div style="background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); padding: 25px; border-radius: 10px; color: #333; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">🎲</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 5px;"><?php echo t('stats_most_selfdrawn'); ?></div>
        <div style="font-size: 1.5em; opacity: 0.9;"><?php echo t('stats_none_registered'); ?></div>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    <?php endif; ?>
    
    <!-- FLEST KASTADE HU -->
    <?php if ($most_thrown && $most_thrown['max_thrown'] > 0): 
        $stmt = $conn->prepare("SELECT first_name, last_name FROM stat_players WHERE id = ?");
        $stmt->execute([$most_thrown['player_id_ref']]);
        $player = $stmt->fetch();
    ?>
    <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); padding: 25px; border-radius: 10px; color: #333; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">💥</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 5px;"><?php echo t('stats_most_thrown'); ?></div>
        <div style="font-size: 2.5em; font-weight: bold; margin-bottom: 5px;"><?php echo $most_thrown['max_thrown']; ?></div>
        <?php if ($player): ?>
        <div style="font-size: 0.9em; opacity: 0.9;">
            <?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?>
        </div>
        <?php endif; ?>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    <?php else: ?>
    <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); padding: 25px; border-radius: 10px; color: #333; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
        <div style="font-size: 2.5em; margin-bottom: 10px;">💥</div>
        <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 5px;"><?php echo t('stats_most_thrown'); ?></div>
        <div style="font-size: 1.5em; opacity: 0.9;"><?php echo t('stats_none_registered'); ?></div>
        <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
    </div>
    <?php endif; ?>
    

</div>

<!-- RANKINGTABELL -->
<div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 30px;">
    <h3 style="margin-top: 0;">🏅 <?php echo t('stats_ranking') . ' ' . $filter_year; ?></h3>
    
    <?php
    // Hämta spelarstatistik
    $where_clause = "g.game_year = ? AND g.approved = 1 AND g.deleted_at IS NULL" . $period_where_extra;
    $params = array_merge([$filter_year], $period_params);
    
    if (!empty($filter_club)) {
        $where_clause .= " AND p.club = ?";
        $params[] = $filter_club;
    }
    
    $sql = "
        SELECT 
            p.id,
            p.first_name,
            p.last_name,
            p.club,
            p.city,
            COALESCE(SUM(CASE 
                WHEN g.player_a_id = p.id THEN g.player_a_tablepoints
                WHEN g.player_b_id = p.id THEN g.player_b_tablepoints
                WHEN g.player_c_id = p.id THEN g.player_c_tablepoints
                WHEN g.player_d_id = p.id THEN g.player_d_tablepoints
            END), 0) AS total_tablepoints,
            COALESCE(SUM(CASE 
                WHEN g.player_a_id = p.id THEN g.player_a_minipoints
                WHEN g.player_b_id = p.id THEN g.player_b_minipoints
                WHEN g.player_c_id = p.id THEN g.player_c_minipoints
                WHEN g.player_d_id = p.id THEN g.player_d_minipoints
            END), 0) AS total_minipoints,
            COUNT(DISTINCT g.id) AS games_played,
            ROUND(COALESCE(SUM(CASE 
                WHEN g.player_a_id = p.id THEN g.player_a_tablepoints
                WHEN g.player_b_id = p.id THEN g.player_b_tablepoints
                WHEN g.player_c_id = p.id THEN g.player_c_tablepoints
                WHEN g.player_d_id = p.id THEN g.player_d_tablepoints
            END), 0) / NULLIF(COUNT(DISTINCT g.id), 0), 2) AS avg_tablepoints,
            ROUND(COALESCE(SUM(CASE 
                WHEN g.player_a_id = p.id THEN g.player_a_minipoints
                WHEN g.player_b_id = p.id THEN g.player_b_minipoints
                WHEN g.player_c_id = p.id THEN g.player_c_minipoints
                WHEN g.player_d_id = p.id THEN g.player_d_minipoints
            END), 0) / NULLIF(COUNT(DISTINCT g.id), 0), 2) AS avg_minipoints
        FROM stat_players p
        INNER JOIN stat_games g ON (
            g.player_a_id = p.id OR 
            g.player_b_id = p.id OR 
            g.player_c_id = p.id OR 
            g.player_d_id = p.id
        )
        WHERE $where_clause AND p.visible_in_stats = 1
        GROUP BY p.id, p.first_name, p.last_name, p.club, p.city
        HAVING games_played >= ?
        ORDER BY avg_tablepoints DESC, avg_minipoints DESC, total_tablepoints DESC, games_played DESC
    ";
    
    $params[] = $min_games;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $players_stats = $stmt->fetchAll();
    ?>
    
    <style>
    /* ---- DESKTOP TABLE ---- */
    .ranking-table { width:100%; border-collapse:collapse; font-size:0.95em; }
    .ranking-table thead tr { background:#f8f9fa; border-bottom:2px solid #dee2e6; }
    .ranking-table th, .ranking-table td { padding:10px 12px; white-space:nowrap; }
    .ranking-table td { border-bottom:1px solid #dee2e6; }
    /* ---- MOBILE CARDS ---- */
    /* Portrait: #, Namn, Mat, Tot BP, Sn BP  (5 col) */
    /* Landscape: +Tot MP, Sn MP              (7 col) */

    .ranking-cards-wrap {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    /* Base row — portrait: 5 columns with fixed widths */
    .ranking-card {
        display: grid;
        grid-template-columns: 28px minmax(0,1fr) 30px 52px 48px;
        align-items: center;
        gap: 0 6px;
        padding: 8px 10px;
        border-bottom: 1px solid #eee;
        font-size: 0.86em;
    }
    .ranking-card:nth-child(even) { background: #fafafa; }

    /* Landscape: show 2 extra columns */
    @media (orientation: landscape) and (max-width:900px) {
        .ranking-card {
            grid-template-columns: 28px minmax(0,1fr) 30px 52px 48px 52px 50px;
        }
        .r-mp-tot, .r-mp-avg { display: block !important; }
    }

    .ranking-card-header {
        background: #005B99 !important;
        color: white;
        font-weight: 700;
        font-size: 0.80em;
        border-radius: 8px 8px 0 0;
        padding: 7px 10px;
    }
    .ranking-card .r-rank  { font-weight:700; color:#005B99; }
    .ranking-card-header .r-rank { color:white; }
    .ranking-card .r-name  { font-weight:600; line-height:1.2; word-break:break-word; }
    .ranking-card-header .r-name { color:white; }
    .ranking-card .r-val   { text-align:right; font-family:monospace; font-size:0.95em; }
    .ranking-card-header .r-val { text-align:right; font-family:inherit; color:white; }
    /* Hide MP cols by default (portrait), shown in landscape via media query above */
    .r-mp-tot, .r-mp-avg { display: none; }

    @media (min-width:701px) {
        .ranking-cards-wrap { display:none; }
        .ranking-table-wrap { display:block; overflow-x:auto; }
    }
    @media (max-width:700px) {
        .ranking-table-wrap { display:none; }
    }
    </style>

    <!-- MOBILE CARDS -->
    <div class="ranking-cards-wrap">
      <div class="ranking-card ranking-card-header">
        <div class="r-rank">#</div>
        <div class="r-name"><?php echo $sv ? 'Spelare' : 'Player'; ?></div>
        <div class="r-val"><?php echo $sv ? 'Mat' : 'Gms'; ?></div>
        <div class="r-val">Tot BP</div>
        <div class="r-val"><?php echo $sv ? 'Sn BP' : 'Avg BP'; ?></div>
        <div class="r-val r-mp-tot">Tot MP</div>
        <div class="r-val r-mp-avg"><?php echo $sv ? 'Sn MP' : 'Avg MP'; ?></div>
      </div>
      <?php $rank = 1; foreach ($players_stats as $player): ?>
      <div class="ranking-card">
        <div class="r-rank"><?php echo $rank++; ?></div>
        <div class="r-name"><?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?></div>
        <div class="r-val"><?php echo $player['games_played']; ?></div>
        <div class="r-val" style="font-weight:700;color:#005B99;"><?php echo number_format($player['total_tablepoints'],1); ?></div>
        <div class="r-val"><?php echo number_format($player['avg_tablepoints'],2); ?></div>
        <div class="r-val r-mp-tot"><?php echo number_format($player['total_minipoints'],0); ?></div>
        <div class="r-val r-mp-avg"><?php echo number_format($player['avg_minipoints'],2); ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- DESKTOP TABLE -->
    <div class="ranking-table-wrap">
    <table class="sortable ranking-table">
        <thead>
            <tr>
                <th style="text-align:left;"><?php echo t('stats_th_rank'); ?></th>
                <th style="text-align:left;"><?php echo t('stats_th_player'); ?></th>
                <th style="text-align:center;"><?php echo t('stats_th_games'); ?></th>
                <th style="text-align:right;"><?php echo t('stats_th_total_bp'); ?></th>
                <th style="text-align:right;"><?php echo t('stats_th_avg_bp'); ?></th>
                <th style="text-align:right;"><?php echo t('stats_th_total_mp'); ?></th>
                <th style="text-align:right;"><?php echo t('stats_th_avg_mp'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php $rank = 1; foreach ($players_stats as $player): ?>
            <tr>
                <td style="font-weight:bold;"><?php echo $rank++; ?></td>
                <td><?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?></td>
                <td style="text-align:center;"><?php echo $player['games_played']; ?></td>
                <td style="text-align:right; font-weight:bold;"><?php echo number_format($player['total_tablepoints'],1); ?></td>
                <td style="text-align:right;"><?php echo number_format($player['avg_tablepoints'],2); ?></td>
                <td style="text-align:right;"><?php echo number_format($player['total_minipoints'],0); ?></td>
                <td style="text-align:right;"><?php echo number_format($player['avg_minipoints'],2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    
    <?php if (empty($players_stats)): ?>
    <p style="text-align: center; color: #666; padding: 40px;">
        <?php echo t('stats_no_data'); ?>
    </p>
    <?php endif; ?>
</div>

<!-- DIAGRAM-SEKTION -->
<?php if (!empty($hands_stats)): ?>
<div style="display: grid; grid-template-columns: 1fr; gap: 30px; margin: 40px 0;">
    
    <!-- HÄNDER-STATISTIK -->
    <div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); padding: 25px; border-radius: 10px; color: #333; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <div style="font-size: 1.3em; font-weight: bold; margin-bottom: 5px;">📊 <?php echo t('stats_hands_chart_title'); ?></div>
        <div style="font-size: 0.95em; opacity: 0.8; margin-bottom: 15px;"><?php echo getPeriodLabel($filter_period, $filter_year); ?></div>
        <div style="background: rgba(255,255,255,0.9); padding: 20px; border-radius: 5px;">
            <?php
            // Beräkna dynamisk höjd baserat på antal datapunkter
            $num_datapoints = count($hands_stats);
            $chart_height = min(800, 300 + max(0, ($num_datapoints - 8) * 30));
            ?>
            <canvas id="handsChart" style="width: 100%; height: <?php echo $chart_height; ?>px;"></canvas>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    const handsData = {
        labels: [<?php 
            echo implode(', ', array_map(function($stat) {
                return "'" . $stat['hands_played'] . ' ' . t('stats_hands_label') . "'";
            }, $hands_stats));
        ?>],
        datasets: [{
            label: 'Antal matcher',
            data: [<?php 
                echo implode(', ', array_map(function($stat) {
                    return $stat['antal_matcher'];
                }, $hands_stats));
            ?>],
            backgroundColor: '#667eea',
            borderColor: '#764ba2',
            borderWidth: 2
        }]
    };
    
    const handsConfig = {
        type: 'bar',
        data: handsData,
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.parsed.x + ' <?php echo t("awards_games"); ?>';
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    };
    
    const handsChart = new Chart(
        document.getElementById('handsChart'),
        handsConfig
    );
    </script>
    
</div>
<?php endif; ?>


<?php includeFooter(); ?>