<?php
// config.php - Database configuratie
define('DB_SERVER', 'ID462020_maaklab.db.webhosting.be');
define('DB_USERNAME', 'ID462020_maaklab');
define('DB_PASSWORD', 'kS8M607q97p82Gs079Ck');
define('DB_NAME', 'ID462020_maaklab');

// Maak database connectie
function getConnection() {
    try {
        $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        
        if ($conn->connect_error) {
            // Verzend een JSON foutmelding in plaats van HTML
            sendResponse('error', 'Database connection failed: ' . $conn->connect_error);
        }
        
        $conn->set_charset("utf8");
        return $conn;
    } catch (Exception $e) {
        // Vang eventuele andere uitzonderingen op
        sendResponse('error', 'Database exception: ' . $e->getMessage());
    }
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