<?php
// Include database connection
require_once 'db_connection.php';

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);
$reservationId = $data['reservationId'] ?? 0;
$printerId = $data['printerId'] ?? 0;

// Validate input
if (empty($reservationId) || empty($printerId)) {
    echo json_encode(['success' => false, 'message' => 'Ongeldige invoer']);
    exit;
}

try {
    // Update printer status to 'in_gebruik'
    $updatePrinterStmt = $conn->prepare("
        UPDATE printer 
        SET Status = 'in_gebruik', LAATSTE_STATUS_CHANGE = NOW() 
        WHERE Printer_ID = ?
    ");
    $updatePrinterStmt->execute([$printerId]);
    
    // Log that this printer was started
    $logStartStmt = $conn->prepare("
        INSERT INTO printer_log (printer_id, action, timestamp) 
        VALUES (?, 'start', NOW())
    ");
    $logStartStmt->execute([$printerId]);
    
    // Update the reservation with the actual start time
    $updateReservationStmt = $conn->prepare("
        UPDATE reservatie 
        SET PRINT_START = NOW() 
        WHERE Reservatie_ID = ? AND PRINT_START IS NULL
    ");
    $updateReservationStmt->execute([$reservationId]);
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Printer succesvol geactiveerd'
    ]);
    
} catch (PDOException $e) {
    // Handle database errors
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
}
?>