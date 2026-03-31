<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

// Kräv superuser eller admin
if (!hasRole('superuser')) {
    showError(t('players_all_error_access'));
}

$success = '';
$error = '';
$action = isset($_GET['action']) ? cleanInput($_GET['action']) : 'list';
$player_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$conn = getDbConnection();

// HANTERA FORMULÄRINLÄMNINGAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // LÄGG TILL NY SPELARE
    if (isset($_POST['add_player'])) {
        $first_name = cleanInput($_POST['first_name']);
        $last_name = cleanInput($_POST['last_name']);
        $city = cleanInput($_POST['city']);
        $club = cleanInput($_POST['club']);
        $ema_number = cleanInput($_POST['ema_number']);
        $country_code = strtoupper(cleanInput($_POST['country_code'] ?? 'SE')) ?: 'SE';
        $email = cleanInput($_POST['email'] ?? '');
        $visible = isset($_POST['visible_in_stats']) ? 1 : 0;
        $club_member = isset($_POST['club_member']) ? 1 : 0;
        
        // Validering
        if (empty($first_name) || empty($last_name)) {
            $error = t('players_all_error_name_required');
        } else {
            try {
                // Generera ett username baserat på namn
                $base_username = strtolower(substr($first_name, 0, 2) . substr($last_name, 0, 4));
                $base_username = preg_replace('/[^a-z0-9]/', '', $base_username);
                $username = $base_username;
                
                // Säkerställ unikt username
                $counter = 1;
                while (true) {
                    $check = $conn->prepare("SELECT id FROM stat_players WHERE username = ?");
                    $check->execute([$username]);
                    if (!$check->fetch()) break;
                    $username = $base_username . $counter;
                    $counter++;
                }
                
                // Nya spelare får automatiskt läsbehörighet
                $role = 'reader';
                
                $stmt = $conn->prepare("
                    INSERT INTO stat_players (username, first_name, last_name, email, city, club, ema_number, country_code, visible_in_stats, club_member, role, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$username, $first_name, $last_name, $email ?: null, $city, $club, $ema_number, $country_code, $visible, $club_member, $role, $_SESSION['user_id']]);
                $new_id = $conn->lastInsertId();
                $success = sprintf(t('players_all_success_added'), $first_name, $last_name, $new_id);
                $action = 'list';
            } catch (PDOException $e) {
                $error = t('players_all_error_add') . ': ' . $e->getMessage();
            }
        }
    }
    
    // UPPDATERA SPELARE
    if (isset($_POST['update_player'])) {
        $id = (int)$_POST['id'];
        $first_name = cleanInput($_POST['first_name']);
        $last_name = cleanInput($_POST['last_name']);
        $city = cleanInput($_POST['city']);
        $club = cleanInput($_POST['club']);
        $ema_number = cleanInput($_POST['ema_number']);
        $country_code = strtoupper(cleanInput($_POST['country_code'] ?? 'SE')) ?: 'SE';
        $email = cleanInput($_POST['email'] ?? '');
        $visible = isset($_POST['visible_in_stats']) ? 1 : 0;
        $club_member = isset($_POST['club_member']) ? 1 : 0;

        if (empty($first_name) || empty($last_name)) {
            $error = t('players_all_error_name_required');
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE stat_players 
                    SET first_name = ?, last_name = ?, email = ?, city = ?, club = ?, ema_number = ?, country_code = ?, visible_in_stats = ?, club_member = ?, last_edited_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$first_name, $last_name, $email ?: null, $city, $club, $ema_number, $country_code, $visible, $club_member, $_SESSION['user_id'], $id]);
                $success = sprintf(t('players_all_success_updated'), $first_name, $last_name);
                $action = 'list';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = sprintf(t('players_all_error_vms_taken'), $player_id);
                } else {
                    $error = "Kunde inte uppdatera spelare: " . $e->getMessage();
                }
            }
        }
    }
    
    // RADERA SPELARE
    if (isset($_POST['delete_player'])) {
        $id = (int)$_POST['id'];
        
        try {
            // Kolla om spelaren har några matcher
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count FROM stat_games 
                WHERE player_a_id = (SELECT id FROM stat_players WHERE id = ?)
                   OR player_b_id = (SELECT id FROM stat_players WHERE id = ?)
                   OR player_c_id = (SELECT id FROM stat_players WHERE id = ?)
                   OR player_d_id = (SELECT id FROM stat_players WHERE id = ?)
            ");
            $stmt->execute([$id, $id, $id, $id]);
            $game_count = $stmt->fetch()['count'];
            
            if ($game_count > 0) {
                $error = sprintf(t('players_all_error_delete_games'), $game_count);
            } else {
                $stmt = $conn->prepare("DELETE FROM stat_players WHERE id = ?");
                $stmt->execute([$id]);
                $success = t('players_all_success_deleted');
                $action = 'list';
            }
        } catch (PDOException $e) {
            $error = "Kunde inte radera spelare: " . $e->getMessage();
        }
    }
}

// HÄMTA SPELARE FÖR REDIGERING
$edit_player = null;
if ($action === 'edit' && $player_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM stat_players WHERE id = ?");
    $stmt->execute([$player_id]);
    $edit_player = $stmt->fetch();
    
    if (!$edit_player) {
        $error = "Spelare hittades inte.";
        $action = 'list';
    }
}

// HÄMTA ALLA SPELARE FÖR LISTAN
$filter = 'vms';

// Sortering
$sort = $_GET['sort'] ?? 'name';
$order = $_GET['order'] ?? 'asc';
$next_order = ($order === 'asc') ? 'desc' : 'asc';

// Bygg ORDER BY
$order_by = match($sort) {
    'id' => "id",
    'first' => "first_name",
    'last' => "last_name", 
    'city' => "city",
    'club' => "club",
    'ema' => "ema_number",
    'visible' => "visible_in_stats",
    'club_member' => "club_member",
    'email' => "email",
    'country' => "country_code",
    default => "last_name, first_name"
};

$order_dir = strtoupper($order);

$where = "WHERE active = 1 AND id NOT IN (1, 2)";
$stmt = $conn->query("
    SELECT id, first_name, last_name, email, city, club, ema_number, country_code, visible_in_stats, club_member, 
           created_by, created_at, last_edited_by, updated_at
    FROM stat_players 
    $where
    ORDER BY $order_by $order_dir
");
$stat_players = $stmt->fetchAll();

includeHeader();
?>

<h2>Spelare</h2>

<?php if ($success): ?>
    <div class="message success">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="message error">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div style="margin: 20px 0;">
    <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn">➕ <?php echo t('players_all_btn_add'); ?></a>

    <?php else: ?>
        <a href="players-vms.php" class="btn btn-secondary">← <?php echo t('players_all_btn_back'); ?></a>
    <?php endif; ?>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <!-- FORMULÄR FÖR LÄGG TILL / REDIGERA -->
    <div style="max-width: 600px;">
        <h3><?php echo $action === 'add' ? t('players_all_form_add_title') : t('players_all_form_edit_title'); ?></h3>
        
        <form method="POST" action="players-all.php">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo $edit_player['id']; ?>">
            <?php endif; ?>
            
            <?php if ($action === 'edit'): ?>
            <div class="form-group">
                <label>ID</label>
                <p style="color:#888;margin:4px 0 8px;">#<?php echo $edit_player['id']; ?></p>
                    <small><?php echo t('players_all_vms_format_hint'); ?></small>
                <?php else: ?>
                    <input type="hidden" name="player_id" value="">
                    <p style="color:#888;margin:4px 0 8px;">— (ingen)</p>
                    <div style="background:#f0f7ff;padding:12px 16px;border-left:4px solid #1976d2;border-radius:4px;margin-top:6px;">
                        <label style="font-weight:600;">Ska nytt Spelar-ID skapas?</label><br>
                        <label style="font-weight:normal;margin-top:6px;display:inline-block;">
                            <input type="radio" name="create_vms" value="1"> Ja — skapa nytt Spelar-ID (lokal spelare som ska finnas i statistiken)
                        </label><br>
                        <label style="font-weight:normal;display:inline-block;">
                            <input type="radio" name="create_vms" value="0" checked> Nej — behåll utan Spelar-ID
                        </label>
                    </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="background: #e8f5e9; padding: 15px; border-left: 4px solid #4CAF50; margin-bottom: 20px; border-radius: 5px;">
                <p style="margin: 0;">
                    <strong>ℹ️ <?php echo t('players_all_vms_auto'); ?></strong>
                </p>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="first_name"><?php echo t('players_all_label_first'); ?></label>
                <input type="text" 
                       id="first_name" 
                       name="first_name" 
                       required
                       value="<?php echo $edit_player ? htmlspecialchars($edit_player['first_name']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="last_name"><?php echo t('players_all_label_last'); ?></label>
                <input type="text" 
                       id="last_name" 
                       name="last_name" 
                       required
                       value="<?php echo $edit_player ? htmlspecialchars($edit_player['last_name']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="city"><?php echo t('players_all_label_city'); ?></label>
                <input type="text" 
                       id="city" 
                       name="city"
                       value="<?php echo $edit_player ? htmlspecialchars($edit_player['city']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="club_select"><?php echo t('players_all_label_club'); ?></label>
                <?php
                $known_clubs = ['(redigera i players-vms.php)'];
                $current_club = isset($edit_player['club']) ? $edit_player['club'] : '';
                $is_custom = $current_club !== '' && !in_array($current_club, $known_clubs);
                ?>
                <select id="club_select" onchange="syncClub(this.value)">
                    <option value="" <?php echo $current_club==='' ? 'selected':''; ?>>— Ingen klubb</option>
                    <?php foreach ($known_clubs as $kc): ?>
                    <option value="<?php echo $kc; ?>" <?php echo $current_club===$kc ? 'selected':''; ?>><?php echo $kc; ?></option>
                    <?php endforeach; ?>
                    <option value="__custom__" <?php echo $is_custom ? 'selected':''; ?>>✏️ Ange fritext...</option>
                </select>
                <input type="text"
                       id="club_custom"
                       placeholder="Ange klubbnamn"
                       value="<?php echo $is_custom ? htmlspecialchars($current_club) : ''; ?>"
                       style="margin-top:6px;width:100%;box-sizing:border-box;<?php echo $is_custom ? '' : 'display:none;'; ?>"
                       oninput="document.getElementById('club_hidden').value=this.value">
                <input type="hidden" id="club_hidden" name="club" value="<?php echo htmlspecialchars($current_club); ?>">
                <script>
                function syncClub(val) {
                    var custom = document.getElementById('club_custom');
                    var hidden = document.getElementById('club_hidden');
                    if (val === '__custom__') {
                        custom.style.display = '';
                        hidden.value = custom.value;
                        custom.focus();
                    } else {
                        custom.style.display = 'none';
                        hidden.value = val;
                    }
                }
                </script>
            </div>
            
            <div class="form-group">
                <label for="ema_number"><?php echo t('players_all_label_ema'); ?></label>
                <input type="text" 
                       id="ema_number" 
                       name="ema_number"
                       value="<?php echo $edit_player ? htmlspecialchars($edit_player['ema_number']) : ''; ?>">
                <small><?php echo t('players_all_ema_hint'); ?></small>
            </div>

            <div class="form-group">
                <label for="country_code"><?php echo t('players_all_label_country'); ?></label>
                <select id="country_code" name="country_code">
                    <?php
                    $current_country = ($edit_player && !empty($edit_player['country_code'])) ? $edit_player['country_code'] : 'SE';
                    foreach (getCountriesForSelect() as $code => $name):
                        $flag = getCountryFlag($code);
                    ?>
                    <option value="<?php echo $code; ?>" <?php echo ($current_country === $code) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name) . ' (' . $code . ') ' . $flag; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="email">E-postadress</label>
                <input type="email" 
                       id="email" 
                       name="email"
                       value="<?php echo $edit_player ? htmlspecialchars($edit_player['email'] ?? '') : ''; ?>">
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" 
                           name="visible_in_stats" 
                           value="1"
                           <?php echo (!$edit_player || $edit_player['visible_in_stats']) ? 'checked' : ''; ?>>
                    <?php echo t('players_all_label_visible'); ?>
                </label>
                <small><?php echo t('players_all_visible_hint'); ?></small>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" 
                           name="club_member" 
                           value="1"
                           <?php echo (!$edit_player || $edit_player['club_member']) ? 'checked' : ''; ?>>
                    <?php echo t('players_all_label_club_member'); ?>
                </label>
                <small><?php echo t('players_all_club_member_hint'); ?></small>
            </div>
            
            <div class="form-group">
                <?php if ($action === 'add'): ?>
                    <button type="submit" name="add_player" class="btn"><?php echo t('players_all_btn_add_player'); ?></button>
                <?php else: ?>
                    <button type="submit" name="update_player" class="btn"><?php echo t('players_all_btn_save'); ?></button>
                <?php endif; ?>
                <a href="players-all.php" class="btn btn-secondary"><?php echo t('btn_cancel'); ?></a>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- LISTA MED ALLA SPELARE -->
    <h3>Alla spelare (<?php echo count($stat_players); ?> st)</h3>
    
    <?php if (empty($stat_players)): ?>
        <div class="message info">
            <p><?php echo t('players_none'); ?></p>
            <p><a href="?action=add" class="btn"><?php echo t('players_add_first'); ?></a></p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="stat_players-all-table">
                <thead>
                    <tr>
                        <th><a href="?sort=vms&order=<?php echo $next_order; ?>&filter=<?php echo $filter; ?>" style="color: inherit; text-decoration: none;"><?php echo t('players_th_vms'); ?> <?php if ($sort === 'vms') echo ($order === 'asc' ? '▲' : '▼'); ?></a></th>
                        <th><a href="?sort=first&order=<?php echo $next_order; ?>&filter=<?php echo $filter; ?>" style="color: inherit; text-decoration: none;"><?php echo t('players_th_first'); ?> <?php if ($sort === 'first') echo ($order === 'asc' ? '▲' : '▼'); ?></a></th>
                        <th><a href="?sort=last&order=<?php echo $next_order; ?>&filter=<?php echo $filter; ?>" style="color: inherit; text-decoration: none;"><?php echo t('players_th_last'); ?> <?php if ($sort === 'last') echo ($order === 'asc' ? '▲' : '▼'); ?></a></th>
                        <th><a href="?sort=city&order=<?php echo $next_order; ?>&filter=<?php echo $filter; ?>" style="color: inherit; text-decoration: none;"><?php echo t('players_th_city'); ?> <?php if ($sort === 'city') echo ($order === 'asc' ? '▲' : '▼'); ?></a></th>
                        <th><a href="?sort=club&order=<?php echo $next_order; ?>&filter=<?php echo $filter; ?>" style="color: inherit; text-decoration: none;"><?php echo t('players_th_club'); ?> <?php if ($sort === 'club') echo ($order === 'asc' ? '▲' : '▼'); ?></a></th>
                        <th><a href="?sort=ema&order=<?php echo $next_order; ?>&filter=<?php echo $filter; ?>" style="color: inherit; text-decoration: none;"><?php echo t('players_th_ema'); ?> <?php if ($sort === 'ema') echo ($order === 'asc' ? '▲' : '▼'); ?></a></th>
                        <th><a href="?sort=country&order=<?php echo $next_order; ?>&filter=<?php echo $filter; ?>" style="color: inherit; text-decoration: none;"><?php echo t('players_th_country'); ?> <?php if ($sort === 'country') echo ($order === 'asc' ? '▲' : '▼'); ?></a></th>
                        <th style="max-width:140px;"><a href="?sort=email&order=<?php echo $next_order; ?>&filter=<?php echo $filter; ?>" style="color: inherit; text-decoration: none;">E-post <?php if ($sort === 'email') echo ($order === 'asc' ? '▲' : '▼'); ?></a></th>
                        <th><?php echo t('players_th_actions'); ?></th>
                        <th><a href="?sort=club_member&order=<?php echo $next_order; ?>&filter=<?php echo $filter; ?>" style="color: inherit; text-decoration: none;"><?php echo t('players_th_club_member'); ?> <?php if ($sort === 'club_member') echo ($order === 'asc' ? '▲' : '▼'); ?></a></th>
                        <th><a href="?sort=visible&order=<?php echo $next_order; ?>&filter=<?php echo $filter; ?>" style="color: inherit; text-decoration: none;"><?php echo t('players_th_visible'); ?> <?php if ($sort === 'visible') echo ($order === 'asc' ? '▲' : '▼'); ?></a></th>
                        <th><?php echo t('players_th_created_by'); ?></th>
                        <th><?php echo t('players_th_edited_by'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stat_players as $player): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($player['id'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($player['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($player['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($player['city'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($player['club']); ?></td>
                        <td><?php echo htmlspecialchars($player['ema_number'] ?? ''); ?></td>
                        <td><?php $cc = strtolower($player['country_code'] ?: 'se'); echo '<span class="fi fi-' . htmlspecialchars($cc) . '"></span> ' . strtoupper(htmlspecialchars($cc)); ?></td>
                        <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($player['email'] ?? ''); ?>">
                            <?php echo htmlspecialchars($player['email'] ?? ''); ?>
                        </td>
                        <td>
                            <a href="?action=edit&id=<?php echo $player['id']; ?>" 
                               style="color: #2196F3; text-decoration: none; margin-right: 10px;">
                                ✏️ <?php echo t('btn_edit'); ?>
                            </a>
                            
                            <?php if (!in_array($player['id'], [1, 2])): ?>
                            <a href="#" 
                               onclick="if(confirm('<?php echo t('players_all_confirm_delete'); ?> <?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?>?')) { document.getElementById('delete-form-<?php echo $player['id']; ?>').submit(); } return false;"
                               style="color: #f44336; text-decoration: none;">
                                🗑️ <?php echo t('btn_delete'); ?>
                            </a>
                            
                            <form id="delete-form-<?php echo $player['id']; ?>" 
                                  method="POST" 
                                  style="display: none;">
                                <input type="hidden" name="id" value="<?php echo $player['id']; ?>">
                                <input type="hidden" name="delete_player" value="1">
                            </form>
                            <?php else: ?>
                            <span style="color: #999; font-size: 0.9em;"><?php echo t('players_all_system_account'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $player['club_member'] ? '✓ ' . t('yes') : '✗ ' . t('no'); ?></td>
                        <td><?php echo $player['visible_in_stats'] ? '✓ ' . t('yes') : '✗ ' . t('no'); ?></td>
                        <td style="font-size:0.9em;white-space:nowrap;">
                            <?php
                            if ($player['created_by']) {
                                $stmt_creator = $conn->prepare("SELECT first_name, last_name FROM stat_players WHERE id = ?");
                                $stmt_creator->execute([$player['created_by']]);
                                $creator = $stmt_creator->fetch();
                                echo $creator ? htmlspecialchars($creator['first_name'] . ' ' . $creator['last_name']) : htmlspecialchars($player['created_by']);
                            } else { echo '—'; }
                            ?>
                            <?php if (!empty($player['created_at']) && $player['created_at'] !== '0000-00-00 00:00:00'): ?>
                                <br><span style="color:#666;"><?php echo date('Y-m-d H:i', strtotime($player['created_at'])); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.9em;white-space:nowrap;">
                            <?php
                            if ($player['last_edited_by']) {
                                $stmt_editor = $conn->prepare("SELECT first_name, last_name FROM stat_players WHERE id = ?");
                                $stmt_editor->execute([$player['last_edited_by']]);
                                $editor = $stmt_editor->fetch();
                                echo $editor ? htmlspecialchars($editor['first_name'] . ' ' . $editor['last_name']) : htmlspecialchars($player['last_edited_by']);
                            } else { echo '—'; }
                            ?>
                            <?php if (!empty($player['updated_at']) && $player['updated_at'] !== '0000-00-00 00:00:00'): ?>
                                <br><span style="color:#666;"><?php echo date('Y-m-d H:i', strtotime($player['updated_at'])); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>


<script>
window.onload = function() {
    document.querySelectorAll(".flag-emoji").forEach(function(el) {
        var cc = el.getAttribute("data-cc").toUpperCase();
        var flag = String.fromCodePoint(0x1F1E6 + cc.charCodeAt(0) - 65) +
                   String.fromCodePoint(0x1F1E6 + cc.charCodeAt(1) - 65);
        el.innerHTML = flag + " " + cc;
    });
};
</script>
<?php includeFooter(); ?>