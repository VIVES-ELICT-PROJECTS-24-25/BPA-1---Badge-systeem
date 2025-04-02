<?php
// Include database connection
require_once '../db_connection.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['userId'] ?? 0;
$cardId = $data['cardId'] ?? '';

// Validate input
if (empty($userId) || empty($cardId)) {
    echo json_encode(['success' => false, 'message' => 'Ongeldige invoer']);
    exit;
}

try {
    // Update the student's card ID
    $stmt = $conn->prepare("
        UPDATE vives 
        SET rfidkaartnr = ? 
        WHERE User_ID = ?
    ");
    $stmt->execute([$cardId, $userId]);
    
    // Check if the update was successful
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Kaart succesvol gekoppeld'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gebruiker niet gevonden of geen wijzigingen aangebracht'
        ]);
    }
} catch (PDOException $e) {
    // Handle database errors
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
}
?>