<?php
require_once 'config.php';
includeHeader();
?>

<div style="max-width: 1000px; margin: 0 auto; padding: 20px;">
    
    <h1>🔗 <?php echo t('links_title'); ?></h1>
    
    <p style="font-size: 1.1em; margin-bottom: 30px;">
        <?php echo t('links_intro'); ?>
    </p>
    
    <!-- Mahjong i världen -->
    <div style="margin-bottom: 40px; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h2 style="color: white; margin-top: 0;">🌍 <?php echo t('links_world'); ?></h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 20px;">
            
            <div style="background: rgba(255,255,255,0.15); padding: 20px; border-radius: 8px; text-align: center;">
                <img src="images/logos/svensk-mahjong.jpg" alt="Svenska Mahjongförbundet" style="max-width: 120px; height: auto; margin-bottom: 15px; border-radius: 5px;">
                <h3 style="color: white; margin: 10px 0; font-size: 1.1em;">Svenska Mahjongförbundet</h3>
                <a href="https://www.svenskmahjong.se/" target="_blank" style="color: #fff3cd; text-decoration: underline; font-size: 0.95em;">
                    www.svenskmahjong.se
                </a>
            </div>
            
            <div style="background: rgba(255,255,255,0.15); padding: 20px; border-radius: 8px; text-align: center;">
                <img src="images/logos/ema.jpg" alt="Europeiska Mahjongförbundet" style="max-width: 120px; height: auto; margin-bottom: 15px; border-radius: 5px;">
                <h3 style="color: white; margin: 10px 0; font-size: 1.1em;">Europeiska Mahjongförbundet (EMA)</h3>
                <a href="http://mahjong-europe.org/portal/" target="_blank" style="color: #fff3cd; text-decoration: underline; font-size: 0.95em;">
                    mahjong-europe.org
                </a>
            </div>
            
            <div style="background: rgba(255,255,255,0.15); padding: 20px; border-radius: 8px; text-align: center;">
                <img src="images/logos/wmo.jpg" alt="Världsmahjongförbundet" style="max-width: 120px; height: auto; margin-bottom: 15px; border-radius: 5px;">
                <h3 style="color: white; margin: 10px 0; font-size: 1.1em;">Världsmahjongförbundet (WMO)</h3>
                <a href="http://www.mindmahjong.com/" target="_blank" style="color: #fff3cd; text-decoration: underline; font-size: 0.95em;">
                    www.mindmahjong.com
                </a>
            </div>
            
        </div>
    </div>
    
    <!-- Mahjongföreningar i Sverige -->
    <div style="padding: 25px; background: linear-gradient(135deg, #66BB6A 0%, #2E7D32 100%); border-radius: 10px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h2 style="color: white; margin-top: 0;"><img src="img/flag_sv.svg" style="width:28px; height:18px; object-fit:cover; border-radius:2px; vertical-align:middle; margin-right:8px; box-shadow:0 1px 3px rgba(0,0,0,0.3);"><?php echo t('links_sweden'); ?></h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-top: 20px;">
            
            <div style="background: white; padding: 20px; border-radius: 8px; text-align: center;">
                <img src="images/logos/goteborg.jpg" alt="Göteborgs Mahjongsällskap" style="max-width: 100px; height: auto; margin-bottom: 15px; border-radius: 5px;">
                <h3 style="color: #005B99; margin: 10px 0; font-size: 1em;">Göteborgs Mahjongsällskap</h3>
                <p style="font-size: 0.85em; margin: 5px 0; color: #666;">(MCR)</p>
                <a href="https://www.facebook.com/GbgMahjong" target="_blank" style="color: #2E7D32; text-decoration: underline; font-size: 0.9em; font-weight: bold;">
                    Facebook
                </a>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; text-align: center;">
                <img src="images/logos/haninge.jpg" alt="Mahjong i Haninge" style="max-width: 100px; height: auto; margin-bottom: 15px; border-radius: 5px;">
                <h3 style="color: #005B99; margin: 10px 0; font-size: 1em;">Mahjong i Haninge</h3>
                <p style="font-size: 0.85em; margin: 5px 0; color: #666;">&nbsp;</p>
                <a href="https://www.facebook.com/mahjongihaninge" target="_blank" style="color: #2E7D32; text-decoration: underline; font-size: 0.9em; font-weight: bold;">
                    Facebook
                </a>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; text-align: center;">
                <img src="images/logos/malmo.jpg" alt="Malmö Mahjongsällskap" style="max-width: 100px; height: auto; margin-bottom: 15px; border-radius: 5px;">
                <h3 style="color: #005B99; margin: 10px 0; font-size: 1em;">Malmö Mahjongsällskap</h3>
                <p style="font-size: 0.85em; margin: 5px 0; color: #666;">&nbsp;</p>
                <a href="https://www.facebook.com/groups/1493146947632253/" target="_blank" style="color: #2E7D32; text-decoration: underline; font-size: 0.9em; font-weight: bold;">
                    Facebook
                </a>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; text-align: center;">
                <img src="images/logos/stockholm.jpg" alt="Stockholm Mahjong" style="max-width: 100px; height: auto; margin-bottom: 15px; border-radius: 5px;">
                <h3 style="color: #005B99; margin: 10px 0; font-size: 1em;">Stockholm Mahjong</h3>
                <p style="font-size: 0.85em; margin: 5px 0; color: #666;">Via Uppsala Mahjong</p>
                <a href="https://www.facebook.com/groups/4687073348" target="_blank" style="color: #2E7D32; text-decoration: underline; font-size: 0.9em; font-weight: bold;">
                    Facebook
                </a>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; text-align: center;">
                <img src="images/logos/uppsala.jpg" alt="Uppsala Mahjong" style="max-width: 100px; height: auto; margin-bottom: 15px; border-radius: 5px;">
                <h3 style="color: #005B99; margin: 10px 0; font-size: 1em;">Uppsala Mahjong</h3>
                <p style="font-size: 0.85em; margin: 5px 0; color: #666;">&nbsp;</p>
                <a href="https://uppsalamahjong.se/" target="_blank" style="color: #2E7D32; text-decoration: underline; font-size: 0.9em; font-weight: bold;">
                    uppsalamahjong.se
                </a>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; text-align: center;">
                <img src="images/logos/vms.jpg" alt="klubben" style="max-width: 100px; height: auto; margin-bottom: 15px; border-radius: 5px;">
                <h3 style="color: #005B99; margin: 10px 0; font-size: 1em;">klubben</h3>
                <p style="font-size: 0.85em; margin: 5px 0; color: #666;">&nbsp;</p>
                <a href="https://varbergsmahjong.se" target="_blank" style="color: #2E7D32; text-decoration: underline; font-size: 0.9em; font-weight: bold;">
                    varbergsmahjong.se
                </a>
            </div>
            
        </div>
    </div>
    
</div>

<?php includeFooter(); ?>
