<?php
// gebruiker_api.php - API endpoints voor gebruikers

require_once 'config.php';

// Set headers voor Cross-Origin Resource Sharing (CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Bepaal de HTTP methode
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Get gebruiker_id from URL if provided
$gebruikerId = null;
if (isset($_GET['id'])) {
    $gebruikerId = intval($_GET['id']);
}

// Verwerk het request op basis van de HTTP methode
switch ($requestMethod) {
    case 'GET':
        if ($gebruikerId) {
            // Haal één gebruiker op
            getGebruiker($gebruikerId);
        } else {
            // Haal alle gebruikers op
            getAllGebruikers();
        }
        break;
    case 'POST':
        // Maak een nieuwe gebruiker
        createGebruiker();
        break;
    case 'PUT':
        // Update een bestaande gebruiker
        if ($gebruikerId) {
            updateGebruiker($gebruikerId);
        } else {
            sendResponse(400, "Gebruiker ID is vereist voor update");
        }
        break;
    case 'DELETE':
        // Verwijder een gebruiker
        if ($gebruikerId) {
            deleteGebruiker($gebruikerId);
        } else {
            sendResponse(400, "Gebruiker ID is vereist voor verwijderen");
        }
        break;
    default:
        sendResponse(405, "Methode niet toegestaan");
        break;
}

// Functie om alle gebruikers op te halen
function getAllGebruikers() {
    $conn = getConnection();
    $query = "SELECT * FROM Gebruiker";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $gebruikers = [];
        while ($row = $result->fetch_assoc()) {
            // Exclude wachtwoord voor veiligheid
            unset($row['WW']);
            $gebruikers[] = $row;
        }
        sendResponse(200, "Gebruikers succesvol opgehaald", $gebruikers);
    } else {
        sendResponse(200, "Geen gebruikers gevonden", []);
    }
    
    $conn->close();
}

// Functie om één gebruiker op te halen
function getGebruiker($id) {
    $conn = getConnection();
    $query = "SELECT * FROM Gebruiker WHERE User_ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $gebruiker = $result->fetch_assoc();
        // Exclude wachtwoord voor veiligheid
        unset($gebruiker['WW']);
        sendResponse(200, "Gebruiker succesvol opgehaald", $gebruiker);
    } else {
        sendResponse(404, "Gebruiker niet gevonden");
    }
    
    $stmt->close();
    $conn->close();
}

// Functie om een nieuwe gebruiker aan te maken
function createGebruiker() {
    $data = getRequestBody();
    
    // Controleer of alle verplichte velden aanwezig zijn
    $required = ['Voornaam', 'Naam', 'Email', 'WW', 'rol'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(400, "Veld '$field' is verplicht");
        }
    }
    
    $conn = getConnection();
    
    // Hash het wachtwoord
    $hashedPassword = password_hash($data['WW'], PASSWORD_DEFAULT);
    $currentDate = date('Y-m-d');
    
    // Bereid query voor
    $query = "INSERT INTO Gebruiker (Vives_Info_ID, Voornaam, Naam, Email, Telnr, WW, rol, Aanmaak_Acc) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "isssssss", 
        $data['Vives_Info_ID'], 
        $data['Voornaam'], 
        $data['Naam'], 
        $data['Email'], 
        $data['Telnr'], 
        $hashedPassword, 
        $data['rol'], 
        $currentDate
    );
    
    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        sendResponse(201, "Gebruiker succesvol aangemaakt", ["User_ID" => $userId]);
    } else {
        sendResponse(500, "Fout bij aanmaken gebruiker: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
}

// Functie om een gebruiker bij te werken
function updateGebruiker($id) {
    $data = getRequestBody();
    
    if (empty($data)) {
        sendResponse(400, "Geen data ontvangen om bij te werken");
    }
    
    $conn = getConnection();
    
    // Begin query opbouwen
    $query = "UPDATE Gebruiker SET ";
    $types = "";
    $params = [];
    
    // Loop door alle velden en voeg ze toe aan de query
    foreach ($data as $key => $value) {
        // Sla User_ID over
        if ($key === 'User_ID') continue;
        
        // Speciale behandeling voor wachtwoord
        if ($key === 'WW' && !empty($value)) {
            $value = password_hash($value, PASSWORD_DEFAULT);
        }
        
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
    $query .= " WHERE User_ID = ?";
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
            sendResponse(200, "Gebruiker succesvol bijgewerkt");
        } else {
            sendResponse(200, "Geen wijzigingen aangebracht of gebruiker niet gevonden");
        }
    } else {
        sendResponse(500, "Fout bij bijwerken gebruiker: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
}

// Functie om een gebruiker te verwijderen
function deleteGebruiker($id) {
    $conn = getConnection();
    
    // Eerst controleren of er reserveringen zijn voor deze gebruiker
    $checkQuery = "SELECT COUNT(*) as count FROM Reservatie WHERE User_ID = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        sendResponse(400, "Kan gebruiker niet verwijderen omdat er nog reserveringen aan gekoppeld zijn");
    }
    
    $checkStmt->close();
    
    // Verwijder de gebruiker
    $query = "DELETE FROM Gebruiker WHERE User_ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            sendResponse(200, "Gebruiker succesvol verwijderd");
        } else {
            sendResponse(404, "Gebruiker niet gevonden");
        }
    } else {
        sendResponse(500, "Fout bij verwijderen gebruiker: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
}
?>