<?php
// Include database connection
require_once 'db_connection.php';

// Schakel error reporting in voor debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log de request voor debugging
$requestData = file_get_contents('php://input');
error_log("Received verify_card request: " . $requestData);

// Get the POST data
$data = json_decode($requestData, true);
$cardId = $data['cardId'] ?? '';

// Validate input
if (empty($cardId)) {
    error_log("No card ID received");
    echo json_encode(['success' => false, 'message' => 'Geen kaart ID ontvangen']);
    exit;
}

// Log the card ID for debugging
error_log("Verifying card ID: " . $cardId);

try {
    // Log de database verbinding status
    error_log("Database connected: " . (isDatabaseConnected() ? "Yes" : "No"));
    
    if (!isDatabaseConnected()) {
        echo json_encode(['success' => false, 'message' => 'Database verbindingsfout: ' . getDatabaseError()]);
        exit;
    }
    
    // Prepare and execute the query to find the user with the given RFID card
    $query = "
        SELECT u.User_ID, u.Voornaam, u.Naam, u.Type, v.rfidkaartnr, v.Vives_id 
        FROM user u 
        JOIN vives v ON u.User_ID = v.User_ID 
        WHERE v.rfidkaartnr = ?
    ";
    error_log("Executing query: " . $query . " with params: " . $cardId);
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$cardId]);
    
    // Check if a user was found
    if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        error_log("User found: " . $user['Voornaam'] . " " . $user['Naam']);
        
        // Update the user's last login timestamp
        $updateQuery = "
            UPDATE user 
            SET LaatsteAanmelding = NOW(), HuidigActief = 1 
            WHERE User_ID = ?
        ";
        error_log("Executing update: " . $updateQuery . " with ID: " . $user['User_ID']);
        
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->execute([$user['User_ID']]);
        
        // Return success response with user data
        $response = [
            'success' => true,
            'user' => $user
        ];
        error_log("Sending success response: " . json_encode($response));
        echo json_encode($response);
    } else {
        error_log("No user found with card ID: " . $cardId);
        
        // Controleer of de kaart in de database staat
        $checkStmt = $conn->prepare("SELECT rfidkaartnr FROM vives WHERE rfidkaartnr = ?");
        $checkStmt->execute([$cardId]);
        
        if ($checkStmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'Kaart gevonden maar geen actieve gebruiker'
            ]);
        } else {
            // No user found with this card ID
            echo json_encode([
                'success' => false,
                'message' => 'Kaart niet gevonden in het systeem'
            ]);
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    
    // Handle database errors
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    
    // Handle general errors
    echo json_encode([
        'success' => false,
        'message' => 'Fout: ' . $e->getMessage()
    ]);
}
?>