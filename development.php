<?php
/**
 * development.php
 * Development page — manage to-do list for system development.
 * Visible only for admin and mainadmin.
 *
 * Upload: www/development.php
 */
require_once 'config.php';

if (!hasRole('admin')) {
    showError(t('dev_err_title'));
}

$conn = getDbConnection();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_task'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($title === '') {
            $error = t('dev_err_title');
        } else {
            $stmt = $conn->query("SELECT COALESCE(MAX(priority), 0) + 1 as next_p FROM stat_dev_tasks WHERE status = 'todo'");
            $next_p = $stmt->fetch()['next_p'];
            $stmt = $conn->prepare("INSERT INTO stat_dev_tasks (title, description, status, priority) VALUES (?, ?, 'todo', ?)");
            $stmt->execute([$title, $description ?: null, $next_p]);
            $success = t('dev_msg_added');
        }
    }

    if (isset($_POST['complete_task'])) {
        $id = (int)$_POST['task_id'];
        $conn->prepare("UPDATE stat_dev_tasks SET status = 'done', completed_at = NOW() WHERE id = ?")->execute([$id]);
        $success = t('dev_msg_completed');
    }

    if (isset($_POST['reopen_task'])) {
        $id = (int)$_POST['task_id'];
        $stmt = $conn->query("SELECT COALESCE(MAX(priority), 0) + 1 as next_p FROM stat_dev_tasks WHERE status = 'todo'");
        $next_p = $stmt->fetch()['next_p'];
        $conn->prepare("UPDATE stat_dev_tasks SET status = 'todo', completed_at = NULL, priority = ? WHERE id = ?")->execute([$next_p, $id]);
        $success = t('dev_msg_reopened');
    }

    if (isset($_POST['update_task'])) {
        $id = (int)$_POST['task_id'];
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $completed_date = trim($_POST['completed_date'] ?? '');
        $created_date = trim($_POST['created_date'] ?? '');
        if ($title === '') { $error = t('dev_err_title'); }
        else {
            $sql = "UPDATE stat_dev_tasks SET title = ?, description = ?";
            $params = [$title, $description ?: null];
            if ($created_date !== '') {
                $sql .= ", created_at = ?";
                $params[] = $created_date . ' 12:00:00';
            }
            if ($completed_date !== '') {
                $sql .= ", completed_at = ?";
                $params[] = $completed_date . ' 12:00:00';
            }
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $conn->prepare($sql)->execute($params);
            $success = t('dev_msg_updated');
        }
    }

    if (isset($_POST['delete_task'])) {
        $conn->prepare("DELETE FROM stat_dev_tasks WHERE id = ?")->execute([(int)$_POST['task_id']]);
        $success = t('dev_msg_deleted');
    }

    if (isset($_POST['split_task'])) {
        $id = (int)$_POST['task_id'];
        $t1 = trim($_POST['split_title_1'] ?? ''); $t2 = trim($_POST['split_title_2'] ?? '');
        $d1 = trim($_POST['split_desc_1'] ?? '');  $d2 = trim($_POST['split_desc_2'] ?? '');
        if ($t1 === '' || $t2 === '') { $error = t('dev_err_split'); }
        else {
            $orig = $conn->prepare("SELECT * FROM stat_dev_tasks WHERE id = ?"); $orig->execute([$id]); $orig = $orig->fetch();
            if ($orig) {
                $conn->beginTransaction();
                $conn->prepare("UPDATE stat_dev_tasks SET title = ?, description = ? WHERE id = ?")->execute([$t1, $d1 ?: null, $id]);
                $conn->prepare("INSERT INTO stat_dev_tasks (title, description, status, priority, completed_at) VALUES (?, ?, ?, ?, ?)")->execute([$t2, $d2 ?: null, $orig['status'], $orig['priority'] + 1, $orig['completed_at']]);
                $conn->commit();
                $success = t('dev_msg_split');
            }
        }
    }

    if (isset($_POST['move_up']) || isset($_POST['move_down'])) {
        $id = (int)$_POST['task_id']; $dir = isset($_POST['move_up']) ? 'up' : 'down';
        $task = $conn->prepare("SELECT id, priority FROM stat_dev_tasks WHERE id = ?"); $task->execute([$id]); $task = $task->fetch();
        if ($task) {
            $sql = $dir === 'up'
                ? "SELECT id, priority FROM stat_dev_tasks WHERE status = 'todo' AND priority < ? ORDER BY priority DESC LIMIT 1"
                : "SELECT id, priority FROM stat_dev_tasks WHERE status = 'todo' AND priority > ? ORDER BY priority ASC LIMIT 1";
            $swap = $conn->prepare($sql); $swap->execute([$task['priority']]); $swap = $swap->fetch();
            if ($swap) {
                $conn->beginTransaction();
                $conn->prepare("UPDATE stat_dev_tasks SET priority = ? WHERE id = ?")->execute([$swap['priority'], $task['id']]);
                $conn->prepare("UPDATE stat_dev_tasks SET priority = ? WHERE id = ?")->execute([$task['priority'], $swap['id']]);
                $conn->commit();
            }
        }
    }

    if (!$error) { header('Location: development.php' . ($success ? '?msg=' . urlencode($success) : '')); exit; }
}

if (isset($_GET['msg'])) $success = $_GET['msg'];

$todo_tasks = $conn->query("SELECT * FROM stat_dev_tasks WHERE status = 'todo' ORDER BY priority ASC")->fetchAll(PDO::FETCH_ASSOC);
$done_tasks = $conn->query("SELECT * FROM stat_dev_tasks WHERE status = 'done' ORDER BY completed_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = t('dev_title');
include 'includes/header.php';
?>

<h2>🛠️ <?php echo t('dev_title'); ?></h2>
<p style="color:#666;margin-bottom:24px;"><strong><?php echo count($todo_tasks); ?></strong> <?php echo t('dev_subtitle_todo'); ?>, <strong><?php echo count($done_tasks); ?></strong> <?php echo t('dev_subtitle_done'); ?>.</p>

<?php if ($success): ?><div class="message success" style="background:#e8f5e9;border:1px solid #4CAF50;color:#2e7d32;padding:12px 20px;border-radius:6px;margin-bottom:20px;">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error" style="background:#ffebee;border:1px solid #f44336;color:#c62828;padding:12px 20px;border-radius:6px;margin-bottom:20px;">❌ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<!-- ADD -->
<div style="background:#e3f2fd;padding:20px;border-radius:8px;border-left:4px solid #1976D2;margin-bottom:30px;">
    <h3 style="margin-top:0;">➕ <?php echo t('dev_add_title'); ?></h3>
    <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:1;min-width:250px;">
            <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.9em;"><?php echo t('dev_label_title'); ?></label>
            <input type="text" name="title" required placeholder="<?php echo t('dev_placeholder_title'); ?>" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;font-size:1em;">
        </div>
        <div style="flex:1;min-width:250px;">
            <label style="display:block;font-weight:600;margin-bottom:4px;font-size:0.9em;"><?php echo t('dev_label_desc'); ?></label>
            <input type="text" name="description" placeholder="<?php echo t('dev_placeholder_desc'); ?>" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;font-size:1em;">
        </div>
        <button type="submit" name="add_task" style="padding:10px 24px;background:#1976D2;color:white;border:none;border-radius:6px;font-size:1em;cursor:pointer;white-space:nowrap;">➕ <?php echo t('dev_btn_add'); ?></button>
    </form>
</div>

<!-- TODO -->
<div style="background:white;padding:25px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-bottom:30px;">
    <h3 style="margin-top:0;color:#e65100;">📋 <?php echo t('dev_section_todo'); ?> (<?php echo count($todo_tasks); ?>)</h3>
    <?php if (empty($todo_tasks)): ?>
        <p style="color:#999;font-style:italic;"><?php echo t('dev_no_todo'); ?></p>
    <?php else: foreach ($todo_tasks as $i => $task): ?>
    <div style="border:1px solid #e0e0e0;border-left:4px solid #FF9800;border-radius:6px;padding:16px;margin-bottom:12px;background:#fafafa;">
        <div class="task-view" id="view-<?php echo $task['id']; ?>">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:1.05em;color:#333;"><?php echo htmlspecialchars($task['title']); ?></div>
                    <?php if ($task['description']): ?><div style="color:#666;font-size:0.9em;margin-top:4px;"><?php echo nl2br(htmlspecialchars($task['description'])); ?></div><?php endif; ?>
                    <div style="color:#999;font-size:0.78em;margin-top:6px;"><?php echo t('dev_added'); ?>: <?php echo date('Y-m-d', strtotime($task['created_at'])); ?></div>
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                    <?php if ($i > 0): ?><form method="POST" style="display:inline;"><input type="hidden" name="task_id" value="<?php echo $task['id']; ?>"><button type="submit" name="move_up" title="<?php echo t('dev_btn_move_up'); ?>" style="padding:4px 8px;background:#f5f5f5;border:1px solid #ccc;border-radius:4px;cursor:pointer;">⬆️</button></form><?php endif; ?>
                    <?php if ($i < count($todo_tasks) - 1): ?><form method="POST" style="display:inline;"><input type="hidden" name="task_id" value="<?php echo $task['id']; ?>"><button type="submit" name="move_down" title="<?php echo t('dev_btn_move_down'); ?>" style="padding:4px 8px;background:#f5f5f5;border:1px solid #ccc;border-radius:4px;cursor:pointer;">⬇️</button></form><?php endif; ?>
                    <form method="POST" style="display:inline;"><input type="hidden" name="task_id" value="<?php echo $task['id']; ?>"><button type="submit" name="complete_task" style="padding:4px 12px;background:#4CAF50;color:white;border:none;border-radius:4px;cursor:pointer;font-size:0.9em;">✅ <?php echo t('dev_btn_done'); ?></button></form>
                    <button onclick="toggleEdit(<?php echo $task['id']; ?>)" title="<?php echo t('dev_btn_edit'); ?>" style="padding:4px 8px;background:#f5f5f5;border:1px solid #ccc;border-radius:4px;cursor:pointer;">✏️</button>
                    <button onclick="toggleSplit(<?php echo $task['id']; ?>)" title="<?php echo t('dev_btn_split'); ?>" style="padding:4px 8px;background:#f5f5f5;border:1px solid #ccc;border-radius:4px;cursor:pointer;">✂️</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo t('dev_confirm_delete'); ?>');"><input type="hidden" name="task_id" value="<?php echo $task['id']; ?>"><button type="submit" name="delete_task" title="<?php echo t('dev_btn_delete'); ?>" style="padding:4px 8px;background:#ffebee;border:1px solid #ef9a9a;border-radius:4px;cursor:pointer;">🗑️</button></form>
                </div>
            </div>
        </div>
        <div class="task-edit" id="edit-<?php echo $task['id']; ?>" style="display:none;">
            <form method="POST"><input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                <div style="margin-bottom:10px;"><label style="font-weight:600;font-size:0.9em;"><?php echo t('dev_label_title'); ?></label><input type="text" name="title" value="<?php echo htmlspecialchars($task['title']); ?>" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;font-size:1em;margin-top:4px;"></div>
                <div style="margin-bottom:10px;"><label style="font-weight:600;font-size:0.9em;"><?php echo t('dev_label_desc'); ?></label><textarea name="description" rows="3" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;font-size:1em;margin-top:4px;"><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea></div>
                <div style="margin-bottom:10px;"><label style="font-weight:600;font-size:0.9em;"><?php echo t('dev_added'); ?>:</label> <input type="date" name="created_date" value="<?php echo date('Y-m-d', strtotime($task['created_at'])); ?>" style="padding:8px;border:1px solid #ccc;border-radius:4px;font-size:1em;margin-top:4px;"></div>
                <div style="display:flex;gap:8px;"><button type="submit" name="update_task" style="padding:8px 20px;background:#1976D2;color:white;border:none;border-radius:4px;cursor:pointer;">💾 <?php echo t('dev_btn_save'); ?></button><button type="button" onclick="toggleEdit(<?php echo $task['id']; ?>)" style="padding:8px 20px;background:#f5f5f5;border:1px solid #ccc;border-radius:4px;cursor:pointer;"><?php echo t('dev_btn_cancel'); ?></button></div>
            </form>
        </div>
        <div class="task-split" id="split-<?php echo $task['id']; ?>" style="display:none;">
            <form method="POST"><input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                <p style="color:#666;font-size:0.9em;margin-bottom:12px;"><?php echo sprintf(t('dev_split_intro'), htmlspecialchars($task['title'])); ?></p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:12px;">
                    <div><label style="font-weight:600;font-size:0.9em;"><?php echo t('dev_split_part1'); ?></label><input type="text" name="split_title_1" value="<?php echo htmlspecialchars($task['title']); ?>" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;margin-top:4px;"><textarea name="split_desc_1" rows="2" placeholder="<?php echo t('dev_label_desc'); ?>" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;margin-top:6px;"><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea></div>
                    <div><label style="font-weight:600;font-size:0.9em;"><?php echo t('dev_split_part2'); ?></label><input type="text" name="split_title_2" required placeholder="<?php echo t('dev_split_new'); ?>" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;margin-top:4px;"><textarea name="split_desc_2" rows="2" placeholder="<?php echo t('dev_label_desc'); ?>" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;margin-top:6px;"></textarea></div>
                </div>
                <div style="display:flex;gap:8px;"><button type="submit" name="split_task" style="padding:8px 20px;background:#FF9800;color:white;border:none;border-radius:4px;cursor:pointer;">✂️ <?php echo t('dev_btn_split'); ?></button><button type="button" onclick="toggleSplit(<?php echo $task['id']; ?>)" style="padding:8px 20px;background:#f5f5f5;border:1px solid #ccc;border-radius:4px;cursor:pointer;"><?php echo t('dev_btn_cancel'); ?></button></div>
            </form>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- DONE -->
<div style="background:white;padding:25px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-bottom:30px;">
    <h3 style="margin-top:0;color:#2e7d32;">✅ <?php echo t('dev_section_done'); ?> (<?php echo count($done_tasks); ?>)</h3>
    <?php if (empty($done_tasks)): ?>
        <p style="color:#999;font-style:italic;"><?php echo t('dev_no_done'); ?></p>
    <?php else: foreach ($done_tasks as $task): ?>
    <div style="border:1px solid #e0e0e0;border-left:4px solid #4CAF50;border-radius:6px;padding:14px 16px;margin-bottom:8px;background:#f9fdf9;">
        <div class="task-view" id="view-<?php echo $task['id']; ?>">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                <div style="flex:1;">
                    <div style="font-weight:600;color:#555;"><?php echo htmlspecialchars($task['title']); ?></div>
                    <?php if ($task['description']): ?><div style="color:#888;font-size:0.85em;margin-top:3px;"><?php echo nl2br(htmlspecialchars($task['description'])); ?></div><?php endif; ?>
                    <div style="color:#4CAF50;font-size:0.78em;margin-top:4px;font-weight:600;"><?php echo t('dev_completed'); ?>: <?php echo date('Y-m-d', strtotime($task['completed_at'])); ?></div>
                </div>
                <div style="display:flex;gap:6px;align-items:center;">
                    <form method="POST" style="display:inline;"><input type="hidden" name="task_id" value="<?php echo $task['id']; ?>"><button type="submit" name="reopen_task" title="<?php echo t('dev_undo_tooltip'); ?>" style="padding:4px 10px;background:#fff3e0;border:1px solid #FFB74D;border-radius:4px;cursor:pointer;font-size:0.85em;">↩️ <?php echo t('dev_btn_undo'); ?></button></form>
                    <button onclick="toggleEdit(<?php echo $task['id']; ?>)" title="<?php echo t('dev_btn_edit'); ?>" style="padding:4px 8px;background:#f5f5f5;border:1px solid #ccc;border-radius:4px;cursor:pointer;">✏️</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo t('dev_confirm_delete'); ?>');"><input type="hidden" name="task_id" value="<?php echo $task['id']; ?>"><button type="submit" name="delete_task" title="<?php echo t('dev_btn_delete'); ?>" style="padding:4px 8px;background:#ffebee;border:1px solid #ef9a9a;border-radius:4px;cursor:pointer;">🗑️</button></form>
                </div>
            </div>
        </div>
        <div class="task-edit" id="edit-<?php echo $task['id']; ?>" style="display:none;">
            <form method="POST"><input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                <div style="margin-bottom:10px;"><label style="font-weight:600;font-size:0.9em;"><?php echo t('dev_label_title'); ?></label><input type="text" name="title" value="<?php echo htmlspecialchars($task['title']); ?>" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;font-size:1em;margin-top:4px;"></div>
                <div style="margin-bottom:10px;"><label style="font-weight:600;font-size:0.9em;"><?php echo t('dev_label_desc'); ?></label><textarea name="description" rows="3" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;font-size:1em;margin-top:4px;"><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea></div>
                <div style="margin-bottom:10px;display:flex;gap:20px;flex-wrap:wrap;">
                    <div><label style="font-weight:600;font-size:0.9em;"><?php echo t('dev_added'); ?>:</label> <input type="date" name="created_date" value="<?php echo date('Y-m-d', strtotime($task['created_at'])); ?>" style="padding:8px;border:1px solid #ccc;border-radius:4px;font-size:1em;margin-top:4px;"></div>
                    <div><label style="font-weight:600;font-size:0.9em;"><?php echo t('dev_completed'); ?>:</label> <input type="date" name="completed_date" value="<?php echo date('Y-m-d', strtotime($task['completed_at'])); ?>" style="padding:8px;border:1px solid #ccc;border-radius:4px;font-size:1em;margin-top:4px;"></div>
                </div>
                <div style="display:flex;gap:8px;"><button type="submit" name="update_task" style="padding:8px 20px;background:#1976D2;color:white;border:none;border-radius:4px;cursor:pointer;">💾 <?php echo t('dev_btn_save'); ?></button><button type="button" onclick="toggleEdit(<?php echo $task['id']; ?>)" style="padding:8px 20px;background:#f5f5f5;border:1px solid #ccc;border-radius:4px;cursor:pointer;"><?php echo t('dev_btn_cancel'); ?></button></div>
            </form>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<script>
function toggleEdit(id) {
    var v = document.getElementById('view-' + id), e = document.getElementById('edit-' + id), s = document.getElementById('split-' + id);
    if (e.style.display === 'none') { v.style.display = 'none'; e.style.display = 'block'; if (s) s.style.display = 'none'; }
    else { v.style.display = 'block'; e.style.display = 'none'; }
}
function toggleSplit(id) {
    var v = document.getElementById('view-' + id), e = document.getElementById('edit-' + id), s = document.getElementById('split-' + id);
    if (s.style.display === 'none') { v.style.display = 'none'; e.style.display = 'none'; s.style.display = 'block'; }
    else { v.style.display = 'block'; s.style.display = 'none'; }
}
</script>

<?php include 'includes/footer.php'; ?>
