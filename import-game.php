<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

// Kräv registrator-behörighet
if (!hasRole('player')) {
    showError(t('import_error_no_access'));
}

$conn = getDbConnection();
$error = '';
$preview_data = null;
$player_mapping = [];

// Steg 1: Förbered import (datum, matchnamn, spelare)
$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // STEG 2: Ladda upp och läs CSV-fil
    if ($step === 2 && isset($_FILES['csv_file'])) {
        $game_date = cleanInput($_POST['game_date']);
        $game_name = cleanInput($_POST['game_name']);
        $player_a_id = cleanInput($_POST['player_a_id']);
        $player_b_id = cleanInput($_POST['player_b_id']);
        $player_c_id = cleanInput($_POST['player_c_id']);
        $player_d_id = cleanInput($_POST['player_d_id']);
        
        // Validera att alla spelare är valda
        if (empty($player_a_id) || empty($player_b_id) || empty($player_c_id) || empty($player_d_id)) {
            $error = t('import_error_players');
            $step = 1;
        } else {
            // Läs CSV-fil
            $file = $_FILES['csv_file'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = t('import_error_upload');
                $step = 1;
            } elseif (!in_array(pathinfo($file['name'], PATHINFO_EXTENSION), ['csv'])) {
                $error = t('import_error_csv_only');
                $step = 1;
            } else {
                // Läs CSV med escape-parameter för PHP 8.4
                $csv_data = [];
                if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
                    while (($row = fgetcsv($handle, 1000, ',', '"', '\\')) !== FALSE) {
                        $csv_data[] = $row;
                    }
                    fclose($handle);
                }
                
                if (empty($csv_data)) {
                    $error = t('import_error_csv_empty');
                    $step = 1;
                } else {
                    // Parse CSV och skapa förhandsgranskning
                    $header = $csv_data[0];
                    
                    // Hitta vilken kolumn som motsvarar vilken spelare
                    // Kolumn E (index 4) = Points [namn] → Spelare A
                    // Kolumn F (index 5) = Points [namn] → Spelare B
                    // Kolumn G (index 6) = Points [namn] → Spelare C
                    // Kolumn H (index 7) = Points [namn] → Spelare D
                    
                    $player_a_name = str_replace('Points ', '', trim($header[4]));
                    $player_b_name = str_replace('Points ', '', trim($header[5]));
                    $player_c_name = str_replace('Points ', '', trim($header[6]));
                    $player_d_name = str_replace('Points ', '', trim($header[7]));
                    
                    // Hämta spelarnamn från databas - FIX: Bara 2 kolumner för FETCH_KEY_PAIR
                    $stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM stat_players WHERE id IN (?, ?, ?, ?)");
                    $stmt->execute([$player_a_id, $player_b_id, $player_c_id, $player_d_id]);
                    $stat_players = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                    
                    $player_mapping = [
                        'A' => ['vms' => $player_a_id, 'name' => $stat_players[$player_a_id] ?? t('import_unknown'), 'file_name' => $player_a_name],
                        'B' => ['vms' => $player_b_id, 'name' => $stat_players[$player_b_id] ?? t('import_unknown'), 'file_name' => $player_b_name],
                        'C' => ['vms' => $player_c_id, 'name' => $stat_players[$player_c_id] ?? t('import_unknown'), 'file_name' => $player_c_name],
                        'D' => ['vms' => $player_d_id, 'name' => $stat_players[$player_d_id] ?? t('import_unknown'), 'file_name' => $player_d_name]
                    ];
                    
                    // Parse händer (jämna rader: 2, 4, 6... upp till 32)
                    $hands = [];
                    $max_hand_points = 0;
                    
                    for ($i = 2; $i <= 32; $i += 2) {
                        if (!isset($csv_data[$i - 1])) break; // Rad finns inte (0-indexerat)
                        
                        $row = $csv_data[$i - 1];
                        
                        // Kolumn A: Hand number
                        $hand_num = (int)$row[0];
                        if ($hand_num === 0 || $row[0] === '-') break; // Inga fler händer
                        
                        // Kolumn B: Winner (namn från fil → konvertera till 1-4)
                        $winner_name = trim($row[1]);
                        $winner = 0;
                        if ($winner_name === $player_a_name) $winner = 1;
                        elseif ($winner_name === $player_b_name) $winner = 2;
                        elseif ($winner_name === $player_c_name) $winner = 3;
                        elseif ($winner_name === $player_d_name) $winner = 4;
                        
                        // Kolumn C: Discarder (namn från fil → konvertera till 1-4, eller 0 för självdragen)
                        $discarder_name = trim($row[2]);
                        $discarder = 0;
                        if ($discarder_name !== '-' && $discarder_name !== '') {
                            if ($discarder_name === $player_a_name) $discarder = 1;
                            elseif ($discarder_name === $player_b_name) $discarder = 2;
                            elseif ($discarder_name === $player_c_name) $discarder = 3;
                            elseif ($discarder_name === $player_d_name) $discarder = 4;
                        }
                        
                        // Kolumn D: Hand Points
                        $hand_points = (int)$row[3];
                        if ($hand_points > $max_hand_points) {
                            $max_hand_points = $hand_points;
                        }
                        
                        $hands[] = [
                            'hand' => $hand_num,
                            'winner' => $winner,
                            'discarder' => $discarder,
                            'points' => $hand_points
                        ];
                    }
                    
                    // Läs totaler från rad 33 (index 32)
                    if (isset($csv_data[32])) {
                        $totals_row = $csv_data[32];
                        
                        // Kolumner E-H: Minipoäng totaler
                        $mp_a = (int)$totals_row[4];
                        $mp_b = (int)$totals_row[5];
                        $mp_c = (int)$totals_row[6];
                        $mp_d = (int)$totals_row[7];
                        
                        // Kolumner I-L: Penalties
                        $penalty_a = isset($totals_row[8]) ? (int)$totals_row[8] : 0;
                        $penalty_b = isset($totals_row[9]) ? (int)$totals_row[9] : 0;
                        $penalty_c = isset($totals_row[10]) ? (int)$totals_row[10] : 0;
                        $penalty_d = isset($totals_row[11]) ? (int)$totals_row[11] : 0;
                        
                        // Räkna ut totala minipoäng (inklusive penalties)
                        $total_mp_a = $mp_a + $penalty_a;
                        $total_mp_b = $mp_b + $penalty_b;
                        $total_mp_c = $mp_c + $penalty_c;
                        $total_mp_d = $mp_d + $penalty_d;
                        
                        // Räkna ut bordspoäng
                        $mp_values = [
                            'A' => $total_mp_a,
                            'B' => $total_mp_b,
                            'C' => $total_mp_c,
                            'D' => $total_mp_d
                        ];
                        arsort($mp_values);
                        $positions = array_keys($mp_values);
                        
                        $bp = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
                        $bp[$positions[0]] = 4; // 1:a plats
                        $bp[$positions[1]] = 2; // 2:a plats
                        $bp[$positions[2]] = 1; // 3:e plats
                        $bp[$positions[3]] = 0; // 4:e plats
                        
                        $preview_data = [
                            'game_date' => $game_date,
                            'game_name' => $game_name,
                            'hands' => $hands,
                            'max_hand_points' => $max_hand_points,
                            'totals' => [
                                'A' => ['mp' => $total_mp_a, 'bp' => $bp['A']],
                                'B' => ['mp' => $total_mp_b, 'bp' => $bp['B']],
                                'C' => ['mp' => $total_mp_c, 'bp' => $bp['C']],
                                'D' => ['mp' => $total_mp_d, 'bp' => $bp['D']]
                            ]
                        ];
                    } else {
                        $error = t('import_error_csv_totals');
                        $step = 1;
                    }
                }
            }
        }
    }
}

// Hämta alla spelare för dropdown
$stmt = $conn->query("SELECT id, first_name, last_name FROM stat_players WHERE id IS NOT NULL AND id NOT IN (1, 2) ORDER BY last_name, first_name");
$all_players = $stmt->fetchAll();

includeHeader();
?>

<h1>📥 <?php echo t('import_title'); ?></h1>

<?php if ($error): ?>
<div class="message error">
    ❌ <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($step === 1): ?>
    <!-- STEG 1: Välj datum, matchnamn och spelare -->
    <div style="max-width: 800px;">
        <div style="background: #e3f2fd; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h3 style="margin-top: 0;">📋 <?php echo t('import_info_title'); ?></h3>
            <p><?php echo t('import_info_desc'); ?></p>
            <ol style="line-height: 1.8;">
                <li><?php echo t('import_step1'); ?></li>
                <li><?php echo t('import_step2'); ?></li>
                <li><?php echo t('import_step3'); ?></li>
                <li><?php echo t('import_step4'); ?></li>
                <li><?php echo t('import_step5'); ?></li>
            </ol>
            <p><strong>💡 <?php echo t('import_tip_label'); ?>:</strong> <?php echo t('import_tip'); ?></p>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="step" value="2">
            
            <div class="form-group">
                <label><?php echo t('import_label_date'); ?> *</label>
                <input type="date" name="game_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="form-group">
                <label><?php echo t('import_label_name'); ?></label>
                <input type="text" name="game_name" placeholder="<?php echo t('import_name_placeholder'); ?>">
                <small><?php echo t('import_name_hint'); ?></small>
            </div>
            
            <h3><?php echo t('import_select_players'); ?></h3>
            
            <div class="form-group">
                <label><?php echo t('import_player_a'); ?> *</label>
                <select name="player_a_id" id="player_a_id" required onchange="filterPlayers()">
                    <option value=""><?php echo t('import_select_player_opt'); ?></option>
                    <?php foreach ($all_players as $player): ?>
                        <option value="<?php echo $player['id']; ?>">
                            <?php echo htmlspecialchars($player['id'] . ' - ' . $player['first_name'] . ' ' . $player['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label><?php echo t('import_player_b'); ?> *</label>
                <select name="player_b_id" id="player_b_id" required onchange="filterPlayers()">
                    <option value=""><?php echo t('import_select_player_opt'); ?></option>
                    <?php foreach ($all_players as $player): ?>
                        <option value="<?php echo $player['id']; ?>">
                            <?php echo htmlspecialchars($player['id'] . ' - ' . $player['first_name'] . ' ' . $player['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label><?php echo t('import_player_c'); ?> *</label>
                <select name="player_c_id" id="player_c_id" required onchange="filterPlayers()">
                    <option value=""><?php echo t('import_select_player_opt'); ?></option>
                    <?php foreach ($all_players as $player): ?>
                        <option value="<?php echo $player['id']; ?>">
                            <?php echo htmlspecialchars($player['id'] . ' - ' . $player['first_name'] . ' ' . $player['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label><?php echo t('import_player_d'); ?> *</label>
                <select name="player_d_id" id="player_d_id" required onchange="filterPlayers()">
                    <option value=""><?php echo t('import_select_player_opt'); ?></option>
                    <?php foreach ($all_players as $player): ?>
                        <option value="<?php echo $player['id']; ?>">
                            <?php echo htmlspecialchars($player['id'] . ' - ' . $player['first_name'] . ' ' . $player['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <h3><?php echo t('import_upload_title'); ?></h3>
            
            <div class="form-group">
                <label><?php echo t('import_upload_label'); ?> *</label>
                <input type="file" name="csv_file" accept=".csv" required>
                <small><?php echo t('import_upload_hint'); ?></small>
            </div>
            
            <div style="margin-top: 30px;">
                <button type="submit" class="btn"><?php echo t('import_btn_next'); ?></button>
                <a href="games.php" class="btn btn-secondary"><?php echo t('btn_cancel'); ?></a>
            </div>
        </form>
    </div>

<?php elseif ($step === 2 && $preview_data): ?>
    <!-- STEG 2: Förhandsgranska och skicka till newgame.php -->
    <div style="background: #fff3cd; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
        <h3 style="margin-top: 0;">👁️ <?php echo t('import_preview_title'); ?></h3>
        <p><?php echo t('import_preview_desc'); ?></p>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
            <strong><?php echo t('import_col_date'); ?>:</strong><br>
            <?php echo htmlspecialchars($preview_data['game_date']); ?>
        </div>
        <?php if ($preview_data['game_name']): ?>
        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
            <strong><?php echo t('import_col_name'); ?>:</strong><br>
            <?php echo htmlspecialchars($preview_data['game_name']); ?>
        </div>
        <?php endif; ?>
        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
            <strong><?php echo t('import_col_hands'); ?>:</strong><br>
            <?php echo count($preview_data['hands']); ?>
        </div>
        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
            <strong><?php echo t('import_col_biggest'); ?>:</strong><br>
            <?php echo $preview_data['max_hand_points']; ?> <?php echo t('stats_pub_points'); ?>
        </div>
    </div>
    
    <h3><?php echo t('import_player_mapping'); ?></h3>
    <table style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th><?php echo t('import_th_position'); ?></th>
                <th><?php echo t('import_th_in_file'); ?></th>
                <th><?php echo t('import_th_vms_player'); ?></th>
                <th><?php echo t('import_th_mp'); ?></th>
                <th><?php echo t('import_th_bp'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (['A', 'B', 'C', 'D'] as $pos): ?>
            <tr>
                <td><strong><?php echo t('import_player_label') . ' ' . $pos; ?></strong></td>
                <td><?php echo htmlspecialchars($player_mapping[$pos]['file_name']); ?></td>
                <td><?php echo htmlspecialchars($player_mapping[$pos]['vms'] . ' - ' . $player_mapping[$pos]['name']); ?></td>
                <td><?php echo $preview_data['totals'][$pos]['mp']; ?></td>
                <td><?php echo $preview_data['totals'][$pos]['bp']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <h3><?php echo t('import_hands_title') . ' (' . count($preview_data['hands']) . ' ' . t('stats_pub_hands') . ')'; ?></h3>
    <div style="overflow-x: auto; margin-bottom: 20px;">
        <table>
            <thead>
                <tr>
                    <th><?php echo t('import_th_hand'); ?></th>
                    <th><?php echo t('import_th_winner'); ?></th>
                    <th><?php echo t('import_th_from'); ?></th>
                    <th><?php echo t('import_th_points'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($preview_data['hands'] as $hand): ?>
                <tr>
                    <td><?php echo $hand['hand']; ?></td>
                    <td><?php 
                        $winners = ['', 'A', 'B', 'C', 'D'];
                        echo t('import_player_label') . ' ' . $winners[$hand['winner']]; 
                    ?></td>
                    <td><?php 
                        if ($hand['discarder'] === 0) {
                            echo t('import_self_drawn');
                        } else {
                            echo t('import_player_label') . ' ' . $winners[$hand['discarder']];
                        }
                    ?></td>
                    <td><?php echo $hand['points']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <form action="newgame.php" method="POST">
        <!-- Skicka all data till newgame.php -->
        <input type="hidden" name="import_mode" value="1">
        <input type="hidden" name="game_date" value="<?php echo htmlspecialchars($preview_data['game_date']); ?>">
        <input type="hidden" name="game_name" value="<?php echo htmlspecialchars($preview_data['game_name']); ?>">
        
        <input type="hidden" name="player_a" value="<?php echo htmlspecialchars($player_mapping['A']['vms']); ?>">
        <input type="hidden" name="player_b" value="<?php echo htmlspecialchars($player_mapping['B']['vms']); ?>">
        <input type="hidden" name="player_c" value="<?php echo htmlspecialchars($player_mapping['C']['vms']); ?>">
        <input type="hidden" name="player_d" value="<?php echo htmlspecialchars($player_mapping['D']['vms']); ?>">
        
        <input type="hidden" name="biggest_hand" value="<?php echo $preview_data['max_hand_points']; ?>">
        
        <?php foreach ($preview_data['hands'] as $hand): ?>
            <input type="hidden" name="hand_<?php echo $hand['hand']; ?>_winner" value="<?php echo $hand['winner']; ?>">
            <input type="hidden" name="hand_<?php echo $hand['hand']; ?>_discarder" value="<?php echo $hand['discarder']; ?>">
            <input type="hidden" name="hand_<?php echo $hand['hand']; ?>_points" value="<?php echo $hand['points']; ?>">
        <?php endforeach; ?>
        
        <div style="margin-top: 30px;">
            <button type="submit" class="btn" style="font-size: 1.1em; padding: 15px 30px;">
                <?php echo t('import_btn_confirm'); ?>
            </button>
            <a href="import-game.php" class="btn btn-secondary"><?php echo t('btn_back'); ?></a>
        </div>
    </form>
<?php endif; ?>

<script>
function filterPlayers() {
    const selectedPlayers = [
        document.getElementById('player_a_id')?.value || '',
        document.getElementById('player_b_id')?.value || '',
        document.getElementById('player_c_id')?.value || '',
        document.getElementById('player_d_id')?.value || ''
    ].filter(v => v !== '');
    
    ['player_a_id', 'player_b_id', 'player_c_id', 'player_d_id'].forEach(function(selectId) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        const currentValue = select.value;
        
        Array.from(select.options).forEach(function(option) {
            if (option.value === '') {
                option.disabled = false;
                return;
            }
            
            // Disabla om spelaren är vald i en annan dropdown (men inte denna)
            if (selectedPlayers.includes(option.value) && option.value !== currentValue) {
                option.disabled = true;
                option.style.color = '#ccc';
            } else {
                option.disabled = false;
                option.style.color = '';
            }
        });
    });
}

// Kör vid sidladdning
document.addEventListener('DOMContentLoaded', filterPlayers);
</script>

<?php includeFooter(); ?>
