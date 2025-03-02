<?php
// printer_api.php - API endpoints voor printers

require_once 'config.php';

// Set headers voor Cross-Origin Resource Sharing (CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Bepaal de HTTP methode
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Get printer_id from URL if provided
$printerId = null;
if (isset($_GET['id'])) {
    $printerId = intval($_GET['id']);
}

// Verwerk het request op basis van de HTTP methode
switch ($requestMethod) {
    case 'GET':
        if ($printerId) {
            // Haal één printer op
            getPrinter($printerId);
        } else {
            // Haal alle printers op
            getAllPrinters();
        }
        break;
    case 'POST':
        // Maak een nieuwe printer
        createPrinter();
        break;
    case 'PUT':
        // Update een bestaande printer
        if ($printerId) {
            updatePrinter($printerId);
        } else {
            sendResponse(400, "Printer ID is vereist voor update");
        }
        break;
    case 'DELETE':
        // Verwijder een printer
        if ($printerId) {
            deletePrinter($printerId);
        } else {
            sendResponse(400, "Printer ID is vereist voor verwijderen");
        }
        break;
    default:
        sendResponse(405, "Methode niet toegestaan");
        break;
}

// Functie om alle printers op te halen
function getAllPrinters() {
    $conn = getConnection();
    $query = "SELECT * FROM Printer";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $printers = [];
        while ($row = $result->fetch_assoc()) {
            $printers[] = $row;
        }
        sendResponse(200, "Printers succesvol opgehaald", $printers);
    } else {
        sendResponse(200, "Geen printers gevonden", []);
    }
    
    $conn->close();
}

// Functie om één printer op te halen
function getPrinter($id) {
    $conn = getConnection();
    $query = "SELECT * FROM Printer WHERE Printer_ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $printer = $result->fetch_assoc();
        sendResponse(200, "Printer succesvol opgehaald", $printer);
    } else {
        sendResponse(404, "Printer niet gevonden");
    }
    
    $stmt->close();
    $conn->close();
}

// Functie om een nieuwe printer aan te maken
function createPrinter() {
    $data = getRequestBody();
    
    // Controleer of alle verplichte velden aanwezig zijn
    $required = ['Status', 'Naam'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(400, "Veld '$field' is verplicht");
        }
    }
    
    $conn = getConnection();
    $currentDateTime = date('Y-m-d H:i:s');
    
    // Bereid query voor
    $query = "INSERT INTO Printer (Status, Naam, Laatste_Status_Change, Info) 
              VALUES (?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "ssss", 
        $data['Status'], 
        $data['Naam'], 
        $currentDateTime, 
        $data['Info'] ?? null
    );
    
    if ($stmt->execute()) {
        $printerId = $stmt->insert_id;
        sendResponse(201, "Printer succesvol aangemaakt", ["Printer_ID" => $printerId]);
    } else {
        sendResponse(500, "Fout bij aanmaken printer: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
}

// Functie om een printer bij te werken
function updatePrinter($id) {
    $data = getRequestBody();
    
    if (empty($data)) {
        sendResponse(400, "Geen data ontvangen om bij te werken");
    }
    
    $conn = getConnection();
    
    // Als de status wordt bijgewerkt, update ook de laatste status change timestamp
    if (isset($data['Status'])) {
        $data['Laatste_Status_Change'] = date('Y-m-d H:i:s');
    }
    
    // Begin query opbouwen
    $query = "UPDATE Printer SET ";
    $types = "";
    $params = [];
    
    // Loop door alle velden en voeg ze toe aan de query
    foreach ($data as $key => $value) {
        // Sla Printer_ID over
        if ($key === 'Printer_ID') continue;
        
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
    $query .= " WHERE Printer_ID = ?";
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
            sendResponse(200, "Printer succesvol bijgewerkt");
        } else {
            sendResponse(200, "Geen wijzigingen aangebracht of printer niet gevonden");
        }
    } else {
        sendResponse(500, "Fout bij bijwerken printer: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
}

// Functie om een printer te verwijderen
function deletePrinter($id) {
    $conn = getConnection();
    
    // Eerst controleren of er reserveringen zijn voor deze printer
    $checkQuery = "SELECT COUNT(*) as count FROM Reservatie WHERE Printer_ID = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        sendResponse(400, "Kan printer niet verwijderen omdat er nog reserveringen aan gekoppeld zijn");
    }
    
    $checkStmt->close();
    
    // Verwijder de printer
    $query = "DELETE FROM Printer WHERE Printer_ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            sendResponse(200, "Printer succesvol verwijderd");
        } else {
            sendResponse(404, "Printer niet gevonden");
        }
    } else {
        sendResponse(500, "Fout bij verwijderen printer: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
}
?>