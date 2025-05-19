<?php
// Start of file
session_start();

// Stel PHP in om alle fouten te laten zien (ontwikkeling)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Log functie
function logError($message) {
    error_log("[store_session.php] " . $message);
}

try {
    // Get the JSON input
    $rawData = file_get_contents('php://input');
    logError("Received raw data: " . $rawData);
    
    $data = json_decode($rawData, true);
    
    // Check voor JSON parsing errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false,
            'message' => 'Ongeldige JSON data: ' . json_last_error_msg()
        ]);
        logError("JSON parse error: " . json_last_error_msg());
        exit;
    }

    if (isset($data['user'])) {
        // Store user data in session
        $_SESSION['user'] = $data['user'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        logError("User session stored successfully: User ID " . $data['user']['User_ID']);
        
        echo json_encode(['success' => true]);
    } else {
        logError("No user data received");
        
        echo json_encode([
            'success' => false,
            'message' => 'Geen gebruikersgegevens ontvangen'
        ]);
    }
} catch (Exception $e) {
    logError("Exception: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Fout: ' . $e->getMessage()
    ]);
}
?>