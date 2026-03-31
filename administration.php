<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

// Kräv admin-behörighet
if (!hasRole('admin')) {
    showError('Du måste vara admin för att komma åt denna sida.');
}

$conn = getDbConnection();
$success = '';
$error = '';
$warning = '';

// HANTERA SETTINGS FÖR UTMÄRKELSER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_awards_settings'])) {
    $min_month = (int)$_POST['awards_min_month'];
    $min_quarter = (int)$_POST['awards_min_quarter'];
    $min_half = (int)$_POST['awards_min_half'];
    $min_year = (int)$_POST['awards_min_year'];
    
    // Validering
    if ($min_month < 1 || $min_month > 500) {
        $error = "Värde för månad måste vara mellan 1 och 500.";
    } elseif ($min_quarter < 1 || $min_quarter > 500) {
        $error = "Värde för kvartal måste vara mellan 1 och 500.";
    } elseif ($min_half < 1 || $min_half > 500) {
        $error = "Värde för halvår måste vara mellan 1 och 500.";
    } elseif ($min_year < 1 || $min_year > 1000) {
        $error = "Värde för helår måste vara mellan 1 och 1000.";
    } else {
        try {
            setSetting('awards_min_month', $min_month);
            setSetting('awards_min_quarter', $min_quarter);
            setSetting('awards_min_half', $min_half);
            setSetting('awards_min_year', $min_year);
            
            $success = "Inställningar för utmärkelser uppdaterade!";
        } catch (Exception $e) {
            $error = "Kunde inte spara inställningar: " . $e->getMessage();
        }
    }
}


// Hämta aktuellt år
$current_year = getCurrentYear();

// Räkna matcher för aktuellt år
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM stat_games WHERE deleted_at IS NULL AND game_year = ?");
$stmt->execute([$current_year]);
$current_games_count = $stmt->fetch()['count'];

// Hämta alla arkiverade år
$stmt = $conn->query("SELECT * FROM stat_archived_years ORDER BY year DESC");
$stat_archived_years = $stmt->fetchAll();

// HANTERA ARKIVERING
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_year'])) {
    $year_to_archive = (int)$_POST['year_to_archive'];
    
    if ($year_to_archive >= $current_year) {
        $error = "Du kan inte arkivera innevarande år eller framtida år.";
    } else {
        try {
            $conn->beginTransaction();
            
            // Kontrollera om året redan är arkiverat
            $stmt = $conn->prepare("SELECT id FROM stat_archived_years WHERE year = ?");
            $stmt->execute([$year_to_archive]);
            if ($stmt->fetch()) {
                throw new Exception("År $year_to_archive är redan arkiverat.");
            }
            
            // Räkna matcher för året
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM stat_games WHERE deleted_at IS NULL AND game_year = ?");
            $stmt->execute([$year_to_archive]);
            $games_count = $stmt->fetch()['count'];
            
            if ($games_count == 0) {
                throw new Exception("Inga matcher hittades för år $year_to_archive.");
            }
            
            // Räkna unika spelare
            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT player_id_ref) as count FROM (
                    SELECT player_a_id as player_id_ref FROM stat_games WHERE deleted_at IS NULL AND game_year = ?
                    UNION
                    SELECT player_b_id FROM stat_games WHERE deleted_at IS NULL AND game_year = ?
                    UNION
                    SELECT player_c_id FROM stat_games WHERE deleted_at IS NULL AND game_year = ?
                    UNION
                    SELECT player_d_id FROM stat_games WHERE deleted_at IS NULL AND game_year = ?
                ) as all_players
            ");
            $stmt->execute([$year_to_archive, $year_to_archive, $year_to_archive, $year_to_archive]);
            $players_count = $stmt->fetch()['count'];
            
            // Skapa arkivtabeller för året
            $archive_games_table = "archived_games_" . $year_to_archive;
            $archive_hands_table = "archived_game_hands_" . $year_to_archive;
            
            // Kopiera stat_games-tabellstrukturen
            $conn->exec("DROP TABLE IF EXISTS `$archive_games_table`");
            $conn->exec("CREATE TABLE `$archive_games_table` LIKE stat_games");
            
            // Kopiera data
            $stmt = $conn->prepare("INSERT INTO `$archive_games_table` SELECT * FROM stat_games WHERE game_year = ?");
            $stmt->execute([$year_to_archive]);
            
            // Kopiera stat_game_hands-tabellstrukturen
            $conn->exec("DROP TABLE IF EXISTS `$archive_hands_table`");
            $conn->exec("CREATE TABLE `$archive_hands_table` LIKE stat_game_hands");
            
            // Kopiera händer för matcherna
            $stmt = $conn->prepare("
                INSERT INTO `$archive_hands_table`
                SELECT gh.* FROM stat_game_hands gh
                INNER JOIN stat_games g ON gh.game_id = g.id
                WHERE g.game_year = ?
            ");
            $stmt->execute([$year_to_archive]);
            
            // Registrera arkiveringen
            $stmt = $conn->prepare("
                INSERT INTO stat_archived_years (year, archived_date, total_games, total_players)
                VALUES (?, NOW(), ?, ?)
            ");
            $stmt->execute([$year_to_archive, $games_count, $players_count]);
            
            // RADERA de arkiverade matcherna från stat_games och stat_game_hands
            // (stat_game_hands raderas automatiskt via CASCADE)
            $stmt = $conn->prepare("DELETE FROM stat_games WHERE game_year = ?");
            $stmt->execute([$year_to_archive]);
            
            $conn->commit();
            $success = "År $year_to_archive har arkiverats! $games_count matcher och $players_count spelare sparade.";
            
            // Uppdatera listan
            $stmt = $conn->query("SELECT * FROM stat_archived_years ORDER BY year DESC");
            $stat_archived_years = $stmt->fetchAll();
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Kunde inte arkivera: " . $e->getMessage();
        }
    }
}

// HANTERA RADERING AV ARKIV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_archive'])) {
    $year_to_delete = (int)$_POST['year_to_delete'];
    
    try {
        $conn->beginTransaction();
        
        // Ta bort tabellerna
        $archive_games_table = "archived_games_" . $year_to_delete;
        $archive_hands_table = "archived_game_hands_" . $year_to_delete;
        
        $conn->exec("DROP TABLE IF EXISTS `$archive_games_table`");
        $conn->exec("DROP TABLE IF EXISTS `$archive_hands_table`");
        
        // Ta bort registreringen
        $stmt = $conn->prepare("DELETE FROM stat_archived_years WHERE year = ?");
        $stmt->execute([$year_to_delete]);
        
        $conn->commit();
        $success = "Arkiv för år $year_to_delete har raderats permanent.";
        
        // Uppdatera listan
        $stmt = $conn->query("SELECT * FROM stat_archived_years ORDER BY year DESC");
        $stat_archived_years = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Kunde inte radera arkiv: " . $e->getMessage();
    }
}

// HANTERA ÅRSBYTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_year'])) {
    $new_year = (int)$_POST['new_year'];
    
    if ($new_year < 2024 || $new_year > 2050) {
        $error = "Ogiltigt år.";
    } elseif ($new_year == $current_year) {
        $error = "Detta är redan det aktuella året.";
    } else {
        try {
            // Varning om det finns matcher för innevarande år
            if ($new_year > $current_year && $current_games_count > 0) {
                $warning = "VARNING: Du har $current_games_count matcher registrerade för $current_year som INTE är arkiverade. Överväg att arkivera före årsbyte.";
            }
            
            $stmt = $conn->prepare("
                UPDATE stat_system_settings 
                SET setting_value = ? 
                WHERE setting_key = 'current_year'
            ");
            $stmt->execute([$new_year]);
            
            $current_year = $new_year;
            $success = "Systemet har bytts till år $new_year.";
            
        } catch (Exception $e) {
            $error = "Kunde inte byta år: " . $e->getMessage();
        }
    }
}

includeHeader();
?>
<style>
body[data-theme="dark"] { --sticky-bg: #252B38; }
</style>

<h2>🔧 Administration</h2>

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

<?php if ($warning): ?>
    <div class="message" style="background: #fff3cd; border: 1px solid #ffc107; color: #856404;">
        <strong>⚠️ VARNING:</strong><br>
        <?php echo htmlspecialchars($warning); ?>
    </div>
<?php endif; ?>

<!-- AKTUELLT ÅR -->
<div class="home-stat-box" style="background: #e8f5e9; padding: 25px; border-radius: 8px; border-left: 4px solid #4CAF50; margin: 30px 0;">
    <h3 style="margin-top: 0;">📅 Aktuellt statistikår</h3>
    <p style="font-size: 1.3em; font-weight: bold; color: #2c5f2d;">
        <?php echo $current_year; ?>
    </p>
    <p>
        <strong><?php echo $current_games_count; ?></strong> matcher registrerade för <?php echo $current_year; ?>
    </p>
</div>

<!-- BYTA ÅR -->
<div style="background: #fff3e0; padding: 25px; border-radius: 8px; border-left: 4px solid #FF9800; margin: 30px 0;">

<!-- INSTÄLLNINGAR VISNING STATISTIK -->
<div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 30px;">
    <h3 style="margin-top: 0;">⚙️ Inställningar visning statistik</h3>
    
    <p style="color: #666; margin-bottom: 20px;">
        Här anger du minimiantal matcher som krävs för att en spelare ska kunna bli ledare/vinnare för olika tidsperioder.
    </p>
    
    <form method="POST" style="max-width: 600px;">
        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">
                Visa ledare/vinnare per månad:
            </label>
            <input type="number" 
                   name="awards_min_month" 
                   min="1" 
                   max="500" 
                   value="<?php echo getSetting('awards_min_month', 5); ?>"
                   style="width: 100px; padding: 8px; font-size: 1em;"
                   required>
            <small style="color: #666; display: block; margin-top: 5px;">
                Minsta antal matcher (1-500). Rekommenderat: 5
            </small>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">
                Visa ledare/vinnare per kvartal:
            </label>
            <input type="number" 
                   name="awards_min_quarter" 
                   min="1" 
                   max="500" 
                   value="<?php echo getSetting('awards_min_quarter', 10); ?>"
                   style="width: 100px; padding: 8px; font-size: 1em;"
                   required>
            <small style="color: #666; display: block; margin-top: 5px;">
                Minsta antal matcher (1-500). Rekommenderat: 10
            </small>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">
                Visa ledare/vinnare per halvår:
            </label>
            <input type="number" 
                   name="awards_min_half" 
                   min="1" 
                   max="500" 
                   value="<?php echo getSetting('awards_min_half', 25); ?>"
                   style="width: 100px; padding: 8px; font-size: 1em;"
                   required>
            <small style="color: #666; display: block; margin-top: 5px;">
                Minsta antal matcher (1-500). Rekommenderat: 25
            </small>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">
                Visa ledare/vinnare per helår:
            </label>
            <input type="number" 
                   name="awards_min_year" 
                   min="1" 
                   max="1000" 
                   value="<?php echo getSetting('awards_min_year', 50); ?>"
                   style="width: 100px; padding: 8px; font-size: 1em;"
                   required>
            <small style="color: #666; display: block; margin-top: 5px;">
                Minsta antal matcher (1-1000). Rekommenderat: 50
            </small>
        </div>
        
        <button type="submit" name="update_awards_settings" class="btn" style="padding: 12px 30px;">
            💾 Spara inställningar
        </button>
    </form>
</div>

    <h3 style="margin-top: 0;">🔄 Byta år</h3>
    <p style="margin-bottom: 20px;">
        När ett nytt år börjar ska du först <strong>arkivera</strong> föregående år och sedan byta till det nya året.
    </p>
    
    <form method="POST" action="administration.php" 
          onsubmit="return confirm('Är du säker på att du vill byta statistikår? Detta påverkar vilka matcher som visas som aktuella.');">
        <div style="display: flex; gap: 15px; align-items: end;">
            <div class="form-group" style="margin: 0; flex: 0 0 200px;">
                <label for="new_year">Nytt år:</label>
                <select id="new_year" name="new_year">
                    <?php for ($y = $current_year - 2; $y <= $current_year + 3; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == ($current_year + 1) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" name="change_year" class="btn" style="background: #FF9800;">
                Byt till nytt år
            </button>
        </div>
    </form>
    
    <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.7); border-radius: 5px;">
        <strong>💡 Rekommenderad process vid årsbyte:</strong>
        <ol style="margin: 10px 0 0 20px; line-height: 1.8;">
            <li>Kontrollera att alla matcher för året är registrerade</li>
            <li>Arkivera året (se nedan)</li>
            <li>Byt till det nya året</li>
            <li>Börja registrera matcher för det nya året</li>
        </ol>
    </div>
</div>

<!-- ARKIVERING -->
<div style="background: #f3e5f5; padding: 25px; border-radius: 8px; border-left: 4px solid #9C27B0; margin: 30px 0;">
    <h3 style="margin-top: 0;">📦 Arkivera år</h3>
    
    <p style="margin-bottom: 20px;">
        Arkivering kopierar alla matcher och händer för ett år till separata arkivtabeller och tar sedan bort dem från den aktiva databasen. 
        Arkiverade matcher kan ses via sidan <a href="previous.php">Tidigare år</a>.
    </p>
    
    <form method="POST" action="administration.php"
          onsubmit="return confirm('VARNING: Arkivering kommer att ta bort alla matcher för det valda året från den aktiva databasen!\n\nMatcherna sparas i arkivet och kan ses via \'Tidigare år\'.\n\nÄr du säker?');">
        <div style="display: flex; gap: 15px; align-items: end;">
            <div class="form-group" style="margin: 0; flex: 0 0 200px;">
                <label for="year_to_archive">Välj år att arkivera:</label>
                <select id="year_to_archive" name="year_to_archive">
                    <?php 
                    // Visa bara år som har matcher och inte redan är arkiverade
                    $stmt = $conn->query("SELECT DISTINCT game_year FROM stat_games ORDER BY game_year DESC");
                    $years_with_games = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $archived_year_numbers = array_column($stat_archived_years, 'year');
                    
                    foreach ($years_with_games as $year):
                        if ($year != $current_year && !in_array($year, $archived_year_numbers)):
                    ?>
                        <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                    <?php 
                        endif;
                    endforeach; 
                    
                    if (empty($years_with_games) || (count($years_with_games) == 1 && $years_with_games[0] == $current_year)):
                    ?>
                        <option value="">Inga år att arkivera</option>
                    <?php endif; ?>
                </select>
            </div>
            <button type="submit" 
                    name="archive_year" 
                    class="btn" 
                    style="background: #9C27B0;"
                    <?php echo (empty($years_with_games) || (count($years_with_games) == 1 && $years_with_games[0] == $current_year)) ? 'disabled' : ''; ?>>
                🗃️ Arkivera år
            </button>
        </div>
    </form>
    
    <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.7); border-radius: 5px;">
        <strong>ℹ️ Vad händer vid arkivering?</strong>
        <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
            <li>Alla matcher kopieras till <code>archived_games_XXXX</code></li>
            <li>Alla händer kopieras till <code>archived_game_hands_XXXX</code></li>
            <li>Året registreras i <code>stat_archived_years</code></li>
            <li>Matcherna tas bort från aktiva tabellen <code>stat_games</code></li>
            <li>Arkiverade matcher syns via "Tidigare år" i menyn</li>
        </ul>
    </div>
</div>

<!-- ARKIVERADE ÅR -->
<h3 style="margin-top: 40px;">📚 Arkiverade år</h3>

<?php if (empty($stat_archived_years)): ?>
    <div class="message info">
        <p>Inga år har arkiverats än.</p>
    </div>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>År</th>
                    <th>Arkiveringsdatum</th>
                    <th>Antal matcher</th>
                    <th>Antal spelare</th>
                    <th>Åtgärder</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stat_archived_years as $archive): ?>
                <tr>
                    <td><strong><?php echo $archive['year']; ?></strong></td>
                    <td><?php echo formatSwedishDate($archive['archived_date']); ?></td>
                    <td><?php echo $archive['total_games']; ?></td>
                    <td><?php echo $archive['total_players']; ?></td>
                    <td>
                        <a href="previous.php?year=<?php echo $archive['year']; ?>" 
                           style="color: #2196F3; text-decoration: none; margin-right: 15px;">
                            👁️ Visa matcher
                        </a>
                        
                        <a href="#" 
                           onclick="if(confirm('VARNING: Detta kommer att radera PERMANENT alla arkiverade matcher för <?php echo $archive['year']; ?>!\n\nDenna åtgärd kan INTE ångras!\n\nÄr du absolut säker?')) { document.getElementById('delete-archive-<?php echo $archive['year']; ?>').submit(); } return false;"
                           style="color: #f44336; text-decoration: none;">
                            🗑️ Radera arkiv
                        </a>
                        
                        <form id="delete-archive-<?php echo $archive['year']; ?>" 
                              method="POST" 
                              style="display: none;">
                            <input type="hidden" name="year_to_delete" value="<?php echo $archive['year']; ?>">
                            <input type="hidden" name="delete_archive" value="1">
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- SYSTEMINFORMATION -->
<div style="margin-top: 50px; padding: 25px; background: #f9f9f9; border-radius: 8px;">
    <h3>ℹ️ Systeminformation</h3>
    
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px;">
        <div>
            <h4>Databas</h4>
            <p><strong>Host:</strong> <?php echo DB_HOST; ?></p>
            <p><strong>Database:</strong> <?php echo DB_NAME; ?></p>
            <p><strong>Charset:</strong> <?php echo DB_CHARSET; ?></p>
        </div>
        
        <div>
            <h4>Statistik</h4>
            <?php
            $stmt = $conn->query("SELECT COUNT(*) as count FROM stat_players");
            $total_players = $stmt->fetch()['count'];
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM stat_users");
            $total_users = $stmt->fetch()['count'];
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM stat_games");
            $total_games = $stmt->fetch()['count'];
            ?>
            <p><strong>Totalt antal spelare:</strong> <?php echo $total_players; ?></p>
            <p><strong>Totalt antal användare:</strong> <?php echo $total_users; ?></p>
            <p><strong>Aktiva matcher:</strong> <?php echo $total_games; ?></p>
        </div>
    </div>
</div>

<div style="margin-top: 30px; text-align: center;">
    <a href="home.php" class="btn btn-secondary">← Tillbaka till startsidan</a>
</div>

<?php includeFooter(); ?>
