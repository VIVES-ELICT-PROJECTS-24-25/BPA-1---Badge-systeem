<?php
// Include database connection
require_once '../db_connection.php';

// Check if database is connected
if (!isDatabaseConnected()) {
    echo json_encode([
        'success' => false,
        'message' => getDatabaseError()
    ]);
    exit;
}

try {
    // Fetch all reservations with user and printer info
    $query = "
        SELECT r.*, 
               CONCAT(u.Voornaam, ' ', u.Naam) as gebruiker_naam,
               p.Versie_Toestel as printer_naam
        FROM reservatie r
        JOIN user u ON r.User_ID = u.User_ID
        LEFT JOIN printer p ON r.Printer_ID = p.Printer_ID
        ORDER BY r.PRINT_START DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $reservations = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'reservations' => $reservations
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
}
?>