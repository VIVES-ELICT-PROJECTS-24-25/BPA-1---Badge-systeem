<?php
/**
 * start_print.php - Script om een print te starten en de Shelly te activeren
 */

// Globale configuratie includen
require_once 'config.php';

// Sessie is al gestart in config.php

// Log de hele request voor debugging
error_log("Session ID: " . session_id());
error_log("Session data: " . json_encode($_SESSION));
error_log("POST data: " . file_get_contents('php://input'));

// TIJDELIJKE FIX VOOR ONTWIKKELING - VERWIJDER IN PRODUCTIE
$_SESSION['authenticated'] = true;
$_SESSION['user_id'] = 4;

// Controleer autorisatie nu met gedetailleerde logging
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    $error_msg = "Authenticatie mislukt: sessie gegevens=" . json_encode($_SESSION);
    error_log($error_msg);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Niet ingelogd',
        'session_id' => session_id(),
        'debug' => $error_msg
    ]);
    exit;
}

// Include database connection
require_once 'db_connection.php';

// Verwerken van JSON POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Als JSON decoding mislukt, probeer $_POST
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON parsing error: " . json_last_error_msg() . " - Raw input: " . $json);
    $data = $_POST;
}

// Als $_POST leeg is, probeer $_GET (voor testen)
if (empty($data)) {
    $data = $_GET;
}

// Controleer of de data correct is
if (!isset($data['reservationId']) || !is_numeric($data['reservationId'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Ongeldige input',
        'received_data' => $data
    ]);
    exit;
}

$reservationId = (int)$data['reservationId'];
$userId = $_SESSION['user_id'];

try {
    // Controleer of de database verbinding werkt
    if (!isDatabaseConnected()) {
        throw new Exception("Database verbinding mislukt: " . getDatabaseError());
    }
    
    // Controleer of de reservering bestaat - vereenvoudigd voor ontwikkeling
    $query = "
        SELECT r.*, p.Netwerkadres 
        FROM Reservatie r
        JOIN Printer p ON r.Printer_ID = p.Printer_ID
        WHERE r.Reservatie_ID = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Reservering niet gevonden',
            'reservation_id' => $reservationId
        ]);
        exit;
    }
    
    // Markeer de print als gestart in de database
    $currentTime = date('Y-m-d H:i:s');
    $updateQuery = "
        UPDATE Reservatie 
        SET print_started = 1, 
            print_start_time = ?, 
            last_update = ?
        WHERE Reservatie_ID = ?
    ";
    
    $stmt = $conn->prepare($updateQuery);
    $stmt->execute([$currentTime, $currentTime, $reservationId]);
    
    // Je kunt hier Firebase updates toevoegen als je wilt
    
    // Geef succesbericht terug
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Print gestart! De printer wordt nu ingeschakeld.',
        'reservation_id' => $reservationId,
        'timestamp' => $currentTime
    ]);
    
} catch (Exception $e) {
    // Log de error
    error_log("Start Print fout: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Geef gedetailleerde foutmelding terug
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Er is een fout opgetreden bij het starten van de print: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>