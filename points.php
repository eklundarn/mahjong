<?php
require_once 'config.php';
includeHeader();

// Alla 67 händer med korrekta bildlänkar från originalet
$hands = [
    // CHOW (1-15)
    ['nr' => 1, 'pts' => 1, 'name' => 'Pure Double Chow', 'desc' => t('hand_desc_1'), 'img' => 'bambu%20-%20pure%20double%20chow%201-3.jpg', 'example' => t('hand_ex_1')],
    ['nr' => 2, 'pts' => 1, 'name' => 'Mixed Double Chow', 'desc' => t('hand_desc_2'), 'img' => 'mixed%20double%20chow.jpg', 'example' => t('hand_ex_2')],
    ['nr' => 3, 'pts' => 1, 'name' => 'Short Straight', 'desc' => t('hand_desc_3'), 'img' => 'tecken%20-%20short%20straight%204-9.jpg', 'example' => t('hand_ex_3')],
    ['nr' => 4, 'pts' => 1, 'name' => 'Terminal Chows', 'desc' => t('hand_desc_4'), 'img' => 'terminal%20chows.jpg', 'example' => t('hand_ex_4')],
    ['nr' => 5, 'pts' => 2, 'name' => 'All Chows', 'desc' => t('hand_desc_5'), 'img' => 'All%20chows.jpg', 'example' => t('hand_ex_5')],
    ['nr' => 6, 'pts' => 6, 'name' => 'Mixed Shifted Chows', 'desc' => t('hand_desc_6'), 'img' => 'ej%20hu%20-%20mixed%20shifted%20chows.jpg', 'example' => t('hand_ex_6')],
    ['nr' => 7, 'pts' => 8, 'name' => 'Mixed Straight', 'desc' => t('hand_desc_7'), 'img' => 'hu%20-%20mixed%20straight.jpg', 'example' => t('hand_ex_7')],
    ['nr' => 8, 'pts' => 8, 'name' => 'Mixed Triple Chow', 'desc' => t('hand_desc_8'), 'img' => 'hu%20-%20mixed%20triple%20chow.jpg', 'example' => t('hand_ex_8')],
    ['nr' => 9, 'pts' => 16, 'name' => 'Pure Straight', 'desc' => t('hand_desc_9'), 'img' => 'hu%20-%20pure%20straight.jpg', 'example' => t('hand_ex_9')],
    ['nr' => 10, 'pts' => 16, 'name' => 'Pure Shifted Chows', 'desc' => t('hand_desc_10'), 'img' => 'hu%20-%20pure%20shifted%20chows.jpg', 'example' => t('hand_ex_10')],
    ['nr' => 11, 'pts' => 16, 'name' => 'Three-Suited Terminal Chows', 'desc' => t('hand_desc_11'), 'img' => 'three-suited%20terminal%20chows.jpg', 'example' => t('hand_ex_11')],
    ['nr' => 12, 'pts' => 24, 'name' => 'Pure Triple Chow', 'desc' => t('hand_desc_12'), 'img' => 'hu%20-%20pure%20triple%20chow.jpg', 'example' => t('hand_ex_12')],
    ['nr' => 13, 'pts' => 32, 'name' => 'Four Shifted Chows', 'desc' => t('hand_desc_13'), 'img' => 'hu%20-%20four%20shifted%20chows.jpg', 'example' => t('hand_ex_13')],
    ['nr' => 14, 'pts' => 48, 'name' => 'Quadruple Chow', 'desc' => t('hand_desc_14'), 'img' => 'hu%20-%20quadruple%20chow.jpg', 'example' => t('hand_ex_14')],
    ['nr' => 15, 'pts' => 64, 'name' => 'Pure Terminal Chows', 'desc' => t('hand_desc_15'), 'img' => 'tecken%20-%20pure%20terminal%20chows.jpg', 'example' => t('hand_ex_15')],
    
    // PUNG (16-38)
    ['nr' => 16, 'pts' => 1, 'name' => 'Pung Terminals/Honours', 'desc' => t('hand_desc_16'), 'img' => 'bambu%20-%20kong%201.jpg', 'example' => t('hand_ex_16')],
    ['nr' => 17, 'pts' => 1, 'name' => 'Melded Kong', 'desc' => t('hand_desc_17'), 'img' => 'cirkel%20-%20kong%208.jpg', 'example' => t('hand_ex_17')],
    ['nr' => 18, 'pts' => 2, 'name' => 'Dragon Pung', 'desc' => t('hand_desc_18'), 'img' => 'drakar%20gröna%20drakar%20pong.jpg', 'example' => t('hand_ex_18')],
    ['nr' => 19, 'pts' => 2, 'name' => 'Prevalent Wind', 'desc' => t('hand_desc_19'), 'img' => 'vind%20east%20öst%20pong.jpg', 'example' => t('hand_ex_19')],
    ['nr' => 20, 'pts' => 2, 'name' => 'Seat Wind', 'desc' => t('hand_desc_20'), 'img' => 'vind%20south%20syd%20kong.jpg', 'example' => t('hand_ex_20')],
    ['nr' => 21, 'pts' => 2, 'name' => 'Double Pung', 'desc' => t('hand_desc_21'), 'img' => 'double%20pung%20tecken%208%20cirkel%208.jpg', 'example' => t('hand_ex_21')],
    ['nr' => 22, 'pts' => 2, 'name' => 'Two Concealed Pungs', 'desc' => t('hand_desc_22'), 'img' => 'double%20pung%20cirkel%201%20bambu%206.jpg', 'example' => t('hand_ex_22')],
    ['nr' => 23, 'pts' => 2, 'name' => 'Concealed Kong', 'desc' => t('hand_desc_23'), 'img' => 'bambu%20-%20kong%208.jpg', 'example' => t('hand_ex_23')],
    ['nr' => 24, 'pts' => 4, 'name' => 'Two Kongs', 'desc' => t('hand_desc_24'), 'img' => 'two%20kongs.jpg', 'example' => t('hand_ex_24')],
    ['nr' => 25, 'pts' => 6, 'name' => 'All Pungs', 'desc' => t('hand_desc_25'), 'img' => 'All%20pungs.jpg', 'example' => t('hand_ex_25')],
    ['nr' => 26, 'pts' => 6, 'name' => 'Two Dragons', 'desc' => t('hand_desc_26'), 'img' => 'ej%20hu%20-%20two%20dragons%20o%201%20terminal%20pung.jpg', 'example' => t('hand_ex_26')],
    ['nr' => 27, 'pts' => 8, 'name' => 'Mixed Shifted Pungs', 'desc' => t('hand_desc_27'), 'img' => 'hu%20-%20mixed%20shifted%20pungs.jpg', 'example' => t('hand_ex_27')],
    ['nr' => 28, 'pts' => 8, 'name' => 'Two Concealed Kongs', 'desc' => t('hand_desc_28'), 'img' => 'two%20concealed%20kongs.jpg', 'example' => t('hand_ex_28')],
    ['nr' => 29, 'pts' => 12, 'name' => 'Big Three Winds', 'desc' => t('hand_desc_29'), 'img' => 'hu%20-%20big%20three%20winds.jpg', 'example' => t('hand_ex_29')],
    ['nr' => 30, 'pts' => 16, 'name' => 'Triple Pung', 'desc' => t('hand_desc_30'), 'img' => 'hu%20-%20triple%20pung.jpg', 'example' => t('hand_ex_30')],
    ['nr' => 31, 'pts' => 16, 'name' => 'Three Concealed Pungs', 'desc' => t('hand_desc_31'), 'img' => 'hu%20-%20three%20concealed%20pungs.jpg', 'example' => t('hand_ex_31')],
    ['nr' => 32, 'pts' => 24, 'name' => 'All Even Pungs', 'desc' => t('hand_desc_32'), 'img' => 'hu%20-%20all%20even%20pungs.jpg', 'example' => t('hand_ex_32')],
    ['nr' => 33, 'pts' => 24, 'name' => 'Pure Shifted Pungs', 'desc' => t('hand_desc_33'), 'img' => 'hu%20-%20pure%20shifted%20pungs.jpg', 'example' => t('hand_ex_33')],
    ['nr' => 34, 'pts' => 32, 'name' => 'Three Kongs', 'desc' => t('hand_desc_34'), 'img' => 'hu%20-%20three%20kongs.jpg', 'example' => t('hand_ex_34')],
    ['nr' => 35, 'pts' => 48, 'name' => 'Four Shifted Pungs', 'desc' => t('hand_desc_35'), 'img' => 'hu%20-%20four%20shifted%20pungs.jpg', 'example' => t('hand_ex_35')],
    ['nr' => 36, 'pts' => 64, 'name' => 'Little Four Winds', 'desc' => t('hand_desc_36'), 'img' => 'hu%20-%20little%20four%20winds.jpg', 'example' => t('hand_ex_36')],
    ['nr' => 37, 'pts' => 64, 'name' => 'Little Three Dragons', 'desc' => t('hand_desc_37'), 'img' => 'hu%20-%20little%20three%20dragons.jpg', 'example' => t('hand_ex_37')],
    ['nr' => 38, 'pts' => 64, 'name' => 'Four Concealed Pungs', 'desc' => t('hand_desc_38'), 'img' => 'hu%20-%20four%20concealed%20pungs.jpg', 'example' => t('hand_ex_38')],
    ['nr' => 39, 'pts' => 88, 'name' => 'Big Four Winds', 'desc' => t('hand_desc_39'), 'img' => 'hu%20-%20big%20four%20winds.jpg', 'example' => t('hand_ex_39')],
    ['nr' => 40, 'pts' => 88, 'name' => 'Big Three Dragons', 'desc' => t('hand_desc_40'), 'img' => 'hu%20-%20big%20three%20dragons.jpg', 'example' => t('hand_ex_40')],
    ['nr' => 41, 'pts' => 88, 'name' => 'Four Kongs', 'desc' => t('hand_desc_41'), 'img' => 'hu%20-%20four%20kongs.jpg', 'example' => t('hand_ex_41')],
    
    // TERMINAL/HONOUR (42-51)
    ['nr' => 42, 'pts' => 2, 'name' => 'All Simples', 'desc' => t('hand_desc_42'), 'img' => 'ej%20hu%20-%20all%20simples.jpg', 'example' => t('hand_ex_42')],
    ['nr' => 43, 'pts' => 4, 'name' => 'Outside Hand', 'desc' => t('hand_desc_43'), 'img' => 'hu%20-%20outside%20hand.jpg', 'example' => t('hand_ex_43')],
    ['nr' => 44, 'pts' => 12, 'name' => 'Upper Four', 'desc' => t('hand_desc_44'), 'img' => 'hu%20-%20upper%20four.jpg', 'example' => t('hand_ex_44')],
    ['nr' => 45, 'pts' => 12, 'name' => 'Lower Four', 'desc' => t('hand_desc_45'), 'img' => 'hu%20-%20lower%20four.jpg', 'example' => t('hand_ex_45')],
    ['nr' => 46, 'pts' => 16, 'name' => 'All Fives', 'desc' => t('hand_desc_46'), 'img' => 'hu%20-%20all%20fives.jpg', 'example' => t('hand_ex_46')],
    ['nr' => 47, 'pts' => 24, 'name' => 'Upper Tiles', 'desc' => t('hand_desc_47'), 'img' => 'hu%20-%20upper%20tiles.jpg', 'example' => t('hand_ex_47')],
    ['nr' => 48, 'pts' => 24, 'name' => 'Middle Tiles', 'desc' => t('hand_desc_48'), 'img' => 'hu%20-%20middle%20tiles.jpg', 'example' => t('hand_ex_48')],
    ['nr' => 49, 'pts' => 24, 'name' => 'Lower Tiles', 'desc' => t('hand_desc_49'), 'img' => 'hu%20-%20lower%20tiles.jpg', 'example' => t('hand_ex_49')],
    ['nr' => 50, 'pts' => 32, 'name' => 'All Terminals/Honours', 'desc' => t('hand_desc_50'), 'img' => 'hu%20-%20all%20honors%20and%20terminals.jpg', 'example' => t('hand_ex_50')],
    ['nr' => 51, 'pts' => 64, 'name' => 'All Terminals', 'desc' => t('hand_desc_51'), 'img' => 'hu%20-%20all%20terminals.jpg', 'example' => t('hand_ex_51')],
    
    // SUIT (52-60)
    ['nr' => 52, 'pts' => 1, 'name' => 'Voided Suit', 'desc' => t('hand_desc_52'), 'img' => 'ej%20hu%20-%20voided%20suit.jpg', 'example' => t('hand_ex_52')],
    ['nr' => 53, 'pts' => 1, 'name' => 'No Honours', 'desc' => t('hand_desc_53'), 'img' => 'ej%20hu%20-%20no%20honours.jpg', 'example' => t('hand_ex_53')],
    ['nr' => 54, 'pts' => 6, 'name' => 'Half Flush', 'desc' => t('hand_desc_54'), 'img' => 'hu%20-%20half%20flush.jpg', 'example' => t('hand_ex_54')],
    ['nr' => 55, 'pts' => 6, 'name' => 'All Types', 'desc' => t('hand_desc_55'), 'img' => 'hu%20-%20all%20types.jpg', 'example' => t('hand_ex_55')],
    ['nr' => 56, 'pts' => 8, 'name' => 'Reversible Tiles', 'desc' => t('hand_desc_56'), 'img' => 'hu%20-%20all%20reversible.jpg', 'example' => t('hand_ex_56')],
    ['nr' => 57, 'pts' => 24, 'name' => 'Full Flush', 'desc' => t('hand_desc_57'), 'img' => 'hu%20-%20full%20flush.jpg', 'example' => t('hand_ex_57')],
    ['nr' => 58, 'pts' => 64, 'name' => 'All Honours', 'desc' => t('hand_desc_58'), 'img' => 'hu%20-%20all%20honours.jpg', 'example' => t('hand_ex_58')],
    ['nr' => 59, 'pts' => 88, 'name' => 'All Green', 'desc' => t('hand_desc_59'), 'img' => 'hu%20-%20all%20green.jpg', 'example' => t('hand_ex_59')],
    ['nr' => 60, 'pts' => 88, 'name' => 'Nine Gates', 'desc' => t('hand_desc_60'), 'img' => 'hu%20-%20nine%20gates.jpg', 'example' => t('hand_ex_60')],
    
    // SPECIAL (61-69)
    ['nr' => 61, 'pts' => '1*', 'name' => 'Flower Tile', 'desc' => t('hand_desc_61'), 'img' => 'blommorna.jpg', 'example' => t('hand_ex_61')],
    ['nr' => 62, 'pts' => 2, 'name' => 'Tile Hog', 'desc' => t('hand_desc_62'), 'img' => 'ej%20hu%20-%20tile%20hog.jpg', 'example' => t('hand_ex_62')],
    ['nr' => 63, 'pts' => 8, 'name' => 'Chicken Hand', 'desc' => t('hand_desc_63'), 'img' => 'hu%20-%20chicken%20hand.jpg', 'example' => t('hand_ex_63')],
    ['nr' => 64, 'pts' => 12, 'name' => 'Knitted Straight', 'desc' => t('hand_desc_64'), 'img' => 'hu%20-%20knitted%20straight.jpg', 'example' => t('hand_ex_64')],
    ['nr' => 65, 'pts' => 12, 'name' => 'Lesser Honours/Knitted Tiles', 'desc' => t('hand_desc_65'), 'img' => 'hu%20-%20lesser%20honours.jpg', 'example' => t('hand_ex_65')],
    ['nr' => 66, 'pts' => 24, 'name' => 'Greater Honours/Knitted Tiles', 'desc' => t('hand_desc_66'), 'img' => 'hu%20-%20greater%20honours.jpg', 'example' => t('hand_ex_66')],
    ['nr' => 67, 'pts' => 24, 'name' => 'Seven Pairs', 'desc' => t('hand_desc_67'), 'img' => 'hu%20-%20seven%20pairs.jpg', 'example' => t('hand_ex_67')],
    ['nr' => 68, 'pts' => 88, 'name' => 'Seven Shifted Pairs', 'desc' => t('hand_desc_68'), 'img' => 'hu%20-%20seven%20shifted%20pairs.jpg', 'example' => t('hand_ex_68')],
    ['nr' => 69, 'pts' => 88, 'name' => 'Thirteen Orphans', 'desc' => t('hand_desc_69'), 'img' => 'hu%20-%20thirteen%20orphans.jpg', 'example' => t('hand_ex_69')],
];

// Bonus poäng (nr 70-81)
$bonus = [
    ['nr' => 70, 'pts' => 1, 'name' => 'Edge Wait', 'desc' => t('bonus_70')],
    ['nr' => 71, 'pts' => 1, 'name' => 'Closed Wait', 'desc' => t('bonus_71')],
    ['nr' => 72, 'pts' => 1, 'name' => 'Single Wait', 'desc' => t('bonus_72')],
    ['nr' => 73, 'pts' => 1, 'name' => 'Self-Drawn', 'desc' => t('bonus_73')],
    ['nr' => 74, 'pts' => 2, 'name' => 'Concealed Hand', 'desc' => t('bonus_74')],
    ['nr' => 75, 'pts' => 4, 'name' => 'Fully Concealed', 'desc' => t('bonus_75')],
    ['nr' => 76, 'pts' => 4, 'name' => 'Last Tile', 'desc' => t('bonus_76')],
    ['nr' => 77, 'pts' => 6, 'name' => 'Melded Hand', 'desc' => t('bonus_77')],
    ['nr' => 78, 'pts' => 8, 'name' => 'Last Tile Draw', 'desc' => t('bonus_78')],
    ['nr' => 79, 'pts' => 8, 'name' => 'Last Tile Claim', 'desc' => t('bonus_79')],
    ['nr' => 80, 'pts' => 8, 'name' => 'Out with Replacement Tile', 'desc' => t('bonus_80')],
    ['nr' => 81, 'pts' => 8, 'name' => 'Robbing the Kong', 'desc' => t('bonus_81')],
];
?>

<style>
    .points-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin: 20px 0;
    }
    .points-table thead tr {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    .points-table th {
        padding: 12px 10px;
        text-align: left;
        border: 1px solid #ddd;
        font-weight: bold;
        font-size: 0.95em;
    }
    .points-table td {
        padding: 10px;
        border: 1px solid #ddd;
        vertical-align: top;
        font-size: 0.9em;
    }
    .points-table tbody tr:nth-child(even) {
        background: #f9f9f9;
    }
    .points-table tbody tr:hover {
        background: #f0f0f0;
    }
    .hand-name {
        font-weight: bold;
        color: #2c5f2d;
    }
    .point-value {
        font-weight: bold;
        font-size: 1.1em;
        color: #764ba2;
    }
    .example-img {
        max-width: 280px;
        max-height: 160px;
        min-height: 80px;
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 4px;
        margin-bottom: 5px;
        display: block;
    }
    .example-text {
        font-size: 0.85em;
        color: #666;
        line-height: 1.3;
    }
    .section-header {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        font-weight: bold;
        font-size: 1.1em;
        padding: 12px;
    }
</style>

<div style="max-width: 1600px; margin: 0 auto; padding: 20px;">
    
    <h1>🎯 <?php echo t('points_title'); ?></h1>
    
    <p style="font-size: 1.1em; margin-bottom: 10px;">
        <?php echo t('points_intro'); ?>
    </p>
    <p style="font-size: 1em; margin-bottom: 30px; color: #666;">
        <?php echo t('points_min_note'); ?>
    </p>
    
    <div style="overflow-x: auto;">
    <table class="points-table">
        <thead>
            <tr>
                <th style="width: 35px;"><?php echo t('points_col_nr'); ?></th>
                <th style="width: 45px;"><?php echo t('points_col_pts'); ?></th>
                <th style="width: 180px;"><?php echo t('points_col_hand'); ?></th>
                <th style="min-width: 200px;"><?php echo t('points_col_desc'); ?></th>
                <th style="min-width: 200px;"><?php echo t('points_col_example'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr class="section-header">
                <td colspan="5">🎴 <?php echo t('points_section_chow'); ?></td>
            </tr>
            <?php foreach (array_slice($hands, 0, 15) as $h): ?>
            <tr>
                <td><?php echo $h['nr']; ?></td>
                <td class="point-value"><?php echo $h['pts']; ?></td>
                <td class="hand-name"><?php echo $h['name']; ?></td>
                <td><?php echo $h['desc']; ?></td>
                <td>
                    <?php if (!empty($h['img'])): ?>
                    <img src="images/brickor/gula/<?php echo $h['img']; ?>" class="example-img" alt="<?php echo $h['name']; ?>">
                    <?php endif; ?>
                    <div class="example-text"><?php echo $h['example']; ?></div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <tr class="section-header">
                <td colspan="5">🀄 <?php echo t('points_section_pung'); ?></td>
            </tr>
            <?php foreach (array_slice($hands, 15, 23) as $h): ?>
            <tr>
                <td><?php echo $h['nr']; ?></td>
                <td class="point-value"><?php echo $h['pts']; ?></td>
                <td class="hand-name"><?php echo $h['name']; ?></td>
                <td><?php echo $h['desc']; ?></td>
                <td>
                    <?php if (!empty($h['img'])): ?>
                    <img src="images/brickor/gula/<?php echo $h['img']; ?>" class="example-img" alt="<?php echo $h['name']; ?>">
                    <?php endif; ?>
                    <div class="example-text"><?php echo $h['example']; ?></div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <tr class="section-header">
                <td colspan="5">🎲 <?php echo t('points_section_terminal'); ?></td>
            </tr>
            <?php foreach (array_slice($hands, 38, 10) as $h): ?>
            <tr>
                <td><?php echo $h['nr']; ?></td>
                <td class="point-value"><?php echo $h['pts']; ?></td>
                <td class="hand-name"><?php echo $h['name']; ?></td>
                <td><?php echo $h['desc']; ?></td>
                <td>
                    <?php if (!empty($h['img'])): ?>
                    <img src="images/brickor/gula/<?php echo $h['img']; ?>" class="example-img" alt="<?php echo $h['name']; ?>">
                    <?php endif; ?>
                    <div class="example-text"><?php echo $h['example']; ?></div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <tr class="section-header">
                <td colspan="5">🎨 <?php echo t('points_section_suit'); ?></td>
            </tr>
            <?php foreach (array_slice($hands, 48, 9) as $h): ?>
            <tr>
                <td><?php echo $h['nr']; ?></td>
                <td class="point-value"><?php echo $h['pts']; ?></td>
                <td class="hand-name"><?php echo $h['name']; ?></td>
                <td><?php echo $h['desc']; ?></td>
                <td>
                    <?php if (!empty($h['img'])): ?>
                    <img src="images/brickor/gula/<?php echo $h['img']; ?>" class="example-img" alt="<?php echo $h['name']; ?>">
                    <?php endif; ?>
                    <div class="example-text"><?php echo $h['example']; ?></div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <tr class="section-header">
                <td colspan="5">⭐ <?php echo t('points_section_special'); ?></td>
            </tr>
            <?php foreach (array_slice($hands, 57) as $h): ?>
            <tr>
                <td><?php echo $h['nr']; ?></td>
                <td class="point-value"><?php echo $h['pts']; ?></td>
                <td class="hand-name"><?php echo $h['name']; ?></td>
                <td><?php echo $h['desc']; ?></td>
                <td>
                    <?php if (!empty($h['img'])): ?>
                    <img src="images/brickor/gula/<?php echo $h['img']; ?>" class="example-img" alt="<?php echo $h['name']; ?>">
                    <?php endif; ?>
                    <div class="example-text"><?php echo $h['example']; ?></div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <tr class="section-header">
                <td colspan="5">✨ <?php echo t('points_section_bonus'); ?></td>
            </tr>
            <?php foreach ($bonus as $b): ?>
            <tr>
                <td><?php echo $b['nr']; ?></td>
                <td class="point-value"><?php echo $b['pts']; ?></td>
                <td class="hand-name"><?php echo $b['name']; ?></td>
                <td colspan="2"><?php echo $b['desc']; ?></td>
            </tr>
            <?php endforeach; ?>
            
        </tbody>
    </table>
    </div>
    
    <div style="margin-top: 30px; padding: 20px; background: #e8f5e9; border-left: 4px solid #4CAF50; border-radius: 5px;">
        <p style="margin: 0;"><strong>💡 Tips:</strong> <?php echo t('points_tip'); ?> 
        <a href="rules.php" style="color: #2c5f2d; font-weight: bold;"><?php echo t('nav_rules'); ?></a> <?php echo t('points_tip_download'); ?></p>
    </div>
    
</div>

<?php includeFooter(); ?>
