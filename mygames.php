<?php
require_once 'config.php';

// Kräv inloggning
if (!isLoggedIn()) {
    redirect('login.php');
}

$conn = getDbConnection();
$player_id = $_SESSION['user_id'];

// Hämta spelarinfo
$stmt = $conn->prepare("SELECT first_name, last_name FROM players WHERE id = ?");
$stmt->execute([$player_id]);
$player = $stmt->fetch();

// Kolla om systemanvändare
$is_system_user = in_array($id, (1, 2));

includeHeader();
?>

<h1>🎲 Mina matcher</h1>

<?php if ($is_system_user): ?>
    <div class="message info">
        <p>Systemanvändare har inga registrerade matcher.</p>
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
    FROM games g
    LEFT JOIN players pa ON g.player_a_id = pa.id
    LEFT JOIN players pb ON g.player_b_id = pb.id
    LEFT JOIN players pc ON g.player_c_id = pc.id
    LEFT JOIN players pd ON g.player_d_id = pd.id
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
$games = $stmt->fetchAll();

$total_matches = count($games);
?>

<?php if ($total_matches === 0): ?>
    <div class="message info">
        <p>Du har inga matcher registrerade ännu.</p>
    </div>
<?php else: ?>

<div style="background: #f0f8ff; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    <h3 style="margin-top: 0;">📊 Snabbstatistik</h3>
    <p><strong>Totalt antal matcher:</strong> <?php echo $total_matches; ?></p>
</div>

<div style="overflow-x: auto;">
    <table class="players-table">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Match</th>
                <th>Motståndare</th>
                <th>Mina BP</th>
                <th>Mina MP</th>
                <th>Position</th>
                <th>Hu</th>
                <th>Selfdrawn</th>
                <th>Kastat</th>
                <th>Händer</th>
                <th>Största hand</th>
                <th>Åtgärder</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($games as $game): 
                // Bestäm position (1-4) baserat på tablepoints
                $positions = [
                    'A' => $game['player_a_tablepoints'],
                    'B' => $game['player_b_tablepoints'],
                    'C' => $game['player_c_tablepoints'],
                    'D' => $game['player_d_tablepoints']
                ];
                arsort($positions);
                $rank = 1;
                $my_rank = 0;
                foreach ($positions as $pos => $points) {
                    if ($pos === $game['my_position']) {
                        $my_rank = $rank;
                        break;
                    }
                    $rank++;
                }
                
                // Bygg motståndarelista
                $opponents = [];
                if ($game['my_position'] !== 'A') $opponents[] = $game['player_a_first'] . ' ' . $game['player_a_last'];
                if ($game['my_position'] !== 'B') $opponents[] = $game['player_b_first'] . ' ' . $game['player_b_last'];
                if ($game['my_position'] !== 'C') $opponents[] = $game['player_c_first'] . ' ' . $game['player_c_last'];
                if ($game['my_position'] !== 'D') $opponents[] = $game['player_d_first'] . ' ' . $game['player_d_last'];
                
                // Färgkodning för position
                $position_color = '';
                if ($my_rank == 1) $position_color = 'style="background: #ffd700; font-weight: bold;"'; // Guld
                elseif ($my_rank == 2) $position_color = 'style="background: #c0c0c0;"'; // Silver
                elseif ($my_rank == 3) $position_color = 'style="background: #cd7f32;"'; // Brons
            ?>
            <tr>
                <td><?php echo date('Y-m-d', strtotime($game['game_date'])); ?></td>
                <td>
                    <strong>#<?php echo $game['game_number']; ?></strong>
                    <?php if ($game['game_name']): ?>
                        <br><small><?php echo htmlspecialchars($game['game_name']); ?></small>
                    <?php endif; ?>
                </td>
                <td style="font-size: 0.9em;">
                    <?php echo implode('<br>', array_map('htmlspecialchars', $opponents)); ?>
                </td>
                <td><strong><?php echo $game['my_tablepoints']; ?></strong></td>
                <td><?php echo $game['my_minipoints']; ?></td>
                <td <?php echo $position_color; ?>>
                    <strong><?php echo $my_rank; ?>:a</strong>
                </td>
                <td><?php echo $game['my_hu'] ?? 0; ?></td>
                <td><?php echo $game['my_selfdrawn'] ?? 0; ?></td>
                <td><?php echo $game['my_thrown_hu'] ?? 0; ?></td>
                <td><?php echo $game['hands_played'] ?? 0; ?></td>
                <td>
                    <?php if ($game['biggest_hand_points'] > 0): ?>
                        <?php echo $game['biggest_hand_points']; ?> p
                        <?php if (strpos($game['biggest_hand_player_id'], $player_id) !== false): ?>
                            <br><small style="color: green;">✓ Min!</small>
                        <?php endif; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td>
                    <a href="view-game.php?id=<?php echo $game['id']; ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.9em;">
                        👁️ Visa
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php endif; ?>

<?php includeFooter(); ?>
