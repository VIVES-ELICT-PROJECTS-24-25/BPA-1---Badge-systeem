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
            getAllFilaments();
        } elseif (preg_match('/^(\d+)$/', $route, $matches)) {
            getFilament($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'POST':
        ensureAdmin();
        createFilament();
        break;
    case 'PUT':
        if (preg_match('/^(\d+)$/', $route, $matches)) {
            ensureAdmin();
            updateFilament($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'DELETE':
        if (preg_match('/^(\d+)$/', $route, $matches)) {
            ensureAdmin();
            deleteFilament($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    default:
        sendResponse(["error" => "Methode niet toegestaan"], 405);
}

// Alle filamenten ophalen
function getAllFilaments() {
    global $conn;
    
    $stmt = $conn->query("SELECT id, Type, Kleur FROM Filament ORDER BY Type, Kleur");
    $filaments = $stmt->fetchAll();
    
    sendResponse($filaments);
}

// Specifiek filament ophalen
function getFilament($filamentId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, Type, Kleur FROM Filament WHERE id = ?");
    $stmt->execute([$filamentId]);
    $filament = $stmt->fetch();
    
    if (!$filament) {
        sendResponse(["error" => "Filament niet gevonden"], 404);
    }
    
    // Compatibele printers toevoegen
    $stmt = $conn->prepare("
        SELECT p.Printer_ID, p.Versie_Toestel, p.Status
        FROM Printer p
        JOIN Filament_compatibiliteit fc ON p.Printer_ID = fc.printer_id
        WHERE fc.filament_id = ?
    ");
    $stmt->execute([$filamentId]);
    $filament['compatibele_printers'] = $stmt->fetchAll();
    
    sendResponse($filament);
}

// Nieuw filament aanmaken
function createFilament() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['Type']) || !isset($data['Kleur'])) {
        sendResponse(["error" => "Verplichte velden ontbreken"], 400);
    }
    
    // Type valideren
    $validTypes = ['PLA', 'ABS', 'PETG', 'TPU', 'Nylon'];
    if (!in_array($data['Type'], $validTypes)) {
        sendResponse(["error" => "Ongeldig filament type"], 400);
    }
    
    // Kleur valideren
    $validKleuren = ['rood', 'blauw', 'groen', 'zwart', 'wit', 'geel', 'transparant'];
    if (!in_array($data['Kleur'], $validKleuren)) {
        sendResponse(["error" => "Ongeldige filament kleur"], 400);
    }
    
    // Controleren of combinatie al bestaat
    $stmt = $conn->prepare("SELECT id FROM Filament WHERE Type = ? AND Kleur = ?");
    $stmt->execute([$data['Type'], $data['Kleur']]);
    if ($stmt->rowCount() > 0) {
        sendResponse(["error" => "Filament met deze type en kleur combinatie bestaat al"], 409);
    }
    
    // Volgende id bepalen
    $stmt = $conn->query("SELECT MAX(id) as maxId FROM Filament");
    $result = $stmt->fetch();
    $newId = ($result['maxId'] ?? 0) + 1;
    
    // Filament aanmaken
    $stmt = $conn->prepare("INSERT INTO Filament (id, Type, Kleur) VALUES (?, ?, ?)");
    $success = $stmt->execute([$newId, $data['Type'], $data['Kleur']]);
    
    if ($success) {
        // Printer compatibiliteit toevoegen indien gespecificeerd
        if (isset($data['printer_ids']) && is_array($data['printer_ids'])) {
            foreach ($data['printer_ids'] as $printerId) {
                $stmt = $conn->prepare("
                    INSERT INTO Filament_compatibiliteit (printer_id, filament_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$printerId, $newId]);
            }
        }
        
        sendResponse(["message" => "Filament succesvol aangemaakt", "id" => $newId], 201);
    } else {
        sendResponse(["error" => "Filament aanmaken mislukt"], 500);
    }
}

// Filament bijwerken
function updateFilament($filamentId) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Controleren of filament bestaat
    $stmt = $conn->prepare("SELECT id FROM Filament WHERE id = ?");
    $stmt->execute([$filamentId]);
    if ($stmt->rowCount() === 0) {
        sendResponse(["error" => "Filament niet gevonden"], 404);
    }
    
    // Update velden opbouwen
    $updateFields = [];
    $params = [];
    
    if (isset($data['Type'])) {
        // Type valideren
        $validTypes = ['PLA', 'ABS', 'PETG', 'TPU', 'Nylon'];
        if (!in_array($data['Type'], $validTypes)) {
            sendResponse(["error" => "Ongeldig filament type"], 400);
        }
        
        $updateFields[] = "Type = ?";
        $params[] = $data['Type'];
    }
    
    if (isset($data['Kleur'])) {
        // Kleur valideren
        $validKleuren = ['rood', 'blauw', 'groen', 'zwart', 'wit', 'geel', 'transparant'];
        if (!in_array($data['Kleur'], $validKleuren)) {
            sendResponse(["error" => "Ongeldige filament kleur"], 400);
        }
        
        $updateFields[] = "Kleur = ?";
        $params[] = $data['Kleur'];
    }
    
    if (empty($updateFields)) {
        sendResponse(["error" => "Geen velden om bij te werken"], 400);
    }
    
    // Controleren of nieuwe type/kleur combinatie al bestaat
    if (isset($data['Type']) && isset($data['Kleur'])) {
        $stmt = $conn->prepare("SELECT id FROM Filament WHERE Type = ? AND Kleur = ? AND id != ?");
        $stmt->execute([$data['Type'], $data['Kleur'], $filamentId]);
        if ($stmt->rowCount() > 0) {
            sendResponse(["error" => "Filament met deze type en kleur combinatie bestaat al"], 409);
        }
    }
    
    // id toevoegen aan params
    $params[] = $filamentId;
    
    // Filament bijwerken
    $stmt = $conn->prepare("UPDATE Filament SET " . implode(", ", $updateFields) . " WHERE id = ?");
    $success = $stmt->execute($params);
    
    if ($success) {
        // Printer compatibiliteit bijwerken indien gespecificeerd
        if (isset($data['printer_ids']) && is_array($data['printer_ids'])) {
            // Huidige compatibiliteiten verwijderen
            $stmt = $conn->prepare("DELETE FROM Filament_compatibiliteit WHERE filament_id = ?");
            $stmt->execute([$filamentId]);
            
            // Nieuwe compatibiliteiten toevoegen
            foreach ($data['printer_ids'] as $printerId) {
                $stmt = $conn->prepare("
                    INSERT INTO Filament_compatibiliteit (printer_id, filament_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$printerId, $filamentId]);
            }
        }
        
        sendResponse(["message" => "Filament succesvol bijgewerkt"]);
    } else {
        sendResponse(["error" => "Filament bijwerken mislukt"], 500);
    }
}

// Filament verwijderen
function deleteFilament($filamentId) {
    global $conn;
    
    // Controleren of filament bestaat
    $stmt = $conn->prepare("SELECT id FROM Filament WHERE id = ?");
    $stmt->execute([$filamentId]);
    if ($stmt->rowCount() === 0) {
        sendResponse(["error" => "Filament niet gevonden"], 404);
    }
    
    // Controleren of filament in gebruik is bij reserveringen
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Reservatie WHERE filament_id = ?");
    $stmt->execute([$filamentId]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        sendResponse(["error" => "Kan filament niet verwijderen: het wordt gebruikt in reserveringen"], 409);
    }
    
    // Compatibiliteiten verwijderen
    $stmt = $conn->prepare("DELETE FROM Filament_compatibiliteit WHERE filament_id = ?");
    $stmt->execute([$filamentId]);
    
    // Filament verwijderen
    $stmt = $conn->prepare("DELETE FROM Filament WHERE id = ?");
    $success = $stmt->execute([$filamentId]);
    
    if ($success) {
        sendResponse(["message" => "Filament succesvol verwijderd"]);
    } else {
        sendResponse(["error" => "Filament verwijderen mislukt"], 500);
    }
}
?>