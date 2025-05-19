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

$page_title = "Kaart toevoegen";
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
    <link rel="stylesheet" href="assets/css/action-buttons.css">
    <link rel="stylesheet" href="assets/css/admin-controls.css">
    <link rel="stylesheet" href="assets/css/standardized-ui.css">
    <link rel="stylesheet" href="assets/css/emergency-fix.css">
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
            <div class="welcome-text">Kaart toevoegen</div>
            <div class="clock-container">
                <div id="clock"></div>
                <div id="date"></div>
            </div>
        </header>

        <!-- Main content -->
        <main>
            <!-- Admin dashboard title removed -->
            
            <div id="card-registration-container">
                <div class="scanner-container">
                    <div id="status-message" class="status-message loading">
                        <i class="fas fa-spinner fa-pulse"></i> Scan een kaart om toe te voegen...
                    </div>
                    
                    <div class="scanner-wrapper">
                        <!-- Scanner box stays in the center -->
                        <div class="scanner-area">
                            <div class="scanner-graphic">
                                <i class="fas fa-id-card"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- User information will be displayed here after successful scan -->
                <div id="user-info-container" style="display:none;"></div>
            </div>
        </main>
        
        <!-- Action buttons at bottom -->
        <div class="action-buttons">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Uitloggen
            </a>
        </div>
    </div>

    <!-- Firebase libraries -->
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
    
    <script src="assets/js/card-registration.js"></script>

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