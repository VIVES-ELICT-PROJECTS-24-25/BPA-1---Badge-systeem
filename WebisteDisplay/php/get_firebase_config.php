<?php
// Include Firebase configuration
require_once 'firebase_config.php';

// Check if Firebase is initialized
if (!isFirebaseConnected()) {
    echo json_encode([
        'error' => getFirebaseError()
    ]);
    exit;
}

// Get Firebase configuration from the service account file
$serviceAccountPath = __DIR__ . '/../maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json';
if (!file_exists($serviceAccountPath)) {
    echo json_encode([
        'error' => 'Firebase service account bestand niet gevonden'
    ]);
    exit;
}

// Read service account data
$serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);

// Build client-side Firebase config
$clientConfig = [
    'apiKey' => 'AIzaSyAKVxIiV5iN57_YgSJp6V0GkMZQS4AZ1Pg', // Dit moet je vervangen met je echte API key
    'authDomain' => $serviceAccount['project_id'] . '.firebaseapp.com',
    'databaseURL' => 'https://' . $serviceAccount['project_id'] . '-default-rtdb.europe-west1.firebasedatabase.app',
    'projectId' => $serviceAccount['project_id'],
    'storageBucket' => $serviceAccount['project_id'] . '.appspot.com',
    'messagingSenderId' => '1079926642562', // Dit moet je vervangen met je echte Sender ID
    'appId' => '1:1079926642562:web:bd0ad7c01ad90dbe76b5f4' // Dit moet je vervangen met je echte App ID
];

// Return the config as JSON
header('Content-Type: application/json');
echo json_encode($clientConfig);
?>