<?php
/**
 * API endpoint: Get Active Prints
 * 
 * Returns a list of all active print jobs with their details
 * Required parameter: token (for authentication)
 */

// Set JSON content type and allow CORS for API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Define API security token
$expected_token = 'SUlhsg673GSbgsJYS6352jkdaLK';

// Check if token is provided and valid
$provided_token = isset($_GET['token']) ? $_GET['token'] : '';
if ($provided_token !== $expected_token) {
    echo json_encode(['error' => 'Ongeldige authenticatie']);
    exit;
}

// Now that we're authenticated, include the database connection
// BELANGRIJK: Verander het pad naar het correcte bestand
require_once '../db_connection.php';  // Dit wijst naar de bovenliggende directory

// Check if connection was successful
if (!isDatabaseConnected()) {
    echo json_encode(['error' => getDatabaseError()]);
    exit;
}

try {
    // Get all active print jobs
    $query = "
        SELECT r.*, p.Netwerkadres, p.Versie_Toestel 
        FROM Reservatie r
        JOIN Printer p ON r.Printer_ID = p.Printer_ID
        WHERE r.print_started = 1 
        AND (r.print_completed = 0 OR r.print_completed IS NULL)
        AND r.PRINT_END >= NOW()
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return success with the records
    echo json_encode([
        'success' => true,
        'data' => $records
    ]);
    
} catch (Exception $e) {
    // Report any errors that occurred during query execution
    echo json_encode([
        'error' => 'Database query error: ' . $e->getMessage()
    ]);
}
?>