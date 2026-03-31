<?php
/**
 * draft-monitor.php
 * Visar pågående live draft-sessioner.
 * Alla inloggade spelare kan se drafts de deltar i.
 * Admin ser alla drafts.
 * "Ta över registrering" laddar en pågående match i mobile-draft.php.
 *
 * Upload: www/draft-monitor.php
 */

require_once 'config.php';

$sv = (currentLang() === 'sv');
$conn = getDbConnection();

if (!isLoggedIn()) {
    $login_url = SITE_URL . '/login.php?redirect=' . urlencode('draft-monitor.php');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>';
    echo '<body style="font-family:\'Segoe UI\',sans-serif;padding:40px;text-align:center;">';
    echo '<h2>🔒 ' . ($sv ? 'Ej inloggad' : 'Not logged in') . '</h2>';
    echo '<p style="font-size:1.1em;line-height:1.6;">' . ($sv
        ? 'Du måste vara inloggad. <a href="' . $login_url . '" style="color:#005B99;text-decoration:underline;font-weight:600;">Logga in här</a>'
        : 'You must be logged in. <a href="' . $login_url . '" style="color:#005B99;text-decoration:underline;font-weight:600;">Log in here</a>') . '</p>';
    echo '</body></html>';
    exit;
}

$is_admin = hasRole('admin');
$currentVms = $_SESSION['user_id'] ?? '';

// Check if tables exist
$tablesExist = true;
try {
    $conn->query("SELECT 1 FROM stat_live_sessions LIMIT 1");
} catch (Exception $e) {
    $tablesExist = false;
}

// AJAX: delete a draft (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (($data['action'] ?? '') === 'delete_draft' && ($data['draft_id'] ?? '') && $is_admin) {
        try {
            $did = $data['draft_id'];
            $conn->prepare("DELETE FROM stat_live_hands WHERE draft_id=?")->execute([$did]);
            $conn->prepare("DELETE FROM stat_live_penalties WHERE draft_id=?")->execute([$did]);
            $conn->prepare("DELETE FROM stat_live_sessions WHERE draft_id=?")->execute([$did]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// Load sessions — admin sees all, players see only theirs
$sessions = [];
if ($tablesExist) {
    try {
        if ($is_admin) {
            $stmt = $conn->query("SELECT * FROM stat_live_sessions ORDER BY FIELD(status,'active','saved','discarded'), updated_at DESC LIMIT 50");
        } else {
            $likeVms = '%"' . str_replace(['%','_'], ['\\%','\\_'], $currentVms) . '"%';
            $stmt = $conn->prepare("SELECT * FROM stat_live_sessions WHERE (created_by_id=? OR players_json LIKE ?) AND updated_at > DATE_SUB(NOW(), INTERVAL 48 HOUR) ORDER BY FIELD(status,'active','saved','discarded'), updated_at DESC LIMIT 20");
            $stmt->execute([$currentVms, $likeVms]);
        }
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="<?php echo currentLang(); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>📋 <?php echo $sv ? 'Pågående matcher' : 'Active games'; ?></title>
<style>
body { margin:0; padding:12px; background:#F7F8FA; font-family:'Segoe UI',Tahoma,sans-serif; }
.container { max-width:800px; margin:0 auto; }
h1 { color:#005B99; font-size:1.3em; text-align:center; margin:8px 0 16px; }
.card { background:white; border-radius:10px; padding:14px; margin-bottom:12px; box-shadow:0 1px 6px rgba(0,0,0,0.08); }
.card.active-card { border-left:4px solid #2e7d32; }
.card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:6px; }
.card-header h3 { margin:0; font-size:1em; color:#333; }
.badge { display:inline-block; padding:2px 10px; border-radius:12px; font-size:0.78em; font-weight:700; color:white; }
.badge-active { background:#2e7d32; }
.badge-saved { background:#005B99; }
.badge-discarded { background:#999; }
.meta { font-size:0.82em; color:#666; line-height:1.6; }
.meta strong { color:#333; }
.hands-table { width:100%; border-collapse:collapse; margin-top:8px; font-size:0.82em; }
.hands-table th { background:#e8f0f8; color:#005B99; padding:5px 6px; text-align:center; font-weight:600; }
.hands-table td { padding:4px 6px; text-align:center; border-bottom:1px solid #f0f0f0; }
.hands-table tr:nth-child(even) { background:#fafafa; }
.penalty-row { background:#fff8e1 !important; }
.btn-takeover { background:#2e7d32; color:white; border:none; padding:10px 20px; border-radius:8px; font-size:0.95em; font-weight:700; cursor:pointer; width:100%; margin-top:10px; }
.btn-takeover:hover { background:#1b5e20; }
.btn-del { background:#c62828; color:white; border:none; padding:6px 14px; border-radius:6px; font-size:0.82em; font-weight:600; cursor:pointer; }
.btn-del:hover { background:#b71c1c; }
.empty { text-align:center; color:#999; padding:40px; font-size:1.1em; }
.no-tables { text-align:center; color:#888; padding:40px; }
.back-link { display:inline-block; margin-bottom:12px; color:#005B99; text-decoration:none; font-weight:600; font-size:0.9em; }
.refresh-btn { background:#005B99; color:white; border:none; padding:8px 18px; border-radius:6px; font-weight:600; cursor:pointer; font-size:0.88em; }
.actions { display:flex; gap:8px; justify-content:flex-end; align-items:center; margin-top:10px; flex-wrap:wrap; }
</style>
</head>
<body>
<div class="container">
<a href="my-page.php" class="back-link">← <?php echo $sv ? 'Tillbaka' : 'Back'; ?></a>
<h1>📋 <?php echo $sv ? 'Pågående matcher' : 'Active games'; ?></h1>

<div style="text-align:center;margin-bottom:16px;">
    <button class="refresh-btn" onclick="location.reload()">🔄 <?php echo $sv ? 'Uppdatera' : 'Refresh'; ?></button>
</div>

<?php if (!$tablesExist): ?>
    <div class="no-tables"><?php echo $sv ? 'Inga pågående matcher att visa.' : 'No active games to show.'; ?></div>
<?php elseif (empty($sessions)): ?>
    <div class="empty"><?php echo $sv ? 'Inga pågående matcher hittades.' : 'No active games found.'; ?></div>
<?php else: ?>
    <?php foreach ($sessions as $s):
        $did = $s['draft_id'];
        $players = json_decode($s['players_json'], true) ?: [];
        $playerNames = array_map(fn($p) => $p['name'] ?? '?', $players);
        $isActive = $s['status'] === 'active';

        // Check if current user is in this match
        $playerInMatch = false;
        foreach ($players as $p) {
            if (($p['vms'] ?? '') === $currentVms) { $playerInMatch = true; break; }
        }

        // Load hands
        $hstmt = $conn->prepare("SELECT * FROM stat_live_hands WHERE draft_id=? ORDER BY hand_number");
        $hstmt->execute([$did]);
        $draftHands = $hstmt->fetchAll(PDO::FETCH_ASSOC);

        // Load penalties
        $pstmt = $conn->prepare("SELECT * FROM stat_live_penalties WHERE draft_id=? ORDER BY penalty_index");
        $pstmt->execute([$did]);
        $draftPenalties = $pstmt->fetchAll(PDO::FETCH_ASSOC);

        $statusClass = 'badge-' . $s['status'];
        $statusLabel = $s['status'] === 'active' ? ($sv ? 'Aktiv' : 'Active') :
                      ($s['status'] === 'saved' ? ($sv ? 'Sparad' : 'Saved') :
                      ($sv ? 'Raderad' : 'Discarded'));

        // Who created it
        $creatorName = $s['created_by_id'] ?? '?';
        foreach ($players as $p) {
            if (($p['vms'] ?? '') === $s['created_by_id']) {
                $creatorName = explode(' ', $p['name'])[0];
                break;
            }
        }
    ?>
    <div class="card<?php echo $isActive ? ' active-card' : ''; ?>" id="card-<?php echo htmlspecialchars($did); ?>">
        <div class="card-header">
            <h3>🀄 <?php echo htmlspecialchars(implode(', ', $playerNames)); ?></h3>
            <span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
        </div>
        <div class="meta">
            <strong><?php echo $sv ? 'Registreras av' : 'Registered by'; ?>:</strong> <?php echo htmlspecialchars($creatorName); ?><br>
            <strong><?php echo $sv ? 'Datum' : 'Date'; ?>:</strong> <?php echo htmlspecialchars($s['game_date'] ?? ''); ?>
            <?php if ($s['game_name']): ?> — <em><?php echo htmlspecialchars($s['game_name']); ?></em><?php endif; ?><br>
            <strong><?php echo $sv ? 'Senast uppdaterad' : 'Last updated'; ?>:</strong> <?php echo htmlspecialchars($s['updated_at']); ?><br>
            <strong><?php echo $sv ? 'Händer' : 'Hands'; ?>:</strong> <?php echo count($draftHands); ?>
            <?php if (count($draftPenalties) > 0): ?> · <strong><?php echo $sv ? 'Straff' : 'Penalties'; ?>:</strong> <?php echo count($draftPenalties); ?><?php endif; ?>
            <?php if ($is_admin): ?><br><strong>Draft ID:</strong> <code style="font-size:0.85em;background:#f5f5f5;padding:1px 4px;border-radius:3px;"><?php echo htmlspecialchars(substr($did, 0, 12)); ?>…</code><?php endif; ?>
        </div>

        <?php if (!empty($draftHands) || !empty($draftPenalties)): ?>
        <table class="hands-table">
            <tr>
                <th>#</th>
                <th><?php echo $sv ? 'Poäng' : 'Points'; ?></th>
                <th><?php echo $sv ? 'Vinnare' : 'Winner'; ?></th>
                <th><?php echo $sv ? 'Från' : 'From'; ?></th>
            </tr>
            <?php foreach ($draftHands as $h):
                $winnerName = isset($players[(int)$h['winner']]) ? $players[(int)$h['winner']]['name'] : '?';
                $fromVal = (int)$h['from_player'];
                if ($fromVal === -1) { $fromLabel = $sv ? 'Självdragen' : 'Self-drawn'; }
                elseif ($fromVal === -2) { $fromLabel = $sv ? 'Oavgjord' : 'Draw'; }
                else { $fromLabel = isset($players[$fromVal]) ? $players[$fromVal]['name'] : '?'; }
            ?>
            <tr>
                <td><?php echo $h['hand_number']; ?></td>
                <td><?php echo $h['points']; ?></td>
                <td><?php echo htmlspecialchars(explode(' ', $winnerName)[0]); ?></td>
                <td><?php echo htmlspecialchars(explode(' ', $fromLabel)[0]); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php foreach ($draftPenalties as $p):
                $penName = isset($players[(int)$p['player_idx']]) ? $players[(int)$p['player_idx']]['name'] : '?';
            ?>
            <tr class="penalty-row">
                <td>⚠</td>
                <td>-<?php echo $p['amount']; ?></td>
                <td colspan="2"><?php echo htmlspecialchars(explode(' ', $penName)[0]); ?> <?php echo $p['distribute'] ? '(±)' : '(−)'; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <?php if ($isActive && ($playerInMatch || $is_admin)): ?>
        <button class="btn-takeover" onclick="takeOver('<?php echo htmlspecialchars($did); ?>')">
            📱 <?php echo $sv ? 'Ta över registrering' : 'Take over registration'; ?>
        </button>
        <?php endif; ?>

        <?php if ($is_admin): ?>
        <div class="actions">
            <button class="btn-del" onclick="deleteDraft('<?php echo htmlspecialchars($did); ?>')">🗑 <?php echo $sv ? 'Radera' : 'Delete'; ?></button>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

</div>

<script>
function takeOver(draftId) {
    const msg = <?php echo json_encode($sv
        ? 'Vill du ta över registreringen av denna match? Matchen laddas med alla händer som redan registrerats.'
        : 'Do you want to take over registration of this game? The game will load with all hands already registered.'); ?>;
    if (!confirm(msg)) return;

    // Store the draft_id in localStorage so mobile-draft.php picks it up
    try {
        window.localStorage.removeItem('mvms_autosave');
        window.localStorage.setItem('mvms_takeover', draftId);
    } catch(e) {}

    window.location.href = 'mobile-draft.php';
}

async function deleteDraft(draftId) {
    if (!confirm(<?php echo json_encode($sv ? 'Radera denna draft permanent?' : 'Delete this draft permanently?'); ?>)) return;
    try {
        const r = await fetch('draft-monitor.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'delete_draft', draft_id: draftId })
        });
        const d = await r.json();
        if (d.success) {
            document.getElementById('card-' + draftId).style.display = 'none';
        } else { alert(d.error || 'Error'); }
    } catch(e) { alert(e.message); }
}
</script>
</body>
</html>
