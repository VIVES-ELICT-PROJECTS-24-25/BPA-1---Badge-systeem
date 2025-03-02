<?php
// config.php - Database configuratie
define('DB_SERVER', 'ID462020_maaklab.db.webhosting.be');
define('DB_USERNAME', 'zie groep');
define('DB_PASSWORD', 'zie groep');
define('DB_NAME', 'ID462020_maaklab');

// Maak database connectie
function getConnection() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8");
    return $conn;
}

// Functie om JSON response te sturen
function sendResponse($status, $message, $data = null) {
    header('Content-Type: application/json');
    
    $response = [
        'status' => $status,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

// Functie om request body te parsen
function getRequestBody() {
    return json_decode(file_get_contents('php://input'), true);
}
?>
