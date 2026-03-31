<?php
// Denna sida kräver INGEN inloggning
define('ALLOW_WITHOUT_LOGIN', true);
require_once 'config.php';

$page_title = 'Återställ lösenord';
includeHeader();

// Hämta token från URL
$token = isset($_GET['token']) ? cleanInput($_GET['token']) : '';

if (empty($token)) {
    echo '<div class="message error">Ogiltig återställningslänk. Kontrollera att du använder hela länken från e-postmeddelandet.</div>';
    echo '<p><a href="forgot-password.php" class="btn">Begär ny återställningslänk</a></p>';
    includeFooter();
    exit;
}
?>

<style>
    .password-reset-container {
        max-width: 500px;
        margin: 0 auto;
        padding: 40px 30px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #2c5f2d;
    }
    
    .form-group input {
        width: 100%;
        padding: 12px;
        border: 2px solid #ddd;
        border-radius: 5px;
        font-size: 1em;
        transition: border-color 0.3s;
    }
    
    .form-group input:focus {
        outline: none;
        border-color: #4CAF50;
    }
    
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 5px;
        display: none;
    }
    
    .alert.success {
        background: #d4edda;
        border: 1px solid #28a745;
        color: #155724;
    }
    
    .alert.error {
        background: #f8d7da;
        border: 1px solid #dc3545;
        color: #721c24;
    }
    
    .btn-primary {
        width: 100%;
        padding: 12px;
        background: #4CAF50;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 1.1em;
        cursor: pointer;
        transition: background 0.3s;
    }
    
    .btn-primary:hover {
        background: #45a049;
    }
    
    .btn-primary:disabled {
        background: #ccc;
        cursor: not-allowed;
    }
    
    .password-strength {
        height: 5px;
        background: #ddd;
        border-radius: 3px;
        margin-top: 8px;
        overflow: hidden;
    }
    
    .password-strength-bar {
        height: 100%;
        width: 0%;
        transition: all 0.3s;
    }
    
    .strength-weak { background: #dc3545; width: 33%; }
    .strength-medium { background: #ffc107; width: 66%; }
    .strength-strong { background: #28a745; width: 100%; }
    
    .password-requirements {
        font-size: 0.9em;
        color: #666;
        margin-top: 8px;
    }
    
    .requirement {
        margin: 4px 0;
    }
    
    .requirement.met {
        color: #28a745;
    }
    
    .requirement.met::before {
        content: '✓ ';
    }
    
    .requirement.unmet::before {
        content: '○ ';
    }
</style>

<div class="password-reset-container">
    <h1>Återställ lösenord</h1>
    <p style="color: #666; margin-bottom: 30px;">Ange ditt nya lösenord</p>
    
    <div id="alertBox" class="alert"></div>
    
    <form id="resetForm">
        <input type="hidden" id="token" value="<?php echo htmlspecialchars($token); ?>">
        
        <div class="form-group">
            <label for="password">Nytt lösenord</label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
            <div class="password-strength">
                <div id="strengthBar" class="password-strength-bar"></div>
            </div>
            <div class="password-requirements">
                <div id="req-length" class="requirement unmet">Minst 8 tecken</div>
                <div id="req-uppercase" class="requirement unmet">Minst en stor bokstav</div>
                <div id="req-lowercase" class="requirement unmet">Minst en liten bokstav</div>
                <div id="req-number" class="requirement unmet">Minst en siffra</div>
            </div>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Bekräfta lösenord</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
        </div>
        
        <button type="submit" class="btn-primary" id="submitBtn">
            Återställ lösenord
        </button>
    </form>
</div>

<script>
const passwordInput = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');
const strengthBar = document.getElementById('strengthBar');

// Password strength checker
passwordInput.addEventListener('input', function() {
    const password = this.value;
    
    // Check requirements
    const hasLength = password.length >= 8;
    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    
    // Update requirement indicators
    updateRequirement('req-length', hasLength);
    updateRequirement('req-uppercase', hasUpper);
    updateRequirement('req-lowercase', hasLower);
    updateRequirement('req-number', hasNumber);
    
    // Calculate strength
    let strength = 0;
    if (hasLength) strength++;
    if (hasUpper) strength++;
    if (hasLower) strength++;
    if (hasNumber) strength++;
    
    // Update strength bar
    strengthBar.className = 'password-strength-bar';
    if (strength < 2) {
        strengthBar.classList.add('strength-weak');
    } else if (strength < 4) {
        strengthBar.classList.add('strength-medium');
    } else {
        strengthBar.classList.add('strength-strong');
    }
});

function updateRequirement(id, met) {
    const elem = document.getElementById(id);
    elem.className = met ? 'requirement met' : 'requirement unmet';
}

// Form submission
document.getElementById('resetForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const token = document.getElementById('token').value;
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const alertBox = document.getElementById('alertBox');
    const submitBtn = document.getElementById('submitBtn');
    
    // Validate passwords match
    if (password !== confirmPassword) {
        alertBox.className = 'alert error';
        alertBox.style.display = 'block';
        alertBox.textContent = 'Lösenorden matchar inte.';
        return;
    }
    
    // Validate password strength
    if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
        alertBox.className = 'alert error';
        alertBox.style.display = 'block';
        alertBox.textContent = 'Lösenordet uppfyller inte alla krav.';
        return;
    }
    
    // Disable button
    submitBtn.disabled = true;
    submitBtn.textContent = 'Återställer...';
    
    try {
        const siteUrl = '<?php echo SITE_URL; ?>';
        const response = await fetch(siteUrl + '/api/reset_password_confirm.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                token: token,
                new_password: password
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alertBox.className = 'alert success';
            alertBox.style.display = 'block';
            alertBox.textContent = data.message || 'Lösenordet har återställts!';
            
            // Redirect to login after 2 seconds
            setTimeout(function() {
                window.location.href = 'login.php';
            }, 2000);
        } else {
            alertBox.className = 'alert error';
            alertBox.style.display = 'block';
            alertBox.textContent = data.error || 'Ett fel uppstod. Försök igen.';
            
            // Re-enable button
            submitBtn.disabled = false;
            submitBtn.textContent = 'Återställ lösenord';
        }
    } catch (error) {
        alertBox.className = 'alert error';
        alertBox.style.display = 'block';
        alertBox.textContent = 'Ett nätverksfel uppstod. Kontrollera din internetanslutning.';
        console.error('Error:', error);
        
        // Re-enable button
        submitBtn.disabled = false;
        submitBtn.textContent = 'Återställ lösenord';
    }
});
</script>

<?php includeFooter(); ?>
