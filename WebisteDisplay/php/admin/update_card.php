<?php
// Include database connection
require_once '../db_connection.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['userId'] ?? 0;
$rfidkaartnr = $data['rfidkaartnr'] ?? '';
$vivesId = $data['vivesId'] ?? '';
$type = $data['type'] ?? 'student';
$opleidingId = $data['opleidingId'] ?? null;

// Validate input
if (empty($userId)) {
    echo json_encode(['success' => false, 'message' => 'Ongeldige gebruiker ID']);
    exit;
}

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Check if vives record exists for this user
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM vives WHERE User_ID = ?");
    $checkStmt->execute([$userId]);
    $exists = (int)$checkStmt->fetchColumn() > 0;
    
    if ($exists) {
        // Update existing record
        $updateStmt = $conn->prepare("
            UPDATE vives 
            SET rfidkaartnr = ?, Vives_id = ?, Type = ?, opleiding_id = ? 
            WHERE User_ID = ?
        ");
        $updateStmt->execute([$rfidkaartnr, $vivesId, $type, $opleidingId ?: null, $userId]);
    } else {
        // Insert new record
        $insertStmt = $conn->prepare("
            INSERT INTO vives (User_ID, Voornaam, Vives_id, opleiding_id, Type, rfidkaartnr) 
            SELECT User_ID, Voornaam, ?, ?, ?, ? 
            FROM user WHERE User_ID = ?
        ");
        $insertStmt->execute([$vivesId, $opleidingId ?: null, $type, $rfidkaartnr, $userId]);
    }
    
    // Make sure the user's type is set properly in the user table if needed
    $userTypeStmt = $conn->prepare("
        UPDATE user 
        SET Type = ? 
        WHERE User_ID = ?
    ");
    $userType = 'student';
    if ($type === 'onderzoeker') $userType = 'onderzoeker';
    if ($type === 'medewerker') $userType = 'beheerder';
    $userTypeStmt->execute([$userType, $userId]);
    
    // Commit transaction
    $conn->commit();
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Kaartgegevens succesvol bijgewerkt'
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    
    // Handle database errors
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
}
?>