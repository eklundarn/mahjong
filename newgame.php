<?php
require_once 'config.php';

if (!hasRole('superuser')) {
    $sv = (currentLang() === 'sv');
    showError(
        $sv 
            ? 'Du har inte behörighet till denna sida. Registrering av nya matcher görs via <strong><a href="games.php">Matcher</a></strong> och <strong><a href="mobile.php">Mobilregistrering</a></strong>.'
            : 'You do not have access to this page. Game registration is done via <strong><a href="games.php">Games</a></strong> and <strong><a href="mobile.php">Mobile registration</a></strong>.',
        $sv ? 'Ej behörig' : 'Access denied'
    );
}

$conn = getDbConnection();
$success = '';
$error = '';

// Hämta alla spelare för dropdowns
$player_sort = isset($_GET['player_sort']) ? cleanInput($_GET['player_sort']) : 'firstname';
if ($player_sort === 'number') {
    $stmt = $conn->query("SELECT id, first_name, last_name FROM stat_players WHERE club_member = 1 AND id NOT IN ('000000', '999999') ORDER BY id");
} elseif ($player_sort === 'lastname') {
    $stmt = $conn->query("SELECT id, first_name, last_name FROM stat_players WHERE club_member = 1 AND id NOT IN ('000000', '999999') ORDER BY last_name, first_name");
} else {
    // Sortera på förnamn som standard
    $stmt = $conn->query("SELECT id, first_name, last_name FROM stat_players WHERE club_member = 1 AND id NOT IN ('000000', '999999') ORDER BY first_name, last_name");
}
$all_players = $stmt->fetchAll();

// Hantera formulärinlämning
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_game'])) {
    
    $game_date = cleanInput($_POST['game_date']);
    $game_name = cleanInput($_POST['game_name'] ?? '');
    
    $player_a = cleanInput($_POST['player_a']);
    $player_b = cleanInput($_POST['player_b']);
    $player_c = cleanInput($_POST['player_c']);
    $player_d = cleanInput($_POST['player_d']);
    
    $penalty_a = (int)($_POST['penalty_a'] ?? 0);
    $penalty_b = (int)($_POST['penalty_b'] ?? 0);
    $penalty_c = (int)($_POST['penalty_c'] ?? 0);
    $penalty_d = (int)($_POST['penalty_d'] ?? 0);
    
    $false_hu_a = (int)($_POST['false_hu_a'] ?? 0);
    $false_hu_b = (int)($_POST['false_hu_b'] ?? 0);
    $false_hu_c = (int)($_POST['false_hu_c'] ?? 0);
    $false_hu_d = (int)($_POST['false_hu_d'] ?? 0);
    
    // Validering
    $errors = [];
    
    if (empty($game_date)) $errors[] = t('newgame_error_date');
    if (empty($player_a) || empty($player_b) || empty($player_c) || empty($player_d)) {
        $errors[] = t('newgame_error_all_players');
    }
    
    $selected_players = [$player_a, $player_b, $player_c, $player_d];
    if (count($selected_players) !== count(array_unique($selected_players))) {
        $errors[] = t('newgame_error_duplicate_players');
    }
    
    // Kolla om vi har hand-data (prioritera detta över quick mode)
    $has_hand_data = false;
    for ($h = 1; $h <= 16; $h++) {
        if (isset($_POST["hand_{$h}_hu"]) && (int)$_POST["hand_{$h}_hu"] > 0) {
            $has_hand_data = true;
            break;
        }
    }
    
    // Kolla om snabbregistrering används (men BARA om vi inte har hand-data!)
    $use_quick_mode = !$has_hand_data && (
        isset($_POST['quick_mini_a']) || isset($_POST['quick_mini_b']) || 
        isset($_POST['quick_mini_c']) || isset($_POST['quick_mini_d'])
    );
    
    // Om snabbregistrering - validera nullsumma
    if ($use_quick_mode) {
        $quick_a = (int)($_POST['quick_mini_a'] ?? 0);
        $quick_b = (int)($_POST['quick_mini_b'] ?? 0);
        $quick_c = (int)($_POST['quick_mini_c'] ?? 0);
        $quick_d = (int)($_POST['quick_mini_d'] ?? 0);
        
        $sum_before_penalties = $quick_a + $quick_b + $quick_c + $quick_d;
        if ($sum_before_penalties != 0) {
            $errors[] = t('newgame_error_minipoints') . $sum_before_penalties;
        }
    }
    
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } else {
        $hand_points = [0, 0, 0, 0]; // A, B, C, D
        $biggest_hand = 0;
        $biggest_hand_player = '';
        $hands_data = [];
        $antal_hander = 0; // Initiera för båda lägena
        
        if ($use_quick_mode) {
            // SNABBREGISTRERING - Läs från quick_mini-fälten och matchinfo
            $antal_hander = (int)($_POST['antal_hander'] ?? 0);
            $biggest_hand = (int)($_POST['biggest_hand'] ?? 0);
            
            // Hantera flera spelare för största hand (array)
            $biggest_hand_players_array = $_POST['biggest_hand_players'] ?? [];
            if (is_array($biggest_hand_players_array)) {
                $biggest_hand_player = implode(',', array_map('cleanInput', $biggest_hand_players_array));
            } else {
                $biggest_hand_player = cleanInput($biggest_hand_players_array);
            }
            
            $hand_points[0] = (int)($_POST['quick_mini_a'] ?? 0);
            $hand_points[1] = (int)($_POST['quick_mini_b'] ?? 0);
            $hand_points[2] = (int)($_POST['quick_mini_c'] ?? 0);
            $hand_points[3] = (int)($_POST['quick_mini_d'] ?? 0);
            
            // Läs hu-statistik från quick-mode formulär
            $zero_rounds = (int)($_POST['zero_rounds'] ?? 0);
            
            // Hu-statistik per spelare
            $hu_stats = [
                [
                    (int)($_POST['hu_a'] ?? 0),          // antal hu
                    (int)($_POST['selfdrawn_a'] ?? 0),   // selfdrawn
                    (int)($_POST['thrown_hu_a'] ?? 0)    // kastade hu
                ],
                [
                    (int)($_POST['hu_b'] ?? 0),
                    (int)($_POST['selfdrawn_b'] ?? 0),
                    (int)($_POST['thrown_hu_b'] ?? 0)
                ],
                [
                    (int)($_POST['hu_c'] ?? 0),
                    (int)($_POST['selfdrawn_c'] ?? 0),
                    (int)($_POST['thrown_hu_c'] ?? 0)
                ],
                [
                    (int)($_POST['hu_d'] ?? 0),
                    (int)($_POST['selfdrawn_d'] ?? 0),
                    (int)($_POST['thrown_hu_d'] ?? 0)
                ]
            ];
        }
        
        // Gå igenom alla 16 händer (endast om INTE snabbregistrering)
        if (!$use_quick_mode) {
        $biggest_hand_players = []; // Array för att samla alla med största hand
        
        // Initiera räknare för hu-statistik
        $hu_stats = [
            [0, 0, 0],  // Spelare A: [hu, selfdrawn, thrown_hu]
            [0, 0, 0],  // Spelare B
            [0, 0, 0],  // Spelare C
            [0, 0, 0]   // Spelare D
        ];
        $zero_rounds = 0;  // Antal 0-rundor
        
        for ($h = 1; $h <= 16; $h++) {
            $hu_points = (int)($_POST["hand_{$h}_hu"] ?? 0);
            $hu_player = (int)($_POST["hand_{$h}_player"] ?? 0);
            $from_player = (int)($_POST["hand_{$h}_from"] ?? 0);
            
            // Kolla om det är en 0-runda
            if ($hu_player === -1 || (isset($_POST["hand_{$h}_player"]) && $_POST["hand_{$h}_player"] === '-1')) {
                $zero_rounds++;
                continue; // Hoppa över resten för denna hand
            }
            
            if ($hu_points > 0 && $hu_player >= 1 && $hu_player <= 4) {
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
                
                // Spara största hand
                if ($hu_points > $biggest_hand) {
                    $biggest_hand = $hu_points;
                    $biggest_hand_players = [$selected_players[$hu_player - 1]]; // Ny störst - börja om
                } elseif ($hu_points == $biggest_hand && $biggest_hand > 0) {
                    // Samma poäng - lägg till spelare om inte redan finns
                    if (!in_array($selected_players[$hu_player - 1], $biggest_hand_players)) {
                        $biggest_hand_players[] = $selected_players[$hu_player - 1];
                    }
                }
                
                // Beräkna poäng för denna hand
                $winner_idx = $hu_player - 1;
                
                if ($from_player === 0) {
                    // Självdragen - alla andra ger 8 + hu_points
                    for ($i = 0; $i < 4; $i++) {
                        if ($i === $winner_idx) {
                            $hand_points[$i] += (8 + $hu_points) * 3;
                        } else {
                            $hand_points[$i] -= (8 + $hu_points);
                        }
                    }
                } else {
                    // Någon annan slängde
                    // Alla får först -8, sedan den som slängde ger ytterligare -hu_points
                    $thrower_idx = $from_player - 1;
                    for ($i = 0; $i < 4; $i++) {
                        if ($i === $winner_idx) {
                            // Vinnaren får: +8 från de två andra + (8 + hu_points) från den som slängde
                            $hand_points[$i] += 8 + 8 + (8 + $hu_points);
                        } elseif ($i === $thrower_idx) {
                            // Den som slängde: -8 - hu_points
                            $hand_points[$i] -= (8 + $hu_points);
                        } else {
                            // De andra två: bara -8
                            $hand_points[$i] -= 8;
                        }
                    }
                }
                
                // Spara handdata
                $hands_data[$h] = [
                    'hu_points' => $hu_points,
                    'hu_player' => $hu_player,
                    'from_player' => $from_player
                ];
            }
        }
        } // Slut på if (!$use_quick_mode)
        
        // Applicera straff (alltid minus, påverkar bara den spelaren)
        $hand_points[0] -= abs($penalty_a);
        $hand_points[1] -= abs($penalty_b);
        $hand_points[2] -= abs($penalty_c);
        $hand_points[3] -= abs($penalty_d);
        
        // Applicera False Hu (påverkar bara den spelaren, sprids INTE)
        // Användaren ansvarar för att skriva in värdena så det blir nollsummespel
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
        
        $table_points = [4, 2, 1, 0];
        for ($i = 0; $i < 4; $i++) {
            $same_count = 1;
            $sum_points = $table_points[$i];
            
            for ($j = $i + 1; $j < 4; $j++) {
                if ($players_data[$j]['mini'] === $players_data[$i]['mini']) {
                    $same_count++;
                    $sum_points += $table_points[$j];
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
        
        $table_a = $table_b = $table_c = $table_d = 0;
        foreach ($players_data as $p) {
            if ($p['vms'] === $player_a) $table_a = $p['table'];
            if ($p['vms'] === $player_b) $table_b = $p['table'];
            if ($p['vms'] === $player_c) $table_c = $p['table'];
            if ($p['vms'] === $player_d) $table_d = $p['table'];
        }
        
        // Konvertera biggest_hand_players array till kommaseparerad sträng
        $biggest_hand_player = !empty($biggest_hand_players) ? implode(',', $biggest_hand_players) : '';
        
        // Spara till databasen
        try {
            $conn->beginTransaction();
            
            $game_year = date('Y', strtotime($game_date));
            
            // Hitta nästa lediga matchnummer (med lås)
            $stmt = $conn->prepare("
                SELECT COALESCE(MAX(game_number), 0) + 1 as next_number
                FROM stat_games 
                WHERE game_year = ?
                FOR UPDATE
            ");
            $stmt->execute([$game_year]);
            $game_number = $stmt->fetch()['next_number'];
            
            $stmt = $conn->prepare("
                INSERT INTO stat_games (
                    game_number, game_name, game_date, game_year,
                    biggest_hand_points, biggest_hand_player_id, hands_played, zero_rounds,
                    player_a_id, player_a_minipoints, player_a_tablepoints, player_a_penalties, player_a_false_hu,
                    player_a_hu, player_a_selfdrawn, player_a_thrown_hu,
                    player_b_id, player_b_minipoints, player_b_tablepoints, player_b_penalties, player_b_false_hu,
                    player_b_hu, player_b_selfdrawn, player_b_thrown_hu,
                    player_c_id, player_c_minipoints, player_c_tablepoints, player_c_penalties, player_c_false_hu,
                    player_c_hu, player_c_selfdrawn, player_c_thrown_hu,
                    player_d_id, player_d_minipoints, player_d_tablepoints, player_d_penalties, player_d_false_hu,
                    player_d_hu, player_d_selfdrawn, player_d_thrown_hu,
                    detailed_entry, approved, created_by_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Bestäm om detaljerad: om vi har händer = detaljerad, annars quick mode
            $detailed_entry_value = (!empty($hands_data)) ? 1 : ($use_quick_mode ? 0 : 1);
            
            // Superuser, admin och mainadmin godkänner automatiskt, registratorer behöver godkännande
            $approved = canApproveGames() ? 1 : 0;
            
            $stmt->execute([
                $game_number, $game_name, $game_date, $game_year,
                $biggest_hand, $biggest_hand_player, $antal_hander, $zero_rounds,
                $player_a, $hand_points[0], $table_a, $penalty_a, $false_hu_a,
                $hu_stats[0][0], $hu_stats[0][1], $hu_stats[0][2],  // hu, selfdrawn, thrown_hu
                $player_b, $hand_points[1], $table_b, $penalty_b, $false_hu_b,
                $hu_stats[1][0], $hu_stats[1][1], $hu_stats[1][2],
                $player_c, $hand_points[2], $table_c, $penalty_c, $false_hu_c,
                $hu_stats[2][0], $hu_stats[2][1], $hu_stats[2][2],
                $player_d, $hand_points[3], $table_d, $penalty_d, $false_hu_d,
                $hu_stats[3][0], $hu_stats[3][1], $hu_stats[3][2],
                $detailed_entry_value,
                $approved,
                $_SESSION['user_id'] ?? null
            ]);
            
            $game_id = $conn->lastInsertId();
            
            // Spara händer
            foreach ($hands_data as $hand_num => $hand) {
                // Beräkna poäng per spelare för denna hand (för visning senare)
                $hp = [0, 0, 0, 0];
                $winner = $hand['hu_player'] - 1;
                
                if ($hand['from_player'] === 0) {
                    // Självdragen
                    for ($i = 0; $i < 4; $i++) {
                        if ($i === $winner) {
                            $hp[$i] = ($hand['hu_points'] + 8) * 3;
                        } else {
                            $hp[$i] = -($hand['hu_points'] + 8);
                        }
                    }
                } else {
                    // Från annan spelare
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
            
            if ($approved == 0) {
                $success = sprintf(t('newgame_success_pending'), $game_number);
            } else {
                $success = sprintf(t('newgame_success_approved'), $game_number);
            }
            $_POST = [];
            
        } catch (PDOException $e) {
            $conn->rollBack();
            if ($e->getCode() == 23000) {
                $error = "Ett fel uppstod vid sparande av matchen.";
            } else {
                $error = "Kunde inte spara match: " . $e->getMessage();
            }
        }
    }
}

// Hantera importerad data från import-game.php
$import_mode = isset($_POST['import_mode']) ? (int)$_POST['import_mode'] : 0;
$import_data = [];

if ($import_mode === 1 && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['save_game'])) {
    // Ta emot all importerad data
    $import_data = [
        'game_date' => $_POST['game_date'] ?? date('Y-m-d'),
        'game_name' => $_POST['game_name'] ?? '',
        'player_a' => $_POST['player_a'] ?? '',
        'player_b' => $_POST['player_b'] ?? '',
        'player_c' => $_POST['player_c'] ?? '',
        'player_d' => $_POST['player_d'] ?? '',
        'biggest_hand' => $_POST['biggest_hand'] ?? 0,
        'hands' => []
    ];
    
    // Samla alla händer
    for ($i = 1; $i <= 16; $i++) {
        if (isset($_POST["hand_{$i}_winner"])) {
            $import_data['hands'][$i] = [
                'winner' => (int)$_POST["hand_{$i}_winner"],
                'discarder' => (int)$_POST["hand_{$i}_discarder"],
                'points' => (int)$_POST["hand_{$i}_points"]
            ];
        }
    }
}

includeHeader();
?>

<style>
.hand-row {
    display: grid;
    grid-template-columns: 80px 120px 120px 120px 1fr;
    gap: 10px;
    align-items: center;
    padding: 8px;
    border-bottom: 1px solid #eee;
}
.hand-row:hover {
    background: #f9f9f9;
}
.hand-input {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 100%;
}
.calculated-points {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 5px;
    font-size: 0.9em;
}
.point-box {
    padding: 5px;
    text-align: center;
    border-radius: 3px;
    font-weight: bold;
}
.point-positive { background: #c8e6c9; color: #2e7d32; }
.point-negative { background: #ffcdd2; color: #c62828; }
.point-zero { background: #f5f5f5; color: #757575; }
</style>

<!-- i18n: dynamiskt språkbyte utan reload -->
<script>
(function() {
    // Alla översättningar matas ut från PHP vid sidladdning
    window.translations = window.translations || {};
    const translations = window.translations;
    Object.assign(translations, {
        sv: <?php
            // Läs sv.php direkt
            $i18n_sv = [];
            $i18n_en = [];
            $i18n_lang_tmp = [];
            if (file_exists(__DIR__ . '/lang/sv.php')) {
                $i18n_lang_tmp = [];
                $lang_backup = $lang ?? [];
                include __DIR__ . '/lang/sv.php';
                $i18n_sv = $lang;
                $lang = $lang_backup;
            }
            if (file_exists(__DIR__ . '/lang/en.php')) {
                $lang_backup = $lang ?? [];
                include __DIR__ . '/lang/en.php';
                $i18n_en = $lang;
                $lang = $lang_backup;
            }
            echo json_encode($i18n_sv, JSON_UNESCAPED_UNICODE);
        ?>,
        en: <?php echo json_encode($i18n_en, JSON_UNESCAPED_UNICODE); ?>
    });

    // Aktuellt språk - globalt tillgängligt
    // PHP-session är alltid master - localStorage används bara för omedelbar UI-uppdatering
    const _phpLang = '<?php echo currentLang(); ?>';
    window.currentLanguage = _phpLang;
    let currentLanguage = _phpLang;
    // Synka localStorage med session
    document.addEventListener('DOMContentLoaded', function() {
        const dbg = document.getElementById('debug_js_lang');
        if (dbg) dbg.textContent = window.currentLanguage + ' (phpLang=' + _phpLang + ')';
        const dbgT = document.getElementById('debug_trans');
        if (dbgT) dbgT.textContent = Object.keys(window.translations).join(',');
    });
    localStorage.setItem('vms_lang', _phpLang);

    // Översätt hela sidan
    function translatePage(lang) {
        if (!translations[lang]) return;
        currentLanguage = lang;
        window.currentLanguage = lang;  // Sätt globalt DIREKT så updateSummary ser rätt språk
        const t = translations[lang];

        // data-i18n attribut på text-element
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            if (t[key]) el.textContent = t[key];
        });

        // data-i18n-placeholder attribut
        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const key = el.getAttribute('data-i18n-placeholder');
            if (t[key]) el.placeholder = t[key];
        });

        // Speciellt: penalty-headers med spelarnamn (data-i18n-player)
        document.querySelectorAll('[data-i18n-player]').forEach(el => {
            const letter = el.getAttribute('data-i18n-player');
            if (t['newgame_player_label']) el.textContent = t['newgame_player_label'] + ' ' + letter;
        });

        // Spelare-labels med kolon (data-i18n-player-label)
        document.querySelectorAll('[data-i18n-player-label]').forEach(el => {
            const letter = el.getAttribute('data-i18n-player-label');
            if (t['newgame_player_label']) el.textContent = t['newgame_player_label'] + ' ' + letter + ':';
        });

        // Alla <option> med data-i18n
        document.querySelectorAll('option[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            if (t[key]) el.textContent = t[key];
        });

        // Uppdatera spelarnamn med rätt språk
        if (typeof filterPlayers === 'function') filterPlayers();

        // Uppdatera summary_label via updateSummary (räknar om med rätt språk)
        if (typeof updateSummary === 'function') updateSummary();

        // Uppdatera flaggknappen - visar AKTIVA språket (samma konvention som header.php)
        const langSwitcher = document.querySelector('.lang-switcher a');
        if (langSwitcher) {
            const img = langSwitcher.querySelector('img');
            let textNode = null;
            langSwitcher.childNodes.forEach(n => { if (n.nodeType === 3) textNode = n; });
            if (lang === 'sv') {
                // Aktiv = svenska → visa SV-flagga, länk byter till EN
                langSwitcher.setAttribute('href', '?lang=en');
                if (img) { img.src = 'img/flag_sv.svg'; img.alt = 'SV'; }
                if (textNode) textNode.textContent = ' SV';
            } else {
                // Aktiv = engelska → visa GB-flagga, länk byter till SV
                langSwitcher.setAttribute('href', '?lang=sv');
                if (img) { img.src = 'img/flag_gb.svg'; img.alt = 'GB'; }
                if (textNode) textNode.textContent = ' GB';
            }
        }

        // Spara i localStorage
        localStorage.setItem('vms_lang', lang);

        // Synka sessionen via dedikerad endpoint (inga sideffekter)
        fetch('set-lang.php?lang=' + lang);
    }

    // Exponera translatePage globalt
    window.translatePage = translatePage;

    // Fånga upp språkklick via event delegation på document
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (!link) return;
        const href = link.getAttribute('href') || '';
        if (!href.includes('lang=')) return;
        e.preventDefault();
        const match = href.match(/lang=([a-z]+)/);
        if (match) translatePage(match[1]);
    });
})();
</script>

<h2 data-i18n="newgame_title"><?php echo t('newgame_title'); ?></h2>

<div style="margin: 20px 0;">
    
    <div style="float: right;">
        <strong data-i18n="newgame_sort_players"><?php echo t('newgame_sort_players'); ?></strong>
        <a href="?player_sort=name" 
           class="btn <?php echo (!isset($_GET['player_sort']) || $_GET['player_sort'] === 'name') ? '' : 'btn-secondary'; ?>" 
           style="padding: 8px 15px; font-size: 0.9em;">
            <span data-i18n="newgame_sort_name"><?php echo t('newgame_sort_name'); ?></span>
        </a>
        <a href="?player_sort=number" 
           class="btn <?php echo (isset($_GET['player_sort']) && $_GET['player_sort'] === 'number') ? '' : 'btn-secondary'; ?>" 
           style="padding: 8px 15px; font-size: 0.9em;">
            <span data-i18n="newgame_sort_number"><?php echo t('newgame_sort_number'); ?></span>
        </a>
    </div>
    <div style="clear: both;"></div>
</div>

<?php if ($success): ?>
    <div class="message success">
        <?php echo htmlspecialchars($success); ?>
        <p style="margin-top: 10px;">
            <a href="games.php" data-i18n="newgame_view_all"><?php echo t('newgame_view_all'); ?></a> | 
            <a href="newgame.php" data-i18n="newgame_register_new"><?php echo t('newgame_register_new'); ?></a>
        </p>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="message error">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<form method="POST" action="newgame.php" id="gameForm">
    
    <!-- MATCHINFORMATION -->
    <fieldset style="border: 2px solid #4CAF50; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;" data-i18n="newgame_legend_info"><?php echo t('newgame_legend_info'); ?></legend>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="game_date" data-i18n="newgame_date_label"><?php echo t('newgame_date_label'); ?></label>
                <input type="date" id="game_date" name="game_date" required
                       value="<?php echo $import_data['game_date'] ?? $_POST['game_date'] ?? date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group">
                <label for="game_name" data-i18n="newgame_name_label"><?php echo t('newgame_name_label'); ?></label>
                <input type="text" id="game_name" name="game_name" 
                       placeholder="<?php echo t('newgame_name_placeholder'); ?>" data-i18n-placeholder="newgame_name_placeholder"
                       value="<?php echo $import_data['game_name'] ?? $_POST['game_name'] ?? ''; ?>">
                <small data-i18n="newgame_name_hint"><?php echo t('newgame_name_hint'); ?></small>
            </div>
        </div>
    </fieldset>
    
    <!-- SPELARE -->
    <fieldset style="border: 2px solid #2196F3; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;" data-i18n="newgame_legend_players"><?php echo t('newgame_legend_players'); ?></legend>
        
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
            <?php 
            $player_labels = ['A', 'B', 'C', 'D'];
            foreach ($player_labels as $label): 
                $player_var = 'player_' . strtolower($label);
            ?>
            <div>
                <label for="<?php echo $player_var; ?>"><strong data-i18n-player-label="<?php echo $label; ?>"><?php echo t('newgame_player_label') . ' ' . $label; ?>:</strong> *</label>
                <select id="<?php echo $player_var; ?>" 
                        name="<?php echo $player_var; ?>" 
                        required
                        onchange="filterPlayers(); updateTotals();"
                        style="width: 100%;">
                    <option value="" data-i18n="newgame_select_player"><?php echo t('newgame_select_player'); ?></option>
                    <?php foreach ($all_players as $p): 
                        $selected_value = $import_data[$player_var] ?? $_POST[$player_var] ?? '';
                        $is_selected = ($selected_value === $p['id']);
                    ?>
                        <option value="<?php echo htmlspecialchars($p['id']); ?>"
                                <?php echo $is_selected ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['id'] . ' - ' . $p['first_name'] . ' ' . $p['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top: 10px;">
            <a href="players-all.php" target="_blank" style="font-size: 0.9em;">
                ➕ <?php echo t('newgame_player_missing'); ?>
            </a>
        </div>
        
        <!-- SNABBREGISTRERING CHECKBOX -->
        <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 5px; border: 2px solid #2196F3;">
            <label style="font-size: 1.1em; display: flex; align-items: center; cursor: pointer;">
                <input type="checkbox" id="quick_mode" onchange="toggleQuickMode()" style="width: 20px; height: 20px; margin-right: 10px;"
                       <?php echo $use_quick_mode ? 'checked' : ''; ?>>
                <strong>⚡ <span data-i18n="newgame_legend_quick"><?php echo t('newgame_legend_quick'); ?></span></strong>
            </label>
            <div style="font-size: 0.9em; color: #666; margin-top: 5px; margin-left: 30px;">
                <span data-i18n="newgame_quick_desc"><?php echo t('newgame_quick_desc'); ?></span>
            </div>
        </div>
    </fieldset>
    
    <!-- HÄNDER -->
    <fieldset id="hands_fieldset" style="border: 2px solid #f5576c; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;" data-i18n="newgame_legend_hands"><?php echo t('newgame_legend_hands'); ?></legend>
        
        
        <div style="font-weight: bold; margin-bottom: 10px;" class="hand-row">
            <div>Hand #</div>
            <div data-i18n="newgame_col_hu"><?php echo t('newgame_col_hu'); ?></div>
            <div data-i18n="newgame_col_winner"><?php echo t('newgame_col_winner'); ?></div>
            <div data-i18n="newgame_col_from"><?php echo t('newgame_col_from'); ?></div>
            <div data-i18n="newgame_col_calc"><?php echo t('newgame_col_calc'); ?></div>
        </div>
        
        <?php for ($h = 1; $h <= 16; $h++):
            // Hämta importerade värden om de finns
            $imp_hand    = $import_data['hands'][$h] ?? null;
            $imp_hu      = $imp_hand ? $imp_hand['points']    : ($_POST["hand_{$h}_hu"]     ?? '');
            $imp_winner  = $imp_hand ? $imp_hand['winner']    : ($_POST["hand_{$h}_player"] ?? '0');
            $imp_from    = $imp_hand ? $imp_hand['discarder'] : ($_POST["hand_{$h}_from"]   ?? '');
            // Nollrunda: winner=-1 i import
            $is_zero     = $imp_hand && $imp_hand['winner'] === -1;
            if ($is_zero) { $imp_winner = 'zero'; $imp_hu = 0; }
        ?>
        <div class="hand-row">
            <div><strong>Hand <?php echo $h; ?></strong></div>
            <div>
                <input type="number"
                       name="hand_<?php echo $h; ?>_hu"
                       id="hand_<?php echo $h; ?>_hu"
                       class="hand-input"
                       min="0"
                       placeholder=""
                       value="<?php echo htmlspecialchars((string)$imp_hu); ?>"
                       onchange="updateTotals()">
            </div>
            <div>
                <select name="hand_<?php echo $h; ?>_player"
                        id="hand_<?php echo $h; ?>_player"
                        class="hand-input"
                        onchange="updateTotals()">
                    <option value="0"<?php echo $imp_winner == '0'  ? ' selected' : ''; ?>>-</option>
                    <option value="1"<?php echo $imp_winner == '1'  ? ' selected' : ''; ?>>1 (A)</option>
                    <option value="2"<?php echo $imp_winner == '2'  ? ' selected' : ''; ?>>2 (B)</option>
                    <option value="3"<?php echo $imp_winner == '3'  ? ' selected' : ''; ?>>3 (C)</option>
                    <option value="4"<?php echo $imp_winner == '4'  ? ' selected' : ''; ?>>4 (D)</option>
                    <option value="zero" data-i18n="newgame_zero_round"<?php echo $imp_winner === 'zero' ? ' selected' : ''; ?>><?php echo t('newgame_zero_round'); ?></option>
                </select>
            </div>
            <div>
                <select name="hand_<?php echo $h; ?>_from"
                        id="hand_<?php echo $h; ?>_from"
                        class="hand-input"
                        onchange="updateTotals()">
                    <option value=""<?php echo $imp_from === ''  ? ' selected' : ''; ?> data-i18n="newgame_select_from"><?php echo t('newgame_select_from'); ?></option>
                    <option value="0"<?php echo $imp_from == '0' ? ' selected' : ''; ?> data-i18n="newgame_selfdrawn"><?php echo t('newgame_selfdrawn'); ?></option>
                    <option value="1"<?php echo $imp_from == '1' ? ' selected' : ''; ?>>1 (A)</option>
                    <option value="2"<?php echo $imp_from == '2' ? ' selected' : ''; ?>>2 (B)</option>
                    <option value="3"<?php echo $imp_from == '3' ? ' selected' : ''; ?>>3 (C)</option>
                    <option value="4"<?php echo $imp_from == '4' ? ' selected' : ''; ?>>4 (D)</option>
                </select>
            </div>
            <div id="hand_<?php echo $h; ?>_calc" class="calculated-points"></div>
        </div>
        <?php endfor; ?>

        <!-- SUMMERINGSRAD -->
        <div style="margin-top: 15px; padding-top: 10px; border-top: 2px solid #4CAF50;">
            <div class="hand-row" style="font-weight: bold;">
                <div style="font-size: 0.85em; color: #2c5f2d;" id="summary_label"><?php echo t('newgame_after_hands_0'); ?></div>
                <div></div>
                <div></div>
                <div></div>
                <div class="calculated-points">
                    <div class="point-box point-zero" id="summary_a">0</div>
                    <div class="point-box point-zero" id="summary_b">0</div>
                    <div class="point-box point-zero" id="summary_c">0</div>
                    <div class="point-box point-zero" id="summary_d">0</div>
                </div>
            </div>
        </div>
    </fieldset>
    
    <!-- SNABBREGISTRERING AVSNITT -->
    <fieldset id="quick_section" style="border: 2px solid #2196F3; padding: 20px; margin: 20px 0; border-radius: 5px; display: none;">
        <legend style="font-weight: bold; font-size: 1.2em;" data-i18n="newgame_legend_quick"><?php echo t('newgame_legend_quick'); ?></legend>
        
        <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo t('newgame_quick_fill_desc'); ?>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
            <?php foreach ($player_labels as $label): ?>
            <div style="text-align: center;">
                <div id="quick_player_name_<?php echo strtolower($label); ?>" style="font-weight: bold; margin-bottom: 10px; min-height: 20px;"><?php echo t('newgame_player_label') . ' ' . $label; ?></div>
                <input type="number" 
                       id="quick_mini_<?php echo strtolower($label); ?>" 
                       name="quick_mini_<?php echo strtolower($label); ?>"
                       placeholder="<?php echo t('newgame_minipoints_placeholder'); ?>" data-i18n-placeholder="newgame_minipoints_placeholder"
                       value="<?php echo isset($_POST['quick_mini_' . strtolower($label)]) ? htmlspecialchars($_POST['quick_mini_' . strtolower($label)]) : ''; ?>"
                       onchange="updateQuickTotals()"
                       style="width: 100%; padding: 15px; font-size: 1.3em; text-align: center; border: 2px solid #2196F3; border-radius: 5px;">
            </div>
            <?php endforeach; ?>
        </div>

    <!-- MATCHINFORMATION -->
    <div style="margin-top: 30px; padding: 20px; background: #f5f5f5; border-radius: 5px;">
        <h4 style="margin-top: 0;">📊 Matchinformation</h4>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="antal_hander"><?php echo t('newgame_hands_played_label'); ?></label>
                <input type="number" 
                       id="antal_hander" 
                       name="antal_hander" 
                       min="0"
                       max="16"
                       value="<?php echo $_POST['antal_hander'] ?? 0; ?>">
            </div>
            
            <div class="form-group">
                <label for="biggest_hand"><?php echo t('newgame_biggest_hand_label'); ?></label>
                <input type="number" 
                       id="biggest_hand" 
                       name="biggest_hand" 
                       min="0"
                       value="<?php echo $_POST['biggest_hand'] ?? 0; ?>">
            </div>
            
            <div class="form-group">
                <label for="biggest_hand_players"><?php echo t('newgame_biggest_hand_players_label'); ?></label>
                <select id="biggest_hand_players" name="biggest_hand_players[]" multiple 
                        style="height: 100px; width: 100%;">
                    <!-- Populeras dynamiskt av JavaScript baserat på valda spelare -->
                </select>
                <small style="color: #666; display: block; margin-top: 5px;">
                    <?php echo t('newgame_biggest_hand_hint'); ?>
                </small>
            </div>
        </div>
    </div>
    
    <!-- HU-STATISTIK -->
    <fieldset style="border: 2px solid #4CAF50; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;">🎯 <?php echo t('newgame_legend_hu_stats'); ?></legend>
        
        <div style="margin-bottom: 20px;">
            <div class="form-group">
                <label for="zero_rounds">Antal 0-rundor i matchen:</label>
                <input type="number" 
                       id="zero_rounds" 
                       name="zero_rounds" 
                       min="0"
                       max="16"
                       value="<?php echo $_POST['zero_rounds'] ?? 0; ?>"
                       style="width: 150px;">
                <small style="color: #666; display: block; margin-top: 5px;">
                    <?php echo t('newgame_zero_rounds_hint'); ?>
                </small>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
            <?php foreach ($player_labels as $label): ?>
            <div>
                <strong id="hu_player_header_<?php echo strtolower($label); ?>"><?php echo t('newgame_player_label') . ' ' . $label; ?></strong>
                <div style="font-size: 0.9em; color: #666; margin-bottom: 10px;" id="hu_player_id_ref_<?php echo strtolower($label); ?>"></div>
                
                <div style="margin: 10px 0;">
                    <label style="font-size: 0.9em;">Antal hu:</label>
                    <input type="number" 
                           name="hu_<?php echo strtolower($label); ?>" 
                           id="hu_<?php echo strtolower($label); ?>"
                           min="0"
                           value="<?php echo $_POST['hu_' . strtolower($label)] ?? 0; ?>"
                           style="width: 100%; padding: 5px;">
                    <small style="color: #666; font-size: 0.85em;"><?php echo t('newgame_won_hands'); ?></small>
                </div>
                
                <div style="margin: 10px 0;">
                    <label style="font-size: 0.9em;">Varav selfdrawn:</label>
                    <input type="number" 
                           name="selfdrawn_<?php echo strtolower($label); ?>" 
                           id="selfdrawn_<?php echo strtolower($label); ?>"
                           min="0"
                           value="<?php echo $_POST['selfdrawn_' . strtolower($label)] ?? 0; ?>"
                           style="width: 100%; padding: 5px;">
                    <small style="color: #666; font-size: 0.85em;"><?php echo t('newgame_selfdrawn_of_hu'); ?></small>
                </div>
                
                <div style="margin: 10px 0;">
                    <label style="font-size: 0.9em;">Kastade hu:</label>
                    <input type="number" 
                           name="thrown_hu_<?php echo strtolower($label); ?>" 
                           id="thrown_hu_<?php echo strtolower($label); ?>"
                           min="0"
                           value="<?php echo $_POST['thrown_hu_' . strtolower($label)] ?? 0; ?>"
                           style="width: 100%; padding: 5px;">
                    <small style="color: #666; font-size: 0.85em;">Kastade till andra</small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </fieldset>
    </fieldset>
    
    <!-- STRAFF OCH FALSE HU -->
    <fieldset style="border: 2px solid #ff9800; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;" data-i18n="newgame_legend_penalties"><?php echo t('newgame_legend_penalties'); ?></legend>
        
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
            <?php foreach ($player_labels as $label): ?>
            <div>
                <strong id="penalty_player_header_<?php echo strtolower($label); ?>" data-i18n-player="<?php echo $label; ?>"><?php echo t('newgame_player_label') . ' ' . $label; ?></strong>
                <div style="font-size: 0.9em; color: #666; margin-bottom: 10px;" id="penalty_player_id_ref_<?php echo strtolower($label); ?>"></div>
                <div style="margin: 10px 0;">
                    <label style="font-size: 0.9em;" data-i18n="newgame_penalties_label"><?php echo t('newgame_penalties_label'); ?></label>
                    <input type="number" 
                           name="penalty_<?php echo strtolower($label); ?>" 
                           id="penalty_<?php echo strtolower($label); ?>"
                           value="0"
                           onchange="smartUpdate()"
                           style="width: 100%; padding: 5px;">
                </div>
                <div style="margin: 10px 0;">
                    <label style="font-size: 0.9em;" data-i18n="newgame_false_hu_label"><?php echo t('newgame_false_hu_label'); ?></label>
                    <input type="number" 
                           name="false_hu_<?php echo strtolower($label); ?>" 
                           id="false_hu_<?php echo strtolower($label); ?>"
                           value="0"
                           onchange="smartUpdate()"
                           style="width: 100%; padding: 5px;">

                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </fieldset>
    
    <!-- TOTALER -->
    <fieldset style="border: 2px solid #9c27b0; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;" data-i18n="newgame_legend_total"><?php echo t('newgame_legend_total'); ?></legend>
        
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; text-align: center;">
            <?php foreach ($player_labels as $label): ?>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                <div style="font-weight: bold; margin-bottom: 5px;" id="total_player_header_<?php echo strtolower($label); ?>"><?php echo t('newgame_player_label') . ' ' . $label; ?></div>
                <div id="total_player_id_ref_<?php echo strtolower($label); ?>" style="font-size: 0.85em; color: #666; margin-bottom: 10px; min-height: 20px;"></div>
                <div style="font-size: 2em; font-weight: bold; color: #2c5f2d;" 
                     id="total_<?php echo strtolower($label); ?>">0</div>
                <div style="font-size: 0.8em; color: #666;" data-i18n="newgame_minipoints_label"><?php echo t('newgame_minipoints_label'); ?></div>
                <div style="margin-top: 10px; font-size: 1.5em; color: #2196F3;" 
                     id="table_<?php echo strtolower($label); ?>">0</div>
                <div style="font-size: 0.8em; color: #666;" data-i18n="newgame_tablepoints_label"><?php echo t('newgame_tablepoints_label'); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div id="biggest_hand_info" class="quick-reg-box" style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px; text-align: center; display: none;">
            <strong>🏆 <span data-i18n="newgame_biggest_hand_summary"><?php echo t('newgame_biggest_hand_summary'); ?></span>:</strong> <span id="biggest_hand_text"></span>
        </div>
    </fieldset>
    
    <!-- KNAPPAR -->
    <div class="form-group" style="margin-top: 30px;">
        <button type="submit" name="save_game" class="btn" style="font-size: 1.1em; padding: 15px 30px;">
            💾 <span data-i18n="newgame_save_btn"><?php echo t('newgame_save_btn'); ?></span>
        </button>
        <a href="newgame.php" class="btn btn-secondary" style="margin-left: 10px;">
            <span data-i18n="btn_cancel"><?php echo t('btn_cancel'); ?></span>
        </a>
    </div>
</form>

<script>
function updateBiggestHandPlayers() {
    const selectedPlayers = [
        {id: 'player_a', label: 'A'},
        {id: 'player_b', label: 'B'},
        {id: 'player_c', label: 'C'},
        {id: 'player_d', label: 'D'}
    ];
    
    const biggestHandSelect = document.getElementById('biggest_hand_players');
    if (!biggestHandSelect) return;
    
    // Spara nuvarande val
    const currentValues = Array.from(biggestHandSelect.selectedOptions).map(opt => opt.value);
    
    // Töm listan
    biggestHandSelect.innerHTML = '';
    
    // Lägg till endast valda spelare
    selectedPlayers.forEach(p => {
        const playerSelect = document.getElementById(p.id);
        if (playerSelect && playerSelect.value) {
            const option = document.createElement('option');
            option.value = playerSelect.value;
            option.textContent = playerSelect.selectedOptions[0].text;
            
            // Återställ val om det fanns
            if (currentValues.includes(playerSelect.value)) {
                option.selected = true;
            }
            
            biggestHandSelect.appendChild(option);
        }
    });
}

function filterPlayers() {
    const selectedPlayers = [
        document.getElementById('player_a').value,
        document.getElementById('player_b').value,
        document.getElementById('player_c').value,
        document.getElementById('player_d').value
    ].filter(v => v !== '');
    
    // Uppdatera biggest hand dropdown
    updateBiggestHandPlayers();
    
    // Uppdatera namn i alla sektioner
    ['a', 'b', 'c', 'd'].forEach(letter => {
        const playerSelect = document.getElementById('player_' + letter);
        const quickNameDiv = document.getElementById('quick_player_name_' + letter);
        const penaltyHeader = document.getElementById('penalty_player_header_' + letter);
        const penaltyVms = document.getElementById('penalty_player_id_ref_' + letter);
        const huHeader = document.getElementById('hu_player_header_' + letter);
        const huVms = document.getElementById('hu_player_id_ref_' + letter);
        const totalHeader = document.getElementById('total_player_header_' + letter);
        const totalVms = document.getElementById('total_player_id_ref_' + letter);
        
        if (playerSelect) {
            const selectedOption = playerSelect.selectedOptions[0];
            const _pl = (typeof translations !== 'undefined' && translations[currentLanguage]) ? (translations[currentLanguage]['newgame_player_label'] || 'Player') : 'Player';
            let nameOnly = _pl + ' ' + letter.toUpperCase();
            let vmsNumber = '';
            
            if (selectedOption && selectedOption.value) {
                // Format: "VMS0075 - Thomas Andersson"
                const parts = selectedOption.text.split(' - ');
                if (parts.length === 2) {
                    vmsNumber = parts[0];
                    nameOnly = parts[1];
                } else {
                    nameOnly = selectedOption.text;
                }
            }
            
            // Snabbregistrering: Bara namn
            if (quickNameDiv) quickNameDiv.textContent = nameOnly;
            
            // False Hu: Namn + VMS
            if (penaltyHeader) penaltyHeader.textContent = nameOnly;
            if (penaltyVms) penaltyVms.textContent = vmsNumber;
            
            // Hu-statistik: Namn + VMS
            if (huHeader) huHeader.textContent = nameOnly;
            if (huVms) huVms.textContent = vmsNumber;
            
            // Slutsumma: Namn + VMS
            if (totalHeader) totalHeader.textContent = nameOnly;
            if (totalVms) totalVms.textContent = vmsNumber;
        }
    });
    
    ['a', 'b', 'c', 'd'].forEach(function(playerLetter) {
        const select = document.getElementById('player_' + playerLetter);
        const currentValue = select.value;
        
        Array.from(select.options).forEach(function(option) {
            if (option.value === '') return;
            
            if (selectedPlayers.includes(option.value) && option.value !== currentValue) {
                option.disabled = true;
                option.style.color = '#ccc';
                if (!option.text.includes('[VALD]')) {
                    option.text = option.text + ' [VALD]';
                }
            } else {
                option.disabled = false;
                option.style.color = '';
                option.text = option.text.replace(' [VALD]', '');
            }
        });
    });
}

// Smart wrapper som anropar rätt uppdateringsfunktion beroende på läge
function smartUpdate() {
    const quickCheckbox = document.getElementById('quick_mode');
    if (quickCheckbox && quickCheckbox.checked) {
        updateQuickTotals();
    } else {
        updateTotals();
    }
}

function updateTotals() {
    let totals = [0, 0, 0, 0];
    let biggestHand = 0;
    let biggestHandPlayer = 0;
    
    // Beräkna från händer
    for (let h = 1; h <= 16; h++) {
        const huInput = document.getElementById('hand_' + h + '_hu');
        const playerSelect = document.getElementById('hand_' + h + '_player');
        const fromPlayerSelect = document.getElementById('hand_' + h + '_from');
        
        const huPoints = parseInt(huInput.value) || 0;
        const playerValue = playerSelect.value;
        const huPlayer = parseInt(playerValue) || 0;
        // fromPlayer beräknas nedan efter updateFromPlayerOptions()
        
        // Hantera 0-runda-alternativ
        if (playerValue === 'zero') {
            // Sätt hu-poäng till 0
            huInput.value = 0;
            // Visa grå 0:or
            let calcHtml = '<div class="calculated-points">';
            for (let i = 0; i < 4; i++) {
                calcHtml += '<div class="point-box point-zero">0</div>';
            }
            calcHtml += '</div>';
            document.getElementById('hand_' + h + '_calc').innerHTML = calcHtml;
            continue; // Hoppa över denna hand i beräkningen
        }
        
        // Uppdatera "från vem"-dropdown baserat på vald vinnare
        // Spara värdet först – updateFromPlayerOptions kan nollställa det om vinnaren = kastnaren
        const savedFromValue = fromPlayerSelect.value;
        updateFromPlayerOptions(h, huPlayer);
        if (fromPlayerSelect.value === '' && savedFromValue !== '') {
            fromPlayerSelect.value = savedFromValue;
        }
        const fromPlayer = fromPlayerSelect.value === '' ? -1 : parseInt(fromPlayerSelect.value);

        if (huPoints > 0 && huPlayer >= 1 && huPlayer <= 4 && fromPlayer >= 0) {
            // Spara största hand
            if (huPoints > biggestHand) {
                biggestHand = huPoints;
                biggestHandPlayer = huPlayer;
            }
            
            const winner = huPlayer - 1;
            let handPoints = [0, 0, 0, 0];
            
            if (fromPlayer === 0) {
                // Självdragen
                for (let i = 0; i < 4; i++) {
                    if (i === winner) {
                        handPoints[i] = (huPoints + 8) * 3;
                    } else {
                        handPoints[i] = -(huPoints + 8);
                    }
                }
            } else {
                // Från annan spelare
                const thrower = fromPlayer - 1;
                for (let i = 0; i < 4; i++) {
                    if (i === winner) {
                        handPoints[i] = 8 + 8 + (8 + huPoints);
                    } else if (i === thrower) {
                        handPoints[i] = -(8 + huPoints);
                    } else {
                        handPoints[i] = -8;
                    }
                }
            }
            
            // Visa beräknade poäng för denna hand
            let calcHtml = '<div class="calculated-points">';
            for (let i = 0; i < 4; i++) {
                const className = handPoints[i] > 0 ? 'point-positive' : (handPoints[i] < 0 ? 'point-negative' : 'point-zero');
                calcHtml += '<div class="point-box ' + className + '">' + (handPoints[i] > 0 ? '+' : '') + handPoints[i] + '</div>';
            }
            calcHtml += '</div>';
            document.getElementById('hand_' + h + '_calc').innerHTML = calcHtml;
            
            // Lägg till i totaler
            for (let i = 0; i < 4; i++) {
                totals[i] += handPoints[i];
            }
        } else {
            document.getElementById('hand_' + h + '_calc').innerHTML = '';
        }
    }
    
    // Applicera straff (alltid minus, påverkar bara den spelaren)
    ['a', 'b', 'c', 'd'].forEach((l, i) => {
        const p = parseInt(document.getElementById('penalty_' + l).value) || 0;
        totals[i] -= Math.abs(p);
    });
    
    // Applicera False Hu (påverkar bara den spelaren, sprids INTE)
    // Användaren skriver in alla false_hu manuellt så det blir nollsummespel totalt
    ['a', 'b', 'c', 'd'].forEach((l, i) => {
        const fh = parseInt(document.getElementById('false_hu_' + l).value) || 0;
        totals[i] += fh;  // Applicera direkt (respekterar tecken)
    });
    
    // Visa totaler
    ['a', 'b', 'c', 'd'].forEach((l, i) => {
        document.getElementById('total_' + l).textContent = totals[i];
    });
    
    // Beräkna bordspoäng - BARA om minst en hand är ifylld
    let tablePoints = [0, 0, 0, 0];
    let anyHandPlayed = false;
    
    // Kolla om någon hand är ifylld
    for (let h = 1; h <= 16; h++) {
        const hu = parseInt(document.getElementById('hand_' + h + '_hu').value) || 0;
        if (hu > 0) {
            anyHandPlayed = true;
            break;
        }
    }
    
    if (anyHandPlayed) {
        let stat_players = totals.map((mini, idx) => ({mini, idx}));
        stat_players.sort((a, b) => b.mini - a.mini);
        
        const tablePointsBase = [4, 2, 1, 0];
        
        for (let i = 0; i < 4; i++) {
            let sameCount = 1;
            let sumPoints = tablePointsBase[i];
            
            for (let j = i + 1; j < 4; j++) {
                if (stat_players[j].mini === stat_players[i].mini) {
                    sameCount++;
                    sumPoints += tablePointsBase[j];
                } else {
                    break;
                }
            }
            
            const shared = sumPoints / sameCount;
            for (let k = 0; k < sameCount; k++) {
                tablePoints[stat_players[i + k].idx] = shared;
            }
            i += (sameCount - 1);
        }
    }
    
    ['a', 'b', 'c', 'd'].forEach((l, i) => {
        document.getElementById('table_' + l).textContent = tablePoints[i].toFixed(1);
    });
    
    // Visa största hand med spelarnamn
    if (biggestHand > 0) {
        const playerLetters = ['A', 'B', 'C', 'D'];
        const playerSelects = ['player_a', 'player_b', 'player_c', 'player_d'];
        const playerSelect = document.getElementById(playerSelects[biggestHandPlayer - 1]);
        const playerName = playerSelect ? playerSelect.selectedOptions[0]?.text || '' : '';
        
        const _bh_tr = (window.translations && window.currentLanguage && window.translations[window.currentLanguage]) ? window.translations[window.currentLanguage] : {};
        const bh_pts = _bh_tr['stats_pub_points'] || 'points';
        const bh_by = _bh_tr['newgame_js_by'] || 'by';
        const bh_pl = _bh_tr['newgame_player_label'] || 'Player';
        document.getElementById('biggest_hand_text').textContent = 
            biggestHand + ' ' + bh_pts + ' - ' + bh_by + ' ' + bh_pl + ' ' + playerLetters[biggestHandPlayer - 1] +
            (playerName ? ' - ' + playerName : '');
        document.getElementById('biggest_hand_info').style.display = 'block';
    } else {
        document.getElementById('biggest_hand_info').style.display = 'none';
    }
}


// Toggle mellan normal och snabbregistrering
function toggleQuickMode() {
    const checkbox = document.getElementById('quick_mode');
    const quickSection = document.getElementById('quick_section');
    
    if (!quickSection) return;
    
    // Hitta händer-fieldset via id
    const handsFS = document.getElementById('hands_fieldset');
    if (!handsFS) return;
    
    if (checkbox.checked) {
        // Visa snabbregistrering, dölj händer
        handsFS.style.display = 'none';
        quickSection.style.display = 'block';
        
        // Kopiera spelarnamn till snabbregistrering
        ['a', 'b', 'c', 'd'].forEach(letter => {
            const playerSelect = document.getElementById('player_' + letter);
            const nameDiv = document.getElementById('quick_player_name_' + letter);
            if (playerSelect && nameDiv) {
                const selectedOption = playerSelect.selectedOptions[0];
                const name = selectedOption ? selectedOption.text : '';
                nameDiv.textContent = name;
            }
        });
        
        updateQuickTotals();
    } else {
        // Visa händer, dölj snabbregistrering
        handsFS.style.display = 'block';
        quickSection.style.display = 'none';
        updateTotals();
    }
}

// Uppdatera totaler i snabbregistrering
function updateQuickTotals() {
    let totals = [0, 0, 0, 0];
    
    // Läs minipoäng från snabbregistrering
    ['a', 'b', 'c', 'd'].forEach((letter, i) => {
        const input = document.getElementById('quick_mini_' + letter);
        if (input) {
            totals[i] = parseInt(input.value) || 0;
        }
    });
    
    // Dra av straff (alltid minus, oavsett tecken)
    ['a', 'b', 'c', 'd'].forEach((letter, i) => {
        const penaltyInput = document.getElementById('penalty_' + letter).value;
        const penalty = parseInt(penaltyInput) || 0;
        totals[i] -= Math.abs(penalty);
    });
    
    // Hantera False Hu i quick mode (lägg bara till/dra av för JUST DEN spelaren)
    // I quick mode kan flera spelare ha false hu samtidigt, så vi sprider INTE effekten
    ['a', 'b', 'c', 'd'].forEach((letter, i) => {
        const falseHuInput = document.getElementById('false_hu_' + letter).value;
        const falseHu = parseInt(falseHuInput) || 0;
        // Applicera direkt på spelaren (om -30 så -30, om 30 så +30)
        totals[i] += falseHu;
    });
    
    // Visa totaler
    ['a', 'b', 'c', 'd'].forEach((letter, i) => {
        document.getElementById('total_' + letter).textContent = totals[i];
    });
    
    // Beräkna bordspoäng - BARA om minst en minipoäng är ifylld
    let anyFilled = totals.some(t => t !== 0);
    let tablePoints = [0, 0, 0, 0];
    
    if (anyFilled) {
        let stat_players = totals.map((mini, idx) => ({mini, idx}));
        stat_players.sort((a, b) => b.mini - a.mini);
        
        const tablePointsBase = [4, 2, 1, 0];
        
        for (let i = 0; i < 4; i++) {
            let sameCount = 1;
            let sumPoints = tablePointsBase[i];
            
            for (let j = i + 1; j < 4; j++) {
                if (stat_players[j].mini === stat_players[i].mini) {
                    sameCount++;
                    sumPoints += tablePointsBase[j];
                } else {
                    break;
                }
            }
            
            const shared = sumPoints / sameCount;
            for (let k = 0; k < sameCount; k++) {
                tablePoints[stat_players[i + k].idx] = shared;
            }
            i += (sameCount - 1);
        }
    }
    
    ['a', 'b', 'c', 'd'].forEach((letter, i) => {
        document.getElementById('table_' + letter).textContent = tablePoints[i].toFixed(1);
    });
}

function updateFromPlayerOptions(handNumber, winningPlayer) {
    const fromSelect = document.getElementById('hand_' + handNumber + '_from');
    const currentValue = fromSelect.value;
    
    // Gå igenom alla options
    Array.from(fromSelect.options).forEach(function(option) {
        if (option.value === '' || option.value === '0') {
            // "Välj från vem" och "Muren" är alltid tillgängliga
            option.disabled = false;
            option.style.color = '';
        } else {
            const playerNum = parseInt(option.value);
            if (playerNum === winningPlayer) {
                // Vinnaren kan inte ge till sig själv
                option.disabled = true;
                option.style.color = '#ccc';
                // Om detta var det valda alternativet, återställ
                if (currentValue === option.value) {
                    fromSelect.value = '';
                }
            } else {
                option.disabled = false;
                option.style.color = '';
            }
        }
    });
}

window.addEventListener('DOMContentLoaded', function() {
    filterPlayers();
    updateTotals();
});

// Uppdatera summeringsrad
function updateSummary() {
    let handCount = 0;
    const handPoints = [0, 0, 0, 0]; // A, B, C, D
    
    // Räkna alla händer (med poäng ELLER markerade som 0-runda)
    for (let h = 1; h <= 16; h++) {
        const huInput = document.getElementById("hand_" + h + "_hu");
        const playerSelect = document.getElementById("hand_" + h + "_player");
        const calcDiv = document.getElementById("hand_" + h + "_calc");
        
        if (!calcDiv) continue;
        
        const hu = parseInt(huInput.value) || 0;
        const isZeroRound = playerSelect.value === 'zero';
        
        // Räkna hand om den har poäng ELLER är markerad som 0-runda
        if (hu > 0 || isZeroRound) {
            handCount++;
            
            // Läs de BERÄKNADE poängen från calc-diven
            const pointBoxes = calcDiv.querySelectorAll('.point-box');
            if (pointBoxes.length === 4) {
                for (let i = 0; i < 4; i++) {
                    const text = pointBoxes[i].textContent;
                    const points = parseInt(text) || 0;
                    handPoints[i] += points;
                }
            }
        }
    }
    
    // Uppdatera label
    const t_summary = (window.translations && window.currentLanguage && window.translations[window.currentLanguage]) ? window.translations[window.currentLanguage] : {};
    const pre = t_summary['newgame_after_hands_pre'] || '<?php echo t("newgame_after_hands_pre"); ?>';
    const post = t_summary['newgame_after_hands_post'] || '<?php echo t("newgame_after_hands_post"); ?>';
    document.getElementById("summary_label").textContent = pre + handCount + " " + post;
    
    // Uppdatera spelares poäng med färgkodning
    const playerIds = ["summary_a", "summary_b", "summary_c", "summary_d"];
    for (let i = 0; i < 4; i++) {
        const elem = document.getElementById(playerIds[i]);
        const points = handPoints[i];
        
        elem.textContent = points > 0 ? "+" + points : points;
        
        // Färgkodning med point-box classes
        elem.className = "point-box";
        if (points > 0) {
            elem.classList.add("point-positive");
        } else if (points < 0) {
            elem.classList.add("point-negative");
        } else {
            elem.classList.add("point-zero");
        }
    }
}

// Anropa updateSummary efter updateTotals
const originalUpdateTotals = updateTotals;
updateTotals = function() {
    originalUpdateTotals();
    updateSummary();
};

// Kör vid sidladdning
window.addEventListener("DOMContentLoaded", function() {
    updateSummary();
});


// Vid sidladdning - om quick_mode är checked, visa snabbregistrering
document.addEventListener('DOMContentLoaded', function() {
    // Uppdatera biggest hand dropdown vid sidladdning
    updateBiggestHandPlayers();
    
    const checkbox = document.getElementById('quick_mode');
    if (checkbox && checkbox.checked) {
        toggleQuickMode();
    }
    
    <?php if ($import_mode === 1 && !empty($import_data)): ?>
    // Data är redan förifylld av PHP via value/selected-attribut.
    // Kör updateTotals() för att visa beräknade poäng.
    updateTotals();
    <?php endif; ?>
});

// ════════════════════════════════════════════════════════
//  AUTOSAVE – sparar formulärdata i localStorage
// ════════════════════════════════════════════════════════
const AUTOSAVE_KEY_NG = 'vms_newgame_autosave';
const IS_IMPORT_MODE = <?php echo $import_mode === 1 ? 'true' : 'false'; ?>;

function autosaveNewgame() {
    try {
        const fields = {};
        // Spara alla input och select i formuläret
        document.querySelectorAll('input[name], select[name]').forEach(el => {
            if (el.type === 'checkbox') {
                fields[el.name] = el.checked;
            } else if (el.multiple) {
                fields[el.name] = Array.from(el.selectedOptions).map(o => o.value);
            } else {
                fields[el.name] = el.value;
            }
        });
        const snap = { savedAt: new Date().toISOString(), fields };
        localStorage.setItem(AUTOSAVE_KEY_NG, JSON.stringify(snap));
    } catch(e) { console.warn('Autosave misslyckades:', e); }
}

// Exponera för session-keeper
window.autosaveNow = autosaveNewgame;

function clearAutosaveNewgame() {
    localStorage.removeItem(AUTOSAVE_KEY_NG);
}

function tryRestoreNewgame() {
    // Hoppa över om vi precis kom från import-game (data redan ifylld av PHP)
    if (IS_IMPORT_MODE) { clearAutosaveNewgame(); return; }
    try {
        const raw = localStorage.getItem(AUTOSAVE_KEY_NG);
        if (!raw) return;
        const snap = JSON.parse(raw);
        const age = (Date.now() - new Date(snap.savedAt).getTime()) / 1000;
        if (age > 28800) { clearAutosaveNewgame(); return; }

        const f = snap.fields;
        // Kontrollera om det finns meningsfullt innehåll
        const hasData = Object.values(f).some(v => v && v !== '' && v !== false);
        if (!hasData) { clearAutosaveNewgame(); return; }

        const savedTime = new Date(snap.savedAt).toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit' });
        if (!confirm('📋 Ofullständigt ifyllt formulär hittades från ' + savedTime + '.\n\nVill du återställa det?')) {
            clearAutosaveNewgame(); return;
        }

        // Återställ fälten
        Object.entries(f).forEach(([name, val]) => {
            const els = document.querySelectorAll('[name="' + name + '"]');
            els.forEach(el => {
                if (el.type === 'checkbox') {
                    el.checked = val;
                } else if (el.multiple && Array.isArray(val)) {
                    Array.from(el.options).forEach(o => { o.selected = val.includes(o.value); });
                } else {
                    el.value = val;
                }
            });
        });

        // Trigga filterPlayers och updateTotals så UI uppdateras
        setTimeout(() => {
            if (typeof filterPlayers === 'function') filterPlayers();
            if (typeof updateTotals === 'function') updateTotals();
            if (typeof updateSummary === 'function') updateSummary();
        }, 300);

        // Visa banner
        const banner = document.createElement('div');
        banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:9999;background:#2e7d32;color:white;text-align:center;padding:10px 20px;font-size:14px;font-weight:500;box-shadow:0 2px 8px rgba(0,0,0,0.3)';
        banner.textContent = '✓ Formulär återställt från autosave';
        document.body.appendChild(banner);
        setTimeout(() => banner.remove(), 4000);

    } catch(e) { console.warn('Återställning misslyckades:', e); clearAutosaveNewgame(); }
}

// Rensa autosave när formuläret submittas (match sparas)
document.querySelector('form')?.addEventListener('submit', () => {
    setTimeout(clearAutosaveNewgame, 1000);
});

// Autosave var 20:e sekund + vid varje change-event
setInterval(autosaveNewgame, 20000);
document.addEventListener('change', autosaveNewgame);
document.addEventListener('input', autosaveNewgame);

// Försök återställa
tryRestoreNewgame();

</script>

<?php includeFooter(); ?>