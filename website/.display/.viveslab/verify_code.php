<?php
// Include database connection
require_once 'db_connection.php';

// Stel PHP in om alle fouten te laten zien (ontwikkeling)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Log functie
function logError($message) {
    error_log("[verify_code.php] " . $message);
}

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
    
    // Extract code
    $code = isset($data['code']) ? trim($data['code']) : '';
    logError("Processing code: " . $code);
    
    // Validate input
    if (empty($code) || strlen($code) != 6 || !is_numeric($code)) {
        echo json_encode([
            'success' => false,
            'message' => 'Ongeldige code. Voer een 6-cijferige code in.'
        ]);
        logError("Invalid code format: " . $code);
        exit;
    }
    
    // Check database connection
    if (!isDatabaseConnected()) {
        echo json_encode([
            'success' => false,
            'message' => 'Database verbindingsfout: ' . getDatabaseError()
        ]);
        logError("Database connection failed: " . getDatabaseError());
        exit;
    }
    
    // Get current date and time
    $now = date('Y-m-d H:i:s');
    
    // Query to verify the code and get the associated reservation
    // AANGEPAST: gebruik Pincode in plaats van toegangscode
    $query = "
        SELECT r.*, p.Versie_Toestel, f.Type as filament_type, f.Kleur as filament_color
        FROM Reservatie r
        LEFT JOIN Printer p ON r.Printer_ID = p.Printer_ID
        LEFT JOIN Filament f ON r.filament_id = f.id
        WHERE r.Pincode = ? 
        AND r.PRINT_END >= ? 
        AND DATE_ADD(r.PRINT_START, INTERVAL -30 MINUTE) <= ?
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$code, $now, $now]);
    
    $reservation = $stmt->fetch();
    
    if ($reservation) {
        logError("Valid code, reservation found: " . $reservation['Reservatie_ID']);
        
        // Return success with reservation data
        echo json_encode([
            'success' => true,
            'message' => 'Code geaccepteerd',
            'reservation' => $reservation
        ]);
    } else {
        // Check if the code exists but is expired
        // AANGEPAST: gebruik Pincode in plaats van toegangscode
        $checkQuery = "
            SELECT COUNT(*) FROM Reservatie WHERE Pincode = ?
        ";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([$code]);
        $codeExists = (bool)$checkStmt->fetchColumn();
        
        if ($codeExists) {
            logError("Code exists but is expired or not yet valid: " . $code);
            echo json_encode([
                'success' => false,
                'message' => 'Deze code is verlopen of nog niet geldig'
            ]);
        } else {
            logError("Invalid code, not found: " . $code);
            echo json_encode([
                'success' => false,
                'message' => 'Ongeldige code'
            ]);
        }
    }
} catch (PDOException $e) {
    logError("PDO Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    logError("General Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Fout: ' . $e->getMessage()
    ]);
}
?>