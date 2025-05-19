<?php
// Initialization
session_start();
$page_title = "Voer uw code in";

// Buffering starten om debug output te verwijderen
ob_start();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - VIVES Maaklab</title>
    <style>
        /* Direct debug output verbergen */
        body > *:not(.container):not(header):not(script):not(style) {
            display: none !important;
        }
    </style>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/buttons.css">
    <link rel="stylesheet" href="assets/css/code-panel.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/standardized-ui.css">
    <link rel="stylesheet" href="assets/css/emergency-fix.css">
    <link rel="stylesheet" href="assets/css/hide-cursor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="container">
        <!-- Header with logo and clock -->
        <header>
            <div class="logo">
                <img src="assets/images/vives_logo.png" alt="VIVES Logo">
            </div>
            <div class="welcome-text"><?php echo $page_title; ?></div>
            <div class="clock-container">
                <div id="clock"></div>
                <div id="date"></div>
            </div>
        </header>

        <!-- Main content -->
        <main>     
            <div class="code-container">
                <div id="status-message" class="status-message">
                    <p>Voer de 6-cijferige code in</p>
                </div>
                
                <div class="code-display">
                    <div class="code-dots">
                        <span class="code-dot"></span>
                        <span class="code-dot"></span>
                        <span class="code-dot"></span>
                        <span class="code-dot"></span>
                        <span class="code-dot"></span>
                        <span class="code-dot"></span>
                    </div>
                    <div class="code-value" id="code-value"></div>
                </div>
                
                <div class="numeric-keypad">
                    <button class="keypad-btn" data-value="1">1</button>
                    <button class="keypad-btn" data-value="2">2</button>
                    <button class="keypad-btn" data-value="3">3</button>
                    <button class="keypad-btn" data-value="4">4</button>
                    <button class="keypad-btn" data-value="5">5</button>
                    <button class="keypad-btn" data-value="6">6</button>
                    <button class="keypad-btn" data-value="7">7</button>
                    <button class="keypad-btn" data-value="8">8</button>
                    <button class="keypad-btn" data-value="9">9</button>
                    <button class="keypad-btn clear-btn" id="clear-btn">
                        <i class="fas fa-backspace"></i>
                    </button>
                    <button class="keypad-btn" data-value="0">0</button>
                    <button class="keypad-btn submit-btn" id="submit-btn">
                        <i class="fas fa-check"></i>
                    </button>
                </div>
            </div>
            
            <!-- User information will be displayed here after successful verification -->
            <div id="user-info-container" style="display:none;"></div>
            
            <!-- Actie knoppen - nu gefixeerd aan onderkant -->
            <div class="action-buttons code-action-buttons">
                <a href="index.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Terug
                </a>
            </div>
        </main>
    </div>

    <!-- JavaScript for clock functionality -->
    <script src="assets/js/clock.js"></script>
    
    <!-- JavaScript for code panel functionality -->
    <script src="assets/js/code-panel.js"></script>
    
    <!-- Prevent text selection and other interactions -->
    <script src="assets/js/prevent-interactions.js"></script>
    
    <script>
    // Direct debug output verwijderen script
    document.addEventListener('DOMContentLoaded', function() {
        // Zoek en verwijder debug tekst nodes
        const bodyNodes = document.body.childNodes;
        for (let i = 0; i < bodyNodes.length; i++) {
            const node = bodyNodes[i];
            if (node.nodeType === 3) { // TEXT_NODE
                if (node.textContent && node.textContent.includes('Current Date')) {
                    node.textContent = '';
                }
            }
        }
    });
    </script>
    
    <!-- Centralized timeout configuration -->
    <script src="assets/js/config_timeout.js"></script>
    <!-- Inactivity timeout script -->
    <script src="assets/js/inactivity-timeout.js"></script>
    <script>
        // Initialize inactivity timeout using centralized configuration
        document.addEventListener('DOMContentLoaded', function() {
            new InactivityManager({
                timeout: TIMEOUT_CONFIG.DEFAULT_TIMEOUT,
                warningTime: TIMEOUT_CONFIG.WARNING_TIME
            });
        });
    </script>
</body>
</html>
<?php
// Buffer verwerken en debug output filteren
$content = ob_get_clean();
$filteredContent = preg_replace('/Current Date and Time.*Current User\'s Login: Piotr-0/s', '', $content);
echo $filteredContent;
?>