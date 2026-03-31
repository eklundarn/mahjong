<?php
require_once 'config.php';

// Kräv superuser eller admin
if (!hasRole('superuser')) {
    showError('Du måste vara superuser eller admin för att redigera matcher.');
}

$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($game_id <= 0) {
    showError('Ogiltigt match-id.');
}

$conn = getDbConnection();
$success = '';
$error = '';

// Hämta matcher med skapare och godkännare info
$stmt = $conn->prepare("
    SELECT g.*,
        creator.first_name as creator_first, creator.last_name as creator_last,
        approver.first_name as approver_first, approver.last_name as approver_last
    FROM stat_games g
    LEFT JOIN stat_players creator ON g.created_by_id = creator.id
    LEFT JOIN stat_players approver ON g.approved_by_id = approver.id
    WHERE g.id = ?
");
$stmt->execute([$game_id]);
$game = $stmt->fetch();

if (!$game) {
    showError('Match hittades inte.');
}

// Hämta händer om detaljerad match
$hands = [];
if ($game['detailed_entry']) {
    $stmt = $conn->prepare("SELECT * FROM stat_game_hands WHERE game_id = ? ORDER BY hand_number ASC");
    $stmt->execute([$game_id]);
    $hands_array = $stmt->fetchAll();
    foreach ($hands_array as $hand) {
        $hands[$hand['hand_number']] = $hand;
    }
}

// Hämta alla spelare för dropdowns
$player_sort = isset($_GET['player_sort']) ? cleanInput($_GET['player_sort']) : 'name';
if ($player_sort === 'number') {
    $stmt = $conn->query("SELECT id, first_name, last_name FROM stat_players ORDER BY id");
} else {
    $stmt = $conn->query("SELECT id, first_name, last_name FROM stat_players ORDER BY last_name, first_name");
}
$all_players = $stmt->fetchAll();

// Hantera uppdatering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_game'])) {
    
    $game_date = cleanInput($_POST['game_date']);
    $game_name = cleanInput($_POST['game_name'] ?? '');
    $game_number = (int)$_POST['game_number'];
    
    $player_a = cleanInput($_POST['player_a']);
    $player_b = cleanInput($_POST['player_b']);
    $player_c = cleanInput($_POST['player_c']);
    $player_d = cleanInput($_POST['player_d']);
    
    // Hantera godkännande (endast för admin)
    $approve_game = isset($_POST['approve_game']) && $_POST['approve_game'] == '1';
    $was_approved = $game['approved'] == 1;
    $now_approved = $was_approved || ($approve_game && hasRole('admin'));
    
    // Validering
    $errors = [];
    
    if (empty($game_date)) $errors[] = "Datum måste anges.";
    if ($game_number < 1) $errors[] = "Matchnummer måste vara minst 1.";
    if (empty($player_a) || empty($player_b) || empty($player_c) || empty($player_d)) {
        $errors[] = "Alla fyra spelare måste väljas.";
    }
    
    $selected_players = [$player_a, $player_b, $player_c, $player_d];
    if (count($selected_players) !== count(array_unique($selected_players))) {
        $errors[] = "Samma spelare kan inte vara med två gånger i samma match.";
    }
    
    if ($game['detailed_entry']) {
        // ALTERNATIV B - Hand-för-hand (samma logik som newgame-b.php)
        $penalty_a = (int)($_POST['penalty_a'] ?? 0);
        $penalty_b = (int)($_POST['penalty_b'] ?? 0);
        $penalty_c = (int)($_POST['penalty_c'] ?? 0);
        $penalty_d = (int)($_POST['penalty_d'] ?? 0);
        
        $false_hu_a = (int)($_POST['false_hu_a'] ?? 0);
        $false_hu_b = (int)($_POST['false_hu_b'] ?? 0);
        $false_hu_c = (int)($_POST['false_hu_c'] ?? 0);
        $false_hu_d = (int)($_POST['false_hu_d'] ?? 0);
        
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        } else {
            // Validera att false hu summerar till 0
            $false_hu_sum = $false_hu_a + $false_hu_b + $false_hu_c + $false_hu_d;
            if ($false_hu_sum !== 0) {
                $errors[] = "False Hu måste summera till 0 (nollsummespel). Nu: $false_hu_sum.";
            }
        }
        
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        } else {
            // Initiera variabler
            $hand_points = [0, 0, 0, 0];
            $biggest_hand = 0;
            $biggest_hand_player = null;
            
            $antal_hander = 0; // Räkna antal spelade händer
            
            // Initiera hu-statistik
            $hu_stats = [
                [0, 0, 0],  // Spelare A: [hu, selfdrawn, thrown_hu]
                [0, 0, 0],  // Spelare B
                [0, 0, 0],  // Spelare C
                [0, 0, 0]   // Spelare D
            ];
            $zero_rounds = 0;  // Antal 0-rundor
            
            for ($h = 1; $h <= 16; $h++) {
                $hu_player_raw = $_POST["hand_{$h}_player"] ?? '';
                $from_player_value = $_POST["hand_{$h}_from"] ?? '';

                // Kolla om det är en 0-runda
                if ($hu_player_raw === 'zero') {
                    $zero_rounds++;
                    $hands_data[$h] = ['hu_points' => 0, 'hu_player' => 0, 'from_player' => -1, 'null_round' => true];
                    continue;
                }

                $hu_points = (int)($_POST["hand_{$h}_hu"] ?? 0);
                $hu_player = (int)$hu_player_raw;
                $from_player = $from_player_value === '' ? -1 : (int)$from_player_value;
                
                if ($hu_points > 0 && $hu_player >= 1 && $hu_player <= 4 && $from_player >= 0) {
                    $antal_hander++; // Räkna denna hand
                    
                    // Räkna hu-statistik
                    $winner_idx = $hu_player - 1;
                    $hu_stats[$winner_idx][0]++; // Vinnaren fick ett hu
                    
                    if ($from_player === 0) {
                        // Självdragen
                        $hu_stats[$winner_idx][1]++; // selfdrawn
                    } else {
                        // Någon annan kastade
                        $thrower_idx = $from_player - 1;
                        $hu_stats[$thrower_idx][2]++; // Den som kastade gav ett hu (thrown_hu)
                    }
                    
                    if ($hu_points > $biggest_hand) {
                        $biggest_hand = $hu_points;
                        $biggest_hand_player = $selected_players[$hu_player - 1];
                    }
                    
                    $winner_idx = $hu_player - 1;
                    
                    if ($from_player === 0) {
                        for ($i = 0; $i < 4; $i++) {
                            if ($i === $winner_idx) {
                                $hand_points[$i] += (8 + $hu_points) * 3;
                            } else {
                                $hand_points[$i] -= (8 + $hu_points);
                            }
                        }
                    } else {
                        $thrower_idx = $from_player - 1;
                        for ($i = 0; $i < 4; $i++) {
                            if ($i === $winner_idx) {
                                $hand_points[$i] += 8 + 8 + (8 + $hu_points);
                            } elseif ($i === $thrower_idx) {
                                $hand_points[$i] -= (8 + $hu_points);
                            } else {
                                $hand_points[$i] -= 8;
                            }
                        }
                    }
                    
                    $hands_data[$h] = [
                        'hu_points' => $hu_points,
                        'hu_player' => $hu_player,
                        'from_player' => $from_player
                    ];
                }
            }
            
            // Applicera straff (alltid minus, påverkar bara den spelaren)
            $hand_points[0] -= abs($penalty_a);
            $hand_points[1] -= abs($penalty_b);
            $hand_points[2] -= abs($penalty_c);
            $hand_points[3] -= abs($penalty_d);
            
            // Applicera False Hu (påverkar bara den spelaren, sprids INTE)
            $hand_points[0] += $false_hu_a;
            $hand_points[1] += $false_hu_b;
            $hand_points[2] += $false_hu_c;
            $hand_points[3] += $false_hu_d;
            
            // Beräkna bordspoäng
            $players_data = [
                ['vms' => $player_a, 'mini' => $hand_points[0], 'idx' => 0],
                ['vms' => $player_b, 'mini' => $hand_points[1], 'idx' => 1],
                ['vms' => $player_c, 'mini' => $hand_points[2], 'idx' => 2],
                ['vms' => $player_d, 'mini' => $hand_points[3], 'idx' => 3]
            ];
            
            usort($players_data, function($a, $b) {
                return $b['mini'] - $a['mini'];
            });
            
            $table_points_base = [4, 2, 1, 0];
            $table_points = [0, 0, 0, 0];
            
            for ($i = 0; $i < 4; $i++) {
                $same_count = 1;
                $sum_points = $table_points_base[$i];
                
                for ($j = $i + 1; $j < 4; $j++) {
                    if ($players_data[$j]['mini'] === $players_data[$i]['mini']) {
                        $same_count++;
                        $sum_points += $table_points_base[$j];
                    } else {
                        break;
                    }
                }
                
                $shared_points = $sum_points / $same_count;
                for ($k = 0; $k < $same_count; $k++) {
                    $table_points[$players_data[$i + $k]['idx']] = $shared_points;
                }
                $i += ($same_count - 1);
            }
            
            $table_a = $table_points[0];
            $table_b = $table_points[1];
            $table_c = $table_points[2];
            $table_d = $table_points[3];
            
            try {
                $conn->beginTransaction();
                
                $game_year = date('Y', strtotime($game_date));
                
                $stmt = $conn->prepare("
                    UPDATE stat_games SET
                        game_number = ?, game_name = ?, game_date = ?, game_year = ?,
                        biggest_hand_points = ?, biggest_hand_player_id = ?, zero_rounds = ?,
                        player_a_id = ?, player_a_minipoints = ?, player_a_tablepoints = ?, player_a_penalties = ?, player_a_false_hu = ?,
                        player_a_hu = ?, player_a_selfdrawn = ?, player_a_thrown_hu = ?,
                        player_b_id = ?, player_b_minipoints = ?, player_b_tablepoints = ?, player_b_penalties = ?, player_b_false_hu = ?,
                        player_b_hu = ?, player_b_selfdrawn = ?, player_b_thrown_hu = ?,
                        player_c_id = ?, player_c_minipoints = ?, player_c_tablepoints = ?, player_c_penalties = ?, player_c_false_hu = ?,
                        player_c_hu = ?, player_c_selfdrawn = ?, player_c_thrown_hu = ?,
                        player_d_id = ?, player_d_minipoints = ?, player_d_tablepoints = ?, player_d_penalties = ?, player_d_false_hu = ?,
                        player_d_hu = ?, player_d_selfdrawn = ?, player_d_thrown_hu = ?,
                        approved = ?, approved_by_id = ?, approved_at = ?
                    WHERE id = ?
                ");
                
                $approved_by = ($now_approved && !$was_approved) ? ($_SESSION['user_id'] ?? null) : $game['approved_by_id'];
                $approved_at = ($now_approved && !$was_approved) ? date('Y-m-d H:i:s') : $game['approved_at'];
                
                $stmt->execute([
                    $game_number, $game_name, $game_date, $game_year,
                    $biggest_hand, $biggest_hand_player, $zero_rounds,
                    $player_a, $hand_points[0], $table_a, $penalty_a, $false_hu_a,
                    $hu_stats[0][0], $hu_stats[0][1], $hu_stats[0][2],
                    $player_b, $hand_points[1], $table_b, $penalty_b, $false_hu_b,
                    $hu_stats[1][0], $hu_stats[1][1], $hu_stats[1][2],
                    $player_c, $hand_points[2], $table_c, $penalty_c, $false_hu_c,
                    $hu_stats[2][0], $hu_stats[2][1], $hu_stats[2][2],
                    $player_d, $hand_points[3], $table_d, $penalty_d, $false_hu_d,
                    $hu_stats[3][0], $hu_stats[3][1], $hu_stats[3][2],
                    $now_approved ? 1 : 0,
                    $approved_by,
                    $approved_at,
                    $game_id
                ]);
                
                // Radera gamla händer
                $stmt = $conn->prepare("DELETE FROM stat_game_hands WHERE game_id = ?");
                $stmt->execute([$game_id]);
                
                // Spara nya händer
                foreach ($hands_data as $hand_num => $hand) {
                    $hp = [0, 0, 0, 0];

                    // Nollrunda – spara med nollor
                    if (!empty($hand['null_round'])) {
                        $stmt = $conn->prepare("
                            INSERT INTO stat_game_hands
                            (game_id, hand_number, hu_points, winning_player, from_player,
                             player_a_points, player_b_points, player_c_points, player_d_points)
                            VALUES (?, ?, 0, 0, -1, 0, 0, 0, 0)
                        ");
                        $stmt->execute([$game_id, $hand_num]);
                        continue;
                    }

                    $winner = $hand['hu_player'] - 1;

                    if ($hand['from_player'] === 0) {
                        for ($i = 0; $i < 4; $i++) {
                            if ($i === $winner) {
                                $hp[$i] = ($hand['hu_points'] + 8) * 3;
                            } else {
                                $hp[$i] = -($hand['hu_points'] + 8);
                            }
                        }
                    } else {
                        $thrower = $hand['from_player'] - 1;
                        for ($i = 0; $i < 4; $i++) {
                            if ($i === $winner) {
                                $hp[$i] = 8 + 8 + (8 + $hand['hu_points']);
                            } elseif ($i === $thrower) {
                                $hp[$i] = -(8 + $hand['hu_points']);
                            } else {
                                $hp[$i] = -8;
                            }
                        }
                    }
                    
                    $stmt = $conn->prepare("
                        INSERT INTO stat_game_hands 
                        (game_id, hand_number, hu_points, winning_player, from_player,
                         player_a_points, player_b_points, player_c_points, player_d_points)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $game_id, $hand_num, $hand['hu_points'], $hand['hu_player'], $hand['from_player'],
                        $hp[0], $hp[1], $hp[2], $hp[3]
                    ]);
                }
                
                $conn->commit();
                
                $success = "Match #$game_number har uppdaterats!";
                
                // Uppdatera $game och $hands
                $stmt = $conn->prepare("SELECT * FROM stat_games WHERE id = ?");
                $stmt->execute([$game_id]);
                $game = $stmt->fetch();
                
                $stmt = $conn->prepare("SELECT * FROM stat_game_hands WHERE game_id = ? ORDER BY hand_number ASC");
                $stmt->execute([$game_id]);
                $hands_array = $stmt->fetchAll();
                $hands = [];
                foreach ($hands_array as $hand) {
                    $hands[$hand['hand_number']] = $hand;
                }
                
            } catch (PDOException $e) {
                $conn->rollBack();
                if ($e->getCode() == 23000) {
                    $error = "Matchnummer $game_number används redan.";
                } else {
                    $error = "Kunde inte uppdatera: " . $e->getMessage();
                }
            }
        }
        
    } else {
        // ALTERNATIV A - Slutresultat (med penalties och false hu)
        $mini_a = (int)$_POST['minipoints_a'];
        $mini_b = (int)$_POST['minipoints_b'];
        $mini_c = (int)$_POST['minipoints_c'];
        $mini_d = (int)$_POST['minipoints_d'];
        
        $penalty_a = (int)($_POST['penalty_a'] ?? 0);
        $penalty_b = (int)($_POST['penalty_b'] ?? 0);
        $penalty_c = (int)($_POST['penalty_c'] ?? 0);
        $penalty_d = (int)($_POST['penalty_d'] ?? 0);
        
        $false_hu_a = (int)($_POST['false_hu_a'] ?? 0);
        $false_hu_b = (int)($_POST['false_hu_b'] ?? 0);
        $false_hu_c = (int)($_POST['false_hu_c'] ?? 0);
        $false_hu_d = (int)($_POST['false_hu_d'] ?? 0);
        
        // Läs hu-statistik
        $zero_rounds = (int)($_POST['zero_rounds'] ?? 0);
        
        $hu_a = (int)($_POST['hu_a'] ?? 0);
        $hu_b = (int)($_POST['hu_b'] ?? 0);
        $hu_c = (int)($_POST['hu_c'] ?? 0);
        $hu_d = (int)($_POST['hu_d'] ?? 0);
        
        $selfdrawn_a = (int)($_POST['selfdrawn_a'] ?? 0);
        $selfdrawn_b = (int)($_POST['selfdrawn_b'] ?? 0);
        $selfdrawn_c = (int)($_POST['selfdrawn_c'] ?? 0);
        $selfdrawn_d = (int)($_POST['selfdrawn_d'] ?? 0);
        
        $thrown_hu_a = (int)($_POST['thrown_hu_a'] ?? 0);
        $thrown_hu_b = (int)($_POST['thrown_hu_b'] ?? 0);
        $thrown_hu_c = (int)($_POST['thrown_hu_c'] ?? 0);
        $thrown_hu_d = (int)($_POST['thrown_hu_d'] ?? 0);
        
        $biggest_hand = (int)$_POST['biggest_hand'];
        $biggest_hand_player = cleanInput($_POST['biggest_hand_player']);
        
        // Validera nollsummespel FÖRE penalties och false hu
        $total = $mini_a + $mini_b + $mini_c + $mini_d;
        if ($total !== 0) {
            $errors[] = "Minipoängen (före penalties/false hu) måste summera till 0. Nu: $total.";
        }
        
        // Validera false hu summerar till 0
        $false_hu_sum = $false_hu_a + $false_hu_b + $false_hu_c + $false_hu_d;
        if ($false_hu_sum !== 0) {
            $errors[] = "False Hu måste summera till 0 (nollsummespel). Nu: $false_hu_sum.";
        }
        
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        } else {
            // Beräkna bordspoäng (baserat på minipoäng FÖRE adjustments)
            $players_data = [
                ['vms' => $player_a, 'mini' => $mini_a],
                ['vms' => $player_b, 'mini' => $mini_b],
                ['vms' => $player_c, 'mini' => $mini_c],
                ['vms' => $player_d, 'mini' => $mini_d]
            ];
            
            usort($players_data, function($a, $b) {
                return $b['mini'] - $a['mini'];
            });
            
            $table_points_arr = [4, 2, 1, 0];
            
            for ($i = 0; $i < 4; $i++) {
                $same_count = 1;
                $sum_points = $table_points_arr[$i];
                
                for ($j = $i + 1; $j < 4; $j++) {
                    if ($players_data[$j]['mini'] === $players_data[$i]['mini']) {
                        $same_count++;
                        $sum_points += $table_points_arr[$j];
                    } else {
                        break;
                    }
                }
                
                $shared_points = $sum_points / $same_count;
                
                for ($k = 0; $k < $same_count; $k++) {
                    $players_data[$i + $k]['table'] = $shared_points;
                }
                
                $i += ($same_count - 1);
            }
            
            $table_a = 0; $table_b = 0; $table_c = 0; $table_d = 0;
            foreach ($players_data as $p) {
                if ($p['vms'] === $player_a) $table_a = $p['table'];
                if ($p['vms'] === $player_b) $table_b = $p['table'];
                if ($p['vms'] === $player_c) $table_c = $p['table'];
                if ($p['vms'] === $player_d) $table_d = $p['table'];
            }
            
            try {
                $game_year = date('Y', strtotime($game_date));
                
                // Beräkna slutgiltiga minipoäng
                $final_mini_a = $mini_a + $false_hu_a - $penalty_a;
                $final_mini_b = $mini_b + $false_hu_b - $penalty_b;
                $final_mini_c = $mini_c + $false_hu_c - $penalty_c;
                $final_mini_d = $mini_d + $false_hu_d - $penalty_d;
                
                $stmt = $conn->prepare("
                    UPDATE stat_games SET
                        game_number = ?, game_name = ?, game_date = ?, game_year = ?,
                        biggest_hand_points = ?, biggest_hand_player_id = ?,
                        player_a_id = ?, player_a_minipoints = ?, player_a_tablepoints = ?, player_a_penalties = ?, player_a_false_hu = ?,
                        player_b_id = ?, player_b_minipoints = ?, player_b_tablepoints = ?, player_b_penalties = ?, player_b_false_hu = ?,
                        player_c_id = ?, player_c_minipoints = ?, player_c_tablepoints = ?, player_c_penalties = ?, player_c_false_hu = ?,
                        player_d_id = ?, player_d_minipoints = ?, player_d_tablepoints = ?, player_d_penalties = ?, player_d_false_hu = ?,
                        approved = ?, approved_by_id = ?, approved_at = ?
                    WHERE id = ?
                ");
                
                $approved_by = ($now_approved && !$was_approved) ? ($_SESSION['user_id'] ?? null) : $game['approved_by_id'];
                $approved_at = ($now_approved && !$was_approved) ? date('Y-m-d H:i:s') : $game['approved_at'];
                
                $stmt->execute([
                    $game_number, $game_name, $game_date, $game_year,
                    $biggest_hand, $biggest_hand_player, $zero_rounds,
                    $player_a, $final_mini_a, $table_a, $penalty_a, $false_hu_a,
                    $hu_a, $selfdrawn_a, $thrown_hu_a,
                    $player_b, $final_mini_b, $table_b, $penalty_b, $false_hu_b,
                    $hu_b, $selfdrawn_b, $thrown_hu_b,
                    $player_c, $final_mini_c, $table_c, $penalty_c, $false_hu_c,
                    $hu_c, $selfdrawn_c, $thrown_hu_c,
                    $player_d, $final_mini_d, $table_d, $penalty_d, $false_hu_d,
                    $hu_d, $selfdrawn_d, $thrown_hu_d,
                    $now_approved ? 1 : 0,
                    $approved_by,
                    $approved_at,
                    $game_id
                ]);
                
                $success = "Match #$game_number har uppdaterats!";
                
                $stmt = $conn->prepare("SELECT * FROM stat_games WHERE id = ?");
                $stmt->execute([$game_id]);
                $game = $stmt->fetch();
                
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Matchnummer $game_number används redan.";
                } else {
                    $error = "Kunde inte uppdatera: " . $e->getMessage();
                }
            }
        }
    }
}

// Nu följer HTML-output beroende på typ
includeHeader();

if ($game['detailed_entry']) {
    // Visa hand-för-hand formulär (kopierat från den include-filen jag skapade tidigare)
    include 'includes/edit-game-detailed.php';
} else {
    // Visa slutresultat formulär
    include 'includes/edit-game-simple.php';
}

includeFooter();
?>