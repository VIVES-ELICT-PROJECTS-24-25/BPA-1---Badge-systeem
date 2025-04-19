<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

// HTTP methode en route ophalen
$method = $_SERVER['REQUEST_METHOD'];
$route = isset($_GET['route']) ? $_GET['route'] : '';

// Router
switch ($method) {
    case 'GET':
        if ($route == 'all') {
            getAllLokalen();
        } elseif (preg_match('/^(\d+)$/', $route, $matches)) {
            getLokaal($matches[1]);
        } elseif ($route == 'openingsuren') {
            getOpeningsuren();
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'POST':
        ensureAdmin();
        if ($route == 'lokaal') {
            createLokaal();
        } elseif ($route == 'openingsuren') {
            createOpeningsuren();
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'PUT':
        ensureAdmin();
        if (preg_match('/^lokaal\/(\d+)$/', $route, $matches)) {
            updateLokaal($matches[1]);
        } elseif (preg_match('/^openingsuren\/(\d+)$/', $route, $matches)) {
            updateOpeningsuren($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'DELETE':
        ensureAdmin();
        if (preg_match('/^lokaal\/(\d+)$/', $route, $matches)) {
            deleteLokaal($matches[1]);
        } elseif (preg_match('/^openingsuren\/(\d+)$/', $route, $matches)) {
            deleteOpeningsuren($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    default:
        sendResponse(["error" => "Methode niet toegestaan"], 405);
}

// Alle lokalen ophalen
function getAllLokalen() {
    global $conn;
    
    $stmt = $conn->query("SELECT id, Locatie FROM Lokalen ORDER BY Locatie");
    $lokalen = $stmt->fetchAll();
    
    sendResponse($lokalen);
}

// Specifiek lokaal ophalen met openingsuren
function getLokaal($lokaalId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, Locatie FROM Lokalen WHERE id = ?");
    $stmt->execute([$lokaalId]);
    $lokaal = $stmt->fetch();
    
    if (!$lokaal) {
        sendResponse(["error" => "Lokaal niet gevonden"], 404);
    }
    
    // Openingsuren ophalen voor dit lokaal
    $stmt = $conn->prepare("
        SELECT id, Tijdstip_start, Tijdstip_einde
        FROM Openingsuren
        WHERE Lokaal_id = ?
        ORDER BY Tijdstip_start
    ");
    
    $stmt->execute([$lokaalId]);
    $lokaal['openingsuren'] = $stmt->fetchAll();
    
    sendResponse($lokaal);
}

// Alle openingsuren ophalen
function getOpeningsuren() {
    global $conn;
    
    $stmt = $conn->query("
        SELECT o.id, o.Lokaal_id, o.Tijdstip_start, o.Tijdstip_einde, l.Locatie
        FROM Openingsuren o
        JOIN Lokalen l ON o.Lokaal_id = l.id
        ORDER BY l.Locatie, o.Tijdstip_start
    ");
    
    $openingsuren = $stmt->fetchAll();
    
    sendResponse($openingsuren);
}

// Nieuw lokaal aanmaken
function createLokaal() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['Locatie'])) {
        sendResponse(["error" => "Locatie is verplicht"], 400);
    }
    
    // Volgende ID bepalen
    $stmt = $conn->query("SELECT MAX(id) as maxId FROM Lokalen");
    $result = $stmt->fetch();
    $newId = ($result['maxId'] ?? 0) + 1;
    
    // Lokaal aanmaken
    $stmt = $conn->prepare("INSERT INTO Lokalen (id, Locatie) VALUES (?, ?)");
    $success = $stmt->execute([$newId, $data['Locatie']]);
    
    if ($success) {
        sendResponse(["message" => "Lokaal succesvol aangemaakt", "id" => $newId], 201);
    } else {
        sendResponse(["error" => "Lokaal aanmaken mislukt"], 500);
    }
}

// Nieuwe openingsuren aanmaken
function createOpeningsuren() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['Lokaal_id']) || !isset($data['Tijdstip_start']) || !isset($data['Tijdstip_einde'])) {
        sendResponse(["error" => "Lokaal_id, Tijdstip_start en Tijdstip_einde zijn verplicht"], 400);
    }
    
    // Controleren of lokaal bestaat
    $stmt = $conn->prepare("SELECT id FROM Lokalen WHERE id = ?");
    $stmt->execute([$data['Lokaal_id']]);
    
    if ($stmt->rowCount() === 0) {
        sendResponse(["error" => "Lokaal niet gevonden"], 404);
    }
    
    // Tijden valideren
    $startTime = new DateTime($data['Tijdstip_start']);
    $endTime = new DateTime($data['Tijdstip_einde']);
    
    if ($startTime >= $endTime) {
        sendResponse(["error" => "Eindtijd moet na starttijd zijn"], 400);
    }
    
    // Controleren op overlappende openingsuren
    $stmt = $conn->prepare("
        SELECT id FROM Openingsuren 
        WHERE Lokaal_id = ? AND 
              ((Tijdstip_start <= ? AND Tijdstip_einde > ?) OR
               (Tijdstip_start < ? AND Tijdstip_einde >= ?) OR
               (Tijdstip_start >= ? AND Tijdstip_einde <= ?))
    ");
    
    $stmt->execute([
        $data['Lokaal_id'],
        $data['Tijdstip_start'], $data['Tijdstip_start'],
        $data['Tijdstip_einde'], $data['Tijdstip_einde'],
        $data['Tijdstip_start'], $data['Tijdstip_einde']
    ]);
    
    if ($stmt->rowCount() > 0) {
        sendResponse(["error" => "Er zijn overlappende openingsuren voor dit tijdslot"], 409);
    }
    
    // Volgende ID bepalen
    $stmt = $conn->query("SELECT MAX(id) as maxId FROM Openingsuren");
    $result = $stmt->fetch();
    $newId = ($result['maxId'] ?? 0) + 1;
    
    // Openingsuren aanmaken
    $stmt = $conn->prepare("
        INSERT INTO Openingsuren (id, Lokaal_id, Tijdstip_start, Tijdstip_einde)
        VALUES (?, ?, ?, ?)
    ");
    
    $success = $stmt->execute([
        $newId,
        $data['Lokaal_id'],
        $data['Tijdstip_start'],
        $data['Tijdstip_einde']
    ]);
    
    if ($success) {
        sendResponse(["message" => "Openingsuren succesvol aangemaakt", "id" => $newId], 201);
    } else {
        sendResponse(["error" => "Openingsuren aanmaken mislukt"], 500);
    }
}

// Lokaal bijwerken
function updateLokaal($lokaalId) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Controleren of lokaal bestaat
    $stmt = $conn->prepare("SELECT id FROM Lokalen WHERE id = ?");
    $stmt->execute([$lokaalId]);
    
    if ($stmt->rowCount() === 0) {
        sendResponse(["error" => "Lokaal niet gevonden"], 404);
    }
    
    if (!isset($data['Locatie'])) {
        sendResponse(["error" => "Locatie is verplicht"], 400);
    }
    
    // Lokaal bijwerken
    $stmt = $conn->prepare("UPDATE Lokalen SET Locatie = ? WHERE id = ?");
    $success = $stmt->execute([$data['Locatie'], $lokaalId]);
    
    if ($success) {
        sendResponse(["message" => "Lokaal succesvol bijgewerkt"]);
    } else {
        sendResponse(["error" => "Lokaal bijwerken mislukt"], 500);
    }
}

// Openingsuren bijwerken
function updateOpeningsuren($openingsurenId) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Controleren of openingsuren bestaan
    $stmt = $conn->prepare("SELECT id, Lokaal_id FROM Openingsuren WHERE id = ?");
    $stmt->execute([$openingsurenId]);
    $openingsuren = $stmt->fetch();
    
    if (!$openingsuren) {
        sendResponse(["error" => "Openingsuren niet gevonden"], 404);
    }
    
    // Update velden opbouwen
    $updateFields = [];
    $params = [];
    
    $lokaalId = $openingsuren['Lokaal_id'];
    
    if (isset($data['Lokaal_id'])) {
        // Controleren of nieuwe lokaal bestaat
        $stmt = $conn->prepare("SELECT id FROM Lokalen WHERE id = ?");
        $stmt->execute([$data['Lokaal_id']]);
        
        if ($stmt->rowCount() === 0) {
            sendResponse(["error" => "Lokaal niet gevonden"], 404);
        }
        
        $lokaalId = $data['Lokaal_id'];
        $updateFields[] = "Lokaal_id = ?";
        $params[] = $lokaalId;
    }
    
    if (isset($data['Tijdstip_start'])) {
        $updateFields[] = "Tijdstip_start = ?";
        $params[] = $data['Tijdstip_start'];
    }
    
    if (isset($data['Tijdstip_einde'])) {
        $updateFields[] = "Tijdstip_einde = ?";
        $params[] = $data['Tijdstip_einde'];
    }
    
    if (empty($updateFields)) {
        sendResponse(["error" => "Geen velden om bij te werken"], 400);
    }
    
    // Tijden valideren als beide worden bijgewerkt
    if (isset($data['Tijdstip_start']) && isset($data['Tijdstip_einde'])) {
        $startTime = new DateTime($data['Tijdstip_start']);
        $endTime = new DateTime($data['Tijdstip_einde']);
        
        if ($startTime >= $endTime) {
            sendResponse(["error" => "Eindtijd moet na starttijd zijn"], 400);
        }
    }
    
    // Controleren op overlappende openingsuren
    if (isset($data['Tijdstip_start']) || isset($data['Tijdstip_einde']) || isset($data['Lokaal_id'])) {
        // Huidige waarden ophalen indien niet bijgewerkt
        if (!isset($data['Tijdstip_start']) || !isset($data['Tijdstip_einde'])) {
            $stmt = $conn->prepare("SELECT Tijdstip_start, Tijdstip_einde FROM Openingsuren WHERE id = ?");
            $stmt->execute([$openingsurenId]);
            $currentTimes = $stmt->fetch();
            
            $startTime = isset($data['Tijdstip_start']) ? $data['Tijdstip_start'] : $currentTimes['Tijdstip_start'];
            $endTime = isset($data['Tijdstip_einde']) ? $data['Tijdstip_einde'] : $currentTimes['Tijdstip_einde'];
        } else {
            $startTime = $data['Tijdstip_start'];
            $endTime = $data['Tijdstip_einde'];
        }
        
        $stmt = $conn->prepare("
            SELECT id FROM Openingsuren 
            WHERE Lokaal_id = ? AND id != ? AND
                  ((Tijdstip_start <= ? AND Tijdstip_einde > ?) OR
                   (Tijdstip_start < ? AND Tijdstip_einde >= ?) OR
                   (Tijdstip_start >= ? AND Tijdstip_einde <= ?))
        ");
        
        $stmt->execute([
            $lokaalId,
            $openingsurenId,
            $startTime, $startTime,
            $endTime, $endTime,
            $startTime, $endTime
        ]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(["error" => "Er zijn overlappende openingsuren voor dit tijdslot"], 409);
        }
    }
    
    // ID toevoegen aan params
    $params[] = $openingsurenId;
    
    // Openingsuren bijwerken
    $stmt = $conn->prepare("UPDATE Openingsuren SET " . implode(", ", $updateFields) . " WHERE id = ?");
    $success = $stmt->execute($params);
    
    if ($success) {
        sendResponse(["message" => "Openingsuren succesvol bijgewerkt"]);
    } else {
        sendResponse(["error" => "Openingsuren bijwerken mislukt"], 500);
    }
}

// Lokaal verwijderen
function deleteLokaal($lokaalId) {
    global $conn;
    
    // Controleren of lokaal bestaat
    $stmt = $conn->prepare("SELECT id FROM Lokalen WHERE id = ?");
    $stmt->execute([$lokaalId]);
    
    if ($stmt->rowCount() === 0) {
        sendResponse(["error" => "Lokaal niet gevonden"], 404);
    }
    
    // Controleren of er openingsuren aan gekoppeld zijn
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Openingsuren WHERE Lokaal_id = ?");
    $stmt->execute([$lokaalId]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        sendResponse(["error" => "Lokaal kan niet worden verwijderd omdat er openingsuren aan gekoppeld zijn"], 409);
    }
    
    // Lokaal verwijderen
    $stmt = $conn->prepare("DELETE FROM Lokalen WHERE id = ?");
    $success = $stmt->execute([$lokaalId]);
    
    if ($success) {
        sendResponse(["message" => "Lokaal succesvol verwijderd"]);
    } else {
        sendResponse(["error" => "Lokaal verwijderen mislukt"], 500);
    }
}

// Openingsuren verwijderen
function deleteOpeningsuren($openingsurenId) {
    global $conn;
    
    // Controleren of openingsuren bestaan
    $stmt = $conn->prepare("SELECT id FROM Openingsuren WHERE id = ?");
    $stmt->execute([$openingsurenId]);
    
    if ($stmt->rowCount() === 0) {
        sendResponse(["error" => "Openingsuren niet gevonden"], 404);
    }
    
    // Openingsuren verwijderen
    $stmt = $conn->prepare("DELETE FROM Openingsuren WHERE id = ?");
    $success = $stmt->execute([$openingsurenId]);
    
    if ($success) {
        sendResponse(["message" => "Openingsuren succesvol verwijderd"]);
    } else {
        sendResponse(["error" => "Openingsuren verwijderen mislukt"], 500);
    }
}
?>