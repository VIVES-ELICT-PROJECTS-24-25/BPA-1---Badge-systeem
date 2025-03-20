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
            // Check voor filter op gebruiker of printer
            if (isset($_GET['user_id'])) {
                getReservatiesByUser(intval($_GET['user_id']));
            } elseif (isset($_GET['printer_id'])) {
                getReservatiesByPrinter(intval($_GET['printer_id']));
            } else {
                // Haal alle reserveringen op
                getAllReservaties();
            }
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
    
    // Join met gebruiker en printer om extra informatie te hebben
    $query = "SELECT r.*, g.Voornaam, g.Naam, p.Naam as Printer_Naam 
              FROM Reservatie r
              JOIN Gebruiker g ON r.User_ID = g.User_ID
              JOIN Printer p ON r.Printer_ID = p.Printer_ID
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

// Functie om één reservering op te halen
function getReservatie($id) {
    $conn = getConnection();
    
    // Join met gebruiker en printer om extra informatie te hebben
    $query = "SELECT r.*, g.Voornaam, g.Naam, p.Naam as Printer_Naam 
              FROM Reservatie r
              JOIN Gebruiker g ON r.User_ID = g.User_ID
              JOIN Printer p ON r.Printer_ID = p.Printer_ID
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

// Functie om reserveringen van een gebruiker op te halen
function getReservatiesByUser($userId) {
    $conn = getConnection();
    
    // Join met printer om printernaam te hebben
    $query = "SELECT r.*, p.Naam as Printer_Naam 
              FROM Reservatie r
              JOIN Printer p ON r.Printer_ID = p.Printer_ID
              WHERE r.User_ID = ?
              ORDER BY r.Date_Time_res DESC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $reservaties = [];
        while ($row = $result->fetch_assoc()) {
            $reservaties[] = $row;
        }
        sendResponse(200, "Reserveringen succesvol opgehaald", $reservaties);
    } else {
        sendResponse(200, "Geen reserveringen gevonden voor deze gebruiker", []);
    }
    
    $stmt->close();
    $conn->close();
}

// Functie om reserveringen voor een printer op te halen
function getReservatiesByPrinter($printerId) {
    $conn = getConnection();
    
    // Join met gebruiker om gebruikersnaam te hebben
    $query = "SELECT r.*, g.Voornaam, g.Naam 
              FROM Reservatie r
              JOIN Gebruiker g ON r.User_ID = g.User_ID
              WHERE r.Printer_ID = ?
              ORDER BY r.Date_Time_res DESC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $printerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $reservaties = [];
        while ($row = $result->fetch_assoc()) {
            $reservaties[] = $row;
        }
        sendResponse(200, "Reserveringen succesvol opgehaald", $reservaties);
    } else {
        sendResponse(200, "Geen reserveringen gevonden voor deze printer", []);
    }
    
    $stmt->close();
    $conn->close();
}

// Functie om een nieuwe reservering aan te maken
function createReservatie() {
    $data = getRequestBody();
    
    // Controleer of alle verplichte velden aanwezig zijn
    $required = ['User_ID', 'Printer_ID', 'Pr_Start', 'Pr_End'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(400, "Veld '$field' is verplicht");
        }
    }
    
    $conn = getConnection();
    
    // Controleer of de printer beschikbaar is voor de opgegeven tijdsperiode
    $checkQuery = "SELECT COUNT(*) as count FROM Reservatie 
                  WHERE Printer_ID = ? AND 
                  ((Pr_Start <= ? AND Pr_End >= ?) OR 
                   (Pr_Start <= ? AND Pr_End >= ?) OR
                   (Pr_Start >= ? AND Pr_End <= ?))";
                   
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param(
        "issssss", 
        $data['Printer_ID'], 
        $data['Pr_End'], 
        $data['Pr_Start'],
        $data['Pr_Start'], 
        $data['Pr_Start'],
        $data['Pr_Start'], 
        $data['Pr_End']
    );
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        sendResponse(400, "De printer is niet beschikbaar in de opgegeven tijdsperiode");
    }
    
    $checkStmt->close();
    
    // Genereer 8-cijferige PIN
    $pin = sprintf("%08d", mt_rand(0, 99999999));
    
    // Bereid query voor
    $query = "INSERT INTO Reservatie (User_ID, Printer_ID, Date_Time_res, Pr_Start, Pr_End, Comment, Pin, Filament_Kleur, Filament_Type) 
              VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "iissssss", 
        $data['User_ID'], 
        $data['Printer_ID'], 
        $data['Pr_Start'], 
        $data['Pr_End'], 
        $data['Comment'] ?? null, 
        $pin,
        $data['Filament_Kleur'] ?? null, 
        $data['Filament_Type'] ?? null
    );
    
    if ($stmt->execute()) {
        $reservatieId = $stmt->insert_id;
        sendResponse(201, "Reservering succesvol aangemaakt", ["Reservatie_ID" => $reservatieId, "Pin" => $pin]);
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
    
    $conn = getConnection();
    
    // Als Pr_Start of Pr_End worden bijgewerkt, controleer beschikbaarheid
    if (isset($data['Pr_Start']) || isset($data['Pr_End'])) {
        // Haal huidige reserveringsgegevens op
        $currentQuery = "SELECT Printer_ID, Pr_Start, Pr_End FROM Reservatie WHERE Reservatie_ID = ?";
        $currentStmt = $conn->prepare($currentQuery);
        $currentStmt->bind_param("i", $id);
        $currentStmt->execute();
        $currentResult = $currentStmt->get_result();
        
        if ($currentResult->num_rows === 0) {
            sendResponse(404, "Reservering niet gevonden");
        }
        
        $current = $currentResult->fetch_assoc();
        $currentStmt->close();
        
        // Gebruik huidige waarden als fallback
        $printerId = $data['Printer_ID'] ?? $current['Printer_ID'];
        $prStart = $data['Pr_Start'] ?? $current['Pr_Start'];
        $prEnd = $data['Pr_End'] ?? $current['Pr_End'];
        
        // Controleer of de printer beschikbaar is voor de opgegeven tijdsperiode
        $checkQuery = "SELECT COUNT(*) as count FROM Reservatie 
                      WHERE Printer_ID = ? AND Reservatie_ID != ? AND 
                      ((Pr_Start <= ? AND Pr_End >= ?) OR 
                       (Pr_Start <= ? AND Pr_End >= ?) OR
                       (Pr_Start >= ? AND Pr_End <= ?))";
                   
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param(
            "iissssss", 
            $printerId, 
            $id,
            $prEnd, 
            $prStart,
            $prStart, 
            $prStart,
            $prStart, 
            $prEnd
        );
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            sendResponse(400, "De printer is niet beschikbaar in de opgegeven tijdsperiode");
        }
        
        $checkStmt->close();
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
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
    }
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            sendResponse(200, "Reservering succesvol bijgewerkt");
        } else {
            sendResponse(200, "Geen wijzigingen aangebracht of reservering niet gevonden");
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
    
    // Controleer of de reservering bestaat
    $checkQuery = "SELECT Reservatie_ID FROM Reservatie WHERE Reservatie_ID = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(404, "Reservering niet gevonden");
    }
    
    $checkStmt->close();
    
    // Bereid query voor om de reservering te verwijderen
    $query = "DELETE FROM Reservatie WHERE Reservatie_ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
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
?>