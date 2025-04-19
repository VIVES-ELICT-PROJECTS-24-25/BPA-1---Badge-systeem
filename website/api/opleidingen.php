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
            getAllOpleidingen();
        } elseif (preg_match('/^(\d+)$/', $route, $matches)) {
            getOpleiding($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'POST':
        ensureAdmin();
        createOpleiding();
        break;
    case 'PUT':
        if (preg_match('/^(\d+)$/', $route, $matches)) {
            ensureAdmin();
            updateOpleiding($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'DELETE':
        if (preg_match('/^(\d+)$/', $route, $matches)) {
            ensureAdmin();
            deleteOpleiding($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    default:
        sendResponse(["error" => "Methode niet toegestaan"], 405);
}

// Alle opleidingen ophalen
function getAllOpleidingen() {
    global $conn;
    
    $stmt = $conn->query("SELECT id, naam FROM opleidingen ORDER BY naam");
    $opleidingen = $stmt->fetchAll();
    
    sendResponse($opleidingen);
}

// Specifieke opleiding ophalen
function getOpleiding($opleidingId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, naam FROM opleidingen WHERE id = ?");
    $stmt->execute([$opleidingId]);
    $opleiding = $stmt->fetch();
    
    if (!$opleiding) {
        sendResponse(["error" => "Opleiding niet gevonden"], 404);
    }
    
    // Aantal studenten in deze opleiding
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS student_count
        FROM Vives
        WHERE opleiding_id = ? AND Type = 'student'
    ");
    
    $stmt->execute([$opleidingId]);
    $studentCount = $stmt->fetch()['student_count'];
    
    $opleiding['student_count'] = $studentCount;
    
    // Aantal OPOs in deze opleiding
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS opo_count
        FROM OPOs
        WHERE opleiding_id = ?
    ");
    
    $stmt->execute([$opleidingId]);
    $opoCount = $stmt->fetch()['opo_count'];
    
    $opleiding['opo_count'] = $opoCount;
    
    // Lijst van OPOs in deze opleiding
    $stmt = $conn->prepare("
        SELECT id, naam
        FROM OPOs
        WHERE opleiding_id = ?
        ORDER BY naam
    ");
    
    $stmt->execute([$opleidingId]);
    $opleiding['opos'] = $stmt->fetchAll();
    
    sendResponse($opleiding);
}

// Nieuwe opleiding aanmaken
function createOpleiding() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['naam'])) {
        sendResponse(["error" => "Naam is verplicht"], 400);
    }
    
    // Volgende ID bepalen
    $stmt = $conn->query("SELECT MAX(id) as maxId FROM opleidingen");
    $result = $stmt->fetch();
    $newId = ($result['maxId'] ?? 0) + 1;
    
    // Opleiding aanmaken
    $stmt = $conn->prepare("INSERT INTO opleidingen (id, naam) VALUES (?, ?)");
    $success = $stmt->execute([$newId, $data['naam']]);
    
    if ($success) {
        sendResponse(["message" => "Opleiding succesvol aangemaakt", "id" => $newId], 201);
    } else {
        sendResponse(["error" => "Opleiding aanmaken mislukt"], 500);
    }
}

// Opleiding bijwerken
function updateOpleiding($opleidingId) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Controleren of opleiding bestaat
    $stmt = $conn->prepare("SELECT id FROM opleidingen WHERE id = ?");
    $stmt->execute([$opleidingId]);
    
    if ($stmt->rowCount() === 0) {
        sendResponse(["error" => "Opleiding niet gevonden"], 404);
    }
    
    if (!isset($data['naam'])) {
        sendResponse(["error" => "Naam is verplicht"], 400);
    }
    
    // Opleiding bijwerken
    $stmt = $conn->prepare("UPDATE opleidingen SET naam = ? WHERE id = ?");
    $success = $stmt->execute([$data['naam'], $opleidingId]);
    
    if ($success) {
        sendResponse(["message" => "Opleiding succesvol bijgewerkt"]);
    } else {
        sendResponse(["error" => "Opleiding bijwerken mislukt"], 500);
    }
}

// Opleiding verwijderen
function deleteOpleiding($opleidingId) {
    global $conn;
    
    // Controleren of opleiding bestaat
    $stmt = $conn->prepare("SELECT id FROM opleidingen WHERE id = ?");
    $stmt->execute([$opleidingId]);
    
    if ($stmt->rowCount() === 0) {
        sendResponse(["error" => "Opleiding niet gevonden"], 404);
    }
    
    // Controleren of opleiding in gebruik is bij OPOs
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM OPOs WHERE opleiding_id = ?");
    $stmt->execute([$opleidingId]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        sendResponse(["error" => "Opleiding kan niet worden verwijderd omdat er OPOs aan gekoppeld zijn"], 409);
    }
    
    // Controleren of opleiding in gebruik is bij Vives gebruikers
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Vives WHERE opleiding_id = ?");
    $stmt->execute([$opleidingId]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        sendResponse(["error" => "Opleiding kan niet worden verwijderd omdat er gebruikers aan gekoppeld zijn"], 409);
    }
    
    // Opleiding verwijderen
    $stmt = $conn->prepare("DELETE FROM opleidingen WHERE id = ?");
    $success = $stmt->execute([$opleidingId]);
    
    if ($success) {
        sendResponse(["message" => "Opleiding succesvol verwijderd"]);
    } else {
        sendResponse(["error" => "Opleiding verwijderen mislukt"], 500);
    }
}
?>