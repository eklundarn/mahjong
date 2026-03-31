<?php
/**
 * login-log.php
 * Login log page — view all login attempts.
 * Only accessible by mainadmin.
 *
 * Upload: www/login-log.php
 */
require_once 'config.php';

if (($_SESSION['role'] ?? '') !== 'mainadmin') {
    showError('Endast huvudadmin har åtkomst till denna sida.');
}

$conn = getDbConnection();

$per_page = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$filter = $_GET['filter'] ?? 'all';
$where = '';
if ($filter === 'success') $where = 'WHERE l.success = 1';
elseif ($filter === 'failed') $where = 'WHERE l.success = 0';

$total = $conn->query("SELECT COUNT(*) as total FROM stat_login_log l $where")->fetch()['total'];
$total_pages = max(1, ceil($total / $per_page));

$stmt = $conn->prepare("
    SELECT l.*, p.first_name, p.last_name, p.id
    FROM stat_login_log l
    LEFT JOIN stat_players p ON p.id = l.player_id
    $where
    ORDER BY l.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats_today = $conn->query("SELECT COUNT(*) as c FROM stat_login_log WHERE DATE(created_at) = CURDATE() AND success = 1")->fetch()['c'];
$stats_failed_today = $conn->query("SELECT COUNT(*) as c FROM stat_login_log WHERE DATE(created_at) = CURDATE() AND success = 0")->fetch()['c'];
$stats_total = $conn->query("SELECT COUNT(*) as c FROM stat_login_log")->fetch()['c'];

$page_title = t('loginlog_title');
include 'includes/header.php';
?>

<h2>🔐 <?php echo t('loginlog_title'); ?></h2>

<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">
    <div style="background:#e8f5e9;padding:12px 20px;border-radius:8px;text-align:center;">
        <div style="font-size:1.6em;font-weight:700;color:#1b5e20;"><?php echo $stats_today; ?></div>
        <div style="font-size:0.8em;color:#2e7d32;"><?php echo t('loginlog_today_ok'); ?></div>
    </div>
    <div style="background:#ffebee;padding:12px 20px;border-radius:8px;text-align:center;">
        <div style="font-size:1.6em;font-weight:700;color:#b71c1c;"><?php echo $stats_failed_today; ?></div>
        <div style="font-size:0.8em;color:#c62828;"><?php echo t('loginlog_today_fail'); ?></div>
    </div>
    <div style="background:#e3f2fd;padding:12px 20px;border-radius:8px;text-align:center;">
        <div style="font-size:1.6em;font-weight:700;color:#005B99;"><?php echo $stats_total; ?></div>
        <div style="font-size:0.8em;color:#005B99;"><?php echo t('loginlog_total'); ?></div>
    </div>
</div>

<div style="margin-bottom:16px;display:flex;gap:8px;">
    <a href="?filter=all" style="padding:6px 14px;border-radius:6px;text-decoration:none;font-size:0.9em;<?php echo $filter === 'all' ? 'background:#005B99;color:white;' : 'background:#e0e0e0;color:#333;'; ?>"><?php echo t('loginlog_filter_all'); ?></a>
    <a href="?filter=success" style="padding:6px 14px;border-radius:6px;text-decoration:none;font-size:0.9em;<?php echo $filter === 'success' ? 'background:#1b5e20;color:white;' : 'background:#e0e0e0;color:#333;'; ?>">✅ <?php echo t('loginlog_filter_ok'); ?></a>
    <a href="?filter=failed" style="padding:6px 14px;border-radius:6px;text-decoration:none;font-size:0.9em;<?php echo $filter === 'failed' ? 'background:#b71c1c;color:white;' : 'background:#e0e0e0;color:#333;'; ?>">❌ <?php echo t('loginlog_filter_fail'); ?></a>
</div>

<div style="background:white;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.9em;">
        <thead>
            <tr style="border-bottom:2px solid #005B99;text-align:left;">
                <th style="padding:10px 12px;"><?php echo t('loginlog_date'); ?></th>
                <th style="padding:10px 12px;"><?php echo t('loginlog_time'); ?></th>
                <th style="padding:10px 12px;"><?php echo t('loginlog_status'); ?></th>
                <th style="padding:10px 12px;"><?php echo t('loginlog_user'); ?></th>
                <th style="padding:10px 12px;"><?php echo t('loginlog_attempted'); ?></th>
                <th style="padding:10px 12px;">IP</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($logs)): ?>
            <tr><td colspan="6" style="padding:20px;text-align:center;color:#999;"><?php echo t('loginlog_empty'); ?></td></tr>
        <?php else: foreach ($logs as $log): ?>
            <tr style="border-bottom:1px solid #f0f0f0;<?php echo $log['success'] ? '' : 'background:#fff8f8;'; ?>">
                <td style="padding:8px 12px;white-space:nowrap;"><?php echo date('Y-m-d', strtotime($log['created_at'])); ?></td>
                <td style="padding:8px 12px;white-space:nowrap;font-family:monospace;"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></td>
                <td style="padding:8px 12px;"><?php echo $log['success'] ? '<span style="color:#1b5e20;font-weight:600;">✅</span>' : '<span style="color:#b71c1c;font-weight:600;">❌</span>'; ?></td>
                <td style="padding:8px 12px;">
                    <?php if ($log['player_id'] && $log['first_name']): ?>
                        <strong><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></strong>
                        <span style="color:#888;font-size:0.85em;">(<?php echo htmlspecialchars($log['id']); ?>)</span>
                    <?php elseif ($log['player_id']): ?>
                        <span style="color:#888;">ID #<?php echo $log['player_id']; ?></span>
                    <?php else: ?>
                        <span style="color:#999;">—</span>
                    <?php endif; ?>
                </td>
                <td style="padding:8px 12px;font-family:monospace;font-size:0.88em;color:#555;"><?php echo htmlspecialchars($log['attempted_username']); ?></td>
                <td style="padding:8px 12px;font-family:monospace;font-size:0.82em;color:#888;"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if ($total_pages > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:16px;flex-wrap:wrap;">
    <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>" style="padding:6px 12px;background:#e0e0e0;border-radius:4px;text-decoration:none;color:#333;">←</a>
    <?php endif; ?>
    <?php for ($p = max(1, $page - 3); $p <= min($total_pages, $page + 3); $p++): ?>
        <a href="?page=<?php echo $p; ?>&filter=<?php echo $filter; ?>" style="padding:6px 12px;border-radius:4px;text-decoration:none;<?php echo $p === $page ? 'background:#005B99;color:white;font-weight:700;' : 'background:#e0e0e0;color:#333;'; ?>"><?php echo $p; ?></a>
    <?php endfor; ?>
    <?php if ($page < $total_pages): ?>
        <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>" style="padding:6px 12px;background:#e0e0e0;border-radius:4px;text-decoration:none;color:#333;">→</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
