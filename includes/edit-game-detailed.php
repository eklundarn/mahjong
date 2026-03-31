<!-- Detta är en include-fil för edit-game.php - HAND-FÖR-HAND -->
<style>
.hand-row {
    display: grid;
    grid-template-columns: 80px 120px 120px 120px 1fr;
    gap: 10px;
    align-items: center;
    padding: 8px;
    border-bottom: 1px solid #eee;
}
.hand-row:hover {
    background: #f9f9f9;
}
.hand-input {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 100%;
}
.calculated-points {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 5px;
    font-size: 0.9em;
}
.point-box {
    padding: 5px;
    text-align: center;
    border-radius: 3px;
    font-weight: bold;
}
.point-positive { background: #c8e6c9; color: #2e7d32; }
.point-negative { background: #ffcdd2; color: #c62828; }
.point-zero { background: #f5f5f5; color: #757575; }
</style>

<h2>Redigera Match <?php echo $game['game_number']; ?> (<?php echo $game['game_year']; ?>) - Hand-för-hand</h2>

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

<form method="POST" action="edit-game.php?id=<?php echo $game_id; ?>" id="gameForm">
    
    <fieldset style="border: 2px solid #4CAF50; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;">Matchinformation</legend>
        
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
    </fieldset>
    
    <fieldset style="border: 2px solid #2196F3; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;">Välj spelare</legend>
        
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
            <?php 
            $player_labels = ['A', 'B', 'C', 'D'];
            foreach ($player_labels as $label): 
                $player_var = 'player_' . strtolower($label);
                $id_key = $player_var . '_id';
            ?>
            <div>
                <label for="<?php echo $player_var; ?>"><strong>Spelare <?php echo $label; ?>:</strong> *</label>
                <select id="<?php echo $player_var; ?>" 
                        name="<?php echo $player_var; ?>" 
                        required
                        onchange="filterPlayers(); updateTotals();"
                        style="width: 100%;">
                    <option value="">-- Välj --</option>
                    <?php foreach ($all_players as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['id']); ?>"
                                <?php echo ($game[$id_key] === $p['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['id'] . ' - ' . $p['first_name'] . ' ' . $p['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endforeach; ?>
        </div>
    </fieldset>
    
    <fieldset style="border: 2px solid #f5576c; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;">Händer (max 16)</legend>
        
        <div style="font-weight: bold; margin-bottom: 10px;" class="hand-row">
            <div>Hand #</div>
            <div>Hu-poäng</div>
            <div>Vinnare (1-4)</div>
            <div>Från vem?</div>
            <div>Beräknade poäng</div>
        </div>
        
        <?php for ($h = 1; $h <= 16; $h++): 
            $hand_data = isset($hands[$h]) ? $hands[$h] : null;
        ?>
        <div class="hand-row">
            <div><strong>Hand <?php echo $h; ?></strong></div>
            <div>
                <input type="number" 
                       name="hand_<?php echo $h; ?>_hu" 
                       id="hand_<?php echo $h; ?>_hu"
                       class="hand-input" 
                       min="0" 
                       placeholder=""
                       value="<?php echo $hand_data ? $hand_data['hu_points'] : ''; ?>"
                       onchange="updateTotals()">
            </div>
            <div>
                <select name="hand_<?php echo $h; ?>_player" 
                        id="hand_<?php echo $h; ?>_player"
                        class="hand-input"
                        onchange="updateTotals()">
                    <option value="" <?php echo (!$hand_data) ? 'selected' : ''; ?>>-</option>
                    <option value="zero" <?php echo ($hand_data && $hand_data['winning_player'] == 0 && $hand_data['hu_points'] == 0) ? 'selected' : ''; ?>>0-runda</option>
                    <option value="1" <?php echo ($hand_data && $hand_data['winning_player'] == 1) ? 'selected' : ''; ?>>1 (A)</option>
                    <option value="2" <?php echo ($hand_data && $hand_data['winning_player'] == 2) ? 'selected' : ''; ?>>2 (B)</option>
                    <option value="3" <?php echo ($hand_data && $hand_data['winning_player'] == 3) ? 'selected' : ''; ?>>3 (C)</option>
                    <option value="4" <?php echo ($hand_data && $hand_data['winning_player'] == 4) ? 'selected' : ''; ?>>4 (D)</option>
                </select>
            </div>
            <div>
                <select name="hand_<?php echo $h; ?>_from" 
                        id="hand_<?php echo $h; ?>_from"
                        class="hand-input"
                        onchange="updateTotals()">
                    <option value="" <?php echo (!$hand_data) ? 'selected' : ''; ?>>-- Välj från vem --</option>
                    <option value="0" <?php echo ($hand_data && $hand_data['from_player'] == 0) ? 'selected' : ''; ?>>Muren (självdragen)</option>
                    <option value="1" <?php echo ($hand_data && $hand_data['from_player'] == 1) ? 'selected' : ''; ?>>1 (A)</option>
                    <option value="2" <?php echo ($hand_data && $hand_data['from_player'] == 2) ? 'selected' : ''; ?>>2 (B)</option>
                    <option value="3" <?php echo ($hand_data && $hand_data['from_player'] == 3) ? 'selected' : ''; ?>>3 (C)</option>
                    <option value="4" <?php echo ($hand_data && $hand_data['from_player'] == 4) ? 'selected' : ''; ?>>4 (D)</option>
                </select>
            </div>
            <div id="hand_<?php echo $h; ?>_calc" class="calculated-points"></div>
        </div>
        <?php endfor; ?>
        
        <!-- SUMMERINGSRAD - Visar totalen från händerna INNAN penalties/false_hu -->
        <div style="margin-top: 15px; padding-top: 10px; border-top: 2px solid #4CAF50;">
            <div class="hand-row" style="font-weight: bold;">
                <div style="font-size: 0.85em; color: #2c5f2d;" id="summary_label">Efter 0 spelade händer</div>
                <div></div>
                <div></div>
                <div></div>
                <div class="calculated-points">
                    <div class="point-box point-zero" id="summary_a">0</div>
                    <div class="point-box point-zero" id="summary_b">0</div>
                    <div class="point-box point-zero" id="summary_c">0</div>
                    <div class="point-box point-zero" id="summary_d">0</div>
                </div>
            </div>
        </div>
    </fieldset>
    
    <fieldset style="border: 2px solid #ff9800; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;">Straff och False Hu</legend>
        
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
            <?php foreach ($player_labels as $label): 
                $penalty_key = 'player_' . strtolower($label) . '_penalties';
                $false_hu_key = 'player_' . strtolower($label) . '_false_hu';
            ?>
            <div>
                <strong id="player_header_<?php echo strtolower($label); ?>">Spelare <?php echo $label; ?></strong>
                <div style="font-size: 0.9em; color: #666; margin-bottom: 10px;" id="player_id_ref_<?php echo strtolower($label); ?>"></div>
                <div style="margin: 10px 0;">
                    <label style="font-size: 0.9em;">Penalties (subtraheras alltid):</label>
                    <input type="number" 
                           name="penalty_<?php echo strtolower($label); ?>" 
                           id="penalty_<?php echo strtolower($label); ?>"
                           value="<?php echo $game[$penalty_key]; ?>"
                           onchange="updateTotals()"
                           style="width: 100%; padding: 5px;">
                </div>
                <div style="margin: 10px 0;">
                    <label style="font-size: 0.9em;">False Hu:</label>
                    <input type="number" 
                           name="false_hu_<?php echo strtolower($label); ?>" 
                           id="false_hu_<?php echo strtolower($label); ?>"
                           value="<?php echo $game[$false_hu_key]; ?>"
                           onchange="updateTotals()"
                           style="width: 100%; padding: 5px;">
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </fieldset>
    
    <fieldset style="border: 2px solid #9c27b0; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <legend style="font-weight: bold; font-size: 1.2em;">Slutsumma</legend>
        
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; text-align: center;">
            <?php foreach ($player_labels as $label): ?>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                <div style="font-weight: bold; margin-bottom: 5px;" id="total_player_header_<?php echo strtolower($label); ?>">Spelare <?php echo $label; ?></div>
                <div style="font-size: 0.9em; color: #666; margin-bottom: 10px;" id="total_player_id_ref_<?php echo strtolower($label); ?>"></div>
                <div style="font-size: 2em; font-weight: bold; color: #2c5f2d;" 
                     id="total_<?php echo strtolower($label); ?>">0</div>
                <div style="font-size: 0.8em; color: #666;">minipoäng</div>
                <div style="margin-top: 10px; font-size: 1.5em; color: #2196F3;" 
                     id="table_<?php echo strtolower($label); ?>">0</div>
                <div style="font-size: 0.8em; color: #666;">bordspoäng</div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div id="biggest_hand_info" style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px; text-align: center; display: none;">
            <strong>🏆 Största hand:</strong> <span id="biggest_hand_text"></span>
        </div>
    </fieldset>
    
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
        const totalHeader = document.getElementById('total_player_header_' + player);
        const totalVms = document.getElementById('total_player_id_ref_' + player);
        
        if (select) {
            const option = select.options[select.selectedIndex];
            let nameText, vmsText;
            
            if (option && option.value) {
                const parts = option.text.split(' - ');
                if (parts.length === 2) {
                    nameText = parts[1];
                    vmsText = parts[0];
                } else {
                    nameText = option.text;
                    vmsText = '';
                }
            } else {
                nameText = 'Spelare ' + player.toUpperCase();
                vmsText = '';
            }
            
            // Uppdatera Straff-sektionen
            if (header) header.textContent = nameText;
            if (vms) vms.textContent = vmsText;
            
            // Uppdatera Totaler-sektionen
            if (totalHeader) totalHeader.textContent = nameText;
            if (totalVms) totalVms.textContent = vmsText;
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    ['a', 'b', 'c', 'd'].forEach(function(player) {
        const select = document.getElementById('player_' + player);
        if (select) {
            select.addEventListener('change', function() {
                updatePlayerLabels();
                filterPlayers();
                updateTotals();
            });
        }
    });
    updatePlayerLabels();
    filterPlayers();
    updateTotals();
});
</script>
</form>

<script>
// Samma JavaScript som i newgame-b.php
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

function updateFromPlayerOptions(handNumber, winningPlayer) {
    const fromSelect = document.getElementById('hand_' + handNumber + '_from');
    const currentValue = fromSelect.value;
    
    Array.from(fromSelect.options).forEach(function(option) {
        if (option.value === '' || option.value === '0') {
            option.disabled = false;
            option.style.color = '';
        } else {
            const playerNum = parseInt(option.value);
            if (playerNum === winningPlayer) {
                option.disabled = true;
                option.style.color = '#ccc';
                if (currentValue === option.value) {
                    fromSelect.value = '';
                }
            } else {
                option.disabled = false;
                option.style.color = '';
            }
        }
    });
}

function updateTotals() {
    let totals = [0, 0, 0, 0];
    let biggestHand = 0;
    let biggestHandPlayer = 0;
    
    for (let h = 1; h <= 16; h++) {
        const huInput = document.getElementById('hand_' + h + '_hu');
        const playerSelect = document.getElementById('hand_' + h + '_player');
        const fromPlayerSelect = document.getElementById('hand_' + h + '_from');
        const playerValue = playerSelect.value;
        const huPoints = parseInt(huInput.value) || 0;
        const huPlayer = parseInt(playerValue) || 0;

        // Hantera 0-runda
        if (playerValue === 'zero') {
            huInput.value = 0;
            let zeroHtml = '<div class="calculated-points">';
            for (let i = 0; i < 4; i++) zeroHtml += '<div class="point-box point-zero">0</div>';
            zeroHtml += '</div>';
            document.getElementById('hand_' + h + '_calc').innerHTML = zeroHtml;
            continue;
        }

        const savedFromValue = fromPlayerSelect.value;
        updateFromPlayerOptions(h, huPlayer);
        if (fromPlayerSelect.value === '' && savedFromValue !== '') {
            fromPlayerSelect.value = savedFromValue;
        }
        const fromPlayer = fromPlayerSelect.value === '' ? -1 : parseInt(fromPlayerSelect.value);

        if (huPoints > 0 && huPlayer >= 1 && huPlayer <= 4 && fromPlayer >= 0) {
            if (huPoints > biggestHand) {
                biggestHand = huPoints;
                biggestHandPlayer = huPlayer;
            }
            
            const winner = huPlayer - 1;
            let handPoints = [0, 0, 0, 0];
            
            if (fromPlayer === 0) {
                for (let i = 0; i < 4; i++) {
                    if (i === winner) {
                        handPoints[i] = (huPoints + 8) * 3;
                    } else {
                        handPoints[i] = -(huPoints + 8);
                    }
                }
            } else {
                const thrower = fromPlayer - 1;
                for (let i = 0; i < 4; i++) {
                    if (i === winner) {
                        handPoints[i] = 8 + 8 + (8 + huPoints);
                    } else if (i === thrower) {
                        handPoints[i] = -(8 + huPoints);
                    } else {
                        handPoints[i] = -8;
                    }
                }
            }
            
            let calcHtml = '<div class="calculated-points">';
            for (let i = 0; i < 4; i++) {
                const className = handPoints[i] > 0 ? 'point-positive' : (handPoints[i] < 0 ? 'point-negative' : 'point-zero');
                calcHtml += '<div class="point-box ' + className + '">' + (handPoints[i] > 0 ? '+' : '') + handPoints[i] + '</div>';
            }
            calcHtml += '</div>';
            document.getElementById('hand_' + h + '_calc').innerHTML = calcHtml;
            
            for (let i = 0; i < 4; i++) {
                totals[i] += handPoints[i];
            }
        } else {
            document.getElementById('hand_' + h + '_calc').innerHTML = '';
        }
    }
    
    // Applicera straff (alltid minus, påverkar bara den spelaren)
    ['a', 'b', 'c', 'd'].forEach((l, i) => {
        const p = parseInt(document.getElementById('penalty_' + l).value) || 0;
        totals[i] -= Math.abs(p);
    });
    
    // Applicera False Hu (påverkar bara den spelaren, sprids INTE)
    ['a', 'b', 'c', 'd'].forEach((l, i) => {
        const fh = parseInt(document.getElementById('false_hu_' + l).value) || 0;
        totals[i] += fh;
    });
    
    ['a', 'b', 'c', 'd'].forEach((l, i) => {
        document.getElementById('total_' + l).textContent = Math.round(totals[i]);
    });
    
    let stat_players = totals.map((mini, idx) => ({mini, idx}));
    stat_players.sort((a, b) => b.mini - a.mini);
    
    const tablePointsBase = [4, 2, 1, 0];
    let tablePoints = [0, 0, 0, 0];
    
    for (let i = 0; i < 4; i++) {
        let sameCount = 1;
        let sumPoints = tablePointsBase[i];
        
        for (let j = i + 1; j < 4; j++) {
            if (stat_players[j].mini === stat_players[i].mini) {
                sameCount++;
                sumPoints += tablePointsBase[j];
            } else {
                break;
            }
        }
        
        const shared = sumPoints / sameCount;
        for (let k = 0; k < sameCount; k++) {
            tablePoints[stat_players[i + k].idx] = shared;
        }
        i += (sameCount - 1);
    }
    
    ['a', 'b', 'c', 'd'].forEach((l, i) => {
        document.getElementById('table_' + l).textContent = tablePoints[i].toFixed(1);
    });
    
    if (biggestHand > 0) {
        const playerLetters = ['a', 'b', 'c', 'd'];
        const playerLetter = playerLetters[biggestHandPlayer - 1];
        const select = document.getElementById('player_' + playerLetter);
        let playerName = 'Spelare ' + playerLetter.toUpperCase();
        
        if (select) {
            const option = select.options[select.selectedIndex];
            if (option && option.value) {
                const parts = option.text.split(' - ');
                if (parts.length === 2) {
                    playerName = parts[1]; // Bara namnet, inte Spelar-ID
                }
            }
        }
        
        document.getElementById('biggest_hand_text').textContent = 
            biggestHand + ' poäng av ' + playerName;
        document.getElementById('biggest_hand_info').style.display = 'block';
    } else {
        document.getElementById('biggest_hand_info').style.display = 'none';
    }
}

// Uppdatera summeringsrad - visar totalen från händerna INNAN penalties/false_hu
function updateSummary() {
    let handCount = 0;
    const handPoints = [0, 0, 0, 0]; // A, B, C, D
    
    // Räkna alla händer
    for (let h = 1; h <= 16; h++) {
        const huInput = document.getElementById("hand_" + h + "_hu");
        const calcDiv = document.getElementById("hand_" + h + "_calc");
        
        if (!calcDiv || !huInput) continue;
        
        const hu = parseInt(huInput.value) || 0;
        
        const playerSelect = document.getElementById("hand_" + h + "_player");
        const isZeroRound = playerSelect && playerSelect.value === 'zero';

        if (hu > 0 || isZeroRound) {
            handCount++;

            if (!isZeroRound) {
                // Läs de BERÄKNADE poängen från calc-diven
                const pointBoxes = calcDiv.querySelectorAll('.point-box');
                if (pointBoxes.length === 4) {
                    for (let i = 0; i < 4; i++) {
                        const text = pointBoxes[i].textContent;
                        const points = parseInt(text) || 0;
                        handPoints[i] += points;
                    }
                }
            }
        }
    }
    
    // Uppdatera label
    const summaryLabel = document.getElementById("summary_label");
    if (summaryLabel) {
        summaryLabel.textContent = "Efter " + handCount + " spelade händer";
    }
    
    // Uppdatera spelares poäng med färgkodning
    const playerIds = ["summary_a", "summary_b", "summary_c", "summary_d"];
    for (let i = 0; i < 4; i++) {
        const elem = document.getElementById(playerIds[i]);
        if (!elem) continue;
        
        const points = handPoints[i];
        elem.textContent = points > 0 ? "+" + points : points;
        
        // Färgkodning
        elem.className = "point-box";
        if (points > 0) {
            elem.classList.add("point-positive");
        } else if (points < 0) {
            elem.classList.add("point-negative");
        } else {
            elem.classList.add("point-zero");
        }
    }
}

// Anropa updateSummary efter updateTotals
const originalUpdateTotals = updateTotals;
updateTotals = function() {
    originalUpdateTotals();
    updateSummary();
};

window.addEventListener('DOMContentLoaded', function() {
    filterPlayers();
    updateTotals();
});
</script>