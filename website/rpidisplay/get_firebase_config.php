<?php
// Dit is een VOLLEDIG bestand

// Check if service account file exists
$serviceAccountPath = __DIR__ . '/maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json';
if (!file_exists($serviceAccountPath)) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Firebase service account bestand niet gevonden: ' . $serviceAccountPath
    ]);
    exit;
}

// Read service account data
$serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Firebase service account bestand kon niet gelezen worden: ' . json_last_error_msg()
    ]);
    exit;
}

// Build client-side Firebase config
$clientConfig = [
    'apiKey' => 'AIzaSyAKVxIiV5iN57_YgSJp6V0GkMZQS4AZ1Pg', // Publieke API key
    'authDomain' => $serviceAccount['project_id'] . '.firebaseapp.com',
    'databaseURL' => 'https://' . $serviceAccount['project_id'] . '-default-rtdb.europe-west1.firebasedatabase.app',
    'projectId' => $serviceAccount['project_id'],
    'storageBucket' => $serviceAccount['project_id'] . '.appspot.com',
    'messagingSenderId' => '1079926642562',
    'appId' => '1:1079926642562:web:bd0ad7c01ad90dbe76b5f4'
];

// Return the config as JSON
header('Content-Type: application/json');
echo json_encode($clientConfig);
?>