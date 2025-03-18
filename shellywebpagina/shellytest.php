<?php
// Schakel error reporting in voor ontwikkeling
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

if (!$autoloaded) {
    die("Fout: Composer autoloader niet gevonden. Voer 'composer install' uit in de projectmap.");
}

// Firebase-verbinding in een try/catch blok
$database = null;
$firebaseInitialized = false;
$initError = "";

try {
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
} catch (Exception $e) {
    $initError = "Firebase initialisatie mislukt: " . $e->getMessage();
    // Geen die() hier, we willen de HTML-interface nog steeds tonen met foutmelding
}

// Functie om status op te halen met foutafhandeling
function getShellyStatus() {
    global $database, $firebaseInitialized;
    
    if (!$firebaseInitialized || !$database) {
        return [];
    }
    
    try {
        $shellies = $database->getReference('shellies')->getValue();
        return $shellies ?: [];
    } catch (Exception $e) {
        error_log("Fout bij ophalen Shelly status: " . $e->getMessage());
        return [];
    }
}

// Verwerk AJAX-verzoeken eerst
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    // Haal status op indien gevraagd
    if (isset($_GET['action']) && $_GET['action'] === 'get_status') {
        $status = getShellyStatus();
        echo json_encode($status);
        exit;
    }
    
    // Verwerk commando indien gevraagd
    if (isset($_POST['action']) && isset($_POST['device'])) {
        $response = ['success' => false, 'message' => 'Onbekende fout'];
        
        if (!$firebaseInitialized) {
            $response = ['success' => false, 'message' => $initError];
            echo json_encode($response);
            exit;
        }
        
        $action = $_POST['action']; // 'on' of 'off'
        $deviceId = $_POST['device']; // 'shelly1', 'shelly2', of 'all'
        
        // Controleer geldige waarden
        if (($action === 'on' || $action === 'off') && 
            ($deviceId === 'shelly1' || $deviceId === 'shelly2' || $deviceId === 'all')) {
            
            // Tijdstempel voor de actie
            $timestamp = date("Y-m-d H:i:s");
            
            try {
                if ($deviceId === 'all') {
                    // Zet alle apparaten aan/uit
                    $devices = ['shelly1', 'shelly2'];
                    foreach ($devices as $device) {
                        $database->getReference("web_commands/{$device}")->set([
                            'state' => $action,
                            'command_processed' => false,
                            'timestamp' => $timestamp,
                            'source' => 'web'
                        ]);
                    }
                    $response = ['success' => true, 'message' => "Alle apparaten zijn op {$action} gezet"];
                } else {
                    // Zet specifiek apparaat aan/uit
                    $database->getReference("web_commands/{$deviceId}")->set([
                        'state' => $action,
                        'command_processed' => false,
                        'timestamp' => $timestamp,
                        'source' => 'web'
                    ]);
                    $response = ['success' => true, 'message' => "{$deviceId} is op {$action} gezet"];
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
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MaakLab Shelly Control</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .device-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f9f9f9;
        }
        .status-indicator {
            display: inline-block;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .status-on {
            background-color: #4CAF50;
        }
        .status-off {
            background-color: #F44336;
        }
        .status-unknown {
            background-color: #9E9E9E;
        }
        .button-group {
            margin-top: 15px;
        }
        button {
            padding: 8px 15px;
            margin-right: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .on-button {
            background-color: #4CAF50;
            color: white;
        }
        .off-button {
            background-color: #F44336;
            color: white;
        }
        .master-controls {
            margin: 20px 0;
            padding: 15px;
            background-color: #e9e9e9;
            border-radius: 8px;
        }
        .status-info {
            margin-top: 5px;
            font-size: 0.9em;
            color: #666;
        }
        .error-box {
            background-color: #ffebee;
            border: 1px solid #f44336;
            color: #d32f2f;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        /* Nieuwe stijlen voor elektrische gegevens */
        .electrical-data {
            margin-top: 15px;
            padding: 12px;
            background-color: #e8f5e9;
            border-radius: 6px;
            border-left: 4px solid #4CAF50;
        }
        .electrical-data h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #2E7D32;
            font-size: 16px;
        }
        .electrical-data-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 8px;
        }
        .electrical-data-item {
            flex: 1;
            min-width: 120px;
        }
        .electrical-data-item .label {
            font-weight: bold;
            font-size: 12px;
            color: #555;
        }
        .electrical-data-item .value {
            font-size: 14px;
        }
        .offline-message {
            color: #F44336;
            font-style: italic;
        }
        .updated-at {
            font-size: 11px;
            color: #757575;
            margin-top: 8px;
            text-align: right;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>MaakLab Shelly Control</h1>
    
    <?php if (!$firebaseInitialized): ?>
    <div class="error-box">
        <strong>Fout bij verbinding met Firebase:</strong> <?php echo htmlspecialchars($initError); ?>
    </div>
    <?php endif; ?>
    
    <div class="master-controls">
        <h2>Alle apparaten</h2>
        <div class="button-group">
            <button class="on-button" onclick="controlAllDevices('on')">Alle AAN</button>
            <button class="off-button" onclick="controlAllDevices('off')">Alle UIT</button>
        </div>
    </div>
    
    <div id="devices-container">
        <!-- Apparaten worden hier dynamisch geladen -->
        <p>Apparaten laden...</p>
    </div>
    
    <script>
        // Functie om apparaatstatus op te halen
        function updateDeviceStatus() {
            $.ajax({
                url: window.location.href,
                type: 'GET',
                dataType: 'json',
                data: { action: 'get_status' },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(data) {
                    let html = '';
                    
                    if (data && Object.keys(data).length > 0) {
                        for (const [deviceId, deviceInfo] of Object.entries(data)) {
                            const statusClass = deviceInfo.state === 'on' ? 'status-on' : 
                                              (deviceInfo.state === 'off' ? 'status-off' : 'status-unknown');
                            
                            let onlineStatus = deviceInfo.online ? 'Online' : 'Offline';
                            let lastToggled = deviceInfo.last_toggled || 'Onbekend';
                            let runningTime = '';
                            
                            if (deviceInfo.state === 'on' && deviceInfo.start_time) {
                                // Bereken hoe lang het apparaat al aan staat
                                const startTime = new Date(deviceInfo.start_time.replace(' ', 'T'));
                                const now = new Date();
                                const diffMs = now - startTime;
                                const diffMins = Math.floor(diffMs / 60000);
                                const hours = Math.floor(diffMins / 60);
                                const mins = diffMins % 60;
                                
                                runningTime = `<div class="status-info">Actief sinds: ${hours}u ${mins}m</div>`;
                            }
                            
                            // Basis apparaat informatie
                            html += `
                                <div class="device-card">
                                    <h2>
                                        <span class="status-indicator ${statusClass}"></span>
                                        ${deviceInfo.name} (${deviceId})
                                    </h2>
                                    <div class="status-info">Status: ${deviceInfo.state.toUpperCase()}</div>
                                    <div class="status-info">Verbinding: ${onlineStatus}</div>
                                    <div class="status-info">Laatst geschakeld: ${lastToggled}</div>
                                    ${runningTime}
                            `;
                            
                            // Voeg elektrische gegevens toe als het apparaat online is
                            if (deviceInfo.online && deviceInfo.state === 'on') {
                                // Controleer of de elektrische gegevens beschikbaar zijn
                                const hasElectricalData = deviceInfo.voltage !== undefined || 
                                                        deviceInfo.current !== undefined || 
                                                        deviceInfo.calculated_power !== undefined;
                                
                                if (hasElectricalData) {
                                    // Formatteren van de elektrische gegevens
                                    const voltage = deviceInfo.voltage !== undefined ? parseFloat(deviceInfo.voltage).toFixed(1) : 'N/A';
                                    const current = deviceInfo.current !== undefined ? parseFloat(deviceInfo.current).toFixed(3) : 'N/A';
                                    const calcPower = deviceInfo.calculated_power !== undefined ? parseFloat(deviceInfo.calculated_power).toFixed(2) : 'N/A';
                                    const reportedPower = deviceInfo.reported_power !== undefined ? parseFloat(deviceInfo.reported_power).toFixed(2) : 'N/A';
                                    const totalEnergy = deviceInfo.last_energy_reading !== undefined ? (parseFloat(deviceInfo.last_energy_reading) / 1000).toFixed(3) : 'N/A';
                                    const updatedAt = deviceInfo.electrical_updated_at || 'Onbekend';
                                    
                                    html += `
                                        <div class="electrical-data">
                                            <h3>Verbruiksgegevens</h3>
                                            <div class="electrical-data-row">
                                                <div class="electrical-data-item">
                                                    <div class="label">Spanning</div>
                                                    <div class="value">${voltage} V</div>
                                                </div>
                                                <div class="electrical-data-item">
                                                    <div class="label">Stroom</div>
                                                    <div class="value">${current} A</div>
                                                </div>
                                            </div>
                                            <div class="electrical-data-row">
                                                <div class="electrical-data-item">
                                                    <div class="label">Berekend vermogen</div>
                                                    <div class="value">${calcPower} W</div>
                                                </div>
                                                <div class="electrical-data-item">
                                                    <div class="label">Gerapporteerd vermogen</div>
                                                    <div class="value">${reportedPower} W</div>
                                                </div>
                                            </div>
                                            <div class="electrical-data-row">
                                                <div class="electrical-data-item">
                                                    <div class="label">Totaal verbruik</div>
                                                    <div class="value">${totalEnergy} kWh</div>
                                                </div>
                                            </div>
                                            <div class="updated-at">Bijgewerkt op: ${updatedAt}</div>
                                        </div>
                                    `;
                                }
                            } else if (deviceInfo.online && deviceInfo.state === 'off') {
                                // Apparaat is wel online maar uitgeschakeld
                                html += `
                                    <div class="electrical-data">
                                        <h3>Verbruiksgegevens</h3>
                                        <div class="status-info">Apparaat is momenteel uitgeschakeld. Geen verbruiksgegevens beschikbaar.</div>
                                    </div>
                                `;
                            } else if (!deviceInfo.online) {
                                // Apparaat is offline
                                html += `
                                    <div class="electrical-data">
                                        <h3>Verbruiksgegevens</h3>
                                        <div class="offline-message">Apparaat is offline. Geen verbinding mogelijk.</div>
                                    </div>
                                `;
                            }
                            
                            // Knoppen voor bediening
                            html += `
                                    <div class="button-group">
                                        <button class="on-button" onclick="controlDevice('${deviceId}', 'on')">AAN</button>
                                        <button class="off-button" onclick="controlDevice('${deviceId}', 'off')">UIT</button>
                                    </div>
                                </div>
                            `;
                        }
                    } else {
                        html = '<p>Geen apparaten gevonden of verbindingsfout met Firebase</p>';
                    }
                    
                    $('#devices-container').html(html);
                },
                error: function(xhr, status, error) {
                    $('#devices-container').html(`<p>Fout bij het ophalen van apparaatstatus: ${error}</p>`);
                    console.error("AJAX fout:", xhr.responseText);
                }
            });
        }
        
        // Functie om een apparaat te bedienen
        function controlDevice(deviceId, action) {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                data: {
                    action: action,
                    device: deviceId
                },
                success: function(response) {
                    if (response.success) {
                        console.log(response.message);
                        // Na korte vertraging status updaten om Firebase tijd te geven
                        setTimeout(updateDeviceStatus, 1000);
                    } else {
                        alert('Fout: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Er is een fout opgetreden bij het verzenden van het commando.');
                    console.error("AJAX fout:", xhr.responseText);
                }
            });
        }
        
        // Functie om alle apparaten te bedienen
        function controlAllDevices(action) {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                data: {
                    action: action,
                    device: 'all'
                },
                success: function(response) {
                    if (response.success) {
                        console.log(response.message);
                        // Na korte vertraging status updaten om Firebase tijd te geven
                        setTimeout(updateDeviceStatus, 1000);
                    } else {
                        alert('Fout: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Er is een fout opgetreden bij het verzenden van het commando.');
                    console.error("AJAX fout:", xhr.responseText);
                }
            });
        }
        
        // Initiële status ophalen
        $(document).ready(function() {
            updateDeviceStatus();
            // Elke 10 seconden status verversen
            setInterval(updateDeviceStatus, 10000);
        });
    </script>
</body>
</html>