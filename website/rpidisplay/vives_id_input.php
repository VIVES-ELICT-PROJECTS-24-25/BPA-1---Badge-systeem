<?php
// Initialization
session_start();
ob_start(); // Buffer output to remove debug text

// Check for admin access
$is_admin = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == 1 && 
           isset($_SESSION['user']['Type']) && $_SESSION['user']['Type'] === 'beheerder';

// Redirect non-admin users
if (!$is_admin) {
    header("Location: studentcard_login.php");
    exit();
}

$page_title = "Voer VIVES nummer in";
$card_id = isset($_GET['card_id']) ? htmlspecialchars($_GET['card_id']) : '';

// Stop if no card_id is provided
if (empty($card_id)) {
    header("Location: admin_cards.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - VIVES Maaklab</title>
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/buttons.css">
    <link rel="stylesheet" href="assets/css/scanner.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/card-registration.css">
    <link rel="stylesheet" href="assets/css/vives-id-input.css">
    <link rel="stylesheet" href="assets/css/action-buttons.css">
    <link rel="stylesheet" href="assets/css/admin-controls.css">
    <link rel="stylesheet" href="assets/css/standardized-ui.css">
    <link rel="stylesheet" href="assets/css/hide-cursor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- JavaScript for clock functionality -->
    <script src="assets/js/clock.js"></script>
    
    <!-- Prevent text selection and other interactions -->
    <script src="assets/js/prevent-interactions.js"></script>
</head>
<body>
    <!-- REMOVE ANY DEBUG TEXT AT TOP OF PAGE -->
    <script>document.currentScript.parentNode.childNodes.forEach(n => { if(n.nodeType === 3) n.textContent = ''; });</script>

    <div class="container">
        <!-- Header with logo and clock -->
        <header>
            <div class="logo">
                <img src="assets/images/vives_logo.png" alt="VIVES Logo">
            </div>
            <div class="welcome-text">Voer je VIVES nummer in</div>
            <div class="clock-container">
                <div id="clock"></div>
                <div id="date"></div>
            </div>
        </header>

        <!-- Main content -->
        <main>
            <div id="vives-id-container">
                <div class="vives-id-input-container">
                    <div class="vives-id-display">
                        <div id="vives-id-value">
                            <span class="prefix-placeholder active">?</span><span class="number-value"></span>
                        </div>
                    </div>
                    
                    <div class="prefix-selection">
                        <button class="prefix-btn" data-prefix="U">U</button>
                        <button class="prefix-btn" data-prefix="R">R</button>
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
                        <button class="keypad-btn submit-btn" id="submit-btn" disabled>
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </div>
                
                <!-- User information will be displayed here after verification -->
                <div id="user-info-container" style="display:none;"></div>
            </div>
            
            <!-- Action buttons at bottom -->
            <div class="action-buttons">
                <a href="admin_cards.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Kaart scannen
                </a>
                <div id="action-middle-container">
                    <!-- Confirm/Cancel buttons will be added here dynamically -->
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Uitloggen
                </a>
            </div>
        </main>
    </div>

    <script>
    // Store the scanned card ID to use in the verification process
    const scannedCardId = "<?php echo $card_id; ?>";
    </script>
    
    <!-- Updated JavaScript for VIVES ID input -->
    <script src="assets/js/vives-id-input.js"></script>
    
    <!-- Centralized timeout configuration -->
    <script src="assets/js/config_timeout.js"></script>
    <!-- Inactivity timeout script -->
    <script src="assets/js/inactivity-timeout.js"></script>
    <script>
        // Initialize inactivity timeout using centralized configuration
        document.addEventListener('DOMContentLoaded', function() {
            new InactivityManager({
                timeout: TIMEOUT_CONFIG.ADMIN_TIMEOUT,
                warningTime: TIMEOUT_CONFIG.WARNING_TIME,
                isAdminPage: true
            });
        });
    </script>
</body>
</html>
<?php
// Process output buffer to remove debug text
$content = ob_get_clean();
$filteredContent = preg_replace('/(Current Date and Time.*?)\s+/s', '', $content);
echo $filteredContent;
?>