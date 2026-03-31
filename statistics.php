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
$conn = getDbConnection();

/* ============================================
 * HJÄLPFUNKTIONER FÖR UTMÄRKELSER
 * ============================================ */

/**
 * Kontrollera om en period är avslutad (färdig för utmärkelse)
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

/**
 * Hämta vinnare för en period
 */
function getLeaderForPeriod($conn, $year, $type, $number = null) {
    $where = "g.game_year = ?";
    $params = [$year];
    $min_matches = 1;
    
    switch ($type) {
        case 'month':
            $where .= " AND MONTH(g.game_date) = ?";
            $params[] = $number;
            $min_matches = 5;
            break;
        case 'quarter':
            $start_month = ($number - 1) * 3 + 1;
            $end_month = $number * 3;
            $where .= " AND MONTH(g.game_date) BETWEEN ? AND ?";
            $params[] = $start_month;
            $params[] = $end_month;
            $min_matches = 15;
            break;
        case 'half':
            $start_month = ($number === 1) ? 1 : 7;
            $end_month = ($number === 1) ? 6 : 12;
            $where .= " AND MONTH(g.game_date) BETWEEN ? AND ?";
            $params[] = $start_month;
            $params[] = $end_month;
            $min_matches = 25;
            break;
        case 'year':
            $min_matches = 50;
            break;
    }
    
    $sql = "
        SELECT 
            p.id,
            p.first_name,
            p.last_name,
            p.club,
            COUNT(DISTINCT g.id) as games_played,
            SUM(
                CASE 
                    WHEN g.player_a_id = p.id THEN g.player_a_tablepoints
                    WHEN g.player_b_id = p.id THEN g.player_b_tablepoints
                    WHEN g.player_c_id = p.id THEN g.player_c_tablepoints
                    WHEN g.player_d_id = p.id THEN g.player_d_tablepoints
                    ELSE 0
                END
            ) as total_tablepoints,
            AVG(
                CASE 
                    WHEN g.player_a_id = p.id THEN g.player_a_tablepoints
                    WHEN g.player_b_id = p.id THEN g.player_b_tablepoints
                    WHEN g.player_c_id = p.id THEN g.player_c_tablepoints
                    WHEN g.player_d_id = p.id THEN g.player_d_tablepoints
                    ELSE 0
                END
            ) as avg_tablepoints
        FROM stat_players p
        INNER JOIN stat_games g ON (
            g.player_a_id = p.id OR 
            g.player_b_id = p.id OR 
            g.player_c_id = p.id OR 
            g.player_d_id = p.id
        )
        WHERE $where
            AND g.approved = 1
            AND p.visible_in_stats = 1
            AND p.id NOT IN (1, 2)
        GROUP BY p.id, p.first_name, p.last_name, p.club
        HAVING games_played >= ?
        ORDER BY avg_tablepoints DESC, total_tablepoints DESC
        LIMIT 1
    ";
    
    $params[] = $min_matches;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetch();
}

/**
 * Rita en utmärkelsebox
 */
function renderAwardBox($leader, $title, $min_matches, $gradient = '135deg, #667eea 0%, #764ba2 100%') {
    if (!$leader) return;
    
    $avg = number_format($leader['avg_tablepoints'], 2, '.', '');
    
    echo '<div style="background: linear-gradient(' . $gradient . '); padding: 25px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">';
    echo '    <div style="font-size: 2.5em; margin-bottom: 10px;">🏆</div>';
    echo '    <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 10px;">' . htmlspecialchars($title) . '</div>';
    echo '    <div style="font-size: 2.5em; font-weight: bold;">';
    echo htmlspecialchars($leader['first_name'] . ' ' . $leader['last_name']);
    echo '    </div>';
    echo '    <div style="font-size: 0.9em; opacity: 0.9; margin-top: 5px;">' . htmlspecialchars($leader['club']) . '</div>';
    echo '    <div style="font-size: 0.9em; opacity: 0.8; margin-top: 10px;">' . number_format($leader['total_tablepoints'], 1) . ' BP · ' . $leader['games_played'] . ' ' . t('stats_pub_games') . '</div>';
    echo '    <div style="font-size: 0.9em; opacity: 0.8;">' . t('stats_pub_avg') . ': ' . $avg . ' BP/' . t('stats_pub_game') . '</div>';
    echo '</div>';
}

// Hämta aktuellt år
$current_year = getCurrentYear();

// Hämta statistik för året
// Antal matcher (endast godkända)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM stat_games WHERE game_year = ? AND approved = 1");
$stmt->execute([$current_year]);
$total_games = $stmt->fetch()['count'];

// Aktiva spelare (endast godkända matcher)
$stmt_active = $conn->prepare("
    SELECT COUNT(DISTINCT p.id) as active_count
    FROM stat_players p
    INNER JOIN stat_games g ON (
        g.player_a_id = p.id OR 
        g.player_b_id = p.id OR 
        g.player_c_id = p.id OR 
        g.player_d_id = p.id
    )
    WHERE g.game_year = ? AND g.approved = 1
");
$stmt_active->execute([$current_year]);
$active_players_count = $stmt_active->fetch()['active_count'];

// Dolda spelare (endast godkända matcher, exkludera systemkonton)
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
        AND g.approved = 1
");
$stmt_hidden->execute([$current_year]);
$hidden_players_count = $stmt_hidden->fetch()['hidden_count'];

// Största hand (endast godkända matcher)
$stmt = $conn->prepare("
    SELECT biggest_hand_points, biggest_hand_player_id, game_date
    FROM stat_games 
    WHERE game_year = ? AND biggest_hand_points > 0 AND approved = 1
    ORDER BY biggest_hand_points DESC, game_date DESC
    LIMIT 1
");
$stmt->execute([$current_year]);
$biggest_hand = $stmt->fetch();

// Hämta spelarnamn för största hand
$biggest_hand_names = [];
if ($biggest_hand && $biggest_hand['biggest_hand_player_id']) {
    $player_id_ref_list = explode(',', $biggest_hand['biggest_hand_player_id']);
    $placeholders = str_repeat('?,', count($player_id_ref_list) - 1) . '?';
    $stmt = $conn->prepare("SELECT first_name, last_name FROM stat_players WHERE id IN ($placeholders)");
    $stmt->execute($player_id_ref_list);
    $stat_players = $stmt->fetchAll();
    foreach ($stat_players as $p) {
        $biggest_hand_names[] = $p['first_name'] . ' ' . $p['last_name'];
    }
}

// Händer-statistik för diagram (endast godkända matcher)
$stmt_hands = $conn->prepare("
    SELECT hands_played, COUNT(*) as antal_matcher
    FROM stat_games
    WHERE game_year = ? AND hands_played > 0 AND approved = 1
    GROUP BY hands_played
    ORDER BY hands_played DESC
");
$stmt_hands->execute([$current_year]);
$hands_stats = $stmt_hands->fetchAll();

// Totalt antal spelare (exkludera systemkonton)
$stmt = $conn->query("SELECT COUNT(*) as count FROM stat_players WHERE id NOT IN (1, 2)");
$total_players = $stmt->fetch()['count'];

// Dolda spelare totalt (exkludera systemkonton)
$stmt = $conn->query("SELECT COUNT(*) as count FROM stat_players WHERE visible_in_stats = 0 AND id NOT IN (1, 2)");
$hidden_total = $stmt->fetch()['count'];

/* ============================================
 * HÄMTA FÄRDIGA UTMÄRKELSER
 * ============================================ */

$completed_awards = [];
$months_sv = [
    1 => t('month_jan'), 2 => t('month_feb'), 3 => t('month_mar'), 4 => t('month_apr'),
    5 => t('month_may'), 6 => t('month_jun'), 7 => t('month_jul'), 8 => t('month_aug'),
    9 => t('month_sep'), 10 => t('month_oct'), 11 => t('month_nov'), 12 => t('month_dec')
];

// MÅNADSRAKETER
for ($month = 1; $month <= 12; $month++) {
    if (isPeriodCompleted($current_year, $month)) {
        $leader = getLeaderForPeriod($conn, $current_year, 'month', $month);
        if ($leader) {
            $completed_awards[] = [
                'type' => 'month',
                'number' => $month,
                'title' => t('stats_pub_monthly_rocket') . ' - ' . $months_sv[$month],
                'leader' => $leader,
                'min_matches' => 5,
                'gradient' => '135deg, #667eea 0%, #764ba2 100%'
            ];
        }
    }
}

// KVARTALSVINNARE
for ($quarter = 1; $quarter <= 4; $quarter++) {
    if (isPeriodCompleted($current_year, null, $quarter)) {
        $leader = getLeaderForPeriod($conn, $current_year, 'quarter', $quarter);
        if ($leader) {
            $completed_awards[] = [
                'type' => 'quarter',
                'number' => $quarter,
                'title' => t('stats_pub_quarter') . ' ' . $quarter . ' ' . t('stats_pub_winner'),
                'leader' => $leader,
                'min_matches' => 15,
                'gradient' => '135deg, #f093fb 0%, #f5576c 100%'
            ];
        }
    }
}

// HALVÅRSVINNARE
for ($half = 1; $half <= 2; $half++) {
    if (isPeriodCompleted($current_year, null, null, $half)) {
        $leader = getLeaderForPeriod($conn, $current_year, 'half', $half);
        if ($leader) {
            $completed_awards[] = [
                'type' => 'half',
                'number' => $half,
                'title' => t('stats_pub_half') . ' ' . $half . ' ' . t('stats_pub_winner'),
                'leader' => $leader,
                'min_matches' => 25,
                'gradient' => '135deg, #4facfe 0%, #00f2fe 100%'
            ];
        }
    }
}

// ÅRSVINNARE
if (isPeriodCompleted($current_year)) {
    $leader = getLeaderForPeriod($conn, $current_year, 'year');
    if ($leader) {
        $completed_awards[] = [
            'type' => 'year',
            'number' => null,
            'title' => t('stats_pub_yearly') . ' ' . $current_year,
            'leader' => $leader,
            'min_matches' => 50,
            'gradient' => '135deg, #fbc2eb 0%, #a6c1ee 100%'
        ];
    }
}

includeHeader();
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    
    <h1>📊 <?php echo t('stats_pub_page_title'); ?></h1>
    
    <!-- STATISTIKBOXAR -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0;">
        
        <!-- Spelade matcher -->
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
            <div style="font-size: 2.5em; margin-bottom: 10px;">🎲</div>
            <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 10px;"><?php echo t('stats_pub_played_games'); ?></div>
            <div style="font-size: 2.5em; font-weight: bold;"><?php echo $total_games; ?></div>
        </div>
        
        <!-- Aktiva spelare -->
        <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 25px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
            <div style="font-size: 2.5em; margin-bottom: 10px;">👥</div>
            <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 5px;"><?php echo t('stats_pub_active_players'); ?>
                <?php if ($hidden_players_count > 0): ?>
                    - <?php echo t('home_of_which'); ?> <?php echo $hidden_players_count; ?> <?php echo $hidden_players_count == 1 ? t('stats_pub_hidden_s') : t('stats_pub_hidden_p'); ?>
                <?php endif; ?>
            </div>
            <div style="font-size: 2.5em; font-weight: bold;"><?php echo $active_players_count; ?></div>
        </div>
        
        <!-- Största hand -->
        <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 25px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
            <div style="font-size: 2.5em; margin-bottom: 10px;">🏆</div>
            <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 5px;"><?php echo t('stats_pub_biggest_hand'); ?></div>
            <?php if ($biggest_hand && $biggest_hand['biggest_hand_points'] > 0): ?>
                <div style="font-size: 2.5em; font-weight: bold; margin-bottom: 5px;"><?php echo $biggest_hand['biggest_hand_points']; ?> <?php echo t('stats_pub_points'); ?></div>
                <div style="font-size: 0.9em; opacity: 0.9;"><?php echo implode(' & ', $biggest_hand_names); ?></div>
            <?php else: ?>
                <div style="font-size: 1.5em; opacity: 0.9;"><?php echo t('stats_pub_none_registered'); ?></div>
            <?php endif; ?>
        </div>
        
    </div>
    
<!-- DIAGRAM-SEKTION -->
<?php if (!empty($hands_stats)): ?>
<div style="display: grid; grid-template-columns: 1fr; gap: 30px; margin: 40px 0;">
    
    <!-- HÄNDER-STATISTIK -->
    <div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); padding: 25px; border-radius: 10px; color: #333; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <div style="font-size: 1.3em; font-weight: bold; margin-bottom: 5px;">📊 <?php echo t('stats_hands_chart_title'); ?></div>
        <div style="font-size: 0.95em; opacity: 0.8; margin-bottom: 15px;"><?php echo $current_year; ?></div>
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
    
    <!-- UTDELADE UTMÄRKELSER -->
    <?php if (!empty($completed_awards)): ?>
    <div style="margin: 40px 0;">
        <h2 style="color: #005B99; margin-bottom: 20px;">🏆 <?php echo t('stats_pub_awarded'); ?> <?php echo $current_year; ?></h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <?php foreach ($completed_awards as $award): ?>
                <?php renderAwardBox($award['leader'], $award['title'], $award['min_matches'], $award['gradient']); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- OM UTMÄRKELSERNA -->
    <div style="padding: 25px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid #4CAF50; margin: 30px 0;">
        <h3 style="margin-top: 0;">ℹ️ <?php echo t('stats_pub_about_awards'); ?></h3>
        <ul style="line-height: 1.8;">
            <li><?php echo t('stats_pub_award_info_1'); ?></li>
            <li><?php echo t('stats_pub_award_info_2'); ?></li>
            <li><?php echo t('stats_pub_award_info_3'); ?></li>
            <li><?php echo t('stats_pub_award_info_4'); ?></li>
        </ul>
        <p style="margin-top: 15px;">
            <?php echo t('stats_pub_award_basis'); ?>
        </p>
    </div>
    
    <!-- LOGGA IN FÖR MER STATISTIK -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center; margin: 30px 0;">
        <h3 style="color: white; margin-top: 0;">📊 <?php echo t('stats_pub_more_title'); ?></h3>
        <p style="font-size: 1.1em; margin-bottom: 20px; opacity: 0.9;">
            <?php echo t('stats_pub_more_desc'); ?>
        </p>
        <a href="login.php" style="display: inline-block; background: white; color: #667eea; padding: 12px 30px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 1.1em; transition: transform 0.2s;">
            <?php echo t('home_login_btn'); ?>
        </a>
    </div>
    
    <!-- ÅRSTATISTIK FOOTER -->
    <div style="background: #e8f5e9; padding: 20px; border-radius: 8px; margin-top: 30px;">
        <h3><?php echo t('home_stats_heading'); ?> <?php echo $current_year; ?></h3>
        <p><strong><?php echo t('home_total_players'); ?>:</strong> <?php echo $total_players; ?> 
        <?php if ($hidden_total > 0): ?>
            (<?php echo t('home_of_which'); ?> <?php echo $hidden_total; ?> <?php echo t('home_hidden_stats'); ?>)
        <?php endif; ?>
        </p>
        <p><strong><?php echo t('home_total_games'); ?>:</strong> <?php echo $total_games; ?></p>
    </div>
    
</div>

<?php includeFooter(); ?>
