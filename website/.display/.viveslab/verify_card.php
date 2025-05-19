<?php
// Schakel PHP error reporting in voor debugging (op productie uitzetten)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Log functie voor debugging
function logError($message) {
    error_log("[verify_card.php] " . $message);
}

// Controleer of db_connection.php bestaat
if (!file_exists('db_connection.php')) {
    echo json_encode([
        'success' => false,
        'message' => 'Database configuratiebestand niet gevonden'
    ]);
    logError("db_connection.php file not found");
    exit;
}

// Include database connection
require_once 'db_connection.php';

try {
    // Lees de raw POST data
    $rawData = file_get_contents('php://input');
    logError("Received raw data: " . $rawData);
    
    // Parse JSON data
    $data = json_decode($rawData, true);
    
    // Check voor JSON parsing errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false,
            'message' => 'Ongeldige JSON data: ' . json_last_error_msg()
        ]);
        logError("JSON parsing error: " . json_last_error_msg());
        exit;
    }
    
    // Extract card ID
    $cardId = isset($data['cardId']) ? trim($data['cardId']) : '';
    logError("Processing card ID: " . $cardId);
    
    // Validate input
    if (empty($cardId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Geen kaart ID ontvangen'
        ]);
        logError("Empty card ID received");
        exit;
    }
    
    // Check database connection - gebruik try/catch voor een veilige controle
    try {
        if (!isset($conn)) {
            echo json_encode([
                'success' => false,
                'message' => 'Database verbinding niet beschikbaar'
            ]);
            logError("Database connection not available");
            exit;
        }
        
        // Test de verbinding
        $testStmt = $conn->query("SELECT 1");
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database verbindingsfout: ' . $e->getMessage()
        ]);
        logError("Database connection test failed: " . $e->getMessage());
        exit;
    }
    
    // Eenvoudigere query voor testen
    $query = "
        SELECT u.User_ID, u.Voornaam, u.Naam, u.Type, v.rfidkaartnr 
        FROM User u 
        JOIN Vives v ON u.User_ID = v.User_ID 
        WHERE v.rfidkaartnr = ?
    ";
    
    logError("Executing query with card ID: " . $cardId);
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$cardId]);
    
    // Check if a user was found
    if ($user = $stmt->fetch()) {
        logError("User found: " . $user['Voornaam'] . " " . $user['Naam']);
        
        // Return success response with user data
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        logError("No user found for card ID: " . $cardId);
        
        // Try a direct check for the card
        $checkQuery = "SELECT COUNT(*) FROM Vives WHERE rfidkaartnr = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([$cardId]);
        $cardExists = (bool)$checkStmt->fetchColumn();
        
        if ($cardExists) {
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
    logError("PDO Exception: " . $e->getMessage());
    
    // Handle database errors
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    logError("General Exception: " . $e->getMessage());
    
    // Handle general errors
    echo json_encode([
        'success' => false,
        'message' => 'Fout: ' . $e->getMessage()
    ]);
}
?>