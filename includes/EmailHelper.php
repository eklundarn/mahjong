<?php
/**
 * E-posthjälpklass för att skicka e-post via Strato SMTP
 * Använder PHPMailer
 */

// Manuell laddning av PHPMailer (ligger direkt i PHPMailer/, inte PHPMailer/src/)
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';

// Ladda email_config.php om den finns och SMTP_HOST inte redan är definierat
if (!defined('SMTP_HOST')) {
    $email_cfg = __DIR__ . '/../config/email_config.php';
    if (file_exists($email_cfg)) {
        require_once $email_cfg;
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailHelper {
    
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->configureSMTP();
    }
    
    /**
     * Konfigurera SMTP-inställningar
     */
    private function configureSMTP() {
        $this->mailer->isSMTP();
        $this->mailer->Host = SMTP_HOST;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = SMTP_USERNAME;
        $this->mailer->Password = SMTP_PASSWORD;
        $this->mailer->SMTPSecure = SMTP_SECURE;
        $this->mailer->Port = SMTP_PORT;
        $this->mailer->CharSet = 'UTF-8';
        
        // Sätt avsändare
        $this->mailer->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $this->mailer->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);
    }
    
    /**
     * Skicka e-post för lösenordsåterställning
     * 
     * @param string $email Mottagarens e-postadress
     * @param string $username Användarnamn
     * @param string $token Återställningstoken
     * @return bool True om e-posten skickades, annars false
     */
    public function sendPasswordResetEmail($email, $username, $token) {
        $this->mailer->clearAddresses();
        $this->mailer->addAddress($email, $username);
        
        $resetLink = SITE_URL . '/reset-password.php?token=' . urlencode($token);
        
        $this->mailer->isHTML(true);
        $this->mailer->Subject = 'Återställ ditt lösenord - Statistik';
        
        // HTML-version
        $this->mailer->Body = $this->getPasswordResetHTMLTemplate($username, $resetLink);
        
        // Textversion (för e-postklienter som inte stöder HTML)
        $this->mailer->AltBody = $this->getPasswordResetTextTemplate($username, $resetLink);
        
        if (!$this->mailer->send()) {
            throw new Exception('PHPMailer Error: ' . $this->mailer->ErrorInfo);
        }
        
        return true;
    }
    
    /**
     * Skicka bekräftelse när lösenordet har ändrats
     * 
     * @param string $email Mottagarens e-postadress
     * @param string $username Användarnamn
     * @return bool True om e-posten skickades, annars false
     */
    public function sendPasswordChangedEmail($email, $username) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email, $username);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Ditt lösenord har ändrats - Mahjong Stats';
            
            // HTML-version
            $this->mailer->Body = $this->getPasswordChangedHTMLTemplate($username);
            
            // Textversion
            $this->mailer->AltBody = $this->getPasswordChangedTextTemplate($username);
            
            $this->mailer->send();
            return true;
            
        } catch (Exception $e) {
            error_log("E-postfel: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
    
    /**
     * HTML-mall för lösenordsåterställning
     */
    private function getPasswordResetHTMLTemplate($username, $resetLink) {
        $expiryHours = RESET_TOKEN_EXPIRY_HOURS;
        
        return "
        <!DOCTYPE html>
        <html lang='sv'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 30px; }
                .button { display: inline-block; background-color: #4CAF50; color: white; padding: 12px 30px; 
                         text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; color: #999; font-size: 12px; padding: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Återställ ditt lösenord</h1>
                </div>
                <div class='content'>
                    <p>Hej <strong>" . htmlspecialchars($username) . "</strong>,</p>
                    <p>Vi har tagit emot en begäran om att återställa lösenordet för ditt konto i Varbergs Mahjongs statistik.</p>
                    <p>Klicka på knappen nedan för att skapa ett nytt lösenord:</p>
                    <div style='text-align: center;'>
                        <a href='" . htmlspecialchars($resetLink) . "' class='button'>Återställ lösenord</a>
                    </div>
                    <p>Eller kopiera och klistra in följande länk i din webbläsare:</p>
                    <p style='word-break: break-all; background-color: #fff; padding: 10px; border: 1px solid #ddd;'>" . 
                    htmlspecialchars($resetLink) . "</p>
                    <p><strong>Viktig information:</strong></p>
                    <ul>
                        <li>Länken är giltig i {$expiryHours} timme" . ($expiryHours > 1 ? 'r' : '') . "</li>
                        <li>Om du inte begärt denna återställning kan du ignorera detta meddelande</li>
                        <li>Ditt lösenord förblir oförändrat tills du skapar ett nytt via länken ovan</li>
                    </ul>
                </div>
                <div class='footer'>
                    <p>Detta är ett automatiskt meddelande från Varbergs Mahjongs statistik. Svara inte på detta e-postmeddelande.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Textmall för lösenordsåterställning
     */
    private function getPasswordResetTextTemplate($username, $resetLink) {
        $expiryHours = RESET_TOKEN_EXPIRY_HOURS;
        
        return "
Återställ ditt lösenord - Mahjong Stats

Hej {$username},

Vi har tagit emot en begäran om att återställa lösenordet för ditt Varbergs Mahjongs statistik-konto.

Återställ ditt lösenord genom att besöka följande länk:
{$resetLink}

Viktig information:
- Länken är giltig i {$expiryHours} timme" . ($expiryHours > 1 ? 'r' : '') . "
- Om du inte begärt denna återställning kan du ignorera detta meddelande
- Ditt lösenord förblir oförändrat tills du skapar ett nytt via länken ovan

---
Detta är ett automatiskt meddelande från Varbergs Mahjongs statistik.
        ";
    }
    
    /**
     * HTML-mall för bekräftelse av ändrat lösenord
     */
    private function getPasswordChangedHTMLTemplate($username) {
        return "
        <!DOCTYPE html>
        <html lang='sv'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 30px; }
                .footer { text-align: center; color: #999; font-size: 12px; padding: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Lösenord ändrat</h1>
                </div>
                <div class='content'>
                    <p>Hej <strong>" . htmlspecialchars($username) . "</strong>,</p>
                    <p>Ditt lösenord för Varbergs Mahjongs statistik har nu ändrats.</p>
                    <p>Om du inte gjorde denna ändring, kontakta administratör omedelbart.</p>
                </div>
                <div class='footer'>
                    <p>Detta är ett automatiskt meddelande från Varbergs Mahjongs statistik.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Textmall för bekräftelse av ändrat lösenord
     */
    private function getPasswordChangedTextTemplate($username) {
        return "
Lösenord ändrat - Mahjong Stats

Hej {$username},

Ditt lösenord för Varbergs Mahjongs statistik har nu ändrats.

Om du inte gjorde denna ändring, kontakta administratör omedelbart.

---
Detta är ett automatiskt meddelande från Varbergs Mahjongs statistik.
        ";
    }
    
    /**
     * Skicka generellt e-postmeddelande med HTML-innehåll
     *
     * @param string $email Mottagarens e-postadress
     * @param string $name Mottagarens namn
     * @param string $subject Ämnesrad
     * @param string $htmlBody HTML-innehåll
     * @return bool True om e-posten skickades
     */
    public function sendGenericEmail($email, $name, $subject, $htmlBody) {
        $this->mailer->clearAddresses();
        $this->mailer->addAddress($email, $name);
        
        $this->mailer->isHTML(true);
        $this->mailer->Subject = $subject;
        
        $siteName = defined('SITE_TITLE') ? SITE_TITLE : 'Klubb';
        
        $this->mailer->Body = "
        <html>
        <body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333;\">
            <div style=\"max-width: 600px; margin: 0 auto; padding: 20px;\">
                <div style=\"text-align: center; padding: 20px 0; border-bottom: 2px solid #005B99;\">
                    <h2 style=\"color: #005B99; margin: 0;\">{$siteName}</h2>
                </div>
                <div style=\"padding: 20px 0;\">
                    {$htmlBody}
                </div>
                <div style=\"padding-top: 20px; border-top: 1px solid #eee; color: #999; font-size: 0.85em;\">
                    <p>Detta är ett automatiskt meddelande från {$siteName}.</p>
                </div>
            </div>
        </body>
        </html>";
        
        $this->mailer->AltBody = strip_tags(str_replace(['<br>','<br/>','</p>'], "\n", $htmlBody));
        
        try {
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Email send error: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
}
?>
