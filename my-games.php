<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

// Kräv inloggning
if (!isLoggedIn()) {
    redirect('login.php');
}

$conn = getDbConnection();
$player_id = $_SESSION['user_id'];

// Hämta spelarinfo
$stmt = $conn->prepare("SELECT first_name, last_name FROM stat_players WHERE id = ?");
$stmt->execute([$player_id]);
$player = $stmt->fetch();

// Kolla om systemanvändare
$is_system_user = in_array($id, [1, 2]);

includeHeader();
?>

<h1>🎲 <?php echo t('mygames_heading'); ?></h1>

<?php if ($is_system_user): ?>
    <div class="message info">
        <p><?php echo t('mypage_no_stats_system'); ?></p>
    </div>
<?php else: ?>

<?php
// Hämta alla matcher där spelaren deltog (endast godkända)
$stmt = $conn->prepare("
    SELECT 
        g.*,
        CASE 
            WHEN g.player_a_id = ? THEN 'A'
            WHEN g.player_b_id = ? THEN 'B'
            WHEN g.player_c_id = ? THEN 'C'
            WHEN g.player_d_id = ? THEN 'D'
        END as my_position,
        CASE 
            WHEN g.player_a_id = ? THEN g.player_a_minipoints
            WHEN g.player_b_id = ? THEN g.player_b_minipoints
            WHEN g.player_c_id = ? THEN g.player_c_minipoints
            WHEN g.player_d_id = ? THEN g.player_d_minipoints
        END as my_minipoints,
        CASE 
            WHEN g.player_a_id = ? THEN g.player_a_tablepoints
            WHEN g.player_b_id = ? THEN g.player_b_tablepoints
            WHEN g.player_c_id = ? THEN g.player_c_tablepoints
            WHEN g.player_d_id = ? THEN g.player_d_tablepoints
        END as my_tablepoints,
        CASE 
            WHEN g.player_a_id = ? THEN g.player_a_hu
            WHEN g.player_b_id = ? THEN g.player_b_hu
            WHEN g.player_c_id = ? THEN g.player_c_hu
            WHEN g.player_d_id = ? THEN g.player_d_hu
        END as my_hu,
        CASE 
            WHEN g.player_a_id = ? THEN g.player_a_selfdrawn
            WHEN g.player_b_id = ? THEN g.player_b_selfdrawn
            WHEN g.player_c_id = ? THEN g.player_c_selfdrawn
            WHEN g.player_d_id = ? THEN g.player_d_selfdrawn
        END as my_selfdrawn,
        CASE 
            WHEN g.player_a_id = ? THEN g.player_a_thrown_hu
            WHEN g.player_b_id = ? THEN g.player_b_thrown_hu
            WHEN g.player_c_id = ? THEN g.player_c_thrown_hu
            WHEN g.player_d_id = ? THEN g.player_d_thrown_hu
        END as my_thrown_hu,
        pa.first_name as player_a_first, pa.last_name as player_a_last,
        pb.first_name as player_b_first, pb.last_name as player_b_last,
        pc.first_name as player_c_first, pc.last_name as player_c_last,
        pd.first_name as player_d_first, pd.last_name as player_d_last
    FROM stat_games g
    LEFT JOIN stat_players pa ON g.player_a_id = pa.id
    LEFT JOIN stat_players pb ON g.player_b_id = pb.id
    LEFT JOIN stat_players pc ON g.player_c_id = pc.id
    LEFT JOIN stat_players pd ON g.player_d_id = pd.id
    WHERE g.approved = 1 
        AND (g.player_a_id = ? OR g.player_b_id = ? OR g.player_c_id = ? OR g.player_d_id = ?)
    ORDER BY g.game_date DESC, g.game_number DESC
");
$stmt->execute([
    $id, $id, $id, $id, // my_position
    $id, $id, $id, $id, // my_minipoints
    $id, $id, $id, $id, // my_tablepoints
    $id, $id, $id, $id, // my_hu
    $id, $id, $id, $id, // my_selfdrawn
    $id, $id, $id, $id, // my_thrown_hu
    $id, $id, $id, $player_id  // WHERE
]);
$stat_games = $stmt->fetchAll();

$total_matches = count($stat_games);
?>

<?php if ($total_matches === 0): ?>
    <div class="message info">
        <p><?php echo t('mygames_no_games'); ?></p>
    </div>
<?php else: ?>

<div class="filter-box" style="background: #f0f8ff; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    <h3 style="margin-top: 0;">📊 <?php echo t('mygames_quickstats'); ?></h3>
    <p><strong><?php echo t('mygames_total'); ?></strong> <?php echo $total_matches; ?></p>
</div>

<style>
/* ============ MY-GAMES MOBILE LAYOUT ============ */
/*
  Portrait  cols: Match# | Pos | MP | Hu | Självdr | Kastat | 👁   (7 col)
  Landscape cols: Motst  | Match# | Pos | MP | Hu | Självdr | Kastat | Bästa | 👁  (9 col)
*/
.mg-wrap { background:white; border-radius:10px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.08); }

/* Portrait: 7 fixed columns */
.mg-row {
    display: grid;
    grid-template-columns: 46px 32px 46px 26px 32px 32px 28px;
    align-items: center;
    gap: 0 4px;
    padding: 7px 8px;
    border-bottom: 1px solid #eee;
    font-size: 0.83em;
}
.mg-row:nth-child(even) { background: #fafafa; }

/* Landscape: 9 columns — add Motståndare first + Bästa before 👁 */
@media (orientation:landscape) and (max-width:1024px) {
    .mg-row {
        grid-template-columns: 52px minmax(80px,160px) 32px 46px 26px 10px 32px 32px 46px 28px;
        gap: 0 8px;
    }
    .mg-land { display: block !important; }
}

.mg-header {
    background: #005B99 !important;
    color: white !important;
    font-weight: 700;
    font-size: 0.78em;
    border-radius: 8px 8px 0 0;
    padding: 6px 8px;
    align-items: start;
}
.mg-header div { color: white !important; }

/* Cell base */
.mg-match  { font-weight:700; }
.mg-pos    { text-align:center; border-radius:4px; padding:2px 1px; font-weight:700; }
.mg-val    { text-align:right; font-family:monospace; }
.mg-vis    { text-align:center; }
.mg-opp    { font-size:0.92em; line-height:1.3; word-break:break-word; }

/* Hidden in portrait, shown in landscape */
.mg-land   { display:none; }

/* Spacer cell (portrait hidden, landscape shown as gap) */
.mg-spacer { display:none; }
@media (orientation:landscape) and (max-width:1024px) {
    .mg-spacer { display:block; }
}

/* Position colours */
.pos-1 { background:#ffd700; color:#1a1a1a; }
.pos-2 { background:#b0b0b0; color:#1a1a1a; }
.pos-3 { background:#a0522d; color:white; }
.pos-4 { background:#f0f0f0; color:#555; }

@media (min-width:701px) {
    .mg-mobile  { display:none; }
    .mg-desktop { display:block; overflow-x:auto; }
}
@media (max-width:700px) {
    .mg-desktop { display:none; }
    .mg-mobile  { display:block; }
}
</style>

<!-- MOBILE -->
<div class="mg-mobile">
<div class="mg-wrap">
  <!-- header row -->
  <div class="mg-row mg-header">
    <div class="mg-match"><?php echo $sv ? 'Match' : 'Match'; ?></div>
    <div class="mg-land mg-opp"><?php echo $sv ? 'Motst.' : 'Opp.'; ?></div>
    <div class="mg-pos"><?php echo $sv ? 'Pos' : 'Pos'; ?></div>
    <div class="mg-val">MP</div>
    <div class="mg-val">Hu</div>
    <div class="mg-spacer"></div>
    <div class="mg-val"><?php echo $sv ? 'Självdr' : 'Self'; ?></div>
    <div class="mg-val"><?php echo $sv ? 'Kastat' : 'Disc'; ?></div>
    <div class="mg-land mg-val"><?php echo $sv ? 'Bästa' : 'Best'; ?></div>
    <div class="mg-vis">👁</div>
  </div>

  <?php foreach ($stat_games as $game):
    $positions = [
        'A' => $game['player_a_tablepoints'],
        'B' => $game['player_b_tablepoints'],
        'C' => $game['player_c_tablepoints'],
        'D' => $game['player_d_tablepoints']
    ];
    arsort($positions);
    $my_rank = 0; $rank = 1;
    foreach ($positions as $pos => $pts) {
        if ($pos === $game['my_position']) { $my_rank = $rank; break; }
        $rank++;
    }
    $pos_class = 'pos-' . $my_rank;
    $bh = $game['biggest_hand_points'] > 0 ? $game['biggest_hand_points'].'p' : '—';
    $bh_mine = $game['biggest_hand_points'] > 0 && strpos($game['biggest_hand_player_id'], $player_id) !== false;
    $opponents = [];
    if ($game['my_position'] !== 'A') $opponents[] = $game['player_a_first'] . ' ' . $game['player_a_last'];
    if ($game['my_position'] !== 'B') $opponents[] = $game['player_b_first'] . ' ' . $game['player_b_last'];
    if ($game['my_position'] !== 'C') $opponents[] = $game['player_c_first'] . ' ' . $game['player_c_last'];
    if ($game['my_position'] !== 'D') $opponents[] = $game['player_d_first'] . ' ' . $game['player_d_last'];
  ?>
  <div class="mg-row">
    <div class="mg-match">
      #<?php echo $game['game_number']; ?>
      <?php if ($game['game_name']): ?><div style="font-size:0.75em;color:#777;font-weight:400;"><?php echo htmlspecialchars($game['game_name']); ?></div><?php endif; ?>
    </div>
    <div class="mg-land mg-opp"><?php echo implode('<br>', array_map('htmlspecialchars', $opponents)); ?></div>
    <div class="mg-pos <?php echo $pos_class; ?>"><?php echo $my_rank; ?>:a</div>
    <div class="mg-val" style="font-weight:700;"><?php echo $game['my_minipoints']; ?></div>
    <div class="mg-val"><?php echo $game['my_hu'] ?? 0; ?></div>
    <div class="mg-spacer"></div>
    <div class="mg-val"><?php echo $game['my_selfdrawn'] ?? 0; ?></div>
    <div class="mg-val"><?php echo $game['my_thrown_hu'] ?? 0; ?></div>
    <div class="mg-land mg-val"><?php echo $bh; ?><?php if ($bh_mine): ?> ✓<?php endif; ?></div>
    <div class="mg-vis">
      <a href="view-game.php?id=<?php echo $game['id']; ?>" style="color:#005B99;text-decoration:none;font-size:1.1em;">👁</a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
</div>

<!-- DESKTOP — original columns restored, Pos before MP, Händer last before button -->
<div class="mg-desktop">
<div style="overflow-x:auto;">
    <table class="stat_players-table">
        <thead>
            <tr>
                <th><?php echo t('mygames_col_match'); ?></th>
                <th><?php echo t('mygames_col_opponents'); ?></th>
                <th><?php echo t('mygames_col_pos'); ?></th>
                <th><?php echo t('mygames_col_mybp'); ?></th>
                <th><?php echo t('mygames_col_mymp'); ?></th>
                <th><?php echo t('mygames_col_hu'); ?></th>
                <th><?php echo t('mygames_col_selfdrawn'); ?></th>
                <th><?php echo t('mygames_col_thrown'); ?></th>
                <th><?php echo t('mygames_col_bighand'); ?></th>
                <th><?php echo t('mygames_col_hands'); ?></th>
                <th><?php echo t('mygames_col_actions'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stat_games as $game):
                $positions = [
                    'A' => $game['player_a_tablepoints'],
                    'B' => $game['player_b_tablepoints'],
                    'C' => $game['player_c_tablepoints'],
                    'D' => $game['player_d_tablepoints']
                ];
                arsort($positions);
                $my_rank = 0; $rank = 1;
                foreach ($positions as $pos => $pts) {
                    if ($pos === $game['my_position']) { $my_rank = $rank; break; }
                    $rank++;
                }
                $opponents = [];
                if ($game['my_position'] !== 'A') $opponents[] = $game['player_a_first'] . ' ' . $game['player_a_last'];
                if ($game['my_position'] !== 'B') $opponents[] = $game['player_b_first'] . ' ' . $game['player_b_last'];
                if ($game['my_position'] !== 'C') $opponents[] = $game['player_c_first'] . ' ' . $game['player_c_last'];
                if ($game['my_position'] !== 'D') $opponents[] = $game['player_d_first'] . ' ' . $game['player_d_last'];
                $pos_styles = ['','background:#ffd700;font-weight:bold;color:#1a1a1a;','background:#b0b0b0;color:#1a1a1a;','background:#a0522d;color:white;',''];
            ?>
            <tr>
                <td>
                    <strong>#<?php echo $game['game_number']; ?></strong>
                    <?php if ($game['game_name']): ?><br><small><?php echo htmlspecialchars($game['game_name']); ?></small><?php endif; ?>
                </td>
                <td style="font-size:0.9em;"><?php echo implode('<br>', array_map('htmlspecialchars', $opponents)); ?></td>
                <td style="<?php echo $pos_styles[$my_rank]; ?>"><strong><?php echo $my_rank; ?>:a</strong></td>
                <td><strong><?php echo $game['my_tablepoints']; ?></strong></td>
                <td><?php echo $game['my_minipoints']; ?></td>
                <td><?php echo $game['my_hu'] ?? 0; ?></td>
                <td><?php echo $game['my_selfdrawn'] ?? 0; ?></td>
                <td><?php echo $game['my_thrown_hu'] ?? 0; ?></td>
                <td>
                    <?php if ($game['biggest_hand_points'] > 0): ?>
                        <?php echo $game['biggest_hand_points']; ?> p
                        <?php if (strpos($game['biggest_hand_player_id'], $player_id) !== false): ?>
                            <br><small style="color:green;">✓ Min!</small>
                        <?php endif; ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td><?php echo $game['hands_played'] ?? 0; ?></td>
                <td>
                    <a href="view-game.php?id=<?php echo $game['id']; ?>" class="btn btn-secondary" style="padding:5px 10px;font-size:0.9em;">
                        👁️ <?php echo $sv ? 'Visa' : 'View'; ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>

<?php endif; ?>

<?php endif; ?>

<?php includeFooter(); ?>
