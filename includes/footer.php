</div> <!-- Stänger .main-content från header.php -->
    
    <footer class="footer" style="max-width: 1200px; margin: 30px auto; padding: 20px; text-align: center; color: #666;">
        <p>&copy; <?php echo date('Y') . ' <a href="https://varbergmahjong.se/" target="_blank" style="color: inherit; text-decoration: none; border-bottom: 1px dotted;">' . t('footer_society_name') . '</a>'; ?></p>
        <p style="font-size: 0.85em; color: #999; margin-top: 5px;">
            <?php echo t('footer_built_with'); ?> <a href="https://claude.ai" target="_blank" style="color: #666; text-decoration: none; border-bottom: 1px dotted #999;">Claude AI</a>
        </p>
        <p style="font-size: 0.9em; margin-top: 10px;">
            <?php 
            if (isLoggedIn()) {
                $user = getCurrentUser();
                echo t('footer_logged_in_as') . ': ' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ' (' . htmlspecialchars(t('role_' . $user['role'], $user['role'])) . ')';
            }
            ?>
        </p>
    </footer>
    
    <!-- Session keeper - håller sessionen vid liv vid användaraktivitet -->
    <!-- Körs BARA om användaren är inloggad -->
    <?php if (isLoggedIn()): ?>
    <script src="/js/session-keeper.js"></script>
    <?php endif; ?>
</body>
</html>