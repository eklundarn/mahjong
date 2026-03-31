<?php
require_once 'config.php';

// Kräv minst registrator-behörighet
if (!hasRole('player')) {
    showError('Du måste ha registrator-behörighet eller högre för att se matcher.');
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($year <= 0 || $game_id <= 0) {
    showError('Ogiltiga parametrar.');
}

$conn = getDbConnection();

// Kontrollera att året är arkiverat
$stmt = $conn->prepare("SELECT * FROM archived_years WHERE year = ?");
$stmt->execute([$year]);
$year_info = $stmt->fetch();

if (!$year_info) {
    showError("År $year är inte arkiverat.");
}

$archive_games_table = "archived_games_" . $year;
$archive_hands_table = "archived_game_hands_" . $year;

// Kontrollera att tabellen finns
$stmt = $conn->query("SHOW TABLES LIKE '$archive_games_table'");
if (!$stmt->fetch()) {
    showError("Arkivtabellen för $year hittades inte.");
}

// Hämta matchinformation
$stmt = $conn->prepare("
    SELECT 
        g.*,
        pa.first_name as pa_first, pa.last_name as pa_last,
        pb.first_name as pb_first, pb.last_name as pb_last,
        pc.first_name as pc_first, pc.last_name as pc_last,
        pd.first_name as pd_first, pd.last_name as pd_last,
        bp.first_name as bp_first, bp.last_name as bp_last
    FROM `$archive_games_table` g
    LEFT JOIN players pa ON g.player_a_id = pa.id
    LEFT JOIN players pb ON g.player_b_id = pb.id
    LEFT JOIN players pc ON g.player_c_id = pc.id
    LEFT JOIN players pd ON g.player_d_id = pd.id
    LEFT JOIN players bp ON g.biggest_hand_player_id = bp.id
    WHERE g.id = ?
");
$stmt->execute([$game_id]);
$game = $stmt->fetch();

if (!$game) {
    showError('Match hittades inte.');
}

// Hämta händer om det är en detaljerad match
$hands = [];
if ($game['detailed_entry']) {
    // Kontrollera att hands-tabellen finns
    $stmt = $conn->query("SHOW TABLES LIKE '$archive_hands_table'");
    if ($stmt->fetch()) {
        $stmt = $conn->prepare("
            SELECT * FROM `$archive_hands_table`
            WHERE game_id = ? 
            ORDER BY hand_number ASC
        ");
        $stmt->execute([$game_id]);
        $hands = $stmt->fetchAll();
    }
}

$players = [
    'A' => [
        'vms' => $game['player_a_id'],
        'name' => $game['pa_first'] . ' ' . $game['pa_last'],
        'mini' => $game['player_a_minipoints'],
        'table' => $game['player_a_tablepoints'],
        'penalties' => $game['player_a_penalties'],
        'false_hu' => $game['player_a_false_hu']
    ],
    'B' => [
        'vms' => $game['player_b_id'],
        'name' => $game['pb_first'] . ' ' . $game['pb_last'],
        'mini' => $game['player_b_minipoints'],
        'table' => $game['player_b_tablepoints'],
        'penalties' => $game['player_b_penalties'],
        'false_hu' => $game['player_b_false_hu']
    ],
    'C' => [
        'vms' => $game['player_c_id'],
        'name' => $game['pc_first'] . ' ' . $game['pc_last'],
        'mini' => $game['player_c_minipoints'],
        'table' => $game['player_c_tablepoints'],
        'penalties' => $game['player_c_penalties'],
        'false_hu' => $game['player_c_false_hu']
    ],
    'D' => [
        'vms' => $game['player_d_id'],
        'name' => $game['pd_first'] . ' ' . $game['pd_last'],
        'mini' => $game['player_d_minipoints'],
        'table' => $game['player_d_tablepoints'],
        'penalties' => $game['player_d_penalties'],
        'false_hu' => $game['player_d_false_hu']
    ]
];

includeHeader();
?>

<div style="background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107; margin: 20px 0;">
    <strong>📚 Arkiverad match från <?php echo $year; ?></strong>
</div>

<h2>Match <?php echo $game['game_number']; ?> (<?php echo $game['game_year']; ?>)</h2>

<div style="margin: 20px 0;">
    <a href="previous.php?year=<?php echo $year; ?>" class="btn btn-secondary">← Tillbaka till arkivet</a>
</div>

<!-- MATCHINFORMATION -->
<div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
        <div>
            <strong>Datum:</strong><br>
            <?php echo formatSwedishDate($game['game_date']); ?>
        </div>
        <div>
            <strong>Matchnummer:</strong><br>
            <?php echo $game['game_number']; ?>
        </div>
        <div>
            <strong>Typ:</strong><br>
            <?php echo $game['detailed_entry'] ? '🤄 Hand-för-hand' : '📊 Slutresultat'; ?>
        </div>
    </div>
    
    <?php if ($game['biggest_hand_points'] > 0): ?>
    <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px;">
        <strong>🏆 Största hand:</strong> 
        <?php echo $game['biggest_hand_points']; ?> poäng av 
        <?php echo htmlspecialchars($game['bp_first'] . ' ' . $game['bp_last']); ?>
        (<?php echo $game['biggest_hand_player_id']; ?>)
    </div>
    <?php endif; ?>
</div>

<!-- SLUTRESULTAT -->
<h3>Slutresultat</h3>
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
    <?php 
    $sorted_players = $players;
    uasort($sorted_players, function($a, $b) {
        return $b['table'] <=> $a['table'];
    });
    
    $medals = ['🥇', '🥈', '🥉', ''];
    $idx = 0;
    
    foreach ($sorted_players as $letter => $p): 
    ?>
    <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 2px solid #ddd;">
        <div style="font-size: 2em; text-align: center; margin-bottom: 10px;">
            <?php echo $medals[$idx]; ?>
        </div>
        <div style="text-align: center; font-weight: bold; font-size: 1.2em; margin-bottom: 5px;">
            <?php echo htmlspecialchars($p['name']); ?>
        </div>
        <div style="text-align: center; color: #666; margin-bottom: 15px;">
            <?php echo htmlspecialchars($p['vms']); ?> (Spelare <?php echo $letter; ?>)
        </div>
        
        <div style="background: white; padding: 15px; border-radius: 5px; margin-top: 10px;">
            <div style="text-align: center; margin-bottom: 10px;">
                <div style="font-size: 2em; font-weight: bold; color: #2196F3;">
                    <?php echo $p['table']; ?>
                </div>
                <div style="font-size: 0.9em; color: #666;">Bordspoäng</div>
            </div>
            
            <div style="text-align: center; padding-top: 10px; border-top: 1px solid #eee;">
                <div style="font-size: 1.5em; font-weight: bold; color: <?php echo $p['mini'] >= 0 ? '#4CAF50' : '#f44336'; ?>">
                    <?php echo $p['mini'] > 0 ? '+' : ''; ?><?php echo $p['mini']; ?>
                </div>
                <div style="font-size: 0.9em; color: #666;">Minipoäng</div>
            </div>
            
            <?php if ($p['penalties'] > 0 || $p['false_hu'] > 0): ?>
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee; font-size: 0.85em;">
                <?php if ($p['penalties'] > 0): ?>
                    <div style="color: #f44336;">Penalties: -<?php echo $p['penalties']; ?></div>
                <?php endif; ?>
                <?php if ($p['false_hu'] > 0): ?>
                    <div style="color: #f44336;">False Hu: -<?php echo $p['false_hu']; ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php 
    $idx++;
    endforeach; 
    ?>
</div>

<!-- HAND-FÖR-HAND DETALJER -->
<?php if ($game['detailed_entry'] && !empty($hands)): ?>
    <h3 style="margin-top: 40px;">Hand-för-hand detaljer</h3>
    
    <div style="margin: 20px 0; padding: 15px; background: #e3f2fd; border-radius: 5px;">
        <strong>💡 Läsguide:</strong>
        <div style="margin-top: 10px; font-size: 0.9em;">
            <span style="background: #c8e6c9; padding: 2px 8px; border-radius: 3px; margin-right: 5px;">Grön</span> = Plus-poäng för handen |
            <span style="background: #ffcdd2; padding: 2px 8px; border-radius: 3px; margin-right: 5px;">Röd</span> = Minus-poäng för handen
        </div>
    </div>
    
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th style="width: 80px;">Hand #</th>
                    <th style="width: 100px;">Hu-poäng</th>
                    <th style="width: 120px;">Vinnare</th>
                    <th style="width: 120px;">Från vem</th>
                    <th>Spelare A<br><?php echo htmlspecialchars($players['A']['name']); ?></th>
                    <th>Spelare B<br><?php echo htmlspecialchars($players['B']['name']); ?></th>
                    <th>Spelare C<br><?php echo htmlspecialchars($players['C']['name']); ?></th>
                    <th>Spelare D<br><?php echo htmlspecialchars($players['D']['name']); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $running_totals = [0, 0, 0, 0];
                
                foreach ($hands as $hand): 
                    $winner_letter = ['', 'A', 'B', 'C', 'D'][$hand['winning_player']];
                    $from_text = $hand['from_player'] === 0 ? 'Muren' : 'Spelare ' . ['', 'A', 'B', 'C', 'D'][$hand['from_player']];
                    
                    $running_totals[0] += $hand['player_a_points'];
                    $running_totals[1] += $hand['player_b_points'];
                    $running_totals[2] += $hand['player_c_points'];
                    $running_totals[3] += $hand['player_d_points'];
                ?>
                <tr>
                    <td><strong>Hand <?php echo $hand['hand_number']; ?></strong></td>
                    <td style="text-align: center;"><strong><?php echo $hand['hu_points']; ?></strong></td>
                    <td>Spelare <?php echo $winner_letter; ?></td>
                    <td><?php echo $from_text; ?></td>
                    
                    <?php 
                    $hand_points = [
                        $hand['player_a_points'],
                        $hand['player_b_points'],
                        $hand['player_c_points'],
                        $hand['player_d_points']
                    ];
                    
                    foreach ($hand_points as $idx => $points):
                        $bg_color = $points > 0 ? '#c8e6c9' : ($points < 0 ? '#ffcdd2' : '#f5f5f5');
                        $text_color = $points > 0 ? '#2e7d32' : ($points < 0 ? '#c62828' : '#757575');
                    ?>
                    <td style="background: <?php echo $bg_color; ?>; color: <?php echo $text_color; ?>; font-weight: bold; text-align: center;">
                        <?php echo $points > 0 ? '+' : ''; ?><?php echo $points; ?>
                        <div style="font-size: 0.8em; color: #666; font-weight: normal; margin-top: 5px;">
                            Totalt: <?php echo $running_totals[$idx]; ?>
                        </div>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                
                <!-- TOTALSUMMERING -->
                <tr style="background: #f0f0f0; font-weight: bold;">
                    <td colspan="4">Totalt från händer</td>
                    <?php foreach ($running_totals as $total): ?>
                    <td style="text-align: center; font-size: 1.2em;">
                        <?php echo $total > 0 ? '+' : ''; ?><?php echo $total; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                
                <!-- STRAFF OCH FALSE HU -->
                <?php if ($players['A']['penalties'] > 0 || $players['B']['penalties'] > 0 || $players['C']['penalties'] > 0 || $players['D']['penalties'] > 0): ?>
                <tr style="background: #fff3e0;">
                    <td colspan="4">Penalties</td>
                    <td style="text-align: center; color: #f44336;">
                        <?php echo $players['A']['penalties'] > 0 ? '-' . $players['A']['penalties'] : '0'; ?>
                    </td>
                    <td style="text-align: center; color: #f44336;">
                        <?php echo $players['B']['penalties'] > 0 ? '-' . $players['B']['penalties'] : '0'; ?>
                    </td>
                    <td style="text-align: center; color: #f44336;">
                        <?php echo $players['C']['penalties'] > 0 ? '-' . $players['C']['penalties'] : '0'; ?>
                    </td>
                    <td style="text-align: center; color: #f44336;">
                        <?php echo $players['D']['penalties'] > 0 ? '-' . $players['D']['penalties'] : '0'; ?>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php if ($players['A']['false_hu'] > 0 || $players['B']['false_hu'] > 0 || $players['C']['false_hu'] > 0 || $players['D']['false_hu'] > 0): ?>
                <tr style="background: #ffebee;">
                    <td colspan="4">False Hu</td>
                    <td style="text-align: center; color: #f44336;">
                        <?php echo $players['A']['false_hu'] > 0 ? '-' . $players['A']['false_hu'] : '0'; ?>
                    </td>
                    <td style="text-align: center; color: #f44336;">
                        <?php echo $players['B']['false_hu'] > 0 ? '-' . $players['B']['false_hu'] : '0'; ?>
                    </td>
                    <td style="text-align: center; color: #f44336;">
                        <?php echo $players['C']['false_hu'] > 0 ? '-' . $players['C']['false_hu'] : '0'; ?>
                    </td>
                    <td style="text-align: center; color: #f44336;">
                        <?php echo $players['D']['false_hu'] > 0 ? '-' . $players['D']['false_hu'] : '0'; ?>
                    </td>
                </tr>
                <?php endif; ?>
                
                <!-- SLUTSUMMA -->
                <tr style="background: #2c5f2d; color: white; font-weight: bold; font-size: 1.1em;">
                    <td colspan="4">SLUTRESULTAT (Minipoäng)</td>
                    <td style="text-align: center;">
                        <?php echo $players['A']['mini'] > 0 ? '+' : ''; ?><?php echo $players['A']['mini']; ?>
                    </td>
                    <td style="text-align: center;">
                        <?php echo $players['B']['mini'] > 0 ? '+' : ''; ?><?php echo $players['B']['mini']; ?>
                    </td>
                    <td style="text-align: center;">
                        <?php echo $players['C']['mini'] > 0 ? '+' : ''; ?><?php echo $players['C']['mini']; ?>
                    </td>
                    <td style="text-align: center;">
                        <?php echo $players['D']['mini'] > 0 ? '+' : ''; ?><?php echo $players['D']['mini']; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="message info" style="margin-top: 40px;">
        <p>Denna match registrerades utan hand-för-hand detaljer.</p>
    </div>
<?php endif; ?>

<div style="margin-top: 30px; text-align: center;">
    <a href="previous.php?year=<?php echo $year; ?>" class="btn btn-secondary">
        ← Tillbaka till arkivet
    </a>
</div>

<?php includeFooter(); ?>