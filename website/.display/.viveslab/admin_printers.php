<?php
// Start the session
session_start();

// Eliminate debug output
ob_start();

// Enable error reporting but don't display to users
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Check for admin access
$is_admin = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == 1 && 
           isset($_SESSION['user']['Type']) && $_SESSION['user']['Type'] === 'beheerder';

// Redirect non-admin users
if (!$is_admin) {
    header("Location: studentcard_login.php");
    exit();
}

// Controleer of Composer autoloader beschikbaar is
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php'
];

$autoloaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require $path;
        $autoloaded = true;
        break;
    }
}

// Firebase-verbinding in een try/catch blok
$database = null;
$firebaseInitialized = false;
$initError = "";

try {
    if ($autoloaded) {
        // Controleer of Firebase SDK-bestand bestaat
        $serviceAccountPath = __DIR__ . '/maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json';
        if (!file_exists($serviceAccountPath)) {
            throw new Exception("Firebase service account bestand niet gevonden: $serviceAccountPath");
        }

        // Firebase initialization voor nieuwere versies (5.x en hoger)
        $factory = new Kreait\Firebase\Factory();
        $firebase = $factory
            ->withServiceAccount($serviceAccountPath)
            ->withDatabaseUri('https://maaklab-project-default-rtdb.europe-west1.firebasedatabase.app');
        
        $database = $firebase->createDatabase();
        $firebaseInitialized = true;
    } else {
        $initError = "Firebase SDK niet geladen. Composer autoloader niet gevonden.";
    }
} catch (Exception $e) {
    $initError = "Firebase initialisatie mislukt: " . $e->getMessage();
}

// Include database connection
require_once 'db_connection.php';

// Initialize variables
$printers = [];
$error_message = null;
$success_message = null;

// Functie om de Shelly ID af te leiden uit printer informatie
function getShellyIdFromPrinter($printer, $index) {
    // Als er een primaire mapping is in de database, gebruik die
    // Anders gebruik "shelly" + printer index (1-gebaseerd)
    return "shelly" . ($index + 1);
}

// Functie om de Shelly configuratie bij te werken in Firebase
function updateShellyConfigInFirebase($database, $printers) {
    if (!$database) return false;
    
    try {
        $shellyConfig = [];
        
        // Voor elke printer, voeg een Shelly configuratie toe
        foreach ($printers as $index => $printer) {
            $shellyId = getShellyIdFromPrinter($printer, $index);
            $networkAddress = isset($printer['netwerkadres']) ? trim($printer['netwerkadres']) : '';
            
            // Alleen IP adressen extraheren als er andere data in staat
            if (preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $networkAddress, $matches)) {
                $networkAddress = $matches[0];
            }
            
            // Alleen toevoegen als er een geldig IP adres is
            if (filter_var($networkAddress, FILTER_VALIDATE_IP)) {
                $shellyConfig[$shellyId] = [
                    'name' => $printer['Versie_Toestel'] ?? ('Printer ' . ($index + 1)),
                    'ip' => $networkAddress,
                    'printer_id' => getPrinterId($printer)
                ];
            }
        }
        
        // Update de configuratie in Firebase als er Shelly devices zijn
        if (count($shellyConfig) > 0) {
            $database->getReference('config/shelly_devices')->set($shellyConfig);
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Fout bij bijwerken Shelly configuratie in Firebase: " . $e->getMessage());
        return false;
    }
}

// Get all printers from the database
try {
    $stmt = $conn->prepare("SELECT * FROM Printer ORDER BY Versie_Toestel");
    $stmt->execute();
    $printers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Als we printers hebben geladen en Firebase werkt, update de Shelly configuratie
    if ($firebaseInitialized && !empty($printers)) {
        $configUpdated = updateShellyConfigInFirebase($database, $printers);
        if ($configUpdated) {
            // Log dat de configuratie is bijgewerkt
            error_log("Shelly configuratie bijgewerkt in Firebase met " . count($printers) . " printers");
        }
    }
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Verwerk AJAX-verzoeken eerst
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    // Haal status op indien gevraagd
    if (isset($_GET['action']) && $_GET['action'] === 'get_status') {
        $status = [];
        
        if ($firebaseInitialized) {
            try {
                $shellies = $database->getReference('shellies')->getValue() ?: [];
                $status = $shellies;
                
                // Ook de printer-shelly mapping doorgeven
                $printer_shelly_map = [];
                foreach ($printers as $index => $printer) {
                    $printerId = getPrinterId($printer);
                    $shellyId = getShellyIdFromPrinter($printer, $index);
                    $printer_shelly_map[$printerId] = $shellyId;
                    
                    // Controleer of deze printer actief is en voeg reserveringsinformatie toe
                    if (isset($shellies[$shellyId]) && 
                        isset($shellies[$shellyId]['state']) && 
                        $shellies[$shellyId]['state'] === 'on') {
                        
                        // Zoek naar actieve reserveringen voor deze printer
                        try {
                            $stmt = $conn->prepare("
                                SELECT Reservatie_ID 
                                FROM Reservatie 
                                WHERE Printer_ID = :printer_id 
                                AND CURRENT_TIMESTAMP BETWEEN PRINT_START AND PRINT_END 
                                AND print_started = 1
                                LIMIT 1
                            ");
                            $stmt->bindParam(':printer_id', $printerId);
                            $stmt->execute();
                            
                            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                // Voeg reserverings-ID toe aan de status
                                $status[$shellyId]['reservation_id'] = $row['Reservatie_ID'];
                            }
                        } catch (PDOException $e) {
                            error_log("Fout bij ophalen reserveringsinfo: " . $e->getMessage());
                        }
                    }
                }
                $status['_mapping'] = $printer_shelly_map;
                
            } catch (Exception $e) {
                error_log("Fout bij ophalen shelly status: " . $e->getMessage());
            }
        }
        
        echo json_encode($status);
        exit;
    }
    
    // Verwerk commando indien gevraagd via Firebase
    if (isset($_POST['action']) && isset($_POST['device'])) {
        $response = ['success' => false, 'message' => 'Onbekende fout'];
        
        if (!$firebaseInitialized) {
            $response = ['success' => false, 'message' => $initError];
            echo json_encode($response);
            exit;
        }
        
        $action = $_POST['action']; // 'on' of 'off'
        $shellyId = $_POST['device']; // Shelly ID (shelly1, shelly2, etc.)
        
        // Controleer geldige waarden
        if (($action === 'on' || $action === 'off')) {
            // Tijdstempel voor de actie
            $timestamp = date("Y-m-d H:i:s");
            
            try {
                if ($shellyId === 'all') {
                    // Zet alle apparaten aan/uit
                    $shellyIds = [];
                    
                    // Genereer shelly IDs voor alle printers
                    for ($i = 0; $i < count($printers); $i++) {
                        $shellyIds[] = "shelly" . ($i + 1);
                    }
                    
                    foreach ($shellyIds as $shellId) {
                        $database->getReference("web_commands/{$shellId}")->set([
                            'state' => $action,
                            'command_processed' => false,
                            'timestamp' => $timestamp,
                            'source' => 'admin_web'
                        ]);
                    }
                    
                    $response = ['success' => true, 'message' => "Commando om alle printers op {$action} te zetten is verstuurd"];
                } else {
                    // Zet specifieke printer aan/uit
                    $database->getReference("web_commands/{$shellyId}")->set([
                        'state' => $action,
                        'command_processed' => false,
                        'timestamp' => $timestamp,
                        'source' => 'admin_web'
                    ]);
                    
                    $response = ['success' => true, 'message' => "Commando verstuurd naar apparaat {$shellyId}"];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => "Firebase fout: " . $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => "Ongeldige parameters"];
        }
        
        echo json_encode($response);
        exit;
    }
}

// Handle printer status updates if form is submitted (traditionele formulier verwerking)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    if (isset($_POST['all_on'])) {
        // Set all printers to 'aan' in Firebase, maar niet in de database
        try {
            // Als Firebase ge�nitialiseerd is, stuur commando naar Firebase
            if ($firebaseInitialized) {
                $timestamp = date("Y-m-d H:i:s");
                $shellyIds = [];
                
                // Genereer shelly IDs voor alle printers
                for ($i = 0; $i < count($printers); $i++) {
                    $shellyIds[] = "shelly" . ($i + 1);
                }
                
                foreach ($shellyIds as $shellyId) {
                    $database->getReference("web_commands/{$shellyId}")->set([
                        'state' => 'on',
                        'command_processed' => false,
                        'timestamp' => $timestamp,
                        'source' => 'admin_web'
                    ]);
                }
                
                $success_message = "Commando om alle printers in te schakelen is verstuurd.";
            } else {
                $error_message = "Firebase niet beschikbaar. Printers kunnen niet worden aangestuurd.";
            }
            
        } catch (Exception $e) {
            $error_message = "Fout bij versturen van commando: " . $e->getMessage();
        }
    } elseif (isset($_POST['printer_id']) && isset($_POST['status'])) {
        try {
            $printer_id = $_POST['printer_id'];
            $status = $_POST['status'];
            
            // Als Firebase ge�nitialiseerd is, stuur ook commando naar Firebase
            if ($firebaseInitialized) {
                // Zoek de juiste printer index
                $printerIndex = -1;
                foreach ($printers as $index => $printer) {
                    if (getPrinterId($printer) == $printer_id) {
                        $printerIndex = $index;
                        break;
                    }
                }
                
                if ($printerIndex >= 0) {
                    $shellyId = "shelly" . ($printerIndex + 1);
                    $firebaseAction = ($status === 'aan') ? 'on' : 'off';
                    $timestamp = date("Y-m-d H:i:s");
                    
                    $database->getReference("web_commands/{$shellyId}")->set([
                        'state' => $firebaseAction,
                        'command_processed' => false,
                        'timestamp' => $timestamp,
                        'source' => 'admin_web'
                    ]);
                    
                    $success_message = "Commando verstuurd om printer status bij te werken.";
                } else {
                    $error_message = "Kon de bijbehorende Shelly niet vinden voor printer ID " . $printer_id;
                }
            } else {
                $error_message = "Firebase niet beschikbaar. Printer kan niet worden aangestuurd.";
            }
            
            // If it's an AJAX request, return JSON response
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                if (isset($error_message)) {
                    echo json_encode(['success' => false, 'message' => $error_message]);
                } else {
                    echo json_encode(['success' => true, 'message' => $success_message]);
                }
                exit;
            }
            
        } catch (Exception $e) {
            $error_message = "Fout bij versturen van commando: " . $e->getMessage();
            
            // If it's an AJAX request, return JSON response
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => false, 'message' => $error_message]);
                exit;
            }
        }
    }
}

// Function to safely get printer ID regardless of field name case
function getPrinterId($printer) {
    if (isset($printer['id'])) return $printer['id'];
    if (isset($printer['ID'])) return $printer['ID'];
    if (isset($printer['Id'])) return $printer['Id'];
    if (isset($printer['printer_id'])) return $printer['printer_id'];
    if (isset($printer['Printer_ID'])) return $printer['Printer_ID'];
    
    // If no ID field found, return 0 as fallback
    return 0;
}

// Clear any output buffers to prevent debug text
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Printers beheren - VIVES Maaklab</title>
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/buttons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/admin-controls.css">
    <link rel="stylesheet" href="assets/css/standardized-ui.css">
    <link rel="stylesheet" href="assets/css/hide-cursor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Ensure the page can scroll when content overflows while keeping footer at bottom */
        body, html {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        
        .container {
            min-height: 100vh; /* Full viewport height */
            display: flex;
            flex-direction: column;
        }
        
        main {
            flex: 1; /* Take remaining space */
            overflow-y: auto; /* Make only the main content scrollable */
            padding-bottom: 80px; /* Space for footer */
            position: relative;
        }
        
        /* Make the action buttons stick to the bottom */
        .action-buttons {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #eee;
            padding: 15px;
            display: flex;
            justify-content: center;
            z-index: 100; /* Ensure it stays on top */
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1); /* Add shadow for better separation */
        }
        
        /* Adjust printer grid for better scrolling */
        .printer-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 20px; /* Extra space at the bottom */
        }
    </style>
    <!-- JavaScript for clock functionality -->
    <script src="assets/js/clock.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Prevent text selection and other interactions -->
    <script src="assets/js/prevent-interactions.js"></script>
    <!-- Simple script to remove debug text -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Remove any debug text at top of page
        document.body.childNodes.forEach(node => {
            if (node.nodeType === Node.TEXT_NODE && node.textContent) {
                node.textContent = '';
            }
        });
    });
    </script>
</head>
<body>
    <div class="container">
        <!-- Header with logo and clock -->
        <header>
            <div class="logo">
                <img src="assets/images/vives_logo.png" alt="VIVES Logo">
            </div>
            <div class="welcome-text">Printers beheren</div>
            <div class="clock-container">
                <div id="clock"></div>
                <div id="date"></div>
            </div>
        </header>

        <!-- Main content area -->
        <main>
            <!-- Admin dashboard title removed -->
            
            <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!$firebaseInitialized): ?>
            <div class="alert alert-warning">
                <strong>Let op:</strong> Firebase-verbinding is niet ge�nitialiseerd. Shelly-apparaten kunnen niet worden aangestuurd.
                <?php if ($initError): ?>
                <br><small><?php echo htmlspecialchars($initError); ?></small>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Control panel for all-on button -->
            <div class="control-panel">
                <form method="post" action="" name="all_on" id="all-on-form">
                    <button type="submit" name="all_on" class="all-on-btn">
                        <i class="fas fa-power-off"></i> Alle printers aan
                    </button>
                </form>
            </div>

            <?php if (empty($printers)): ?>
            <div class="no-printers">
                <i class="fas fa-info-circle fa-3x" style="color:#999; margin-bottom:15px;"></i>
                <h3>Geen printers gevonden</h3>
                <p>Er zijn nog geen printers beschikbaar in het systeem.</p>
            </div>
            <?php else: ?>
            <div class="printer-grid">
                <?php foreach ($printers as $index => $printer): 
                    $printerId = getPrinterId($printer); // Use our helper function
                    $shellyId = getShellyIdFromPrinter($printer, $index); // Alleen intern gebruikt, niet getoond
                    // Clean up the network address to ensure we only display the IP address
                    $networkAddress = isset($printer['netwerkadres']) ? htmlspecialchars($printer['netwerkadres']) : '';
                    // Make sure we only display valid IP addresses
                    if (preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $networkAddress, $matches)) {
                        $cleanNetworkAddress = $matches[0];
                    } else {
                        $cleanNetworkAddress = $networkAddress;
                    }
                ?>
                <div class="printer-card status-<?php echo htmlspecialchars($printer['status'] ?? 'uit'); ?>" 
                     id="printer-card-<?php echo $printerId; ?>" 
                     data-printer-id="<?php echo $printerId; ?>"
                     data-shelly-id="<?php echo htmlspecialchars($shellyId); ?>">
                    <div class="printer-name"><?php echo htmlspecialchars($printer['Versie_Toestel']); ?></div>
                    <div class="printer-address"><?php echo $cleanNetworkAddress; ?></div>
                    <!-- Reservering info zal hier worden weergegeven indien nodig -->
                    <div class="printer-reservation-info" id="reservation-info-<?php echo $printerId; ?>"></div>
                    <div class="button-container">
                        <form method="post" class="printer-form" style="flex: 1;" data-printer-id="<?php echo $printerId; ?>">
                            <input type="hidden" name="printer_id" value="<?php echo $printerId; ?>">
                            <input type="hidden" name="status" value="aan">
                            <button type="submit" class="btn-on" data-action="on">AAN</button>
                        </form>
                        <form method="post" class="printer-form" style="flex: 1;" data-printer-id="<?php echo $printerId; ?>">
                            <input type="hidden" name="printer_id" value="<?php echo $printerId; ?>">
                            <input type="hidden" name="status" value="uit">
                            <button type="submit" class="btn-off" data-action="off" id="btn-off-<?php echo $printerId; ?>">UIT</button>
                        </form>
                    </div>
                    <!-- Hier wordt de Firebase status dynamisch toegevoegd -->
                    <div class="printer-firebase-status" id="firebase-status-<?php echo htmlspecialchars($shellyId); ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Action buttons at bottom -->
            <div class="action-buttons">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Uitloggen
                </a>
            </div>
        </main>
    </div>

    <!-- JavaScript for Enhanced Printer Management -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // The PHP now handles the clean IP display, but we'll keep this as a fallback
        document.querySelectorAll('.printer-address').forEach(element => {
            let text = element.textContent;
            // If the text doesn't look like a pure IP address, try to extract it
            if (text && !text.match(/^\b(?:\d{1,3}\.){3}\d{1,3}\b$/)) {
                let ipMatch = text.match(/\b(?:\d{1,3}\.){3}\d{1,3}\b/);
                if (ipMatch) {
                    element.textContent = ipMatch[0];
                }
            }
        });
        
        // Get all printer control forms
        const printerForms = document.querySelectorAll('.printer-form');
        
        // Add submit event listeners to all forms
        printerForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent normal form submission
                
                // Get the button that was clicked
                const button = this.querySelector('button');
                
                // Get the printer card
                const printerCard = this.closest('.printer-card');
                
                // Get the printer name
                const printerName = printerCard.querySelector('.printer-name').innerText;
                
                // Get the action (on/off)
                const action = button.dataset.action; // Gebruik data-action attribuut voor 'on'/'off'
                
                // Get the shelly ID (from data attribute)
                const shellyId = printerCard.dataset.shellyId;
                
                // Get the printer ID 
                const printerId = printerCard.dataset.printerId;
                
                // Update visual status immediately for better UX
                const statusClass = action === 'on' ? 'aan' : 'uit';
                printerCard.classList.remove('status-aan', 'status-uit');
                printerCard.classList.add('status-' + statusClass);
                
                // Show loading state on the button
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                button.disabled = true;
                
                // Submit to Firebase via our AJAX endpoint
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    data: {
                        action: action,
                        device: shellyId,
                        printer_id: printerId
                    },
                    success: function(data) {
                        if (data.success) {
                            showNotification(`Commando verstuurd naar printer "${printerName}".`, 'success');
                            // Update Firebase status after a short delay
                            setTimeout(updatePrinterStatus, 1000);
                        } else {
                            showNotification(data.message || 'Er is een fout opgetreden.', 'error');
                            
                            // DON'T revert the visual status change - keep the UI responsive
                            // The Firebase status update will correct it if needed
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        showNotification('Er is een fout opgetreden bij het versturen van het commando.', 'error');
                        
                        // DON'T revert the visual status change - keep the UI responsive
                        // The Firebase status update will correct it if needed
                    },
                    complete: function() {
                        // Reset button state
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                });
            });
        });
        
        // Handle the "all printers on" button
        const allOnForm = document.getElementById('all-on-form');
        if (allOnForm) {
            allOnForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Submit to Firebase via our AJAX endpoint
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    data: {
                        action: 'on',
                        device: 'all'
                    },
                    success: function(data) {
                        if (data.success) {
                            showNotification(data.message || 'Commando verstuurd om alle printers aan te zetten.', 'success');
                            
                            // Update alle printer kaarten voor visuele feedback
                            document.querySelectorAll('.printer-card').forEach(card => {
                                card.classList.remove('status-uit');
                                card.classList.add('status-aan');
                            });
                            
                            // Update Firebase status after a short delay
                            setTimeout(updatePrinterStatus, 1000);
                        } else {
                            showNotification(data.message || 'Er is een fout opgetreden.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        showNotification('Er is een fout opgetreden bij het versturen van het commando.', 'error');
                    }
                });
            });
        }
        
        // Function to show notifications
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = 'notification ' + type;
            notification.innerHTML = message;
            document.body.appendChild(notification);
            
            // Remove any existing notifications that might be in fadeout
            const oldNotifications = document.querySelectorAll('.notification.fadeOut');
            oldNotifications.forEach(note => note.remove());
            
            // Automatically remove after 3 seconds
            setTimeout(() => {
                notification.classList.add('fadeOut');
                setTimeout(() => {
                    notification.remove();
                }, 500);
            }, 3000);
        }
        
        // Functie om Firebase status op te halen en weer te geven
        function updatePrinterStatus() {
            $.ajax({
                url: window.location.href,
                type: 'GET',
                dataType: 'json',
                data: { action: 'get_status' },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(data) {
                    if (data && Object.keys(data).length > 0) {
                        // Mapping verwijderen als die aanwezig is
                        const mapping = data._mapping || {};
                        delete data._mapping;
                        
                        // Loop door alle shelly apparaten en update de status
                        for (const [shellyId, deviceInfo] of Object.entries(data)) {
                            const statusElement = document.getElementById(`firebase-status-${shellyId}`);
                            const printerCard = document.querySelector(`.printer-card[data-shelly-id="${shellyId}"]`);
                            
                            if (statusElement && deviceInfo && printerCard) {
                                // Get printer ID
                                const printerId = printerCard.dataset.printerId;
                                
                                // Basis status informatie
                                let statusHTML = `<div class="firebase-status-box">`;
                                
                                // Online/offline status
                                statusHTML += `<div class="status-info">Verbinding: ${deviceInfo.online ? 'Online' : 'Offline'}</div>`;
                                
                                // Als het apparaat online is en aan staat, toon extra informatie
                                if (deviceInfo.online) {
                                    const currentState = deviceInfo.state === 'on' ? 'aan' : 'uit';
                                    statusHTML += `<div class="status-info">Status: ${currentState.toUpperCase()}</div>`;
                                    
                                    if (deviceInfo.state === 'on') {
                                        // Hoe lang het apparaat al aan staat
                                        if (deviceInfo.start_time) {
                                            const startTime = new Date(deviceInfo.start_time.replace(' ', 'T'));
                                            const now = new Date();
                                            const diffMs = now - startTime;
                                            const diffMins = Math.floor(diffMs / 60000);
                                            const hours = Math.floor(diffMins / 60);
                                            const mins = diffMins % 60;
                                            
                                            statusHTML += `<div class="status-info">Actief sinds: ${hours}u ${mins}m</div>`;
                                        }
                                        
                                        // Controleer of de printer aan staat door een reservering
                                        const isReservation = deviceInfo.reservation_id ? true : false;
                                        const reservationId = deviceInfo.reservation_id || null;
                                        
                                        // Toon reserveringsinformatie en schakel de UIT knop uit indien nodig
                                        const reservationInfoElement = document.getElementById(`reservation-info-${printerId}`);
                                        const offButton = document.getElementById(`btn-off-${printerId}`);
                                        
                                        if (isReservation && reservationInfoElement) {
                                            reservationInfoElement.innerHTML = `<div class="reservation-badge">Reservatie #${reservationId}</div>`;
                                            
                                            // Disable the "uit" button
                                            if (offButton) {
                                                offButton.disabled = true;
                                                offButton.title = "Kan niet worden uitgeschakeld tijdens actieve reservering";
                                            }
                                        } else {
                                            // Clear reservation info if not a reservation
                                            if (reservationInfoElement) {
                                                reservationInfoElement.innerHTML = '';
                                            }
                                            
                                            // Enable the "uit" button if there's no active reservation
                                            if (offButton) {
                                                offButton.disabled = false;
                                                offButton.title = "";
                                            }
                                        }
                                    } else {
                                        // When printer is off, make sure reservation info is cleared
                                        const reservationInfoElement = document.getElementById(`reservation-info-${printerId}`);
                                        if (reservationInfoElement) {
                                            reservationInfoElement.innerHTML = '';
                                        }
                                        // Enable the "uit" button when printer is off
                                        const offButton = document.getElementById(`btn-off-${printerId}`);
                                        if (offButton) {
                                            offButton.disabled = false;
                                            offButton.title = "";
                                        }
                                    }
                                } else {
                                    statusHTML += `<div class="status-info">Status: Offline</div>`;
                                    
                                    // Laatste fout tonen indien beschikbaar
                                    if (deviceInfo.last_error) {
                                        statusHTML += `<div class="status-error">Fout: ${deviceInfo.last_error}</div>`;
                                    }
                                }
                                
                                statusHTML += `</div>`;
                                
                                // Update de status element
                                statusElement.innerHTML = statusHTML;
                                
                                // Update de visual status (aan/uit) op basis van Firebase
                                if (printerCard && deviceInfo.state) {
                                    const statusClass = deviceInfo.state === 'on' ? 'aan' : 'uit';
                                    printerCard.classList.remove('status-aan', 'status-uit');
                                    printerCard.classList.add('status-' + statusClass);
                                }
                            }
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Fout bij ophalen van Firebase status:", error);
                }
            });
        }
        
        // Voeg CSS toe voor Firebase status weergave en verbeterde UI
        const style = document.createElement('style');
        style.textContent = `
            .firebase-status-box {
                margin-top: 15px;
                padding: 10px;
                background-color: #f5f5f5;
                border-radius: 5px;
                border-left: 3px solid #4285F4;
                font-size: 0.9em;
            }
            .status-info {
                margin-bottom: 5px;
                color: #555;
            }
            .status-error {
                color: #f44336;
                font-style: italic;
            }
            /* Verbeterde uitlijning voor knoppen */
            .button-container {
                display: flex;
                width: 100%;
                justify-content: space-between;
                margin-top: 10px;
                gap: 10px; /* Voegt ruimte tussen de knoppen toe */
            }
            .printer-form {
                flex: 1; /* Beide formulieren nemen evenveel ruimte in */
                text-align: center;
            }
            .btn-on, .btn-off {
                width: 100%; /* Knoppen nemen de volledige breedte van het formulier in */
                padding: 8px 0;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-weight: bold;
                transition: background-color 0.2s;
            }
            .btn-on {
                background-color: #4CAF50;
                color: white;
            }
            .btn-off {
                background-color: #f44336;
                color: white;
            }
            .btn-on:hover {
                background-color: #3e8e41;
            }
            .btn-off:hover {
                background-color: #d32f2f;
            }
            .btn-off:disabled {
                background-color: #cccccc;
                color: #888888;
                cursor: not-allowed;
                opacity: 0.7;
            }
            /* Stijl voor reserveringsinformatie */
            .printer-reservation-info {
                margin: 5px 0;
                min-height: 24px; /* Houdt ruimte gereserveerd, zelfs als er geen reservering is */
                text-align: center; /* Centreer de inhoud horizontaal */
                width: 100%; /* Zorg dat de container de volledige breedte gebruikt */
            }
            .reservation-badge {
                display: inline-block;
                background-color: #FF9800;
                color: white;
                font-size: 0.85em;
                padding: 4px 8px;
                border-radius: 4px;
                margin-top: 5px;
                font-weight: bold;
            }
            /* Algemene notificatiestijlen */
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 4px;
                background-color: rgba(0, 0, 0, 0.8);
                color: white;
                z-index: 1000;
                transition: opacity 0.5s;
                max-width: 300px;
            }
            .notification.success {
                background-color: rgba(46, 125, 50, 0.9);
            }
            .notification.error {
                background-color: rgba(211, 47, 47, 0.9);
            }
            .notification.fadeOut {
                opacity: 0;
            }
        `;
        document.head.appendChild(style);
        
        // Initi�le update van printer status
        updatePrinterStatus();
        
        // Update printer status elke 10 seconden
        setInterval(updatePrinterStatus, 10000);
        
        // Handle any success messages on page load
        <?php if (isset($success_message)): ?>
        setTimeout(() => {
            const alertSuccess = document.querySelector('.alert-success');
            if (alertSuccess) {
                alertSuccess.classList.add('fadeOut');
                setTimeout(() => {
                    alertSuccess.remove();
                }, 500);
            }
        }, 3000);
        <?php endif; ?>

        // Informatieve console log
        console.log("Printer Management initialized successfully. Current date/time: 2025-05-08 14:57:15. User: larsvdkerkhove");
    });
    </script>

    <!-- Direct clock initialization script to ensure clock updates -->
    <script>
        // Get the clock and date elements
        const clockElement = document.getElementById('clock');
        const dateElement = document.getElementById('date');
        
        // Function to update the clock
        function updateClock() {
            const now = new Date();
            
            // Format the time (HH:MM:SS)
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const timeString = `${hours}:${minutes}:${seconds}`;
            
            // Format the date (DD/MM/YYYY)
            const day = String(now.getDate()).padStart(2, '0');
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const year = now.getFullYear();
            const dateString = `${day}/${month}/${year}`;
            
            // Update the elements
            if (clockElement) clockElement.textContent = timeString;
            if (dateElement) dateElement.textContent = dateString;
        }
        
        // Update the clock immediately and then every second
        updateClock();
        setInterval(updateClock, 1000);
    </script>
    
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