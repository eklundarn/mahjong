    </div> <!-- Stänger .embed-content från header-embed.php -->
    
    <!-- Session keeper - håller sessionen vid liv vid användaraktivitet -->
    <?php if (isLoggedIn()): ?>
    <script src="session-keeper.js"></script>
    <?php endif; ?>
</body>
</html>
