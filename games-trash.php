<?php
/**
 * games-trash.php
 * Papperskorg för raderade matcher. Bara admin kan se och hantera.
 * Återställ eller radera permanent.
 *
 * Upload: www/games-trash.php
 */
require_once 'config.php';

if (!hasRole('superuser')) {
    showError(currentLang() === 'sv' 
        ? 'Du har inte behörighet att se denna sida.' 
        : 'You do not have access to this page.');
}

$conn = getDbConnection();
$success = '';
$error = '';
$sv = (currentLang() === 'sv');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_id = (int)($_POST['game_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($game_id > 0) {
        try {
            if ($action === 'restore') {
                $conn->prepare("UPDATE stat_games SET deleted_at = NULL, deleted_by_id = NULL WHERE id = ?")
                     ->execute([$game_id]);
                $success = t('trash_restored');
            } elseif ($action === 'permanent_delete') {
                $conn->beginTransaction();
                $conn->prepare("DELETE FROM stat_game_hands WHERE game_id = ?")->execute([$game_id]);
                $conn->prepare("DELETE FROM stat_games WHERE id = ?")->execute([$game_id]);
                $conn->commit();
                $success = t('trash_deleted_permanent');
            } elseif ($action === 'restore_all') {
                $conn->query("UPDATE stat_games SET deleted_at = NULL, deleted_by_id = NULL WHERE deleted_at IS NOT NULL");
                $success = t('trash_restored_all');
            } elseif ($action === 'empty_trash') {
                $conn->beginTransaction();
                $conn->query("DELETE FROM stat_game_hands WHERE game_id IN (SELECT id FROM stat_games WHERE deleted_at IS NOT NULL)");
                $conn->query("DELETE FROM stat_games WHERE deleted_at IS NOT NULL");
                $conn->commit();
                $success = t('trash_emptied');
            }
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    } elseif ($action === 'restore_all' || $action === 'empty_trash') {
        // These don't need game_id
        try {
            if ($action === 'restore_all') {
                $conn->query("UPDATE stat_games SET deleted_at = NULL, deleted_by_id = NULL WHERE deleted_at IS NOT NULL");
                $success = t('trash_restored_all');
            } elseif ($action === 'empty_trash') {
                $conn->beginTransaction();
                $conn->query("DELETE FROM stat_game_hands WHERE game_id IN (SELECT id FROM stat_games WHERE deleted_at IS NOT NULL)");
                $conn->query("DELETE FROM stat_games WHERE deleted_at IS NOT NULL");
                $conn->commit();
                $success = t('trash_emptied');
            }
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Fetch trashed games
$stmt = $conn->query("
    SELECT g.*,
        pa.first_name as pa_first, pa.last_name as pa_last,
        pb.first_name as pb_first, pb.last_name as pb_last,
        pc.first_name as pc_first, pc.last_name as pc_last,
        pd.first_name as pd_first, pd.last_name as pd_last,
        del.first_name as del_first, del.last_name as del_last
    FROM stat_games g
    LEFT JOIN stat_players pa  ON g.player_a_id = pa.id
    LEFT JOIN stat_players pb  ON g.player_b_id = pb.id
    LEFT JOIN stat_players pc  ON g.player_c_id = pc.id
    LEFT JOIN stat_players pd  ON g.player_d_id = pd.id
    LEFT JOIN stat_players del ON g.deleted_by_id = del.id
    WHERE g.deleted_at IS NOT NULL
    ORDER BY g.deleted_at DESC
");
$trashed = $stmt->fetchAll();

includeHeader();
?>

<h1>🗑️ <?php echo t('trash_title'); ?></h1>

<p style="color:#666;margin-bottom:20px;">
    <a href="games.php" style="color:#005B99;">← <?php echo t('trash_back'); ?></a>
</p>

<?php if ($success): ?><div class="message success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if (empty($trashed)): ?>
<div class="message info">
    <p>🗑️ <?php echo t('trash_empty'); ?></p>
</div>

<?php else: ?>

<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="POST" onsubmit="return confirm('<?php echo t('trash_restore_confirm'); ?>')">
        <input type="hidden" name="action" value="restore_all">
        <button type="submit" class="btn" style="background:#4caf50;">♻️ <?php echo t('trash_restore_all'); ?> (<?php echo count($trashed); ?>)</button>
    </form>
    <form method="POST" onsubmit="return confirm('<?php echo t('trash_empty_confirm'); ?>')">
        <input type="hidden" name="action" value="empty_trash">
        <button type="submit" class="btn" style="background:#c62828;color:white;">💀 <?php echo t('trash_empty_btn'); ?></button>
    </form>
</div>

<div style="overflow-x:auto;">
<table>
    <thead>
        <tr>
            <th>#</th>
            <th><?php echo t('trash_deleted_col') . ' / ' . t('games_date'); ?></th>
            <th><?php echo t('games_name'); ?></th>
            <th><?php echo t('nav_players'); ?></th>
            <th><?php echo t('trash_deleted_col'); ?></th>
            <th><?php echo t('games_actions'); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($trashed as $game): ?>
    <tr>
        <td style="font-family:monospace;"><?php echo $game['game_number']; ?></td>
        <td><?php echo $game['game_date']; ?></td>
        <td><?php echo htmlspecialchars($game['game_name'] ?: '—'); ?></td>
        <td style="font-size:0.85em;">
            <?php 
            $names = [];
            foreach (['a','b','c','d'] as $slot) {
                $f = $game['p'.$slot.'_first'] ?? '';
                $l = $game['p'.$slot.'_last'] ?? '';
                if ($f) $names[] = $f . ' ' . substr($l, 0, 1) . '.';
            }
            echo htmlspecialchars(implode(', ', $names));
            ?>
        </td>
        <td style="font-size:0.82em;">
            <?php echo date('Y-m-d H:i', strtotime($game['deleted_at'])); ?>
            <?php if ($game['del_first']): ?>
                <br><span style="color:#888;"><?php echo htmlspecialchars($game['del_first'] . ' ' . $game['del_last']); ?></span>
            <?php endif; ?>
        </td>
        <td style="white-space:nowrap;">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                <input type="hidden" name="action" value="restore">
                <button type="submit" class="btn" style="padding:4px 12px;font-size:0.85em;background:#4caf50;" title="<?php echo t('trash_restore'); ?>">
                    ♻️ <?php echo t('trash_restore'); ?>
                </button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo t('trash_delete_confirm'); ?>')">
                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                <input type="hidden" name="action" value="permanent_delete">
                <button type="submit" class="btn" style="padding:4px 12px;font-size:0.85em;background:#c62828;color:white;" title="<?php echo t('trash_delete_confirm'); ?>">
                    💀 <?php echo t('trash_delete_permanent'); ?>
                </button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<p style="font-size:0.85em;color:#888;margin-top:12px;">
    <?php echo count($trashed); ?> <?php echo t('trash_count'); ?>
</p>

<?php endif; ?>

<?php includeFooter(); ?>
