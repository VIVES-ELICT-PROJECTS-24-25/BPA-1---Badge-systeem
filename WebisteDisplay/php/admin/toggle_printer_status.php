<?php
// Include database connection
require_once '../db_connection.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$printerId = $data['printerId'] ?? 0;
$newStatus = $data['newStatus'] ?? '';

// Validate input
if (empty($printerId) || empty($newStatus)) {
    echo json_encode(['success' => false, 'message' => 'Ongeldige invoer']);
    exit;
}

// Validate status value
$validStatuses = ['beschikbaar', 'in_gebruik', 'onderhoud', 'defect'];
if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Ongeldige status']);
    exit;
}

try {
    // Update printer status
    $stmt = $conn->prepare("
        UPDATE printer 
        SET Status = ?, LAATSTE_STATUS_CHANGE = NOW() 
        WHERE Printer_ID = ?
    ");
    $stmt->execute([$newStatus, $printerId]);
    
    // Log the status change
    $logStmt = $conn->prepare("
        INSERT INTO printer_log (printer_id, action, timestamp, notes) 
        VALUES (?, ?, NOW(), ?)
    ");
    
    $action = 'maintenance';
    if ($newStatus === 'beschikbaar') $action = 'start';
    if ($newStatus === 'defect') $action = 'error';
    
    $notes = "Status handmatig gewijzigd naar: $newStatus";
    $logStmt->execute([$printerId, $action, $notes]);
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Printerstatus succesvol gewijzigd'
    ]);
    
} catch (PDOException $e) {
    // Handle database errors
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
}
?>