<?php
// Schakel error reporting in voor ontwikkeling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Controleer of Composer autoloader beschikbaar is
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php'
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
    $serviceAccountPath = __DIR__ . '/../maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json';
    
    // Debug informatie
    if (!file_exists($serviceAccountPath)) {
        throw new Exception("Firebase service account bestand niet gevonden: $serviceAccountPath");
    }
    
    // Extra logging toevoegen
    error_log("Loading Firebase config from: " . $serviceAccountPath);
    
    // Firebase initialization voor nieuwere versies (5.x en hoger)
    $factory = new Kreait\Firebase\Factory();
    $firebase = $factory
        ->withServiceAccount($serviceAccountPath)
        ->withDatabaseUri('https://maaklab-project-default-rtdb.europe-west1.firebasedatabase.app');
    
    $database = $firebase->createDatabase();
    $firebaseInitialized = true;
    
    // Success logging
    error_log("Firebase successfully initialized");
    
} catch (Exception $e) {
    $initError = "Firebase initialisatie mislukt: " . $e->getMessage();
    error_log("Firebase initialization error: " . $e->getMessage());
}

// Function to check if Firebase connection is established
function isFirebaseConnected() {
    global $firebaseInitialized;
    return $firebaseInitialized;
}

// Function to get Firebase error message if any
function getFirebaseError() {
    global $initError;
    return $initError;
}

// Function to get Firebase database instance
function getFirebaseDatabase() {
    global $database;
    return $database;
}
?>