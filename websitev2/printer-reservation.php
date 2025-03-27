<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

// Controleer of gebruiker is ingelogd
if (!isset($_SESSION['User_ID'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['User_ID'];
$error = '';
$success = '';

// Controleer of het een POST request is
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Haal formuliergegevens op
    $printerId = isset($_POST['printer_id']) ? (int)$_POST['printer_id'] : 0;
    $startDate = $_POST['start_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $endTime = $_POST['end_time'] ?? '';
    $filamentId = isset($_POST['filament_id']) ? (int)$_POST['filament_id'] : 0;
    $notes = trim($_POST['notes'] ?? '');
    
    // Validatie
    if (empty($printerId) || empty($startDate) || empty($startTime) || empty($endDate) || empty($endTime) || empty($filamentId)) {
        $error = 'Alle verplichte velden moeten worden ingevuld.';
    } else {
        // Bouw datetime strings
        $startDateTime = $startDate . ' ' . $startTime . ':00';
        $endDateTime = $endDate . ' ' . $endTime . ':00';
        
        // Controleer of startdatum in de toekomst ligt
        if (strtotime($startDateTime) < time()) {
            $error = 'De starttijd moet in de toekomst liggen.';
        } 
        // Controleer of einddatum na startdatum ligt
        elseif (strtotime($endDateTime) <= strtotime($startDateTime)) {
            $error = 'De eindtijd moet na de starttijd liggen.';
        } else {
            try {
                // Begin een transactie
                $conn->beginTransaction();
                
                // Controleer of printer beschikbaar is
                $stmt = $conn->prepare("SELECT Status FROM Printer WHERE Printer_ID = ?");
                $stmt->execute([$printerId]);
                $printer = $stmt->fetch();
                
                if (!$printer) {
                    $error = 'De geselecteerde printer bestaat niet.';
                } elseif ($printer['Status'] !== 'beschikbaar') {
                    $error = 'De geselecteerde printer is momenteel niet beschikbaar.';
                } else {
                    // Controleer of printer al gereserveerd is in het opgegeven tijdslot
                    $stmt = $conn->prepare("
                        SELECT Reservatie_ID 
                        FROM Reservatie 
                        WHERE Printer_ID = ? 
                          AND Status != 'geannuleerd'
                          AND (
                              (PRINT_START <= ? AND PRINT_END > ?) OR
                              (PRINT_START < ? AND PRINT_END >= ?) OR
                              (PRINT_START >= ? AND PRINT_END <= ?)
                          )
                    ");
                    $stmt->execute([
                        $printerId, 
                        $endDateTime, $startDateTime,    // Overlap begin
                        $endDateTime, $startDateTime,    // Overlap eind
                        $startDateTime, $endDateTime     // Volledig binnen
                    ]);
                    
                    if ($stmt->rowCount() > 0) {
                        $error = 'De printer is al gereserveerd in het gekozen tijdslot.';
                    } else {
                        // Genereer een nieuw Reservatie_ID (auto increment simuleren)
                        $stmtMaxId = $conn->query("SELECT MAX(Reservatie_ID) as maxId FROM Reservatie");
                        $result = $stmtMaxId->fetch();
                        $newReservationId = ($result['maxId'] ?? 0) + 1;
                        
                        // Maak de reservering
                        $stmt = $conn->prepare("
                            INSERT INTO Reservatie (
                                Reservatie_ID, User_ID, Printer_ID, DATE_TIME_RESERVATIE, 
                                PRINT_START, PRINT_END, Status, filament_id, opmerking
                            ) VALUES (?, ?, ?, NOW(), ?, ?, 'wachtend', ?, ?)
                        ");
                        $stmt->execute([
                            $newReservationId,
                            $userId,
                            $printerId,
                            $startDateTime,
                            $endDateTime,
                            $filamentId,
                            $notes
                        ]);
                        
                        // Update printer status als de reservering meteen begint
                        if (strtotime($startDateTime) <= time() + 900) { // Binnen 15 minuten
                            $stmt = $conn->prepare("
                                UPDATE Printer 
                                SET Status = 'in_gebruik' 
                                WHERE Printer_ID = ?
                            ");
                            $stmt->execute([$printerId]);
                            
                            // Update reservering status naar actief
                            $stmt = $conn->prepare("
                                UPDATE Reservatie 
                                SET Status = 'actief' 
                                WHERE Reservatie_ID = ?
                            ");
                            $stmt->execute([$newReservationId]);
                        }
                        
                        // Commit transactie
                        $conn->commit();
                        
                        // Redirect naar reserveringsbevestiging
                        header('Location: reservation-details.php?id=' . $newReservationId . '&new=1');
                        exit;
                    }
                }
            } catch (PDOException $e) {
                // Rollback bij fouten
                $conn->rollBack();
                $error = 'Er is een fout opgetreden bij het maken van je reservering: ' . $e->getMessage();
            }
        }
    }
}

// Als er een fout is, terug naar printer details pagina met foutmelding
if ($error) {
    $_SESSION['reservation_error'] = $error;
    header('Location: printer-details.php?id=' . $printerId);
    exit;
}
?>