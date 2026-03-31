<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

if (!hasRole('player')) { showError(t('games_error_access')); }

$conn = getDbConnection();
$success = '';
$error = '';

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_game'])) {
    if (!hasRole('superuser')) {
        $error = t('games_error_delete_role');
    } else {
        $game_id = (int)$_POST['game_id'];
        try {
            $conn->prepare("UPDATE stat_games SET deleted_at = NOW(), deleted_by_id = ? WHERE id = ?")
                 ->execute([$_SESSION['user_id'] ?? null, $game_id]);
            $success = t('games_moved_to_trash');
        } catch (PDOException $e) {
            $error = t('games_error_delete_failed') . ': ' . $e->getMessage();
        }
    }
}

$filter_year   = isset($_GET['year'])   ? (int)$_GET['year']         : getCurrentYear();
$filter_player = isset($_GET['player']) ? cleanInput($_GET['player']) : '';
$filter_period = isset($_GET['period']) ? cleanInput($_GET['period']) : 'all';
$current_user_id   = $_SESSION['user_id'] ?? '';
$sv            = ($sv);

// Sortering
$sort  = isset($_GET['sort'])  ? cleanInput($_GET['sort'])  : 'number';
$order = isset($_GET['order']) ? cleanInput($_GET['order']) : 'desc';
$next_order = ($order === 'asc') ? 'desc' : 'asc';

$order_by = match($sort) {
    'number'  => "g.game_number",
    'name'    => "g.game_name",
    'date'    => "g.game_date",
    'biggest' => "g.biggest_hand_points",
    'creator' => "creator.last_name",
    'approver'=> "approver.last_name",
    default   => "g.game_number"
};
$order_dir = ($order === 'asc') ? 'ASC' : 'DESC';

$stmt = $conn->query("SELECT DISTINCT game_year FROM stat_games WHERE deleted_at IS NULL ORDER BY game_year DESC");
$available_years = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $conn->query("SELECT id, first_name, last_name FROM stat_players ORDER BY last_name, first_name");
$all_players = $stmt->fetchAll();

$where  = ["g.game_year = ?", "g.deleted_at IS NULL"];
$params = [$filter_year];

if (!empty($filter_player)) {
    $where[]  = "(g.player_a_id = ? OR g.player_b_id = ? OR g.player_c_id = ? OR g.player_d_id = ?)";
    $params   = array_merge($params, [$filter_player, $filter_player, $filter_player, $filter_player]);
}

$period_where = '';
if ($filter_period !== 'all') {
    $parts = explode('_', $filter_period);
    $ptype = $parts[0];
    $pnum  = (int)$parts[1];
    switch ($ptype) {
        case 'month':
            $period_where = ' AND MONTH(g.game_date) = ?';
            $params[]     = $pnum; break;
        case 'quarter':
            $period_where = ' AND MONTH(g.game_date) BETWEEN ? AND ?';
            $params[]     = ($pnum-1)*3+1; $params[] = $pnum*3; break;
        case 'half':
            $period_where = ' AND MONTH(g.game_date) BETWEEN ? AND ?';
            $params[]     = ($pnum===1)?1:7; $params[] = ($pnum===1)?6:12; break;
    }
}

$where_clause = implode(' AND ', $where);

$sql = "SELECT g.*,
        pa.first_name as pa_first, pa.last_name as pa_last, pa.visible_in_stats as pa_visible,
        pb.first_name as pb_first, pb.last_name as pb_last, pb.visible_in_stats as pb_visible,
        pc.first_name as pc_first, pc.last_name as pc_last, pc.visible_in_stats as pc_visible,
        pd.first_name as pd_first, pd.last_name as pd_last, pd.visible_in_stats as pd_visible,
        bp.first_name as bp_first, bp.last_name as bp_last, bp.visible_in_stats as bp_visible,
        creator.first_name  as creator_first,  creator.last_name  as creator_last,
        approver.first_name as approver_first, approver.last_name as approver_last
    FROM stat_games g
    LEFT JOIN stat_players pa       ON g.player_a_id            = pa.id
    LEFT JOIN stat_players pb       ON g.player_b_id            = pb.id
    LEFT JOIN stat_players pc       ON g.player_c_id            = pc.id
    LEFT JOIN stat_players pd       ON g.player_d_id            = pd.id
    LEFT JOIN stat_players bp       ON g.biggest_hand_player_id = bp.id
    LEFT JOIN stat_players creator  ON g.created_by_id          = creator.id
    LEFT JOIN stat_players approver ON g.approved_by_id         = approver.id
    WHERE $where_clause $period_where
    ORDER BY $order_by $order_dir";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$stat_games = $stmt->fetchAll();

// Helper: get biggest hand player name(s)
function getBiggestHandName($game) {
    if ($game['biggest_hand_points'] <= 0) return '';
    $bh_player_list = explode(',', $game['biggest_hand_player_id'] ?? '');
    if (count($bh_player_list) === 1 && !empty($game['bp_first'])) {
        return $game['bp_visible'] ? htmlspecialchars($game['bp_first'].' '.$game['bp_last']) : t('games_hidden_player');
    } elseif (count($bh_player_list) > 1) {
        $slots = ['a'=>['vms'=>'player_a_id','first'=>'pa_first','last'=>'pa_last'],'b'=>['vms'=>'player_b_id','first'=>'pb_first','last'=>'pb_last'],'c'=>['vms'=>'player_c_id','first'=>'pc_first','last'=>'pc_last'],'d'=>['vms'=>'player_d_id','first'=>'pd_first','last'=>'pd_last']];
        $names = [];
        foreach ($bh_player_list as $bv) {
            $bv = trim($bv);
            foreach ($slots as $s) {
                if ($game[$s['vms']] === $bv && !empty($game[$s['first']])) {
                    $names[] = htmlspecialchars($game[$s['first']].' '.$game[$s['last']]);
                    break;
                }
            }
        }
        return $names ? implode(' & ', $names) : count($bh_player_list).' '.t('nav_players');
    }
    return t('games_hidden_player');
}

includeHeader();
?>

<h2><?php echo t("games_title") . " " . $filter_year; ?></h2>

<div style="margin:20px 0;">
    <a href="ranking.php" class="btn" style="background:#2196F3;">📊 <?php echo t("nav_stats_pub"); ?></a>
    <?php if (hasRole('superuser')): 
        $trash_count = $conn->query("SELECT COUNT(*) FROM stat_games WHERE deleted_at IS NOT NULL")->fetchColumn();
    ?>
    <a href="games-trash.php" class="btn" style="background:#f9a825;color:#333;">
        🗑️ <?php echo t('trash_btn'); ?><?php if ($trash_count): ?> (<?php echo $trash_count; ?>)<?php endif; ?>
    </a>
    <?php endif; ?>
</div>

<?php if ($success): ?><div class="message success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error):   ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<!-- FILTER -->
<div class="filter-box" style="background:#f9f9f9;padding:20px;border-radius:5px;margin:20px 0;">
    <form method="GET" action="games.php">
        <div style="display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap;">

            <div class="form-group" style="margin:0;">
                <label><?php echo t('games_filter_year'); ?></label>
                <select name="year" onchange="this.form.submit()">
                    <?php foreach ($available_years as $y): ?>
                    <option value="<?php echo $y; ?>" <?php echo ($y==$filter_year)?'selected':''; ?>><?php echo $y; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin:0;">
                <label><?php echo t('mystat_filter_period'); ?></label>
                <select name="period" onchange="this.form.submit()">
                    <option value="all"       <?php echo $filter_period==='all'       ?'selected':'';?>><?php echo t('stats_full_year'); ?></option>
                    <option value="month_1"   <?php echo $filter_period==='month_1'   ?'selected':'';?>><?php echo t('month_jan'); ?></option>
                    <option value="month_2"   <?php echo $filter_period==='month_2'   ?'selected':'';?>><?php echo t('month_feb'); ?></option>
                    <option value="month_3"   <?php echo $filter_period==='month_3'   ?'selected':'';?>><?php echo t('month_mar'); ?></option>
                    <option value="quarter_1" <?php echo $filter_period==='quarter_1' ?'selected':'';?>><?php echo t('stats_quarter').' 1'; ?></option>
                    <option value="month_4"   <?php echo $filter_period==='month_4'   ?'selected':'';?>><?php echo t('month_apr'); ?></option>
                    <option value="month_5"   <?php echo $filter_period==='month_5'   ?'selected':'';?>><?php echo t('month_may'); ?></option>
                    <option value="month_6"   <?php echo $filter_period==='month_6'   ?'selected':'';?>><?php echo t('month_jun'); ?></option>
                    <option value="quarter_2" <?php echo $filter_period==='quarter_2' ?'selected':'';?>><?php echo t('stats_quarter').' 2'; ?></option>
                    <option value="half_1"    <?php echo $filter_period==='half_1'    ?'selected':'';?>><?php echo t('stats_half1'); ?></option>
                    <option value="month_7"   <?php echo $filter_period==='month_7'   ?'selected':'';?>><?php echo t('month_jul'); ?></option>
                    <option value="month_8"   <?php echo $filter_period==='month_8'   ?'selected':'';?>><?php echo t('month_aug'); ?></option>
                    <option value="month_9"   <?php echo $filter_period==='month_9'   ?'selected':'';?>><?php echo t('month_sep'); ?></option>
                    <option value="quarter_3" <?php echo $filter_period==='quarter_3' ?'selected':'';?>><?php echo t('stats_quarter').' 3'; ?></option>
                    <option value="month_10"  <?php echo $filter_period==='month_10'  ?'selected':'';?>><?php echo t('month_oct'); ?></option>
                    <option value="month_11"  <?php echo $filter_period==='month_11'  ?'selected':'';?>><?php echo t('month_nov'); ?></option>
                    <option value="month_12"  <?php echo $filter_period==='month_12'  ?'selected':'';?>><?php echo t('month_dec'); ?></option>
                    <option value="quarter_4" <?php echo $filter_period==='quarter_4' ?'selected':'';?>><?php echo t('stats_quarter').' 4'; ?></option>
                    <option value="half_2"    <?php echo $filter_period==='half_2'    ?'selected':'';?>><?php echo t('stats_half2'); ?></option>
                </select>
            </div>

            <div class="form-group" style="margin:0;">
                <label><?php echo t('games_filter_player'); ?></label>
                <select name="player" onchange="this.form.submit()">
                    <option value=""><?php echo t('games_all_players'); ?></option>
                    <?php foreach ($all_players as $p): ?>
                    <option value="<?php echo htmlspecialchars($p['id']); ?>"
                            <?php echo ($filter_player===$p['id'])?'selected':''; ?>>
                        <?php echo htmlspecialchars($p['id'].' - '.$p['first_name'].' '.$p['last_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($filter_player)): ?>
            <div>
                <a href="games.php?year=<?php echo $filter_year; ?>" class="btn btn-secondary">
                    <?php echo t('btn_clear_filter'); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- MATCHLISTA -->
<?php if (empty($stat_games)): ?>
    <div class="message info">
        <p><?php echo t('games_none_found').' '.$filter_year.(!empty($filter_player)?' '.t('games_and_player'):''); ?></p>
    </div>
<?php else: ?>
    <p style="margin:20px 0;">
        <strong><?php echo count($stat_games); ?></strong> <?php echo t('awards_games');
        if (!empty($filter_player)) {
            $fp = reset(array_filter($all_players, fn($p) => $p['id']===$filter_player));
            echo ' '.t('games_with').' '.htmlspecialchars($fp['first_name'].' '.$fp['last_name']);
        } ?>
    </p>

    <div class="mobile-hint">
        📱 <strong><?php echo t('tip_label'); ?>:</strong> <?php echo t('games_rotate_tip'); ?>
    </div>


<?php
function sortLink($col, $label, $sort, $next_order, $order, $filter_year, $filter_player, $filter_period) {
    $arrow = ($sort === $col) ? ($order === 'asc' ? ' ▲' : ' ▼') : '';
    $url = "games.php?sort={$col}&order={$next_order}&year={$filter_year}&player={$filter_player}&period={$filter_period}";
    return '<a href="' . $url . '" style="color:inherit;text-decoration:none;">' . $label . $arrow . '</a>';
}
?>
    <style>
    /* ---- MOBILE CARD LAYOUT ---- */
    .game-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        margin-bottom: 12px;
        overflow: hidden;
    }
    .game-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px 8px;
        border-bottom: 1px solid #f0f0f0;
    }
    .game-card-title { font-size: 1.05em; font-weight: 700; color: #005B99; }
    .game-card-date  { font-size: 0.85em; color: #777; }
    .game-card-players {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px;
        padding: 10px 14px;
    }
    .game-card-player {
        background: #f9f9f9;
        border-radius: 6px;
        padding: 6px 8px;
        font-size: 0.85em;
    }
    .game-card-player.confirmed { border-left: 3px solid #4caf50; }
    .game-card-player-name { font-weight: 600; }
    .game-card-player-scores { color: #555; font-size: 0.9em; }
    .game-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 14px;
        background: #f8f8f8;
        border-top: 1px solid #f0f0f0;
        flex-wrap: wrap;
        gap: 6px;
    }
    .game-card-meta { font-size: 0.8em; color: #777; }
    .game-card-actions { display: flex; gap: 6px; flex-wrap: wrap; }

    /* Hide table on mobile, show cards */
    @media (max-width: 700px) {
        .games-table-wrap { display: none; }
        .games-cards-wrap { display: block; }
        .mobile-hint { display: none !important; }
    }
    /* Hide cards on desktop, show table */
    @media (min-width: 701px) {
        .games-cards-wrap { display: none; }
        .games-table-wrap { display: block; overflow-x: auto; }
    }
    /* Override global mobile btn:width:100% for card action buttons */
    .game-card-actions .btn,
    .game-card-actions button.btn,
    .game-card-actions form .btn {
        width: auto !important;
        min-width: 90px !important;
        height: 36px !important;
        line-height: 1 !important;
        margin: 0 !important;
        min-height: unset !important;
        padding: 0 10px !important;
        font-size: 0.78em !important;
        white-space: nowrap;
        text-align: center;
        box-sizing: border-box;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
    }
    .game-card-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        align-items: center;
    }
    /* Uniform action buttons — both mobile cards and desktop table */
    .btn-action {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-width: 90px !important;
        height: 34px !important;
        min-height: unset !important;
        padding: 0 12px !important;
        font-size: 0.85em !important;
        white-space: nowrap !important;
        margin: 2px 2px !important;
        box-sizing: border-box !important;
        line-height: 1 !important;
    }
    </style>

    <?php
    $require_conf = defined('REQUIRE_PLAYER_CONFIRMATION') && REQUIRE_PLAYER_CONFIRMATION;
    ?>

    <!-- ========== MOBILE CARDS ========== -->
    <div class="games-cards-wrap">
    <?php foreach ($stat_games as $game):
        $pigs = [
            ['vms'=>$game['player_a_id'],'name'=>$game['pa_first'].' '.$game['pa_last'],'mini'=>$game['player_a_minipoints'],'table'=>$game['player_a_tablepoints'],'confirmed'=>!empty($game['player_a_confirmed_at']),'slot'=>'a'],
            ['vms'=>$game['player_b_id'],'name'=>$game['pb_first'].' '.$game['pb_last'],'mini'=>$game['player_b_minipoints'],'table'=>$game['player_b_tablepoints'],'confirmed'=>!empty($game['player_b_confirmed_at']),'slot'=>'b'],
            ['vms'=>$game['player_c_id'],'name'=>$game['pc_first'].' '.$game['pc_last'],'mini'=>$game['player_c_minipoints'],'table'=>$game['player_c_tablepoints'],'confirmed'=>!empty($game['player_c_confirmed_at']),'slot'=>'c'],
            ['vms'=>$game['player_d_id'],'name'=>$game['pd_first'].' '.$game['pd_last'],'mini'=>$game['player_d_minipoints'],'table'=>$game['player_d_tablepoints'],'confirmed'=>!empty($game['player_d_confirmed_at']),'slot'=>'d'],
        ];
        usort($pigs, fn($a,$b) => $b['table'] <=> $a['table']);
        $medals = ['🥇','🥈','🥉',''];
        $g_ok = 0; foreach (['a','b','c','d'] as $gs) { if (!empty($game['player_'.$gs.'_confirmed_at'])) $g_ok++; }
        $bh_name = getBiggestHandName($game);
    ?>
    <div class="game-card">
        <!-- Header: match# + date + status -->
        <div class="game-card-header">
            <div>
                <div class="game-card-title">
                    <?php echo t('games_game_label').' '.$game['game_number']; ?>
                    <?php if (!empty($game['game_name'])): ?>
                        <span style="font-size:0.8em;color:#555;font-weight:400;"> — <?php echo htmlspecialchars($game['game_name']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($require_conf): ?>
                <div style="font-size:0.78em;margin-top:3px;">
                    <?php if ($game['approved']): ?>
                        <span style="color:#4caf50;">✅ <?php echo t('games_approved'); ?></span>
                    <?php else: ?>
                        <span style="color:#ff9800;">⏳ <?php echo t('games_pending'); ?> (<?php echo $g_ok; ?>/4 OK)</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div style="text-align:right;">
                <div class="game-card-date"><?php echo formatSwedishDate($game['game_date']); ?></div>
                <?php if ($game['biggest_hand_points'] > 0): ?>
                <div style="font-size:0.8em;color:#d32f2f;font-weight:700;margin-top:2px;">🀄 <?php echo $game['biggest_hand_points']; ?> <?php echo t('stats_pub_points'); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Players 2x2 grid -->
        <div class="game-card-players">
        <?php foreach ($pigs as $pidx => $p):
            $p_is_me = ($p['vms'] === ($current_user_id ?? ''));
            $p_can_confirm = $require_conf && $p_is_me && !$p['confirmed'] && !$game['approved'];
        ?>
            <div class="game-card-player <?php echo $p['confirmed'] ? 'confirmed' : ''; ?>">
                <div class="game-card-player-name"><?php echo $medals[$pidx].' '.htmlspecialchars($p['name']); ?></div>
                <div class="game-card-player-scores">BP: <strong><?php echo $p['table']; ?></strong> · MP: <?php echo $p['mini']; ?></div>
                <?php if ($require_conf): ?>
                    <?php if ($p['confirmed']): ?>
                        <span style="background:#4caf50;color:white;padding:1px 7px;border-radius:8px;font-size:0.75em;font-weight:bold;">OK</span>
                    <?php elseif ($p_can_confirm): ?>
                        <button onclick="confirmFromList(<?php echo $game['id']; ?>,'<?php echo $p['slot']; ?>',this)"
                                style="background:#ff9800;color:white;border:none;padding:2px 10px;border-radius:8px;font-size:0.75em;font-weight:bold;cursor:pointer;margin-top:3px;">Tryck OK</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>

        <!-- Footer: meta + actions -->
        <div class="game-card-footer">
            <div class="game-card-meta">
                <?php if (!empty($game['approver_first']) && $game['approved']): ?>
                    <?php echo $sv ? 'Fastställd' : 'Approved'; ?>: <?php echo htmlspecialchars($game['approver_first'].' '.$game['approver_last']); ?>
                <?php elseif (!empty($game['creator_first'])): ?>
                    <?php echo $sv ? 'Registrerad' : 'Registered'; ?>: <?php echo htmlspecialchars($game['creator_first'].' '.$game['creator_last']); ?>
                <?php endif; ?>
            </div>
            <div class="game-card-actions">
                <a href="view-game.php?id=<?php echo $game['id']; ?>" class="btn btn-view-game btn-action"
                   style="background:#2e7d32;color:#FECC02;">
                    <?php echo t('btn_view'); ?>
                </a>
                <?php if ($require_conf && !$game['approved'] && canApproveGames()): ?>
                <button onclick="approveFromList(<?php echo $game['id']; ?>,this)" class="btn btn-action" id="approve-<?php echo $game['id']; ?>"
                        style="background:<?php echo ($g_ok>=4)?'#4caf50':'#2196F3'; ?>;color:white;">
                    <?php echo t('games_approve'); ?>
                </button>
                <?php endif; ?>
                <?php if (hasRole('player')): ?>
                <a href="edit-game.php?id=<?php echo $game['id']; ?>" class="btn btn-action"
                   style="background:#FF9800;">
                    <?php echo t('btn_edit'); ?>
                </a>
                <?php endif; ?>
                <?php if (hasRole('superuser')): ?>
                <form method="POST" style="display:inline-block;margin:0;"
                      onsubmit="return confirm('<?php echo addslashes(t('games_confirm_delete')); ?>');">
                    <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                    <button type="submit" name="delete_game" class="btn btn-danger btn-delete-game btn-action">
                        <?php echo t('btn_delete'); ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div><!-- end cards -->

    <!-- ========== DESKTOP TABLE ========== -->
    <div class="games-table-wrap">
    <table class="stat_games-table">
        <thead>
            <tr>
                <th style="width:100px;"><?php echo sortLink('name', t('games_th_game'), $sort, $next_order, $order, $filter_year, $filter_player, $filter_period); ?></th>
                <th style="width:120px;"><?php echo sortLink('date', t('games_th_date'), $sort, $next_order, $order, $filter_year, $filter_player, $filter_period); ?></th>
                <th><?php echo t('games_th_players'); ?></th>
                <th style="width:140px;"><?php echo sortLink('biggest', t('stats_biggest_hand'), $sort, $next_order, $order, $filter_year, $filter_player, $filter_period); ?></th>
                <th style="width:160px;"><?php echo sortLink('creator', t('games_th_registered_by'), $sort, $next_order, $order, $filter_year, $filter_player, $filter_period); ?></th>
                <th style="width:160px;"><?php echo sortLink('approver', t('games_th_approved_by'), $sort, $next_order, $order, $filter_year, $filter_player, $filter_period); ?></th>
                <th style="width:150px;"><?php echo t('games_th_actions'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $require_conf = defined('REQUIRE_PLAYER_CONFIRMATION') && REQUIRE_PLAYER_CONFIRMATION;
        foreach ($stat_games as $game):
            $pigs = [
                ['vms'=>$game['player_a_id'],'name'=>$game['pa_first'].' '.$game['pa_last'],'mini'=>$game['player_a_minipoints'],'table'=>$game['player_a_tablepoints'],'confirmed'=>!empty($game['player_a_confirmed_at']),'slot'=>'a'],
                ['vms'=>$game['player_b_id'],'name'=>$game['pb_first'].' '.$game['pb_last'],'mini'=>$game['player_b_minipoints'],'table'=>$game['player_b_tablepoints'],'confirmed'=>!empty($game['player_b_confirmed_at']),'slot'=>'b'],
                ['vms'=>$game['player_c_id'],'name'=>$game['pc_first'].' '.$game['pc_last'],'mini'=>$game['player_c_minipoints'],'table'=>$game['player_c_tablepoints'],'confirmed'=>!empty($game['player_c_confirmed_at']),'slot'=>'c'],
                ['vms'=>$game['player_d_id'],'name'=>$game['pd_first'].' '.$game['pd_last'],'mini'=>$game['player_d_minipoints'],'table'=>$game['player_d_tablepoints'],'confirmed'=>!empty($game['player_d_confirmed_at']),'slot'=>'d'],
            ];
            usort($pigs, fn($a,$b) => $b['table'] <=> $a['table']);
            $medals = ['🥇','🥈','🥉',''];
            $total_bp = array_sum(array_column($pigs,'table'));
        ?>
        <tr>
            <td>
                <strong style="font-size:1.2em;"><?php echo t('games_game_label').' '.$game['game_number']; ?></strong>
                <?php if (!empty($game['game_name'])): ?>
                <div style="font-size:0.85em;margin-top:3px;"><?php echo sortLink('name', htmlspecialchars($game['game_name']), $sort, $next_order, $order, $filter_year, $filter_player, $filter_period); ?></div>
                <?php endif; ?>
                <?php if ($require_conf): ?>
                <div style="font-size:0.8em;margin-top:4px;">
                    <?php if ($game['approved']): ?>
                        <span style="color:#4caf50;">✅ <?php echo t('games_approved'); ?></span>
                    <?php else: 
                        $g_ok = 0;
                        foreach (['a','b','c','d'] as $gs) { if (!empty($game['player_'.$gs.'_confirmed_at'])) $g_ok++; }
                    ?>
                        <span style="color:#ff9800;">⏳ <?php echo t('games_pending'); ?> (<?php echo $g_ok; ?>/4 OK)</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </td>
            <td><?php echo formatSwedishDate($game['game_date']); ?></td>
            <td data-sort="<?php echo $total_bp; ?>">
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;font-size:0.9em;">
                <?php foreach ($pigs as $pidx => $p): 
                    $p_is_me = ($p['vms'] === ($current_user_id ?? ''));
                    $p_can_confirm = $require_conf && $p_is_me && !$p['confirmed'] && !$game['approved'];
                ?>
                    <div class="player-card" style="padding:8px;background:#f9f9f9;border-radius:5px;<?php echo $p['confirmed'] ? 'border:1px solid #4caf50;' : ''; ?>">
                        <div style="font-weight:bold;"><?php echo $medals[$pidx].' '.htmlspecialchars($p['name']); ?></div>
                        <div style="font-size:0.85em;color:#666;"><?php echo htmlspecialchars($p['vms']); ?></div>
                        <div style="font-size:0.85em;margin-top:3px;">BP: <strong><?php echo $p['table']; ?></strong> | MP: <?php echo $p['mini']; ?></div>
                        <?php if ($require_conf): ?>
                            <?php if ($p['confirmed']): ?>
                                <div style="margin-top:4px;"><span style="background:#4caf50;color:white;padding:1px 8px;border-radius:10px;font-size:0.75em;font-weight:bold;">OK</span></div>
                            <?php elseif ($p_can_confirm): ?>
                                <div style="margin-top:4px;">
                                    <button onclick="confirmFromList(<?php echo $game['id']; ?>,'<?php echo $p['slot']; ?>',this)" 
                                            style="background:#ff9800;color:white;border:none;padding:2px 12px;border-radius:10px;font-size:0.75em;font-weight:bold;cursor:pointer;">Tryck OK</button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </td>
            <td>
                <?php if ($game['biggest_hand_points'] > 0): ?>
                    <div style="font-weight:bold;color:#d32f2f;font-size:1.1em;"><?php echo $game['biggest_hand_points'].' '.t('stats_pub_points'); ?></div>
                    <?php if ($game['biggest_hand_player_id']): ?>
                    <div style="font-size:0.9em;margin-top:3px;">
                    <?php
                    $bh_player_list = explode(',', $game['biggest_hand_player_id']);
                    if (count($bh_player_list) === 1 && $game['bp_first']) {
                        // Single player, JOIN worked
                        echo $game['bp_visible'] ? htmlspecialchars($game['bp_first'].' '.$game['bp_last']) : t('games_hidden_player');
                    } elseif (count($bh_player_list) > 1) {
                        // Multiple players — look them up from the game's own player data
                        $bh_names = [];
                        foreach ($bh_player_list as $bh_player) {
                            $bh_player = trim($bh_player);
                            $slots = [
                                'a' => ['vms'=>'player_a_id','first'=>'pa_first','last'=>'pa_last'],
                                'b' => ['vms'=>'player_b_id','first'=>'pb_first','last'=>'pb_last'],
                                'c' => ['vms'=>'player_c_id','first'=>'pc_first','last'=>'pc_last'],
                                'd' => ['vms'=>'player_d_id','first'=>'pd_first','last'=>'pd_last'],
                            ];
                            foreach ($slots as $s) {
                                if ($game[$s['vms']] === $bh_player && !empty($game[$s['first']])) {
                                    $bh_names[] = htmlspecialchars($game[$s['first']].' '.$game[$s['last']]);
                                    break;
                                }
                            }
                        }
                        echo $bh_names ? implode(' & ', $bh_names) : count($bh_player_list).' '.t('nav_players');
                    } else {
                        echo t('games_hidden_player');
                    }
                    ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?><span style="color:#999;">-</span><?php endif; ?>
            </td>
            <td style="font-size:0.9em;">
                <?php if (!empty($game['creator_first'])): ?>
                    <div><?php echo htmlspecialchars($game['creator_first'].' '.$game['creator_last']); ?></div>
                <?php endif; ?>
                <?php if (!empty($game['created_at'])): ?>
                    <div style="font-size:0.85em;color:#666;"><?php echo (new DateTime($game['created_at']))->format('Y-m-d H:i'); ?></div>
                <?php endif; ?>
                <?php if ($game['approved']==0): ?>
                    <div style="color:#ff9800;font-size:0.85em;margin-top:3px;">⏳ <?php echo t('games_pending'); ?></div>
                <?php endif; ?>
                <?php if (empty($game['creator_first']) && empty($game['created_at'])): ?>
                    <span style="color:#999;">-</span>
                <?php endif; ?>
            </td>
            <td style="font-size:0.9em;">
                <?php if ($game['approved']==1 && !empty($game['approver_first'])): ?>
                    <div><?php echo htmlspecialchars($game['approver_first'].' '.$game['approver_last']); ?></div>
                    <?php if (!empty($game['approved_at'])): ?><div style="font-size:0.85em;"><?php echo date('Y-m-d H:i',strtotime($game['approved_at'])); ?></div><?php endif; ?>
                <?php elseif ($game['approved']==0): ?>
                    <span style="color:#ff9800;">⏳</span>
                <?php else: ?><span style="color:#999;">-</span><?php endif; ?>
            </td>
            <td>
                <a href="view-game.php?id=<?php echo $game['id']; ?>"
                   class="btn btn-view-game btn-action"
                   style="background:#2e7d32;color:#FECC02;">
                    <?php echo t('btn_view'); ?>
                </a>
                <?php if ($require_conf && !$game['approved'] && canApproveGames()): 
                    $g_confirmed = 0;
                    foreach (['a','b','c','d'] as $gs) { if (!empty($game['player_'.$gs.'_confirmed_at'])) $g_confirmed++; }
                    $g_all_ok = ($g_confirmed >= 4);
                ?>
                <button onclick="approveFromList(<?php echo $game['id']; ?>,this)" 
                        class="btn btn-action" id="approve-<?php echo $game['id']; ?>"
                        style="background:<?php echo $g_all_ok ? '#4caf50' : '#2196F3'; ?>;color:white;">
                    <?php echo t('games_approve'); ?>
                </button>
                <?php endif; ?>
                <?php if (hasRole('player')): ?>
                <a href="edit-game.php?id=<?php echo $game['id']; ?>"
                   class="btn btn-action"
                   style="background:#FF9800;">
                    <?php echo t('btn_edit'); ?>
                </a>
                <?php endif; ?>
                <?php if (hasRole('superuser')): ?>
                <form method="POST" style="display:inline-block;margin:0;"
                      onsubmit="return confirm('<?php echo addslashes(t('games_confirm_delete')); ?>');">
                    <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                    <button type="submit" name="delete_game" class="btn btn-danger btn-delete-game btn-action">
                        <?php echo t('btn_delete'); ?>
                    </button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div><!-- end desktop table -->
    </div><!-- end games-table-wrap -->

<?php endif; ?>

<?php if ($require_conf): ?>
<script>
function confirmFromList(gameId, slot, btn) {
    btn.disabled = true;
    btn.textContent = '...';
    fetch('api/confirm_game.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'game_id=' + gameId + '&action=confirm'
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            btn.outerHTML = '<span style="background:#4caf50;color:white;padding:1px 8px;border-radius:10px;font-size:0.75em;font-weight:bold;">OK</span>';
            // Update approve button color if all confirmed
            const approveBtn = document.getElementById('approve-' + gameId);
            if (approveBtn && res.all_confirmed) {
                approveBtn.style.background = '#4caf50';
            }
        } else {
            btn.disabled = false;
            btn.textContent = res.error || 'Fel';
        }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Fel'; });
}

function approveFromList(gameId, btn) {
    if (!confirm('<?php echo t('games_approve_confirm'); ?>')) return;
    btn.disabled = true;
    btn.textContent = '...';
    fetch('api/confirm_game.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'game_id=' + gameId + '&action=approve'
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) location.reload();
        else { btn.disabled = false; btn.textContent = res.error || 'Fel'; }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Fel'; });
}
</script>
<?php endif; ?>

<?php includeFooter(); ?>
