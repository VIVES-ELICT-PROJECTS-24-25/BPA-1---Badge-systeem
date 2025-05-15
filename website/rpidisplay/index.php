<?php
// Initialization
session_start();

// Check if the user just logged out
$just_logged_out = isset($_SESSION['just_logged_out']) && $_SESSION['just_logged_out'] === true;

// Clear the flash message
if ($just_logged_out) {
    unset($_SESSION['just_logged_out']);
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welkom - VIVES Maaklab</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/buttons.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/standardized-ui.css">
    <link rel="stylesheet" href="assets/css/hide-cursor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Inline CSS om zeker te zijn dat deze stijlen worden toegepast */
        .logout-notification {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            margin: 0 auto 20px auto;
            max-width: 300px;
            text-align: center;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            animation: fadeOut 0.5s 2.5s forwards;
        }
        
        .logout-notification i {
            margin-right: 8px;
            font-size: 1rem;
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; visibility: hidden; }
        }
        
        .login-container {
            margin: 0 auto;
            max-width: 800px;
            text-align: center;
        }

        .login-options {
            margin-top: 2rem;
        }

        .login-buttons {
            display: flex;
            flex-direction: row; /* Belangrijk: horizontale richting */
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }
        
        /* Zorg ervoor dat buttons naast elkaar staan */
        .login-btn {
            flex: 0 0 auto; /* Niet laten groeien/krimpen, blijf bij intrinsieke grootte */
            margin: 10px; /* Extra margin tussen buttons */
        }
        
        @media (max-width: 600px) {
            .login-buttons {
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header with logo and clock -->
        <header>
            <div class="logo">
                <img src="assets/images/vives_logo.png" alt="VIVES Logo">
            </div>
            <div class="welcome-text">VIVES Maaklab</div>
            <div class="clock-container">
                <div id="clock"></div>
                <div id="date"></div>
            </div>
        </header>

        <!-- Main content -->
        <main>
            <h1>Welkom bij het VIVES Maaklab</h1>
            
            <?php if ($just_logged_out): ?>
            <div class="logout-notification">
                <i class="fas fa-check-circle"></i> U bent uitgelogd
            </div>
            <?php endif; ?>
            
            <div class="login-container">
                <h2>Kies een optie om in te loggen:</h2>
                
                <div class="login-options">
                    <div class="login-buttons">
                        <a href="studentcard_login.php" class="login-btn">
                            <i class="fas fa-id-card"></i>
                            <span>Studentenkaart</span>
                        </a>
                        
                        <a href="code_login.php" class="login-btn">
                            <i class="fas fa-key"></i>
                            <span>Code</span>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript for clock functionality -->
    <script src="assets/js/clock.js"></script>
    
    <!-- Logout manager to handle logout state -->
    <script src="assets/js/logout-manager.js"></script>
    
    <!-- Prevent text selection and other interactions -->
    <script src="assets/js/prevent-interactions.js"></script>
    
    <!-- Set logout state on page load if needed -->
    <?php if ($just_logged_out): ?>
    <script>
        // Update localStorage to record the logout
        localStorage.setItem('lastLogout', Date.now().toString());
        localStorage.setItem('lastScannedCard', '');
    </script>
    <?php endif; ?>
    
    <!-- No inactivity timeout on index page -->
</body>
</html>