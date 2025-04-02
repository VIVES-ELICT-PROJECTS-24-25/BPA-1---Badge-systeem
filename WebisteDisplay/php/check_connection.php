<?php
// Include database and Firebase configuration
require_once 'db_connection.php';
require_once 'firebase_config.php';

// Return connection status
header('Content-Type: application/json');
echo json_encode([
    'databaseConnected' => isDatabaseConnected(),
    'databaseError' => getDatabaseError(),
    'firebaseConnected' => isFirebaseConnected(),
    'firebaseError' => getFirebaseError()
]);
?>