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

// Hämta månadnamn
$months = [
    1 => t('month_jan'), 2 => t('month_feb'), 3 => t('month_mar'), 4 => t('month_apr'),
    5 => t('month_may'), 6 => t('month_jun'), 7 => t('month_jul'), 8 => t('month_aug'),
    9 => t('month_sep'), 10 => t('month_oct'), 11 => t('month_nov'), 12 => t('month_dec')
];

// Hämta alla spelare för helåret (för prispallen)
$stmt = $conn->prepare("
    SELECT 
        p.id,
        p.first_name,
        p.last_name,
        p.club,
        p.city,
        COUNT(DISTINCT g.id) AS games_played,
        
        COALESCE(SUM(CASE 
            WHEN g.player_a_id = p.id THEN g.player_a_tablepoints
            WHEN g.player_b_id = p.id THEN g.player_b_tablepoints
            WHEN g.player_c_id = p.id THEN g.player_c_tablepoints
            WHEN g.player_d_id = p.id THEN g.player_d_tablepoints
        END), 0) AS total_tablepoints,
        
        CASE 
            WHEN COUNT(DISTINCT g.id) > 0 THEN 
                COALESCE(SUM(CASE 
                    WHEN g.player_a_id = p.id THEN g.player_a_tablepoints
                    WHEN g.player_b_id = p.id THEN g.player_b_tablepoints
                    WHEN g.player_c_id = p.id THEN g.player_c_tablepoints
                    WHEN g.player_d_id = p.id THEN g.player_d_tablepoints
                END), 0) / COUNT(DISTINCT g.id)
            ELSE 0
        END AS avg_tablepoints,
        
        CASE 
            WHEN COUNT(DISTINCT g.id) > 0 THEN 
                COALESCE(SUM(CASE 
                    WHEN g.player_a_id = p.id THEN g.player_a_minipoints
                    WHEN g.player_b_id = p.id THEN g.player_b_minipoints
                    WHEN g.player_c_id = p.id THEN g.player_c_minipoints
                    WHEN g.player_d_id = p.id THEN g.player_d_minipoints
                END), 0) / COUNT(DISTINCT g.id)
            ELSE 0
        END AS avg_minipoints,
        
        SUM(CASE 
            WHEN (g.player_a_id = p.id AND g.player_a_tablepoints = 4) OR
                 (g.player_b_id = p.id AND g.player_b_tablepoints = 4) OR
                 (g.player_c_id = p.id AND g.player_c_tablepoints = 4) OR
                 (g.player_d_id = p.id AND g.player_d_tablepoints = 4)
            THEN 1 ELSE 0
        END) AS first_place
        
    FROM stat_players p
    LEFT JOIN stat_games g ON (
        (g.player_a_id = p.id OR 
         g.player_b_id = p.id OR 
         g.player_c_id = p.id OR 
         g.player_d_id = p.id)
        AND g.game_year = ?
        AND g.approved = 1
    )
    WHERE p.visible_in_stats = 1
    GROUP BY p.id, p.id, p.first_name, p.last_name, p.club, p.city
    HAVING games_played >= 50
    ORDER BY avg_tablepoints DESC, avg_minipoints DESC
");
$stmt->execute([$current_year]);
$all_qualified_players = $stmt->fetchAll();

// Hämta aktuell månad
$current_month = (int)date('n');

// Hämta månadens raket (för aktuell eller senaste månad med matcher)
$monthly_winners = [];
for ($month = $current_month; $month >= 1; $month--) {
    $stmt = $conn->prepare("
        SELECT 
            p.first_name,
            p.last_name,
            p.city,
            p.club,
            COUNT(DISTINCT g.id) AS games_played,
            
            CASE 
                WHEN COUNT(DISTINCT g.id) > 0 THEN 
                    COALESCE(SUM(CASE 
                        WHEN g.player_a_id = p.id THEN g.player_a_tablepoints
                        WHEN g.player_b_id = p.id THEN g.player_b_tablepoints
                        WHEN g.player_c_id = p.id THEN g.player_c_tablepoints
                        WHEN g.player_d_id = p.id THEN g.player_d_tablepoints
                    END), 0) / COUNT(DISTINCT g.id)
                ELSE 0
            END AS avg_tablepoints,
            
            CASE 
                WHEN COUNT(DISTINCT g.id) > 0 THEN 
                    COALESCE(SUM(CASE 
                        WHEN g.player_a_id = p.id THEN g.player_a_minipoints
                        WHEN g.player_b_id = p.id THEN g.player_b_minipoints
                        WHEN g.player_c_id = p.id THEN g.player_c_minipoints
                        WHEN g.player_d_id = p.id THEN g.player_d_minipoints
                    END), 0) / COUNT(DISTINCT g.id)
                ELSE 0
            END AS avg_minipoints
            
        FROM stat_players p
        LEFT JOIN stat_games g ON (
            (g.player_a_id = p.id OR 
             g.player_b_id = p.id OR 
             g.player_c_id = p.id OR 
             g.player_d_id = p.id)
            AND g.game_year = ?
            AND MONTH(g.game_date) = ?
            AND g.approved = 1
        )
        WHERE p.visible_in_stats = 1
        GROUP BY p.id, p.first_name, p.last_name, p.city, p.club
        HAVING games_played >= 5
        ORDER BY avg_tablepoints DESC, avg_minipoints DESC
        LIMIT 1
    ");
    $stmt->execute([$current_year, $month]);
    $winner = $stmt->fetch();
    
    if ($winner) {
        $monthly_winners[$month] = $winner;
    }
}

// Hämta halvårsvinnare
$stmt = $conn->prepare("
    SELECT 
        p.first_name,
        p.last_name,
        p.club,
        p.city,
        COUNT(DISTINCT g.id) AS games_played,
        
        CASE 
            WHEN COUNT(DISTINCT g.id) > 0 THEN 
                COALESCE(SUM(CASE 
                    WHEN g.player_a_id = p.id THEN g.player_a_tablepoints
                    WHEN g.player_b_id = p.id THEN g.player_b_tablepoints
                    WHEN g.player_c_id = p.id THEN g.player_c_tablepoints
                    WHEN g.player_d_id = p.id THEN g.player_d_tablepoints
                END), 0) / COUNT(DISTINCT g.id)
            ELSE 0
        END AS avg_tablepoints,
        
        CASE 
            WHEN COUNT(DISTINCT g.id) > 0 
            THEN COALESCE(SUM(CASE 
                WHEN g.player_a_id = p.id THEN g.player_a_minipoints
                WHEN g.player_b_id = p.id THEN g.player_b_minipoints
                WHEN g.player_c_id = p.id THEN g.player_c_minipoints
                WHEN g.player_d_id = p.id THEN g.player_d_minipoints
            END), 0) / COUNT(DISTINCT g.id)
            ELSE 0
        END AS avg_minipoints
        
    FROM stat_players p
    LEFT JOIN stat_games g ON (
        (g.player_a_id = p.id OR 
         g.player_b_id = p.id OR 
         g.player_c_id = p.id OR 
         g.player_d_id = p.id)
        AND g.game_year = ?
        AND MONTH(g.game_date) BETWEEN 1 AND 6
        AND g.approved = 1
    )
    WHERE p.visible_in_stats = 1
    GROUP BY p.id, p.first_name, p.last_name, p.club, p.city
    HAVING games_played >= 25
    ORDER BY avg_tablepoints DESC, avg_minipoints DESC
    LIMIT 1
");
$stmt->execute([$current_year]);
$half_year_winner = $stmt->fetch();

// Hämta halvårsvinnare 2 (juli-december)
$stmt = $conn->prepare("
    SELECT 
        p.first_name,
        p.last_name,
        p.club,
        p.city,
        COUNT(DISTINCT g.id) AS games_played,
        
        CASE 
            WHEN COUNT(DISTINCT g.id) > 0 THEN 
                COALESCE(SUM(CASE 
                    WHEN g.player_a_id = p.id THEN g.player_a_tablepoints
                    WHEN g.player_b_id = p.id THEN g.player_b_tablepoints
                    WHEN g.player_c_id = p.id THEN g.player_c_tablepoints
                    WHEN g.player_d_id = p.id THEN g.player_d_tablepoints
                END), 0) / COUNT(DISTINCT g.id)
            ELSE 0
        END AS avg_tablepoints,
        
        CASE 
            WHEN COUNT(DISTINCT g.id) > 0 
            THEN COALESCE(SUM(CASE 
                WHEN g.player_a_id = p.id THEN g.player_a_minipoints
                WHEN g.player_b_id = p.id THEN g.player_b_minipoints
                WHEN g.player_c_id = p.id THEN g.player_c_minipoints
                WHEN g.player_d_id = p.id THEN g.player_d_minipoints
            END), 0) / COUNT(DISTINCT g.id)
            ELSE 0
        END AS avg_minipoints
        
    FROM stat_players p
    LEFT JOIN stat_games g ON (
        (g.player_a_id = p.id OR 
         g.player_b_id = p.id OR 
         g.player_c_id = p.id OR 
         g.player_d_id = p.id)
        AND g.game_year = ?
        AND MONTH(g.game_date) BETWEEN 7 AND 12
        AND g.approved = 1
    )
    WHERE p.visible_in_stats = 1
    GROUP BY p.id, p.first_name, p.last_name, p.club, p.city
    HAVING games_played >= 25
    ORDER BY avg_tablepoints DESC, avg_minipoints DESC
    LIMIT 1
");
$stmt->execute([$current_year]);
$half_year_winner_2 = $stmt->fetch();

includeHeader();
?>

<h2>🏆 <?php echo t('awards_title') . ' ' . $current_year; ?></h2>
<p style="color: #666; margin-bottom: 30px;"><?php echo t('awards_intro') . ' ' . $current_year; ?></p>

<!-- PRISPALLEN -->
<?php if (!empty($all_qualified_players) && count($all_qualified_players) >= 1): ?>
<div style="margin-bottom: 50px;">
    <h3 style="border-bottom: 3px solid #4CAF50; padding-bottom: 10px; margin-bottom: 25px;">
        🎖️ <?php echo t('awards_podium_title'); ?>
    </h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <?php
        $podium = array_slice($all_qualified_players, 0, 3);
        $medals = ['🥇', '🥈', '🥉'];
        $colors = [
            'linear-gradient(135deg, #FFD700 0%, #FFA500 100%)', // Guld
            'linear-gradient(135deg, #C0C0C0 0%, #808080 100%)', // Silver
            'linear-gradient(135deg, #CD7F32 0%, #8B4513 100%)'  // Brons
        ];
        $ranks = [t('awards_rank_1'), t('awards_rank_2'), t('awards_rank_3')];
        
        foreach ($podium as $index => $player):
        ?>
        <div style="background: <?php echo $colors[$index]; ?>; padding: 30px 25px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
            <div style="font-size: 3.5em; margin-bottom: 10px;">
                <?php echo $medals[$index]; ?>
            </div>
            <div style="font-size: 0.9em; opacity: 0.9; margin-bottom: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">
                <?php echo $ranks[$index]; ?>
            </div>
            <div style="font-size: 1.4em; font-weight: bold; margin-bottom: 5px;">
                <?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?>
            </div>
            <div style="font-size: 0.95em; opacity: 0.9; margin-bottom: 20px;">
                <?php echo htmlspecialchars($player['city'] ?: $player['club']); ?>
            </div>
            <div style="font-size: 1.6em; font-weight: bold; margin-bottom: 5px;">
                Ø <?php echo number_format($player['avg_tablepoints'], 2); ?> BP
            </div>
            <div style="font-size: 0.95em; opacity: 0.9;">
                <?php echo $player['games_played']; ?> <?php echo t('awards_games'); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (count($all_qualified_players) < 3): ?>
    <p style="color: #666; font-style: italic; margin-top: 15px;">
        <?php echo t('awards_podium_qualifier'); ?>
    </p>
    <?php endif; ?>
</div>
<?php else: ?>
<div style="margin-bottom: 50px;">
    <h3 style="border-bottom: 3px solid #4CAF50; padding-bottom: 10px; margin-bottom: 25px;">
        🎖️ <?php echo t('awards_podium_title'); ?>
    </h3>
    <div class="message info">
        <p><?php echo t('awards_podium_none'); ?></p>
    </div>
</div>
<?php endif; ?>

<!-- MÅNADENS RAKET -->
<div style="margin-bottom: 50px;">
    <h3 style="border-bottom: 3px solid #4CAF50; padding-bottom: 10px; margin-bottom: 25px;">
        🚀 <?php echo t('awards_monthly_title'); ?>
    </h3>
    
    <?php if (!empty($monthly_winners)): ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <?php foreach ($monthly_winners as $month => $winner): ?>
        <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffc107 100%); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h4 style="margin-top: 0; color: #856404; font-size: 1.3em;">
                🚀 <?php echo $months[$month]; ?> <?php echo $current_year; ?>
            </h4>
            <div style="font-size: 1.3em; font-weight: bold; margin: 15px 0; color: #333;">
                <?php echo htmlspecialchars($winner['first_name'] . ' ' . $winner['last_name']); ?>
            </div>
            <div style="font-size: 1em; color: #666; margin-bottom: 15px;">
                <?php echo htmlspecialchars($winner['city'] ?: $winner['club']); ?>
            </div>
            <div style="font-size: 1.4em; font-weight: bold; color: #2c5f2d;">
                Ø <?php echo number_format($winner['avg_tablepoints'], 2); ?> BP
            </div>
            <div style="font-size: 0.95em; color: #666; margin-top: 5px;">
                <?php echo $winner['games_played']; ?> <?php echo t('awards_games'); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <p style="color: #666; font-style: italic; margin-top: 15px;">
        <?php echo t('awards_monthly_qualifier'); ?>
    </p>
    <?php else: ?>
    <div class="message info">
        <p><?php echo t('awards_monthly_none'); ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- SPECIALUTMÄRKELSER -->
<div style="margin-bottom: 50px;">
    <h3 style="border-bottom: 3px solid #4CAF50; padding-bottom: 10px; margin-bottom: 25px;">
        🌟 <?php echo t('awards_special_title') . ' ' . $current_year; ?>
    </h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
        
        <!-- HALVÅRSVINNARE -->
        <div style="background: linear-gradient(135deg, #FA8BFF 0%, #2BD2FF 90%); padding: 30px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
            <h4 style="color: white; margin-top: 0; font-size: 1.4em;">🎖️ <?php echo t('awards_half1_title'); ?></h4>
            <?php if ($half_year_winner): ?>
                <div style="font-size: 1.6em; font-weight: bold; margin: 20px 0;">
                    <?php echo htmlspecialchars($half_year_winner['first_name'] . ' ' . $half_year_winner['last_name']); ?>
                </div>
                <div style="font-size: 1.1em; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($half_year_winner['city'] ?: $half_year_winner['club']); ?>
                </div>
                <div style="font-size: 1.4em; font-weight: bold;">
                    Ø <?php echo number_format($half_year_winner['avg_tablepoints'], 2); ?> BP
                </div>
                <div style="font-size: 1em; opacity: 0.9; margin-top: 5px;">
                    <?php echo $half_year_winner['games_played']; ?> <?php echo t('awards_games'); ?>
                </div>
            <?php else: ?>
                <p style="opacity: 0.9; font-size: 1.1em;"><?php echo t('awards_half_none'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- HALVÅRSVINNARE 2 (Jul-Dec) -->
        <div style="background: linear-gradient(135deg, #FA8BFF 0%, #2BD2FF 90%); padding: 30px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
            <h4 style="color: white; margin-top: 0; font-size: 1.4em;">🎖️ <?php echo t('awards_half2_title'); ?></h4>
            <?php if ($half_year_winner_2): ?>
                <div style="font-size: 1.6em; font-weight: bold; margin: 20px 0;">
                    <?php echo htmlspecialchars($half_year_winner_2['first_name'] . ' ' . $half_year_winner_2['last_name']); ?>
                </div>
                <div style="font-size: 1.1em; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($half_year_winner_2['city'] ?: $half_year_winner_2['club']); ?>
                </div>
                <div style="font-size: 1.4em; font-weight: bold;">
                    Ø <?php echo number_format($half_year_winner_2['avg_tablepoints'], 2); ?> BP
                </div>
                <div style="font-size: 1em; opacity: 0.9; margin-top: 5px;">
                    <?php echo $half_year_winner_2['games_played']; ?> <?php echo t('awards_games'); ?>
                </div>
            <?php else: ?>
                <p style="opacity: 0.9; font-size: 1.1em;"><?php echo t('awards_half_none'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- HELÅRSVINNARE -->
        <div style="background: linear-gradient(135deg, #FFD89B 0%, #19547B 100%); padding: 30px; border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
            <h4 style="color: white; margin-top: 0; font-size: 1.4em;">👑 <?php echo t('awards_year_title'); ?></h4>
            <?php if (!empty($all_qualified_players)): 
                $year_winner = $all_qualified_players[0];
            ?>
                <div style="font-size: 1.6em; font-weight: bold; margin: 20px 0;">
                    <?php echo htmlspecialchars($year_winner['first_name'] . ' ' . $year_winner['last_name']); ?>
                </div>
                <div style="font-size: 1.1em; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($year_winner['city'] ?: $year_winner['club']); ?>
                </div>
                <div style="font-size: 1.4em; font-weight: bold;">
                    Ø <?php echo number_format($year_winner['avg_tablepoints'], 2); ?> BP
                </div>
                <div style="font-size: 1em; opacity: 0.9; margin-top: 5px;">
                    <?php echo $year_winner['games_played']; ?> <?php echo t('awards_games'); ?> | 
                    <?php echo t('awards_total'); ?>: <?php echo $year_winner['total_tablepoints']; ?> BP
                </div>
            <?php else: ?>
                <p style="opacity: 0.9; font-size: 1.1em;"><?php echo t('awards_year_none'); ?></p>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- INFO -->
<div class="about-box" style="padding: 25px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid #4CAF50;">
    <h3 style="margin-top: 0;">ℹ️ <?php echo t('awards_info_title'); ?></h3>
    <ul style="line-height: 1.8;">
        <li><?php echo t('awards_info_podium'); ?></li>
        <li><?php echo t('awards_info_monthly'); ?></li>
        <li><?php echo t('awards_info_half'); ?></li>
        <li><?php echo t('awards_info_year'); ?></li>
    </ul>
    <p style="margin-top: 15px; color: #666;">
        <?php echo t('awards_info_desc'); ?>
    </p>
</div>

<?php includeFooter(); ?>
