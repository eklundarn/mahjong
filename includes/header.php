<?php
if (!isset($vms_root)) $vms_root = '';
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
}
?>
<!DOCTYPE html>
<?php $__lang = currentLang(); ?>
<html lang="<?php echo $__lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo (defined('SITE_TITLE_EN') && isset($_SESSION['lang']) && $_SESSION['lang'] === 'en') ? SITE_TITLE_EN : SITE_TITLE; ?></title>
    <style>
        :root {
            --blue:        #005B99;
            --blue-dark:   #003D6B;
            --blue-light:  #1A7DBF;
            --yellow:      #FECC02;
            --yellow-dark: #D4A900;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #F7F8FA;
            color: #333;
            line-height: 1.6;
        }
        
        .header-container {
            background: white;
            border-bottom: 3px solid #FECC02;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px 0;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        
        .logo {
            width: <?php echo LOGO_SIZE; ?>px;
            height: <?php echo LOGO_SIZE; ?>px;
            flex-shrink: 0;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .site-title {
            font-size: 2.5em;
            color: #005B99;
            font-weight: bold;
        }
        
        /* ============================================ */
        /* NY MENYSTRUKTUR MED DROPDOWN */
        /* ============================================ */
        
        .navigation {
            background: #005B99;
            padding: 0;
            position: relative;
        }
        
        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
        }
        
        /* Huvudmenyitem */
        .nav-item {
            position: relative;
        }
        
        .nav-item > a {
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
            cursor: pointer;
            font-weight: bold;
        }
        
        .nav-item > a:hover {
            background: #1A7DBF;
        }
        
        .nav-item > a.active {
            background: #003D6B;
        }
        
        /* Dropdown-container */
        .dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            background: #003D6B;
            min-width: 250px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            display: none;
            z-index: 1000;
        }
        
        /* Visa dropdown vid hover (desktop) eller touch-tap */
        .nav-item:hover .dropdown,
        .nav-item.dropdown-open .dropdown {
            display: block;
        }
        
        /* Dropdown-items */
        .dropdown a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
            border-bottom: 1px solid #003D6B;
        }
        
        .dropdown a:last-child {
            border-bottom: none;
        }
        
        .dropdown a:hover {
            background: #1A7DBF;
        }
        
        /* Ikoner */
        .icon {
            font-size: 1.1em;
        }
        .icon-yellow {
            color: #FECC02;
            font-size: 1.1em;
        }
        
        /* Dropdown-pil */
        .dropdown-arrow {
            margin-left: auto;
            font-size: 0.8em;
        }
        
        .main-content {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
            background: white;
            min-height: 500px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            border-left: 4px solid;
        }
        
        .message.success {
            background: #E3F0FA;
            border-color: #005B99;
            color: #003D6B;
        }
        
        .message.error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .message.warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        .message.info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        
        h1, h2, h3 {
            color: #005B99;
            margin-bottom: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #005B99;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 1em;
        }
        
        .btn:hover {
            background: #003D6B;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #005B99;
        }
        
        table th a {
            color: #005B99;
            text-decoration: none;
        }
        
        table th a:hover {
            text-decoration: underline;
        }
        
        table tr:hover {
            background: #f8f9fa;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #005B99;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .site-title {
                font-size: 1.8em;
            }
            
            .nav-content {
                flex-direction: column;
            }
            
            .nav-item {
                width: 100%;
            }
            
            .dropdown {
                position: static;
                display: none;
                box-shadow: none;
            }
            
            .nav-item:hover .dropdown,
            .nav-item.dropdown-open .dropdown {
                display: block;
            }
            
            /* MOBIL-OPTIMERINGAR */
            
            /* Mindre logo på mobil */
            .logo {
                width: 80px !important;
                height: 80px !important;
            }
            
            /* Större knappar för touch */
            .btn {
                min-height: 44px;
                padding: 12px 20px;
                width: 100%;
                margin: 8px 0;
                font-size: 16px;
            }
            
            /* Större formulär */
            .form-group input,
            .form-group select,
            .form-group textarea {
                min-height: 48px;
                font-size: 16px;
            }
            
            /* Mindre spacing */
            .main-content {
                padding: 15px;
                margin: 15px auto;
            }
            
            /* Mindre text i tabeller */
            table {
                font-size: 13px;
            }
            
            table th,
            table td {
                padding: 8px 6px;
            }
        }
        
        /* Mobile hint message */
        .mobile-hint {
            display: none;
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 12px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 0.9em;
            color: #0d47a1;
        }
        
        /* RESPONSIVA TABELLKOLUMNER */
        
        /* Tablet + Mobil Landscape (480-1024px) - 6 kolumner */
        @media (max-width: 1024px) {
            table th:nth-child(3),  /* Ort */
            table td:nth-child(3),
            table th:nth-child(5),  /* Totalt BP */
            table td:nth-child(5),
            table th:nth-child(9),  /* Vinst% */
            table td:nth-child(9) {
                display: none;
            }
        }
        
        /* Mobil Portrait (<480px) - 3 kolumner */
        @media (max-width: 480px) {
            .site-title {
                font-size: 1.5em;
            }
            
            .logo {
                width: 60px !important;
                height: 60px !important;
            }
            
            /* Visa mobile hint */
            .mobile-hint {
                display: block;
            }
            
            /* Dölj fler kolumner */
            table th:nth-child(1),  /* Placering */
            table td:nth-child(1),
            table th:nth-child(4),  /* Matcher */
            table td:nth-child(4),
            table th:nth-child(8),  /* Placeringar */
            table td:nth-child(8) {
                display: none;
            }
            
            table {
                font-size: 12px;
            }
            
            table th,
            table td {
                padding: 6px 4px;
            }
        }
        
        /* MOBILANPASSNINGAR FÖR VMS */
        
        /* STATISTICS.PHP - Rankinglista */
        @media (max-width: 767px) and (orientation: portrait) {
            .ranking-table thead th:nth-child(1), .ranking-table tbody td:nth-child(1),  /* Rank */
            .ranking-table thead th:nth-child(3), .ranking-table tbody td:nth-child(3),  /* Klubb */
            .ranking-table thead th:nth-child(4), .ranking-table tbody td:nth-child(4),  /* Matcher */
            .ranking-table thead th:nth-child(5), .ranking-table tbody td:nth-child(5),  /* Totalt BP */
            .ranking-table thead th:nth-child(7), .ranking-table tbody td:nth-child(7) { /* Totalt MP */
                display: none !important;
            }
        }
        
        @media (min-width: 768px) and (max-width: 1024px) and (orientation: landscape) {
            .ranking-table thead th:nth-child(1), .ranking-table tbody td:nth-child(1),  /* Rank */
            .ranking-table thead th:nth-child(3), .ranking-table tbody td:nth-child(3) { /* Klubb */
                display: none !important;
            }
        }
        
        /* GAMES.PHP - Matcher */
        .stat_games-table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        @media (max-width: 1024px) {
            .stat_games-table {
                font-size: 0.85em;
            }
            .stat_games-table th,
            .stat_games-table td {
                padding: 8px 4px !important;
                white-space: nowrap;
            }
        }
        
        /* PLAYERS.PHP - Spelare */
        @media (max-width: 767px) and (orientation: portrait) {
            .stat_players-table thead th:nth-child(1), .stat_players-table tbody td:nth-child(1),  /* VMS */
            .stat_players-table thead th:nth-child(4), .stat_players-table tbody td:nth-child(4),  /* Ort */
            .stat_players-table thead th:nth-child(5), .stat_players-table tbody td:nth-child(5),  /* Klubb */
            .stat_players-table thead th:nth-child(6), .stat_players-table tbody td:nth-child(6) { /* EMA */
                display: none !important;
            }
        }
        
        @media (min-width: 768px) and (max-width: 1024px) and (orientation: landscape) {
            .stat_players-table thead th:nth-child(1), .stat_players-table tbody td:nth-child(1),  /* VMS */
            .stat_players-table thead th:nth-child(6), .stat_players-table tbody td:nth-child(6) { /* EMA */
                display: none !important;
            }
        }
        
        /* PLAYERS-ALL.PHP - Alla spelare */
        @media (max-width: 767px) and (orientation: portrait) {
            .stat_players-all-table thead th:nth-child(1), .stat_players-all-table tbody td:nth-child(1),  /* VMS */
            .stat_players-all-table thead th:nth-child(4), .stat_players-all-table tbody td:nth-child(4),  /* Ort */
            .stat_players-all-table thead th:nth-child(5), .stat_players-all-table tbody td:nth-child(5),  /* Klubb */
            .stat_players-all-table thead th:nth-child(6), .stat_players-all-table tbody td:nth-child(6),  /* EMA */
            .stat_players-all-table thead th:nth-child(7), .stat_players-all-table tbody td:nth-child(7) { /* Syns */
                display: none !important;
            }
        }
        
        @media (min-width: 768px) and (max-width: 1024px) and (orientation: landscape) {
            .stat_players-all-table thead th:nth-child(1), .stat_players-all-table tbody td:nth-child(1),  /* VMS */
            .stat_players-all-table thead th:nth-child(5), .stat_players-all-table tbody td:nth-child(5),  /* Klubb */
            .stat_players-all-table thead th:nth-child(6), .stat_players-all-table tbody td:nth-child(6) { /* EMA */
                display: none !important;
            }
        }
        
        /* POINTS.PHP - Poängtabell */
        @media (max-width: 767px) and (orientation: portrait) {
            .points-table thead th:nth-child(1), .points-table tbody td:nth-child(1),  /* Nr */
            .points-table thead th:nth-child(4), .points-table tbody td:nth-child(4),  /* Beskrivning */
            .points-table thead th:nth-child(5), .points-table tbody td:nth-child(5) { /* Exempel */
                display: none !important;
            }
            .points-table {
                font-size: 0.85em;
            }
            .points-table th,
            .points-table td {
                word-wrap: break-word;
                white-space: normal;
            }
        }
        
        @media (min-width: 768px) and (max-width: 1024px) and (orientation: landscape) {
            .points-table thead th:nth-child(1), .points-table tbody td:nth-child(1),  /* Nr */
            .points-table thead th:nth-child(5), .points-table tbody td:nth-child(5) { /* Exempel */
                display: none !important;
            }
            .points-table th,
            .points-table td {
                word-wrap: break-word;
                white-space: normal;
            }
        }

        /* ═══════════════════════════════════════════ */
        /* SPRÅKKNAPP                                  */
        /* ═══════════════════════════════════════════ */
        .lang-switcher {
            display: flex;
            align-items: center;
        }
        .nav-right-group {
            display: flex;
            align-items: center;
        }

        .lang-switcher a {
            display: flex;
            align-items: center;
            gap: 6px;
            color: white;
            text-decoration: none;
            padding: 15px 14px;
            font-size: 0.9em;
            font-weight: 700;
            letter-spacing: 0.05em;
            transition: background 0.2s;
            white-space: nowrap;
        }

        .lang-switcher a:hover {
            background: #1A7DBF;
        }

        .lang-flag {
            width: 22px;
            height: 14px;
            object-fit: cover;
            border-radius: 2px;
            vertical-align: middle;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }

        /* ═══════════════════════════════════════════ */
        /* DARK MODE                                   */
        /* ═══════════════════════════════════════════ */
        body[data-theme="dark"] {
            background: #1A1F2B;
            color: #E0E6EF;
        }

        body[data-theme="dark"] .header-container {
            background: #12161F;
        }

        body[data-theme="dark"] .site-title {
            color: #FECC02;
        }

        body[data-theme="dark"] .navigation {
            background: #D4A900;
        }

        body[data-theme="dark"] .nav-item > a {
            color: #1A1F2B;
        }

        body[data-theme="dark"] .nav-item > a:hover {
            background: rgba(0,0,0,0.15);
        }

        body[data-theme="dark"] .dropdown {
            background: #D4A900;
        }

        body[data-theme="dark"] .dropdown a {
            color: #1A1F2B;
            border-bottom-color: rgba(0,0,0,0.15);
        }

        body[data-theme="dark"] .dropdown a:hover {
            background: rgba(0,0,0,0.15);
        }

        body[data-theme="dark"] .lang-switcher a {
            color: #1A1F2B;
        }

        body[data-theme="dark"] .lang-switcher a:hover {
            background: rgba(0,0,0,0.15);
        }

        body[data-theme="dark"] .main-content {
            background: transparent;
        }

        body[data-theme="dark"] table {
            background: #252B38;
            color: #E0E6EF;
        }

        body[data-theme="dark"] table th {
            background: #1E2430;
            color: #FECC02;
        }

        body[data-theme="dark"] table td {
            border-bottom-color: #2E3748;
        }

        body[data-theme="dark"] table tr:hover td {
            background: #2E3748;
        }

        body[data-theme="dark"] .btn {
            background: #005B99;
            color: white;
        }

        body[data-theme="dark"] .btn:hover {
            background: #1A7DBF;
        }

        body[data-theme="dark"] input,
        body[data-theme="dark"] select,
        body[data-theme="dark"] textarea {
            background: #2E3748;
            color: #E0E6EF;
            border-color: #3E4A5E;
        }

        body[data-theme="dark"] .message.success {
            background: #1A3320;
            color: #6FCF97;
            border-color: #27AE60;
        }

        body[data-theme="dark"] .message.error {
            background: #2D1A1A;
            color: #EB5757;
            border-color: #EB5757;
        }

        body[data-theme="dark"] .icon-yellow {
            color: #1A1F2B;
        }

        .dark-toggle {
            display: flex;
            align-items: center;
            padding: 8px 10px;
            cursor: pointer;
            color: white;
            font-size: 1.1em;
            background: none;
            border: 2px solid rgba(0,0,0,0.4);
            border-radius: 50%;
            margin: 8px 6px;
            width: 38px;
            height: 38px;
            justify-content: center;
            transition: background 0.2s, border-color 0.2s;
        }

        .dark-toggle:hover {
            background: #1A7DBF;
        }

        body[data-theme="dark"] .dark-toggle {
            color: #1A1F2B;
        }

        body[data-theme="dark"] .dark-toggle:hover {
            background: rgba(0,0,0,0.15);
        }

        /* Gul rubrik i dark mode */
        body[data-theme="dark"] h1,
        body[data-theme="dark"] h2,
        body[data-theme="dark"] h3 {
            color: #FECC02;
        }

        /* Sorterbar tabell */
        th.sortable {
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }
        th.sortable:hover {
            background: #1A7DBF;
            color: white;
        }
        th.sortable::after {
            content: ' ⇅';
            font-size: 0.75em;
            opacity: 0.5;
        }
        th.sortable.sort-asc::after {
            content: ' ▲';
            opacity: 1;
        }
        th.sortable.sort-desc::after {
            content: ' ▼';
            opacity: 1;
        }
        body[data-theme="dark"] th.sortable:hover {
            background: #D4A900;
            color: #1a1a1a;
        }

        /* ═══════════════════════════════════════════════════════ */
        /* GLOBALA DARK MODE REGLER - ljusa rutor, filter-boxar   */
        /* ═══════════════════════════════════════════════════════ */

        /* Ljusa info/filter-rutor → mörkare blå */
        body[data-theme="dark"] [style*="background: #f9f9f9"],
        body[data-theme="dark"] [style*="background: #f8f9fa"],
        body[data-theme="dark"] [style*="background: #f0f8ff"],
        body[data-theme="dark"] [style*="background: #e8f5e9"],
        body[data-theme="dark"] [style*="background: #fff3cd"],
        body[data-theme="dark"] [style*="background: #f8f8f8"],
        body[data-theme="dark"] [style*="background: white"],
        body[data-theme="dark"] [style*="background: #fff;"],
        body[data-theme="dark"] [style*="background:#fff"] {
            background: #1E2A3A !important;
            color: #E0E6EF !important;
        }

        /* Alla label/ledtexter → gula */
        body[data-theme="dark"] label,
        body[data-theme="dark"] .form-group label,
        body[data-theme="dark"] legend {
            color: #FECC02 !important;
        }

        /* Footer text */
        body[data-theme="dark"] footer,
        body[data-theme="dark"] .footer,
        body[data-theme="dark"] [class*="footer"] {
            color: #FECC02 !important;
        }

        /* Kolumntext i tabeller - game#, date, bighand, registered by */
        body[data-theme="dark"] td {
            color: #E0E6EF;
        }

        /* Spelarkort i games.php → svart text */
        body[data-theme="dark"] [style*="background: white; padding"][style*="border-radius"] {
            background: #2A3545 !important;
            color: #E0E6EF !important;
        }
        body[data-theme="dark"] [style*="background: white; padding"][style*="border-radius"] * {
            color: #E0E6EF !important;
        }

        /* Min-markering i my-page speltabell - ta bort ljus bakgrund */
        body[data-theme="dark"] [style*="background: #fff3cd; padding: 2px"] {
            background: #005B99 !important;
            color: white !important;
        }

        /* Knappar i games: View grön, Delete röd */
        body[data-theme="dark"] .btn-view-game {
            background: #2e7d32 !important;
            color: white !important;
        }
        body[data-theme="dark"] .btn-delete-game {
            background: #c62828 !important;
            color: #FECC02 !important;
        }

        /* change-password h2 → blå (ej gul) */
        body[data-theme="dark"] .changepw-heading {
            color: #1A7DBF !important;
        }

        /* Gul text i filter-rutor */
        body[data-theme="dark"] [style*="background: #f9f9f9"] label,
        body[data-theme="dark"] [style*="background: #f8f9fa"] label,
        body[data-theme="dark"] [style*="background: #1E2A3A"] label {
            color: #FECC02 !important;
        }

        /* Created by / last edited text i players-all */
        body[data-theme="dark"] .col-created-by,
        body[data-theme="dark"] .col-edited-by {
            color: #FECC02 !important;
        }

        /* Statistik-boxar på vms/index - mörkare blå */
        body[data-theme="dark"] [style*="background: #e8f5e9"],
        body[data-theme="dark"] [style*="background: linear-gradient"][style*="e8f5e9"] {
            background: #1E2A3A !important;
        }

        /* "Du är inloggad" och stats-box på index */
        body[data-theme="dark"] .home-stat-box,
        body[data-theme="dark"] .login-info-box {
            background: #1E2A3A !important;
            color: #FECC02 !important;
        }

        /* Viktigt-rutan i configuration */
        body[data-theme="dark"] [style*="background: #fff3cd"][style*="border-left"][style*="ffc107"] {
            background: #1E2A3A !important;
            color: #FECC02 !important;
            border-left-color: #FECC02 !important;
        }

        /* Filter-box: gul bakgrund blå text i dark mode för ranking/games/users/mystat */
        body[data-theme="dark"] .filter-box {
            background: #D4A900 !important;
            color: #003D6B !important;
        }
        body[data-theme="dark"] .filter-box label,
        body[data-theme="dark"] .filter-box h3,
        body[data-theme="dark"] .filter-box * {
            color: #003D6B !important;
        }
        body[data-theme="dark"] .filter-box select,
        body[data-theme="dark"] .filter-box input[type="text"],
        body[data-theme="dark"] .filter-box input[type="number"] {
            background: #FECC02 !important;
            color: #003D6B !important;
            border: 2px solid #003D6B !important;
            -webkit-text-fill-color: #003D6B !important;
        }
        body[data-theme="dark"] .filter-box select option {
            background: #FECC02 !important;
            color: #003D6B !important;
        }
        body[data-theme="dark"] .filter-box .btn {
            color: white !important;
            background: #005B99 !important;
        }

        /* About-rutor → mörkt blå */
        body[data-theme="dark"] .about-box {
            background: #1E2A3A !important;
            color: #FECC02 !important;
        }
        body[data-theme="dark"] .about-box * {
            color: #FECC02 !important;
        }

        /* Snabbregistrering och import-rutor */
        body[data-theme="dark"] .quick-reg-box,
        body[data-theme="dark"] .import-box {
            background: #1E2A3A !important;
            color: #FECC02 !important;
        }
        body[data-theme="dark"] .quick-reg-box *,
        body[data-theme="dark"] .import-box * {
            color: #FECC02 !important;
        }

        /* VMS-info ruta i players-all */
        body[data-theme="dark"] .vms-info-box {
            background: #1E2A3A !important;
            color: #FECC02 !important;
        }
        /* ── Newgame: ta bort hover på hand-rader ── */
        body[data-theme="dark"] .hand-row:hover,
        .hand-row:hover { background: transparent !important; }
        
        /* ── My-statistics: filter-text svart ── */

        /* ── Users: siffror gula, tips-ruta blå ── */
        body[data-theme="dark"] .stat-number { color: #FECC02 !important; }
        body[data-theme="dark"] .mobile-hint,
        body[data-theme="dark"] .message.info.tips-box {
            background: #1E2A3A !important;
            color: #FECC02 !important;
        }
        
        /* ── Players.php: about-ruta + th gula ── */
        body[data-theme="dark"] .players-about { background: #1E2A3A !important; color: #FECC02 !important; }
        body[data-theme="dark"] .players-about * { color: #FECC02 !important; }
        
        /* ── Configuration: labels gula ── */
        body[data-theme="dark"] .config-section label { color: #FECC02 !important; }
        
        /* ── Change-password: rubrik ── */
        body[data-theme="dark"] .changepw-heading { color: #1A7DBF !important; }
    </style>
</head>
<body>
    <div class="header-container">
        <div class="header-content">
            <div class="logo">
                <img src="<?php echo $vms_root; ?>img/logo.png" alt="Logo">
            </div>
            <h1 class="site-title"><?php echo t('site_title'); ?></h1>
        </div>
    </div>
    
    <nav class="navigation">
        <div class="nav-content">
            <!-- STARTSIDA -->
            <div class="nav-item">
                <a href="<?php echo $vms_root; ?>index.php">
                    <span class="icon">🏠</span>
                    <?php echo t('nav_home'); ?>
                </a>
            </div>
            
            <!-- STATISTIK - alltid synlig, dropdown för inloggade -->
            <div class="nav-item">
                <a href="<?php echo $vms_root; ?>statistics.php">
                    <span class="icon">📊</span>
                    <?php echo t('nav_stats_pub'); ?>
                    <?php if (isLoggedIn()): ?>
                    <span class="dropdown-arrow">▼</span>
                    <?php endif; ?>
                </a>
                <?php if (isLoggedIn()): ?>
                <div class="dropdown">
                    <a href="<?php echo $vms_root; ?>ranking.php">
                        <span class="icon">📈</span>
                        <?php echo t('nav_ranking'); ?>
                    </a>
                    <a href="<?php echo $vms_root; ?>awards.php">
                        <span class="icon">🏆</span>
                        <?php echo t('nav_awards'); ?>
                    </a>
                    <a href="<?php echo $vms_root; ?>previous.php">
                        <span class="icon">📚</span>
                        <?php echo t('nav_previous_years'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (isLoggedIn()): ?>
                
                <!-- MATCHER -->
                <?php if (hasRole('player')): ?>
                <div class="nav-item">
                    <a href="<?php echo $vms_root; ?>games.php">
                        <span class="icon-yellow">◈</span>
                        <?php echo t('nav_games'); ?>
                        <span class="dropdown-arrow">▼</span>
                    </a>
                    <div class="dropdown">
                        <a href="<?php echo $vms_root; ?>mobile.php">
                            <span class="icon">📱</span>
                            <?php echo t('nav_mobile_reg'); ?>
                        </a>
                        <a href="<?php echo $vms_root; ?>draft-monitor.php">
                            <span class="icon">📋</span>
                            <?php echo currentLang() === 'sv' ? 'Pågående matcher' : 'Active games'; ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- SPELARE -->
                <div class="nav-item">
                    <a href="<?php echo $vms_root; ?>players.php">
                        <span class="icon">👁️</span>
                        <?php echo t('nav_players'); ?>
                    </a>
                </div>
            
            <?php endif; ?>
            
            <!-- OM / ABOUT (alltid synlig) -->
            <div class="nav-item">
                <a href="<?php echo $vms_root; ?>about-club.php">
                    <span class="icon">ℹ️</span>
                    <?php echo t('nav_about'); ?>
                    <span class="dropdown-arrow">▼</span>
                </a>
                <div class="dropdown">
                    <a href="<?php echo $vms_root; ?>about-club.php">
                        <span class="icon">🏠</span>
                        <?php echo t('nav_about_club'); ?>
                    </a>
                    <a href="<?php echo $vms_root; ?>about-mahjong.php">
                        <span class="icon">🀄</span>
                        <?php echo t('nav_about_mahjong'); ?>
                    </a>
                    <a href="<?php echo $vms_root; ?>rules.php">
                        <span class="icon">§</span>
                        <?php echo t('nav_rules'); ?>
                    </a>
                    <a href="<?php echo $vms_root; ?>templates.php">
                        <span class="icon">📄</span>
                        <?php echo t('nav_templates'); ?>
                    </a>
                    <a href="<?php echo $vms_root; ?>links.php">
                        <span class="icon">🔗</span>
                        <?php echo t('nav_links'); ?>
                    </a>
                </div>
            </div>
            
            <?php if (isLoggedIn()): ?>

                <?php if (hasRole('admin')): ?>
                <!-- ADMIN -->
                <div class="nav-item">
                    <a href="<?php echo $vms_root; ?>administration.php">
                        <span class="icon">⚙️</span>
                        Admin
                        <span class="dropdown-arrow">▼</span>
                    </a>
                    <div class="dropdown">
                        <a href="<?php echo $vms_root; ?>players.php?action=add">
                            <span class="icon">➕</span>
                            <?php echo t('nav_new_player'); ?>
                        </a>
                        <a href="<?php echo $vms_root; ?>players.php">
                            <span class="icon">👤</span>
                            <?php echo t('nav_all_players'); ?>
                        </a>
                        <a href="<?php echo $vms_root; ?>import-game.php">
                            <span class="icon">📥</span>
                            <?php echo t('nav_import_game'); ?>
                        </a>
                        <a href="<?php echo $vms_root; ?>administration.php">
                            <span class="icon">🔧</span>
                            <?php echo t('nav_administration'); ?>
                        </a>
                        <?php if (($_SESSION['role'] ?? '') === 'mainadmin'): ?>
                        <a href="<?php echo $vms_root; ?>configuration.php">
                            <span class="icon">⚙️</span>
                            <?php echo t('nav_configuration'); ?>
                        </a>
                        <a href="<?php echo $vms_root; ?>login-log.php">
                            <span class="icon">🔐</span>
                            <?php echo t('loginlog_title'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- MINA SIDOR -->
                <div class="nav-item">
                    <a href="<?php echo $vms_root; ?>my-page.php">
                        <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                        <span class="icon" style="margin-left:6px;">🏠</span>
                        <?php echo t('nav_mypages'); ?>
                        <span class="dropdown-arrow">▼</span>
                    </a>
                    <div class="dropdown">
                        <a href="<?php echo $vms_root; ?>my-games.php">
                            <span class="icon">🎲</span>
                            <?php echo t('nav_my_games'); ?>
                        </a>
                        <a href="<?php echo $vms_root; ?>my-statistics.php">
                            <span class="icon">📊</span>
                            <?php echo t('nav_my_stats'); ?>
                        </a>
                        <?php if (hasRole('admin')): ?>
                        <a href="<?php echo $vms_root; ?>newgame.php">
                            <span class="icon">➕</span>
                            <?php echo t('nav_new_game'); ?>
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo $vms_root; ?>change-password.php">
                            <span class="icon">🔑</span>
                            <?php echo t('nav_change_password'); ?>
                        </a>
                        <a href="<?php echo $vms_root; ?>logout.php">
                            <span class="icon">🚪</span>
                            <?php echo t('nav_logout'); ?>
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- LOGGA IN -->
                <div class="nav-item">
                    <a href="<?php echo $vms_root; ?>login.php">
                        <span class="icon">🔑</span>
                        <?php echo t('nav_login'); ?>
                    </a>
                </div>

            <?php endif; ?>
            <!-- SPRÅKKNAPP -->
            <div class="nav-right-group">
            <div class="lang-switcher" style="margin-left:0;">
                <?php if (currentLang() === 'sv'): ?>
                    <a href="?lang=en"><img src="<?php echo $vms_root; ?>img/flag_gb.svg" class="lang-flag" alt="EN"> EN</a>
                <?php else: ?>
                    <a href="?lang=sv"><img src="<?php echo $vms_root; ?>img/flag_sv.svg" class="lang-flag" alt="SV"> SV</a>
                <?php endif; ?>
            </div>
            </div><!-- nav-right-group -->
        </div>
    </nav>

    <div class="main-content">

<script>
(function() {
    var saved = localStorage.getItem('vms_theme');
    if (saved === 'dark') {
        document.body.setAttribute('data-theme', 'dark');
        var btn = document.getElementById('darkToggleBtn');
        if (btn) btn.textContent = '☀️';
    }
})();


// Sorterbar tabell
function extractSortKey(cell) {
    // Hitta data-sort om den finns (numerisk)
    if (cell && cell.dataset && cell.dataset.sort !== undefined) return parseFloat(cell.dataset.sort);
    var text = cell.innerText.trim();
    // Extrahera första numret ur texten (t.ex. "4.0 MP" → 4.0, "BP: 4.0 | MP: 372" → 4.0)
    var m = text.match(/-?[0-9]+[.,]?[0-9]*/);
    if (m) return parseFloat(m[0].replace(',', '.'));
    return text;
}

function makeSortable(table) {
    var headers = table.querySelectorAll('th');
    headers.forEach(function(th, col) {
        th.classList.add('sortable');
        th.addEventListener('click', function() {
            var asc = !th.classList.contains('sort-asc');
            headers.forEach(function(h) { h.classList.remove('sort-asc', 'sort-desc'); });
            th.classList.add(asc ? 'sort-asc' : 'sort-desc');
            var tbody = table.querySelector('tbody');
            var rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort(function(a, b) {
                var aKey = extractSortKey(a.cells[col] || {innerText:'', dataset:{}});
                var bKey = extractSortKey(b.cells[col] || {innerText:'', dataset:{}});
                var aNum = typeof aKey === 'number' ? aKey : parseFloat(String(aKey).replace(',','.'));
                var bNum = typeof bKey === 'number' ? bKey : parseFloat(String(bKey).replace(',','.'));
                if (!isNaN(aNum) && !isNaN(bNum)) return asc ? aNum - bNum : bNum - aNum;
                var aStr = String(aKey), bStr = String(bKey);
                return asc ? aStr.localeCompare(bStr, 'sv') : bStr.localeCompare(aStr, 'sv');
            });
            rows.forEach(function(r) { tbody.appendChild(r); });
        });
    });
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('table').forEach(function(t) {
        if (t.querySelector('thead') && t.querySelector('tbody')) {
            makeSortable(t);
        }
    });

    // Touch-friendly dropdown navigation
    // First tap opens dropdown, second tap navigates to the link
    var isTouchDevice = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
    if (isTouchDevice) {
        var openItem = null;
        
        document.querySelectorAll('.nav-item').forEach(function(item) {
            var dropdown = item.querySelector('.dropdown');
            var link = item.querySelector(':scope > a');
            if (!dropdown || !link) return;
            
            link.addEventListener('click', function(e) {
                if (openItem === item) {
                    // Second tap - navigate to the link
                    return;
                }
                // First tap - open dropdown, prevent navigation
                e.preventDefault();
                
                // Close any other open dropdown
                if (openItem && openItem !== item) {
                    openItem.classList.remove('dropdown-open');
                }
                
                item.classList.add('dropdown-open');
                openItem = item;
            });
        });
        
        // Tap outside closes dropdown
        document.addEventListener('click', function(e) {
            if (openItem && !openItem.contains(e.target)) {
                openItem.classList.remove('dropdown-open');
                openItem = null;
            }
        });
    }
});
</script>
