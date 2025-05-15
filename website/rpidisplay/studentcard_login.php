<?php
// Initialization
session_start();
$page_title = "Scan je kaart";  // Kortere titel

// Check if this is a direct access from admin menu
$view_reservations = isset($_GET['view_reservations']) && $_GET['view_reservations'] == '1';
$is_admin_session = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == 1 && 
                    isset($_SESSION['user']['Type']) && $_SESSION['user']['Type'] === 'beheerder';

// Check voor debug
$debug = isset($_GET['debug']) ? true : false;
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - VIVES Maaklab</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/buttons.css">
    <link rel="stylesheet" href="assets/css/scanner.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/standardized-ui.css">
    <link rel="stylesheet" href="assets/css/emergency-fix.css">
    <link rel="stylesheet" href="assets/css/scan-notifications.css">
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
            <div class="welcome-text">
            <?php if ($view_reservations && $is_admin_session): ?>
                <strong>Welkom <?php echo htmlspecialchars($_SESSION['user']['Voornaam'] . ' ' . $_SESSION['user']['Naam']); ?></strong>
            <?php else: ?>
                <!-- Removed page title as requested -->
            <?php endif; ?>
            </div>
            <div class="clock-container">
                <div id="clock"></div>
                <div id="date"></div>
            </div>
        </header>

        <!-- Main content -->
        <main>
            <?php if (!$view_reservations || !$is_admin_session): ?>
            <h1 style="font-size:1.5rem; margin-bottom:1rem;"><?php echo $page_title; ?></h1> <!-- Kleinere titel met minder marge -->
            
            <div class="scanner-container">
                <div id="status-message" class="status-message loading">
                    <i class="fas fa-spinner fa-pulse"></i> Wachten op kaart...
                </div>
                
                <div class="scanner-wrapper">
                    <!-- Scanner box stays in the center -->
                    <div class="scanner-area">
                        <div class="scanner-graphic">
                            <i class="fas fa-id-card"></i>
                        </div>
                    </div>
                    
                    <!-- Arrow is positioned absolutely to the right -->
                    <div class="arrow-container">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- User information will be displayed here after successful scan -->
            <div id="user-info-container" <?php if (!$view_reservations || !$is_admin_session): ?>style="display:none;"<?php endif; ?>></div>
            
            <!-- Actie knoppen - nu gefixeerd aan onderkant (alleen zichtbaar bij scanner, niet bij reservaties weergave) -->
            <?php if (!$view_reservations || !$is_admin_session): ?>
            <div class="action-buttons scanner-action-buttons">
                <a href="index.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Terug
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($debug): ?>
            <div style="margin-top: 20px; border: 1px solid #ccc; padding: 8px; font-size: 11px; text-align: left; background: #f9f9f9;">
                <h4>Debug Info:</h4>
                <p>Firebase config file: <?php echo file_exists(__DIR__ . '/maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json') ? 'Gevonden' : 'Niet gevonden'; ?></p>
                <p>Path: <?php echo __DIR__; ?></p>
                <p>PHP Version: <?php echo phpversion(); ?></p>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- JavaScript for clock functionality -->
    <script src="assets/js/clock.js"></script>
    
    <!-- Firebase libraries -->
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
    <script src="assets/js/logout-manager.js"></script>
    <script src="assets/js/reservation-page-detector.js"></script>
    <script src="assets/js/real-card-scanner.js"></script>
    <script src="assets/js/print-controller.js"></script>
    <script src="assets/js/text-center-fix.js"></script>
    
    <?php if ($view_reservations && $is_admin_session): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Back button removed as requested
        const container = document.getElementById('user-info-container');
        
        // Auto-display user dashboard for admin when accessed via the "Mijn reservaties" button
        const userData = <?php echo json_encode($_SESSION['user']); ?>;
        
        // Import the displayUserDashboard function from real-card-scanner.js
        if (typeof displayUserDashboard === 'function') {
            displayUserDashboard(userData);
            // Add back button after dashboard is loaded
            container.insertAdjacentHTML('beforeend', backBtnHTML);
        } else {
            // If the function isn't directly accessible, use a simpler approach
            fetch(`get_user_reservations.php?user_id=${userData.User_ID}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Create reservations HTML
                        let reservationsHTML = createReservationsHTML(data.reservations || []);
                        
                        // Add logout button for admin reservations
                        reservationsHTML += `
                            <div class="action-buttons">
                                <a href="logout.php" class="logout-btn">
                                    <i class="fas fa-sign-out-alt"></i> Uitloggen
                                </a>
                            </div>
                        `;
                        
                        document.getElementById('user-info-container').innerHTML = reservationsHTML;
                        
                        // Add event listeners to Start Print buttons
                        document.querySelectorAll('.start-print-btn').forEach(btn => {
                            btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                const reservationId = this.getAttribute('data-id');
                                startPrint(reservationId);
                            });
                        });
                    } else {
                        document.getElementById('user-info-container').innerHTML = `
                            <div class="status-message error">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>Er is een fout opgetreden bij het ophalen van je reserveringen: ${data.message || 'Onbekende fout'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('user-info-container').innerHTML = `
                        <div class="status-message error">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Fout bij het ophalen van reserveringen: ${error.message}</p>
                        </div>
                    `;
                });
        }
    });
    
    // Helper function to create reservations HTML
    function createReservationsHTML(reservations) {
        const now = new Date();
        
        let html = `
            <div class="reservations-container">
                <h3 style="text-align: center; width: 100%; display: block; margin-left: auto; margin-right: auto;">Je actieve reserveringen</h3>
        `;
        
        if (reservations.length === 0) {
            html += `
                <div class="no-reservations">
                    <i class="fas fa-calendar-times fa-3x"></i>
                    <p>Je hebt geen actieve reserveringen.</p>
                </div>
            `;
        } else {
            reservations.forEach(reservation => {
                const startTime = new Date(reservation.PRINT_START);
                const endTime = new Date(reservation.PRINT_END);
                const isActive = now >= startTime && now <= endTime;
                const isUpcoming = now < startTime;
                const isPrintStarted = reservation.print_started === 1 || reservation.print_started === true;
                const printerName = reservation.Versie_Toestel || `Printer #${reservation.Printer_ID}`;
                
                html += `
                <div class="reservation-card" data-id="${reservation.Reservatie_ID}">
                    <div class="reservation-header">
                        <div class="reservation-title">Reservering #${reservation.Reservatie_ID}</div>
                        <div class="reservation-status ${isActive ? 'status-active' : 'status-upcoming'}">
                            ${isActive ? 'Actief' : 'Aankomend'}
                        </div>
                    </div>
                    
                    <div class="reservation-details">
                        <div class="detail-item">
                            <div class="detail-label">Printer</div>
                            <div class="detail-value">${printerName}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Start</div>
                            <div class="detail-value">${formatDate(startTime)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Eind</div>
                            <div class="detail-value">${formatDate(endTime)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Filament</div>
                            <div class="detail-value">${reservation.filament_type || 'eigen/geen'}</div>
                        </div>
                    </div>
                    
                    <div class="reservation-actions">
                        ${isActive ? 
                            isPrintStarted ? 
                                `<div class="print-status active"><i class="fas fa-print"></i> Print actief</div>` : 
                                `<a href="#" class="action-btn start-print-btn" data-id="${reservation.Reservatie_ID}">Start Print</a>`
                            : ''
                        }
                    </div>
                </div>
                `;
            });
        }
        
        html += `
            </div>
        `;
        
        return html;
    }
    
    // Helper function to format date
    function formatDate(date) {
        return date.toLocaleString('nl-BE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // Function to start a print
    function startPrint(reservationId) {
        const actionButton = document.querySelector(`.start-print-btn[data-id="${reservationId}"]`);
        if (actionButton) {
            if (actionButton.disabled) {
                console.log('Print start already in progress, ignoring');
                return;
            }
            const originalText = actionButton.textContent;
            actionButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Bezig...';
            actionButton.disabled = true;
        }

        fetch('start_print.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ reservationId: reservationId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Succes - bijwerken UI
                    showMessage('success', 'Print succesvol gestart! De printer wordt nu ingeschakeld.');

                    // Update alle reserveringskaarten die overeenkomen met deze ID
                    document.querySelectorAll(`.reservation-card[data-id="${reservationId}"]`).forEach(card => {
                        // Vervang de start-print knop met een "actieve print" melding
                        const actionsDiv = card.querySelector('.reservation-actions');
                        if (actionsDiv) {
                            actionsDiv.innerHTML = `
                                <div class="print-status active">
                                    <i class="fas fa-print"></i> Print actief
                                </div>
                            `;
                        }
                    });
                } else {
                    // Toon foutmelding
                    showMessage('error', data.message || 'Er is een fout opgetreden bij het starten van de print');

                    // Reset de knop
                    if (actionButton) {
                        actionButton.innerHTML = originalText || 'Start Print';
                        actionButton.disabled = false;
                    }
                }
            })
            .catch(error => {
                showMessage('error', 'Er is een fout opgetreden bij het starten van de print');

                // Reset de knop
                if (actionButton) {
                    actionButton.innerHTML = originalText || 'Start Print';
                    actionButton.disabled = false;
                }
            });
    }
    
    // Helper function to show messages
    function showMessage(type, message) {
        const reservationsContainer = document.querySelector('.reservations-container');
        if (reservationsContainer) {
            const msgElement = document.createElement('div');
            msgElement.className = `status-message ${type}`;
            msgElement.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <p>${message}</p>
            `;

            // Voeg de melding toe aan het begin van de container
            reservationsContainer.insertBefore(msgElement, reservationsContainer.firstChild);

            // Laat de melding na 8 seconden verdwijnen
            setTimeout(() => {
                msgElement.style.opacity = '0';
                setTimeout(() => {
                    msgElement.remove();
                }, 500); // Verwijder na fade-out
            }, 8000);
        }
    }
    </script>
    <?php endif; ?>
    
    <?php if ($debug): ?>
    <!-- Debug console voor ontwikkelaars -->
    <div style="position: fixed; bottom: 50px; right: 10px; background: rgba(0,0,0,0.7); color: lime; 
         font-family: monospace; padding: 10px; border-radius: 5px; max-width: 400px; max-height: 200px; 
         overflow: auto; z-index: 9999; font-size: 12px;">
        <div id="debug-console"></div>
    </div>

    <script>
    console.log('Debug mode active');
    
    // Log any errors that occur
    window.onerror = function(message, source, lineno, colno, error) {
        console.error('JavaScript error:', message, 'at', source, lineno, colno, error);
        return false;
    };
    
    // Maak een kopie van de console.log functie voor debug output
    (function(){
        const oldLog = console.log;
        const oldError = console.error;
        const debugConsole = document.getElementById('debug-console');
        
        console.log = function() {
            // Originele console.log aanroepen
            oldLog.apply(console, arguments);
            
            // Log naar debug console
            const args = Array.from(arguments).map(arg => {
                if (typeof arg === 'object') {
                    try {
                        return JSON.stringify(arg);
                    } catch (e) {
                        return arg.toString();
                    }
                }
                return arg;
            });
            
            const line = document.createElement('div');
            line.textContent = args.join(' ');
            debugConsole.appendChild(line);
            
            // Scroll naar beneden
            debugConsole.scrollTop = debugConsole.scrollHeight;
        };
        
        console.error = function() {
            // Originele console.error aanroepen
            oldError.apply(console, arguments);
            
            // Log naar debug console in rood
            const args = Array.from(arguments);
            const line = document.createElement('div');
            line.textContent = args.join(' ');
            line.style.color = '#ff6b6b';
            debugConsole.appendChild(line);
            
            // Scroll naar beneden
            debugConsole.scrollTop = debugConsole.scrollHeight;
        };
    })();
    </script>
    <?php endif; ?>
    
    <!-- Centralized timeout configuration -->
    <script src="assets/js/config_timeout.js"></script>
    <!-- Inactivity timeout script -->
    <script src="assets/js/inactivity-timeout.js"></script>
    <script src="assets/js/text-center-fix.js"></script>
    
    <!-- Prevent text selection and other interactions -->
    <script src="assets/js/prevent-interactions.js"></script>
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