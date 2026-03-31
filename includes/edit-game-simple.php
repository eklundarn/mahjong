<!-- Detta är en include-fil för edit-game.php - SLUTRESULTAT -->

<h2>Redigera Match <?php echo $game['game_number']; ?> (<?php echo $game['game_year']; ?>)</h2>

<div style="margin: 20px 0;">
    <a href="games.php" class="btn btn-secondary">← Tillbaka till matcher</a>
    <a href="view-game.php?id=<?php echo $game['id']; ?>" class="btn btn-secondary">👁️ Visa match</a>
    
    <div style="float: right;">
        <strong>Sortera spelare:</strong>
        <a href="?id=<?php echo $game_id; ?>&player_sort=name" 
           class="btn <?php echo (!isset($_GET['player_sort']) || $_GET['player_sort'] === 'name') ? '' : 'btn-secondary'; ?>" 
           style="padding: 8px 15px; font-size: 0.9em;">Efter namn</a>
        <a href="?id=<?php echo $game_id; ?>&player_sort=number" 
           class="btn <?php echo (isset($_GET['player_sort']) && $_GET['player_sort'] === 'number') ? '' : 'btn-secondary'; ?>" 
           style="padding: 8px 15px; font-size: 0.9em;">Efter Spelar-ID</a>
    </div>
    <div style="clear: both;"></div>
</div>

<?php if ($success): ?>
    <div class="message success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="message error"><?php echo $error; ?></div>
<?php endif; ?>

<form method="POST" action="edit-game.php?id=<?php echo $game_id; ?>">
    
    <fieldset style="border: 2px solid #4CAF50; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em; color: #2c5f2d;">Matchinformation</legend>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="game_date">Datum: *</label>
                <input type="date" id="game_date" name="game_date" required
                       value="<?php echo $game['game_date']; ?>">
            </div>
            
            <div class="form-group">
                <label for="game_number">Matchnummer: *</label>
                <input type="number" id="game_number" name="game_number" required min="1"
                       value="<?php echo $game['game_number']; ?>">
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="biggest_hand">Matchens största hand (poäng):</label>
                <input type="number" id="biggest_hand" name="biggest_hand" min="0"
                       value="<?php echo $game['biggest_hand_points']; ?>">
            </div>
            
            <div class="form-group">
                <label for="biggest_hand_player">Spelare som fick största hand:</label>
                <select id="biggest_hand_player" name="biggest_hand_player">
                    <option value="">-- Välj spelare --</option>
                    <?php foreach ($all_players as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['id']); ?>"
                                <?php echo ($game['biggest_hand_player_id'] === $p['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['id'] . ' - ' . $p['first_name'] . ' ' . $p['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </fieldset>
    
    <!-- GODKÄNNANDE (endast för admin) -->
    <?php if (hasRole('admin')): ?>
    <fieldset style="border: 2px solid <?php echo $game['approved'] ? '#4CAF50' : '#ff9800'; ?>; padding: 20px; margin: 20px 0; border-radius: 5px; background: <?php echo $game['approved'] ? '#e8f5e9' : '#fff3e0'; ?>;">
        <legend style="font-weight: bold; font-size: 1.2em; color: <?php echo $game['approved'] ? '#2e7d32' : '#ef6c00'; ?>;">
            <?php echo $game['approved'] ? '✓ Godkännande' : '⏳ Godkännande'; ?>
        </legend>
        
        <?php if ($game['approved']): ?>
            <!-- Redan godkänd -->
            <div style="padding: 15px;">
                <p style="font-weight: bold; color: #2e7d32; font-size: 1.1em;">
                    ✓ Godkänd av admin
                </p>
                <?php if (!empty($game['approved_at']) && !empty($game['approved_by_id'])): ?>
                    <?php 
                    // Hämta admin-namn
                    $stmt_approver = $conn->prepare("SELECT first_name, last_name FROM stat_players WHERE id = ?");
                    $stmt_approver->execute([$game['approved_by_id']]);
                    $approver = $stmt_approver->fetch();
                    
                    $approved_dt = new DateTime($game['approved_at']);
                    ?>
                    <p style="color: #666; margin-top: 10px;">
                        <?php echo $approved_dt->format('Y-m-d'); ?> kl <?php echo $approved_dt->format('H:i'); ?>
                        <?php if ($approver): ?>
                            (<?php echo htmlspecialchars($approver['first_name'] . ' ' . $approver['last_name']); ?>)
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Väntar på godkännande -->
            <div class="form-group" style="padding: 15px;">
                <label style="display: flex; align-items: center; cursor: pointer; font-size: 1.1em;">
                    <input type="checkbox" name="approve_game" value="1" 
                           style="width: 24px; height: 24px; margin-right: 12px; cursor: pointer;">
                    <strong>Godkänn resultatet</strong>
                </label>
                <p style="margin: 10px 0 0 36px; color: #666; font-size: 0.95em;">
                    När du kryssar i denna ruta och sparar kommer matchen att godkännas och räknas med i statistiken.
                </p>
            </div>
        <?php endif; ?>
    </fieldset>
    <?php endif; ?>
    
    <?php 
    $player_labels = ['A', 'B', 'C', 'D'];
    $player_colors = ['#667eea', '#f093fb', '#4facfe', '#43e97b'];
    
    foreach ($player_labels as $index => $label): 
        $player_var = 'player_' . strtolower($label);
        $id_key = 'player_' . strtolower($label) . '_id';
    ?>
    
    <fieldset style="border: 2px solid <?php echo $player_colors[$index]; ?>; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em; color: <?php echo $player_colors[$index]; ?>;">
            Spelare <?php echo $label; ?>
        </legend>
        
        <div class="form-group">
            <label for="<?php echo $player_var; ?>">Välj spelare: *</label>
            <select id="<?php echo $player_var; ?>" 
                    name="<?php echo $player_var; ?>" 
                    required
                    onchange="updatePlayerName('<?php echo strtolower($label); ?>'); filterPlayers();">
                <option value="">-- Välj spelare --</option>
                <?php foreach ($all_players as $p): ?>
                    <option value="<?php echo htmlspecialchars($p['id']); ?>"
                            data-name="<?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>"
                            <?php echo ($game[$id_key] === $p['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['id'] . ' - ' . $p['first_name'] . ' ' . $p['last_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div id="name_display_<?php echo strtolower($label); ?>" 
             style="margin-top: 10px; padding: 10px; background: rgba(0,0,0,0.05); border-radius: 5px; font-weight: bold;">
        </div>
    </fieldset>
    
    <?php endforeach; ?>
    
    <!-- MATCHINFORMATION -->
    <fieldset style="border: 2px solid #9c27b0; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;">Matchinformation</legend>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="antal_hander">Antal spelade händer:</label>
                <input type="number" 
                       id="antal_hander" 
                       name="antal_hander" 
                       min="0"
                       max="16"
                       value="<?php echo $game['hands_played'] ?? 0; ?>">
            </div>
            
            <div class="form-group">
                <label for="biggest_hand">Största hand (poäng):</label>
                <input type="number" 
                       id="biggest_hand" 
                       name="biggest_hand" 
                       min="0"
                       value="<?php echo $game['biggest_hand_points'] ?? 0; ?>">
            </div>
            
            <div class="form-group">
                <label for="biggest_hand_player">Spelare med största hand:</label>
                <select id="biggest_hand_player" name="biggest_hand_player">
                    <option value="">-- Välj spelare --</option>
                    <?php 
                    $stmt_players = $conn->prepare("SELECT id, first_name, last_name FROM stat_players ORDER BY id");
                    $stmt_players->execute();
                    $allplayers = $stmt_players->fetchAll();
                    foreach ($allplayers as $p): 
                    ?>
                        <option value="<?php echo $p['id']; ?>"
                                <?php echo (isset($game['biggest_hand_player_id']) && $game['biggest_hand_player_id'] === $p['id']) ? 'selected' : ''; ?>>
                            <?php echo $p['id'] . ' - ' . $p['first_name'] . ' ' . $p['last_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </fieldset>
    
    <!-- HU-STATISTIK -->
    <fieldset style="border: 2px solid #4CAF50; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;">🎯 Hu-statistik</legend>
        
        <div style="margin-bottom: 20px;">
            <div class="form-group">
                <label for="zero_rounds">Antal 0-rundor i matchen:</label>
                <input type="number" 
                       id="zero_rounds" 
                       name="zero_rounds" 
                       min="0"
                       max="16"
                       value="<?php echo $game['zero_rounds'] ?? 0; ?>"
                       style="width: 150px;">
                <small style="color: #666; display: block; margin-top: 5px;">
                    Antal rundor där ingen vann (alla fick 0 minipoäng)
                </small>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
            <?php foreach ($player_labels as $label): 
                $hu_key = 'player_' . strtolower($label) . '_hu';
                $selfdrawn_key = 'player_' . strtolower($label) . '_selfdrawn';
                $thrown_key = 'player_' . strtolower($label) . '_thrown_hu';
            ?>
            <div>
                <strong id="hu_player_header_<?php echo strtolower($label); ?>">Spelare <?php echo $label; ?></strong>
                <div style="font-size: 0.9em; color: #666; margin-bottom: 10px;" id="hu_player_id_ref_<?php echo strtolower($label); ?>"></div>
                
                <div style="margin: 10px 0;">
                    <label style="font-size: 0.9em;">Antal hu:</label>
                    <input type="number" 
                           name="hu_<?php echo strtolower($label); ?>" 
                           id="hu_<?php echo strtolower($label); ?>"
                           min="0"
                           value="<?php echo $game[$hu_key] ?? 0; ?>"
                           style="width: 100%; padding: 5px;">
                    <small style="color: #666; font-size: 0.85em;">Vunna händer</small>
                </div>
                
                <div style="margin: 10px 0;">
                    <label style="font-size: 0.9em;">Varav selfdrawn:</label>
                    <input type="number" 
                           name="selfdrawn_<?php echo strtolower($label); ?>" 
                           id="selfdrawn_<?php echo strtolower($label); ?>"
                           min="0"
                           value="<?php echo $game[$selfdrawn_key] ?? 0; ?>"
                           style="width: 100%; padding: 5px;">
                    <small style="color: #666; font-size: 0.85em;">Självdragna av totala hu</small>
                </div>
                
                <div style="margin: 10px 0;">
                    <label style="font-size: 0.9em;">Kastade hu:</label>
                    <input type="number" 
                           name="thrown_hu_<?php echo strtolower($label); ?>" 
                           id="thrown_hu_<?php echo strtolower($label); ?>"
                           min="0"
                           value="<?php echo $game[$thrown_key] ?? 0; ?>"
                           style="width: 100%; padding: 5px;">
                    <small style="color: #666; font-size: 0.85em;">Kastade till andra</small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </fieldset>
    
    <!-- MINIPOÄNG, PENALTIES OCH FALSE HU -->
    <fieldset style="border: 2px solid #ff9800; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;">Minipoäng, Penalties och False Hu</legend>
        
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
            <?php 
            foreach ($player_labels as $label):
                $mini_key = 'player_' . strtolower($label) . '_minipoints';
                $penalty_key = 'player_' . strtolower($label) . '_penalties';
                $false_hu_key = 'player_' . strtolower($label) . '_false_hu';
                
                // I databasen sparas minipoints EFTER penalties och false_hu
                // För att visa ursprungsvärdet måste vi räkna baklänges
                $stored_mini = $game[$mini_key];
                $penalty = $game[$penalty_key] ?? 0;
                $false_hu = $game[$false_hu_key] ?? 0;
                
                // Räkna baklänges: stored = original - penalty + false_hu
                // Alltså: original = stored + penalty - false_hu
                $original_mini = $stored_mini + abs($penalty) - $false_hu;
            ?>
            <div>
                <strong id="player_header_<?php echo strtolower($label); ?>">Spelare <?php echo $label; ?></strong>
                <div style="font-size: 0.9em; color: #666; margin-bottom: 10px;" id="player_id_ref_<?php echo strtolower($label); ?>"></div>
                <div style="margin: 10px 0;">
                    <label style="font-size: 0.9em;">Minipoäng: *</label>
                    <input type="number" 
                           name="minipoints_<?php echo strtolower($label); ?>" 
                           id="minipoints_<?php echo strtolower($label); ?>"
                           required
                           value="<?php echo round($original_mini); ?>"
                           onchange="calculateTotals()"
                           style="width: 100%; padding: 8px; font-size: 1.1em; font-weight: bold;">
                </div>
                <div style="margin: 10px 0;">
                    <label style="font-size: 0.9em;">Penalties (subtraheras alltid):</label>
                    <input type="number" 
                           name="penalty_<?php echo strtolower($label); ?>" 
                           id="penalty_<?php echo strtolower($label); ?>"
                           value="<?php echo $penalty; ?>"
                           onchange="calculateTotals()"
                           style="width: 100%; padding: 5px;">
                </div>
                <div style="margin: 10px 0;">
                    <label style="font-size: 0.9em;">False Hu:</label>
                    <input type="number" 
                           name="false_hu_<?php echo strtolower($label); ?>" 
                           id="false_hu_<?php echo strtolower($label); ?>"
                           value="<?php echo $false_hu; ?>"
                           onchange="calculateTotals()"
                           style="width: 100%; padding: 5px;">
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </fieldset>
    
    <!-- BERÄKNADE TOTALER -->
    <fieldset style="border: 2px solid #9c27b0; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;">Slutsumma</legend>
        
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; text-align: center;">
            <?php foreach ($player_labels as $label): ?>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                <div style="font-weight: bold; margin-bottom: 5px;" id="total_player_header_<?php echo strtolower($label); ?>">Spelare <?php echo $label; ?></div>
                <div style="font-size: 0.9em; color: #666; margin-bottom: 10px;" id="total_player_id_ref_<?php echo strtolower($label); ?>"></div>
                
                <div style="margin: 10px 0;">
                    <div style="font-size: 0.8em; color: #666;">Totala minipoäng:</div>
                    <div style="font-size: 1.8em; font-weight: bold; color: #2c5f2d;" 
                         id="total_mini_<?php echo strtolower($label); ?>">0</div>
                </div>
                
                <div style="margin: 10px 0; padding-top: 10px; border-top: 1px solid #ddd;">
                    <div style="font-size: 0.8em; color: #666;">Bordspoäng:</div>
                    <div style="font-size: 1.5em; color: #2196F3; font-weight: bold;" 
                         id="table_<?php echo strtolower($label); ?>">0</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div id="validation_warning" style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px; display: none;">
            <strong>⚠️ Varning:</strong> <span id="validation_text"></span>
        </div>
    </fieldset>
    
    <!-- GODKÄNNANDE (endast för admin/superuser) -->
    <?php if (hasRole('admin') && $game['approved'] == 0): ?>
    <fieldset style="border: 2px solid #4CAF50; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em; color: #4CAF50;">✅ Godkännande</legend>
        <div style="background: #e8f5e9; padding: 15px; border-radius: 5px;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 1.1em;">
                <input type="checkbox" name="approve_game" value="1" style="width: 20px; height: 20px;">
                <span>
                    <strong>Godkänn denna match</strong><br>
                    <small style="color: #666;">
                        Matchen är registrerad av 
                        <?php 
                        if (!empty($game['creator_first']) && !empty($game['creator_last'])) {
                            echo htmlspecialchars($game['creator_first'] . ' ' . $game['creator_last']);
                        } else {
                            echo 'okänd användare';
                        }
                        ?>
                        och väntar på godkännande.
                    </small>
                </span>
            </label>
        </div>
    </fieldset>
    <?php elseif ($game['approved'] == 1): ?>
    <div style="background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #4CAF50;">
        <strong>✅ Godkänd match</strong><br>
        <small style="color: #666;">
            <?php if (!empty($game['approver_first']) && !empty($game['approver_last'])): ?>
                Godkänd av <?php echo htmlspecialchars($game['approver_first'] . ' ' . $game['approver_last']); ?>
            <?php else: ?>
                Godkänd
            <?php endif; ?>
            <?php if (!empty($game['approved_at'])): ?>
                <?php 
                $approved = new DateTime($game['approved_at']);
                echo $approved->format('Y-m-d H:i');
                ?>
            <?php endif; ?>
        </small>
    </div>
    <?php endif; ?>
    
    <div class="form-group" style="margin-top: 30px;">
        <button type="submit" name="update_game" class="btn" style="font-size: 1.1em; padding: 15px 30px;">
            💾 Spara ändringar
        </button>
        <a href="games.php" class="btn btn-secondary" style="margin-left: 10px;">Avbryt</a>
    </div>
<script>
function updatePlayerLabels() {
    ['a', 'b', 'c', 'd'].forEach(function(player) {
        const select = document.getElementById('player_' + player);
        const header = document.getElementById('player_header_' + player);
        const vms = document.getElementById('player_id_ref_' + player);
        const huHeader = document.getElementById('hu_player_header_' + player);
        const huVms = document.getElementById('hu_player_id_ref_' + player);
        const totalHeader = document.getElementById('total_player_header_' + player);
        const totalVms = document.getElementById('total_player_id_ref_' + player);
        
        if (select) {
            const option = select.options[select.selectedIndex];
            let nameText, vmsText;
            
            if (option && option.value) {
                // Format: "VMS0076 - Anna Andersson Åkerblom"
                const parts = option.text.split(' - ');
                if (parts.length === 2) {
                    nameText = parts[1]; // Namn
                    vmsText = parts[0];  // Spelar-ID
                } else {
                    nameText = option.text;
                    vmsText = '';
                }
            } else {
                nameText = 'Spelare ' + player.toUpperCase();
                vmsText = '';
            }
            
            // Uppdatera False Hu-sektionen
            if (header) header.textContent = nameText;
            if (vms) vms.textContent = vmsText;
            
            // Uppdatera Hu-statistik-sektionen
            if (huHeader) huHeader.textContent = nameText;
            if (huVms) huVms.textContent = vmsText;
            
            // Uppdatera Totaler-sektionen
            if (totalHeader) totalHeader.textContent = nameText;
            if (totalVms) totalVms.textContent = vmsText;
        }
    });
}

// Lyssna på ändringar
document.addEventListener('DOMContentLoaded', function() {
    ['a', 'b', 'c', 'd'].forEach(function(player) {
        const select = document.getElementById('player_' + player);
        if (select) {
            select.addEventListener('change', updatePlayerLabels);
        }
    });
    updatePlayerLabels(); // Initial uppdatering
});
</script>
</form>

<script>
function updatePlayerName(player) {
    const select = document.getElementById('player_' + player);
    const displayDiv = document.getElementById('name_display_' + player);
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        const name = selectedOption.getAttribute('data-name');
        displayDiv.innerHTML = '👤 ' + name;
    } else {
        displayDiv.innerHTML = '';
    }
}

function filterPlayers() {
    const selectedPlayers = [
        document.getElementById('player_a').value,
        document.getElementById('player_b').value,
        document.getElementById('player_c').value,
        document.getElementById('player_d').value
    ].filter(v => v !== '');
    
    ['a', 'b', 'c', 'd'].forEach(function(playerLetter) {
        const select = document.getElementById('player_' + playerLetter);
        const currentValue = select.value;
        
        Array.from(select.options).forEach(function(option) {
            if (option.value === '') return;
            
            if (selectedPlayers.includes(option.value) && option.value !== currentValue) {
                option.disabled = true;
                option.style.color = '#ccc';
                if (!option.text.includes('[VALD]')) {
                    option.text = option.text + ' [VALD]';
                }
            } else {
                option.disabled = false;
                option.style.color = '';
                option.text = option.text.replace(' [VALD]', '');
            }
        });
    });
}

function calculateTotals() {
    const stat_players = ['a', 'b', 'c', 'd'];
    
    // Hämta värden
    const minipoints = stat_players.map(p => parseInt(document.getElementById('minipoints_' + p).value) || 0);
    const penalties = stat_players.map(p => parseInt(document.getElementById('penalty_' + p).value) || 0);
    const falseHu = stat_players.map(p => parseInt(document.getElementById('false_hu_' + p).value) || 0);
    
    // Applicera straff (alltid minus) och false hu (direkt värde)
    const totalMini = minipoints.map((m, i) => {
        return m - Math.abs(penalties[i]) + falseHu[i];
    });
    
    // Visa totala minipoäng
    stat_players.forEach((p, i) => {
        document.getElementById('total_mini_' + p).textContent = Math.round(totalMini[i]);
    });
    
    // Validera nollsummespel för MINIPOINTS (före penalties/false_hu)
    const miniSum = minipoints.reduce((a, b) => a + b, 0);
    const warningDiv = document.getElementById('validation_warning');
    const warningText = document.getElementById('validation_text');
    
    if (miniSum !== 0) {
        warningText.textContent = 'Minipoängen (före penalties/false hu) summerar till ' + miniSum + ' istället för 0!';
        warningDiv.style.display = 'block';
    } else {
        warningDiv.style.display = 'none';
    }
    
    // Beräkna bordspoäng (baserat på minipoäng FÖRE adjustments)
    let playersData = minipoints.map((mini, idx) => ({mini, idx}));
    playersData.sort((a, b) => b.mini - a.mini);
    
    const tablePointsBase = [4, 2, 1, 0];
    let tablePoints = [0, 0, 0, 0];
    
    for (let i = 0; i < 4; i++) {
        let sameCount = 1;
        let sumPoints = tablePointsBase[i];
        
        for (let j = i + 1; j < 4; j++) {
            if (playersData[j].mini === playersData[i].mini) {
                sameCount++;
                sumPoints += tablePointsBase[j];
            } else {
                break;
            }
        }
        
        const shared = sumPoints / sameCount;
        for (let k = 0; k < sameCount; k++) {
            tablePoints[playersData[i + k].idx] = shared;
        }
        i += (sameCount - 1);
    }
    
    stat_players.forEach((p, i) => {
        document.getElementById('table_' + p).textContent = tablePoints[i].toFixed(1);
    });
}

window.addEventListener('DOMContentLoaded', function() {
    ['a', 'b', 'c', 'd'].forEach(function(player) {
        updatePlayerName(player);
    });
    filterPlayers();
    calculateTotals();
});
</script>