<?php
/**
 * external-tournaments.php
 * Admin — hantera externa turneringar som klubbmedlemmar deltagit i.
 * Mobilanpassad, tvåspråkig.
 *
 * Upload: www/external-tournaments.php
 */
require_once 'config.php';

if (!hasRole('admin')) {
    showError(t('dev_err_title'));
}

$conn = getDbConnection();
$sv = (currentLang() === 'sv');
$success = '';
$error = '';

// Ensure website_url column exists
try {
    $conn->query("SELECT website_url FROM stat_external_tournaments LIMIT 1");
} catch (Exception $e) {
    $conn->query("ALTER TABLE stat_external_tournaments ADD COLUMN website_url VARCHAR(500) DEFAULT NULL AFTER best_club_placement");
}

// Ensure best_club_ema column exists
try {
    $conn->query("SELECT best_club_ema FROM stat_external_tournaments LIMIT 1");
} catch (Exception $e) {
    $conn->query("ALTER TABLE stat_external_tournaments ADD COLUMN best_club_ema VARCHAR(20) DEFAULT NULL AFTER best_club_placement");
}

// Ensure referees_ema column exists (comma-separated EMA numbers)
try {
    $conn->query("SELECT referees_ema FROM stat_external_tournaments LIMIT 1");
} catch (Exception $e) {
    $conn->query("ALTER TABLE stat_external_tournaments ADD COLUMN referees_ema VARCHAR(200) DEFAULT NULL AFTER best_club_ema");
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_tournament'])) {
        $name = trim($_POST['name'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = strtolower(trim($_POST['country_code'] ?? 'se'));
        $date_start = trim($_POST['date_start'] ?? '');
        $date_end = trim($_POST['date_end'] ?? '') ?: $date_start;
        $mto_id = trim($_POST['mto_id'] ?? '') ?: null;
        $website_url = trim($_POST['website_url'] ?? '') ?: null;
        $vms_part = trim($_POST['club_participants'] ?? '') ?: null;
        $total_part = trim($_POST['total_participants'] ?? '') ?: null;
        $best_place = trim($_POST['best_club_placement'] ?? '') ?: null;
        $best_ema = trim($_POST['best_club_ema'] ?? '') ?: null;
        $referees_ema = trim($_POST['referees_ema'] ?? '') ?: null;

        if ($name === '' || $city === '' || $date_start === '') {
            $error = $sv ? 'Namn, stad och startdatum krävs.' : 'Name, city and start date are required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO stat_external_tournaments (mto_id, name, city, country_code, date_start, date_end, club_participants, total_participants, best_club_placement, best_club_ema, referees_ema, website_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$mto_id, $name, $city, $country, $date_start, $date_end, $vms_part, $total_part, $best_place, $best_ema, $referees_ema, $website_url]);
            $success = $sv ? 'Turnering tillagd.' : 'Tournament added.';
        }
    }

    if (isset($_POST['update_tournament'])) {
        $id = (int)$_POST['tournament_id'];
        $name = trim($_POST['name'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = strtolower(trim($_POST['country_code'] ?? 'se'));
        $date_start = trim($_POST['date_start'] ?? '');
        $date_end = trim($_POST['date_end'] ?? '') ?: $date_start;
        $mto_id = trim($_POST['mto_id'] ?? '') ?: null;
        $website_url = trim($_POST['website_url'] ?? '') ?: null;
        $vms_part = trim($_POST['club_participants'] ?? '') ?: null;
        $total_part = trim($_POST['total_participants'] ?? '') ?: null;
        $best_place = trim($_POST['best_club_placement'] ?? '') ?: null;
        $best_ema = trim($_POST['best_club_ema'] ?? '') ?: null;
        $referees_ema = trim($_POST['referees_ema'] ?? '') ?: null;

        if ($name === '' || $city === '' || $date_start === '') {
            $error = $sv ? 'Namn, stad och startdatum krävs.' : 'Name, city and start date are required.';
        } else {
            $stmt = $conn->prepare("UPDATE stat_external_tournaments SET mto_id=?, name=?, city=?, country_code=?, date_start=?, date_end=?, club_participants=?, total_participants=?, best_club_placement=?, best_club_ema=?, referees_ema=?, website_url=? WHERE id=?");
            $stmt->execute([$mto_id, $name, $city, $country, $date_start, $date_end, $vms_part, $total_part, $best_place, $best_ema, $referees_ema, $website_url, $id]);
            $success = $sv ? 'Turnering uppdaterad.' : 'Tournament updated.';
        }
    }

    if (isset($_POST['delete_tournament'])) {
        $id = (int)$_POST['tournament_id'];
        $conn->prepare("DELETE FROM stat_external_tournaments WHERE id = ?")->execute([$id]);
        $success = $sv ? 'Turnering borttagen.' : 'Tournament deleted.';
    }

    if (!$error) { header('Location: external-tournaments.php' . ($success ? '?msg=' . urlencode($success) : '')); exit; }
}

if (isset($_GET['msg'])) $success = $_GET['msg'];

$tournaments = $conn->query("SELECT * FROM stat_external_tournaments ORDER BY date_start DESC")->fetchAll(PDO::FETCH_ASSOC);
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

$page_title = $sv ? 'Externa turneringar' : 'External Tournaments';
include 'includes/header.php';
?>

<style>
.et-form { display:grid; gap:12px; }
.et-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.et-row3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }
.et-row4 { display:grid; grid-template-columns:80px 1fr 1fr 100px; gap:10px; }
.et-field label { display:block; font-weight:600; font-size:0.85em; margin-bottom:3px; color:#333; }
.et-field input { width:100%; padding:8px; border:1px solid #ccc; border-radius:5px; font-size:0.92em; }
.et-card {
    background:white; border:1px solid #e0e8f0; border-radius:10px; padding:14px 16px; margin-bottom:10px;
    box-shadow:0 1px 4px rgba(0,0,0,0.04);
}
.et-card-name { font-weight:700; color:#005B99; font-size:1.02em; margin-bottom:4px; }
.et-card-meta { display:flex; flex-wrap:wrap; gap:4px 14px; font-size:0.85em; color:#555; margin-bottom:8px; }
.et-card-actions { display:flex; gap:8px; }
.et-card-actions a, .et-card-actions button {
    padding:5px 12px; border-radius:5px; font-size:0.85em; cursor:pointer; text-decoration:none; border:1px solid #ccc;
}
.et-card.editing { background:#fff8e1; border-color:#ffc107; }

@media (max-width:600px) {
    .et-row, .et-row3, .et-row4 { grid-template-columns:1fr; }
}
</style>

<h2>🌍 <?php echo $sv ? 'Externa turneringar' : 'External Tournaments'; ?></h2>
<p style="color:#666;margin-bottom:24px;">
    <?php echo $sv ? 'Turneringar som klubbmedlemmar deltagit i.' : 'Tournaments attended by club members.'; ?>
    <strong><?php echo count($tournaments); ?></strong> <?php echo $sv ? 'registrerade' : 'registered'; ?>.
</p>

<?php if ($success): ?><div class="message success" style="background:#e8f5e9;border:1px solid #4CAF50;color:#2e7d32;padding:12px 20px;border-radius:6px;margin-bottom:20px;">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error" style="background:#ffebee;border:1px solid #f44336;color:#c62828;padding:12px 20px;border-radius:6px;margin-bottom:20px;">❌ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<!-- ADD FORM -->
<details style="background:#e3f2fd;border-radius:8px;border-left:4px solid #1976D2;margin-bottom:30px;padding:0;">
    <summary style="padding:14px 20px;cursor:pointer;font-weight:700;font-size:1em;">➕ <?php echo $sv ? 'Lägg till turnering' : 'Add tournament'; ?></summary>
    <div style="padding:4px 20px 20px;">
    <form method="POST" class="et-form">
        <div class="et-row">
            <div class="et-field">
                <label><?php echo $sv ? 'Namn' : 'Name'; ?> *</label>
                <input type="text" name="name" required placeholder="MCR Open 2026">
            </div>
            <div class="et-field">
                <label><?php echo $sv ? 'Stad' : 'City'; ?> *</label>
                <input type="text" name="city" required placeholder="Copenhagen">
            </div>
        </div>
        <div class="et-row4">
            <div class="et-field">
                <label><?php echo $sv ? 'Land' : 'Country'; ?></label>
                <input type="text" name="country_code" value="se" maxlength="2" placeholder="se" style="text-transform:lowercase;">
            </div>
            <div class="et-field">
                <label><?php echo $sv ? 'Startdatum' : 'Start date'; ?> *</label>
                <input type="date" name="date_start" required>
            </div>
            <div class="et-field">
                <label><?php echo $sv ? 'Slutdatum' : 'End date'; ?></label>
                <input type="date" name="date_end">
            </div>
            <div class="et-field">
                <label>MTO ID</label>
                <input type="number" name="mto_id" placeholder="445">
            </div>
        </div>
        <div class="et-field">
            <label><?php echo $sv ? 'Webbsida (URL)' : 'Website (URL)'; ?></label>
            <input type="url" name="website_url" placeholder="https://example.com/tournament">
        </div>
        <div class="et-row3">
            <div class="et-field">
                <label><?php echo $sv ? 'Klubbdeltagare' : 'Club participants'; ?></label>
                <input type="number" name="club_participants" min="1" placeholder="3">
            </div>
            <div class="et-field">
                <label><?php echo $sv ? 'Totalt deltagare' : 'Total participants'; ?></label>
                <input type="number" name="total_participants" min="1" placeholder="80">
            </div>
            <div class="et-field">
                <label><?php echo $sv ? 'Bästa klubbplacering' : 'Best club placement'; ?></label>
                <input type="number" name="best_club_placement" min="1" placeholder="5">
            </div>
        </div>
        <div class="et-field">
            <label><?php echo $sv ? 'Bästa klubbspelarens EMA-nr' : 'Best club player EMA number'; ?></label>
            <input type="text" name="best_club_ema" placeholder="09990170" maxlength="20">
        </div>
        <div class="et-field">
            <label><?php echo $sv ? 'Domare från klubben (EMA-nr, kommaseparerade)' : 'Club referees (EMA numbers, comma-separated)'; ?></label>
            <input type="text" name="referees_ema" placeholder="09990170, 09990165" maxlength="200">
        </div>
        <div>
            <button type="submit" name="add_tournament" style="padding:10px 24px;background:#1976D2;color:white;border:none;border-radius:6px;font-size:1em;cursor:pointer;">
                ➕ <?php echo $sv ? 'Lägg till' : 'Add'; ?>
            </button>
        </div>
    </form>
    </div>
</details>

<!-- TOURNAMENT LIST — card layout -->
<?php foreach ($tournaments as $t):
    $d1 = date('j M Y', strtotime($t['date_start']));
    $d2 = $t['date_end'] ? date('j M Y', strtotime($t['date_end'])) : $d1;
    $date_str = ($d1 === $d2) ? $d1 : $d1 . ' – ' . $d2;
    $web_url = trim($t['website_url'] ?? '');

    if ($edit_id === (int)$t['id']):
?>
    <!-- EDIT MODE -->
    <div class="et-card editing">
        <form method="POST" class="et-form">
            <input type="hidden" name="tournament_id" value="<?php echo $t['id']; ?>">
            <div class="et-row">
                <div class="et-field">
                    <label><?php echo $sv ? 'Namn' : 'Name'; ?> *</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($t['name']); ?>" required>
                </div>
                <div class="et-field">
                    <label><?php echo $sv ? 'Stad' : 'City'; ?> *</label>
                    <input type="text" name="city" value="<?php echo htmlspecialchars($t['city']); ?>" required>
                </div>
            </div>
            <div class="et-row4">
                <div class="et-field">
                    <label><?php echo $sv ? 'Land' : 'Country'; ?></label>
                    <input type="text" name="country_code" value="<?php echo htmlspecialchars($t['country_code']); ?>" maxlength="2" style="text-transform:lowercase;">
                </div>
                <div class="et-field">
                    <label><?php echo $sv ? 'Startdatum' : 'Start date'; ?> *</label>
                    <input type="date" name="date_start" value="<?php echo $t['date_start']; ?>" required>
                </div>
                <div class="et-field">
                    <label><?php echo $sv ? 'Slutdatum' : 'End date'; ?></label>
                    <input type="date" name="date_end" value="<?php echo $t['date_end']; ?>">
                </div>
                <div class="et-field">
                    <label>MTO ID</label>
                    <input type="number" name="mto_id" value="<?php echo $t['mto_id']; ?>">
                </div>
            </div>
            <div class="et-field">
                <label><?php echo $sv ? 'Webbsida (URL)' : 'Website (URL)'; ?></label>
                <input type="url" name="website_url" value="<?php echo htmlspecialchars($web_url); ?>" placeholder="https://...">
            </div>
            <div class="et-row3">
                <div class="et-field">
                    <label><?php echo $sv ? 'Klubbdeltagare' : 'Club participants'; ?></label>
                    <input type="number" name="club_participants" value="<?php echo $t['club_participants']; ?>" min="1">
                </div>
                <div class="et-field">
                    <label><?php echo $sv ? 'Totalt deltagare' : 'Total participants'; ?></label>
                    <input type="number" name="total_participants" value="<?php echo $t['total_participants']; ?>" min="1">
                </div>
                <div class="et-field">
                    <label><?php echo $sv ? 'Bästa klubbplacering' : 'Best club placement'; ?></label>
                    <input type="number" name="best_club_placement" value="<?php echo $t['best_club_placement']; ?>" min="1">
                </div>
            </div>
            <div class="et-field">
                <label><?php echo $sv ? 'Bästa klubbspelarens EMA-nr' : 'Best club player EMA number'; ?></label>
                <input type="text" name="best_club_ema" value="<?php echo htmlspecialchars($t['best_club_ema'] ?? ''); ?>" placeholder="09990170" maxlength="20">
            </div>
            <div class="et-field">
                <label><?php echo $sv ? 'Domare från klubben (EMA-nr, kommaseparerade)' : 'Club referees (EMA numbers, comma-separated)'; ?></label>
                <input type="text" name="referees_ema" value="<?php echo htmlspecialchars($t['referees_ema'] ?? ''); ?>" placeholder="09990170, 09990165" maxlength="200">
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" name="update_tournament" style="padding:8px 20px;background:#1976D2;color:white;border:none;border-radius:5px;cursor:pointer;">
                    💾 <?php echo $sv ? 'Spara' : 'Save'; ?>
                </button>
                <a href="external-tournaments.php" style="padding:8px 20px;background:#f5f5f5;border:1px solid #ccc;border-radius:5px;color:#333;">
                    <?php echo $sv ? 'Avbryt' : 'Cancel'; ?>
                </a>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- VIEW MODE -->
    <div class="et-card">
        <div class="et-card-name">
            <span class="fi fi-<?php echo $t['country_code']; ?>" style="margin-right:6px;"></span>
            <?php if ($web_url): ?>
                <a href="<?php echo htmlspecialchars($web_url); ?>" target="_blank" style="color:#005B99;"><?php echo htmlspecialchars($t['name']); ?></a>
            <?php else: ?>
                <?php echo htmlspecialchars($t['name']); ?>
            <?php endif; ?>
        </div>
        <div class="et-card-meta">
            <span>📍 <?php echo htmlspecialchars($t['city']); ?>, <?php
                $country_names_sv = ['se'=>'Sverige','dk'=>'Danmark','de'=>'Tyskland','fr'=>'Frankrike','it'=>'Italien','pl'=>'Polen','hu'=>'Ungern','nl'=>'Nederländerna','es'=>'Spanien','no'=>'Norge','fi'=>'Finland','gb'=>'Storbritannien','at'=>'Österrike','be'=>'Belgien','cz'=>'Tjeckien','ch'=>'Schweiz'];
                $country_names_en = ['se'=>'Sweden','dk'=>'Denmark','de'=>'Germany','fr'=>'France','it'=>'Italy','pl'=>'Poland','hu'=>'Hungary','nl'=>'Netherlands','es'=>'Spain','no'=>'Norway','fi'=>'Finland','gb'=>'United Kingdom','at'=>'Austria','be'=>'Belgium','cz'=>'Czech Republic','ch'=>'Switzerland'];
                $cc = strtolower($t['country_code']);
                echo $sv ? ($country_names_sv[$cc] ?? strtoupper($cc)) : ($country_names_en[$cc] ?? strtoupper($cc));
            ?></span>
            <span>📅 <?php echo $date_str; ?></span>
            <?php if ($t['club_participants']): ?><span>👥 <?php echo $t['club_participants']; ?>/<?php echo $t['total_participants'] ?? '?'; ?> <?php echo $sv ? 'spelare från klubben' : 'players from the club'; ?></span><?php endif; ?>
            <?php if ($t['best_club_placement']):
                $best_name = '';
                $best_ema = trim($t['best_club_ema'] ?? '');
                if ($best_ema) {
                    $bs = $conn->prepare("SELECT first_name, last_name FROM club_users WHERE ema_number = ? LIMIT 1");
                    $bs->execute([$best_ema]);
                    $br = $bs->fetch();
                    if ($br) $best_name = $br['first_name'] . ' ' . $br['last_name'];
                }
                $ema_profile_url = $best_ema ? 'https://mahjong-europe.org/ranking/Players/' . $best_ema . '.html' : '';
            ?>
            <span>🏆 <?php echo $sv ? 'Bäste klubb' : 'Best club'; ?>: #<?php echo $t['best_club_placement']; ?>
                <?php if ($best_name && $ema_profile_url): ?>
                    — <a href="<?php echo $ema_profile_url; ?>" target="_blank" style="color:#005B99;"><?php echo htmlspecialchars($best_name); ?></a>
                <?php elseif ($best_name): ?>
                    — <?php echo htmlspecialchars($best_name); ?>
                <?php endif; ?>
            </span>
            <?php endif; ?>
            <?php
            $refs_ema_str = trim($t['referees_ema'] ?? '');
            if ($refs_ema_str):
                $ref_emas = array_map('trim', explode(',', $refs_ema_str));
                $ref_names = [];
                foreach ($ref_emas as $rema) {
                    if (!$rema) continue;
                    $rs = $conn->prepare("SELECT first_name, last_name FROM club_users WHERE ema_number = ? LIMIT 1");
                    $rs->execute([$rema]);
                    $rr = $rs->fetch();
                    $rname = $rr ? $rr['first_name'] . ' ' . $rr['last_name'] : $rema;
                    $rurl = 'https://mahjong-europe.org/ranking/Players/' . $rema . '.html';
                    $ref_names[] = '<a href="' . $rurl . '" target="_blank" style="color:#005B99;">' . htmlspecialchars($rname) . '</a>';
                }
                if (!empty($ref_names)):
            ?>
            <span>⚖️ <?php echo $sv ? 'Domare' : 'Referees'; ?>: <?php echo implode(', ', $ref_names); ?></span>
            <?php endif; endif; ?>
        </div>
        <div class="et-card-actions">
            <a href="?edit=<?php echo $t['id']; ?>" style="background:#f5f5f5;color:#333;">✏️ <?php echo $sv ? 'Redigera' : 'Edit'; ?></a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $sv ? 'Radera denna turnering?' : 'Delete this tournament?'; ?>');">
                <input type="hidden" name="tournament_id" value="<?php echo $t['id']; ?>">
                <button type="submit" name="delete_tournament" style="background:#ffebee;color:#c62828;border-color:#ef9a9a;">🗑️ <?php echo $sv ? 'Radera' : 'Delete'; ?></button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php endforeach; ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@7.2.3/css/flag-icons.min.css">

<?php include 'includes/footer.php'; ?>
