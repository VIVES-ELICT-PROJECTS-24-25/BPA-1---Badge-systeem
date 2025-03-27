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
            getAllOPOs();
        } elseif (preg_match('/^(\d+)$/', $route, $matches)) {
            getOPO($matches[1]);
        } elseif ($route == 'by-opleiding' && isset($_GET['opleiding_id'])) {
            getOPOsByOpleiding($_GET['opleiding_id']);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'POST':
        ensureAdmin();
        createOPO();
        break;
    case 'PUT':
        if (preg_match('/^(\d+)$/', $route, $matches)) {
            ensureAdmin();
            updateOPO($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'DELETE':
        if (preg_match('/^(\d+)$/', $route, $matches)) {
            ensureAdmin();
            deleteOPO($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    default:
        sendResponse(["error" => "Methode niet toegestaan"], 405);
}

// Alle OPOs ophalen
function getAllOPOs() {
    global $conn;
    
    $stmt = $conn->query("
        SELECT o.id, o.naam, o.opleiding_id, op.naam AS opleiding_naam
        FROM OPOs o
        LEFT JOIN opleidingen op ON o.opleiding_id = op.id
        ORDER BY o.naam
    ");
    
    $opos = $stmt->fetchAll();
    
    sendResponse($opos);
}

// Specifieke OPO ophalen
function getOPO($opoId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT o.id, o.naam, o.opleiding_id, op.naam AS opleiding_naam
        FROM OPOs o
        LEFT JOIN opleidingen op ON o.opleiding_id = op.id
        WHERE o.id = ?
    ");
    
    $stmt->execute([$opoId]);
    $opo = $stmt->fetch();
    
    if (!$opo) {
        sendResponse(["error" => "OPO niet gevonden"], 404);
    }
    
    // Aantal studenten die deze OPO hebben gebruikt voor kostenbewijzen
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT r.User_ID) AS student_count
        FROM kostenbewijzing_studenten ks
        JOIN Reservatie r ON ks.reservatie_id = r.Reservatie_ID
        WHERE ks.OPO_id = ?
    ");
    
    $stmt->execute([$opoId]);
    $studentCount = $stmt->fetch()['student_count'];
    
    $opo['student_count'] = $studentCount;
    
    // Aantal reserveringen voor deze OPO
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS reservation_count
        FROM kostenbewijzing_studenten
        WHERE OPO_id = ?
    ");
    
    $stmt->execute([$opoId]);
    $reservationCount = $stmt->fetch()['reservation_count'];
    
    $opo['reservation_count'] = $reservationCount;
    
    sendResponse($opo);
}

// OPOs per opleiding ophalen
function getOPOsByOpleiding($opleidingId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT o.id, o.naam, o.opleiding_id, op.naam AS opleiding_naam
        FROM OPOs o
        LEFT JOIN opleidingen op ON o.opleiding_id = op.id
        WHERE o.opleiding_id = ?
        ORDER BY o.naam
    ");
    
    $stmt->execute([$opleidingId]);
    $opos = $stmt->fetchAll();
    
    sendResponse($opos);
}

// Nieuwe OPO aanmaken
function createOPO() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['naam'])) {
        sendResponse(["error" => "Naam is verplicht"], 400);
    }
    
    // Controleren of opleiding bestaat indien opgegeven
    if (isset($data['opleiding_id'])) {
        $stmt = $conn->prepare("SELECT id FROM opleidingen WHERE id = ?");
        $stmt->execute([$data['opleiding_id']]);
        
        if ($stmt->rowCount() === 0) {
            sendResponse(["error" => "Opleiding niet gevonden"], 404);
        }
    }
    
    // Volgende ID bepalen
    $stmt = $conn->query("SELECT MAX(id) as maxId FROM OPOs");
    $result = $stmt->fetch();
    $newId = ($result['maxId'] ?? 0) + 1;
    
    // OPO aanmaken
    $stmt = $conn->prepare("INSERT INTO OPOs (id, opleiding_id, naam) VALUES (?, ?, ?)");
    $success = $stmt->execute([
        $newId,
        $data['opleiding_id'] ?? null,
        $data['naam']
    ]);
    
    if ($success) {
        sendResponse(["message" => "OPO succesvol aangemaakt", "id" => $newId], 201);
    } else {
        sendResponse(["error" => "OPO aanmaken mislukt"], 500);
    }
}

// OPO bijwerken
function updateOPO($opoId) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Controleren of OPO bestaat
    $stmt = $conn->prepare("SELECT id FROM OPOs WHERE id = ?");
    $stmt->execute([$opoId]);
    
    if ($stmt->rowCount() === 0) {
        sendResponse(["error" => "OPO niet gevonden"], 404);
    }
    
    // Update velden opbouwen
    $updateFields = [];
    $params = [];
    
    if (isset($data['naam'])) {
        $updateFields[] = "naam = ?";
        $params[] = $data['naam'];
    }
    
    if (isset($data['opleiding_id'])) {
        // Controleren of opleiding bestaat
        if ($data['opleiding_id'] !== null) {
            $stmt = $conn->prepare("SELECT id FROM opleidingen WHERE id = ?");
            $stmt->execute([$data['opleiding_id']]);
            
            if ($stmt->rowCount() === 0) {
                sendResponse(["error" => "Opleiding niet gevonden"], 404);
            }
        }
        
        $updateFields[] = "opleiding_id = ?";
        $params[] = $data['opleiding_id'];
    }
    
    if (empty($updateFields)) {
        sendResponse(["error" => "Geen velden om bij te werken"], 400);
    }
    
    // ID toevoegen aan params
    $params[] = $opoId;
    
    // OPO bijwerken
    $stmt = $conn->prepare("UPDATE OPOs SET " . implode(", ", $updateFields) . " WHERE id = ?");
    $success = $stmt->execute($params);
    
    if ($success) {
        sendResponse(["message" => "OPO succesvol bijgewerkt"]);
    } else {
        sendResponse(["error" => "OPO bijwerken mislukt"], 500);
    }
}

// OPO verwijderen
function deleteOPO($opoId) {
    global $conn;
    
    // Controleren of OPO bestaat
    $stmt = $conn->prepare("SELECT id FROM OPOs WHERE id = ?");
    $stmt->execute([$opoId]);
    
    if ($stmt->rowCount() === 0) {
        sendResponse(["error" => "OPO niet gevonden"], 404);
    }
    
    // Controleren of OPO in gebruik is
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM kostenbewijzing_studenten WHERE OPO_id = ?");
    $stmt->execute([$opoId]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        sendResponse(["error" => "OPO kan niet worden verwijderd omdat het in gebruik is voor kostenbewijzen"], 409);
    }
    
    // OPO verwijderen
    $stmt = $conn->prepare("DELETE FROM OPOs WHERE id = ?");
    $success = $stmt->execute([$opoId]);
    
    if ($success) {
        sendResponse(["message" => "OPO succesvol verwijderd"]);
    } else {
        sendResponse(["error" => "OPO verwijderen mislukt"], 500);
    }
}
?>