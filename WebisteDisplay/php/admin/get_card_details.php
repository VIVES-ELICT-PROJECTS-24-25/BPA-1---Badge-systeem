<?php
// Include database connection
require_once '../db_connection.php';

// Get the user ID from query parameter
$userId = $_GET['userId'] ?? 0;

// Validate input
if (empty($userId)) {
    echo json_encode(['success' => false, 'message' => 'Ongeldige gebruiker ID']);
    exit;
}

try {
    // Get card details
    $cardStmt = $conn->prepare("
        SELECT v.*, u.Voornaam, u.Naam 
        FROM vives v 
        JOIN user u ON v.User_ID = u.User_ID 
        WHERE v.User_ID = ?
    ");
    $cardStmt->execute([$userId]);
    $card = $cardStmt->fetch();
    
    if (!$card) {
        // If card not found, try to get just user info
        $userStmt = $conn->prepare("
            SELECT User_ID, Voornaam, Naam 
            FROM user 
            WHERE User_ID = ?
        ");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Gebruiker niet gevonden']);
            exit;
        }
        
        // Return user without card info
        $card = $user;
    }
    
    // Get all opleidingen
    $opleidingenStmt = $conn->prepare("SELECT id, naam FROM opleidingen ORDER BY naam");
    $opleidingenStmt->execute();
    $opleidingen = $opleidingenStmt->fetchAll();
    
    // Return card and opleidingen data
    echo json_encode([
        'success' => true,
        'card' => $card,
        'opleidingen' => $opleidingen
    ]);
    
} catch (PDOException $e) {
    // Handle database errors
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
}
?>