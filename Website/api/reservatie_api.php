<?php
// reservatie_api.php - API endpoints voor reserveringen

require_once 'config.php';

// Set headers voor Cross-Origin Resource Sharing (CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Bepaal de HTTP methode
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Get reservatie_id from URL if provided
$reservatieId = null;
if (isset($_GET['id'])) {
    $reservatieId = intval($_GET['id']);
}

// Verwerk het request op basis van de HTTP methode
switch ($requestMethod) {
    case 'GET':
        if ($reservatieId) {
            // Haal één reservering op
            getReservatie($reservatieId);
        } else {
            // Haal alle reserveringen op
            getAllReservaties();
        }
        break;
    case 'POST':
        // Maak een nieuwe reservering
        createReservatie();
        break;
    case 'PUT':
        // Update een bestaande reservering
        if ($reservatieId) {
            updateReservatie($reservatieId);
        } else {
            sendResponse(400, "Reservatie ID is vereist voor update");
        }
        break;
    case 'DELETE':
        // Verwijder een reservering
        if ($reservatieId) {
            deleteReservatie($reservatieId);
        } else {
            sendResponse(400, "Reservatie ID is vereist voor verwijderen");
        }
        break;
    default:
        sendResponse(405, "Methode niet toegestaan");
        break;
}

// Functie om alle reserveringen op te halen
function getAllReservaties() {
    $conn = getConnection();
    
    // Join met gebruiker en printer tabellen om naam en printername te krijgen
    $query = "SELECT r.*, 
              CONCAT(g.Voornaam, ' ', g.Naam) AS GebruikerNaam,
              p.Naam AS PrinterNaam
              FROM Reservatie r
              LEFT JOIN Gebruiker g ON r.User_ID = g.User_ID
              LEFT JOIN Printer p ON r.Printer_ID = p.Printer_ID
              ORDER BY r.Date_Time_res DESC";
    
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $reservaties = [];
        while ($row = $result->fetch_assoc()) {
            $reservaties[] = $row;
        }
        sendResponse(200, "Reserveringen succesvol opgehaald", $reservaties);
    } else {
        sendResponse(200, "Geen reserveringen gevonden", []);
    }
    
    $conn->close();
}

// Functie om recente reserveringen op te halen
function getRecentReservaties() {
    $conn = getConnection();
    $query = "SELECT r.Reservatie_ID, CONCAT(g.Voornaam, ' ', g.Naam) AS GebruikerNaam, 
              p.Naam AS PrinterNaam, r.Pr_Start, r.Pr_End
              FROM Reservatie r
              LEFT JOIN Gebruiker g ON r.User_ID = g.User_ID
              LEFT JOIN Printer p ON r.Printer_ID = p.Printer_ID
              ORDER BY r.Date_Time_res DESC
              LIMIT 10";
    
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $reservaties = [];
        while ($row = $result->fetch_assoc()) {
            $reservaties[] = $row;
        }
        sendResponse(200, "Recente reserveringen succesvol opgehaald", $reservaties);
    } else {
        sendResponse(200, "Geen recente reserveringen gevonden", []);
    }
    
    $conn->close();
}

// Functie om één reservering op te halen
function getReservatie($id) {
    $conn = getConnection();
    $query = "SELECT r.*,
              CONCAT(g.Voornaam, ' ', g.Naam) AS GebruikerNaam,
              p.Naam AS PrinterNaam
              FROM Reservatie r
              LEFT JOIN Gebruiker g ON r.User_ID = g.User_ID
              LEFT JOIN Printer p ON r.Printer_ID = p.Printer_ID
              WHERE r.Reservatie_ID = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $reservatie = $result->fetch_assoc();
        sendResponse(200, "Reservering succesvol opgehaald", $reservatie);
    } else {
        sendResponse(404, "Reservering niet gevonden");
    }
    
    $stmt->close();
    $conn->close();
}

// Functie om een nieuwe reservering aan te maken
function createReservatie() {
    $data = getRequestBody();
    
    // Controleer of alle verplichte velden aanwezig zijn
    $required = ['User_ID', 'Printer_ID', 'Pr_Start', 'Pr_End', 'Pin'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(400, "Veld '$field' is verplicht");
        }
    }
    
    // Valideer PIN (8 cijfers)
    if (!preg_match('/^[0-9]{8}$/', $data['Pin'])) {
        sendResponse(400, "PIN moet exact 8 cijfers bevatten");
    }
    
    $conn = getConnection();
    $currentDateTime = date('Y-m-d H:i:s');
    
    // Controleer of de printer beschikbaar is voor de aangegeven periode
    $overlappingQuery = "SELECT COUNT(*) AS overlap FROM Reservatie 
                        WHERE Printer_ID = ? 
                        AND ((Pr_Start <= ? AND Pr_End >= ?) OR 
                             (Pr_Start <= ? AND Pr_End >= ?) OR
                             (Pr_Start >= ? AND Pr_End <= ?))";
    
    $overlapStmt = $conn->prepare($overlappingQuery);
    $overlapStmt->bind_param(
        "issssss", 
        $data['Printer_ID'], 
        $data['Pr_End'], 
        $data['Pr_Start'],
        $data['Pr_Start'], 
        $data['Pr_Start'],
        $data['Pr_Start'], 
        $data['Pr_End']
    );
    $overlapStmt->execute();
    $overlapResult = $overlapStmt->get_result();
    $overlapRow = $overlapResult->fetch_assoc();
    
    if ($overlapRow['overlap'] > 0) {
        sendResponse(400, "De printer is niet beschikbaar in de aangegeven periode");
    }
    
    $overlapStmt->close();
    
    // Bereid query voor
    $query = "INSERT INTO Reservatie (User_ID, Printer_ID, Date_Time_res, Pr_Start, Pr_End, 
              Comment, Pin, Filament_Kleur, Filament_Type) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "iissssss", 
        $data['User_ID'], 
        $data['Printer_ID'], 
        $currentDateTime, 
        $data['Pr_Start'], 
        $data['Pr_End'], 
        $data['Comment'] ?? null, 
        $data['Pin'], 
        $data['Filament_Kleur'] ?? null, 
        $data['Filament_Type'] ?? null
    );
    
    if ($stmt->execute()) {
        $reservatieId = $stmt->insert_id;
        
        // Update de printer status naar Gereserveerd
        $updatePrinterQuery = "UPDATE Printer SET Status = 'Gereserveerd', 
                              Laatste_Status_Change = ? WHERE Printer_ID = ?";
        $updateStmt = $conn->prepare($updatePrinterQuery);
        $updateStmt->bind_param("si", $currentDateTime, $data['Printer_ID']);
        $updateStmt->execute();
        $updateStmt->close();
        
        sendResponse(201, "Reservering succesvol aangemaakt", ["Reservatie_ID" => $reservatieId]);
    } else {
        sendResponse(500, "Fout bij aanmaken reservering: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
}

// Functie om een reservering bij te werken
function updateReservatie($id) {
    $data = getRequestBody();
    
    if (empty($data)) {
        sendResponse(400, "Geen data ontvangen om bij te werken");
    }
    
    // Valideer PIN als deze is ingesteld
    if (isset($data['Pin']) && !empty($data['Pin'])) {
        if (!preg_match('/^[0-9]{8}$/', $data['Pin'])) {
            sendResponse(400, "PIN moet exact 8 cijfers bevatten");
        }
    }
    
    $conn = getConnection();
    
    // Controleer eerst of de reservering bestaat
    $checkQuery = "SELECT * FROM Reservatie WHERE Reservatie_ID = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        $conn->close();
        sendResponse(404, "Reservering niet gevonden");
    }
    
    $currentReservation = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    // Als Printer_ID, Pr_Start of Pr_End is gewijzigd, controleer beschikbaarheid
    if ((isset($data['Printer_ID']) && $data['Printer_ID'] != $currentReservation['Printer_ID']) ||
        (isset($data['Pr_Start']) && $data['Pr_Start'] != $currentReservation['Pr_Start']) ||
        (isset($data['Pr_End']) && $data['Pr_End'] != $currentReservation['Pr_End'])) {
        
        $printerId = $data['Printer_ID'] ?? $currentReservation['Printer_ID'];
        $startTime = $data['Pr_Start'] ?? $currentReservation['Pr_Start'];
        $endTime = $data['Pr_End'] ?? $currentReservation['Pr_End'];
        
        $overlappingQuery = "SELECT COUNT(*) AS overlap FROM Reservatie 
                            WHERE Printer_ID = ? AND Reservatie_ID != ? AND
                            ((Pr_Start <= ? AND Pr_End >= ?) OR 
                             (Pr_Start <= ? AND Pr_End >= ?) OR
                             (Pr_Start >= ? AND Pr_End <= ?))";
        
        $overlapStmt = $conn->prepare($overlappingQuery);
        $overlapStmt->bind_param(
            "iissssss", 
            $printerId, 
            $id,
            $endTime, 
            $startTime,
            $startTime, 
            $startTime,
            $startTime, 
            $endTime
        );
        $overlapStmt->execute();
        $overlapResult = $overlapStmt->get_result();
        $overlapRow = $overlapResult->fetch_assoc();
        
        if ($overlapRow['overlap'] > 0) {
            $overlapStmt->close();
            $conn->close();
            sendResponse(400, "De printer is niet beschikbaar in de aangegeven periode");
        }
        
        $overlapStmt->close();
    }
    
    // Begin query opbouwen
    $query = "UPDATE Reservatie SET ";
    $types = "";
    $params = [];
    
    // Loop door alle velden en voeg ze toe aan de query
    foreach ($data as $key => $value) {
        // Sla Reservatie_ID over
        if ($key === 'Reservatie_ID') continue;
        
        $query .= "$key = ?, ";
        
        // Bepaal het type voor bind_param
        if (is_int($value)) {
            $types .= "i";
        } elseif (is_double($value)) {
            $types .= "d";
        } else {
            $types .= "s";
        }
        
        $params[] = $value;
    }
    
    // Verwijder laatste komma en spatie
    $query = rtrim($query, ", ");
    
    // Voeg WHERE clause toe
    $query .= " WHERE Reservatie_ID = ?";
    $types .= "i";
    $params[] = $id;
    
    // Bereid statement voor en bind parameters
    $stmt = $conn->prepare($query);
    
    // Dynamisch parameters binden
    if (!empty($params)) {
        $bindParams = array_merge([$types], $params);
        $stmt->bind_param(...$bindParams);
    }
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Als Printer_ID is veranderd, update printer status
            if (isset($data['Printer_ID']) && $data['Printer_ID'] != $currentReservation['Printer_ID']) {
                $currentDateTime = date('Y-m-d H:i:s');
                
                // Reset oude printer status naar Beschikbaar
                $resetOldPrinterQuery = "UPDATE Printer SET Status = 'Beschikbaar', 
                                     Laatste_Status_Change = ? WHERE Printer_ID = ?";
                $resetStmt = $conn->prepare($resetOldPrinterQuery);
                $resetStmt->bind_param("si", $currentDateTime, $currentReservation['Printer_ID']);
                $resetStmt->execute();
                $resetStmt->close();
                
                // Update nieuwe printer status naar Gereserveerd
                $updateNewPrinterQuery = "UPDATE Printer SET Status = 'Gereserveerd', 
                                      Laatste_Status_Change = ? WHERE Printer_ID = ?";
                $updateStmt = $conn->prepare($updateNewPrinterQuery);
                $updateStmt->bind_param("si", $currentDateTime, $data['Printer_ID']);
                $updateStmt->execute();
                $updateStmt->close();
            }
            
            sendResponse(200, "Reservering succesvol bijgewerkt");
        } else {
            sendResponse(200, "Geen wijzigingen aangebracht");
        }
    } else {
        sendResponse(500, "Fout bij bijwerken reservering: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
}

// Functie om een reservering te verwijderen
function deleteReservatie($id) {
    $conn = getConnection();
    
    // Haal eerst de reservering op om te weten welke printer vrijgemaakt moet worden
    $getQuery = "SELECT Printer_ID FROM Reservatie WHERE Reservatie_ID = ?";
    $getStmt = $conn->prepare($getQuery);
    $getStmt->bind_param("i", $id);
    $getStmt->execute();
    $getResult = $getStmt->get_result();
    
    if ($getResult->num_rows === 0) {
        $getStmt->close();
        $conn->close();
        sendResponse(404, "Reservering niet gevonden");
    }
    
    $reservatie = $getResult->fetch_assoc();
    $printerId = $reservatie['Printer_ID'];
    $getStmt->close();
    
    // Verwijder de reservering
    $query = "DELETE FROM Reservatie WHERE Reservatie_ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Update printer status naar Beschikbaar
            $currentDateTime = date('Y-m-d H:i:s');
            $updateQuery = "UPDATE Printer SET Status = 'Beschikbaar', 
                           Laatste_Status_Change = ? WHERE Printer_ID = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("si", $currentDateTime, $printerId);
            $updateStmt->execute();
            $updateStmt->close();
            
            sendResponse(200, "Reservering succesvol verwijderd");
        } else {
            sendResponse(404, "Reservering niet gevonden");
        }
    } else {
        sendResponse(500, "Fout bij verwijderen reservering: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
}

// Als endpoint voor dashboard statistieken wordt aangeroepen
if (isset($_GET['action']) && $_GET['action'] === 'recent') {
    getRecentReservaties();
    exit;
}

// Als endpoint voor actieve reserveringen wordt aangeroepen
if (isset($_GET['action']) && $_GET['action'] === 'active') {
    $conn = getConnection();
    $now = date('Y-m-d H:i:s');
    
    $query = "SELECT COUNT(*) as count FROM Reservatie WHERE Pr_Start <= ? AND Pr_End >= ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $now, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    sendResponse(200, "Actieve reserveringen geteld", ["count" => $row['count']]);
    
    $stmt->close();
    $conn->close();
    exit;
}
?>