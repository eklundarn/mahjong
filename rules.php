<?php
require_once 'config.php';
includeHeader();
?>

<div style="max-width: 1000px; margin: 0 auto; padding: 20px;">
    
    <h1>📖 <?php echo t('rules_title'); ?></h1>
    
    <p style="font-size: 1.1em; line-height: 1.6;">
        <?php echo t('rules_intro1'); ?>
    </p>
    
    <p style="font-size: 1.1em; line-height: 1.6;">
        <?php echo t('rules_intro2'); ?>
    </p>
    
    <!-- MCR -->
    <div style="margin-top: 40px; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h2 style="color: white; margin-top: 0;"><?php echo t('rules_mcr_title'); ?></h2>
        
        <p style="font-size: 1.05em; line-height: 1.7;">
            <?php echo t('rules_mcr_desc'); ?>
        </p>
        
        <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3 style="color: white; margin-top: 0;">📚 <?php echo t('rules_docs'); ?></h3>
            
            <p style="margin: 10px 0;">
                <strong>MCR Green Book (2006):</strong><br>
                <a href="http://mahjong-europe.org/portal/images/docs/mcr_EN.pdf" target="_blank" style="color: #fff3cd;">
                    <?php echo t('rules_link_ema'); ?>
                </a> | 
                <a href="templates/wmo-mcr-en-2006.pdf" target="_blank" style="color: #fff3cd;">
                    <?php echo t('rules_link_local'); ?>
                </a>
            </p>
            
            <p style="margin: 10px 0;">
                <strong>MERS - Tournament Regulations:</strong><br>
                <a href="http://mahjong-europe.org/portal/images/docs/mcr_regulations.pdf" target="_blank" style="color: #fff3cd;">
                    <?php echo t('rules_link_ema'); ?>
                </a> | 
                <a href="templates/ema-mcr-regulations-2006.pdf" target="_blank" style="color: #fff3cd;">
                    <?php echo t('rules_link_local'); ?>
                </a>
            </p>
            
            <p style="margin: 10px 0;">
                <strong>MCR Penalties (2009):</strong><br>
                <a href="http://mahjong-europe.org/portal/images/docs/mcr_penalties.pdf" target="_blank" style="color: #fff3cd;">
                    <?php echo t('rules_link_ema'); ?>
                </a> | 
                <a href="templates/ema-mcr_penalties-2009.pdf" target="_blank" style="color: #fff3cd;">
                    <?php echo t('rules_link_local'); ?>
                </a>
            </p>
            
            <p style="margin: 10px 0;">
                <strong><?php echo t('rules_scoring_mcr'); ?>:</strong><br>
                <a href="points.php" style="color: #fff3cd;">
                    <?php echo t('rules_link_here'); ?>
                </a>
            </p>
        </div>
    </div>
    
    <!-- Riichi -->
    <div style="margin-top: 30px; padding: 25px; background: linear-gradient(135deg, #FFA726 0%, #FFE082 100%); border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h2 style="color: white; margin-top: 0;"><?php echo t('rules_riichi_title'); ?></h2>
        
        <p style="font-size: 1.05em; line-height: 1.7;">
            <?php echo t('rules_riichi_desc'); ?>
        </p>
        
        <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3 style="color: white; margin-top: 0;">📚 <?php echo t('rules_docs'); ?></h3>
            
            <p style="margin: 10px 0;">
                <strong>Riichi - Rules for Japanese Mahjong (2016):</strong><br>
                <a href="https://mahjong-europe.org/portal/images/docs/Riichi-rules-2016-EN.pdf" target="_blank" style="color: #fff3cd;">
                    <?php echo t('rules_link_ema'); ?>
                </a> | 
                <a href="templates/riichi-scoresheet-en-2016.pdf" target="_blank" style="color: #fff3cd;">
                    <?php echo t('rules_link_local'); ?>
                </a>
            </p>
            
            <p style="margin: 10px 0;">
                <strong><?php echo t('rules_scoring_riichi'); ?>:</strong><br>
                <a href="templates.php" style="color: #fff3cd;">
                    <?php echo t('rules_link_here'); ?>
                </a>
            </p>
        </div>
    </div>
    
    
</div>

<?php includeFooter(); ?>
