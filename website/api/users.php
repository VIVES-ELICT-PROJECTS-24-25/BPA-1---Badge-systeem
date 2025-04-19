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
        if ($route == 'profile') {
            $userId = authenticate();
            getUserProfile($userId);
        } elseif ($route == 'all' && isset($_SESSION['Type']) && $_SESSION['Type'] == 'beheerder') {
            getAllUsers();
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'POST':
        if ($route == 'register') {
            registerUser();
        } elseif ($route == 'login') {
            loginUser();
        } elseif ($route == 'logout') {
            logoutUser();
        } elseif ($route == 'update') {
            $userId = authenticate();
            updateUser($userId);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'PUT':
        if (preg_match('/^(\d+)$/', $route, $matches)) {
            ensureAdmin();
            updateUserByAdmin($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'DELETE':
        if (preg_match('/^(\d+)$/', $route, $matches)) {
            ensureAdmin();
            deleteUser($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    default:
        sendResponse(["error" => "Methode niet toegestaan"], 405);
}

// Gebruikersprofiel ophalen
function getUserProfile($userId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT u.User_ID, u.Voornaam, u.Naam, u.Emailadres, u.Telefoon, u.Type, u.AanmaakAccount, 
               u.LaatsteAanmelding, u.HuidigActief, v.Vives_id, v.Type AS Vives_Type, 
               o.naam AS Opleiding
        FROM User u
        LEFT JOIN Vives v ON u.User_ID = v.User_ID
        LEFT JOIN opleidingen o ON v.opleiding_id = o.id
        WHERE u.User_ID = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse(["error" => "Gebruiker niet gevonden"], 404);
    }
    
    // Wachtwoord uit response verwijderen
    unset($user['Wachtwoord']);
    
    sendResponse($user);
}

// Alle gebruikers ophalen (alleen beheerder)
function getAllUsers() {
    global $conn;
    
    $stmt = $conn->query("
        SELECT u.User_ID, u.Voornaam, u.Naam, u.Emailadres, u.Telefoon, u.Type, u.AanmaakAccount, 
               u.LaatsteAanmelding, u.HuidigActief
        FROM User u
        ORDER BY u.Naam, u.Voornaam
    ");
    $users = $stmt->fetchAll();
    
    sendResponse($users);
}

// Nieuwe gebruiker registreren
function registerUser() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['Voornaam']) || !isset($data['Naam']) || !isset($data['Emailadres']) || !isset($data['Wachtwoord'])) {
        sendResponse(["error" => "Verplichte velden ontbreken"], 400);
    }
    
    // E-mail valideren
    if (!filter_var($data['Emailadres'], FILTER_VALIDATE_EMAIL)) {
        sendResponse(["error" => "Ongeldig e-mail formaat"], 400);
    }
    
    // Controleren of e-mail al bestaat
    $stmt = $conn->prepare("SELECT User_ID FROM User WHERE Emailadres = ?");
    $stmt->execute([$data['Emailadres']]);
    if ($stmt->rowCount() > 0) {
        sendResponse(["error" => "E-mailadres is al in gebruik"], 409);
    }
    
    // Wachtwoord hashen
    $hashedPassword = password_hash($data['Wachtwoord'], PASSWORD_DEFAULT);
    
    // Type valideren (standaard naar 'student' als niet gespecificeerd)
    $type = isset($data['Type']) ? $data['Type'] : 'student';
    if (!in_array($type, ['student', 'onderzoeker', 'beheerder'])) {
        $type = 'student';
    }
    
    // Volgende User_ID bepalen
    $stmt = $conn->query("SELECT MAX(User_ID) as maxId FROM User");
    $result = $stmt->fetch();
    $newUserId = ($result['maxId'] ?? 0) + 1;
    
    // Gebruiker aanmaken
    $stmt = $conn->prepare("
        INSERT INTO User (User_ID, Voornaam, Naam, Emailadres, Telefoon, Wachtwoord, Type, 
                         AanmaakAccount, LaatsteAanmelding, HuidigActief)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1)
    ");
    
    $success = $stmt->execute([
        $newUserId,
        $data['Voornaam'],
        $data['Naam'],
        $data['Emailadres'],
        $data['Telefoon'] ?? null,
        $hashedPassword,
        $type
    ]);
    
    // Als het Vives gegevens betreft, voeg toe aan Vives tabel
    if ($success && isset($data['Vives_id']) && !empty($data['Vives_id'])) {
        // Vives type valideren
        $vivesType = isset($data['Vives_Type']) ? $data['Vives_Type'] : 'student';
        if (!in_array($vivesType, ['student', 'medewerker', 'onderzoeker'])) {
            $vivesType = 'student';
        }
        
        $stmt = $conn->prepare("
            INSERT INTO Vives (User_ID, Voornaam, Vives_id, opleiding_id, Type)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $newUserId,
            $data['Voornaam'],
            $data['Vives_id'],
            $data['opleiding_id'] ?? null,
            $vivesType
        ]);
    }
    
    if ($success) {
        sendResponse(["message" => "Gebruiker succesvol geregistreerd", "User_ID" => $newUserId], 201);
    } else {
        sendResponse(["error" => "Registratie mislukt"], 500);
    }
}

// Gebruiker inloggen
function loginUser() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['Emailadres']) || !isset($data['Wachtwoord'])) {
        sendResponse(["error" => "E-mail of wachtwoord ontbreekt"], 400);
    }
    
    $stmt = $conn->prepare("
        SELECT User_ID, Voornaam, Naam, Emailadres, Wachtwoord, Type 
        FROM User 
        WHERE Emailadres = ?
    ");
    $stmt->execute([$data['Emailadres']]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($data['Wachtwoord'], $user['Wachtwoord'])) {
        sendResponse(["error" => "Ongeldige inloggegevens"], 401);
    }
    
    // Update laatste aanmelding en actieve status
    $stmt = $conn->prepare("
        UPDATE User 
        SET LaatsteAanmelding = NOW(), HuidigActief = 1 
        WHERE User_ID = ?
    ");
    $stmt->execute([$user['User_ID']]);
    
    // Sessie variabelen instellen
    $_SESSION['User_ID'] = $user['User_ID'];
    $_SESSION['Voornaam'] = $user['Voornaam'];
    $_SESSION['Naam'] = $user['Naam'];
    $_SESSION['Type'] = $user['Type'];
    
    // Wachtwoord uit response verwijderen
    unset($user['Wachtwoord']);
    
    sendResponse(["message" => "Succesvol ingelogd", "user" => $user]);
}

// Gebruiker uitloggen
function logoutUser() {
    global $conn;
    
    if (isset($_SESSION['User_ID'])) {
        // Update actieve status
        $stmt = $conn->prepare("UPDATE User SET HuidigActief = 0 WHERE User_ID = ?");
        $stmt->execute([$_SESSION['User_ID']]);
    }
    
    // Sessie vernietigen
    session_destroy();
    sendResponse(["message" => "Succesvol uitgelogd"]);
}

// Gebruikersprofiel bijwerken
function updateUser($userId) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Update velden opbouwen
    $updateFields = [];
    $params = [];
    
    if (isset($data['Emailadres'])) {
        if (!filter_var($data['Emailadres'], FILTER_VALIDATE_EMAIL)) {
            sendResponse(["error" => "Ongeldig e-mail formaat"], 400);
        }
        
        // Controleren of e-mail al bestaat bij een andere gebruiker
        $stmt = $conn->prepare("SELECT User_ID FROM User WHERE Emailadres = ? AND User_ID != ?");
        $stmt->execute([$data['Emailadres'], $userId]);
        if ($stmt->rowCount() > 0) {
            sendResponse(["error" => "E-mailadres is al in gebruik"], 409);
        }
        
        $updateFields[] = "Emailadres = ?";
        $params[] = $data['Emailadres'];
    }
    
    if (isset($data['Voornaam'])) {
        $updateFields[] = "Voornaam = ?";
        $params[] = $data['Voornaam'];
    }
    
    if (isset($data['Naam'])) {
        $updateFields[] = "Naam = ?";
        $params[] = $data['Naam'];
    }
    
    if (isset($data['Telefoon'])) {
        $updateFields[] = "Telefoon = ?";
        $params[] = $data['Telefoon'];
    }
    
    if (isset($data['Wachtwoord']) && !empty($data['Wachtwoord'])) {
        $updateFields[] = "Wachtwoord = ?";
        $params[] = password_hash($data['Wachtwoord'], PASSWORD_DEFAULT);
    }
    
    if (empty($updateFields)) {
        sendResponse(["error" => "Geen velden om bij te werken"], 400);
    }
    
    // User_ID toevoegen aan params
    $params[] = $userId;
    
    // Gebruiker bijwerken
    $stmt = $conn->prepare("UPDATE User SET " . implode(", ", $updateFields) . " WHERE User_ID = ?");
    $success = $stmt->execute($params);
    
    if ($success) {
        // Vives gegevens bijwerken indien aanwezig
        if (isset($data['Vives_id']) || isset($data['opleiding_id']) || isset($data['Vives_Type'])) {
            // Controleren of er al een Vives record bestaat
            $stmt = $conn->prepare("SELECT User_ID FROM Vives WHERE User_ID = ?");
            $stmt->execute([$userId]);
            $vivesExists = $stmt->rowCount() > 0;
            
            if ($vivesExists) {
                $vivesUpdateFields = [];
                $vivesParams = [];
                
                if (isset($data['Voornaam'])) {
                    $vivesUpdateFields[] = "Voornaam = ?";
                    $vivesParams[] = $data['Voornaam'];
                }
                
                if (isset($data['Vives_id'])) {
                    $vivesUpdateFields[] = "Vives_id = ?";
                    $vivesParams[] = $data['Vives_id'];
                }
                
                if (isset($data['opleiding_id'])) {
                    $vivesUpdateFields[] = "opleiding_id = ?";
                    $vivesParams[] = $data['opleiding_id'];
                }
                
                if (isset($data['Vives_Type'])) {
                    $vivesType = $data['Vives_Type'];
                    if (!in_array($vivesType, ['student', 'medewerker', 'onderzoeker'])) {
                        $vivesType = 'student';
                    }
                    $vivesUpdateFields[] = "Type = ?";
                    $vivesParams[] = $vivesType;
                }
                
                if (!empty($vivesUpdateFields)) {
                    $vivesParams[] = $userId;
                    $stmt = $conn->prepare("UPDATE Vives SET " . implode(", ", $vivesUpdateFields) . " WHERE User_ID = ?");
                    $stmt->execute($vivesParams);
                }
            } else if (isset($data['Vives_id']) && !empty($data['Vives_id'])) {
                // Nieuw Vives record aanmaken
                $vivesType = isset($data['Vives_Type']) ? $data['Vives_Type'] : 'student';
                if (!in_array($vivesType, ['student', 'medewerker', 'onderzoeker'])) {
                    $vivesType = 'student';
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO Vives (User_ID, Voornaam, Vives_id, opleiding_id, Type)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $userId,
                    $data['Voornaam'] ?? '',
                    $data['Vives_id'],
                    $data['opleiding_id'] ?? null,
                    $vivesType
                ]);
            }
        }
        
        sendResponse(["message" => "Profiel succesvol bijgewerkt"]);
    } else {
        sendResponse(["error" => "Profiel bijwerken mislukt"], 500);
    }
}

// Gebruiker bijwerken door beheerder
function updateUserByAdmin($userId) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Controleren of gebruiker bestaat
    $stmt = $conn->prepare("SELECT User_ID FROM User WHERE User_ID = ?");
    $stmt->execute([$userId]);
    if ($stmt->rowCount() === 0) {
        sendResponse(["error" => "Gebruiker niet gevonden"], 404);
    }
    
    // Update velden opbouwen
    $updateFields = [];
    $params = [];
    
    if (isset($data['Emailadres'])) {
        if (!filter_var($data['Emailadres'], FILTER_VALIDATE_EMAIL)) {
            sendResponse(["error" => "Ongeldig e-mail formaat"], 400);
        }
        
        // Controleren of e-mail al bestaat bij een andere gebruiker
        $stmt = $conn->prepare("SELECT User_ID FROM User WHERE Emailadres = ? AND User_ID != ?");
        $stmt->execute([$data['Emailadres'], $userId]);
        if ($stmt->rowCount() > 0) {
            sendResponse(["error" => "E-mailadres is al in gebruik"], 409);
        }
        
        $updateFields[] = "Emailadres = ?";
        $params[] = $data['Emailadres'];
    }
    
    if (isset($data['Voornaam'])) {
        $updateFields[] = "Voornaam = ?";
        $params[] = $data['Voornaam'];
    }
    
    if (isset($data['Naam'])) {
        $updateFields[] = "Naam = ?";
        $params[] = $data['Naam'];
    }
    
    if (isset($data['Telefoon'])) {
        $updateFields[] = "Telefoon = ?";
        $params[] = $data['Telefoon'];
    }
    
    if (isset($data['Type'])) {
        if (!in_array($data['Type'], ['student', 'onderzoeker', 'beheerder'])) {
            sendResponse(["error" => "Ongeldig gebruikerstype"], 400);
        }
        $updateFields[] = "Type = ?";
        $params[] = $data['Type'];
    }
    
    if (isset($data['HuidigActief'])) {
        $updateFields[] = "HuidigActief = ?";
        $params[] = $data['HuidigActief'] ? 1 : 0;
    }
    
    if (isset($data['Wachtwoord']) && !empty($data['Wachtwoord'])) {
        $updateFields[] = "Wachtwoord = ?";
        $params[] = password_hash($data['Wachtwoord'], PASSWORD_DEFAULT);
    }
    
    if (empty($updateFields)) {
        sendResponse(["error" => "Geen velden om bij te werken"], 400);
    }
    
    // User_ID toevoegen aan params
    $params[] = $userId;
    
    // Gebruiker bijwerken
    $stmt = $conn->prepare("UPDATE User SET " . implode(", ", $updateFields) . " WHERE User_ID = ?");
    $success = $stmt->execute($params);
    
    if ($success) {
        // Vives gegevens bijwerken indien aanwezig
        if (isset($data['Vives_id']) || isset($data['opleiding_id']) || isset($data['Vives_Type'])) {
            // Controleren of er al een Vives record bestaat
            $stmt = $conn->prepare("SELECT User_ID FROM Vives WHERE User_ID = ?");
            $stmt->execute([$userId]);
            $vivesExists = $stmt->rowCount() > 0;
            
            if ($vivesExists) {
                $vivesUpdateFields = [];
                $vivesParams = [];
                
                if (isset($data['Voornaam'])) {
                    $vivesUpdateFields[] = "Voornaam = ?";
                    $vivesParams[] = $data['Voornaam'];
                }
                
                if (isset($data['Vives_id'])) {
                    $vivesUpdateFields[] = "Vives_id = ?";
                    $vivesParams[] = $data['Vives_id'];
                }
                
                if (isset($data['opleiding_id'])) {
                    $vivesUpdateFields[] = "opleiding_id = ?";
                    $vivesParams[] = $data['opleiding_id'];
                }
                
                if (isset($data['Vives_Type'])) {
                    $vivesType = $data['Vives_Type'];
                    if (!in_array($vivesType, ['student', 'medewerker', 'onderzoeker'])) {
                        $vivesType = 'student';
                    }
                    $vivesUpdateFields[] = "Type = ?";
                    $vivesParams[] = $vivesType;
                }
                
                if (!empty($vivesUpdateFields)) {
                    $vivesParams[] = $userId;
                    $stmt = $conn->prepare("UPDATE Vives SET " . implode(", ", $vivesUpdateFields) . " WHERE User_ID = ?");
                    $stmt->execute($vivesParams);
                }
            } else if (isset($data['Vives_id']) && !empty($data['Vives_id'])) {
                // Nieuw Vives record aanmaken
                $vivesType = isset($data['Vives_Type']) ? $data['Vives_Type'] : 'student';
                if (!in_array($vivesType, ['student', 'medewerker', 'onderzoeker'])) {
                    $vivesType = 'student';
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO Vives (User_ID, Voornaam, Vives_id, opleiding_id, Type)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $userId,
                    $data['Voornaam'] ?? '',
                    $data['Vives_id'],
                    $data['opleiding_id'] ?? null,
                    $vivesType
                ]);
            }
        }
        
        sendResponse(["message" => "Gebruiker succesvol bijgewerkt"]);
    } else {
        sendResponse(["error" => "Gebruiker bijwerken mislukt"], 500);
    }
}

// Gebruiker verwijderen
function deleteUser($userId) {
    global $conn;
    
    // Controleren of gebruiker bestaat
    $stmt = $conn->prepare("SELECT User_ID FROM User WHERE User_ID = ?");
    $stmt->execute([$userId]);
    if ($stmt->rowCount() === 0) {
        sendResponse(["error" => "Gebruiker niet gevonden"], 404);
    }
    
    // Gebruiker verwijderen
    $stmt = $conn->prepare("DELETE FROM User WHERE User_ID = ?");
    $success = $stmt->execute([$userId]);
    
    if ($success) {
        sendResponse(["message" => "Gebruiker succesvol verwijderd"]);
    } else {
        sendResponse(["error" => "Gebruiker verwijderen mislukt"], 500);
    }
}
?>