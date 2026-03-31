<?php
require_once 'config.php';

// Kräv minst registrator-behörighet — redirect till login om ej inloggad
if (!isLoggedIn()) {
    $return_url = 'view-game.php?id=' . ((int)($_GET['id'] ?? 0));
    header('Location: login.php?redirect=' . urlencode($return_url));
    exit;
}
if (!hasRole('player')) {
    showError('Du måste ha registrator-behörighet eller högre för att se matcher.');
}

$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($game_id <= 0) {
    showError('Ogiltigt match-id.');
}

$conn = getDbConnection();

// Hämta matchinformation
$stmt = $conn->prepare("
    SELECT 
        g.*,
        pa.first_name as pa_first, pa.last_name as pa_last,
        pb.first_name as pb_first, pb.last_name as pb_last,
        pc.first_name as pc_first, pc.last_name as pc_last,
        pd.first_name as pd_first, pd.last_name as pd_last,
        bp.first_name as bp_first, bp.last_name as bp_last
    FROM stat_games g
    LEFT JOIN stat_players pa ON g.player_a_id = pa.id
    LEFT JOIN stat_players pb ON g.player_b_id = pb.id
    LEFT JOIN stat_players pc ON g.player_c_id = pc.id
    LEFT JOIN stat_players pd ON g.player_d_id = pd.id
    LEFT JOIN stat_players bp ON g.biggest_hand_player_id = bp.id
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
    $stmt = $conn->prepare("
        SELECT * FROM stat_game_hands 
        WHERE game_id = ? 
        ORDER BY hand_number ASC
    ");
    $stmt->execute([$game_id]);
    $hands = $stmt->fetchAll();
}

$stat_players = [
    'A' => [
        'vms' => $game['player_a_id'],
        'name' => $game['pa_first'] . ' ' . $game['pa_last'],
        'mini' => $game['player_a_minipoints'],
        'table' => $game['player_a_tablepoints'],
        'penalties' => $game['player_a_penalties'],
        'false_hu' => $game['player_a_false_hu'],
        'confirmed_at' => $game['player_a_confirmed_at'] ?? null,
        'confirmed_by' => $game['player_a_confirmed_by'] ?? null,
        'slot' => 'a',
    ],
    'B' => [
        'vms' => $game['player_b_id'],
        'name' => $game['pb_first'] . ' ' . $game['pb_last'],
        'mini' => $game['player_b_minipoints'],
        'table' => $game['player_b_tablepoints'],
        'penalties' => $game['player_b_penalties'],
        'false_hu' => $game['player_b_false_hu'],
        'confirmed_at' => $game['player_b_confirmed_at'] ?? null,
        'confirmed_by' => $game['player_b_confirmed_by'] ?? null,
        'slot' => 'b',
    ],
    'C' => [
        'vms' => $game['player_c_id'],
        'name' => $game['pc_first'] . ' ' . $game['pc_last'],
        'mini' => $game['player_c_minipoints'],
        'table' => $game['player_c_tablepoints'],
        'penalties' => $game['player_c_penalties'],
        'false_hu' => $game['player_c_false_hu'],
        'confirmed_at' => $game['player_c_confirmed_at'] ?? null,
        'confirmed_by' => $game['player_c_confirmed_by'] ?? null,
        'slot' => 'c',
    ],
    'D' => [
        'vms' => $game['player_d_id'],
        'name' => $game['pd_first'] . ' ' . $game['pd_last'],
        'mini' => $game['player_d_minipoints'],
        'table' => $game['player_d_tablepoints'],
        'penalties' => $game['player_d_penalties'],
        'false_hu' => $game['player_d_false_hu'],
        'confirmed_at' => $game['player_d_confirmed_at'] ?? null,
        'confirmed_by' => $game['player_d_confirmed_by'] ?? null,
        'slot' => 'd',
    ]
];

$require_confirmation = defined('REQUIRE_PLAYER_CONFIRMATION') && REQUIRE_PLAYER_CONFIRMATION;
$current_user_id = $_SESSION['user_id'] ?? '';
$is_player_in_game = in_array($current_user_id, [$game['player_a_id'], $game['player_b_id'], $game['player_c_id'], $game['player_d_id']]);
$confirmed_count = 0;
foreach (['a','b','c','d'] as $s) {
    if ($game['player_'.$s.'_confirmed_at']) $confirmed_count++;
}
$all_confirmed = ($confirmed_count >= 4);
$sv = (currentLang() === 'sv');

includeHeader();
?>

<h2>Match <?php echo $game['game_number']; ?> (<?php echo $game['game_year']; ?>)</h2>

<div style="margin: 20px 0;">
    <a href="games.php" class="btn btn-secondary">← Tillbaka till matcher</a>
    <?php if (hasRole('superuser')): ?>
        <a href="edit-game.php?id=<?php echo $game['id']; ?>" class="btn" style="background: #FF9800;">
            ✏️ Redigera match
        </a>
    <?php endif; ?>
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
            <?php echo $game['detailed_entry'] ? '🀄 Hand-för-hand' : '📊 Slutresultat'; ?>
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

<?php // Approve button for admin (above player cards)
if ($require_confirmation && !$game['approved'] && canApproveGames()): ?>
<div style="margin-bottom:16px;text-align:right;">
    <button id="btnApprove" onclick="approveGame(<?php echo $game['id']; ?>)" 
            class="btn" style="background:<?php echo $all_confirmed ? '#4caf50' : '#2196F3'; ?>;color:white;padding:10px 24px;font-size:1em;">
        <?php echo t('games_approve'); ?>
        <?php if (!$all_confirmed): ?><span style="font-size:0.8em;opacity:0.8;">(<?php echo $confirmed_count; ?>/4 OK)</span><?php endif; ?>
    </button>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
    <?php 
    $sorted_players = $stat_players;
    uasort($sorted_players, function($a, $b) {
        return $b['table'] <=> $a['table'];
    });
    
    $medals = ['🥇', '🥈', '🥉', ''];
    $idx = 0;
    
    foreach ($sorted_players as $letter => $p): 
    $is_me = ($p['vms'] === $current_user_id);
    $is_confirmed = !empty($p['confirmed_at']);
    $can_confirm = $require_confirmation && $is_me && !$is_confirmed && !$game['approved'];
    ?>
    <div id="card-<?php echo $p['slot']; ?>" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 2px solid <?php echo $is_confirmed ? '#4caf50' : '#ddd'; ?>;">
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
        
        <?php // OK badge or confirm button ?>
        <?php if ($require_confirmation): ?>
        <div id="ok-<?php echo $p['slot']; ?>" style="text-align:center;margin-top:12px;">
            <?php if ($is_confirmed): ?>
                <span style="display:inline-block;background:#4caf50;color:white;padding:4px 16px;border-radius:20px;font-weight:bold;font-size:0.9em;">OK ✓</span>
            <?php elseif ($can_confirm): ?>
                <button onclick="confirmGame(<?php echo $game['id']; ?>, '<?php echo $p['slot']; ?>')" 
                        style="display:inline-block;background:#ff9800;color:white;padding:8px 24px;border:none;border-radius:20px;font-weight:bold;font-size:1em;cursor:pointer;">
                    <?php echo t('confirm_press_ok'); ?>
                </button>
            <?php else: ?>
                <span style="display:inline-block;background:#eee;color:#999;padding:4px 16px;border-radius:20px;font-size:0.85em;">
                    <?php echo t('confirm_waiting'); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php 
    $idx++;
    endforeach; 
    ?>
</div>

<?php if ($game['approved']): ?>
<div style="background:#e8f5e9;border:1px solid #4caf50;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:0.9em;">
    ✅ <?php echo t('confirm_approved_label'); ?>
    <?php if ($game['approved_at']): ?>
        <?php echo date('Y-m-d H:i', strtotime($game['approved_at'])); ?>
    <?php endif; ?>
    <?php 
    if ($game['approved_by_id']) {
        $appr_stmt = $conn->prepare("SELECT first_name, last_name FROM stat_players WHERE id = ?");
        $appr_stmt->execute([$game['approved_by_id']]);
        $appr = $appr_stmt->fetch();
        if ($appr) echo ' — ' . htmlspecialchars($appr['first_name'] . ' ' . $appr['last_name']);
    }
    ?>
</div>
<?php endif; ?>

<?php if ($require_confirmation && !$game['approved']): ?>
<script>
function confirmGame(gameId, slot) {
    const btn = event.target;
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
            document.getElementById('ok-' + slot).innerHTML = 
                '<span style="display:inline-block;background:#4caf50;color:white;padding:4px 16px;border-radius:20px;font-weight:bold;font-size:0.9em;">OK ✓</span>';
            document.getElementById('card-' + slot).style.borderColor = '#4caf50';
            // Update approve button
            const approveBtn = document.getElementById('btnApprove');
            if (approveBtn && res.all_confirmed) {
                approveBtn.style.background = '#4caf50';
                approveBtn.innerHTML = '<?php echo t('games_approve'); ?>';
            } else if (approveBtn) {
                approveBtn.querySelector('span').textContent = '(' + res.confirmed_count + '/4 OK)';
            }
        } else {
            btn.disabled = false;
            btn.textContent = res.error || 'Error';
        }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Error'; });
}

function approveGame(gameId) {
    const btn = document.getElementById('btnApprove');
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
        if (res.success) {
            location.reload();
        } else {
            btn.disabled = false;
            btn.textContent = res.error || 'Error';
        }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Error'; });
}
</script>
<?php endif; ?>

<!-- HAND-FÖR-HAND DETALJER -->
<?php if ($game['detailed_entry'] && !empty($hands)): ?>

<h3 style="margin-top:40px;"><?php echo $sv ? 'Hand-för-hand' : 'Hand by hand'; ?></h3>

<!-- Legend -->
<div style="display:flex;flex-wrap:wrap;gap:6px;margin:12px 0 16px;font-size:0.85em;">
    <span style="background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:12px;font-weight:600;">🀄 <?php echo $sv ? 'Självdrog' : 'Self-drawn'; ?></span>
    <span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:12px;font-weight:600;">✅ <?php echo $sv ? 'Vann hu' : 'Won hu'; ?></span>
    <span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:12px;font-weight:600;">❌ <?php echo $sv ? 'Kastade' : 'Discarded'; ?></span>
</div>

<!-- Compact hand list -->
<div style="background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);overflow-x:auto;">

<!-- Header -->
<div style="display:grid;grid-template-columns:36px 36px repeat(4,1fr);background:#005B99;color:white;font-weight:700;font-size:0.82em;padding:8px 10px;">
    <div>#</div>
    <div><?php echo $sv ? 'Pts' : 'Pts'; ?></div>
    <?php foreach (['A','B','C','D'] as $slot):
        $full = $stat_players[$slot]['name'];
        $fname = explode(' ', trim($full))[0];
    ?>
    <div style="text-align:right;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        <?php echo htmlspecialchars($fname); ?>
    </div>
    <?php endforeach; ?>
</div>

<?php
$running_totals = [0,0,0,0];
foreach ($hands as $hidx => $hand):
    $hand_points = [
        $hand['player_a_points'],
        $hand['player_b_points'],
        $hand['player_c_points'],
        $hand['player_d_points'],
    ];
    $running_totals[0] += $hand_points[0];
    $running_totals[1] += $hand_points[1];
    $running_totals[2] += $hand_points[2];
    $running_totals[3] += $hand_points[3];
    $wind_sep = ($hidx > 0 && $hidx % 4 === 0) ? 'border-top:2px solid #005B99;' : '';
    $hu = $hand['hu_points'];
    $from = (int)$hand['from_player'];
    $winner = (int)$hand['winning_player'];
    $pts_label = $hu > 0 ? ($from === 0 ? $hu.'🀄' : $hu) : ($sv ? 'Oavgj' : 'Draw');
?>
<div style="display:grid;grid-template-columns:36px 36px repeat(4,1fr);padding:5px 10px;border-bottom:1px solid #f0f0f0;font-size:0.85em;align-items:center;<?php echo $wind_sep; ?>">
    <div style="color:#888;font-size:0.9em;"><?php echo $hidx+1; ?></div>
    <div style="font-size:0.78em;color:#555;"><?php echo $pts_label; ?></div>
    <?php foreach ($hand_points as $idx => $points):
        $player_num = $idx + 1;
        $is_winner       = ($winner === $player_num);
        $is_self         = ($from === 0 && $is_winner);
        $is_discard_win  = ($is_winner && $from > 0);
        $is_discarder    = ($from === $player_num);

        if ($is_self)        { $bg = '#dbeafe'; $tc = '#1e40af'; }
        elseif ($is_discard_win) { $bg = '#dcfce7'; $tc = '#166534'; }
        elseif ($is_discarder)   { $bg = '#fee2e2'; $tc = '#991b1b'; }
        else                     { $bg = 'transparent'; $tc = ($points < 0 ? '#555' : '#333'); }

        $sign = $points > 0 ? '+' : '';
        $rt = $running_totals[$idx];
        $rt_sign = $rt >= 0 ? '+' : '';
    ?>
    <div style="text-align:right;padding:3px 4px;border-radius:4px;background:<?php echo $bg; ?>;">
        <div style="font-weight:700;font-family:monospace;color:<?php echo $tc; ?>;"><?php echo $sign.$points; ?></div>
        <div style="font-size:0.75em;color:#333;font-family:monospace;"><?php echo $rt_sign.$rt; ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<!-- Totals bar -->
<div style="display:grid;grid-template-columns:36px 36px repeat(4,1fr);background:#e8f0f8;border-top:2px solid #c5d5e5;padding:10px;font-size:0.88em;font-weight:700;">
    <div style="grid-column:span 2;color:#005B99;"><?php echo $sv ? 'Total' : 'Total'; ?></div>
    <?php foreach ($running_totals as $rt):
        $sign = $rt >= 0 ? '+' : '';
        $col  = $rt > 0 ? '#1b5e20' : ($rt < 0 ? '#b71c1c' : '#555');
    ?>
    <div style="text-align:right;font-family:monospace;color:<?php echo $col; ?>;"><?php echo $sign.$rt; ?></div>
    <?php endforeach; ?>
</div>

<!-- Penalties row if any -->
<?php
$has_penalties = ($stat_players['A']['penalties'] + $stat_players['B']['penalties'] + $stat_players['C']['penalties'] + $stat_players['D']['penalties']) > 0;
if ($has_penalties):
?>
<div style="display:grid;grid-template-columns:36px 36px repeat(4,1fr);background:#fff8e1;border-top:2px solid #ffc107;padding:8px 10px;font-size:0.85em;">
    <div style="grid-column:span 2;color:#856404;font-weight:600;">⚠ <?php echo $sv ? 'Straff' : 'Penalty'; ?></div>
    <?php foreach (['A','B','C','D'] as $slot):
        $pen = $stat_players[$slot]['penalties'];
    ?>
    <div style="text-align:right;font-family:monospace;color:<?php echo $pen > 0 ? '#c62828' : '#999'; ?>;">
        <?php echo $pen > 0 ? '-'.$pen : '—'; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

</div><!-- end hand list -->

<?php else: ?>
    <div class="message info" style="margin-top: 40px;">
        <p><?php echo $sv ? 'Denna match registrerades som snabbregistrering, utan hand-för-hand detaljer.' : 'This match was registered without hand-by-hand details.'; ?></p>
    </div>
<?php endif; ?>

<?php includeFooter(); ?>