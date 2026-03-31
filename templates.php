<?php
require_once 'config.php';
includeHeader();
?>

<div style="max-width: 1000px; margin: 0 auto; padding: 20px;">
    
    <h1>📄 <?php echo t('templates_title'); ?></h1>
    
    <p style="font-size: 1.1em; margin-bottom: 30px;">
        <?php echo t('templates_intro'); ?>
    </p>
    
    <!-- MCR -->
    <div style="margin-bottom: 40px; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h2 style="color: white; margin-top: 0;">📋 <?php echo t('templates_mcr_title'); ?></h2>
        
        <p style="font-size: 1.05em; line-height: 1.7;">
            För att räkna poäng vid Mahjongspel använder vi formuläret för Mahjongspel enligt MCR.
        </p>
        
        <p style="font-size: 1.05em; line-height: 1.7;">
            <strong>Poängfördelning:</strong> Vinnaren får 4 poäng, tvåan får 2 poäng, trean 1 poäng och den som kommer sist får 0 poäng 
            (poängen kallas även bordspoäng).
        </p>
        
        <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3 style="color: white; margin-top: 0;">📝 <?php echo t('templates_forms'); ?></h3>
            
            <div style="margin: 15px 0; padding: 15px; background: rgba(255,255,255,0.15); border-radius: 5px;">
                <p style="margin: 0 0 10px 0; font-weight: bold;">Formulär för Mahjongspel enligt MCR</p>
                <p style="margin: 0; font-size: 0.95em;">
                    <a href="templates/vms-scoresheets-ver-1.5-251106.pdf" target="_blank" style="color: #fff3cd; text-decoration: underline;">
                        📥 <?php echo t('templates_download'); ?>
                    </a>
                </p>
            </div>
            
            <div style="margin: 15px 0; padding: 15px; background: rgba(255,255,255,0.15); border-radius: 5px;">
                <p style="margin: 0 0 10px 0; font-weight: bold;">Formulär för 5 och 6 spelare</p>
                <p style="margin: 0; font-size: 0.95em;">
                    Ibland är man fler än 4 som vill spela - här är formuläret för Mahjongspel enligt MCR med 5 respektive 6 spelare.
                </p>
                <p style="margin: 10px 0 0 0; font-size: 0.95em;">
                    <a href="templates/vms-coresheets-for-5-and-6-players-ver-1.1-251015.pdf" target="_blank" style="color: #fff3cd; text-decoration: underline;">
                        📥 <?php echo t('templates_download'); ?>
                    </a>
                </p>
            </div>
            
            <div style="margin: 15px 0; padding: 15px; background: rgba(255,255,255,0.15); border-radius: 5px;">
                <p style="margin: 0 0 10px 0; font-weight: bold;">Poängpapper för att räkna poäng (färg)</p>
                <p style="margin: 0; font-size: 0.95em;">
                    Ett poängpapper för att räkna poäng i Mahjong enligt MCR.
                </p>
                <p style="margin: 10px 0 0 0; font-size: 0.95em;">
                    <a href="templates/vms-mahjong-points-big-colour.pdf" target="_blank" style="color: #fff3cd; text-decoration: underline;">
                        📥 <?php echo t('templates_download'); ?>
                    </a>
                </p>
            </div>
            
            <div style="margin: 15px 0; padding: 15px; background: rgba(255,255,255,0.15); border-radius: 5px;">
                <p style="margin: 0 0 10px 0; font-weight: bold;">Poängpapper med minus för motståndare</p>
                <p style="margin: 0; font-size: 0.95em;">
                    Ett poängpapper för att räkna poäng i Mahjong enligt MCR med minus för motståndarna.
                </p>
                <p style="margin: 10px 0 0 0; font-size: 0.95em;">
                    <a href="templates/vms-points.pdf" target="_blank" style="color: #fff3cd; text-decoration: underline;">
                        📥 <?php echo t('templates_download'); ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Riichi -->
    <div style="padding: 25px; background: linear-gradient(135deg, #FFA726 0%, #FFE082 100%); border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h2 style="color: white; margin-top: 0;">📋 <?php echo t('templates_riichi_title'); ?></h2>
        
        <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3 style="color: white; margin-top: 0;">📝 <?php echo t('templates_scoresheet'); ?></h3>
            
            <div style="margin: 15px 0; padding: 15px; background: rgba(255,255,255,0.15); border-radius: 5px;">
                <p style="margin: 0 0 10px 0; font-weight: bold;">Riichi Scoresheet</p>
                <p style="margin: 0; font-size: 0.95em;">
                    <a href="http://mahjong-europe.org/portal/images/docs/riichi_scoresheet_EN.pdf" target="_blank" style="color: #fff3cd; text-decoration: underline;">
                        <?php echo t('rules_link_ema'); ?>
                    </a>
                </p>
                <p style="margin: 10px 0 0 0; font-size: 0.95em;">
                    Finns även <a href="templates/riichi-scoresheet-en-2016.pdf" target="_blank" style="color: #fff3cd; text-decoration: underline;">
                        <?php echo t('rules_link_local'); ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
    
</div>

<?php includeFooter(); ?>
