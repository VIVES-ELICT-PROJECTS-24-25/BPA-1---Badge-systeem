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
            getAllPrinters();
        } elseif (preg_match('/^(\d+)$/', $route, $matches)) {
            getPrinter($matches[1]);
        } elseif ($route == 'available') {
            getAvailablePrinters();
        } elseif ($route == 'filaments') {
            getFilamentTypes();
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'POST':
        ensureAdmin();
        createPrinter();
        break;
    case 'PUT':
        if (preg_match('/^(\d+)$/', $route, $matches)) {
            ensureAdmin();
            updatePrinter($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'DELETE':
        if (preg_match('/^(\d+)$/', $route, $matches)) {
            ensureAdmin();
            deletePrinter($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    default:
        sendResponse(["error" => "Methode niet toegestaan"], 405);
}

// Alle printers ophalen
function getAllPrinters() {
    global $conn;
    
    $stmt = $conn->query("
        SELECT p.Printer_ID, p.Status, p.LAATSTE_STATUS_CHANGE, p.netwerkadres, 
               p.Versie_Toestel, p.Software, p.Datadrager, p.Opmerkingen,
               b.lengte, b.breedte, b.hoogte
        FROM Printer p
        LEFT JOIN bouwvolume b ON p.Bouwvolume_id = b.id
        ORDER BY p.Printer_ID
    ");
    $printers = $stmt->fetchAll();
    
    // Filament informatie toevoegen aan elke printer
    foreach ($printers as &$printer) {
        $stmt = $conn->prepare("
            SELECT f.id, f.Type, f.Kleur
            FROM Filament f
            JOIN Filament_compatibiliteit fc ON f.id = fc.filament_id
            WHERE fc.printer_id = ?
        ");
        $stmt->execute([$printer['Printer_ID']]);
        $printer['filaments'] = $stmt->fetchAll();
    }
    
    sendResponse($printers);
}

// Beschikbare printers ophalen
function getAvailablePrinters() {
    global $conn;
    
    $stmt = $conn->query("
        SELECT p.Printer_ID, p.Status, p.Versie_Toestel, p.Software, p.Datadrager,
               b.lengte, b.breedte, b.hoogte
        FROM Printer p
        LEFT JOIN bouwvolume b ON p.Bouwvolume_id = b.id
        WHERE p.Status = 'beschikbaar'
        ORDER BY p.Printer_ID
    ");
    $printers = $stmt->fetchAll();
    
    // Filament informatie toevoegen aan elke printer
    foreach ($printers as &$printer) {
        $stmt = $conn->prepare("
            SELECT f.id, f.Type, f.Kleur
            FROM Filament f
            JOIN Filament_compatibiliteit fc ON f.id = fc.filament_id
            WHERE fc.printer_id = ?
        ");
        $stmt->execute([$printer['Printer_ID']]);
        $printer['filaments'] = $stmt->fetchAll();
    }
    
    sendResponse($printers);
}

// Eén printer ophalen
function getPrinter($printerId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT p.Printer_ID, p.Status, p.LAATSTE_STATUS_CHANGE, p.netwerkadres, 
               p.Versie_Toestel, p.Software, p.Datadrager, p.Opmerkingen,
               b.id as Bouwvolume_id, b.lengte, b.breedte, b.hoogte
        FROM Printer p
        LEFT JOIN bouwvolume b ON p.Bouwvolume_id = b.id
        WHERE p.Printer_ID = ?
    ");
    $stmt->execute([$printerId]);
    $printer = $stmt->fetch();
    
    if (!$printer) {
        sendResponse(["error" => "Printer niet gevonden"], 404);
    }
    
    // Filament informatie toevoegen
    $stmt = $conn->prepare("
        SELECT f.id, f.Type, f.Kleur
        FROM Filament f
        JOIN Filament_compatibiliteit fc ON f.id = fc.filament_id
        WHERE fc.printer_id = ?
    ");
    $stmt->execute([$printerId]);
    $printer['filaments'] = $stmt->fetchAll();
    
    // Lokaal informatie toevoegen (indien aanwezig in database schema)
    // Deze relatie was niet duidelijk in de SQL schema, maar kan toegevoegd worden
    
    sendResponse($printer);
}

// Filament types ophalen
function getFilamentTypes() {
    global $conn;
    
    $stmt = $conn->query("SELECT id, Type, Kleur FROM Filament ORDER BY Type, Kleur");
    $filaments = $stmt->fetchAll();
    
    sendResponse($filaments);
}

// Nieuwe printer aanmaken
function createPrinter() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['Status']) || !isset($data['Versie_Toestel'])) {
        sendResponse(["error" => "Verplichte velden ontbreken"], 400);
    }
    
    // Status valideren
    $validStatuses = ['beschikbaar', 'in_gebruik', 'onderhoud', 'defect'];
    if (!in_array($data['Status'], $validStatuses)) {
        sendResponse(["error" => "Ongeldige status"], 400);
    }
    
    // Software valideren
    $validSoftware = ['versie1', 'versie2', 'versie3'];
    if (isset($data['Software']) && !in_array($data['Software'], $validSoftware)) {
        sendResponse(["error" => "Ongeldige software versie"], 400);
    }
    
    // Datadrager valideren
    $validDatadrager = ['SD', 'USB', 'WIFI'];
    if (isset($data['Datadrager']) && !in_array($data['Datadrager'], $validDatadrager)) {
        sendResponse(["error" => "Ongeldige datadrager"], 400);
    }
    
    // Volgende Printer_ID bepalen
    $stmt = $conn->query("SELECT MAX(Printer_ID) as maxId FROM Printer");
    $result = $stmt->fetch();
    $newPrinterId = ($result['maxId'] ?? 0) + 1;
    
    // Bouwvolume aanmaken of ophalen indien gespecificeerd
    $bouwvolumeId = null;
    if (isset($data['lengte']) && isset($data['breedte']) && isset($data['hoogte'])) {
        // Controleren of bouwvolume al bestaat
        $stmt = $conn->prepare("
            SELECT id FROM bouwvolume 
            WHERE lengte = ? AND breedte = ? AND hoogte = ?
        ");
        $stmt->execute([$data['lengte'], $data['breedte'], $data['hoogte']]);
        $existingVolume = $stmt->fetch();
        
        if ($existingVolume) {
            $bouwvolumeId = $existingVolume['id'];
        } else {
            // Volgende bouwvolume id bepalen
            $stmt = $conn->query("SELECT MAX(id) as maxId FROM bouwvolume");
            $result = $stmt->fetch();
            $newBouwvolumeId = ($result['maxId'] ?? 0) + 1;
            
            // Nieuw bouwvolume aanmaken
            $stmt = $conn->prepare("INSERT INTO bouwvolume (id, lengte, breedte, hoogte) VALUES (?, ?, ?, ?)");
            $stmt->execute([$newBouwvolumeId, $data['lengte'], $data['breedte'], $data['hoogte']]);
            
            $bouwvolumeId = $newBouwvolumeId;
        }
    }
    
    // Printer aanmaken
    $stmt = $conn->prepare("
        INSERT INTO Printer (Printer_ID, Status, LAATSTE_STATUS_CHANGE, netwerkadres, 
                            Versie_Toestel, Software, Datadrager, Bouwvolume_id, Opmerkingen)
        VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)
    ");
    
    $success = $stmt->execute([
        $newPrinterId,
        $data['Status'],
        $data['netwerkadres'] ?? null,
        $data['Versie_Toestel'],
        $data['Software'] ?? null,
        $data['Datadrager'] ?? null,
        $bouwvolumeId,
        $data['Opmerkingen'] ?? null
    ]);
    
    if ($success) {
        // Filament compatibiliteit toevoegen indien gespecificeerd
        if (isset($data['filament_ids']) && is_array($data['filament_ids'])) {
            foreach ($data['filament_ids'] as $filamentId) {
                $stmt = $conn->prepare("
                    INSERT INTO Filament_compatibiliteit (printer_id, filament_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$newPrinterId, $filamentId]);
            }
        }
        
        sendResponse(["message" => "Printer succesvol aangemaakt", "Printer_ID" => $newPrinterId], 201);
    } else {
        sendResponse(["error" => "Printer aanmaken mislukt"], 500);
    }
}

// Printer bijwerken
function updatePrinter($printerId) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Controleren of printer bestaat
    $stmt = $conn->prepare("SELECT Printer_ID FROM Printer WHERE Printer_ID = ?");
    $stmt->execute([$printerId]);
    if ($stmt->rowCount() === 0) {
        sendResponse(["error" => "Printer niet gevonden"], 404);
    }
    
    // Update velden opbouwen
    $updateFields = [];
    $params = [];
    
    if (isset($data['Status'])) {
        $validStatuses = ['beschikbaar', 'in_gebruik', 'onderhoud', 'defect'];
        if (!in_array($data['Status'], $validStatuses)) {
            sendResponse(["error" => "Ongeldige status"], 400);
        }
        
        $updateFields[] = "Status = ?";
        $params[] = $data['Status'];
        $updateFields[] = "LAATSTE_STATUS_CHANGE = NOW()";
    }
    
    if (isset($data['netwerkadres'])) {
        $updateFields[] = "netwerkadres = ?";
        $params[] = $data['netwerkadres'];
    }
    
    if (isset($data['Versie_Toestel'])) {
        $updateFields[] = "Versie_Toestel = ?";
        $params[] = $data['Versie_Toestel'];
    }
    
    if (isset($data['Software'])) {
        $validSoftware = ['versie1', 'versie2', 'versie3'];
        if (!in_array($data['Software'], $validSoftware)) {
            sendResponse(["error" => "Ongeldige software versie"], 400);
        }
        
        $updateFields[] = "Software = ?";
        $params[] = $data['Software'];
    }
    
    if (isset($data['Datadrager'])) {
        $validDatadrager = ['SD', 'USB', 'WIFI'];
        if (!in_array($data['Datadrager'], $validDatadrager)) {
            sendResponse(["error" => "Ongeldige datadrager"], 400);
        }
        
        $updateFields[] = "Datadrager = ?";
        $params[] = $data['Datadrager'];
    }
    
    if (isset($data['Opmerkingen'])) {
        $updateFields[] = "Opmerkingen = ?";
        $params[] = $data['Opmerkingen'];
    }
    
    // Bouwvolume bijwerken indien gespecificeerd
    $bouwvolumeId = null;
    if (isset($data['lengte']) && isset($data['breedte']) && isset($data['hoogte'])) {
        // Controleren of bouwvolume al bestaat
        $stmt = $conn->prepare("
            SELECT id FROM bouwvolume 
            WHERE lengte = ? AND breedte = ? AND hoogte = ?
        ");
        $stmt->execute([$data['lengte'], $data['breedte'], $data['hoogte']]);
        $existingVolume = $stmt->fetch();
        
        if ($existingVolume) {
            $bouwvolumeId = $existingVolume['id'];
        } else {
            // Volgende bouwvolume id bepalen
            $stmt = $conn->query("SELECT MAX(id) as maxId FROM bouwvolume");
            $result = $stmt->fetch();
            $newBouwvolumeId = ($result['maxId'] ?? 0) + 1;
            
            // Nieuw bouwvolume aanmaken
            $stmt = $conn->prepare("INSERT INTO bouwvolume (id, lengte, breedte, hoogte) VALUES (?, ?, ?, ?)");
            $stmt->execute([$newBouwvolumeId, $data['lengte'], $data['breedte'], $data['hoogte']]);
            
            $bouwvolumeId = $newBouwvolumeId;
        }
        
        $updateFields[] = "Bouwvolume_id = ?";
        $params[] = $bouwvolumeId;
    }
    
    if (empty($updateFields)) {
        sendResponse(["error" => "Geen velden om bij te werken"], 400);
    }
    
    // Printer_ID toevoegen aan params
    $params[] = $printerId;
    
    // Printer bijwerken
    $stmt = $conn->prepare("UPDATE Printer SET " . implode(", ", $updateFields) . " WHERE Printer_ID = ?");
    $success = $stmt->execute($params);
    
    if ($success) {
        // Filament compatibiliteit bijwerken indien gespecificeerd
        if (isset($data['filament_ids']) && is_array($data['filament_ids'])) {
            // Huidige filament compatibiliteiten verwijderen
            $stmt = $conn->prepare("DELETE FROM Filament_compatibiliteit WHERE printer_id = ?");
            $stmt->execute([$printerId]);
            
            // Nieuwe filament compatibiliteiten toevoegen
            foreach ($data['filament_ids'] as $filamentId) {
                $stmt = $conn->prepare("
                    INSERT INTO Filament_compatibiliteit (printer_id, filament_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$printerId, $filamentId]);
            }
        }
        
        sendResponse(["message" => "Printer succesvol bijgewerkt"]);
    } else {
        sendResponse(["error" => "Printer bijwerken mislukt"], 500);
    }
}

// Printer verwijderen
function deletePrinter($printerId) {
    global $conn;
    
    // Controleren of printer bestaat
    $stmt = $conn->prepare("SELECT Printer_ID FROM Printer WHERE Printer_ID = ?");
    $stmt->execute([$printerId]);
    if ($stmt->rowCount() === 0) {
        sendResponse(["error" => "Printer niet gevonden"], 404);
    }
    
    // Controleren of printer reserveringen heeft
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Reservatie WHERE Printer_ID = ?");
    $stmt->execute([$printerId]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        sendResponse(["error" => "Kan printer niet verwijderen: deze heeft nog reserveringen"], 409);
    }
    
    // Filament compatibiliteiten verwijderen
    $stmt = $conn->prepare("DELETE FROM Filament_compatibiliteit WHERE printer_id = ?");
    $stmt->execute([$printerId]);
    
    // Printer verwijderen
    $stmt = $conn->prepare("DELETE FROM Printer WHERE Printer_ID = ?");
    $success = $stmt->execute([$printerId]);
    
    if ($success) {
        sendResponse(["message" => "Printer succesvol verwijderd"]);
    } else {
        sendResponse(["error" => "Printer verwijderen mislukt"], 500);
    }
}
?>