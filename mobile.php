<?php
/**
 * mobile.php
 * Mobiloptimerad matchregistrering.
 * Inspirerad av Mahjong Tracker-appen men som webbsida.
 *
 * Upload: www/mobile.php
 */

// Session lifetime is handled by config.php (SESSION_LIFETIME setting)
// The custom session path in config.php protects against shared hosting GC

require_once 'config.php';

// Mobile translations
$sv = (currentLang() === 'sv');
$mt = [
    'title'       => $sv ? '🀄 Ny match' : '🀄 New Game',
    'date'        => $sv ? 'Datum' : 'Date',
    'gameName'    => $sv ? 'Matchnamn' : 'Game name',
    'gameNamePh'  => $sv ? 'Valfritt' : 'Optional',
    'tapSelect'   => $sv ? 'Tryck för att välja' : 'Tap to select player',
    'start'       => $sv ? '▶ STARTA' : '▶ START',
    'table'       => $sv ? '🀄 BORD' : '🀄 TABLE',    'list'        => $sv ? '📋 LISTA' : '📋 LIST',
    'draw'        => $sv ? '🏳 OAVGJORD' : '🏳 DRAW',
    'undo'        => $sv ? '↩ Ångra' : '↩ Undo',
    'penalty'     => $sv ? '⚠ Straff' : '⚠ Penalty',
    'endGame'     => $sv ? '🏁 Avsluta' : '🏁 End Game',
    'saveGame'    => $sv ? '💾 Spara match' : '💾 Save Game',
    'endGame'     => $sv ? '🏁 Avsluta match' : '🏁 End Game',
    'newGame'     => $sv ? '🀄 Ny match' : '🀄 New Game',
    'back'        => $sv ? '← Tillbaka' : '← Back',
    'results'     => $sv ? '🏆 Slutresultat' : '🏆 Final Results',
    'discarder'   => $sv ? 'Från vem?' : 'Discarder?',
    'points'      => $sv ? 'Poäng:' : 'Points:',
    'penTitle'    => $sv ? '⚠️ Straff' : '⚠️ Penalty',
    'penDist'     => $sv ? 'Fördela som plus till övriga' : 'Distribute as plus to other players',
    'close'       => $sv ? 'Stäng' : 'Close',
    'cancel'      => $sv ? 'Avbryt' : 'Cancel',
    'selectPlayer'=> $sv ? 'Välj spelare' : 'Select player',
    'searchPh'    => $sv ? 'Skriv 2+ bokstäver...' : 'Type 2+ letters to search...',
];

if (!isLoggedIn()) {
    $login_url = SITE_URL . '/login.php?redirect=' . urlencode('mobile.php');
    $msg = $sv
        ? 'Du har inte behörighet att öppna sidan eftersom du inte är inloggad. <a href="' . $login_url . '" style="color:#005B99;text-decoration:underline;font-weight:600;">Logga in här</a>'
        : 'You do not have access to this page because you are not logged in. <a href="' . $login_url . '" style="color:#005B99;text-decoration:underline;font-weight:600;">Log in here</a>';
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>';
    echo '<body style="font-family:\'Segoe UI\',sans-serif;padding:40px;text-align:center;">';
    echo '<h2>🔒 ' . ($sv ? 'Ej inloggad' : 'Not logged in') . '</h2>';
    echo '<p style="font-size:1.1em;line-height:1.6;">' . $msg . '</p>';
    echo '</body></html>';
    exit;
}

if (!hasRole('player')) {
    $logout_url = SITE_URL . '/logout.php';
    $msg = $sv
        ? 'Du har inte behörighet att visa sidan. <a href="' . $logout_url . '" style="color:#005B99;text-decoration:underline;font-weight:600;">Klicka här för att logga ut</a>. Logga sedan in med ett spelarkonto för att kunna registrera en match.'
        : 'You do not have permission to view this page. <a href="' . $logout_url . '" style="color:#005B99;text-decoration:underline;font-weight:600;">Click here to log out</a>. Then log in with a player account to register a game.';
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>';
    echo '<body style="font-family:\'Segoe UI\',sans-serif;padding:40px;text-align:center;">';
    echo '<h2>🚫 ' . ($sv ? 'Ingen behörighet' : 'Access denied') . '</h2>';
    echo '<p style="font-size:1.1em;line-height:1.6;">' . $msg . '</p>';
    echo '</body></html>';
    exit;
}

$conn = getDbConnection();

// Hämta aktiva spelare
$stmt = $conn->query("
    SELECT id, first_name, last_name 
    FROM stat_players 
    WHERE club_member = 1 AND active = 1
      AND id NOT IN (1, 2, 3)
    ORDER BY first_name, last_name
");
$all_players = $stmt->fetchAll();

// AJAX: Spara match
$_raw = file_get_contents('php://input');
$_json = json_decode($_raw, true);

// ============================================================
// DRAFT TABLES — auto-create if missing
// ============================================================
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS `stat_live_sessions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `draft_id` VARCHAR(36) NOT NULL UNIQUE,
        `created_by_id` VARCHAR(20) DEFAULT NULL,
        `players_json` TEXT NOT NULL,
        `game_date` DATE DEFAULT NULL,
        `game_name` VARCHAR(100) DEFAULT '',
        `status` ENUM('active','saved','discarded') DEFAULT 'active',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (`status`),
        INDEX idx_created_by (`created_by_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add taken_over_by_id column if missing
    try {
        $conn->exec("ALTER TABLE `stat_live_sessions` ADD COLUMN `taken_over_by_id` VARCHAR(20) DEFAULT NULL AFTER `status`");
    } catch (Exception $e) { /* already exists */ }

    // Add timer_json column if missing
    try {
        $conn->exec("ALTER TABLE `stat_live_sessions` ADD COLUMN `timer_json` TEXT DEFAULT NULL AFTER `taken_over_by_id`");
    } catch (Exception $e) { /* already exists */ }

    $conn->exec("CREATE TABLE IF NOT EXISTS `stat_live_hands` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `draft_id` VARCHAR(36) NOT NULL,
        `hand_number` INT NOT NULL,
        `points` INT NOT NULL DEFAULT 0,
        `winner` INT NOT NULL DEFAULT 0,
        `from_player` INT NOT NULL DEFAULT -2,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_draft (`draft_id`),
        UNIQUE KEY uk_draft_hand (`draft_id`, `hand_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->exec("CREATE TABLE IF NOT EXISTS `stat_live_penalties` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `draft_id` VARCHAR(36) NOT NULL,
        `penalty_index` INT NOT NULL,
        `player_idx` INT NOT NULL,
        `amount` INT NOT NULL DEFAULT 0,
        `distribute` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_draft (`draft_id`),
        UNIQUE KEY uk_draft_pen (`draft_id`, `penalty_index`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Tables already exist or will fail gracefully
}

// ============================================================
// AJAX ENDPOINTS — Draft (live hand-for-hand saving)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_json['action'])) {
    $action = $_json['action'];
    $currentVms = $_SESSION['user_id'] ?? null;

    // --- Start draft session ---
    if ($action === 'start_draft') {
        header('Content-Type: application/json');
        try {
            $draft_id = $_json['draft_id'] ?? '';
            $players_json = json_encode($_json['players'] ?? []);
            $game_date = $_json['game_date'] ?? date('Y-m-d');
            $game_name = trim($_json['game_name'] ?? '');

            if (!$draft_id || strlen($draft_id) < 10) {
                echo json_encode(['success' => false, 'error' => 'Invalid draft_id']); exit;
            }

            $conn->prepare("INSERT INTO stat_live_sessions (draft_id, created_by_id, players_json, game_date, game_name) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE players_json=VALUES(players_json), game_date=VALUES(game_date), game_name=VALUES(game_name), status='active'")
                 ->execute([$draft_id, $currentVms, $players_json, $game_date, $game_name]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    // --- Save a single hand ---
    if ($action === 'save_draft_hand') {
        header('Content-Type: application/json');
        try {
            $draft_id = $_json['draft_id'] ?? '';
            $hand = $_json['hand'] ?? [];
            $hand_num = (int)($hand['hand_number'] ?? 0);

            if (!$draft_id || $hand_num <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid data']); exit;
            }

            $conn->prepare("INSERT INTO stat_live_hands (draft_id, hand_number, points, winner, from_player) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE points=VALUES(points), winner=VALUES(winner), from_player=VALUES(from_player)")
                 ->execute([$draft_id, $hand_num, (int)($hand['points'] ?? 0), (int)($hand['winner'] ?? 0), (int)($hand['from'] ?? -2)]);

            // Update session timestamp
            $conn->prepare("UPDATE stat_live_sessions SET updated_at=NOW() WHERE draft_id=?")->execute([$draft_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    // --- Delete a hand and renumber ---
    if ($action === 'delete_draft_hand') {
        header('Content-Type: application/json');
        try {
            $draft_id = $_json['draft_id'] ?? '';
            $hand_num = (int)($_json['hand_number'] ?? 0);

            $conn->prepare("DELETE FROM stat_live_hands WHERE draft_id=? AND hand_number=?")->execute([$draft_id, $hand_num]);
            // Renumber remaining hands
            $rows = $conn->prepare("SELECT id FROM stat_live_hands WHERE draft_id=? ORDER BY hand_number");
            $rows->execute([$draft_id]);
            $num = 1;
            foreach ($rows->fetchAll() as $r) {
                $conn->prepare("UPDATE stat_live_hands SET hand_number=? WHERE id=?")->execute([$num, $r['id']]);
                $num++;
            }
            $conn->prepare("UPDATE stat_live_sessions SET updated_at=NOW() WHERE draft_id=?")->execute([$draft_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    // --- Save a penalty ---
    if ($action === 'save_draft_penalty') {
        header('Content-Type: application/json');
        try {
            $draft_id = $_json['draft_id'] ?? '';
            $pen = $_json['penalty'] ?? [];
            $pen_idx = (int)($pen['penalty_index'] ?? 0);

            $conn->prepare("INSERT INTO stat_live_penalties (draft_id, penalty_index, player_idx, amount, distribute) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE player_idx=VALUES(player_idx), amount=VALUES(amount), distribute=VALUES(distribute)")
                 ->execute([$draft_id, $pen_idx, (int)($pen['player_idx'] ?? 0), (int)($pen['amount'] ?? 0), (int)($pen['distribute'] ?? 1)]);
            $conn->prepare("UPDATE stat_live_sessions SET updated_at=NOW() WHERE draft_id=?")->execute([$draft_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    // --- Delete a penalty and renumber ---
    if ($action === 'delete_draft_penalty') {
        header('Content-Type: application/json');
        try {
            $draft_id = $_json['draft_id'] ?? '';
            $pen_idx = (int)($_json['penalty_index'] ?? 0);

            $conn->prepare("DELETE FROM stat_live_penalties WHERE draft_id=? AND penalty_index=?")->execute([$draft_id, $pen_idx]);
            $rows = $conn->prepare("SELECT id FROM stat_live_penalties WHERE draft_id=? ORDER BY penalty_index");
            $rows->execute([$draft_id]);
            $num = 0;
            foreach ($rows->fetchAll() as $r) {
                $conn->prepare("UPDATE stat_live_penalties SET penalty_index=? WHERE id=?")->execute([$num, $r['id']]);
                $num++;
            }
            $conn->prepare("UPDATE stat_live_sessions SET updated_at=NOW() WHERE draft_id=?")->execute([$draft_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    // --- Load a draft (for recovery) ---
    if ($action === 'load_draft') {
        header('Content-Type: application/json');
        try {
            $draft_id = $_json['draft_id'] ?? '';
            $sess = $conn->prepare("SELECT * FROM stat_live_sessions WHERE draft_id=? AND status='active'");
            $sess->execute([$draft_id]);
            $session = $sess->fetch(PDO::FETCH_ASSOC);
            if (!$session) { echo json_encode(['success' => false, 'error' => 'Draft not found']); exit; }

            $h = $conn->prepare("SELECT hand_number, points, winner, from_player FROM stat_live_hands WHERE draft_id=? ORDER BY hand_number");
            $h->execute([$draft_id]);
            $draftHands = $h->fetchAll(PDO::FETCH_ASSOC);

            $p = $conn->prepare("SELECT penalty_index, player_idx, amount, distribute FROM stat_live_penalties WHERE draft_id=? ORDER BY penalty_index");
            $p->execute([$draft_id]);
            $draftPenalties = $p->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'session' => $session,
                'hands' => $draftHands,
                'penalties' => $draftPenalties
            ]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    // --- Check for active drafts by current user (as creator or participant) ---
    if ($action === 'check_active_drafts') {
        header('Content-Type: application/json');
        try {
            $stmt = $conn->prepare("SELECT draft_id, created_by_id, players_json, game_date, game_name, updated_at FROM stat_live_sessions WHERE status='active' AND updated_at > DATE_SUB(NOW(), INTERVAL 48 HOUR) AND (created_by_id=? OR players_json LIKE ?) ORDER BY updated_at DESC LIMIT 5");
            $likeVms = '%"' . str_replace(['%','_'], ['\\%','\\_'], $currentVms) . '"%';
            $stmt->execute([$currentVms, $likeVms]);
            $drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'drafts' => $drafts]);
        } catch (Exception $e) { echo json_encode(['success' => true, 'drafts' => []]); }
        exit;
    }

    // --- Discard a draft ---
    if ($action === 'discard_draft') {
        header('Content-Type: application/json');
        try {
            $draft_id = $_json['draft_id'] ?? '';
            $conn->prepare("UPDATE stat_live_sessions SET status='discarded' WHERE draft_id=?")->execute([$draft_id]);
            $conn->prepare("DELETE FROM stat_live_hands WHERE draft_id=?")->execute([$draft_id]);
            $conn->prepare("DELETE FROM stat_live_penalties WHERE draft_id=?")->execute([$draft_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    // --- Takeover a draft (mark who took over) ---
    if ($action === 'takeover_draft') {
        header('Content-Type: application/json');
        try {
            $draft_id = $_json['draft_id'] ?? '';
            $conn->prepare("UPDATE stat_live_sessions SET taken_over_by_id=? WHERE draft_id=? AND status='active'")
                 ->execute([$currentVms, $draft_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    // --- Check if someone else took over this draft ---
    if ($action === 'check_takeover') {
        header('Content-Type: application/json');
        try {
            $draft_id = $_json['draft_id'] ?? '';
            $stmt = $conn->prepare("SELECT taken_over_by_id FROM stat_live_sessions WHERE draft_id=? AND status='active'");
            $stmt->execute([$draft_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['taken_over_by_id'] && $row['taken_over_by_id'] !== $currentVms) {
                // Look up the name
                $ns = $conn->prepare("SELECT first_name, last_name FROM stat_players WHERE id=? LIMIT 1");
                $ns->execute([$row['taken_over_by_id']]);
                $nr = $ns->fetch();
                $takerName = $nr ? ($nr['first_name'] . ' ' . $nr['last_name']) : $row['taken_over_by_id'];
                echo json_encode(['taken_over' => true, 'by_vms' => $row['taken_over_by_id'], 'by_name' => $takerName]);
            } else {
                echo json_encode(['taken_over' => false]);
            }
        } catch (Exception $e) { echo json_encode(['taken_over' => false]); }
        exit;
    }

    // --- Save timer state ---
    if ($action === 'save_timer') {
        header('Content-Type: application/json');
        try {
            $draft_id = $_json['draft_id'] ?? '';
            $timer_json = json_encode($_json['timer'] ?? []);
            $conn->prepare("UPDATE stat_live_sessions SET timer_json=? WHERE draft_id=? AND status='active'")
                 ->execute([$timer_json, $draft_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_json['action']) && $_json['action'] === 'save_game') {
    header('Content-Type: application/json');
    
    try {
        $data = $_json;
        $players_vms = [$data['players'][0]['vms'], $data['players'][1]['vms'], $data['players'][2]['vms'], $data['players'][3]['vms']];
        $game_date = $data['game_date'] ?? date('Y-m-d');
        $game_name = trim($data['game_name'] ?? '');
        $hands = $data['hands'];
        $penalties = $data['penalties'] ?? [];

        if (count($players_vms) !== count(array_unique($players_vms))) {
            echo json_encode(['success' => false, 'error' => 'Duplicate players']);
            exit;
        }

        // Beräkna poäng
        $mp = [0, 0, 0, 0];
        $hu_count = [0, 0, 0, 0];
        $hu_given = [0, 0, 0, 0];
        $selfdrawn = [0, 0, 0, 0];
        $biggest = 0;
        $biggest_vms = [];
        $hands_db = [];
        $num_hands = 0;
        $zero_rounds = 0;

        foreach ($hands as $h) {
            $hp = (int)$h['points'];
            $win = (int)$h['winner'];
            $from = (int)$h['from']; // -1=self, 0-3=player idx, -2=draw/zero

            if ($hp <= 0 || $from === -2) {
                $num_hands++;
                $zero_rounds++;
                $hands_db[] = ['hp' => 0, 'winner' => 0, 'from' => 0, 'null' => true];
                continue;
            }
            $num_hands++;
            $hu_count[$win]++;

            if ($hp > $biggest) {
                $biggest = $hp;
                $biggest_vms = [$players_vms[$win]];
            } elseif ($hp === $biggest) {
                if (!in_array($players_vms[$win], $biggest_vms)) $biggest_vms[] = $players_vms[$win];
            }

            if ($from === -1) {
                $selfdrawn[$win]++;
                for ($i = 0; $i < 4; $i++) {
                    $mp[$i] += ($i === $win) ? ($hp + 8) * 3 : -($hp + 8);
                }
                $hands_db[] = ['hp' => $hp, 'winner' => $win + 1, 'from' => 0];
            } else {
                $hu_given[$from]++;
                for ($i = 0; $i < 4; $i++) {
                    if ($i === $win) $mp[$i] += 8 + 8 + (8 + $hp);
                    elseif ($i === $from) $mp[$i] -= (8 + $hp);
                    else $mp[$i] -= 8;
                }
                $hands_db[] = ['hp' => $hp, 'winner' => $win + 1, 'from' => $from + 1];
            }
        }

        // Penalties
        $pen_totals = [0, 0, 0, 0];
        foreach ($penalties as $p) {
            $pidx = (int)$p['idx'];
            $amt = (int)$p['amount'];
            $distribute = $p['distribute'] ?? true;
            $pen_totals[$pidx] += $amt;
            $mp[$pidx] -= $amt;
            
            if ($distribute) {
                $share = intdiv($amt, 3);
                $remainder = $amt - ($share * 3);
                $firstOther = true;
                for ($i = 0; $i < 4; $i++) {
                    if ($i !== $pidx) {
                        $bonus = $share;
                        if ($firstOther && $remainder > 0) { $bonus += $remainder; $firstOther = false; }
                        else { $firstOther = false; }
                        $mp[$i] += $bonus;
                    }
                }
            }
        }

        // Table points
        $sorted = [];
        for ($i = 0; $i < 4; $i++) $sorted[] = ['vms' => $players_vms[$i], 'mp' => $mp[$i], 'idx' => $i];
        usort($sorted, fn($a, $b) => $b['mp'] - $a['mp']);
        $bp_base = [4, 2, 1, 0];
        $tp = [0, 0, 0, 0];
        for ($i = 0; $i < 4; $i++) {
            $same = 1; $sum = $bp_base[$i];
            for ($j = $i + 1; $j < 4; $j++) {
                if ($sorted[$j]['mp'] === $sorted[$i]['mp']) { $same++; $sum += $bp_base[$j]; } else break;
            }
            $tp_val = round($sum / $same, 2);
            for ($k = $i; $k < $i + $same && $k < 4; $k++) {
                $tp[$sorted[$k]['idx']] = $tp_val;
            }
            $i += $same - 1;
        }

        // Next game number
        $maxGame = $conn->query("SELECT COALESCE(MAX(game_number), 0) as m FROM stat_games")->fetch()['m'];
        $gameNum = $maxGame + 1;
        $biggest_vms_str = implode(',', $biggest_vms);

        $stmt = $conn->prepare("
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

        $currentVms = $_SESSION['user_id'] ?? null;
        $game_year = (int)date('Y', strtotime($game_date));
        $require_confirmation = defined('REQUIRE_PLAYER_CONFIRMATION') && REQUIRE_PLAYER_CONFIRMATION;
        $auto_approved = ($require_confirmation) ? 0 : (canApproveGames() ? 1 : 0);

        $stmt->execute([
            $gameNum, $game_name ?: date('Y-m-d'), $game_date, $game_year,
            $biggest, $biggest_vms_str, $num_hands, $zero_rounds,
            1, $auto_approved, $currentVms,
            $players_vms[0], $mp[0], $tp[0], $pen_totals[0], 0,
            $hu_count[0], $selfdrawn[0], $hu_given[0], $hu_given[0], $selfdrawn[0],
            $players_vms[1], $mp[1], $tp[1], $pen_totals[1], 0,
            $hu_count[1], $selfdrawn[1], $hu_given[1], $hu_given[1], $selfdrawn[1],
            $players_vms[2], $mp[2], $tp[2], $pen_totals[2], 0,
            $hu_count[2], $selfdrawn[2], $hu_given[2], $hu_given[2], $selfdrawn[2],
            $players_vms[3], $mp[3], $tp[3], $pen_totals[3], 0,
            $hu_count[3], $selfdrawn[3], $hu_given[3], $hu_given[3], $selfdrawn[3]
        ]);

        $gameId = $conn->lastInsertId();

        // Spara händer
        foreach ($hands_db as $idx => $hd) {
            // Calculate per-player points for this hand
            $hp2 = [0, 0, 0, 0];
            if (!($hd['null'] ?? false) && $hd['hp'] > 0) {
                $w = $hd['winner'] - 1; // 1-based to 0-based
                $f = $hd['from']; // 0=self, 1-4=player
                if ($f === 0) {
                    // Self-drawn
                    for ($i = 0; $i < 4; $i++)
                        $hp2[$i] = ($i === $w) ? ($hd['hp'] + 8) * 3 : -($hd['hp'] + 8);
                } else {
                    $thr = $f - 1; // 1-based to 0-based
                    for ($i = 0; $i < 4; $i++) {
                        if ($i === $w) $hp2[$i] = 8 + 8 + (8 + $hd['hp']);
                        elseif ($i === $thr) $hp2[$i] = -(8 + $hd['hp']);
                        else $hp2[$i] = -8;
                    }
                }
            }
            $conn->prepare("INSERT INTO stat_game_hands (game_id, hand_number, hu_points, winning_player, from_player, player_a_points, player_b_points, player_c_points, player_d_points) VALUES (?,?,?,?,?,?,?,?,?)")
                 ->execute([$gameId, $idx + 1, $hd['hp'], $hd['winner'], $hd['from'], $hp2[0], $hp2[1], $hp2[2], $hp2[3]]);
        }

        // Auto-confirm the protocol keeper (the person saving)
        $email_recipients = []; // Collect recipients, send after JSON response
        if ($require_confirmation && $currentVms) {
            try {
                foreach (['a','b','c','d'] as $slot) {
                    if ($players_vms[$slot === 'a' ? 0 : ($slot === 'b' ? 1 : ($slot === 'c' ? 2 : 3))] === $currentVms) {
                        $conn->prepare("UPDATE stat_games SET player_{$slot}_confirmed_at = NOW(), player_{$slot}_confirmed_by = ? WHERE id = ?")
                             ->execute([$currentVms, $gameId]);
                        break;
                    }
                }
                // Collect email recipients (don't send yet)
                if (defined('SMTP_HOST') && SMTP_HOST !== '') {
                    foreach ($players_vms as $pvms) {
                        if ($pvms === $currentVms) continue;
                        $pstmt = $conn->prepare("SELECT first_name, last_name, email FROM stat_players WHERE id = ?");
                        $pstmt->execute([$pvms]);
                        $player = $pstmt->fetch();
                        if ($player && $player['email'] && strpos($player['email'], 'placeholder') === false) {
                            $email_recipients[] = $player;
                        }
                    }
                }
            } catch (\Throwable $confEx) {
                error_log("Player confirmation error: " . $confEx->getMessage());
            }
        }

        // Clean up any active draft for this game
        if (isset($data['draft_id']) && $data['draft_id']) {
            try {
                $conn->prepare("UPDATE stat_live_sessions SET status='saved' WHERE draft_id=?")->execute([$data['draft_id']]);
                $conn->prepare("DELETE FROM stat_live_hands WHERE draft_id=?")->execute([$data['draft_id']]);
                $conn->prepare("DELETE FROM stat_live_penalties WHERE draft_id=?")->execute([$data['draft_id']]);
            } catch (Exception $e) { /* non-critical */ }
        }

        // ALWAYS send JSON response first
        echo json_encode(['success' => true, 'game_id' => $gameId, 'game_number' => $gameNum, 'approved' => $auto_approved]);
        
        // Flush output so client gets response immediately
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            ob_end_flush();
            flush();
        }
        
        // Now send emails (after client has received response)
        if (!empty($email_recipients)) {
            try {
                require_once __DIR__ . '/includes/EmailHelper.php';
                $game_url = SITE_URL . '/view-game.php?id=' . $gameId;
                $keeper_stmt = $conn->prepare("SELECT first_name, last_name FROM stat_players WHERE id = ?");
                $keeper_stmt->execute([$currentVms]);
                $keeper = $keeper_stmt->fetch();
                $keeper_name = $keeper ? $keeper['first_name'] . ' ' . $keeper['last_name'] : $currentVms;
                
                foreach ($email_recipients as $player) {
                    $pname = $player['first_name'] . ' ' . $player['last_name'];
                    $emailHelper = new EmailHelper();
                    $emailHelper->sendGenericEmail(
                        $player['email'],
                        $pname,
                        currentLang() === 'sv' 
                            ? "Match $gameNum — Väntar på ditt OK"
                            : "Game $gameNum — Awaiting your confirmation",
                        currentLang() === 'sv'
                            ? "<p>Hej $pname!</p><p><strong>$keeper_name</strong> har registrerat match #$gameNum där du deltog.</p><p>Granska resultatet och tryck OK:</p><p><a href=\"$game_url\" style=\"display:inline-block;padding:12px 24px;background:#4caf50;color:white;text-decoration:none;border-radius:6px;font-weight:bold;\">Granska &amp; Godkänn →</a></p><p style=\"color:#888;font-size:0.9em;\">Om du inte kan klicka på knappen, kopiera denna länk: $game_url</p>"
                            : "<p>Hi $pname!</p><p><strong>$keeper_name</strong> has registered game #$gameNum where you participated.</p><p>Review the result and confirm:</p><p><a href=\"$game_url\" style=\"display:inline-block;padding:12px 24px;background:#4caf50;color:white;text-decoration:none;border-radius:6px;font-weight:bold;\">Review &amp; Confirm →</a></p><p style=\"color:#888;font-size:0.9em;\">If you can't click the button, copy this link: $game_url</p>"
                    );
                }
            } catch (\Throwable $mailEx) {
                error_log("Confirmation email error: " . $mailEx->getMessage());
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Don't include the main header - this is a standalone mobile page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>🀄 Mobile Game</title>
</head>
<body style="margin:0;padding:8px;background:#F7F8FA;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;overscroll-behavior:none;">

<style>
/* ===== MOBILE GAME APP ===== */
.mvms { max-width: 480px; margin: 0 auto; padding: 0 8px; font-family: 'Segoe UI', sans-serif; }
.mvms h1 { text-align: center; color: #005B99; font-size: 1.4em; margin: 12px 0; }

/* Screens */
.mvms-screen { display: none; }
.mvms-screen.active { display: block; }

/* Player setup */
.mvms-setup { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
.mvms-wind-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #eee; }
.mvms-wind-row:last-child { border-bottom: none; }
.mvms-wind-char { width: 44px; text-align: center; line-height: 1; }
.mvms-wind-label { font-size: 0.75em; color: #888; text-transform: uppercase; letter-spacing: 0.1em; }
.mvms-wind-row select { flex: 1; padding: 10px; border: 2px solid #ddd; border-radius: 8px; font-size: 1em; background: white; }
.mvms-wind-row select:focus { border-color: #005B99; outline: none; }

/* Table view - the mahjong table */
.mvms-table { position: relative; background: linear-gradient(135deg, #e8f0f8, #f5f7fa); border-radius: 16px; padding: 20px; min-height: 420px; color: #333; margin: 12px 0; border: 2px solid #c5d5e5; }

/* Each seat: relative container, player info centered as always */
.mvms-seat { position: absolute; text-align: center; cursor: pointer; }
.mvms-seat:active { opacity: 0.8; }
.mvms-seat .wind-char { font-size: 1.6em; opacity: 0.8; }
.mvms-seat .score { font-size: 2em; font-weight: 800; }
.mvms-seat .name { font-size: 0.85em; font-weight: 600; color: #444; }
.mvms-seat .score.positive { color: #1b5e20; }
.mvms-seat .score.negative { color: #b71c1c; }
.mvms-seat .score.zero { color: #888; }

/* 32 table: absolutely positioned to the right of the seat, doesn't move player info */
.mvms-seat .rule32 {
    position: absolute;
    left: 100%;
    top: 50%;
    transform: translateY(-50%);
    margin-left: 6px;
    font-size: 0.72em;
    line-height: 1.4;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s;
}
.mvms-seat .rule32.show { opacity: 1; }
.mvms-seat .rule32 .r32-s { color: #6a1b9a; font-weight: 700; }
.mvms-seat .rule32 .r32-d { color: #0a3d8f; font-weight: 700; }

/* Seat positions — whole seat div rotated so each player reads from their side */
.mvms-seat-bottom {
    bottom: 18px; left: 50%; transform: translateX(-50%);
}
.mvms-seat-top {
    top: 18px; left: 50%;
    transform: translateX(-50%) rotate(180deg);
}
.mvms-seat-left {
    top: 50%; left: 30px;
    transform: translateY(-50%) rotate(90deg);
}
.mvms-seat-right {
    top: 50%; right: 30px;
    transform: translateY(-50%) rotate(-90deg);
}
.mvms-center { position: absolute; top: 48%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
.mvms-center-box { display: inline-block; padding: 6px 14px; border: 1.5px solid #b0c4de; border-radius: 10px; background: rgba(255,255,255,0.7); box-shadow: 0 1px 4px rgba(0,91,153,0.08); }
.mvms-center .hand-num { font-size: 0.92em; color: #005B99; font-weight: 700; }
.mvms-center .wind-indicator { font-size: 1.2em; margin-top: 4px; }

/* Hu dialog */
.mvms-dialog { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 16px; }
.mvms-dialog.open { display: flex; }
.mvms-dialog-box { background: white; border-radius: 16px; padding: 24px; width: 100%; max-width: 400px; max-height: 90vh; overflow-y: auto; }
.mvms-dialog-title { text-align: center; font-size: 1.2em; font-weight: 700; color: #005B99; margin-bottom: 16px; }

/* Big buttons */
.mvms-btn-hu { display: block; width: 100%; padding: 20px; margin: 8px 0; border: none; border-radius: 12px; font-size: 1.2em; font-weight: 700; color: white; cursor: pointer; transition: transform 0.1s; }
.mvms-btn-hu:active { transform: scale(0.97); }
.mvms-btn-mahjong { background: #2e7d32; }
.mvms-btn-draw { background: #e91e63; }
.mvms-btn-penalty { background: #7b1fa2; }

/* From selection */
.mvms-from-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 16px 0; }
.mvms-from-btn { padding: 14px 8px; border: 2px solid #ddd; border-radius: 10px; text-align: center; cursor: pointer; transition: all 0.15s; background: white; }
.mvms-from-btn.selected { border-color: #e91e63; background: #fce4ec; color: #e91e63; }
.mvms-from-btn .wind-char { font-size: 1.3em; display: block; }
.mvms-from-btn .name { font-size: 0.82em; }

/* Number pad */
.mvms-numpad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 16px 0; }
.mvms-numpad button { padding: 16px; border: none; border-radius: 10px; font-size: 1.3em; font-weight: 700; background: #005B99; color: white; cursor: pointer; }
.mvms-numpad button:active { background: #003D6B; }
.mvms-numpad .btn-clear { background: #dc3545; }
.mvms-numpad .btn-zero { background: #005B99; }
.mvms-points-display { text-align: center; font-size: 2.5em; font-weight: 800; color: #005B99; margin: 8px 0; min-height: 1.2em; }

/* Hand list — new design with color coding */
.mvms-hand-list { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }

/* Each row is a swipe container */
.hand-row-wrap { position: relative; overflow: hidden; border-bottom: 1px solid #f0f0f0; }
.hand-row-wrap.wind-sep { border-top: 2px solid #005B99; }
.hand-row-wrap:last-child { border-bottom: none; }

/* Swipe action buttons revealed on left swipe */
.hand-row-actions { position: absolute; right: 0; top: 0; bottom: 0; display: flex; align-items: stretch; transform: translateX(100%); transition: transform 0.2s; z-index: 2; }
.hand-row-actions .act-edit { background: #005B99; color: white; border: none; padding: 0 16px; cursor: pointer; font-size: 1.1em; }
.hand-row-actions .act-del  { background: #c62828; color: white; border: none; padding: 0 16px; cursor: pointer; font-size: 1.1em; }
.hand-row-wrap.swiped .hand-row-actions { transform: translateX(0); }
.hand-row-wrap.swiped .hand-row-inner  { transform: translateX(-96px); }

/* The actual row content */
.hand-row-inner { display: grid; padding: 6px 8px; font-size: 1em; align-items: center; transition: transform 0.2s; background: white; position: relative; z-index: 1; }
.hand-row-inner.header { background: #005B99; color: white; font-weight: 700; font-size: 0.8em; padding: 8px; }

/* Per-player cell */
.hr-cell { text-align: right; padding: 3px 4px; border-radius: 4px; }
.hr-delta { font-weight: 500; font-family: monospace; font-size: 1.05em; }
.hr-delta.win-self  { color: #0a3d8f; }   /* blue  — self-drawn */
.hr-delta.win-disc  { color: #1b5e20; }   /* green — won from discard */
.hr-delta.lost-disc { color: #7f0000; }   /* red   — discarded */
.hr-delta.paid      { color: #555; }      /* grey  — paid -8 */
.hr-delta.draw      { color: #999; }      /* grey  — draw */

/* Cell backgrounds */
.hr-cell.bg-win-self  { background: #dbeafe; }  /* light blue */
.hr-cell.bg-win-disc  { background: #dcfce7; }  /* light green */
.hr-cell.bg-lost-disc { background: #fee2e2; }  /* light red */

.hr-cumul { font-size: 0.9em; color: #333; font-family: monospace; }

/* Row icons */
.hand-row-inner .row-icons { display: flex; gap: 2px; justify-content: flex-end; align-items: center; }
.row-icons button { border: none; background: none; cursor: pointer; padding: 2px 3px; font-size: 1em; line-height: 1; }

.mvms-hand-row.header { background: #005B99; color: white; font-weight: 700; font-size: 0.8em; }
.mvms-hand-row.wind-sep { border-top: 2px solid #005B99; }

/* Totals bar */
.mvms-totals { display: grid; grid-template-columns: repeat(4, 1fr); background: #e8f0f8; color: #333; border-radius: 12px; padding: 12px 8px; margin: 12px 0; text-align: center; border: 2px solid #c5d5e5; }
.mvms-totals .total-name { font-size: 0.75em; color: #005B99; opacity: 0.8; }
.mvms-totals .total-score { font-size: 1.4em; font-weight: 800; }
.mvms-totals .total-score.neg { color: #b71c1c; }
.mvms-totals .total-score.pos { color: #1b5e20; }

/* Results */
.mvms-result { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
.mvms-result-row { display: flex; align-items: center; padding: 14px 0; border-bottom: 1px solid #eee; gap: 16px; }
.mvms-result-row:last-child { border-bottom: none; }
.mvms-result-rank { font-size: 1.2em; font-weight: 700; width: 40px; text-align: center; color: #888; }
.mvms-result-name { flex: 1; font-size: 1.1em; font-weight: 600; }
.mvms-result-tp { font-size: 1.3em; font-weight: 800; color: #005B99; width: 36px; text-align: center; }
.mvms-result-mp { font-size: 1.1em; font-weight: 700; width: 70px; text-align: right; }
.mvms-result-mp.neg { color: #c62828; }

/* Action buttons */
.mvms-actions { display: flex; gap: 8px; margin: 12px 0; flex-wrap: wrap; }
.mvms-btn { padding: 10px 16px; border: none; border-radius: 8px; font-size: 0.9em; font-weight: 600; cursor: pointer; flex: 1; min-width: 100px; text-align: center; }
.mvms-btn-primary { background: #005B99; color: white; }
.mvms-btn-secondary { background: #e0e0e0; color: #333; }
.mvms-btn-save { background: #2e7d32; color: white; font-size: 1.1em; padding: 14px; }
.mvms-btn-new { background: #FECC02; color: #003D6B; }

/* Nav tabs */
.mvms-tabs { display: flex; gap: 4px; margin: 12px 0; }
.mvms-tab { flex: 1; padding: 10px; text-align: center; border: none; border-radius: 8px 8px 0 0; font-weight: 600; cursor: pointer; font-size: 0.88em; }
.mvms-tab.active { background: #005B99; color: white; }
.mvms-tab:not(.active) { background: #e8ecf0; color: #555; }

.mvms-btn-draw-btn { background: #f9a825; color: #333; font-weight: 700; }
.mvms-btn-draw-btn:active { background: #f57f17; }

    .mvms-seat .score { font-size: 1.5em; }
    .mvms-seat .wind-char { font-size: 1.2em; }
    .mvms-table { min-height: 320px; padding: 14px; }
}

/* Timer pulse animation */
@keyframes timerPulse {
    0%, 100% { color: #c62828; }
    50% { color: #ff8a80; }
}
.timer-pulse {
    animation: timerPulse 1s ease-in-out infinite;
}

/* Timer overlay between table and buttons — always visible when timer running */
@keyframes boldPulse {
    0%, 49% { font-weight: 400; }
    50%, 100% { font-weight: 800; }
}
.timer-overlay {
    text-align: center;
    font-family: monospace;
    font-size: 1.65em;
    font-weight: 400;
    color: #333;
    animation: boldPulse 2s step-start infinite;
    padding: 6px 0 2px;
    letter-spacing: 1px;
}
.timer-overlay.timer-low {
    color: #b71c1c;
}
</style>

<div class="mvms" id="app">

<!-- Header -->
<div style="display:flex;align-items:center;gap:10px;padding:8px 4px;margin-bottom:4px;">
    <img src="img/logo.png" alt="Logo" style="height:32px;width:32px;object-fit:contain;" onerror="this.style.display='none'">
    <span style="font-size:0.88em;font-weight:600;color:#005B99;letter-spacing:0.02em;">klubben</span>
</div>

<!-- ===== SCREEN 1: SETUP ===== -->
<div class="mvms-screen active" id="screen-setup">
    <h1><?php echo $mt["title"]; ?></h1>
    
    <div class="mvms-setup">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
                <label style="font-weight:600;color:#005B99;font-size:0.88em;"><?php echo $mt["date"]; ?></label>
                <input type="date" id="gameDate" value="<?php echo date('Y-m-d'); ?>"
                       style="width:100%;padding:10px;border:2px solid #ddd;border-radius:8px;font-size:1em;margin-top:4px;font-family:inherit;box-sizing:border-box;-webkit-appearance:none;color:#333;">
            </div>
            <div>
                <label style="font-weight:600;color:#005B99;font-size:0.88em;"><?php echo $mt["gameName"]; ?></label>
                <input type="text" id="gameName" placeholder="<?php echo $mt['gameNamePh']; ?>" 
                       style="width:100%;padding:10px;border:2px solid #ddd;border-radius:8px;font-size:1em;margin-top:4px;font-family:inherit;box-sizing:border-box;">
            </div>
        </div>

        <?php for ($i = 0; $i < 4; $i++): 
            $windFiles = ['east','south','west','north'];
        ?>
        <div class="mvms-wind-row" onclick="openPicker(<?php echo $i; ?>)">
            <div><div class="mvms-wind-char"><img src="images/winds/wind-<?php echo $windFiles[$i]; ?>-dark.png" alt="<?php echo ['東','南','西','北'][$i]; ?>" style="width:32px;height:32px;object-fit:contain;"></div><div class="mvms-wind-label"><?php echo ['East','South','West','North'][$i]; ?></div></div>
            <div style="flex:1;padding:10px;border:2px solid #ddd;border-radius:8px;color:#999;font-size:1em;background:white;" id="slot-<?php echo $i; ?>">
                <?php echo $mt["tapSelect"]; ?>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div id="shuffleRow" style="display:none;margin-top:10px;">
        <button class="mvms-btn" onclick="shufflePlayers()" style="width:100%;background:#5c4e8a;color:white;padding:12px;font-size:1em;">
            🎲 <?php echo currentLang() === 'sv' ? 'Blanda platser' : 'Shuffle seats'; ?>
        </button>
    </div>

    <button class="mvms-btn mvms-btn-primary" style="width:100%;margin-top:10px;padding:14px;font-size:1.1em;" onclick="startGame()">
        <?php echo $mt['start']; ?>
    </button>
</div>

<!-- ===== SCREEN 2: GAME TABLE ===== -->
<div class="mvms-screen" id="screen-game">
    
    <div class="mvms-tabs">
        <button class="mvms-tab active" onclick="showGameTab('table')"><?php echo $mt['table']; ?></button>
        <button class="mvms-tab" onclick="showGameTab('list')"><?php echo $mt['list']; ?></button>
        <button class="mvms-tab" onclick="showGameTab('clock')">⏱ <?php echo currentLang() === 'sv' ? 'KLOCKA' : 'CLOCK'; ?></button>
        <a href="?lang=<?php echo currentLang() === 'sv' ? 'en' : 'sv'; ?>" onclick="event.preventDefault();safeLangSwitch(this.href);" class="mvms-tab" style="text-decoration:none;font-size:1.3em;line-height:1;padding:6px 10px;"><?php echo currentLang() === 'sv' ? '🇬🇧' : '🇸🇪'; ?></a>
    </div>

    <!-- Table view -->
    <div id="tab-table">
        <div style="position:relative;">
            <div class="mvms-table" id="gameTable">
                <!-- Populated by JS -->
            </div>
        </div>
        <div id="timerOverlay" class="timer-overlay" style="display:none;"></div>

        <div class="mvms-actions" style="margin-bottom:4px;">
            <button class="mvms-btn mvms-btn-draw-btn" onclick="drawHand()"><?php echo $mt['draw']; ?></button>
            <button class="mvms-btn mvms-btn-secondary" onclick="showPenalty()"><?php echo $mt['penalty']; ?></button>
        </div>
        <div class="mvms-actions" style="margin-bottom:4px;">
            <button class="mvms-btn mvms-btn-secondary" onclick="undoLastHand()"><?php echo $mt['undo']; ?></button>
            <button class="mvms-btn mvms-btn-secondary" id="btn32" onclick="toggle32()" style="background:#5c4e8a;color:white;">32</button>
        </div>
        <div class="mvms-actions">
            <button class="mvms-btn mvms-btn-primary" onclick="endGame()" style="font-size:1em;padding:12px;"><?php echo $mt['endGame']; ?></button>
        </div>
    </div>

    <!-- List view -->
    <div id="tab-list" style="display:none;">
        <div class="mvms-hand-list" id="handList">
            <!-- Populated by JS -->
        </div>
        <div class="mvms-totals" id="totalsBar">
            <!-- Populated by JS -->
        </div>
        <button class="mvms-btn mvms-btn-save" style="width:100%;margin-top:12px;" onclick="endGame()">
            <?php echo $mt['endGame']; ?>
        </button>
    </div>

    <!-- Clock/Timer view -->
    <div id="tab-clock" style="display:none;">
        <div style="background:#f5f7fa;border-radius:16px;padding:24px 16px;border:2px solid #c5d5e5;text-align:center;">
            <!-- Date -->
            <div id="dateDisplay" style="font-size:1.1em;font-weight:600;color:#005B99;margin-bottom:8px;"></div>

            <!-- Current time -->
            <div id="clockDisplay" style="font-family:monospace;font-size:4em;font-weight:800;color:#1b5e20;letter-spacing:2px;line-height:1.2;">
                00:00:00
            </div>
            <div style="font-size:0.75em;color:#888;margin-bottom:20px;">
                <?php echo currentLang() === 'sv' ? 'Klockan' : 'Current time'; ?>
            </div>

            <!-- Timer -->
            <div id="timerDisplay" style="font-family:monospace;font-size:4em;font-weight:800;color:#c62828;letter-spacing:2px;line-height:1.2;">
                01:55:00
            </div>
            <div style="font-size:0.75em;color:#888;margin-bottom:20px;">
                <?php echo currentLang() === 'sv' ? 'Timer' : 'Timer'; ?>
            </div>

            <!-- Timer controls -->
            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-bottom:16px;">
                <button onclick="timerStartStop()" id="timerStartBtn" class="mvms-btn" style="padding:12px 24px;background:#1b5e20;color:white;border:none;border-radius:8px;font-size:1em;font-weight:700;cursor:pointer;">
                    ▶ <?php echo currentLang() === 'sv' ? 'Starta' : 'Start'; ?>
                </button>
                <button onclick="timerReset()" class="mvms-btn" style="padding:12px 24px;background:#e0e0e0;color:#333;border:none;border-radius:8px;font-size:1em;font-weight:600;cursor:pointer;">
                    ↺ <?php echo currentLang() === 'sv' ? 'Nollställ' : 'Reset'; ?> 01:55
                </button>
                <button onclick="timerCustom()" class="mvms-btn" style="padding:12px 24px;background:#e0e0e0;color:#333;border:none;border-radius:8px;font-size:1em;font-weight:600;cursor:pointer;">
                    ⚙ <?php echo currentLang() === 'sv' ? 'Ändra tid' : 'Set time'; ?>
                </button>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:0.95em;cursor:pointer;color:#555;">
                    <input type="checkbox" id="timerSoundEnabled" checked style="margin-right:6px;transform:scale(1.2);vertical-align:middle;">
                    🔔 <?php echo currentLang() === 'sv' ? 'Ljud/vibration när tiden är ute' : 'Sound/vibration when time is up'; ?>
                </label>
            </div>

            <!-- Custom time setter (hidden by default) -->
            <div id="timerCustomPanel" style="display:none;margin-top:12px;padding:16px;background:white;border-radius:8px;border:1px solid #ddd;">
                <div style="display:flex;gap:12px;justify-content:center;align-items:center;">
                    <div style="text-align:center;">
                        <div style="font-size:0.8em;color:#888;margin-bottom:4px;"><?php echo currentLang() === 'sv' ? 'Timmar' : 'Hours'; ?></div>
                        <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                            <button onclick="timerAdjust('h',1)" style="border:none;background:#e8f0f8;border-radius:6px;padding:8px 16px;font-size:1.2em;cursor:pointer;">▲</button>
                            <span id="customH" style="font-family:monospace;font-size:2em;font-weight:700;color:#005B99;">01</span>
                            <button onclick="timerAdjust('h',-1)" style="border:none;background:#e8f0f8;border-radius:6px;padding:8px 16px;font-size:1.2em;cursor:pointer;">▼</button>
                        </div>
                    </div>
                    <span style="font-size:2em;font-weight:700;color:#888;">:</span>
                    <div style="text-align:center;">
                        <div style="font-size:0.8em;color:#888;margin-bottom:4px;"><?php echo currentLang() === 'sv' ? 'Minuter' : 'Minutes'; ?></div>
                        <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                            <button onclick="timerAdjust('m',1)" style="border:none;background:#e8f0f8;border-radius:6px;padding:8px 16px;font-size:1.2em;cursor:pointer;">▲</button>
                            <span id="customM" style="font-family:monospace;font-size:2em;font-weight:700;color:#005B99;">55</span>
                            <button onclick="timerAdjust('m',-1)" style="border:none;background:#e8f0f8;border-radius:6px;padding:8px 16px;font-size:1.2em;cursor:pointer;">▼</button>
                        </div>
                    </div>
                    <span style="font-size:2em;font-weight:700;color:#888;">:</span>
                    <div style="text-align:center;">
                        <div style="font-size:0.8em;color:#888;margin-bottom:4px;"><?php echo currentLang() === 'sv' ? 'Sekunder' : 'Seconds'; ?></div>
                        <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                            <button onclick="timerAdjust('s',1)" style="border:none;background:#e8f0f8;border-radius:6px;padding:8px 16px;font-size:1.2em;cursor:pointer;">▲</button>
                            <span id="customS" style="font-family:monospace;font-size:2em;font-weight:700;color:#005B99;">00</span>
                            <button onclick="timerAdjust('s',-1)" style="border:none;background:#e8f0f8;border-radius:6px;padding:8px 16px;font-size:1.2em;cursor:pointer;">▼</button>
                        </div>
                    </div>
                </div>
                <button onclick="timerSetCustom()" style="margin-top:12px;padding:10px 24px;background:#005B99;color:white;border:none;border-radius:8px;font-size:1em;font-weight:600;cursor:pointer;">
                    ✓ OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== SCREEN 3: RESULTS ===== -->
<div class="mvms-screen" id="screen-results">
    <h1><?php echo $mt["results"]; ?></h1>
    <div class="mvms-result" id="resultsBox">
        <!-- Populated by JS -->
    </div>
    <div class="mvms-actions" style="margin-top:16px;">
        <button class="mvms-btn mvms-btn-save" onclick="saveGame()" id="btnSave"><?php echo $mt['saveGame']; ?></button>
    </div>
    <div class="mvms-actions">
        <button class="mvms-btn mvms-btn-new" onclick="newGame()"><?php echo $mt['newGame']; ?></button>
        <button class="mvms-btn mvms-btn-secondary" onclick="backToGame()"><?php echo $mt['back']; ?></button>
    </div>
    <div style="text-align:center;margin-top:16px;">
        <details style="display:inline-block;">
            <summary style="font-size:0.8em;color:#999;cursor:pointer;user-select:none;"><?php echo $sv ? '⚠ Farliga åtgärder — ex radera match' : '⚠ Dangerous actions — e.g. delete game'; ?></summary>
            <div style="margin-top:8px;">
                <button class="mvms-btn" onclick="discardGame()" style="background:#c62828;color:white;font-size:0.85em;padding:8px 16px;"><?php echo $sv ? '🗑 Radera pågående match' : '🗑 Discard game'; ?></button>
            </div>
        </details>
    </div>
    <div id="saveMsg" style="text-align:center;margin-top:12px;"></div>
</div>

<!-- ===== PLAYER PICKER DIALOG ===== -->
<div class="mvms-dialog" id="pickerDialog">
    <div class="mvms-dialog-box" style="padding:16px;">
        <div class="mvms-dialog-title" id="pickerTitle"><?php echo $mt['selectPlayer']; ?></div>
        <input type="search" id="pickerSearch" placeholder="<?php echo $mt['searchPh']; ?>"
               oninput="filterSearch(this.value)" onkeydown="if(event.key==='Enter'){event.preventDefault();pickFirstResult();}" autocomplete="off" inputmode="search"
               style="width:100%;padding:12px;border:2px solid #005B99;border-radius:8px;font-size:1.1em;margin-bottom:8px;-webkit-appearance:none;">
        <div id="pickerResults" style="max-height:50vh;overflow-y:auto;">
            <div style="color:#999;text-align:center;padding:20px;font-size:0.9em;">Type at least 2 characters</div>
        </div>
        <button class="mvms-btn mvms-btn-secondary" style="width:100%;margin-top:8px;" onclick="closePicker()"><?php echo $mt['cancel']; ?></button>
    </div>
</div>

<!-- ===== HU DIALOG ===== -->
<div class="mvms-dialog" id="huDialog">
    <div class="mvms-dialog-box">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
            <div class="mvms-dialog-title" id="huTitle" style="margin-bottom:0;"><?php echo $sv ? 'Vem fick Mahjong?' : 'Who got Mahjong?'; ?></div>
            <button onclick="cancelHu()" style="background:#dc3545;color:white;border:none;border-radius:8px;padding:8px 14px;font-size:1.1em;font-weight:700;cursor:pointer;" title="<?php echo $sv ? 'Avbryt' : 'Cancel'; ?>">✕</button>
        </div>
        
        <!-- Step 1: Winner name shown -->
        <div id="huStep1">
            <div style="text-align:center;margin:12px 0;">
                <div id="huWinnerWind" style="font-size:2em;color:#005B99;"></div>
                <div id="huWinnerName" style="font-size:1.2em;font-weight:700;"></div>
            </div>

            <div class="mvms-dialog-title" style="font-size:1em;margin-top:20px;"><?php echo $mt['discarder']; ?></div>
            <div class="mvms-from-grid" id="fromGrid">
                <!-- Populated by JS -->
            </div>

            <div class="mvms-dialog-title" style="font-size:1em;"><?php echo $mt['points']; ?></div>
            <div class="mvms-points-display" id="huPoints">0</div>
            <div class="mvms-numpad">
                <button onclick="numpad(7)">7</button>
                <button onclick="numpad(8)">8</button>
                <button onclick="numpad(9)">9</button>
                <button onclick="numpad(4)">4</button>
                <button onclick="numpad(5)">5</button>
                <button onclick="numpad(6)">6</button>
                <button onclick="numpad(1)">1</button>
                <button onclick="numpad(2)">2</button>
                <button onclick="numpad(3)">3</button>
                <button class="btn-clear" onclick="numpad(-1)">⌫</button>
                <button onclick="numpad(0)">0</button>
                <button style="background:#2e7d32;" onclick="confirmHu()">✓</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== EDIT HAND DIALOG ===== -->
<div class="mvms-dialog" id="editHandDialog">
    <div class="mvms-dialog-box">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <div class="mvms-dialog-title" style="margin-bottom:0;">
                ✏️ <?php echo $sv ? 'Redigera hand' : 'Edit hand'; ?> <span id="editHandNum"></span>
            </div>
            <button onclick="closeEditHand()" style="background:#dc3545;color:white;border:none;border-radius:8px;padding:8px 14px;font-size:1.1em;cursor:pointer;">✕</button>
        </div>

        <div class="mvms-dialog-title" style="font-size:1em;"><?php echo $sv ? 'Vinnare:' : 'Winner:'; ?></div>
        <div class="mvms-from-grid" id="editWinnerGrid"></div>

        <div class="mvms-dialog-title" style="font-size:1em;"><?php echo $sv ? 'Från vem?' : 'From whom?'; ?></div>
        <div class="mvms-from-grid" id="editFromGrid"></div>

        <div class="mvms-dialog-title" style="font-size:1em;"><?php echo $sv ? 'Poäng:' : 'Points:'; ?></div>
        <div class="mvms-points-display" id="editPoints">0</div>
        <div class="mvms-numpad">
            <button onclick="editNumpad(7)">7</button><button onclick="editNumpad(8)">8</button><button onclick="editNumpad(9)">9</button>
            <button onclick="editNumpad(4)">4</button><button onclick="editNumpad(5)">5</button><button onclick="editNumpad(6)">6</button>
            <button onclick="editNumpad(1)">1</button><button onclick="editNumpad(2)">2</button><button onclick="editNumpad(3)">3</button>
            <button class="btn-clear" onclick="editNumpad(-1)">⌫</button>
            <button onclick="editNumpad(0)">0</button>
            <button style="background:#2e7d32;" onclick="confirmEditHand()">✓</button>
        </div>
    </div>
</div>
<div class="mvms-dialog" id="penDialog">
    <div class="mvms-dialog-box">
        <div class="mvms-dialog-title"><?php echo $mt['penTitle']; ?></div>
        <div id="penGrid" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:16px 0;">
            <!-- Populated by JS -->
        </div>
        <div class="mvms-points-display" id="penPoints">0</div>
        <div style="margin:8px 0 16px;text-align:center;">
            <label style="font-size:0.92em;cursor:pointer;">
                <input type="checkbox" id="penDistribute" checked style="margin-right:6px;transform:scale(1.2);vertical-align:middle;">
                <?php echo $mt["penDist"]; ?>
            </label>
        </div>
        <div class="mvms-numpad">
            <button onclick="penNumpad(7)">7</button>
            <button onclick="penNumpad(8)">8</button>
            <button onclick="penNumpad(9)">9</button>
            <button onclick="penNumpad(4)">4</button>
            <button onclick="penNumpad(5)">5</button>
            <button onclick="penNumpad(6)">6</button>
            <button onclick="penNumpad(1)">1</button>
            <button onclick="penNumpad(2)">2</button>
            <button onclick="penNumpad(3)">3</button>
            <button class="btn-clear" onclick="penNumpad(-1)">✕</button>
            <button onclick="penNumpad(0)">0</button>
            <button style="background:#7b1fa2;" onclick="confirmPenalty()">✓</button>
        </div>
        <button class="mvms-btn mvms-btn-secondary" style="width:100%;margin-top:8px;" onclick="closePenalty()"><?php echo $mt['close']; ?></button>
    </div>
</div>

<script>
const WINDS = ['東','南','西','北'];
const WIND_NAMES = ['East','South','West','North'];
const WIND_FILES = ['east','south','west','north'];
function windImg(idx, size, variant) {
    variant = variant || 'dark';
    return `<img src="images/winds/wind-${WIND_FILES[idx]}-${variant}.png" alt="${WINDS[idx]}" style="width:${size}px;height:${size}px;object-fit:contain;vertical-align:middle;">`;
}

// Seat rotation maps: who sits at [öst, syd, väst, nord] per wind section
const SEAT_MAPS = [
    [0, 1, 2, 3], // East wind section (hand 1-4)
    [1, 0, 3, 2], // South wind section (hand 5-8)
    [2, 3, 1, 0], // West wind section (hand 9-12)
    [3, 2, 0, 1], // North wind section (hand 13-16)
];

// Get a player's current wind (0=E,1=S,2=W,3=N) for the current hand
function getPlayerWind(playerIdx) {
    const displayHand = Math.min(currentHand, 16);
    const windSection = Math.min(Math.floor((displayHand - 1) / 4), 3);
    const handInSection = (displayHand - 1) % 4;
    const seats = SEAT_MAPS[windSection];
    // Find which seat position this player occupies
    const seatPos = seats.indexOf(playerIdx);
    if (seatPos < 0) return playerIdx; // fallback
    return (seatPos - handInSection + 4) % 4;
}
const allPlayers = <?php echo json_encode($all_players); ?>;
const LANG = '<?php echo currentLang(); ?>';
const T = LANG === 'sv' ? {
    lang: 'sv',
    newGame: '🀄 Ny match',
    gameName: 'Matchnamn',
    gameNamePh: 'Valfritt',
    date: 'Datum',
    tapToSelect: 'Tryck för att välja spelare',
    start: '▶ STARTA',
    table: '🀄 BORD',
    list: '📋 LISTA',
    handOf: 'Hand',
    wind: 'vind',
    gameOver: 'Matchen slut',
    handsPlayed: 'händer spelade',
    draw: '🏳 OAVGJORD',
    undo: '↩ Ångra',
    penalty: '⚠ Straff',
    endGame: '🏁 Avsluta',
    saveGame: '💾 Spara match',
    saving: 'Sparar...',
    saved: '✅ Sparad!',
    savedApproved: 'Match #{n} sparad och fastställd.',
    savedPending: 'Match #{n} sparad — inväntar fastställande av admin.',
    viewGame: '👁 Visa match',
    backToMyPage: '🏠 Min sida',
    errorSave: '❌ Fel',
    newGameBtn: '🀄 Ny match',
    leaveRound: '🚪 Avsluta rundan',
    back: '← Tillbaka',
    results: '🏆 Slutresultat',
    bestHand: 'Bästa hand',
    selectAll: 'Välj alla 4 spelare',
    unique: 'Varje spelare måste vara unik',
    whoGotHu: 'Vem fick Mahjong?',
    discarder: 'Från vem?',
    points: 'Poäng:',
    min8: 'Minst 8 poäng för hu',
    selectPlayer: 'Välj spelare',
    searchPh: 'Skriv 2+ bokstäver...',
    searchMin: 'Skriv minst 2 tecken',
    noMatch: 'Inga träffar',
    penTitle: '⚠️ Straff',
    penDistribute: 'Fördela som plus till övriga spelare',
    penSelectPlayer: 'Välj en spelare',
    penEnterAmount: 'Ange straffbelopp',
    deleteHand: 'Radera hand',
    deletePenalty: 'Radera detta straff?',
    confirmLeave: 'Du har en pågående match. Vill du verkligen lämna?\n\nMatchen sparas automatiskt och kan återställas.',
    confirmNew: 'Starta ny match? Osparad data försvinner.',
    resumeGame: 'Återuppta tidigare match?',
    view: 'Visa →',
    close: 'Stäng',
} : {
    lang: 'en',
    newGame: '🀄 New Game',
    gameName: 'Game name',
    gameNamePh: 'Optional',
    date: 'Date',
    tapToSelect: 'Tap to select player',
    start: '▶ START',
    table: '🀄 TABLE',
    list: '📋 LIST',
    handOf: 'Hand',
    wind: 'wind',
    gameOver: 'Game Over',
    handsPlayed: 'hands played',
    draw: '🏳 DRAW',
    undo: '↩ Undo',
    penalty: '⚠ Penalty',
    endGame: '🏁 End Game',
    saveGame: '💾 Save Game',
    saving: 'Saving...',
    saved: '✅ Saved!',
    savedApproved: 'Game #{n} saved and confirmed.',
    savedPending: 'Game #{n} saved — awaiting admin confirmation.',
    viewGame: '👁 View Game',
    backToMyPage: '🏠 My Page',
    errorSave: '❌ Error',
    newGameBtn: '🀄 New Game',
    leaveRound: '🚪 Leave round',
    back: '← Back',
    results: '🏆 Final Results',
    bestHand: 'Best hand',
    selectAll: 'Select all 4 players',
    unique: 'Each player must be unique',
    whoGotHu: 'Who got Mahjong?',
    discarder: 'Discarder?',
    points: 'Points:',
    min8: 'Minimum 8 points for hu',
    selectPlayer: 'Select player',
    searchPh: 'Type 2+ letters to search...',
    searchMin: 'Type at least 2 characters',
    noMatch: 'No matches',
    penTitle: '⚠️ Penalty',
    penDistribute: 'Distribute as plus to other players',
    penSelectPlayer: 'Select a player',
    penEnterAmount: 'Enter penalty amount',
    deleteHand: 'Delete hand',
    deletePenalty: 'Delete this penalty?',
    confirmLeave: 'You have an active game. Are you sure you want to leave?\n\nThe game is auto-saved and can be restored.',
    confirmNew: 'Start a new game? Unsaved data will be lost.',
    resumeGame: 'Resume previous game?',
    view: 'View →',
    close: 'Close',
};

let players = [{},{},{},{}]; // {vms, name, first}
let hands = []; // [{points, winner, from}]
let penalties = []; // [{idx, amount}]
let scores = [0,0,0,0];
let currentHand = 1;
let huWinner = -1;
let huFrom = -2; // -1=self, -2=none, 0-3=player
let huPointsStr = '';
let penPlayer = -1;
let penPointsStr = '0';
let pickerIdx = -1;

// Player picker
function openPicker(idx) {
    pickerIdx = idx;
    document.getElementById('pickerTitle').innerHTML = windImg(idx, 24, 'dark') + ' ' + WIND_NAMES[idx] + ' — ' + T.selectPlayer;
    const searchEl = document.getElementById('pickerSearch');
    searchEl.value = '';
    document.getElementById('pickerResults').innerHTML = '<div style="color:#999;text-align:center;padding:20px;font-size:0.9em;">' + T.searchMin + '</div>';
    document.getElementById('pickerDialog').classList.add('open');
    // Focus immediately within user gesture to trigger keyboard on iOS
    searchEl.focus();
    // Fallback for slower devices
    setTimeout(() => searchEl.focus(), 50);
}

function closePicker() {
    document.getElementById('pickerDialog').classList.remove('open');
}

function pickFirstResult() {
    const firstResult = document.querySelector('#pickerResults div[onclick]');
    if (firstResult && firstResult.style.pointerEvents !== 'none') {
        firstResult.click();
    }
}

function filterSearch(q) {
    const box = document.getElementById('pickerResults');
    if (q.length < 2) {
        box.innerHTML = '<div style="color:#999;text-align:center;padding:20px;font-size:0.9em;">' + T.searchMin + '</div>';
        return;
    }
    q = q.toLowerCase();
    const results = allPlayers.filter(p =>
        (p.first_name + ' ' + p.last_name).toLowerCase().includes(q) || p.id.toLowerCase().includes(q)
    ).slice(0, 15);

    if (!results.length) {
        box.innerHTML = '<div style="color:#999;text-align:center;padding:20px;">' + T.noMatch + '</div>';
        return;
    }

    box.innerHTML = results.map(p => {
        const name = p.first_name + ' ' + p.last_name;
        const already = players.some((pl, i) => i !== pickerIdx && pl.vms === p.id);
        const style = already ? 'opacity:0.4;pointer-events:none;' : 'cursor:pointer;';
        return `<div onclick="pickPlayer('${p.id}', '${name.replace(/'/g, "\\'")}')"
                     style="padding:12px;border-bottom:1px solid #eee;${style}">
            <span style="font-weight:600;">${name}</span>
            <span style="float:right;color:#888;font-size:0.85em;">${p.id}</span>
        </div>`;
    }).join('');
}

function pickPlayer(vms, name) {
    const firstName = name.split(' ')[0];
    players[pickerIdx] = { vms: vms, name: name, first: firstName, displayFirst: firstName, slotLabel: name };
    closePicker();
    updateDuplicateNames();
    const allFilled = players.every(p => p.vms);
    document.getElementById('shuffleRow').style.display = allFilled ? 'block' : 'none';

    // Auto-advance: open picker for next empty slot
    if (!allFilled) {
        for (let next = pickerIdx + 1; next < 4; next++) {
            if (!players[next] || !players[next].vms) {
                setTimeout(() => openPicker(next), 200);
                return;
            }
        }
        // Check earlier slots too (in case user filled out of order)
        for (let next = 0; next < pickerIdx; next++) {
            if (!players[next] || !players[next].vms) {
                setTimeout(() => openPicker(next), 200);
                return;
            }
        }
    }
}

function updateSlot(idx) {
    const slot = document.getElementById('slot-' + idx);
    if (!slot) return;
    if (!players[idx] || !players[idx].vms) return;
    slot.textContent = players[idx].slotLabel;
    slot.style.color = '#000';
    slot.style.fontWeight = '600';
}

function shufflePlayers() {
    for (let i = 3; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [players[i], players[j]] = [players[j], players[i]];
    }
    updateDuplicateNames();
}

function updateDuplicateNames() {
    // Count how many times each first name appears
    const counts = {};
    players.forEach(p => {
        if (p.vms) counts[p.first] = (counts[p.first] || 0) + 1;
    });

    // For duplicate first names, try last name initial first
    players.forEach(p => {
        if (!p.vms) return;
        if (counts[p.first] > 1) {
            const lastName = p.name.split(' ').slice(1).join(' ');
            p._lastInitial = lastName ? lastName.charAt(0).toUpperCase() : '';
        }
    });

    // Check if first + last initial is enough to disambiguate
    const initialCounts = {};
    players.forEach(p => {
        if (!p.vms || counts[p.first] <= 1) return;
        const key = p.first + ' ' + p._lastInitial;
        initialCounts[key] = (initialCounts[key] || 0) + 1;
    });

    // Assign labels
    const seenInitials = {};
    players.forEach(p => {
        if (!p.vms) return;
        if (counts[p.first] <= 1) {
            // Unique first name — no suffix needed
            p.displayFirst = p.first;
            p.slotLabel = p.name;
        } else {
            const key = p.first + ' ' + p._lastInitial;
            if (initialCounts[key] <= 1) {
                // Last initial is enough: "Johan E"
                p.displayFirst = p.first + ' ' + p._lastInitial;
                p.slotLabel = p.name;
            } else {
                // Still ambiguous: "Johan E (1)", "Johan E (2)"
                seenInitials[key] = (seenInitials[key] || 0) + 1;
                const n = seenInitials[key];
                p.displayFirst = p.first + ' ' + p._lastInitial + ' (' + n + ')';
                p.slotLabel = p.name + ' (' + n + ')';
            }
        }
    });

    for (let i = 0; i < 4; i++) updateSlot(i);
}

function startGame() {
    for (let w = 0; w < 4; w++) {
        if (!players[w].vms) { alert(T.selectAll); return; }
    }
    const vms = players.map(p => p.vms);
    if (new Set(vms).size !== 4) { alert(T.unique); return; }

    scores = [0,0,0,0];
    hands = [];
    penalties = [];
    currentHand = 1;

    // Create a server draft session
    draftId = crypto.randomUUID ? crypto.randomUUID() : ('d-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9));
    draftPost({
        action: 'start_draft',
        draft_id: draftId,
        players: players.map(p => ({ vms: p.vms, name: p.name })),
        game_date: document.getElementById('gameDate').value || new Date().toISOString().slice(0, 10),
        game_name: document.getElementById('gameName').value || ''
    });

    showScreen('game');
    renderTable();
}

// ============================================================
// DRAFT — live hand-for-hand server backup + offline queue
// ============================================================
let draftId = null;
const DRAFT_Q_KEY = 'mvms_draft_queue';
let draftQueue = [];
let draftProcessing = false;

function draftQueueLoad() {
    try { draftQueue = JSON.parse(localStorage.getItem(DRAFT_Q_KEY) || '[]'); } catch(e) { draftQueue = []; }
}
function draftQueueSave() {
    try { localStorage.setItem(DRAFT_Q_KEY, JSON.stringify(draftQueue)); } catch(e) {}
}
function draftQueueClear() {
    draftQueue = [];
    try { localStorage.removeItem(DRAFT_Q_KEY); } catch(e) {}
}
function draftQueueAdd(body) {
    draftQueue.push({ body, ts: Date.now() });
    draftQueueSave();
    updateDraftBadge();
}

function updateDraftBadge() {
    let badge = document.getElementById('draftBadge');
    if (!badge) {
        badge = document.createElement('div');
        badge.id = 'draftBadge';
        badge.style.cssText = 'position:fixed;top:10px;left:10px;background:#e65100;color:white;padding:4px 10px;border-radius:20px;font-size:0.78em;font-weight:700;z-index:999;display:none;cursor:pointer;';
        badge.addEventListener('click', () => processDraftQueue());
        document.body.appendChild(badge);
    }
    const n = draftQueue.length;
    if (n > 0) {
        badge.textContent = (T.lang === 'sv' ? '⏳ ' + n + ' i kö' : '⏳ ' + n + ' queued');
        badge.style.display = 'block';
    } else {
        badge.style.display = 'none';
    }
}

async function processDraftQueue() {
    if (draftProcessing || draftQueue.length === 0) return;
    draftProcessing = true;
    while (draftQueue.length > 0) {
        const item = draftQueue[0];
        if (Date.now() - item.ts > 86400000) { draftQueue.shift(); draftQueueSave(); continue; }
        try {
            const r = await fetch('mobile.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(item.body)
            });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();
            if (d.error && !d.success) {
                // Server error, skip this item
                console.warn('Draft queue server error:', d.error);
            }
            draftQueue.shift();
            draftQueueSave();
            updateDraftBadge();
        } catch(e) {
            // Network error — stop and retry later
            break;
        }
    }
    draftProcessing = false;
    updateDraftBadge();
}

async function draftPost(body) {
    try {
        const r = await fetch('mobile.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body)
        });
        if (r.status === 401) {
            // Session expired — queue for later, don't lose data
            console.warn('Session expired during draft post — queuing');
            draftQueueAdd(body);
            return;
        }
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const d = await r.json();
        if (!d.success) console.warn('Draft action failed:', d.error);
    } catch(e) {
        // Network failure — queue it
        draftQueueAdd(body);
    }
}

function saveDraftHand(hand, handNumber) {
    if (!draftId) return;
    draftPost({
        action: 'save_draft_hand',
        draft_id: draftId,
        hand: { hand_number: handNumber, points: hand.points, winner: hand.winner, from: hand.from }
    });
}

function deleteDraftHand(handNumber) {
    if (!draftId) return;
    draftPost({
        action: 'delete_draft_hand',
        draft_id: draftId,
        hand_number: handNumber
    });
}

function saveDraftPenalty(penalty, penaltyIndex) {
    if (!draftId) return;
    draftPost({
        action: 'save_draft_penalty',
        draft_id: draftId,
        penalty: { penalty_index: penaltyIndex, player_idx: penalty.idx, amount: penalty.amount, distribute: penalty.distribute ? 1 : 0 }
    });
}

function deleteDraftPenalty(penaltyIndex) {
    if (!draftId) return;
    draftPost({
        action: 'delete_draft_penalty',
        draft_id: draftId,
        penalty_index: penaltyIndex
    });
}

async function discardDraft() {
    if (!draftId) return;
    try {
        await fetch('mobile.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'discard_draft', draft_id: draftId })
        });
    } catch(e) {
        console.log('Discard draft failed:', e.message);
    }
    draftId = null;
    draftQueueClear();
}

// Process queue when coming back online
window.addEventListener('online', () => setTimeout(() => processDraftQueue(), 1000));
// Periodic retry every 30s
setInterval(() => { if (draftQueue.length > 0 && navigator.onLine !== false) processDraftQueue(); }, 30000);
// Periodic autoSave every 30s as safety net
setInterval(() => { if (!gameDiscarded && typeof hands !== 'undefined' && hands.length > 0) { try { autoSave(); } catch(e) {} } }, 30000);
// Load queue on startup
draftQueueLoad();
updateDraftBadge();
if (draftQueue.length > 0) setTimeout(() => processDraftQueue(), 2000);

function safeLangSwitch(url) {
    try { autoSave(); saveTimerLocal(); } catch(e) {}
    var ok = !!window.localStorage.getItem('mvms_autosave');
    if (!ok && typeof hands !== 'undefined' && hands.length > 0) {
        var msg = T.lang === 'sv' ? 'Kunde inte spara lokalt. Byta språk ändå?' : 'Could not save locally. Switch language anyway?';
        if (!confirm(msg)) return;
    }
    location.replace(url);
}

function showScreen(name) {
    document.querySelectorAll('.mvms-screen').forEach(s => s.classList.remove('active'));
    document.getElementById('screen-' + name).classList.add('active');
}

function showGameTab(tab) {
    document.querySelectorAll('.mvms-tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('tab-table').style.display = tab === 'table' ? '' : 'none';
    document.getElementById('tab-list').style.display = tab === 'list' ? '' : 'none';
    document.getElementById('tab-clock').style.display = tab === 'clock' ? '' : 'none';
    if (tab === 'list') renderList();
}

// ===== TABLE RENDERING =====
function renderTable() {
    // Position layout per wind section:
    // East wind:  top=E(0) left=S(1) bottom=W(2) right=N(3)
    // South wind: top=S(1) left=E(0) bottom=N(3) right=W(2)
    // West wind:  top=W(2) left=N(3) bottom=S(1) right=E(0)
    // North wind: top=N(3) left=W(2) bottom=E(0) right=S(1)
    
    const displayHand = Math.min(currentHand, 16);
    const windSection = Math.min(Math.floor((displayHand - 1) / 4), 3);
    const gameOver = currentHand > 16;
    
    const seats = SEAT_MAPS[windSection];
    const seatMap = [
        { cls: 'mvms-seat-top',    w: seats[0] },
        { cls: 'mvms-seat-left',   w: seats[1] },
        { cls: 'mvms-seat-bottom', w: seats[2] },
        { cls: 'mvms-seat-right',  w: seats[3] },
    ];
    
    let html = '';
    
    // Calculate hand offset within current 4-hand section
    const handInSection = (displayHand - 1) % 4;
    
    seatMap.forEach((pos, seatPos) => {
        const w = pos.w;
        const s = scores[w];
        const cls = s > 0 ? 'positive' : (s < 0 ? 'negative' : 'zero');
        const currentWind = (seatPos - handInSection + 4) % 4;
        html += `<div class="mvms-seat ${pos.cls}" onclick="playerTapped(${w})">
            <div class="wind-char">${windImg(currentWind, 28, 'dark')}</div>
            <div class="score ${cls}">${s >= 0 ? '+' + s : s}</div>
            <div class="name">${players[w].displayFirst || players[w].first}</div>
            ${render32html(w)}
        </div>`;
    });

    if (gameOver) {
        html += `<div class="mvms-center">
            <div class="hand-num" style="font-size:1.1em;font-weight:700;">${T.gameOver}</div>
            <div style="margin-top:8px;font-size:0.85em;opacity:0.7;">16 ${T.handsPlayed}</div>
        </div>`;
    } else {
        html += `<div class="mvms-center">
            <div class="mvms-center-box">
                <div class="hand-num">${T.handOf} ${currentHand}/16</div>
                <div class="wind-indicator">${windImg(windSection, 22, 'dark')} ${T.wind}</div>
            </div>
        </div>`;
    }

    document.getElementById('gameTable').innerHTML = html;
}

// ===== 32-RULE =====
let show32 = false;

function toggle32() {
    show32 = !show32;
    const btn = document.getElementById('btn32');
    btn.style.background = show32 ? '#7e57c2' : '#5c4e8a';
    btn.style.boxShadow = show32 ? '0 0 8px rgba(126,87,194,0.6)' : 'none';
    document.querySelectorAll('.rule32').forEach(el => {
        el.classList.toggle('show', show32);
    });
}

function calc32(myScore, myPos, allScores) {
    const ranked = allScores
        .map((sc, i) => ({ score: sc, pos: i }))
        .sort((a, b) => b.score - a.score);
    
    const myRank = ranked.findIndex(r => r.pos === myPos);
    
    // MCR 32-rule — minimum hu points to strictly overtake:
    //
    // O (hu from someone else, target only pays 8):
    //   My gain: pts+16, target loss: 8. Swing = pts+24.
    //   Need pts+24 > diff → pts > diff-24 → pts = max(8, diff-23)
    //
    // D (hu directly from target, target pays pts+8):
    //   My gain: pts+16, target loss: pts+8. Swing = 2*pts+24.
    //   Need 2*pts+24 > diff → pts > (diff-24)/2 → pts = max(8, floor((diff-24)/2)+1)
    //   Simplified: max(8, floor(diff/2)-11) when diff is even, etc.
    //
    // S (self-drawn, everyone pays pts+8):
    //   My gain: 3*(pts+8), target loss: pts+8. Swing = 4*(pts+8).
    //   Need 4*(pts+8) > diff → pts+8 > diff/4 → pts > diff/4-8
    //   pts = max(8, floor(diff/4)-7) if diff%4==0 else max(8, floor(diff/4)-7+1)
    //   Simplified: max(8, ceil(diff/4)-7) but need strict >, so if 4*(pts+8)==diff exactly, need +1
    
    const lines = [];
    for (let r = 0; r < myRank; r++) {
        const target = ranked[r];
        const diff = target.score - myScore;
        if (diff <= 0) continue;
        
        // O: pts+24 > diff → pts >= diff-23 (strictly overtake)
        const o = Math.max(8, diff - 23);
        
        // D: 2*pts+24 > diff → pts >= ceil((diff-23)/2)
        const d = Math.max(8, Math.ceil((diff - 23) / 2));
        
        // S: 4*(pts+8) > diff → pts > diff/4-8
        // Smallest integer pts strictly greater than diff/4-8
        let sMin = diff / 4 - 8;
        let s = (sMin === Math.floor(sMin)) ? sMin + 1 : Math.ceil(sMin);
        s = Math.max(8, s);
        
        lines.push({ targetPos: target.pos, s: s, d: d, o: o });
    }
    return lines;
}

function render32html(windIdx) {
    const lines = calc32(scores[windIdx], windIdx, scores);
    if (lines.length === 0) return '';
    
    let html = '<div class="rule32' + (show32 ? ' show' : '') + '">';
    html += '<table style="border-collapse:collapse;font-size:1em;line-height:1.4;">';
    html += '<tr style="font-size:0.8em;color:#555;font-weight:600;"><td style="padding:0 3px;">#</td><td style="padding:0 3px;">S</td><td style="padding:0 3px;">D</td><td style="padding:0 3px;">O</td></tr>';
    lines.forEach((line, i) => {
        html += `<tr>
            <td style="padding:0 3px;color:#555;font-size:0.8em;">${i + 1}</td>
            <td style="padding:0 3px;color:#7b1fa2;font-weight:600;">${line.s}</td>
            <td style="padding:0 3px;color:#0d47a1;font-weight:600;">${line.d}</td>
            <td style="padding:0 3px;color:#e65100;font-weight:600;">${line.o}</td>
        </tr>`;
    });
    html += '</table></div>';
    return html;
}

// ===== PLAYER TAPPED = MAHJONG =====
function playerTapped(w) {
    if (currentHand > 16) return;
    huWinner = w;
    huFrom = -2;
    huPointsStr = '';

    document.getElementById('huWinnerWind').innerHTML = windImg(getPlayerWind(w), 36, 'dark');
    document.getElementById('huWinnerName').textContent = players[w].displayFirst || players[w].first;
    document.getElementById('huPoints').textContent = '0';

    // Build from grid (other 3 players)
    let fromHtml = '';
    for (let i = 0; i < 4; i++) {
        if (i === w) continue;
        fromHtml += `<div class="mvms-from-btn" onclick="selectFrom(${i})" id="from-${i}">
            <span class="wind-char">${windImg(getPlayerWind(i), 24, 'dark')}</span>
            <span class="name">${players[i].displayFirst || players[i].first}</span>
        </div>`;
    }
    document.getElementById('fromGrid').innerHTML = fromHtml;

    document.getElementById('huDialog').classList.add('open');
}

function cancelHu() {
    huWinner = -1;
    huFrom = -2;
    huPointsStr = '';
    document.getElementById('huDialog').classList.remove('open');
}

function selectFrom(idx) {
    huFrom = idx;
    document.querySelectorAll('.mvms-from-btn').forEach(b => b.classList.remove('selected'));
    const el = document.getElementById('from-' + idx);
    if (el) el.classList.add('selected');
}

function numpad(n) {
    if (n === -1) { huPointsStr = ''; }
    else { huPointsStr += '' + n; }
    if (huPointsStr.length > 3) huPointsStr = huPointsStr.slice(-3);
    document.getElementById('huPoints').textContent = huPointsStr || '0';
}

async function guardTakeover() {
    if (takeoverLocked) return true;
    if (!draftId) return false;
    try {
        const r = await fetch('mobile.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'check_takeover', draft_id: draftId })
        });
        const d = await r.json();
        if (d.taken_over) {
            checkTakeoverStatus(); // triggers the full overlay
            return true;
        }
    } catch(e) { /* offline — allow */ }
    return false;
}

async function confirmHu() {
    const pts = parseInt(huPointsStr) || 0;
    if (pts < 8 && pts !== 0) { alert(T.min8); return; }
    if (await guardTakeover()) return;

    const from = (huFrom === -2) ? -1 : huFrom; // -2=self pick becomes -1

    hands.push({ points: pts, winner: huWinner, from: from });
    saveDraftHand({ points: pts, winner: huWinner, from: from }, hands.length);

    // Calculate score changes
    if (pts > 0) {
        if (from === -1) {
            // Self-drawn
            for (let i = 0; i < 4; i++) {
                scores[i] += (i === huWinner) ? (pts + 8) * 3 : -(pts + 8);
            }
        } else {
            // From someone
            for (let i = 0; i < 4; i++) {
                if (i === huWinner) scores[i] += 8 + 8 + (8 + pts);
                else if (i === from) scores[i] -= (8 + pts);
                else scores[i] -= 8;
            }
        }
    }

    currentHand++;
    document.getElementById('huDialog').classList.remove('open');
    renderTable();

    if (currentHand > 16) {
        setTimeout(() => endGame(), 500);
    }
}

async function drawHand() {
    if (await guardTakeover()) return;
    hands.push({ points: 0, winner: 0, from: -2 });
    saveDraftHand({ points: 0, winner: 0, from: -2 }, hands.length);
    currentHand++;
    renderTable();
    if (currentHand > 16) {
        setTimeout(() => endGame(), 500);
    }
}

async function undoLastHand() {
    if (hands.length === 0) return;
    const msg = T.lang === 'sv'
        ? `Ångra hand ${hands.length}?`
        : `Undo hand ${hands.length}?`;
    if (!confirm(msg)) return;
    if (await guardTakeover()) return;
    deleteDraftHand(hands.length); // delete the last hand on server (1-based)
    const h = hands.pop();
    currentHand--;

    // Reverse score
    if (h.points > 0) {
        if (h.from === -1) {
            for (let i = 0; i < 4; i++) {
                scores[i] -= (i === h.winner) ? (h.points + 8) * 3 : -(h.points + 8);
            }
        } else if (h.from >= 0) {
            for (let i = 0; i < 4; i++) {
                if (i === h.winner) scores[i] -= 8 + 8 + (8 + h.points);
                else if (i === h.from) scores[i] += (8 + h.points);
                else scores[i] += 8;
            }
        }
    }
    renderTable();
}

// ===== LIST VIEW =====
function renderList() {
    const cols = `28px 28px repeat(4, 1fr) 44px`;

    // Header
    let html = `<div class="hand-row-wrap">
        <div class="hand-row-inner header" style="grid-template-columns:${cols};">
            <div>#</div><div>Pts</div>
            ${players.map(p => `<div style="text-align:right;">${p.displayFirst || p.first}</div>`).join('')}
            <div></div>
        </div>
    </div>`;

    let cumul = [0,0,0,0];
    hands.forEach((h, idx) => {
        const windSep = (idx % 4 === 0 && idx > 0) ? ' wind-sep' : '';
        let deltas = [0,0,0,0];
        if (h.points > 0) {
            if (h.from === -1) {
                for (let i = 0; i < 4; i++) deltas[i] = (i === h.winner) ? (h.points+8)*3 : -(h.points+8);
            } else {
                for (let i = 0; i < 4; i++) {
                    if (i === h.winner) deltas[i] = 8+8+(8+h.points);
                    else if (i === h.from) deltas[i] = -(8+h.points);
                    else deltas[i] = -8;
                }
            }
        }
        for (let i = 0; i < 4; i++) cumul[i] += deltas[i];

        // Determine color class per player
        const cls = deltas.map((d, i) => {
            if (h.points === 0) return 'draw';
            if (i === h.winner && h.from === -1) return 'win-self';   // blue: self-drawn win
            if (i === h.winner) return 'win-disc';                     // green: won from discard
            if (h.from >= 0 && i === h.from) return 'lost-disc';      // red: discarded
            if (d < 0) return 'paid';                                   // grey: paid -8
            return '';
        });

        const pts = h.points || 0;
        const ptsLabel = h.from === -1 ? `${pts}🀄` : `${pts}`;

        html += `<div class="hand-row-wrap${windSep}" data-idx="${idx}">
            <div class="hand-row-actions">
                <button class="act-edit" onclick="editHand(${idx})">✏️</button>
                <button class="act-del"  onclick="confirmDeleteHand(${idx})">✕</button>
            </div>
            <div class="hand-row-inner" style="grid-template-columns:${cols};">
                <div style="color:#888;">${idx+1}</div>
                <div style="font-size:0.78em;color:#555;">${ptsLabel}</div>
                ${deltas.map((d, i) => {
                    const sign = d > 0 ? '+' : '';
                    const cv = cumul[i];
                    const bgCls = cls[i] === 'win-self' ? 'bg-win-self' : cls[i] === 'win-disc' ? 'bg-win-disc' : cls[i] === 'lost-disc' ? 'bg-lost-disc' : '';
                    return `<div class="hr-cell ${bgCls}">
                        <div class="hr-delta ${cls[i]}">${d !== 0 ? sign+d : '0'}</div>
                        <div class="hr-cumul">${cv >= 0 ? '+' : ''}${cv}</div>
                    </div>`;
                }).join('')}
                <div class="row-icons">
                    <button onclick="editHand(${idx})" title="Redigera">✏️</button>
                    <button onclick="confirmDeleteHand(${idx})" style="color:#c62828;" title="Radera">✕</button>
                </div>
            </div>
        </div>`;
    });

    document.getElementById('handList').innerHTML = html;
    attachSwipe();

    // Penalties
    if (penalties.length > 0) {
        let penHtml = `<div class="hand-row-wrap" style="background:#fff3cd;border-top:2px solid #ffc107;">
            <div class="hand-row-inner" style="grid-template-columns:1fr;">
                <div style="font-weight:700;color:#856404;">⚠ ${T.penTitle}</div>
            </div>
        </div>`;
        penalties.forEach((p, pidx) => {
            const share = p.distribute ? Math.floor(p.amount / 3) : 0;
            const remainder = p.distribute ? (p.amount - share * 3) : 0;
            let firstOther = true;
            penHtml += `<div class="hand-row-wrap">
                <div class="hand-row-actions">
                    <button class="act-del" onclick="deletePenalty(${pidx})">✕</button>
                </div>
                <div class="hand-row-inner" style="background:#fff8e1;grid-template-columns:${cols};">
                    <div>⚠</div>
                    <div style="font-size:0.75em;color:#856404;">${p.distribute ? '±' : '−'}</div>
                    ${players.map((pl, i) => {
                        if (i === p.idx) return `<div class="hr-cell"><div class="hr-delta lost-disc">-${p.amount}</div></div>`;
                        if (p.distribute) {
                            let bonus = share;
                            if (firstOther && remainder > 0) { bonus += remainder; firstOther = false; } else { firstOther = false; }
                            return `<div class="hr-cell"><div class="hr-delta win-disc">+${bonus}</div></div>`;
                        }
                        return `<div></div>`;
                    }).join('')}
                    <div class="row-icons">
                        <button onclick="deletePenalty(${pidx})" style="color:#c62828;">✕</button>
                    </div>
                </div>
            </div>`;
        });
        document.getElementById('handList').innerHTML += penHtml;
    }

    // Totals
    document.getElementById('totalsBar').innerHTML = scores.map((s, i) =>
        `<div><div class="total-name">${players[i].displayFirst || players[i].first}</div><div class="total-score${s < 0 ? ' neg' : (s > 0 ? ' pos' : '')}">${s >= 0 ? '+' : ''}${s}</div></div>`
    ).join('');
}

// ===== SWIPE TO REVEAL ACTIONS =====
function attachSwipe() {
    document.querySelectorAll('.hand-row-wrap[data-idx]').forEach(row => {
        let startX = 0, startY = 0, swiping = false;
        row.addEventListener('touchstart', e => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            swiping = true;
        }, { passive: true });
        row.addEventListener('touchend', e => {
            if (!swiping) return;
            const dx = e.changedTouches[0].clientX - startX;
            const dy = Math.abs(e.changedTouches[0].clientY - startY);
            if (dy > 30) return; // vertical scroll, ignore
            if (dx < -40) {
                // Close all others first
                document.querySelectorAll('.hand-row-wrap.swiped').forEach(r => { if (r !== row) r.classList.remove('swiped'); });
                row.classList.add('swiped');
            } else if (dx > 20) {
                row.classList.remove('swiped');
            }
            swiping = false;
        }, { passive: true });
    });
    // Tap anywhere else closes swipe
    document.addEventListener('touchstart', e => {
        if (!e.target.closest('.hand-row-wrap')) {
            document.querySelectorAll('.hand-row-wrap.swiped').forEach(r => r.classList.remove('swiped'));
        }
    }, { passive: true });
}

// ===== CONFIRM DELETE =====
function confirmDeleteHand(idx) {
    // Close any swipe first
    document.querySelectorAll('.hand-row-wrap.swiped').forEach(r => r.classList.remove('swiped'));
    const h = hands[idx];
    const handNum = h ? idx + 1 : idx;
    const msg = (T.lang === 'sv') ? `Radera hand ${handNum}?` : `Delete hand ${handNum}?`;
    if (confirm(msg)) deleteHand(idx);
}

// ===== EDIT HAND =====
let editIdx = -1;

function editHand(idx) {
    document.querySelectorAll('.hand-row-wrap.swiped').forEach(r => r.classList.remove('swiped'));
    const h = hands[idx];
    if (!h || h.points === undefined) return;
    editIdx = idx;

    // Populate edit dialog
    document.getElementById('editHandNum').textContent = idx + 1;
    document.getElementById('editPoints').textContent = h.points || '0';
    editPointsStr = String(h.points || '');

    // Winner buttons
    let winHtml = '';
    for (let i = 0; i < 4; i++) {
        const sel = i === h.winner ? 'style="border-color:#005B99;background:#e3f2fd;"' : '';
        winHtml += `<div class="mvms-from-btn" id="ew-${i}" onclick="setEditWinner(${i})" ${sel}>
            <span class="wind-char">${windImg(getPlayerWind(i), 22, 'dark')}</span>
            <span class="name">${players[i].displayFirst || players[i].first}</span>
        </div>`;
    }
    document.getElementById('editWinnerGrid').innerHTML = winHtml;
    editWinner = h.winner >= 0 ? h.winner : -1;

    // From buttons
    let fromHtml = `<div class="mvms-from-btn${h.from === -1 ? ' selected' : ''}" onclick="setEditFrom(-1)" id="ef-self">
        <span class="wind-char">🀄</span>
        <span class="name">${T.lang === 'sv' ? 'Självdragen' : 'Self'}</span>
    </div>`;
    for (let i = 0; i < 4; i++) {
        if (i === h.winner) continue;
        const sel = i === h.from ? ' selected' : '';
        fromHtml += `<div class="mvms-from-btn${sel}" id="ef-${i}" onclick="setEditFrom(${i})">
            <span class="wind-char">${windImg(getPlayerWind(i), 22, 'dark')}</span>
            <span class="name">${players[i].displayFirst || players[i].first}</span>
        </div>`;
    }
    document.getElementById('editFromGrid').innerHTML = fromHtml;
    editFrom = h.from;

    document.getElementById('editHandDialog').classList.add('open');
}

let editWinner = -1, editFrom = -2, editPointsStr = '';

function setEditWinner(w) {
    editWinner = w;
    document.querySelectorAll('[id^="ew-"]').forEach(el => el.style.borderColor = el.style.background = '');
    const el = document.getElementById('ew-' + w);
    if (el) { el.style.borderColor = '#005B99'; el.style.background = '#e3f2fd'; }
    // Rebuild from grid without winner
    let fromHtml = `<div class="mvms-from-btn${editFrom === -1 ? ' selected' : ''}" onclick="setEditFrom(-1)" id="ef-self">
        <span class="wind-char">🀄</span>
        <span class="name">${T.lang === 'sv' ? 'Självdragen' : 'Self'}</span>
    </div>`;
    for (let i = 0; i < 4; i++) {
        if (i === w) continue;
        const sel = i === editFrom ? ' selected' : '';
        fromHtml += `<div class="mvms-from-btn${sel}" id="ef-${i}" onclick="setEditFrom(${i})">
            <span class="wind-char">${windImg(getPlayerWind(i), 22, 'dark')}</span>
            <span class="name">${players[i].displayFirst || players[i].first}</span>
        </div>`;
    }
    document.getElementById('editFromGrid').innerHTML = fromHtml;
}

function setEditFrom(f) {
    editFrom = f;
    document.querySelectorAll('[id^="ef-"]').forEach(el => el.classList.remove('selected'));
    const key = f === -1 ? 'ef-self' : 'ef-' + f;
    const el = document.getElementById(key);
    if (el) el.classList.add('selected');
}

function editNumpad(n) {
    if (n === -1) { editPointsStr = ''; }
    else { editPointsStr += '' + n; }
    if (editPointsStr.length > 3) editPointsStr = editPointsStr.slice(-3);
    document.getElementById('editPoints').textContent = editPointsStr || '0';
}

async function confirmEditHand() {
    const pts = parseInt(editPointsStr) || 0;
    if (pts < 8 && pts !== 0) { alert(T.min8); return; }
    if (editWinner < 0 || editFrom === -2) { alert(T.lang === 'sv' ? 'Välj vinnare och varifrån' : 'Select winner and source'); return; }
    if (await guardTakeover()) return;

    // Reverse old hand scores
    const old = hands[editIdx];
    if (old.points > 0) {
        if (old.from === -1) {
            for (let i = 0; i < 4; i++) scores[i] -= (i === old.winner) ? (old.points+8)*3 : -(old.points+8);
        } else if (old.from >= 0) {
            for (let i = 0; i < 4; i++) {
                if (i === old.winner) scores[i] -= 8+8+(8+old.points);
                else if (i === old.from) scores[i] += (8+old.points);
                else scores[i] += 8;
            }
        }
    }

    // Apply new scores
    if (pts > 0) {
        if (editFrom === -1) {
            for (let i = 0; i < 4; i++) scores[i] += (i === editWinner) ? (pts+8)*3 : -(pts+8);
        } else {
            for (let i = 0; i < 4; i++) {
                if (i === editWinner) scores[i] += 8+8+(8+pts);
                else if (i === editFrom) scores[i] -= (8+pts);
                else scores[i] -= 8;
            }
        }
    }

    hands[editIdx] = { ...old, points: pts, winner: editWinner, from: editFrom };
    saveDraftHand(hands[editIdx], editIdx + 1); // re-save edited hand
    document.getElementById('editHandDialog').classList.remove('open');
    renderTable();
    renderList();
}

function closeEditHand() {
    document.getElementById('editHandDialog').classList.remove('open');
    editIdx = -1;
}

// Recalculate all scores from scratch
function recalcScores() {
    scores = [0,0,0,0];
    hands.forEach(h => {
        if (h.points > 0) {
            if (h.from === -1) {
                for (let i = 0; i < 4; i++) scores[i] += (i === h.winner) ? (h.points+8)*3 : -(h.points+8);
            } else if (h.from >= 0) {
                for (let i = 0; i < 4; i++) {
                    if (i === h.winner) scores[i] += 8+8+(8+h.points);
                    else if (i === h.from) scores[i] -= (8+h.points);
                    else scores[i] -= 8;
                }
            }
        }
    });
    penalties.forEach(p => {
        scores[p.idx] -= p.amount;
        if (p.distribute) {
            const share = Math.floor(p.amount / 3);
            const remainder = p.amount - share * 3;
            let firstOther = true;
            for (let i = 0; i < 4; i++) {
                if (i !== p.idx) {
                    scores[i] += share;
                    if (firstOther && remainder > 0) { scores[i] += remainder; firstOther = false; }
                    else { firstOther = false; }
                }
            }
        }
    });
}

async function deleteHand(idx) {
    if (!confirm(T.deleteHand + ' ' + (idx + 1) + '?')) return;
    if (await guardTakeover()) return;
    deleteDraftHand(idx + 1); // 1-based hand_number
    hands.splice(idx, 1);
    currentHand = hands.length + 1;
    // Re-save all remaining hands to server (renumber)
    hands.forEach((h, i) => saveDraftHand(h, i + 1));
    recalcScores();
    renderList();
    renderTable();
}

async function deletePenalty(idx) {
    if (!confirm(T.deletePenalty)) return;
    if (await guardTakeover()) return;
    deleteDraftPenalty(idx);
    penalties.splice(idx, 1);
    // Re-save remaining penalties (renumber)
    penalties.forEach((p, i) => saveDraftPenalty(p, i));
    recalcScores();
    renderList();
    renderTable();
}

// ===== PENALTY =====
function showPenalty() {
    penPlayer = -1;
    penPointsStr = '';
    let html = '';
    for (let i = 0; i < 4; i++) {
        html += `<div class="mvms-from-btn" onclick="selectPenPlayer(${i})" id="pen-${i}">
            <span class="wind-char">${windImg(getPlayerWind(i), 24, 'dark')}</span>
            <span class="name">${players[i].displayFirst || players[i].first}</span>
        </div>`;
    }
    document.getElementById('penGrid').innerHTML = html;
    document.getElementById('penPoints').textContent = '0';
    document.getElementById('penDistribute').checked = true;
    document.getElementById('penDialog').classList.add('open');
}

function selectPenPlayer(idx) {
    penPlayer = idx;
    document.querySelectorAll('#penGrid .mvms-from-btn').forEach(b => b.classList.remove('selected'));
    document.getElementById('pen-' + idx).classList.add('selected');
}

function penNumpad(n) {
    if (n === -1) penPointsStr = '';
    else penPointsStr += '' + n;
    if (penPointsStr.length > 3) penPointsStr = penPointsStr.slice(-3);
    document.getElementById('penPoints').textContent = penPointsStr || '0';
}

async function confirmPenalty() {
    if (penPlayer < 0) { alert(T.penSelectPlayer); return; }
    const amt = parseInt(penPointsStr) || 0;
    if (amt <= 0) { alert(T.penEnterAmount); return; }
    if (await guardTakeover()) return;
    const distribute = document.getElementById('penDistribute').checked;
    
    penalties.push({ idx: penPlayer, amount: amt, distribute: distribute });
    saveDraftPenalty({ idx: penPlayer, amount: amt, distribute: distribute }, penalties.length - 1);
    scores[penPlayer] -= amt;
    
    if (distribute) {
        // Distribute equally to other 3 players
        const share = Math.floor(amt / 3);
        const remainder = amt - (share * 3);
        for (let i = 0; i < 4; i++) {
            if (i !== penPlayer) scores[i] += share;
        }
        // Give remainder to first non-penalized player (keeps sum = 0)
        if (remainder > 0) {
            for (let i = 0; i < 4; i++) {
                if (i !== penPlayer) { scores[i] += remainder; break; }
            }
        }
    }
    
    closePenalty();
    renderTable();
}

function closePenalty() {
    document.getElementById('penDialog').classList.remove('open');
}

// ===== END GAME =====
async function endGame() {
    if (await guardTakeover()) return;
    // Sort by score
    let ranked = players.map((p, i) => ({ ...p, idx: i, score: scores[i] }));
    ranked.sort((a, b) => b.score - a.score);

    // Table points
    const bpBase = [4, 2, 1, 0];
    ranked.forEach((p, i) => { p.tp = bpBase[i]; });
    // Handle ties
    for (let i = 0; i < 4; i++) {
        let same = 1, sum = bpBase[i];
        for (let j = i + 1; j < 4; j++) {
            if (ranked[j].score === ranked[i].score) { same++; sum += bpBase[j]; } else break;
        }
        const tp = Math.round(sum / same * 100) / 100;
        for (let k = i; k < i + same && k < 4; k++) ranked[k].tp = tp;
        i += same - 1;
    }

    // Best hand
    let bestHand = 0, bestPlayer = '';
    hands.forEach(h => {
        if (h.points > bestHand) { bestHand = h.points; bestPlayer = players[h.winner].first; }
    });

    const ranks = ['🏆', '🥈', '🥉', '4'];
    let html = '';
    ranked.forEach((p, i) => {
        const mpCls = p.score < 0 ? ' neg' : '';
        html += `<div class="mvms-result-row">
            <div class="mvms-result-rank">${ranks[i]}</div>
            <div class="mvms-result-name">${p.name}</div>
            <div class="mvms-result-tp">${p.tp}</div>
            <div class="mvms-result-mp${mpCls}">${p.score >= 0 ? '+' : ''}${p.score}</div>
        </div>`;
    });

    if (bestHand > 0) {
        html += `<hr style="margin:16px 0;border:none;border-top:1px solid #eee;">
                 <div style="text-align:center;color:#888;">${T.bestHand}: <strong>${bestHand}</strong> — ${bestPlayer}</div>`;
    }

    document.getElementById('resultsBox').innerHTML = html;
    document.getElementById('btnSave').disabled = false;
    document.getElementById('btnSave').textContent = T.saveGame;
    document.getElementById('saveMsg').textContent = '';
    showScreen('results');
}

function backToGame() { showScreen('game'); }

async function discardGame() {
    const msg1 = T.lang === 'sv' 
        ? 'Är du säker på att du vill radera matchen? Det går inte att ångra!'
        : 'Are you sure you want to delete the game? This cannot be undone!';
    if (!confirm(msg1)) return;
    const msg2 = T.lang === 'sv'
        ? 'Är du HELT säker på att du vill radera matchen? Det går inte att ångra!'
        : 'Are you COMPLETELY sure you want to delete the game? This cannot be undone!';
    if (!confirm(msg2)) return;
    gameDiscarded = true; clearTimerLocal();
    await discardDraft();
    window.localStorage.removeItem('mvms_autosave');
    window.location.href = 'my-page.php';
}

async function newGame() {
    const msg1 = T.lang === 'sv'
        ? 'Är du säker på att du vill starta en ny match? Osparad data försvinner.'
        : 'Are you sure you want to start a new game? Unsaved data will be lost.';
    if (!confirm(msg1)) return;
    const msg2 = T.lang === 'sv'
        ? 'Är du HELT säker? All data i pågående match raderas!'
        : 'Are you COMPLETELY sure? All data in the current game will be deleted!';
    if (!confirm(msg2)) return;
    gameDiscarded = true; clearTimerLocal();
    await discardDraft();
    window.localStorage.removeItem('mvms_autosave');
    showScreen('setup');
    // Reset state for new game
    hands = []; penalties = []; scores = [0,0,0,0]; currentHand = 1; draftId = null;
    players = [{vms:'',name:'',first:'',displayFirst:'',slotLabel:''},{vms:'',name:'',first:'',displayFirst:'',slotLabel:''},{vms:'',name:'',first:'',displayFirst:'',slotLabel:''},{vms:'',name:'',first:'',displayFirst:'',slotLabel:''}];
    timerSeconds = timerDefaultSeconds; timerRunning = false; timerStartedAt = null; timerAlarmFired = false;
    clearInterval(timerInterval);
    updateTimerDisplay();
    gameDiscarded = false;
}

// ===== SAVE =====
async function saveGame() {
    if (await guardTakeover()) return;
    document.getElementById('btnSave').disabled = true;
    document.getElementById('btnSave').textContent = T.saving;

    const data = {
        action: 'save_game',
        draft_id: draftId || null,
        players: players.map(p => ({ vms: p.vms, name: p.name })),
        hands: hands,
        penalties: penalties,
        game_date: document.getElementById('gameDate').value || new Date().toISOString().slice(0, 10),
        game_name: document.getElementById('gameName').value
    };

    fetch('mobile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            gameDiscarded = true; clearTimerLocal();
            draftId = null;
            draftQueueClear();
            window.localStorage.removeItem('mvms_autosave');
            document.getElementById('btnSave').textContent = T.saved;
            const msg = res.approved
                ? T.savedApproved.replace('{n}', res.game_number)
                : T.savedPending.replace('{n}', res.game_number);
            document.getElementById('saveMsg').innerHTML = 
                msg + `<div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;justify-content:center;">` +
                `<a href="view-game.php?id=${res.game_id}" style="display:inline-block;padding:10px 20px;background:#2e7d32;color:white;text-decoration:none;border-radius:6px;font-weight:bold;">${T.viewGame}</a>` +
                `<button onclick="location.reload()" style="padding:10px 20px;background:#2196F3;color:white;border:none;border-radius:6px;font-weight:bold;cursor:pointer;">${T.newGameBtn}</button>` +
                `<a href="games.php" style="display:inline-block;padding:10px 20px;background:#005B99;color:white;text-decoration:none;border-radius:6px;font-weight:bold;">${T.leaveRound}</a>` +
                `<a href="my-page.php" style="display:inline-block;padding:10px 20px;background:#666;color:white;text-decoration:none;border-radius:6px;font-weight:bold;">${T.backToMyPage}</a>` +
                `</div>`;
        } else {
            document.getElementById('btnSave').textContent = T.errorSave;
            document.getElementById('saveMsg').textContent = res.error || 'Save failed';
            document.getElementById('btnSave').disabled = false;
        }
    })
    .catch(err => {
        document.getElementById('btnSave').textContent = T.errorSave;
        document.getElementById('saveMsg').textContent = err.message;
        document.getElementById('btnSave').disabled = false;
    });
}

// ===== AUTOSAVE to localStorage =====
let gameDiscarded = false;

function autoSave() {
    if (gameDiscarded) return;
    try {
        // Calculate current timerSeconds if running
        let savedTimerSeconds = timerSeconds;
        if (timerRunning && timerStartedAt) {
            const elapsed = Math.floor((Date.now() - timerStartedAt) / 1000);
            savedTimerSeconds = Math.max(0, timerSecondsAtStart - elapsed);
        }
        const state = {
            players, hands, penalties, scores, currentHand, draftId,
            gameName: document.getElementById('gameName').value,
            savedAt: Date.now(),
            timer: {
                timerSeconds: savedTimerSeconds,
                timerRunning: timerRunning,
                timerStartedAt: timerStartedAt,
                timerSecondsAtStart: timerSecondsAtStart,
                savedAt: Date.now()
            }
        };
        window.localStorage.setItem('mvms_autosave', JSON.stringify(state));
        updateLastSaved();
    } catch(e) {
        if (!window._autoSaveWarnShown && typeof hands !== 'undefined' && hands.length > 0) {
            window._autoSaveWarnShown = true;
            var msg = T.lang === 'sv'
                ? '⚠ Kunde inte spara lokalt (lagring full?). Dina händer sparas till servern men den lokala säkerhetskopian fungerar inte.'
                : '⚠ Could not save locally (storage full?). Your hands are saved to the server but the local backup is not working.';
            setTimeout(() => alert(msg), 100);
        }
    }
}

function updateLastSaved() {
    var el = document.getElementById('lastSavedInd');
    if (!el) {
        el = document.createElement('div');
        el.id = 'lastSavedInd';
        el.style.cssText = 'position:fixed;bottom:4px;right:8px;font-size:0.65em;color:#999;z-index:50;pointer-events:none;';
        document.body.appendChild(el);
    }
    var now = new Date();
    el.textContent = (T.lang === 'sv' ? 'Sparad ' : 'Saved ') + now.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
}

function autoLoad() {
    try {
        const raw = window.localStorage.getItem('mvms_autosave');
        if (!raw) return false;
        const state = JSON.parse(raw);
        if (!state.players || !state.players[0].vms) return false;
        // If saved very recently (language switch, page reload), skip confirm dialog
        const isQuickReload = state.savedAt && (Date.now() - state.savedAt < 10000);
        if (!isQuickReload) {
            if (!confirm(T.resumeGame)) { window.localStorage.removeItem('mvms_autosave'); return false; }
        }
        players = state.players;
        hands = state.hands;
        penalties = state.penalties || [];
        scores = state.scores;
        currentHand = state.currentHand;
        draftId = state.draftId || null;
        if (state.gameName) document.getElementById('gameName').value = state.gameName;

        // Restore timer BEFORE renderTable (which triggers autoSave)
        if (state.timer) restoreTimerFromServer(state.timer);

        showScreen('game');
        updateDuplicateNames();
        renderTable();
        renderList();

        // If we have a draftId, try to sync with server — server may have more hands
        if (draftId) {
            tryServerRecovery(draftId, true);
        }
        return true;
    } catch(e) { return false; }
}

// Try to recover/sync from server draft
async function tryServerRecovery(did, hasLocal) {
    try {
        const r = await fetch('mobile.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'load_draft', draft_id: did })
        });
        const d = await r.json();
        if (!d.success || !d.hands) return;

        // If server has more hands than local, use server data
        if (d.hands.length > hands.length) {
            const msg = T.lang === 'sv'
                ? `Servern har ${d.hands.length} händer, lokalt finns ${hands.length}. Återställa från servern?`
                : `Server has ${d.hands.length} hands, locally ${hands.length}. Restore from server?`;
            if (confirm(msg)) {
                hands = d.hands.map(h => ({
                    points: parseInt(h.points),
                    winner: parseInt(h.winner),
                    from: parseInt(h.from_player)
                }));
                penalties = (d.penalties || []).map(p => ({
                    idx: parseInt(p.player_idx),
                    amount: parseInt(p.amount),
                    distribute: !!parseInt(p.distribute)
                }));
                currentHand = hands.length + 1;
                recalcScores();
                renderTable();
                renderList();
                if (d.session && d.session.timer_json) restoreTimerFromServer(d.session.timer_json);
            }
        }
    } catch(e) {
        console.log('Server recovery check failed (offline?):', e.message);
    }
}

// Check for active server drafts on page load (handles phone crash / battery death)
async function checkServerDrafts() {
    try {
        const r = await fetch('mobile.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'check_active_drafts' })
        });
        const d = await r.json();
        if (!d.success || !d.drafts || d.drafts.length === 0) return;

        // Only show if we don't already have a game loaded
        if (hands.length > 0) return;

        const draft = d.drafts[0]; // most recent
        const dp = JSON.parse(draft.players_json);
        const names = dp.map(p => p.name).join(', ');
        const msg = T.lang === 'sv'
            ? `Det finns en pågående match på servern:\n${names}\n(${draft.game_date})\n\nVill du återställa den?`
            : `There is an active game on the server:\n${names}\n(${draft.game_date})\n\nDo you want to restore it?`;

        if (confirm(msg)) {
            // Load the draft
            const r2 = await fetch('mobile.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'load_draft', draft_id: draft.draft_id })
            });
            const d2 = await r2.json();
            if (!d2.success) return;

            draftId = draft.draft_id;
            players = dp.map(p => {
                const first = p.name.split(' ')[0];
                return { vms: p.vms, name: p.name, first, displayFirst: first, slotLabel: p.name };
            });
            updateDuplicateNames();

            hands = (d2.hands || []).map(h => ({
                points: parseInt(h.points),
                winner: parseInt(h.winner),
                from: parseInt(h.from_player)
            }));
            penalties = (d2.penalties || []).map(p => ({
                idx: parseInt(p.player_idx),
                amount: parseInt(p.amount),
                distribute: !!parseInt(p.distribute)
            }));
            currentHand = hands.length + 1;
            recalcScores();

            if (draft.game_date) document.getElementById('gameDate').value = draft.game_date;
            if (draft.game_name) document.getElementById('gameName').value = draft.game_name;

            showScreen('game');
            renderTable();
            renderList();
            if (d2.session && d2.session.timer_json) restoreTimerFromServer(d2.session.timer_json);
        } else {
            // User declined — offer to discard
            const discardMsg = T.lang === 'sv'
                ? 'Vill du radera den sparade matchen från servern?'
                : 'Do you want to discard the saved game from the server?';
            if (confirm(discardMsg)) {
                fetch('mobile.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'discard_draft', draft_id: draft.draft_id })
                });
            }
        }
    } catch(e) {
        console.log('Server draft check failed:', e.message);
    }
}

// Hook autosave into every state change
const _origRenderTable = renderTable;
renderTable = function() { _origRenderTable(); autoSave(); };

// --- Takeover from draft-monitor ---
async function handleTakeover() {
    try {
        const takeoverId = localStorage.getItem('mvms_takeover');
        if (!takeoverId) return false;
        localStorage.removeItem('mvms_takeover');

        // Tell server who is taking over
        await fetch('mobile.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'takeover_draft', draft_id: takeoverId })
        });

        const r = await fetch('mobile.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'load_draft', draft_id: takeoverId })
        });
        const d = await r.json();
        if (!d.success || !d.session) return false;

        const dp = JSON.parse(d.session.players_json);
        draftId = takeoverId;
        players = dp.map(p => {
            const first = p.name.split(' ')[0];
            return { vms: p.vms, name: p.name, first, displayFirst: first, slotLabel: p.name };
        });
        updateDuplicateNames();

        hands = (d.hands || []).map(h => ({
            points: parseInt(h.points),
            winner: parseInt(h.winner),
            from: parseInt(h.from_player)
        }));
        penalties = (d.penalties || []).map(p => ({
            idx: parseInt(p.player_idx),
            amount: parseInt(p.amount),
            distribute: !!parseInt(p.distribute)
        }));
        currentHand = hands.length + 1;
        recalcScores();

        if (d.session.game_date) document.getElementById('gameDate').value = d.session.game_date;
        if (d.session.game_name) document.getElementById('gameName').value = d.session.game_name;

        showScreen('game');
        renderTable();
        renderList();
        if (d.session.timer_json) restoreTimerFromServer(d.session.timer_json);

        // Show takeover welcome dialog
        const timerInfo = timerRunning
            ? (T.lang === 'sv' ? 'Klockan tickar — ' + formatTime(timerSeconds) + ' kvar.' : 'Timer is running — ' + formatTime(timerSeconds) + ' remaining.')
            : (T.lang === 'sv' ? 'Klockan är pausad på ' + formatTime(timerSeconds) + '.' : 'Timer is paused at ' + formatTime(timerSeconds) + '.');
        const handsInfo = T.lang === 'sv'
            ? hands.length + ' händer registrerade.'
            : hands.length + ' hands registered.';

        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;';
        const box = document.createElement('div');
        box.style.cssText = 'background:white;border-radius:16px;padding:32px 24px;max-width:360px;text-align:center;margin:16px;';
        box.innerHTML = '<div style="font-size:2em;margin-bottom:12px;">📱</div>'
            + '<h2 style="margin:0 0 12px;color:#005B99;font-size:1.15em;">'
            + (T.lang === 'sv' ? 'Du har tagit över registreringen' : 'You have taken over the registration') + '</h2>'
            + '<p style="font-size:0.95em;line-height:1.5;color:#333;">' + handsInfo + '<br>' + timerInfo + '</p>'
            + '<div style="display:flex;gap:10px;justify-content:center;margin-top:18px;flex-wrap:wrap;">'
            + (timerRunning ? '' : '<button id="takeoverStartTimer" style="padding:12px 22px;background:#1b5e20;color:white;border:none;border-radius:8px;font-size:0.95em;font-weight:600;cursor:pointer;">'
              + (T.lang === 'sv' ? '▶ Starta klockan' : '▶ Start timer') + '</button>')
            + '<button id="takeoverClose" style="padding:12px 22px;background:#005B99;color:white;border:none;border-radius:8px;font-size:0.95em;font-weight:600;cursor:pointer;">'
            + (T.lang === 'sv' ? 'OK' : 'OK') + '</button>'
            + '</div>';
        overlay.appendChild(box);
        document.body.appendChild(overlay);

        document.getElementById('takeoverClose').onclick = () => overlay.remove();
        const startBtn = document.getElementById('takeoverStartTimer');
        if (startBtn) {
            startBtn.onclick = () => {
                if (!timerRunning) timerStartStop();
                overlay.remove();
            };
        }

        return true;
    } catch(e) {
        console.log('Takeover failed:', e.message);
        return false;
    }
}

// ============================================================
// TAKEOVER DETECTION — poll server to check if someone else took over
// ============================================================
let takeoverLocked = false;

async function checkTakeoverStatus() {
    if (!draftId || takeoverLocked || !hands.length) return;
    try {
        const r = await fetch('mobile.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'check_takeover', draft_id: draftId })
        });
        const d = await r.json();
        if (d.taken_over) {
            takeoverLocked = true;
            // Show takeover overlay
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;';
            const box = document.createElement('div');
            box.style.cssText = 'background:white;border-radius:16px;padding:32px 24px;max-width:360px;text-align:center;margin:16px;';
            const vmsLabel = d.by_name || d.by_vms;
            box.innerHTML = T.lang === 'sv'
                ? '<div style="font-size:2em;margin-bottom:12px;">📱</div>'
                  + '<h2 style="margin:0 0 12px;color:#005B99;font-size:1.2em;">Registreringen har tagits över</h2>'
                  + '<p style="font-size:1.05em;line-height:1.5;color:#333;"><strong>' + vmsLabel + '</strong> har tagit över registreringen av denna match.</p>'
                  + '<p style="color:#888;font-size:0.9em;">Du behöver inte göra något mer. Dina registrerade händer finns kvar.</p>'
                  + '<button onclick="window.location.href=\'my-page.php\'" style="margin-top:16px;padding:12px 28px;background:#005B99;color:white;border:none;border-radius:8px;font-size:1em;font-weight:600;cursor:pointer;">OK</button>'
                : '<div style="font-size:2em;margin-bottom:12px;">📱</div>'
                  + '<h2 style="margin:0 0 12px;color:#005B99;font-size:1.2em;">Registration taken over</h2>'
                  + '<p style="font-size:1.05em;line-height:1.5;color:#333;"><strong>' + vmsLabel + '</strong> has taken over the registration of this game.</p>'
                  + '<p style="color:#888;font-size:0.9em;">You don\'t need to do anything. Your registered hands are saved.</p>'
                  + '<button onclick="window.location.href=\'my-page.php\'" style="margin-top:16px;padding:12px 28px;background:#005B99;color:white;border:none;border-radius:8px;font-size:1em;font-weight:600;cursor:pointer;">OK</button>';
            overlay.appendChild(box);
            document.body.appendChild(overlay);
            // Clear local state so we don't interfere
            window.localStorage.removeItem('mvms_autosave');
        }
    } catch(e) {
        // Offline — skip check
    }
}

// Poll every 10 seconds
setInterval(checkTakeoverStatus, 10000);

// Timer variables — must be declared before autoLoad restores them
let timerSeconds = 1 * 3600 + 55 * 60; // default 1:55:00
let timerDefaultSeconds = 1 * 3600 + 55 * 60;
let timerRunning = false;
let timerInterval = null;
let timerStartedAt = null;       // Date.now() when timer was started/resumed
let timerSecondsAtStart = 0;     // timerSeconds value when started/resumed
let timerAlarmFired = false;     // prevent multiple alarms
let customH = 1;
let customM = 55;
let customS = 0;

// Restore timer from its own localStorage key immediately
(function() {
    try {
        const raw = localStorage.getItem('mvms_timer');
        if (!raw) return;
        const t = JSON.parse(raw);
        if (!t || !t.savedAt) return;
        // Only restore if saved within last 24 hours
        if (Date.now() - t.savedAt > 86400000) { localStorage.removeItem('mvms_timer'); return; }
        if (t.timerRunning && t.timerStartedAt) {
            const elapsed = Math.floor((Date.now() - t.timerStartedAt) / 1000);
            timerSeconds = Math.max(0, t.timerSecondsAtStart - elapsed);
            timerSecondsAtStart = timerSeconds;
            timerStartedAt = Date.now();
            timerRunning = true;
            timerAlarmFired = timerSeconds <= 0;
        } else {
            timerSeconds = (t.timerSeconds !== undefined && t.timerSeconds !== null) ? t.timerSeconds : timerDefaultSeconds;
            timerRunning = false;
            timerStartedAt = null;
        }
    } catch(e) {}
})();

function saveTimerLocal() {
    try {
        let secs = timerSeconds;
        if (timerRunning && timerStartedAt) {
            const elapsed = Math.floor((Date.now() - timerStartedAt) / 1000);
            secs = Math.max(0, timerSecondsAtStart - elapsed);
        }
        localStorage.setItem('mvms_timer', JSON.stringify({
            timerSeconds: secs,
            timerRunning: timerRunning,
            timerStartedAt: timerStartedAt,
            timerSecondsAtStart: timerSecondsAtStart,
            savedAt: Date.now()
        }));
    } catch(e) {}
}

function clearTimerLocal() {
    try { localStorage.removeItem('mvms_timer'); } catch(e) {}
}

// Try to restore on load — first takeover, then local, then server
if (localStorage.getItem('mvms_takeover')) {
    handleTakeover();
} else if (!autoLoad()) {
    // No local save found — check server for orphaned drafts
    checkServerDrafts();
}

// ===== CLOCK & TIMER =====
// (timer variables declared earlier, before autoLoad)

function pad2(n) { return n.toString().padStart(2, '0'); }

function formatTime(totalSec) {
    const h = Math.floor(totalSec / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    return pad2(h) + ':' + pad2(m) + ':' + pad2(s);
}

// Clock and date - update every second
const DATE_DAYS_SV = ['söndag','måndag','tisdag','onsdag','torsdag','fredag','lördag'];
const DATE_DAYS_EN = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
const DATE_MONTHS_SV = ['januari','februari','mars','april','maj','juni','juli','augusti','september','oktober','november','december'];
const DATE_MONTHS_EN = ['January','February','March','April','May','June','July','August','September','October','November','December'];

function updateClockAndDate() {
    const now = new Date();
    const el = document.getElementById('clockDisplay');
    if (el) el.textContent = pad2(now.getHours()) + ':' + pad2(now.getMinutes()) + ':' + pad2(now.getSeconds());
    const dateEl = document.getElementById('dateDisplay');
    if (dateEl) {
        if (LANG === 'sv') {
            dateEl.textContent = DATE_DAYS_SV[now.getDay()] + ' ' + now.getDate() + ' ' + DATE_MONTHS_SV[now.getMonth()] + ' ' + now.getFullYear();
        } else {
            dateEl.textContent = DATE_DAYS_EN[now.getDay()] + ', ' + now.getDate() + ' ' + DATE_MONTHS_EN[now.getMonth()] + ' ' + now.getFullYear();
        }
    }
}
setInterval(updateClockAndDate, 1000);
updateClockAndDate();

function updateTimerDisplay() {
    const el = document.getElementById('timerDisplay');
    if (!el) return;
    el.textContent = formatTime(timerSeconds);
    
    const isLow = timerRunning && timerSeconds > 0 && timerSeconds <= 300; // 5 min
    const isZero = timerSeconds <= 0;
    
    if (isLow || isZero) {
        el.classList.add('timer-pulse');
    } else {
        el.classList.remove('timer-pulse');
    }
    
    // Update overlay on table view
    updateTimerOverlay();
}

function updateTimerOverlay() {
    const overlay = document.getElementById('timerOverlay');
    if (!overlay) return;
    if (timerRunning) {
        overlay.textContent = formatTime(Math.max(0, timerSeconds));
        overlay.style.display = 'block';
        overlay.classList.toggle('timer-low', timerSeconds <= 300);
    } else {
        overlay.style.display = 'none';
    }
}

// ===== TIMER ALARM SOUND =====
const PLING_WAV_B64 = 'data:audio/wav;base64,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';
let plingAudio  = null;
let plingAudio2 = null;
let plingAudio3 = null;

function initAudioCtx() {
    // Create three separate Audio objects for the three alarm plings
    // (can't reuse same object for overlapping/rapid playback on iOS)
    try {
        if (!plingAudio)  plingAudio  = new Audio(PLING_WAV_B64);
        if (!plingAudio2) plingAudio2 = new Audio(PLING_WAV_B64);
        if (!plingAudio3) plingAudio3 = new Audio(PLING_WAV_B64);

        // Play pling audibly — confirms timer started + unlocks all three
        plingAudio.volume = 1.0;
        plingAudio.currentTime = 0;
        plingAudio.play().catch(() => {});

        // Unlock the other two silently
        [plingAudio2, plingAudio3].forEach(a => {
            a.volume = 0;
            const p = a.play();
            if (p) p.then(() => {
                a.pause();
                a.currentTime = 0;
                a.volume = 1.0;
            }).catch(() => { a.volume = 1.0; });
        });
    } catch(e) {}
}

function playTimerAlarm() {
    const enabled = document.getElementById('timerSoundEnabled');
    if (enabled && !enabled.checked) return;

    // Vibration — Android (three short pulses)
    try {
        if (navigator.vibrate) navigator.vibrate([200, 150, 200, 150, 200]);
    } catch(e) {}

    // Three plings — iOS
    try {
        const audios = [plingAudio, plingAudio2, plingAudio3];
        audios.forEach((a, i) => {
            if (a) setTimeout(() => {
                a.volume = 1.0;
                a.currentTime = 0;
                a.play().catch(() => {});
            }, i * 350);
        });
    } catch(e) {}

    // Alert dialog
    setTimeout(() => {
        alert(LANG === 'sv' ? '\u23F0 Tiden \u00E4r ute!' : '\u23F0 Time is up!');
    }, 1200);
}

function timerTick() {
    if (!timerRunning || !timerStartedAt) return;
    const elapsed = Math.floor((Date.now() - timerStartedAt) / 1000);
    timerSeconds = Math.max(0, timerSecondsAtStart - elapsed);
    if (timerSeconds <= 0 && !timerAlarmFired) {
        timerAlarmFired = true;
        playTimerAlarm();
    }
    updateTimerDisplay();
}

function syncTimerToServer() {
    if (!draftId) return;
    // Save absolute wall-clock state so another device can reconstruct
    const state = {
        timerSeconds: timerSeconds,
        timerRunning: timerRunning,
        timerStartedAt: timerStartedAt, // absolute ms timestamp
        timerSecondsAtStart: timerSecondsAtStart,
        savedAt: Date.now()
    };
    draftPost({
        action: 'save_timer',
        draft_id: draftId,
        timer: state
    });
}

function restoreTimerFromServer(timerData) {
    if (!timerData) return;
    const t = (typeof timerData === 'string') ? JSON.parse(timerData) : timerData;
    if (!t || !t.savedAt) return;

    if (t.timerRunning && t.timerStartedAt) {
        // Timer was running — calculate how much time has passed since it was saved
        const elapsed = Math.floor((Date.now() - t.timerStartedAt) / 1000);
        timerSeconds = Math.max(0, t.timerSecondsAtStart - elapsed);
        timerSecondsAtStart = timerSeconds;
        timerStartedAt = Date.now();
        timerRunning = true;
        timerAlarmFired = timerSeconds <= 0;
        clearInterval(timerInterval);
        timerInterval = setInterval(timerTick, 250);
        const btn = document.getElementById('timerStartBtn');
        if (btn) {
            btn.innerHTML = '⏸ ' + (LANG === 'sv' ? 'Pausa' : 'Pause');
            btn.style.background = '#e65100';
        }
    } else {
        // Timer was paused
        timerSeconds = t.timerSeconds || timerDefaultSeconds;
        timerRunning = false;
        timerStartedAt = null;
        clearInterval(timerInterval);
        const btn = document.getElementById('timerStartBtn');
        if (btn) {
            btn.innerHTML = '▶ ' + (LANG === 'sv' ? 'Starta' : 'Start');
            btn.style.background = '#1b5e20';
        }
    }
    updateTimerDisplay();
}

function timerStartStop() {
    const btn = document.getElementById('timerStartBtn');
    if (timerRunning) {
        // Pause — save remaining time
        clearInterval(timerInterval);
        const elapsed = Math.floor((Date.now() - timerStartedAt) / 1000);
        timerSeconds = Math.max(0, timerSecondsAtStart - elapsed);
        timerRunning = false;
        timerStartedAt = null;
        btn.innerHTML = '▶ ' + (LANG === 'sv' ? 'Starta' : 'Start');
        btn.style.background = '#1b5e20';
    } else {
        if (timerSeconds <= 0) return; // don't start at zero
        try { initAudioCtx(); } catch(e) {}
        timerStartedAt = Date.now();
        timerSecondsAtStart = timerSeconds;
        timerAlarmFired = false;
        timerInterval = setInterval(timerTick, 250); // 4x/sec for snappy catch-up
        timerRunning = true;
        btn.innerHTML = '⏸ ' + (LANG === 'sv' ? 'Pausa' : 'Pause');
        btn.style.background = '#e65100';
    }
    updateTimerDisplay();
    syncTimerToServer(); saveTimerLocal(); autoSave();
}

function timerReset() {
    try {
        clearInterval(timerInterval);
        timerRunning = false;
        timerStartedAt = null;
        timerAlarmFired = false;
        timerSeconds = timerDefaultSeconds;
        const btn = document.getElementById('timerStartBtn');
        btn.innerHTML = '▶ ' + (LANG === 'sv' ? 'Starta' : 'Start');
        btn.style.background = '#1b5e20';
        updateTimerDisplay();
        syncTimerToServer(); saveTimerLocal(); autoSave();
    } catch(e) {}
}

function timerCustom() {
    const panel = document.getElementById('timerCustomPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function timerAdjust(unit, delta) {
    if (unit === 'h') {
        customH = ((customH + delta) % 24 + 24) % 24;
        document.getElementById('customH').textContent = pad2(customH);
    } else if (unit === 'm') {
        customM = ((customM + delta) % 60 + 60) % 60;
        document.getElementById('customM').textContent = pad2(customM);
    } else {
        customS = ((customS + delta) % 60 + 60) % 60;
        document.getElementById('customS').textContent = pad2(customS);
    }
}

function timerSetCustom() {
    timerSeconds = customH * 3600 + customM * 60 + customS;
    // Do NOT change timerDefaultSeconds — reset always goes back to 1:55:00
    clearInterval(timerInterval);
    timerRunning = false;
    timerStartedAt = null;
    timerAlarmFired = false;
    const btn = document.getElementById('timerStartBtn');
    btn.innerHTML = '▶ ' + (LANG === 'sv' ? 'Starta' : 'Start');
    btn.style.background = '#1b5e20';
    document.getElementById('timerCustomPanel').style.display = 'none';
    updateTimerDisplay();
    syncTimerToServer(); saveTimerLocal(); autoSave();
}

// Catch up timer immediately when screen turns on or tab regains focus
document.addEventListener('visibilitychange', function() {
    if (!document.hidden && timerRunning) {
        timerTick(); // immediate recalc from wall clock
    }
});

// Update overlay when 32-rule toggles
const _origToggle32 = toggle32;
toggle32 = function() {
    _origToggle32();
    updateTimerOverlay();
};

updateTimerDisplay();
// If timer was restored as running from localStorage, start the interval and update button
if (timerRunning && !timerInterval) {
    timerInterval = setInterval(timerTick, 250);
    const _tbtn = document.getElementById('timerStartBtn');
    if (_tbtn) {
        _tbtn.innerHTML = '⏸ ' + (LANG === 'sv' ? 'Pausa' : 'Pause');
        _tbtn.style.background = '#e65100';
    }
}
</script>

<!-- Session heartbeat to keep login alive -->
<script>
// Heartbeat every 2 minutes to keep session alive
setInterval(() => {
    fetch('api/session_heartbeat.php')
    .then(r => {
        if (r.status === 401) {
            // Session died — but we have data in localStorage, so DON'T reload
            // Just show a subtle warning and try to re-auth silently
            console.warn('Session expired — data is safe in localStorage');
            const banner = document.getElementById('offlineBanner');
            if (banner) {
                banner.textContent = LANG === 'sv' 
                    ? '⚠ Sessionen har gått ut — din data är sparad lokalt. Logga in igen för att synka.' 
                    : '⚠ Session expired — your data is saved locally. Log in again to sync.';
                banner.style.display = 'block';
                banner.style.background = '#fff3e0';
                banner.style.color = '#e65100';
            }
            return null;
        }
        return r.json();
    })
    .then(data => {
        if (data && data.success) {
            // Session is alive — hide any warning
            const banner = document.getElementById('offlineBanner');
            if (banner && banner.style.background === 'rgb(255, 243, 224)') {
                banner.style.display = 'none';
            }
        }
    })
    .catch(() => {}); // Network error, try again next interval
}, 120000); // 2 minutes
</script>

<!-- Navigation guard — prevent accidental back during game -->
<script>
(function() {
    function gameActive() {
        return typeof hands !== 'undefined' && hands.length > 0 
            && typeof currentHand !== 'undefined' && currentHand <= 16
            && !gameDiscarded;
    }

    // 1. Always save state before page unloads (safety net)
    window.addEventListener('pagehide', function() {
        if (gameActive()) {
            try { saveTimerLocal(); autoSave(); } catch(e) {}
        }
    });

    // 2. Warn before leaving page
    window.addEventListener('beforeunload', function(e) {
        if (gameActive()) {
            try { saveTimerLocal(); autoSave(); } catch(e) {}
            e.preventDefault(); e.returnValue = ''; return '';
        }
    });

    // 3. Catch back button — double confirm
    history.pushState(null, '', location.href);
    window.addEventListener('popstate', function(e) {
        if (gameActive()) {
            history.pushState(null, '', location.href);
            var sv = (typeof T !== 'undefined' && T.lang === 'sv');
            var msg1 = sv ? 'Är du säker på att du vill lämna matchen?' : 'Are you sure you want to leave the game?';
            if (!confirm(msg1)) return;
            var msg2 = sv ? 'Är du HELT säker på att du vill lämna matchen? Den kan gå förlorad!' : 'Are you COMPLETELY sure you want to leave the game? It may be lost!';
            if (!confirm(msg2)) return;
            try { saveTimerLocal(); autoSave(); } catch(ex) {}
            gameDiscarded = true;
            history.back();
        }
    });

    // 4. Disable pull-to-refresh on Android/iOS (overscroll)
    var lastY = 0;
    document.addEventListener('touchstart', function(e) { lastY = e.touches[0].clientY; }, { passive: true });
    document.addEventListener('touchmove', function(e) {
        var y = e.touches[0].clientY;
        if (document.scrollingElement.scrollTop === 0 && y > lastY && typeof hands !== 'undefined' && hands.length > 0) {
            e.preventDefault();
        }
    }, { passive: false });
})();
</script>

</body>
</html>
