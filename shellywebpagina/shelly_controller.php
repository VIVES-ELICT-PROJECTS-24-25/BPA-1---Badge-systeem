<?php
// shelly_controller.php - Plaats dit op je Combell webserver

// Firebase configuratie
require_once 'vendor/autoload.php'; // Composer autoloader

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

// Firebase credentials - sla deze op in een veilig bestand op je server
$serviceAccountPath = __DIR__ . '/maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json';

// Firebase initialiseren
$factory = (new Factory)
    ->withServiceAccount($serviceAccountPath)
    ->withDatabaseUri('https://maaklab-project-default-rtdb.europe-west1.firebasedatabase.app');

$database = $factory->createDatabase();

// Shelly apparaten configuratie
$shellyDevices = [
    'shelly1' => [
        'name' => 'Shelly 1',
        'ip' => '192.168.0.250'
    ],
    'shelly2' => [
        'name' => 'Shelly 2',
        'ip' => '192.168.0.221'
    ]
];

// Functie om een Shelly-apparaat te schakelen VIA FIREBASE
function controlShellyPlug($deviceId, $state) {
    global $shellyDevices, $database;
    
    if (!isset($shellyDevices[$deviceId])) {
        return ['success' => false, 'message' => 'Apparaat niet gevonden'];
    }
    
    $device = $shellyDevices[$deviceId];
    $timestamp = date('Y-m-d H:i:s');
    $reference = $database->getReference("shellies/$deviceId");
    
    // Update Firebase met het commando en markeer als NIET verwerkt
    $updates = [
        'state' => $state,
        'last_toggled' => $timestamp,
        'command_processed' => false  // BELANGRIJK: Markeer als nieuw commando dat verwerkt moet worden
    ];
    
    // Als we het apparaat inschakelen, sla dan de start-tijd op
    if ($state == 'on') {
        $updates['start_time'] = $timestamp;
    }
    
    $reference->update($updates);
    
    return [
        'success' => true, 
        'message' => "Opdracht om Shelly {$device['name']} " . ($state == 'on' ? 'IN' : 'UIT') . " te schakelen is verstuurd"
    ];
}

// API endpoints
$action = isset($_GET['action']) ? $_GET['action'] : '';

header('Content-Type: application/json');

switch ($action) {
    case 'getDevices':
        $devices = [];
        foreach ($shellyDevices as $deviceId => $deviceInfo) {
            // Haal gegevens op uit Firebase
            $reference = $database->getReference("shellies/$deviceId");
            $deviceData = $reference->getValue();
            
            if (!$deviceData) {
                // Als er nog geen data is, initialiseren we het apparaat in Firebase
                $timestamp = date('Y-m-d H:i:s');
                
                $deviceData = [
                    'name' => $deviceInfo['name'],
                    'ip' => $deviceInfo['ip'],
                    'state' => 'unknown',
                    'online' => false,
                    'last_toggled' => $timestamp,
                    'command_processed' => true  // Geef aan dat er geen openstaand commando is
                ];
                
                $reference->set($deviceData);
            } else {
                // Voeg naam en IP toe aan de data als ze ontbreken
                $deviceData['name'] = $deviceInfo['name'];
                $deviceData['ip'] = $deviceInfo['ip'];
                
                // Runtime berekenen als het apparaat aan is
                if ($deviceData['state'] == 'on' && isset($deviceData['start_time'])) {
                    $startTime = new DateTime($deviceData['start_time']);
                    $now = new DateTime();
                    $interval = $now->diff($startTime);
                    
                    $deviceData['runtime'] = sprintf(
                        "%du %dm %ds",
                        $interval->h + ($interval->days * 24),
                        $interval->i,
                        $interval->s
                    );
                } else {
                    $deviceData['runtime'] = "0u 0m 0s";
                }
            }
            
            $devices[$deviceId] = $deviceData;
        }
        
        echo json_encode($devices);
        break;
        
    case 'control':
        $deviceId = isset($_POST['device_id']) ? $_POST['device_id'] : '';
        $state = isset($_POST['state']) ? $_POST['state'] : '';
        
        if (!$deviceId || !$state || !in_array($state, ['on', 'off'])) {
            echo json_encode(['success' => false, 'message' => 'Ongeldige parameters']);
            break;
        }
        
        $result = controlShellyPlug($deviceId, $state);
        echo json_encode($result);
        break;
        
    case 'refresh':
        // Bij refresh halen we alleen de huidige waarden uit Firebase op
        echo json_encode(['success' => true, 'message' => 'Apparaten worden ververst']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ongeldige actie']);
}
?>