<?php
/**
 * users.php
 * User management page reading from stat_players table.
 * Admin only.
 *
 * Upload: www/players.php
 */
require_once 'config.php';

if (!hasRole('admin')) {
    showError(t('users_error_access'));
}

$conn = getDbConnection();
$success = '';
$error = '';

// Flash messages
if (isset($_SESSION['users_msg'])) {
    $flash = $_SESSION['users_msg'];
    unset($_SESSION['users_msg']);
    if ($flash['type'] === 'success') $success = $flash['text'];
    else $error = $flash['text'];
}

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'change_password') {
        $uid = (int)$_POST['user_id'];
        $pw = $_POST['new_password'] ?? '';
        if (strlen($pw) < 8) {
            $error = currentLang() === 'sv' ? 'Lösenordet måste vara minst 8 tecken.' : 'Password must be at least 8 characters.';
        } else {
            $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => defined('PASSWORD_COST') ? PASSWORD_COST : 12]);
            $conn->prepare("UPDATE stat_players SET password_hash = ? WHERE id = ?")->execute([$hash, $uid]);
            $_SESSION['users_msg'] = ['type' => 'success', 'text' => currentLang() === 'sv' ? 'Lösenord ändrat.' : 'Password changed.'];
            header('Location: players.php'); exit;
        }
    }
    
    if ($action === 'delete') {
        $uid = (int)$_POST['user_id'];
        $check = $conn->prepare("SELECT id FROM stat_players WHERE id = ?");
        $check->execute([$uid]);
        $u = $check->fetch();
        if ($u && $u['id'] > 2) {
            $conn->prepare("DELETE FROM stat_players WHERE id = ?")->execute([$uid]);
            $_SESSION['users_msg'] = ['type' => 'success', 'text' => currentLang() === 'sv' ? 'Användare borttagen.' : 'User deleted.'];
        }
        header('Location: players.php'); exit;
    }
    
    if ($action === 'update') {
        $uid = (int)$_POST['user_id'];
        $fields = ['username','first_name','last_name','email','phone','club','city','country_code','role','ema_number'];
        $sets = []; $params = [];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                $val = trim($_POST[$f]);
                if ($f === 'ema_number') $val = $val ?: null;
                $sets[] = "`$f` = ?";
                $params[] = $val;
            }
        }
        $club_member = isset($_POST['club_member']) ? 1 : 0;
        $tournament_player = isset($_POST['tournament_player']) ? 1 : 0;
        $visible = isset($_POST['visible_in_stats']) ? 1 : 0;
        $active = isset($_POST['active']) ? 1 : 0;
        $sets[] = "club_member = ?"; $params[] = $club_member;
        $sets[] = "tournament_player = ?"; $params[] = $tournament_player;
        $sets[] = "visible_in_stats = ?"; $params[] = $visible;
        $sets[] = "active = ?"; $params[] = $active;
        $sets[] = "last_edited_by = ?"; $params[] = $_SESSION['user_id'] ?? '';
        $params[] = $uid;
        $conn->prepare("UPDATE stat_players SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        $_SESSION['users_msg'] = ['type' => 'success', 'text' => currentLang() === 'sv' ? 'Användare uppdaterad.' : 'User updated.'];
        header('Location: players.php'); exit;
    }

    if ($action === 'create') {
        $fname = trim($_POST['first_name'] ?? '');
        $lname = trim($_POST['last_name'] ?? '');
        if (!$fname || !$lname) {
            $error = $sv ? 'Förnamn och efternamn krävs.' : 'First name and last name are required.';
        } else {
            $ema_num = trim($_POST['ema_number'] ?? '') ?: null;
            $username = trim($_POST['username'] ?? '') ?: null;
            $email = trim($_POST['email'] ?? '') ?: null;
            $phone = trim($_POST['phone'] ?? '') ?: null;
            $club = trim($_POST['club'] ?? '') ?: null;
            $city = trim($_POST['city'] ?? '') ?: null;
            $cc = trim($_POST['country_code'] ?? 'SE');
            $role = $_POST['role'] ?? 'reader';
            $club_member = isset($_POST['club_member']) ? 1 : 0;
            $tournament_player = isset($_POST['tournament_player']) ? 1 : 0;
            $visible = isset($_POST['visible_in_stats']) ? 1 : 0;
            $active = isset($_POST['active']) ? 1 : 0;
            $pw = trim($_POST['new_password'] ?? '');
            $hash = $pw && strlen($pw) >= 8 ? password_hash($pw, PASSWORD_BCRYPT, ['cost' => defined('PASSWORD_COST') ? PASSWORD_COST : 12]) : null;

            $conn->prepare("INSERT INTO stat_players (first_name, last_name, ema_number, username, email, phone, club, city, country_code, role, club_member, tournament_player, visible_in_stats, active, password_hash, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$fname, $lname, $ema_num, $username, $email, $phone, $club, $city, $cc, $role, $club_member, $tournament_player, $visible, $active, $hash, $_SESSION['user_id'] ?? '']);
            $_SESSION['users_msg'] = ['type' => 'success', 'text' => ($sv ? 'Ny användare skapad: ' : 'New user created: ') . $fname . ' ' . $lname];
            header('Location: players.php'); exit;
        }
    }
}

// Sorting
$sort = $_GET['sort'] ?? 'id';
$dir = $_GET['dir'] ?? 'asc';
$valid_sorts = ['id','ema','fname','lname','email','phone','club','city','country','role','pw','club_member','visible','tour','status','login','created','created_by','updated','updated_by'];
if (!in_array($sort, $valid_sorts)) $sort = 'id';
if (!in_array($dir, ['asc','desc'])) $dir = 'asc';

$order_map = [
    'id' => 'id',
    'ema' => "CASE WHEN ema_number IS NOT NULL THEN 0 ELSE 1 END, ema_number",
    'fname' => 'first_name', 'lname' => 'last_name', 'email' => 'email', 'phone' => 'phone',
    'club' => 'club', 'city' => 'city', 'country' => 'country_code', 'role' => 'role',
    'pw' => 'has_password', 'club_member' => 'club_member', 'visible' => 'visible_in_stats',
    'tour' => 'tournament_player', 'status' => 'active', 'login' => 'last_login_at',
    'created' => 'created_at', 'created_by' => 'created_by', 'updated' => 'updated_at', 'updated_by' => 'last_edited_by',
];
$order_col = $order_map[$sort] ?? 'id';
$order_dir = strtoupper($dir);

$users = $conn->query("
    SELECT *, 
           CASE WHEN password_hash IS NOT NULL AND password_hash != '' THEN 1 ELSE 0 END as has_password
    FROM stat_players 
    ORDER BY {$order_col} {$order_dir}
")->fetchAll(PDO::FETCH_ASSOC);

$total = count($users);

// Load permission types for modal
$perm_types_all = $conn->query("SELECT * FROM permission_types ORDER BY category, sort_order")->fetchAll();
$perm_by_cat = [];
foreach ($perm_types_all as $p) $perm_by_cat[$p['category']][] = $p;

// Load all current permissions as lookup
$all_perms_raw = $conn->query("SELECT player_id, permission_key FROM player_permissions")->fetchAll();
$perms_map = [];
foreach ($all_perms_raw as $row) $perms_map[$row['player_id']][] = $row['permission_key'];

$club_count = count(array_filter($users, fn($u) => $u['club_member']));
$external_count = $total - $club_count;
$active_count = count(array_filter($users, fn($u) => $u['active']));
$with_password = count(array_filter($users, fn($u) => $u['has_password']));

// Country name lookup
$country_names_sv = ['SE'=>'Sverige','DK'=>'Danmark','NO'=>'Norge','FI'=>'Finland','DE'=>'Tyskland','FR'=>'Frankrike','NL'=>'Nederländerna','BE'=>'Belgien','AT'=>'Österrike','CH'=>'Schweiz','IT'=>'Italien','ES'=>'Spanien','PT'=>'Portugal','PL'=>'Polen','CZ'=>'Tjeckien','HU'=>'Ungern','RO'=>'Rumänien','BG'=>'Bulgarien','HR'=>'Kroatien','GB'=>'Storbritannien','IE'=>'Irland','US'=>'USA','CA'=>'Kanada','JP'=>'Japan','CN'=>'Kina','TW'=>'Taiwan','KR'=>'Sydkorea','RU'=>'Ryssland','UA'=>'Ukraina','LT'=>'Litauen','LV'=>'Lettland','EE'=>'Estland','IL'=>'Israel','SG'=>'Singapore','HK'=>'Hongkong'];
$country_names_en = ['SE'=>'Sweden','DK'=>'Denmark','NO'=>'Norway','FI'=>'Finland','DE'=>'Germany','FR'=>'France','NL'=>'Netherlands','BE'=>'Belgium','AT'=>'Austria','CH'=>'Switzerland','IT'=>'Italy','ES'=>'Spain','PT'=>'Portugal','PL'=>'Poland','CZ'=>'Czech Republic','HU'=>'Hungary','RO'=>'Romania','BG'=>'Bulgaria','HR'=>'Croatia','GB'=>'United Kingdom','IE'=>'Ireland','US'=>'USA','CA'=>'Canada','JP'=>'Japan','CN'=>'China','TW'=>'Taiwan','KR'=>'South Korea','RU'=>'Russia','UA'=>'Ukraine','LT'=>'Lithuania','LV'=>'Latvia','EE'=>'Estonia','IL'=>'Israel','SG'=>'Singapore','HK'=>'Hong Kong'];

function countryDisplay($code) {
    global $country_names_sv, $country_names_en;
    $code_upper = strtoupper($code);
    $names = currentLang() === 'sv' ? $country_names_sv : $country_names_en;
    $name = $names[$code_upper] ?? $code_upper;
    $flag = '<span class="fi fi-' . strtolower($code) . '" style="margin-right:4px;"></span>';
    return $flag . htmlspecialchars($name);
}

function thSort($col, $label) {
    global $sort, $dir;
    $new_dir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $arrow = ($sort === $col) ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="?sort=' . $col . '&dir=' . $new_dir . '" style="color:white !important;text-decoration:none;white-space:nowrap;">' . $label . $arrow . '</a>';
}

$sv = (currentLang() === 'sv');
$page_title = $sv ? 'Användare - ' . SITE_TITLE : 'Users - ' . (defined('SITE_TITLE_EN') ? SITE_TITLE_EN : SITE_TITLE);
include 'includes/header.php';
?>

<h2><?php echo $sv ? 'Användare — ' . SITE_TITLE : 'Users — ' . (defined('SITE_TITLE_EN') ? SITE_TITLE_EN : SITE_TITLE); ?></h2>
<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px;align-items:stretch;">
    <div onclick="document.getElementById('createModal').style.display='block'" style="background:#005B99;padding:10px 20px;border-radius:8px;text-align:center;cursor:pointer;display:flex;align-items:center;gap:6px;transition:opacity 0.15s;" onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
        <span style="font-size:1.8em;color:white;line-height:1;font-weight:700;">+</span>
        <span style="font-size:0.82em;color:white;font-weight:700;white-space:nowrap;"><?php echo $sv ? 'Ny spelare' : 'New player'; ?></span>
    </div>
    <div style="background:#e8f5e9;padding:10px 16px;border-radius:8px;text-align:center;">
        <div style="font-size:1.5em;font-weight:700;color:#2e7d32;"><?php echo $total; ?></div>
        <div style="font-size:0.82em;color:#2e7d32;"><?php echo $sv ? 'Totalt' : 'Total'; ?></div>
    </div>
    <div style="background:#e3f2fd;padding:10px 16px;border-radius:8px;text-align:center;">
        <div style="font-size:1.5em;font-weight:700;color:#005B99;"><?php echo $club_count; ?></div>
        <div style="font-size:0.82em;color:#005B99;"><?php echo $sv ? 'Medlemmar' : 'Members'; ?></div>
    </div>
    <div style="background:#f3e5f5;padding:10px 16px;border-radius:8px;text-align:center;">
        <div style="font-size:1.5em;font-weight:700;color:#7b1fa2;"><?php echo $external_count; ?></div>
        <div style="font-size:0.82em;color:#7b1fa2;"><?php echo $sv ? 'Externa' : 'External'; ?></div>
    </div>
    <div style="background:#fff3e0;padding:10px 16px;border-radius:8px;text-align:center;">
        <div style="font-size:1.5em;font-weight:700;color:#e65100;"><?php echo $with_password; ?></div>
        <div style="font-size:0.82em;color:#e65100;"><?php echo $sv ? 'Med lösenord' : 'With password'; ?></div>
    </div>
</div>

<?php if ($success): ?>
<div class="message success" style="background:#e8f5e9;border:1px solid #4CAF50;color:#2e7d32;padding:12px 20px;border-radius:6px;margin-bottom:20px;">✅ <?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="message error" style="background:#ffebee;border:1px solid #f44336;color:#c62828;padding:12px 20px;border-radius:6px;margin-bottom:20px;">❌ <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;flex-wrap:wrap;">
    <div style="position:relative;flex:1;min-width:200px;max-width:400px;">
        <input type="text" id="userSearch" placeholder="<?php echo $sv ? '🔍 Sök namn, EMA, klubb, stad...' : '🔍 Search name, EMA, club, city...'; ?>" style="width:100%;padding:10px 14px 10px 14px;border:2px solid #005B99;border-radius:8px;font-size:0.95em;box-sizing:border-box;" oninput="filterUsers()">
    </div>
    <span id="searchCount" style="font-size:0.85em;color:#666;"></span>
</div>

<div style="overflow-x:auto;">
<table style="width:100%;border-collapse:collapse;font-size:0.95em;" id="usersTable">
    <thead>
        <tr style="background:#005B99;color:white;font-size:0.9em;">
            <th style="padding:8px 6px;text-align:center;"><?php echo thSort('id', 'ID'); ?></th>
            <th style="padding:8px 6px;"><?php echo thSort('ema', 'EMA'); ?></th>
            <th style="padding:8px 6px;"><?php echo thSort('fname', $sv ? 'Förnamn' : 'First name'); ?></th>
            <th style="padding:8px 6px;"><?php echo thSort('lname', $sv ? 'Efternamn' : 'Last name'); ?></th>
            <th style="padding:8px 6px;"><?php echo thSort('email', 'E-post'); ?></th>
            <th style="padding:8px 6px;"><?php echo thSort('phone', $sv ? 'Telefon' : 'Phone'); ?></th>
            <th style="padding:8px 6px;"><?php echo thSort('club', $sv ? 'Klubb' : 'Club'); ?></th>
            <th style="padding:8px 6px;"><?php echo thSort('city', $sv ? 'Stad' : 'City'); ?></th>
            <th style="padding:8px 6px;"><?php echo thSort('country', $sv ? 'Land' : 'Country'); ?></th>
            <th style="padding:8px 6px;"><?php echo thSort('role', $sv ? 'Roll' : 'Role'); ?></th>
            <th style="padding:8px 6px;text-align:center;"><?php echo thSort('pw', $sv ? 'Lösen' : 'PW'); ?></th>
            <th style="padding:8px 6px;text-align:center;"><?php echo thSort('club_member', $sv ? 'Medlem' : 'Member'); ?></th>
            <th style="padding:8px 6px;text-align:center;"><?php echo thSort('visible', $sv ? 'Synlig' : 'Visible'); ?></th>
            <th style="padding:8px 6px;text-align:center;"><?php echo thSort('tour', $sv ? 'Turn.' : 'Tour.'); ?></th>
            <th style="padding:8px 6px;text-align:center;"><?php echo thSort('status', 'Status'); ?></th>
            <th style="padding:8px 6px;"><?php echo thSort('login', $sv ? 'Senast inloggad' : 'Last login'); ?></th>
            <th style="padding:8px 6px;"><?php echo thSort('created', $sv ? 'Skapad' : 'Created'); ?></th>
            <th style="padding:8px 6px;"><?php echo thSort('created_by', $sv ? 'Skapad av' : 'By'); ?></th>
            <th style="padding:8px 6px;"><?php echo thSort('updated', $sv ? 'Uppdaterad' : 'Updated'); ?></th>
            <th style="padding:8px 6px;"><?php echo thSort('updated_by', $sv ? 'Uppd. av' : 'By'); ?></th>
            <th style="padding:8px 6px;"><?php echo $sv ? 'Åtgärder' : 'Actions'; ?></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): 
        $is_system = $u['id'] <= 2;
        $row_bg = $is_system ? '#f5f5f5' : ($u['active'] ? 'white' : '#fff3e0');
        $ema = $u['ema_number'] ?? '';
        $has_ema = preg_match('/^\d{7,8}$/', $ema);
    ?>
    <tr style="border-bottom:1px solid #e0e0e0;background:<?php echo $row_bg; ?>;" id="row-<?php echo $u['id']; ?>">
        <td style="padding:6px;text-align:center;color:#999;"><?php echo $u['id']; ?></td>
        
        <td style="padding:6px;">
            <?php if ($has_ema): ?>
                <a href="https://mahjong-europe.org/ranking/Players/<?php echo $ema; ?>.html" target="_blank" style="color:#005B99;"><?php echo $ema; ?></a>
            <?php else: ?>
                <?php echo htmlspecialchars($ema); ?>
            <?php endif; ?>
        </td>
        <td style="padding:6px;cursor:pointer;color:#005B99;" onclick="editUser(<?php echo $u['id']; ?>)"><?php echo htmlspecialchars($u['first_name']); ?></td>
        <td style="padding:6px;font-weight:600;cursor:pointer;color:#005B99;" onclick="editUser(<?php echo $u['id']; ?>)"><?php echo htmlspecialchars($u['last_name']); ?></td>
        <td style="padding:6px;cursor:pointer;color:#005B99;" onclick="editUser(<?php echo $u['id']; ?>)"><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
        <td style="padding:6px;"><?php echo htmlspecialchars($u['phone'] ?? ''); ?></td>
        <td style="padding:6px;"><?php echo htmlspecialchars($u['club'] ?? ''); ?></td>
        <td style="padding:6px;"><?php echo htmlspecialchars($u['city'] ?? ''); ?></td>
        <td style="padding:6px;white-space:nowrap;"><?php echo countryDisplay($u['country_code']); ?></td>
        <td style="padding:6px;">
            <?php 
            $role_colors = ['mainadmin'=>'#b71c1c','admin'=>'#e65100','player'=>'#1b5e20','reader'=>'#666'];
            $rc = $role_colors[$u['role']] ?? '#333';
            ?>
            <span style="color:<?php echo $rc; ?>;font-weight:600;"><?php echo $u['role']; ?></span>
        </td>
        <td style="padding:6px;text-align:center;"><?php echo $u['has_password'] ? '🔑' : ''; ?></td>
        <td style="padding:6px;text-align:center;"><?php echo $u['club_member'] ? '✅' : ''; ?></td>
        <td style="padding:6px;text-align:center;"><?php echo $u['visible_in_stats'] ? '👁️' : '🚫'; ?></td>
        <td style="padding:6px;text-align:center;"><?php echo $u['tournament_player'] ? '🀄' : ''; ?></td>
        <td style="padding:6px;text-align:center;"><?php echo $u['active'] ? '✅' : '❌'; ?></td>
        <td style="padding:6px;white-space:nowrap;"><?php echo $u['last_login_at'] ? date('Y-m-d H:i', strtotime($u['last_login_at'])) : '—'; ?></td>
        <td style="padding:6px;white-space:nowrap;"><?php echo $u['created_at'] ? date('Y-m-d', strtotime($u['created_at'])) : '—'; ?></td>
        <td style="padding:6px;"><?php echo htmlspecialchars($u['created_by'] ?? ''); ?></td>
        <td style="padding:6px;white-space:nowrap;"><?php echo $u['updated_at'] ? date('Y-m-d', strtotime($u['updated_at'])) : '—'; ?></td>
        <td style="padding:6px;"><?php echo htmlspecialchars($u['last_edited_by'] ?? ''); ?></td>
        <td style="padding:5px;white-space:nowrap;">
            <button onclick="editUser(<?php echo $u['id']; ?>)" style="border:none;background:none;cursor:pointer;font-size:1.2em;padding:2px;" title="<?php echo $sv ? 'Redigera' : 'Edit'; ?>">✏️</button>
            <button onclick="openPerms(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['first_name'].' '.$u['last_name'])); ?>')" style="border:none;background:none;cursor:pointer;font-size:1.2em;padding:2px;" title="<?php echo $sv ? 'Behörigheter' : 'Permissions'; ?>">🔐</button>
            <button onclick="changePassword(<?php echo $u['id']; ?>)" style="border:none;background:none;cursor:pointer;font-size:1.2em;padding:2px;" title="<?php echo $sv ? 'Byt lösenord' : 'Change password'; ?>">🔑</button>
            <?php if (!$is_system): ?>
            <button onclick="deleteUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['first_name'] . ' ' . $u['last_name']), ENT_QUOTES); ?>')" style="border:none;background:none;cursor:pointer;font-size:1.2em;padding:2px;color:#c62828;" title="<?php echo $sv ? 'Ta bort' : 'Delete'; ?>">🗑️</button>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- CREATE MODAL -->
<div id="createModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;overflow-y:auto;">
    <div style="max-width:550px;margin:40px auto;background:white;padding:28px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <h3 style="margin:0 0 16px;color:#2e7d32;">➕ <?php echo $sv ? 'Ny spelare' : 'New player'; ?></h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Förnamn' : 'First name'; ?> *</label><input type="text" name="first_name" required style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
                <div><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Efternamn' : 'Last name'; ?> *</label><input type="text" name="last_name" required style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div><label style="font-size:0.82em;font-weight:600;">EMA</label><input type="text" name="ema_number" placeholder="70000000" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
            </div>
            <div style="margin-bottom:10px;"><label style="font-size:0.82em;font-weight:600;">Username</label><input type="text" name="username" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
            <div style="margin-bottom:10px;"><label style="font-size:0.82em;font-weight:600;">E-post</label><input type="email" name="email" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Telefon' : 'Phone'; ?></label><input type="text" name="phone" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
                <div><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Klubb' : 'Club'; ?></label><input type="text" name="club" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 80px;gap:10px;margin-bottom:10px;">
                <div><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Stad' : 'City'; ?></label><input type="text" name="city" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
                <div><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Land' : 'Country'; ?></label><input type="text" name="country_code" value="SE" maxlength="2" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;text-transform:uppercase;"></div>
                <div><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Roll' : 'Role'; ?></label>
                    <select name="role" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;">
                        <option value="reader">reader</option>
                        <option value="player">player</option>
                        <option value="admin">admin</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:10px;"><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Lösenord (valfritt, minst 8 tecken)' : 'Password (optional, min 8 chars)'; ?></label><input type="password" name="new_password" minlength="8" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
            <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
                <label style="font-size:0.88em;"><input type="checkbox" name="club_member"> <?php echo $sv ? 'Klubbmedlem' : 'Club member'; ?></label>
                <label style="font-size:0.88em;"><input type="checkbox" name="tournament_player" checked> <?php echo $sv ? 'Turneringsspelare' : 'Tournament player'; ?></label>
                <label style="font-size:0.88em;"><input type="checkbox" name="visible_in_stats"> <?php echo $sv ? 'Synlig' : 'Visible'; ?></label>
                <label style="font-size:0.88em;"><input type="checkbox" name="active" checked> <?php echo $sv ? 'Aktiv' : 'Active'; ?></label>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn" style="flex:1;padding:10px;background:#2e7d32;color:white;">➕ <?php echo $sv ? 'Skapa' : 'Create'; ?></button>
                <button type="button" class="btn btn-secondary" style="flex:1;padding:10px;" onclick="document.getElementById('createModal').style.display='none';"><?php echo $sv ? 'Avbryt' : 'Cancel'; ?></button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;overflow-y:auto;">
    <div style="max-width:550px;margin:40px auto;background:white;padding:28px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <h3 style="margin:0 0 16px;color:#005B99;">✏️ <?php echo $sv ? 'Redigera användare' : 'Edit user'; ?></h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="user_id" id="eu_id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Förnamn' : 'First name'; ?></label><input type="text" name="first_name" id="eu_fname" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
                <div><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Efternamn' : 'Last name'; ?></label><input type="text" name="last_name" id="eu_lname" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div><label style="font-size:0.82em;font-weight:600;">EMA</label><input type="text" name="ema_number" id="eu_ema" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
            </div>
            <div style="margin-bottom:10px;"><label style="font-size:0.82em;font-weight:600;">Username</label><input type="text" name="username" id="eu_uname" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
            <div style="margin-bottom:10px;"><label style="font-size:0.82em;font-weight:600;">E-post</label><input type="email" name="email" id="eu_email" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Telefon' : 'Phone'; ?></label><input type="text" name="phone" id="eu_phone" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
                <div><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Klubb' : 'Club'; ?></label><input type="text" name="club" id="eu_club" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 80px;gap:10px;margin-bottom:10px;">
                <div><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Stad' : 'City'; ?></label><input type="text" name="city" id="eu_city" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;"></div>
                <div><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Land' : 'Country'; ?></label><input type="text" name="country_code" id="eu_cc" maxlength="2" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;text-transform:uppercase;"></div>
                <div><label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Roll' : 'Role'; ?></label>
                    <select name="role" id="eu_role" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:4px;">
                        <option value="reader">reader</option>
                        <option value="player">player</option>
                        <option value="admin">admin</option>
                        <option value="mainadmin">mainadmin</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
                <label style="font-size:0.88em;"><input type="checkbox" name="club_member" id="eu_club_member"> <?php echo $sv ? 'Klubbmedlem' : 'Club member'; ?></label>
                <label style="font-size:0.88em;"><input type="checkbox" name="tournament_player" id="eu_tour"> <?php echo $sv ? 'Turneringsspelare' : 'Tournament player'; ?></label>
                <label style="font-size:0.88em;"><input type="checkbox" name="visible_in_stats" id="eu_visible"> <?php echo $sv ? 'Synlig' : 'Visible'; ?></label>
                <label style="font-size:0.88em;"><input type="checkbox" name="active" id="eu_active"> <?php echo $sv ? 'Aktiv' : 'Active'; ?></label>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn" style="flex:1;padding:10px;">💾 <?php echo $sv ? 'Spara' : 'Save'; ?></button>
                <button type="button" class="btn btn-secondary" style="flex:1;padding:10px;" onclick="document.getElementById('editModal').style.display='none';"><?php echo $sv ? 'Avbryt' : 'Cancel'; ?></button>
            </div>
        </form>
    </div>
</div>

<!-- PERMISSIONS MODAL -->
<div id="permsModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;overflow-y:auto;">
    <div style="max-width:560px;margin:40px auto;background:white;padding:28px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;color:#005B99;">🔐 <span id="pm_title"></span></h3>
            <button onclick="document.getElementById('permsModal').style.display='none'" style="border:none;background:none;cursor:pointer;font-size:1.4em;color:#666;">✕</button>
        </div>
        <input type="hidden" id="pm_uid">
        <div id="pm_perms"></div>
        <div style="margin-top:16px;text-align:right;">
            <button onclick="document.getElementById('permsModal').style.display='none'" class="btn btn-secondary" style="padding:8px 20px;">
                <?php echo $sv ? 'Stäng' : 'Close'; ?>
            </button>
        </div>
    </div>
</div>

<!-- PASSWORD MODAL -->
<div id="pwModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;">
    <div style="max-width:400px;margin:100px auto;background:white;padding:28px;border-radius:12px;">
        <h3 style="margin:0 0 16px;color:#005B99;">🔑 <?php echo $sv ? 'Byt lösenord' : 'Change password'; ?></h3>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="user_id" id="pw_uid">
            <div style="margin-bottom:12px;">
                <label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Nytt lösenord' : 'New password'; ?></label>
                <input type="password" name="new_password" required minlength="8" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;" id="pw1">
            </div>
            <div style="margin-bottom:16px;">
                <label style="font-size:0.82em;font-weight:600;"><?php echo $sv ? 'Bekräfta lösenord' : 'Confirm password'; ?></label>
                <input type="password" required minlength="8" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;" id="pw2">
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn" style="flex:1;padding:10px;" onclick="if(document.getElementById('pw1').value!==document.getElementById('pw2').value){alert('<?php echo $sv ? 'Lösenorden matchar inte!' : 'Passwords do not match!'; ?>');return false;}">💾 <?php echo $sv ? 'Spara' : 'Save'; ?></button>
                <button type="button" class="btn btn-secondary" style="flex:1;padding:10px;" onclick="document.getElementById('pwModal').style.display='none';"><?php echo $sv ? 'Avbryt' : 'Cancel'; ?></button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE FORM -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" id="del_uid">
</form>

<script>
const SV = <?php echo (currentLang()==='sv') ? 'true' : 'false'; ?>;

// ===== SEARCH / FILTER =====
function filterUsers() {
    const q = document.getElementById('userSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#usersTable tbody tr');
    let visible = 0;
    rows.forEach(row => {
        if (!q) { row.style.display = ''; visible++; return; }
        const text = row.textContent.toLowerCase();
        const match = q.split(/\s+/).every(word => text.includes(word));
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    const total = rows.length;
    const countEl = document.getElementById('searchCount');
    if (q) {
        countEl.textContent = (SV ? visible + ' av ' + total + ' visas' : visible + ' of ' + total + ' shown');
    } else {
        countEl.textContent = '';
    }
}

// User data for edit modal
const userData = <?php echo json_encode(array_map(function($u) {
    return [
        'id' => $u['id'], 'ema_number' => $u['ema_number'] ?? '',
        'username' => $u['username'] ?? '', 'first_name' => $u['first_name'], 'last_name' => $u['last_name'],
        'email' => $u['email'] ?? '', 'phone' => $u['phone'] ?? '', 'club' => $u['club'] ?? '',
        'city' => $u['city'] ?? '', 'country_code' => $u['country_code'] ?? 'SE', 'role' => $u['role'],
        'club_member' => (int)$u['club_member'], 'tournament_player' => (int)$u['tournament_player'],
        'visible_in_stats' => (int)$u['visible_in_stats'], 'active' => (int)$u['active'],
    ];
}, $users)); ?>;

function editUser(id) {
    const u = userData.find(x => x.id === id);
    if (!u) return;
    document.getElementById('eu_id').value = u.id;
    document.getElementById('eu_fname').value = u.first_name;
    document.getElementById('eu_lname').value = u.last_name;
    document.getElementById('eu_ema').value = u.ema_number;
    document.getElementById('eu_uname').value = u.username;
    document.getElementById('eu_email').value = u.email;
    document.getElementById('eu_phone').value = u.phone;
    document.getElementById('eu_club').value = u.club;
    document.getElementById('eu_city').value = u.city;
    document.getElementById('eu_cc').value = u.country_code;
    document.getElementById('eu_role').value = u.role;
    document.getElementById('eu_club_member').checked = u.club_member === 1;
    document.getElementById('eu_tour').checked = u.tournament_player === 1;
    document.getElementById('eu_visible').checked = u.visible_in_stats === 1;
    document.getElementById('eu_active').checked = u.active === 1;
    document.getElementById('editModal').style.display = 'block';
}

function changePassword(id) {
    document.getElementById('pw_uid').value = id;
    document.getElementById('pw1').value = '';
    document.getElementById('pw2').value = '';
    document.getElementById('pwModal').style.display = 'block';
}

// ===== PERMISSIONS MODAL =====
const permTypes = <?php echo json_encode($perm_by_cat); ?>;
const permsMap  = <?php echo json_encode($perms_map); ?>;

function openPerms(userId, userName) {
    document.getElementById('pm_title').textContent = (SV ? 'Behörigheter: ' : 'Permissions: ') + userName;
    document.getElementById('pm_uid').value = userId;
    
    const container = document.getElementById('pm_perms');
    const userPerms = permsMap[userId] || [];
    let html = '';
    
    const cats = {
        system:     SV ? 'Systemroller'      : 'System roles',
        vms:        SV ? 'Klubb-behörigheter'  : 'Club permissions',
        tournament: SV ? 'Turneringar'       : 'Tournaments',
    };
    
    Object.entries(cats).forEach(([cat, catLabel]) => {
        const perms = permTypes[cat] || [];
        if (!perms.length) return;
        html += `<div style="margin-bottom:12px;">
            <div style="font-weight:700;color:#005B99;font-size:0.85em;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">${catLabel}</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">`;
        perms.forEach(p => {
            const checked = userPerms.includes(p.key) ? 'checked' : '';
            const label = SV ? p.label_sv : p.label_en;
            html += `<label style="display:flex;align-items:center;gap:4px;background:#f5f7fa;padding:4px 10px;border-radius:16px;cursor:pointer;font-size:0.88em;border:1px solid #ddd;">
                <input type="checkbox" data-perm="${p.key}" data-user="${userId}" ${checked}
                    onchange="togglePermInline(this)"
                    style="cursor:pointer;">
                ${label}
            </label>`;
        });
        html += '</div></div>';
    });
    
    container.innerHTML = html;
    document.getElementById('permsModal').style.display = 'block';
}

async function togglePermInline(cb) {
    const perm = cb.dataset.perm;
    const userId = parseInt(cb.dataset.user);
    const action = cb.checked ? 'grant' : 'revoke';
    cb.disabled = true;
    try {
        const r = await fetch('admin-tournament-roles.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action, player_id: userId, permission_key: perm})
        });
        const d = await r.json();
        if (!d.success) {
            alert(d.error || 'Error');
            cb.checked = !cb.checked;
        } else {
            // Update local permsMap
            if (!permsMap[userId]) permsMap[userId] = [];
            if (action === 'grant') {
                if (!permsMap[userId].includes(perm)) permsMap[userId].push(perm);
            } else {
                permsMap[userId] = permsMap[userId].filter(p => p !== perm);
            }
        }
    } catch(e) {
        alert(e.message);
        cb.checked = !cb.checked;
    }
    cb.disabled = false;
}

function deleteUser(id, name) {
    if (confirm('<?php echo $sv ? "Ta bort" : "Delete"; ?> ' + name + '?')) {
        document.getElementById('del_uid').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@7.2.3/css/flag-icons.min.css">
<style>
#usersTable thead th { background: #005B99 !important; color: #fff !important; }
#usersTable thead th a,
#usersTable thead th a:link,
#usersTable thead th a:visited,
#usersTable thead th a:hover,
#usersTable thead th a:active { color: #fff !important; text-decoration: none !important; }
#usersTable thead th a:hover { text-decoration: underline !important; }
</style>

<?php include 'includes/footer.php'; ?>
