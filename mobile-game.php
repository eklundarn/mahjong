<?php
require_once 'config.php';

if (!hasRole('player')) {
    showError('Du måste ha registrator-behörighet eller högre för att registrera matcher.');
}

$conn = getDbConnection();

// Hämta alla aktiva spelare för autocomplete
$stmt = $conn->query("
    SELECT id, first_name, last_name 
    FROM stat_players 
    WHERE club_member = 1 AND id NOT IN ('000000', '999999') 
    ORDER BY first_name, last_name
");
$all_players = $stmt->fetchAll();

// Hantera AJAX-spara
$_raw_input = file_get_contents('php://input');
$_json_data = json_decode($_raw_input, true);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_json_data['action']) && $_json_data['action'] === 'save_game') {
    header('Content-Type: application/json');
    
    $data = $_json_data;
    
    try {
        $player_a = $data['stat_players'][0]['vms'];
        $player_b = $data['stat_players'][1]['vms'];
        $player_c = $data['stat_players'][2]['vms'];
        $player_d = $data['stat_players'][3]['vms'];
        $game_date = $data['game_date'];
        $game_name = trim($data['game_name'] ?? '');
        $hands = $data['hands']; // array of {hu_points, winner (0-3), from (-1=self, 0-3)}
        $penalties = $data['penalties']; // [{player_idx, amount}]

        // Validering
        $selected = [$player_a, $player_b, $player_c, $player_d];
        if (count($selected) !== count(array_unique($selected))) {
            echo json_encode(['success' => false, 'error' => 'Samma spelare kan inte vara med två gånger.']);
            exit;
        }

        // Beräkna poäng
        $hand_points = [0, 0, 0, 0];
        $hu_received  = [0, 0, 0, 0];
        $hu_given     = [0, 0, 0, 0];
        $self_drawn   = [0, 0, 0, 0];
        $biggest_hand = 0;
        $biggest_hand_players = [];
        $hands_data = [];
        $antal_hander = 0;
        $zero_rounds = 0;

        foreach ($hands as $idx => $hand) {
            $hp   = (int)$hand['hu_points'];
            $win  = (int)$hand['winner'];   // 0-3
            $from = (int)$hand['from'];     // -1=self, 0-3

            if ($hp <= 0) {
                // Noll-runda - räknas som spelad men ger 0 poäng
                $antal_hander++;
                $zero_rounds++;
                $hands_data[] = ['hp' => 0, 'winner' => 0, 'from' => 0, 'null_round' => true];
                continue;
            }
            $antal_hander++;
            $hu_received[$win]++;

            if ($hp > $biggest_hand) {
                $biggest_hand = $hp;
                $biggest_hand_players = [$selected[$win]];
            } elseif ($hp === $biggest_hand) {
                if (!in_array($selected[$win], $biggest_hand_players))
                    $biggest_hand_players[] = $selected[$win];
            }

            if ($from === -1) {
                // Självdragen
                $self_drawn[$win]++;
                for ($i = 0; $i < 4; $i++) {
                    $hand_points[$i] += ($i === $win) ? ($hp + 8) * 3 : -($hp + 8);
                }
                $hands_data[] = ['hp' => $hp, 'winner' => $win + 1, 'from' => 0];
            } else {
                // Kastas av from
                $hu_given[$from]++;
                for ($i = 0; $i < 4; $i++) {
                    if ($i === $win)       $hand_points[$i] += 8 + 8 + (8 + $hp);
                    elseif ($i === $from)  $hand_points[$i] -= (8 + $hp);
                    else                   $hand_points[$i] -= 8;
                }
                $hands_data[] = ['hp' => $hp, 'winner' => $win + 1, 'from' => $from + 1];
            }
        }

        // Applicera penalties
        $penalty_totals = [0, 0, 0, 0];
        foreach ($penalties as $pen) {
            $pidx = (int)$pen['player_idx'];
            $amt  = (int)$pen['amount'];
            $penalty_totals[$pidx] += $amt;
            $hand_points[$pidx] -= $amt;
        }

        // Bordspoäng
        $players_sorted = [
            ['vms' => $player_a, 'mini' => $hand_points[0], 'idx' => 0],
            ['vms' => $player_b, 'mini' => $hand_points[1], 'idx' => 1],
            ['vms' => $player_c, 'mini' => $hand_points[2], 'idx' => 2],
            ['vms' => $player_d, 'mini' => $hand_points[3], 'idx' => 3],
        ];
        usort($players_sorted, fn($a, $b) => $b['mini'] - $a['mini']);
        $bp_base = [4, 2, 1, 0];
        $table_pts = [0, 0, 0, 0];
        for ($i = 0; $i < 4; $i++) {
            $same = 1; $sum = $bp_base[$i];
            for ($j = $i + 1; $j < 4; $j++) {
                if ($players_sorted[$j]['mini'] === $players_sorted[$i]['mini']) { $same++; $sum += $bp_base[$j]; }
                else break;
            }
            $shared = $sum / $same;
            for ($k = 0; $k < $same; $k++) $table_pts[$players_sorted[$i + $k]['idx']] = $shared;
            $i += ($same - 1);
        }

        $conn->beginTransaction();
        $game_year = date('Y', strtotime($game_date));
        $stmt2 = $conn->prepare("SELECT COALESCE(MAX(game_number),0)+1 as n FROM stat_games WHERE game_year=? FOR UPDATE");
        $stmt2->execute([$game_year]);
        $game_number = $stmt2->fetch()['n'];

        $approved = canApproveGames() ? 1 : 0;
        $biggest_hand_player_str = implode(',', $biggest_hand_players);

        $stmt3 = $conn->prepare("
            INSERT INTO stat_games (
                game_number, game_name, game_date, game_year,
                biggest_hand_points, biggest_hand_player_id, hands_played, zero_rounds,
                detailed_entry, approved, created_by_id,
                player_a_id, player_a_minipoints, player_a_tablepoints, player_a_penalties, player_a_false_hu,
                player_a_hu, player_a_selfdrawn, player_a_thrown_hu, player_a_hu_given, player_a_self_drawn,
                player_b_id, player_b_minipoints, player_b_tablepoints, player_b_penalties, player_b_false_hu,
                player_b_hu, player_b_selfdrawn, player_b_thrown_hu, player_b_hu_given, player_b_self_drawn,
                player_c_id, player_c_minipoints, player_c_tablepoints, player_c_penalties, player_c_false_hu,
                player_c_hu, player_c_selfdrawn, player_c_thrown_hu, player_c_hu_given, player_c_self_drawn,
                player_d_id, player_d_minipoints, player_d_tablepoints, player_d_penalties, player_d_false_hu,
                player_d_hu, player_d_selfdrawn, player_d_thrown_hu, player_d_hu_given, player_d_self_drawn
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt3->execute([
            $game_number, $game_name, $game_date, $game_year,
            $biggest_hand, $biggest_hand_player_str, $antal_hander, $zero_rounds,
            1, $approved, $_SESSION['user_id'] ?? null,
            $player_a, $hand_points[0], $table_pts[0], $penalty_totals[0], 0,
            $hu_received[0], $self_drawn[0], $hu_given[0], $hu_given[0], $self_drawn[0],
            $player_b, $hand_points[1], $table_pts[1], $penalty_totals[1], 0,
            $hu_received[1], $self_drawn[1], $hu_given[1], $hu_given[1], $self_drawn[1],
            $player_c, $hand_points[2], $table_pts[2], $penalty_totals[2], 0,
            $hu_received[2], $self_drawn[2], $hu_given[2], $hu_given[2], $self_drawn[2],
            $player_d, $hand_points[3], $table_pts[3], $penalty_totals[3], 0,
            $hu_received[3], $self_drawn[3], $hu_given[3], $hu_given[3], $self_drawn[3]
        ]);
        $game_id = $conn->lastInsertId();

        // Spara händer
        foreach ($hands_data as $hnum => $h) {
            $hp2 = [0, 0, 0, 0];
            if (empty($h['null_round'])) {
                $w = $h['winner'] - 1;
                if ($h['from'] === 0) {
                    for ($i = 0; $i < 4; $i++)
                        $hp2[$i] = ($i === $w) ? ($h['hp'] + 8) * 3 : -($h['hp'] + 8);
                } else {
                    $thr = $h['from'] - 1;
                    for ($i = 0; $i < 4; $i++) {
                        if ($i === $w)        $hp2[$i] = 8 + 8 + (8 + $h['hp']);
                        elseif ($i === $thr)  $hp2[$i] = -(8 + $h['hp']);
                        else                  $hp2[$i] = -8;
                    }
                }
            }
            $stmt4 = $conn->prepare("
                INSERT INTO stat_game_hands (game_id, hand_number, hu_points, winning_player, from_player,
                    player_a_points, player_b_points, player_c_points, player_d_points)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");
            $stmt4->execute([$game_id, $hnum + 1, $h['hp'], $h['winner'], $h['from'],
                $hp2[0], $hp2[1], $hp2[2], $hp2[3]]);
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'game_number' => $game_number,
            'approved' => $approved,
            'totals' => $hand_points,
            'table_pts' => $table_pts
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Bygg JSON för alla spelare (till autocomplete)
$players_json = json_encode(array_map(fn($p) => [
    'vms'  => $p['id'],
    'name' => $p['first_name'] . ' ' . $p['last_name'],
    'display' => $p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['id'] . ')',
], $all_players));
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Mobilregistrering</title>
<style>
/* ── RESET & BASE ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --green:   #2e7d32;
    --green2:  #4CAF50;
    --blue:    #1565c0;
    --blue2:   #1976d2;
    --red:     #c62828;
    --orange:  #e65100;
    --gray:    #616161;
    --light:   #f5f5f5;
    --card:    #ffffff;
    --border:  #e0e0e0;
    --shadow:  0 2px 8px rgba(0,0,0,.12);
    --radius:  12px;
    --bottom-bar: 72px;
}
html, body {
    height: 100%;
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: #eeeeee;
    color: #212121;
    -webkit-tap-highlight-color: transparent;
    overscroll-behavior: none;
}

/* ── TOP BAR ── */
.topbar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 100;
    background: var(--green);
    color: white;
    display: flex; align-items: center; gap: 12px;
    padding: 0 16px;
    height: 56px;
    box-shadow: 0 2px 6px rgba(0,0,0,.25);
}
.topbar-title { font-size: 1.1em; font-weight: 600; flex: 1; }
.topbar-back {
    background: none; border: none; color: white;
    font-size: 1.3em; cursor: pointer; padding: 8px;
    display: none;
}
.topbar-back.visible { display: block; }
.topbar-round {
    font-size: 0.85em; background: rgba(255,255,255,.2);
    padding: 4px 10px; border-radius: 20px; white-space: nowrap;
}

/* ── PAGES ── */
.page {
    display: none;
    padding-top: 56px;
    min-height: 100vh;
}
.page.active { display: block; }
.page-content {
    padding: 16px;
    padding-bottom: calc(var(--bottom-bar) + 16px);
}

/* ── BOTTOM ACTION BAR ── */
.bottom-bar {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 100;
    background: white;
    border-top: 1px solid var(--border);
    display: flex; gap: 8px;
    padding: 10px 16px;
    box-shadow: 0 -2px 8px rgba(0,0,0,.1);
    height: var(--bottom-bar);
}
.bottom-bar.hidden { display: none; }

/* ── CARDS ── */
.card {
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 16px;
    margin-bottom: 12px;
}
.card-title {
    font-size: 0.75em; text-transform: uppercase;
    letter-spacing: .08em; color: var(--gray);
    margin-bottom: 10px; font-weight: 600;
}

/* ── BUTTONS ── */
.btn {
    display: flex; align-items: center; justify-content: center;
    gap: 6px; border: none; border-radius: 10px;
    cursor: pointer; font-size: 1em; font-weight: 600;
    padding: 0 16px; min-height: 48px;
    transition: opacity .15s, transform .1s;
    text-decoration: none;
}
.btn:active { transform: scale(.97); opacity: .85; }
.btn-primary { background: var(--green2); color: white; flex: 1; }
.btn-blue    { background: var(--blue2);  color: white; flex: 1; }
.btn-gray    { background: #9e9e9e;        color: white; }
.btn-red     { background: var(--red);     color: white; }
.btn-orange  { background: var(--orange);  color: white; }
.btn-outline {
    background: white; color: var(--green);
    border: 2px solid var(--green2); flex: 1;
}
.btn-full { width: 100%; }
.btn-lg { min-height: 56px; font-size: 1.1em; border-radius: 12px; }
.btn-sm { min-height: 36px; font-size: 0.85em; padding: 0 12px; border-radius: 8px; }

/* ── AUTOCOMPLETE ── */
.search-wrap { position: relative; }
.search-input {
    width: 100%; padding: 14px 16px;
    border: 2px solid var(--border); border-radius: 10px;
    font-size: 1em;
    -webkit-appearance: none;
}
.search-input:focus { outline: none; border-color: var(--green2); }
.search-drop {
    position: absolute; left: 0; right: 0;
    background: white; border: 1px solid var(--border);
    border-radius: 0 0 10px 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,.15);
    max-height: 220px; overflow-y: auto;
    z-index: 200; display: none;
}
.search-drop.open { display: block; }
.search-item {
    padding: 12px 16px; cursor: pointer;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
}
.search-item:last-child { border-bottom: none; }
.search-item:hover, .search-item.highlighted { background: #e8f5e9; }
.search-vms { font-size: .78em; color: var(--gray); }

/* ── PLAYER SETUP ── */
.player-slots { display: grid; gap: 10px; }
.player-slot {
    display: flex; align-items: center; gap: 12px;
    background: var(--card); border-radius: var(--radius);
    padding: 14px 16px; box-shadow: var(--shadow);
    cursor: pointer; transition: background .15s;
}
.player-slot:active { background: #e8f5e9; }
.slot-wind {
    font-size: 1.6em; width: 40px; text-align: center; flex-shrink: 0;
}
.slot-info { flex: 1; }
.slot-label { font-size: .75em; color: var(--gray); text-transform: uppercase; letter-spacing: .06em; }
.slot-name { font-weight: 600; font-size: 1em; margin-top: 2px; }
.slot-name.empty { color: #bdbdbd; font-weight: 400; }
.slot-arrow { color: #bdbdbd; font-size: 1.2em; }
.slot-check { color: var(--green2); font-size: 1.2em; display: none; }

/* ── SCOREBOARD (game view) ── */
.scoreboard {
    display: grid; grid-template-columns: 1fr 1fr;
    grid-template-rows: auto auto auto;
    gap: 8px;
    margin: 8px 0 12px;
}
.score-cell {
    background: var(--card); border-radius: var(--radius);
    padding: 12px; box-shadow: var(--shadow);
    text-align: center; position: relative;
    min-height: 80px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.score-cell.wind-east { grid-column: 1; }
.score-cell.wind-south { grid-column: 2; }
.score-cell.wind-west { grid-column: 1; }
.score-cell.wind-north { grid-column: 2; }
.score-wind { font-size: .7em; color: var(--gray); text-transform: uppercase; letter-spacing: .08em; }
.score-name { font-weight: 700; font-size: .95em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 110px; }
.score-pts {
    font-size: 1.6em; font-weight: 800; margin-top: 2px;
    transition: color .3s;
}
.score-pts.pos { color: var(--green); }
.score-pts.neg { color: var(--red); }
.score-pts.zero { color: var(--gray); }
.score-badge {
    position: absolute; top: 6px; right: 8px;
    font-size: .7em; font-weight: 700;
    background: var(--orange); color: white;
    border-radius: 20px; padding: 2px 6px;
    display: none;
}
.score-badge.visible { display: block; }

/* ── ROUND INFO CENTER ── */
.round-center {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; gap: 4px;
    background: var(--card); border-radius: var(--radius);
    padding: 12px; box-shadow: var(--shadow);
    grid-column: 1 / -1;
}
.round-label { font-size: .75em; color: var(--gray); }
.round-num { font-size: 1.5em; font-weight: 800; color: var(--green); }
.round-hands { font-size: .8em; color: var(--gray); }

/* ── HAND INPUT MODAL ── */
.modal-overlay {
    display: none; position: fixed;
    inset: 0; z-index: 300;
    background: rgba(0,0,0,.5);
    align-items: flex-end;
}
.modal-overlay.open { display: flex; }
.modal-sheet {
    background: white; width: 100%;
    border-radius: 20px 20px 0 0;
    padding: 20px 20px 32px;
    max-height: 90vh; overflow-y: auto;
    animation: slideUp .2s ease-out;
}
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.modal-handle {
    width: 40px; height: 4px; background: #e0e0e0;
    border-radius: 2px; margin: 0 auto 16px;
}
.modal-title { font-size: 1.15em; font-weight: 700; margin-bottom: 16px; }
.modal-section { margin-bottom: 20px; }
.modal-label { font-size: .8em; font-weight: 700; color: var(--gray); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px; }

/* ── POINT SLIDER ── */
.point-display {
    text-align: center; font-size: 3em; font-weight: 900;
    color: var(--green); margin: 8px 0;
    line-height: 1;
}
.slider-wrap { padding: 0 8px; }
input[type=range] {
    -webkit-appearance: none; width: 100%; height: 6px;
    background: #e0e0e0; border-radius: 3px; outline: none;
    margin: 4px 0;
}
input[type=range]::-webkit-slider-thumb {
    -webkit-appearance: none; width: 28px; height: 28px;
    border-radius: 50%; background: var(--green2);
    cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,.2);
}
.slider-labels { display: flex; justify-content: space-between; font-size: .75em; color: var(--gray); margin-top: 2px; }

/* ── PLAYER PICKER (radio style) ── */
.player-radio-group { display: flex; flex-direction: column; gap: 8px; }
.player-radio {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; border-radius: 10px;
    border: 2px solid var(--border);
    cursor: pointer; transition: all .15s;
}
.player-radio.selected {
    border-color: var(--green2); background: #e8f5e9;
}
.player-radio-dot {
    width: 22px; height: 22px; border-radius: 50%;
    border: 2px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: all .15s;
}
.player-radio.selected .player-radio-dot {
    border-color: var(--green2); background: var(--green2);
}
.player-radio.selected .player-radio-dot::after {
    content: ''; width: 10px; height: 10px; border-radius: 50%; background: white;
}
.player-radio-name { font-weight: 600; }
.player-radio-wind { font-size: .75em; color: var(--gray); }

/* ── HAND LIST ── */
.hand-list { display: flex; flex-direction: column; gap: 6px; }
.hand-item {
    display: flex; align-items: center; gap: 10px;
    background: var(--card); border-radius: 10px;
    padding: 10px 14px; box-shadow: 0 1px 4px rgba(0,0,0,.08);
}
.hand-num { font-size: .8em; color: var(--gray); width: 32px; flex-shrink: 0; }
.hand-pts { font-weight: 800; font-size: 1.1em; min-width: 32px; }
.hand-info { flex: 1; font-size: .85em; color: var(--gray); }
.hand-winner { font-weight: 600; color: var(--green); }
.hand-scores { display: flex; gap: 4px; flex-wrap: wrap; }
.hand-score-pill {
    font-size: .75em; padding: 2px 7px; border-radius: 20px; font-weight: 600;
}
.pill-pos { background: #e8f5e9; color: var(--green); }
.pill-neg { background: #ffebee; color: var(--red); }
.pill-zero { background: #f5f5f5; color: var(--gray); }
.hand-del { color: #bdbdbd; font-size: 1.1em; cursor: pointer; padding: 4px 8px; }

/* ── PENALTY MODAL ── */
.penalty-btns { display: flex; gap: 8px; margin-top: 8px; }
.pen-preset {
    flex: 1; padding: 10px; border: 2px solid var(--border);
    border-radius: 8px; background: white; font-size: 1em; font-weight: 700;
    cursor: pointer; text-align: center;
    transition: all .15s;
}
.pen-preset.sel { border-color: var(--orange); background: #fff3e0; color: var(--orange); }
.pen-input {
    width: 100%; padding: 12px; border: 2px solid var(--border);
    border-radius: 10px; font-size: 1.1em; text-align: center;
    margin-top: 8px;
}
.pen-input:focus { outline: none; border-color: var(--orange); }

/* ── SUMMARY PAGE ── */
.summary-table { width: 100%; border-collapse: collapse; font-size: .9em; }
.summary-table th { background: #f5f5f5; padding: 8px 10px; text-align: right; font-size: .75em; color: var(--gray); text-transform: uppercase; }
.summary-table th:first-child { text-align: left; }
.summary-table td { padding: 8px 10px; border-bottom: 1px solid var(--border); text-align: right; font-weight: 600; }
.summary-table td:first-child { text-align: left; font-weight: 400; color: var(--gray); font-size: .82em; }
.summary-table tr.total-row td { border-top: 2px solid var(--green); font-size: 1.05em; font-weight: 800; padding-top: 12px; }
.pts-pos { color: var(--green); }
.pts-neg { color: var(--red); }
.pts-zero { color: var(--gray); }

/* ── MISC ── */
.chip {
    display: inline-flex; align-items: center;
    background: #e8f5e9; color: var(--green);
    border-radius: 20px; padding: 4px 10px; font-size: .8em; font-weight: 600;
    gap: 4px;
}
.chip-orange { background: #fff3e0; color: var(--orange); }
.chip-red { background: #ffebee; color: var(--red); }
.divider { height: 1px; background: var(--border); margin: 12px 0; }
.empty-state { text-align: center; color: #bdbdbd; padding: 24px; font-size: .95em; }
.tag { font-size: .7em; color: var(--gray); }
.success-icon { font-size: 4em; text-align: center; margin: 16px 0; }
.date-input {
    width: 100%; padding: 14px 16px;
    border: 2px solid var(--border); border-radius: 10px;
    font-size: 1em; -webkit-appearance: none;
}
.date-input:focus { outline: none; border-color: var(--green2); }
input[type=number].pts-manual {
    width: 100%; padding: 10px 14px;
    border: 2px solid var(--border); border-radius: 10px;
    font-size: 1.2em; text-align: center; -webkit-appearance: none;
}
input[type=number].pts-manual:focus { outline: none; border-color: var(--green2); }
.wind-chip {
    display: inline-block; width: 28px; height: 28px; line-height: 28px;
    text-align: center; border-radius: 6px; background: #e8f5e9;
    font-size: 1em; font-weight: 700; color: var(--green);
}
</style>
</head>
<body>

<!-- ═══════════════════════════════════════════
     TOP BAR
════════════════════════════════════════════ -->
<div class="topbar">
    <button class="topbar-back" id="backBtn" onclick="goBack()">←</button>
    <div class="topbar-title" id="topbarTitle">Mobilregistrering</div>
    <div class="topbar-round" id="topbarRound" style="display:none"></div>
</div>

<!-- ═══════════════════════════════════════════
     PAGE 1 – SETUP
════════════════════════════════════════════ -->
<div class="page active" id="page-setup">
<div class="page-content">

    <div class="card">
        <div class="card-title">📅 Matchdatum</div>
        <input type="date" class="date-input" id="gameDate" value="<?php echo date('Y-m-d'); ?>">
    </div>

    <div class="card">
        <div class="card-title">🏷️ Matchnamn (valfritt)</div>
        <input type="text" class="search-input" id="gameName" maxlength="255">
    </div>

    <div class="card">
        <div class="card-title">👥 Välj spelare</div>
        <div class="player-slots" id="playerSlots">
            <?php
            $winds = ['Öst','Syd','Väst','Nord'];
            $wind_imgs = ['img/ost.png','img/syd.png','img/vast.png','img/nord.png'];
            for ($i = 0; $i < 4; $i++):
            ?>
            <div class="player-slot" onclick="openPlayerPicker(<?= $i ?>)" id="slot<?= $i ?>">
                <div class="slot-wind" id="slotWind<?= $i ?>">
                    <img src="<?= $wind_imgs[$i] ?>" style="width:36px;height:36px;object-fit:contain;">
                </div>
                <div class="slot-info">
                    <div class="slot-label"><?= $winds[$i] ?></div>
                    <div class="slot-name empty" id="slotName<?= $i ?>">Välj spelare…</div>
                </div>
                <div class="slot-check" id="slotCheck<?= $i ?>">✓</div>
                <div class="slot-arrow" id="slotArrow<?= $i ?>">›</div>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="card" style="background:#e8f5e9; border:2px solid var(--green2);">
        <div style="font-size:.85em; color:var(--green);">
            💡 Tryck på ett spelarval för att söka bland klubbmedlemmar. Du kan shuffla vindarna med knappen nedan.
        </div>
    </div>

</div>
<div class="bottom-bar">
    <button class="btn btn-outline" onclick="shufflePlayers()">🔀 Blanda vindar</button>
    <button class="btn btn-primary btn-lg" onclick="startGame()">▶ Starta match</button>
</div>
</div>

<!-- ═══════════════════════════════════════════
     PAGE 2 – PLAYER PICKER (search)
════════════════════════════════════════════ -->
<div class="page" id="page-picker">
<div class="page-content">
    <div class="card-title" id="pickerWindLabel" style="font-size:1em; margin-bottom:12px;"></div>
    <div class="search-wrap">
        <input type="text" class="search-input" id="searchInput"
               placeholder="Sök namn eller Spelar-ID…"
               oninput="filterPlayers(this.value)"
               autocomplete="off">
        <div class="search-drop" id="searchDrop"></div>
    </div>
    <div style="margin-top:12px; color:var(--gray); font-size:.85em;">
        Skriv minst 2 tecken för att söka
    </div>
</div>
<div class="bottom-bar">
    <button class="btn btn-gray" onclick="cancelPicker()">Avbryt</button>
</div>
</div>

<!-- ═══════════════════════════════════════════
     PAGE 3 – GAME (scoreboard + hands)
════════════════════════════════════════════ -->
<div class="page" id="page-game">
<div class="page-content">

    <!-- Scoreboard -->
    <div class="scoreboard">
        <div class="round-center">
            <div class="round-label">Aktuell runda</div>
            <div class="round-num" id="roundLabel">Öst 1</div>
            <div class="round-hands" id="handsCount">0 händer spelade</div>
        </div>
        <?php for($i=0;$i<4;$i++): ?>
        <div class="score-cell" id="scoreCell<?=$i?>" onclick="openHandModalForPlayer(<?=$i?>)" style="cursor:pointer;">
            <div class="score-badge" id="scoreBadge<?=$i?>">❌HU</div>
            <div class="score-wind" id="scoreWind<?=$i?>"></div>
            <div class="score-name" id="scoreName<?=$i?>"></div>
            <div class="score-pts zero" id="scorePts<?=$i?>">0</div>
        </div>
        <?php endfor; ?>
    </div>

    <!-- Knappar för att registrera hand -->
    <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:12px;">
        <button class="btn btn-primary btn-full btn-lg" onclick="openHandModal()" id="addHandBtn">🀄 Registrera ny hand</button>
        <button class="btn btn-full" onclick="registerNullRound()" id="nullRoundBtn"
                style="background:#9e9e9e; color:white; min-height:44px; font-size:.95em;">⊘ 0-runda</button>
    </div>

    <!-- Hand list -->
    <div class="card">
        <div class="card-title" style="display:flex;justify-content:space-between;">
            <span>🀄 Spelade händer</span>
            <span id="handCountBadge" class="chip">0 / 16</span>
        </div>
        <div class="hand-list" id="handList">
            <div class="empty-state">Tryck på "Registrera ny hand" för att börja</div>
        </div>
    </div>

    <div style="padding: 0 0 8px;">
        <button class="btn btn-primary btn-full btn-lg" onclick="saveGame()" id="saveBtn">💾 Spara match</button>
    </div>

</div>
<div class="bottom-bar">
    <button class="btn btn-gray btn-sm" onclick="openTools()">⚙️ Verktyg</button>
    <button class="btn btn-blue btn-full" onclick="showSummary()">📋 Visa resultat</button>
</div>
</div>

<!-- ═══════════════════════════════════════════
     PAGE 4 – SUMMARY / RESULT
════════════════════════════════════════════ -->
<div class="page" id="page-summary">
<div class="page-content">
    <div class="card">
        <div class="card-title">📊 Alla händer</div>
        <div id="summaryHandTable"></div>
    </div>
    <div class="card">
        <div class="card-title" id="summaryResultTitle">🏁 Slutresultat</div>
        <div id="summaryFinal"></div>
    </div>
</div>
<div class="bottom-bar">
    <button class="btn btn-gray btn-full" onclick="showPage('page-game')">← Tillbaka</button>
</div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL – HAND INPUT
════════════════════════════════════════════ -->
<div class="modal-overlay" id="handModal">
<div class="modal-sheet">
    <div class="modal-handle"></div>
    <div class="modal-title" id="handModalTitle">Registrera hand</div>

    <!-- Hu-poäng -->
    <div class="modal-section" id="huSection">
        <div class="modal-label">🎯 Hu-poäng</div>
        <div class="point-display" id="ptDisplay">8</div>
        <div class="slider-wrap">
            <input type="range" id="ptSlider" min="8" max="88" step="1" value="8"
                   oninput="onSliderChange(this.value)">
            <div class="slider-labels">
                <span>8</span><span>24</span><span>40</span><span>56</span><span>72</span><span>88</span>
            </div>
        </div>
        <div style="display:flex; gap:8px; margin-top:10px; flex-wrap:wrap;" id="ptPresets"></div>
        <div style="margin-top:8px;">
            <input type="number" class="pts-manual" id="ptManual" min="8" max="999"
                   placeholder="Eller skriv manuellt" oninput="onManualPts(this.value)">
        </div>
    </div>

    <!-- Vinnare -->
    <div class="modal-section" id="winnerSection">
        <div class="modal-label">🏆 Vinnare (tog hu)</div>
        <div class="player-radio-group" id="winnerGroup"></div>
    </div>

    <!-- Från vem -->
    <div class="modal-section" id="fromSection">
        <div class="modal-label">🎴 Kastades av</div>
        <div class="player-radio-group" id="fromGroup"></div>
    </div>

    <div class="modal-section">
        <label style="display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:10px; border:2px solid var(--border); cursor:pointer; background:#f9f9f9;">
            <input type="checkbox" id="nullRound" style="width:22px;height:22px;accent-color:#9e9e9e;cursor:pointer;" onchange="toggleNullRound(this.checked)">
            <div>
                <div style="font-weight:700;">Noll-runda</div>
                <div style="font-size:.8em;color:var(--gray);">Ingen vinnare – alla spelare får 0 poäng</div>
            </div>
        </label>
    </div>

    <div style="display:flex; gap:10px; margin-top:8px;">
        <button class="btn btn-gray" onclick="closeHandModal()">Avbryt</button>
        <button class="btn btn-primary" onclick="submitHand()" id="handSubmitBtn">✓ Registrera</button>
    </div>
</div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL – TOOLS
════════════════════════════════════════════ -->
<div class="modal-overlay" id="toolsModal">
<div class="modal-sheet">
    <div class="modal-handle"></div>
    <div class="modal-title">⚙️ Verktyg</div>

    <div style="display:flex; flex-direction:column; gap:10px;">
        <button class="btn btn-orange btn-full" onclick="undoLastHand()">↩ Ångra senaste hand</button>
        <button class="btn btn-orange btn-full" onclick="openPenaltyModal()">⚠️ Lägg till penalty</button>
        <div class="divider"></div>
        <button class="btn btn-red btn-full" onclick="confirmNewGame()">🗑 Avbryt match</button>
    </div>
    <div style="margin-top:16px;">
        <button class="btn btn-gray btn-full" onclick="closeTools()">Stäng</button>
    </div>
</div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL – PENALTY
════════════════════════════════════════════ -->
<div class="modal-overlay" id="penaltyModal">
<div class="modal-sheet">
    <div class="modal-handle"></div>
    <div class="modal-title">⚠️ Lägg till penalty</div>
    <div class="modal-section">
        <div class="modal-label">Spelare</div>
        <div class="player-radio-group" id="penPlayerGroup"></div>
    </div>
    <div class="modal-section">
        <div class="modal-label">Penalty-poäng</div>
        <div class="penalty-btns">
            <div class="pen-preset" onclick="setPenPreset(this, 10)">10</div>
            <div class="pen-preset" onclick="setPenPreset(this, 30)">30</div>
            <div class="pen-preset" onclick="setPenPreset(this, 60)">60</div>
        </div>
        <input type="number" class="pen-input" id="penAmount" placeholder="Eller ange antal" min="1">
    </div>
    <div class="modal-section">
        <label style="display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:10px; border:2px solid var(--border); cursor:pointer;" id="shareLabel">
            <input type="checkbox" id="penShare" checked style="width:22px;height:22px;accent-color:var(--orange);cursor:pointer;">
            <div>
                <div style="font-weight:700;">Fördela på övriga spelare</div>
                <div style="font-size:.8em;color:var(--gray);">Penaltybeloppet delas ut som pluspoäng till de tre andra</div>
            </div>
        </label>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn btn-gray" onclick="closePenaltyModal()">Avbryt</button>
        <button class="btn btn-orange" onclick="submitPenalty()">Lägg till</button>
    </div>
</div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL – SUCCESS
════════════════════════════════════════════ -->
<div class="modal-overlay" id="successModal">
<div class="modal-sheet" style="text-align:center;">
    <div class="modal-handle"></div>
    <div class="success-icon">🎉</div>
    <div class="modal-title" id="successTitle">Match sparad!</div>
    <p id="successMsg" style="color:var(--gray); margin-bottom:20px;"></p>
    <div style="display:flex; flex-direction:column; gap:10px;">
        <a class="btn btn-primary btn-full" href="games.php">📋 Visa matcher</a>
        <a class="btn btn-outline btn-full" href="mobile-game.php">➕ Ny match</a>
    </div>
</div>
</div>

<script>
// ════════════════════════════════════════════════════════
//  DATA
// ════════════════════════════════════════════════════════
const ALL_PLAYERS = <?= $players_json ?>;
const WIND_IMGS = ['img/ost.png','img/syd.png','img/vast.png','img/nord.png'];
const WIND_IMGS_WHITE = ['img/ost_white.png','img/syd_white.png','img/vast_white.png','img/nord_white.png'];
const WIND_NAMES = ['Öst','Syd','Väst','Nord'];
const ROUND_WIND_NAMES = ['Öst','Syd','Väst','Nord'];

let state = {
    stat_players: [null, null, null, null],  // {vms, name}
    hands: [],     // {hu_points, winner(0-3), from(-1 or 0-3), round}
    penalties: [], // {player_idx, amount, label}
    gameDate: document.getElementById('gameDate').value,
    gameName: '',
    currentRound: 0, // 0-15 (Öst1-4, Syd1-4, Väst1-4, Nord1-4)
};

let pickerTargetIdx = -1;
let editingHandIdx = -1;
let selectedWinner = -1;
let selectedFrom = -2; // -2 = not selected, -1 = self, 0-3 = player
let selectedPenPlayer = -1;
let currentPage = 'page-setup';
let pageHistory = ['page-setup'];

// ════════════════════════════════════════════════════════
//  NAVIGATION
// ════════════════════════════════════════════════════════
function showPage(id) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    currentPage = id;

    const backBtn = document.getElementById('backBtn');
    backBtn.classList.toggle('visible', id !== 'page-setup');

    const roundEl = document.getElementById('topbarRound');
    if (id === 'page-game' || id === 'page-summary') {
        roundEl.style.display = '';
        recalcCurrentRound();  // synkar state.currentRound och alla UI-element
    } else {
        roundEl.style.display = 'none';
    }

    // Title
    const titles = {
        'page-setup': 'Mobilregistrering',
        'page-picker': 'Välj spelare',
        'page-game': 'Pågående match',
        'page-summary': 'Matchresultat'
    };
    document.getElementById('topbarTitle').textContent = titles[id] || '';
}

function goBack() {
    const prev = { 'page-picker': 'page-setup', 'page-game': 'page-setup', 'page-summary': 'page-game' };
    // Om vi är i en pågående match, fråga innan vi lämnar
    if (currentPage === 'page-game' && hands.length > 0) {
        if (!confirm('Du har en pågående match. Vill du verkligen lämna?\n\nMatchen sparas automatiskt och kan återställas.')) {
            return;
        }
    }
    if (prev[currentPage]) showPage(prev[currentPage]);
}

// ════════════════════════════════════════════════════════
//  PLAYER PICKER
// ════════════════════════════════════════════════════════
function openPlayerPicker(idx) {
    pickerTargetIdx = idx;
    const label = `<img src="${WIND_IMGS[idx]}" style="width:20px;height:20px;object-fit:contain;vertical-align:middle;"> ${WIND_NAMES[idx]}`;
    document.getElementById('pickerWindLabel').innerHTML = `<span class="chip">${label}</span> &nbsp;Välj spelare`;
    document.getElementById('searchInput').value = '';
    document.getElementById('searchDrop').innerHTML = '';
    document.getElementById('searchDrop').classList.remove('open');
    showPage('page-picker');
    setTimeout(() => document.getElementById('searchInput').focus(), 200);
}

function cancelPicker() { showPage('page-setup'); }

function filterPlayers(q) {
    const drop = document.getElementById('searchDrop');
    if (q.length < 2) { drop.classList.remove('open'); drop.innerHTML = ''; return; }
    q = q.toLowerCase();
    const results = ALL_PLAYERS.filter(p =>
        p.name.toLowerCase().includes(q) || p.vms.includes(q)
    ).slice(0, 20);

    if (!results.length) {
        drop.innerHTML = '<div class="search-item" style="color:#bdbdbd;">Inga träffar</div>';
        drop.classList.add('open');
        return;
    }

    drop.innerHTML = results.map(p => {
        const safeName = p.name.replace(/&/g,'&amp;').replace(/"/g,'&quot;');
        return `<div class="search-item" data-vms="${p.vms}" data-name="${safeName}">
            <div style="font-weight:600;">${p.name}</div>
            <div class="search-vms">${p.vms}</div>
        </div>`;
    }).join('');

    drop.querySelectorAll('.search-item').forEach(el => {
        el.addEventListener('pointerdown', function(e) {
            e.preventDefault();
            selectPlayer(this.dataset.vms, this.dataset.name);
        });
    });

    drop.classList.add('open');
}

function selectPlayer(vms, name) {
    // Check duplicate
    for (let i = 0; i < 4; i++) {
        if (i !== pickerTargetIdx && state.stat_players[i] && state.stat_players[i].vms === vms) {
            alert('Den spelaren är redan vald i en annan position.');
            return;
        }
    }
    state.stat_players[pickerTargetIdx] = { vms, name };
    updateSlot(pickerTargetIdx);
    showPage('page-setup');
}

function updateSlot(idx) {
    const p = state.stat_players[idx];
    const nameEl = document.getElementById('slotName' + idx);
    const checkEl = document.getElementById('slotCheck' + idx);
    const arrowEl = document.getElementById('slotArrow' + idx);
    if (p) {
        nameEl.textContent = p.name;
        nameEl.classList.remove('empty');
        checkEl.style.display = 'block';
        arrowEl.style.display = 'none';
    } else {
        nameEl.textContent = 'Välj spelare…';
        nameEl.classList.add('empty');
        checkEl.style.display = 'none';
        arrowEl.style.display = '';
    }
}

function shufflePlayers() {
    const stat_players = [...state.stat_players];
    for (let i = stat_players.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [stat_players[i], stat_players[j]] = [stat_players[j], stat_players[i]];
    }
    state.stat_players = stat_players;
    for (let i = 0; i < 4; i++) updateSlot(i);
}

// ════════════════════════════════════════════════════════
//  GAME START
// ════════════════════════════════════════════════════════
function startGame() {
    if (state.stat_players.some(p => !p)) {
        alert('Välj alla fyra spelare först.');
        return;
    }
    state.hands = [];
    state.penalties = [];
    state.currentRound = 0;
    state.gameDate = document.getElementById('gameDate').value;
    state.gameName = document.getElementById('gameName').value.trim();
    initScoreboard();
    renderHandList();
    updateScoreboard();
    showPage('page-game');
}

function initScoreboard() {
    for (let i = 0; i < 4; i++) {
        document.getElementById('scoreWind' + i).innerHTML =
            `<img src="${WIND_IMGS_WHITE[i]}" style="width:28px;height:28px;object-fit:contain;vertical-align:middle;margin-right:4px;">${WIND_NAMES[i]}`;
        document.getElementById('scoreName' + i).textContent = state.stat_players[i].name;
        // Uppdatera slot-wind med bild
        const sw = document.getElementById('slotWind' + i);
        if (sw) sw.innerHTML = `<img src="${WIND_IMGS[i]}" style="width:36px;height:36px;object-fit:contain;">`;
    }
}

// ════════════════════════════════════════════════════════
//  ROUND TRACKING
// ════════════════════════════════════════════════════════
function getRoundLabel(r) {
    // Används i hand-lista och summary (text)
    return 'Runda ' + (r + 1);
}

function getRoundLabelHTML(r) {
    // Används i "Aktuell runda"-rutan med vindikon
    const idx = Math.floor(r / 4);
    const handNum = r + 1;
    return `<img src="${WIND_IMGS[idx]}" style="width:28px;height:28px;object-fit:contain;vertical-align:middle;margin-right:4px;">Runda ${handNum}`;
}

function getTopbarRoundHTML(r) {
    const handNum = r + 1;
    return `Runda ${handNum}`;
}

function recalcCurrentRound() {
    // Current round = number of hands played (max 15)
    state.currentRound = Math.min(state.hands.length, 15);
    document.getElementById('roundLabel').innerHTML = getRoundLabelHTML(state.currentRound);
    document.getElementById('topbarRound').innerHTML = getTopbarRoundHTML(state.currentRound);
    document.getElementById('handsCount').textContent = state.hands.length + ' händer spelade';
    document.getElementById('handCountBadge').textContent = state.hands.length + ' / 16';
    const maxReached = state.hands.length >= 16;
    document.getElementById('addHandBtn').disabled = maxReached;
    const nullBtn = document.getElementById('nullRoundBtn');
    if (nullBtn) nullBtn.disabled = maxReached;
}

// ════════════════════════════════════════════════════════
//  SCORE CALCULATION
// ════════════════════════════════════════════════════════
function calcTotals() {
    let totals = [0, 0, 0, 0];
    for (const h of state.hands) {
        const pts = handPoints(h.hu_points, h.winner, h.from, h.null_round);
        for (let i = 0; i < 4; i++) totals[i] += pts[i];
    }
    for (const pen of state.penalties) {
        totals[pen.player_idx] -= pen.amount;
        if (pen.share) {
            // Distribute evenly to the other 3 stat_players
            const share = Math.round(pen.amount / 3);
            for (let i = 0; i < 4; i++) {
                if (i !== pen.player_idx) totals[i] += share;
            }
        }
    }
    return totals;
}

function handPoints(hp, win, from, isNull) {
    if (isNull) return [0, 0, 0, 0];
    const pts = [0, 0, 0, 0];
    if (from === -1) { // self-drawn
        for (let i = 0; i < 4; i++)
            pts[i] = (i === win) ? (hp + 8) * 3 : -(hp + 8);
    } else {
        for (let i = 0; i < 4; i++) {
            if (i === win)       pts[i] = 8 + 8 + (8 + hp);
            else if (i === from) pts[i] = -(8 + hp);
            else                 pts[i] = -8;
        }
    }
    return pts;
}

function updateScoreboard() {
    const totals = calcTotals();
    for (let i = 0; i < 4; i++) {
        const el = document.getElementById('scorePts' + i);
        el.textContent = totals[i] > 0 ? '+' + totals[i] : totals[i];
        el.className = 'score-pts ' + (totals[i] > 0 ? 'pos' : totals[i] < 0 ? 'neg' : 'zero');
    }
    recalcCurrentRound();
}

// ════════════════════════════════════════════════════════
//  HAND MODAL
// ════════════════════════════════════════════════════════
function openHandModalForPlayer(playerIdx) {
    openHandModal(-1, playerIdx);
}

function openHandModal(editIdx = -1, preselectedWinner = -1) {
    editingHandIdx = editIdx;
    selectedWinner = -1;
    selectedFrom = -2;

    // Reset points
    const initPts = editIdx >= 0 ? state.hands[editIdx].hu_points : 8;
    document.getElementById('ptSlider').value = Math.min(initPts, 88);
    document.getElementById('ptDisplay').textContent = initPts;
    document.getElementById('ptManual').value = initPts > 88 ? initPts : '';

    if (editIdx >= 0) {
        selectedWinner = state.hands[editIdx].winner;
        selectedFrom = state.hands[editIdx].from;
        document.getElementById('handModalTitle').textContent = 'Redigera hand ' + (editIdx + 1);
    } else {
        if (preselectedWinner >= 0) selectedWinner = preselectedWinner;
        document.getElementById('handModalTitle').textContent = 'Hand ' + (state.hands.length + 1);
    }
    // Reset noll-runda
    document.getElementById('nullRound').checked = false;
    toggleNullRound(false);

    // Build presets
    buildPtPresets();

    // Build winner radio
    buildWinnerRadio();

    // Build from radio
    buildFromRadio();

    document.getElementById('handModal').classList.add('open');
}

function buildPtPresets() {
    const presets = [8, 16, 24, 32, 48, 64, 88];
    const curVal = parseInt(document.getElementById('ptSlider').value);
    document.getElementById('ptPresets').innerHTML = presets.map(p => `
        <button class="btn btn-sm ${p === curVal ? 'btn-primary' : 'btn-outline'}"
                onclick="setPresetPts(${p})" style="flex:0 0 auto; min-width:44px;">${p}</button>
    `).join('');
}

function setPresetPts(v) {
    document.getElementById('ptSlider').value = Math.min(v, 88);
    document.getElementById('ptDisplay').textContent = v;
    document.getElementById('ptManual').value = '';
    buildPtPresets();
}

function onSliderChange(v) {
    document.getElementById('ptDisplay').textContent = v;
    document.getElementById('ptManual').value = '';
    buildPtPresets();
}

function onManualPts(v) {
    const n = parseInt(v);
    if (n >= 8) {
        document.getElementById('ptDisplay').textContent = n;
        document.getElementById('ptSlider').value = Math.min(n, 88);
        buildPtPresets();
    }
}

function getCurrentPts() {
    const manual = parseInt(document.getElementById('ptManual').value);
    if (!isNaN(manual) && manual >= 8) return manual;
    return parseInt(document.getElementById('ptSlider').value);
}

function buildWinnerRadio() {
    document.getElementById('winnerGroup').innerHTML = state.stat_players.map((p, i) => `
        <div class="player-radio ${selectedWinner === i ? 'selected' : ''}"
             onclick="selectWinner(${i})" id="winner${i}">
            <div class="player-radio-dot"></div>
            <img src="${WIND_IMGS[i]}" style="width:28px;height:28px;object-fit:contain;flex-shrink:0;">
            <div>
                <div class="player-radio-name">${p.name}</div>
                <div class="player-radio-wind">${WIND_NAMES[i]}</div>
            </div>
        </div>
    `).join('');
}

function selectWinner(i) {
    selectedWinner = i;
    buildWinnerRadio();
    buildFromRadio(); // rebuild so winner can't be "from"
}

function buildFromRadio() {
    const rows = [
        `<div class="player-radio ${selectedFrom === -1 ? 'selected' : ''}"
              onclick="selectFrom(-1)" id="from_self">
            <div class="player-radio-dot"></div>
            <div style="font-size:1.4em;">🀄</div>
            <div>
                <div class="player-radio-name">Självdragen (muren)</div>
                <div class="player-radio-wind">Selfdrawn</div>
            </div>
        </div>`
    ];
    state.stat_players.forEach((p, i) => {
        if (i === selectedWinner) return;
        rows.push(`
            <div class="player-radio ${selectedFrom === i ? 'selected' : ''}"
                 onclick="selectFrom(${i})" id="from_${i}">
                <div class="player-radio-dot"></div>
                <img src="${WIND_IMGS[i]}" style="width:28px;height:28px;object-fit:contain;flex-shrink:0;">
                <div>
                    <div class="player-radio-name">${p.name}</div>
                    <div class="player-radio-wind">${WIND_NAMES[i]}</div>
                </div>
            </div>
        `);
    });
    document.getElementById('fromGroup').innerHTML = rows.join('');
}

function selectFrom(i) {
    selectedFrom = i;
    buildFromRadio();
}

function closeHandModal() {
    document.getElementById('handModal').classList.remove('open');
}

function registerNullRound() {
    if (state.hands.length >= 16) { alert('Max 16 händer per match.'); return; }
    const hand = { hu_points: 0, winner: -1, from: -1, round: state.hands.length, null_round: true };
    state.hands.push(hand);
    renderHandList();
    updateScoreboard();
}

function toggleNullRound(isNull) {
    ['huSection', 'winnerSection', 'fromSection'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.style.opacity = isNull ? '0.3' : '1';
            el.style.pointerEvents = isNull ? 'none' : '';
        }
    });
}

function submitHand() {
    const isNull = document.getElementById('nullRound').checked;
    let hand;

    if (isNull) {
        hand = { hu_points: 0, winner: -1, from: -1, round: state.hands.length, null_round: true };
    } else {
        const pts = getCurrentPts();
        if (pts < 8) { alert('Hu-poängen måste vara minst 8.'); return; }
        if (selectedWinner < 0) { alert('Välj vinnare.'); return; }
        if (selectedFrom === -2) { alert('Välj vem som kastade (eller självdragen).'); return; }
        hand = { hu_points: pts, winner: selectedWinner, from: selectedFrom, round: state.hands.length };
    }

    if (editingHandIdx >= 0) {
        state.hands[editingHandIdx] = hand;
    } else {
        if (state.hands.length >= 16) { alert('Max 16 händer per match.'); return; }
        state.hands.push(hand);
    }

    closeHandModal();
    renderHandList();
    updateScoreboard();
}

// ════════════════════════════════════════════════════════
//  HAND LIST
// ════════════════════════════════════════════════════════
function renderHandList() {
    const list = document.getElementById('handList');
    if (!state.hands.length) {
        list.innerHTML = '<div class="empty-state">Tryck + för att registrera en hand</div>';
        return;
    }
    list.innerHTML = state.hands.map((h, idx) => {
        // Noll-runda – rendera separat för att undvika krasch på winner=-1
        if (h.null_round) {
            return `
            <div class="hand-item" style="background:#f9f9f9;">
                <div class="hand-num" style="font-weight:700; color:#bdbdbd;">${idx + 1}</div>
                <div class="hand-pts" style="color:#bdbdbd;">0</div>
                <div class="hand-info">
                    <div style="color:#9e9e9e; font-style:italic;">Noll-runda</div>
                    <div style="font-size:.8em;color:#bdbdbd;">Alla: ±0</div>
                </div>
                <div class="hand-del" onclick="deleteHand(${idx})">✕</div>
            </div>`;
        }
        const pts = handPoints(h.hu_points, h.winner, h.from, false);
        const winName = state.stat_players[h.winner].name.split(' ')[0];
        const fromStr = h.from === -1 ? 'Selfdrawn' : 'från ' + state.stat_players[h.from].name.split(' ')[0];
        const pills = pts.map((p, i) => {
            const cls = p > 0 ? 'pill-pos' : p < 0 ? 'pill-neg' : 'pill-zero';
            const sign = p > 0 ? '+' : '';
            return `<span class="hand-score-pill ${cls}">${state.stat_players[i].name.split(' ')[0]}: ${sign}${p}</span>`;
        }).join('');
        return `
            <div class="hand-item">
                <div class="hand-num" style="font-weight:700; color:#616161;">${idx + 1}</div>
                <div class="hand-pts">${h.hu_points}</div>
                <div class="hand-info">
                    <div class="hand-winner">${winName}</div>
                    <div>${fromStr}</div>
                    <div class="hand-scores" style="margin-top:4px;">${pills}</div>
                </div>
                <div class="hand-del" onclick="deleteHand(${idx})">✕</div>
            </div>
        `;
    }).join('');

    // Penalties
    if (state.penalties.length) {
        list.innerHTML += state.penalties.map((pen, idx) => {
            const shareInfo = pen.share ? ` (+${Math.round(pen.amount/3)} till övriga)` : '';
            return `
            <div class="hand-item" style="background:#fff3e0;">
                <div class="hand-num" style="color:var(--orange);">PEN</div>
                <div class="hand-pts" style="color:var(--orange);">${pen.amount}</div>
                <div class="hand-info">
                    <div style="color:var(--orange); font-weight:700;">${state.stat_players[pen.player_idx].name.split(' ')[0]}</div>
                    <div style="font-size:.8em; color:var(--gray);">Penalty${shareInfo}</div>
                </div>
                <div class="hand-del" onclick="deletePenalty(${idx})">✕</div>
            </div>`;
        }).join('');
    }
}

function deleteHand(idx) {
    if (!confirm('Ta bort hand ' + (idx + 1) + '?')) return;
    state.hands.splice(idx, 1);
    renderHandList();
    updateScoreboard();
}

// ════════════════════════════════════════════════════════
//  TOOLS
// ════════════════════════════════════════════════════════
function openTools() { document.getElementById('toolsModal').classList.add('open'); }
function closeTools() { document.getElementById('toolsModal').classList.remove('open'); }

function undoLastHand() {
    if (!state.hands.length) { alert('Inga händer att ångra.'); return; }
    if (!confirm('Ta bort senaste handen?')) return;
    state.hands.pop();
    closeTools();
    renderHandList();
    updateScoreboard();
}

function confirmNewGame() {
    if (!confirm('Avbryt matchen och börja om?')) return;
    state.hands = [];
    state.penalties = [];
    closeTools();
    showPage('page-setup');
}

// ════════════════════════════════════════════════════════
//  PENALTY
// ════════════════════════════════════════════════════════
function openPenaltyModal() {
    closeTools();
    selectedPenPlayer = -1;
    document.getElementById('penAmount').value = '';
    document.querySelectorAll('.pen-preset').forEach(b => b.classList.remove('sel'));

    document.getElementById('penPlayerGroup').innerHTML = state.stat_players.map((p, i) => `
        <div class="player-radio" onclick="selectPenPlayer(${i})" id="penPlayer${i}">
            <div class="player-radio-dot"></div>
            <div>
                <div class="player-radio-name">${p.name}</div>
                <div class="player-radio-wind">${WIND_NAMES[i]}</div>
            </div>
        </div>
    `).join('');

    document.getElementById('penaltyModal').classList.add('open');
}

function closePenaltyModal() { document.getElementById('penaltyModal').classList.remove('open'); }

function selectPenPlayer(i) {
    selectedPenPlayer = i;
    document.querySelectorAll('#penPlayerGroup .player-radio').forEach((el, j) => {
        el.classList.toggle('selected', j === i);
    });
}

function setPenPreset(el, val) {
    document.querySelectorAll('.pen-preset').forEach(b => b.classList.remove('sel'));
    el.classList.add('sel');
    document.getElementById('penAmount').value = val;
}

function submitPenalty() {
    if (selectedPenPlayer < 0) { alert('Välj spelare.'); return; }
    const amt = parseInt(document.getElementById('penAmount').value);
    if (!amt || amt <= 0) { alert('Ange giltigt penalty-belopp.'); return; }
    const share = document.getElementById('penShare').checked;
    state.penalties.push({ player_idx: selectedPenPlayer, amount: amt, share: share });
    closePenaltyModal();
    renderHandList();
    updateScoreboard();
}

function deletePenalty(idx) {
    if (!confirm('Ta bort penalty?')) return;
    state.penalties.splice(idx, 1);
    renderHandList();
    updateScoreboard();
}

function deleteSummaryHand(idx) {
    if (!confirm('Ta bort hand ' + (idx + 1) + '?')) return;
    state.hands.splice(idx, 1);
    renderHandList();
    updateScoreboard();
    renderSummary();
}

function deleteSummaryPenalty(idx) {
    if (!confirm('Ta bort penalty?')) return;
    state.penalties.splice(idx, 1);
    renderHandList();
    updateScoreboard();
    renderSummary();
}

// ════════════════════════════════════════════════════════
//  SUMMARY
// ════════════════════════════════════════════════════════
function showSummary() {
    renderSummary();
    showPage('page-summary');
}

function renderSummary() {
    const totals = calcTotals();
    // Uppdatera rubrik
    const titleEl = document.getElementById('summaryResultTitle');
    if (titleEl) {
        if (state.hands.length >= 16) {
            titleEl.textContent = '🏁 Slutresultat';
        } else {
            titleEl.textContent = '🏁 Resultat efter ' + state.hands.length + ' spelade ' + (state.hands.length === 1 ? 'hand' : 'händer');
        }
    }

    // Table points
    const sorted = totals.map((m, i) => ({ m, i })).sort((a, b) => b.m - a.m);
    const bpBase = [4, 2, 1, 0];
    const tp = [0, 0, 0, 0];
    for (let i = 0; i < 4; ) {
        let same = 1, sum = bpBase[i];
        for (let j = i + 1; j < 4; j++) {
            if (sorted[j].m === sorted[i].m) { same++; sum += bpBase[j]; } else break;
        }
        const shared = sum / same;
        for (let k = 0; k < same; k++) tp[sorted[i + k].i] = shared;
        i += same;
    }

    // Hand table
    const names = state.stat_players.map(p => p.name.split(' ')[0]);
    let tableHTML = `<div style="overflow-x:auto;"><table class="summary-table">
        <thead><tr>
            <th>#</th>
            <th>Pts</th>
            ${names.map(n => `<th>${n}</th>`).join('')}
        </tr></thead><tbody>`;

    let running = [0, 0, 0, 0];
    state.hands.forEach((h, idx) => {
        const pts = handPoints(h.hu_points, h.winner, h.from, h.null_round);
        for (let i = 0; i < 4; i++) running[i] += pts[i];
        const winner = h.winner;
        if (h.null_round) {
            tableHTML += `<tr style="background:#f9f9f9; color:#9e9e9e;">
                <td style="white-space:nowrap; font-style:italic;">
                    ${idx + 1}
                    <span onclick="deleteSummaryHand(${idx})" style="color:#bdbdbd;cursor:pointer;margin-left:4px;">✕</span>
                </td>
                <td>–</td>
                <td colspan="4" style="text-align:center; font-style:italic;">Noll-runda (0)</td>
            </tr>`;
        } else {
        tableHTML += `<tr>
            <td style="white-space:nowrap;">
                ${idx + 1}
                <span onclick="deleteSummaryHand(${idx})" style="color:#bdbdbd;cursor:pointer;margin-left:4px;font-size:1em;">✕</span>
            </td>
            <td>${h.hu_points}</td>
            ${pts.map((p, i) => {
                const cls = p > 0 ? 'pts-pos' : p < 0 ? 'pts-neg' : 'pts-zero';
                const bold = i === winner ? 'font-weight:800;' : '';
                return `<td class="${cls}" style="${bold}">${p > 0 ? '+' : ''}${p}</td>`;
            }).join('')}
        </tr>`;
        }
    });

    // Penalties in table
    state.penalties.forEach((pen, pidx) => {
        const row = [0, 0, 0, 0];
        row[pen.player_idx] = -pen.amount;
        if (pen.share) {
            const s = Math.round(pen.amount / 3);
            for (let i = 0; i < 4; i++) if (i !== pen.player_idx) row[i] = s;
        }
        for (let i = 0; i < 4; i++) running[i] += row[i];
        tableHTML += `<tr style="background:#fff3e0;">
            <td style="white-space:nowrap;color:var(--orange);">
                Pen
                <span onclick="deleteSummaryPenalty(${pidx})" style="color:#bdbdbd;cursor:pointer;margin-left:4px;">✕</span>
            </td>
            <td style="color:var(--orange);">${pen.amount}</td>
            ${row.map(p => {
                const cls = p > 0 ? 'pts-pos' : p < 0 ? 'pts-neg' : 'pts-zero';
                return `<td class="${cls}">${p !== 0 ? (p > 0 ? '+' : '') + p : '–'}</td>`;
            }).join('')}
        </tr>`;
    });

    tableHTML += `</tbody><tfoot><tr class="total-row">
        <td colspan="2">Summa</td>
        ${totals.map(t => {
            const cls = t > 0 ? 'pts-pos' : t < 0 ? 'pts-neg' : 'pts-zero';
            return `<td class="${cls}">${t > 0 ? '+' : ''}${t}</td>`;
        }).join('')}
    </tr></tfoot></table></div>`;

    document.getElementById('summaryHandTable').innerHTML = tableHTML;

    // Final cards
    const finalSorted = totals.map((t, i) => ({ t, i, tp: tp[i] })).sort((a, b) => b.t - a.t);
    const medals = ['🥇', '🥈', '🥉', '4️⃣'];
    document.getElementById('summaryFinal').innerHTML = `
        <div style="display:grid; gap:8px;">
            ${finalSorted.map((r, rank) => {
                const tclass = r.t > 0 ? 'pts-pos' : r.t < 0 ? 'pts-neg' : 'pts-zero';
                return `<div style="display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--border);">
                    <div style="font-size:1.4em;">${medals[rank]}</div>
                    <div style="flex:1;">
                        <div style="font-weight:700;">${state.stat_players[r.i].name}</div>
                        <div class="tag"><img src="${WIND_IMGS[r.i]}" style="width:16px;height:16px;object-fit:contain;vertical-align:middle;"> ${WIND_NAMES[r.i]} &nbsp;|&nbsp; BP: ${r.tp.toFixed(1)}</div>
                    </div>
                    <div class="${tclass}" style="font-size:1.5em; font-weight:900;">${r.t > 0 ? '+' : ''}${r.t}</div>
                </div>`;
            }).join('')}
        </div>
    `;
}

// ════════════════════════════════════════════════════════
//  SAVE GAME
// ════════════════════════════════════════════════════════
async function saveGame() {
    if (!state.hands.length) { alert('Inga händer registrerade.'); return; }

    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Sparar…';

    const payload = {
        action: 'save_game',
        game_date: state.gameDate,
        game_name: state.gameName,
        stat_players: state.stat_players,
        hands: state.hands,
        penalties: state.penalties
    };

    try {
        const res = await fetch('mobile-game.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('successTitle').textContent = `Match #${data.game_number} sparad!`;
            document.getElementById('successMsg').textContent = data.approved
                ? '✅ Matchen är godkänd och syns nu i statistiken.'
                : '⏳ Matchen väntar på godkännande från en admin.';
            document.getElementById('successModal').classList.add('open');
        } else {
            alert('Fel vid sparande: ' + (data.error || 'Okänt fel'));
            btn.disabled = false;
            btn.textContent = '💾 Spara match';
        }
    } catch (e) {
        alert('Nätverksfel – försök igen.');
        btn.disabled = false;
        btn.textContent = '💾 Spara match';
    }
}

// ════════════════════════════════════════════════════════
//  INIT
// ════════════════════════════════════════════════════════
document.getElementById('gameDate').addEventListener('change', e => {
    state.gameDate = e.target.value;
});

// Close modals on overlay click
['handModal', 'toolsModal', 'penaltyModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
    });
});

// ════════════════════════════════════════════════════════
//  AUTOSAVE – sparar löpande i localStorage
// ════════════════════════════════════════════════════════
const AUTOSAVE_KEY = 'vms_mobile_game_autosave';

function autosave() {
    try {
        const snapshot = {
            savedAt: new Date().toISOString(),
            page: currentPage,
            state: JSON.parse(JSON.stringify(state)),
            gameDate: document.getElementById('gameDate')?.value || '',
            gameName: document.getElementById('gameName')?.value || '',
        };
        localStorage.setItem(AUTOSAVE_KEY, JSON.stringify(snapshot));
    } catch(e) {
        console.warn('Autosave misslyckades:', e);
    }
}

// Exponera för session-keeper
window.autosaveNow = autosave;

function clearAutosave() {
    localStorage.removeItem(AUTOSAVE_KEY);
}

function tryRestoreAutosave() {
    try {
        const raw = localStorage.getItem(AUTOSAVE_KEY);
        if (!raw) return;
        const snap = JSON.parse(raw);

        // Ignorera spardata äldre än 8 timmar
        const age = (Date.now() - new Date(snap.savedAt).getTime()) / 1000;
        if (age > 28800) { clearAutosave(); return; }

        // Kontrollera att det finns meningsfullt speldata
        const hasGame = snap.state?.hands?.length > 0 || snap.state?.stat_players?.some(p => p);
        if (!hasGame) { clearAutosave(); return; }

        const savedTime = new Date(snap.savedAt).toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit' });
        const handsCount = snap.state?.hands?.length || 0;
        const playerNames = snap.state?.stat_players?.filter(p=>p).map(p=>p.name).join(', ') || '–';

        const msg = `📋 Osparad match hittades från ${savedTime}\n\n` +
                    `Spelare: ${playerNames}\n` +
                    `Händer registrerade: ${handsCount}\n\n` +
                    `Vill du återställa matchen?`;

        if (confirm(msg)) {
            restoreFromSnapshot(snap);
        } else {
            clearAutosave();
        }
    } catch(e) {
        console.warn('Återställning misslyckades:', e);
        clearAutosave();
    }
}

function restoreFromSnapshot(snap) {
    // Återställ state
    state.stat_players    = snap.state.stat_players    || [null,null,null,null];
    state.hands      = snap.state.hands      || [];
    state.penalties  = snap.state.penalties  || [];
    state.currentRound = snap.state.currentRound || 0;
    state.gameDate   = snap.state.gameDate   || '';
    state.gameName   = snap.state.gameName   || '';

    // Sätt formulärvärden
    const dateEl = document.getElementById('gameDate');
    const nameEl = document.getElementById('gameName');
    if (dateEl && snap.gameDate) dateEl.value = snap.gameDate;
    if (nameEl && snap.gameName) nameEl.value = snap.gameName;

    // Visa spelarnamn i slots
    state.stat_players.forEach((p, i) => {
        if (!p) return;
        const slot = document.getElementById('slot' + (i+1));
        if (slot) slot.querySelector('.slot-name').textContent = p.name;
    });

    // Gå till spelplanen om händer finns
    if (state.hands.length > 0) {
        initScoreboard();
        renderHandList();
        updateScoreboard();
        showPage('page-game');
        showRestoreBanner(state.hands.length);
    }
}

function showRestoreBanner(handsCount) {
    const banner = document.createElement('div');
    banner.style.cssText = [
        'position:fixed','top:0','left:0','right:0','z-index:9999',
        'background:#2e7d32','color:white','text-align:center',
        'padding:12px 20px','font-size:14px','font-weight:500',
        'box-shadow:0 2px 8px rgba(0,0,0,0.3)'
    ].join(';');
    banner.textContent = `✓ Match återställd – ${handsCount} händer laddade`;
    document.body.appendChild(banner);
    setTimeout(() => banner.remove(), 4000);
}

// Autosave-triggers: efter varje hand och penalty
const _origSubmitHand = submitHand;
submitHand = function() { _origSubmitHand(); autosave(); };

// Autosave vid startGame
const _origStartGame = startGame;
startGame = function() { _origStartGame(); autosave(); };

// Rensa autosave när matchen sparas lyckat
const _origSaveGame = saveGame;
saveGame = async function() {
    await _origSaveGame();
    // Rensa bara om sparet verkade lyckas (saveGame navigerar bort vid lyckad sparning)
    setTimeout(() => { clearAutosave(); }, 2000);
};

// Periodisk autosave var 30:e sekund
setInterval(autosave, 30000);

// Session heartbeat — håller sessionen vid liv under lång match
setInterval(() => {
    fetch('api/session_heartbeat.php')
    .then(r => r.json())
    .then(data => {
        if (!data.success || data.session_expired) {
            alert('Din session har gått ut. Sidan laddas om.');
            location.reload();
        }
    })
    .catch(() => {});
}, 120000); // var 2:a minut

// Försök återställa vid sidladdning
tryRestoreAutosave();
</script>
</body>
</html>
