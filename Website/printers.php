<?php
session_start();

// Controleer of de gebruiker is ingelogd
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Gebruiker is niet ingelogd, redirect naar de inlogpagina
    header('Location: index.php'); // Vervang door de juiste inlogpagina
    exit;
}

// Als de gebruiker is ingelogd, kan de rest van de pagina worden weergegeven
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Styles/mystyle.css">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">

    
<script src="Scripts/navigation.js"></script>
    <title>Printers</title>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">
                <img src="images/vives smile.svg" alt="Vives Logo" />
            </a>
            
            <button class="nav-toggle" aria-label="Open menu">
                <span class="hamburger"></span>
            </button>

            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="reservatie.php" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="16"/>
                            <line x1="8" y1="12" x2="16" y2="12"/>
                        </svg>
                        Reserveer een printer
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mijnKalender.php" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Mijn reservaties
                    </a>
                </li>
                <li class="nav-item">
                    <a href="printers.php" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9V2h12v7"/>
                            <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                            <rect x="6" y="14" width="12" height="8"/>
                        </svg>
                        Info over printers
                    </a>
                </li>
                <li class="nav-item">
                    <a href="uitlog.php" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Log uit
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <script>
        document.querySelector('.nav-toggle').addEventListener('click', function() {
            document.querySelector('.nav-menu').classList.toggle('active');
            this.classList.toggle('active');
        });
    </script>
    <section class="photo-info">
        <div class="photo-item">
            <img src="images/Creality3D_Creality_3D_Ender_3_V3_SE_3D_printer_DKI00192_m1_big.jpg" alt="Printer 1">
            <div class="photo-text">
                <h2>Printer 1 (3DP 01) </h2>
                <p>Dit is een ender3 V3 dat werkt met Cura </p>
            </div>
        </div>
        <div class="photo-item">
            <img src="images/ENDER3_V2.jpg" alt="Printer 2">
            <div class="photo-text">
                <h2>Printer 2 (3DP 02)</h2>
                <p>Dit is een ender3 V2 met een bowden drive dat werkt met Cura </p>
            </div>
        </div>
        <div class="photo-item reverse">
            <div class="photo-text">
                <h2>Printer 3 (3DP 03)</h2>
                <p>Dit is een Ender3 pro Dat werkt met Cura</p>
            </div>
            <img src="images/ender-3-pro_2.jpg" alt="Printer 2">
        </div>
        
        
    </section>
    



</body>
</html>