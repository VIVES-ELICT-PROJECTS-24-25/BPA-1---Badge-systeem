<?php
// check_print_status.php - Controleert of een print actief is
header('Content-Type: application/json');

// Include database connection
require_once 'db_connection.php';

// Initialiseer de response
$response = [
    'success' => false,
    'is_active' => false,
    'message' => ''
];

// Verkrijg de reserverings-ID
$reservationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($reservationId <= 0) {
    $response['message'] = 'Geen geldige reserverings-ID opgegeven';
    echo json_encode($response);
    exit;
}

try {
    // Controleer of de database verbinding werkt
    if (!isDatabaseConnected()) {
        $response['message'] = 'Database verbindingsfout: ' . getDatabaseError();
        echo json_encode($response);
        exit;
    }
    
    // Query om te controleren of de print actief is
    $query = "
        SELECT 
            print_started, 
            print_completed,
            print_start_time,
            print_end_time,
            PRINT_END 
        FROM Reservatie 
        WHERE Reservatie_ID = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$reservationId]);
    $printData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$printData) {
        $response['message'] = 'Reservering niet gevonden';
        echo json_encode($response);
        exit;
    }
    
    // Controleer of de print is gestart maar niet voltooid
    $isStarted = (bool)$printData['print_started'];
    $isCompleted = (bool)$printData['print_completed'];
    
    // Controleer of de reservering nog actief is
    $now = new DateTime();
    $endTime = new DateTime($printData['PRINT_END']);
    $isExpired = $now > $endTime;
    
    // Print is actief als deze is gestart maar niet voltooid en de reservering nog niet is verlopen
    $isActive = $isStarted && !$isCompleted;
    
    $response = [
        'success' => true,
        'is_active' => $isActive,
        'is_expired' => $isExpired,
        'print_started' => $isStarted,
        'print_completed' => $isCompleted,
        'message' => $isActive ? 'Print is actief' : 'Print is niet actief'
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    $response['message'] = 'Database fout: ' . $e->getMessage();
    echo json_encode($response);
} catch (Exception $e) {
    $response['message'] = 'Fout: ' . $e->getMessage();
    echo json_encode($response);
}
?>