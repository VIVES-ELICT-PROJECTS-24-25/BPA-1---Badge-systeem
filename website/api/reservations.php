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
            if (isset($_SESSION['Type']) && $_SESSION['Type'] == 'beheerder') {
                getAllReservations();
            } else {
                $userId = authenticate();
                getUserReservations($userId);
            }
        } elseif ($route == 'my') {
            $userId = authenticate();
            getUserReservations($userId);
        } elseif ($route == 'calendar') {
            getCalendarReservations();
        } elseif (preg_match('/^(\d+)$/', $route, $matches)) {
            getReservation($matches[1]);
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'POST':
        authenticate();
        createReservation();
        break;
    case 'PUT':
        if (preg_match('/^(\d+)$/', $route, $matches)) {
            if (isset($_SESSION['Type']) && $_SESSION['Type'] == 'beheerder') {
                updateReservation($matches[1]);
            } else {
                $userId = authenticate();
                updateUserReservation($userId, $matches[1]);
            }
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    case 'DELETE':
        if (preg_match('/^(\d+)$/', $route, $matches)) {
            if (isset($_SESSION['Type']) && $_SESSION['Type'] == 'beheerder') {
                deleteReservation($matches[1]);
            } else {
                $userId = authenticate();
                deleteUserReservation($userId, $matches[1]);
            }
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    default:
        sendResponse(["error" => "Methode niet toegestaan"], 405);
}

// Alle reserveringen ophalen (beheerder)
function getAllReservations() {
    global $conn;
    
    $stmt = $conn->query("
        SELECT r.Reservatie_ID, r.User_ID, r.Printer_ID, r.DATE_TIME_RESERVATIE, r.PRINT_START, 
               r.PRINT_END, r.Comment, r.Pincode, r.filament_id, r.verbruik,
               u.Voornaam, u.Naam, u.Emailadres,
               p.Status AS Printer_Status, p.Versie_Toestel,
               f.Type AS Filament_Type, f.Kleur AS Filament_Kleur
        FROM Reservatie r
        JOIN User u ON r.User_ID = u.User_ID
        JOIN Printer p ON r.Printer_ID = p.Printer_ID
        LEFT JOIN Filament f ON r.filament_id = f.id
        ORDER BY r.PRINT_START DESC
    ");
    
    $reservations = $stmt->fetchAll();
    
    sendResponse($reservations);
}

// Gebruiker's reserveringen ophalen
function getUserReservations($userId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT r.Reservatie_ID, r.Printer_ID, r.DATE_TIME_RESERVATIE, r.PRINT_START, 
               r.PRINT_END, r.Comment, r.Pincode, r.filament_id, r.verbruik,
               p.Status AS Printer_Status, p.Versie_Toestel,
               f.Type AS Filament_Type, f.Kleur AS Filament_Kleur
        FROM Reservatie r
        JOIN Printer p ON r.Printer_ID = p.Printer_ID
        LEFT JOIN Filament f ON r.filament_id = f.id
        WHERE r.User_ID = ?
        ORDER BY r.PRINT_START DESC
    ");
    
    $stmt->execute([$userId]);
    $reservations = $stmt->fetchAll();
    
    // Kostenbewijs informatie toevoegen voor student/onderzoeker
    foreach ($reservations as &$reservation) {
        // Voor studenten
        $stmt = $conn->prepare("
            SELECT ks.eigen_rekening, o.naam AS OPO_naam
            FROM kostenbewijzing_studenten ks
            LEFT JOIN OPOs o ON ks.OPO_id = o.id
            WHERE ks.reservatie_id = ?
        ");
        $stmt->execute([$reservation['Reservatie_ID']]);
        $student_kosten = $stmt->fetch();
        if ($student_kosten) {
            $reservation['kosten_student'] = $student_kosten;
        }
        
        // Voor onderzoekers
        $stmt = $conn->prepare("
            SELECT onderzoeksproject, kostenpost
            FROM kostenbewijzing_onderzoekers
            WHERE reservatie_id = ?
        ");
        $stmt->execute([$reservation['Reservatie_ID']]);
        $onderzoeker_kosten = $stmt->fetch();
        if ($onderzoeker_kosten) {
            $reservation['kosten_onderzoeker'] = $onderzoeker_kosten;
        }
        
        // Software reservatie info
        $stmt = $conn->prepare("
            SELECT ontwikkelstand
            FROM reservatie_software
            WHERE reservatie_id = ?
        ");
        $stmt->execute([$reservation['Reservatie_ID']]);
        $software_info = $stmt->fetch();
        if ($software_info) {
            $reservation['software_info'] = $software_info;
        }
    }
    
    sendResponse($reservations);
}

// Kalender reserveringen ophalen
function getCalendarReservations() {
    global $conn;
    
    // Start- en einddatum uit query parameters voor kalender filtering
    $start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
    $end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d', strtotime('+30 days'));
    
    // MODIFIED: Removed user information from the query
    $stmt = $conn->prepare("
        SELECT r.Reservatie_ID, r.Printer_ID, r.PRINT_START, r.PRINT_END,
               p.Versie_Toestel
        FROM Reservatie r
        JOIN Printer p ON r.Printer_ID = p.Printer_ID
        WHERE DATE(r.PRINT_START) >= ? AND DATE(r.PRINT_END) <= ?
        ORDER BY r.PRINT_START
    ");
    
    $stmt->execute([$start, $end]);
    $reservations = $stmt->fetchAll();
    
    // MODIFIED: Only include printer name and reservation ID in title, no user info
    $calendarEvents = [];
    foreach ($reservations as $reservation) {
        $calendarEvents[] = [
            'id' => $reservation['Reservatie_ID'],
            'title' => $reservation['Versie_Toestel'] . ' (Res. #' . $reservation['Reservatie_ID'] . ')',
            'start' => $reservation['PRINT_START'],
            'end' => $reservation['PRINT_END'],
            'resourceId' => $reservation['Printer_ID']
        ];
    }
    
    sendResponse($calendarEvents);
}

// Specifieke reservering ophalen
function getReservation($reservationId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT r.Reservatie_ID, r.User_ID, r.Printer_ID, r.DATE_TIME_RESERVATIE, r.PRINT_START, 
               r.PRINT_END, r.Comment, r.Pincode, r.filament_id, r.verbruik,
               u.Voornaam, u.Naam, u.Emailadres, u.Type AS User_Type,
               p.Status AS Printer_Status, p.Versie_Toestel,
               f.Type AS Filament_Type, f.Kleur AS Filament_Kleur
        FROM Reservatie r
        JOIN User u ON r.User_ID = u.User_ID
        JOIN Printer p ON r.Printer_ID = p.Printer_ID
        LEFT JOIN Filament f ON r.filament_id = f.id
        WHERE r.Reservatie_ID = ?
    ");
    
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        sendResponse(["error" => "Reservering niet gevonden"], 404);
    }
    
    // Controleren of gebruiker toegang heeft tot deze reservering
    if ($_SESSION['Type'] != 'beheerder' && $reservation['User_ID'] != $_SESSION['User_ID']) {
        sendResponse(["error" => "Geen toegang tot deze reservering"], 403);
    }
    
    // Kostenbewijs informatie toevoegen voor student/onderzoeker
    // Voor studenten
    $stmt = $conn->prepare("
        SELECT ks.eigen_rekening, ks.OPO_id, o.naam AS OPO_naam
        FROM kostenbewijzing_studenten ks
        LEFT JOIN OPOs o ON ks.OPO_id = o.id
        WHERE ks.reservatie_id = ?
    ");
    $stmt->execute([$reservationId]);
    $student_kosten = $stmt->fetch();
    if ($student_kosten) {
        $reservation['kosten_student'] = $student_kosten;
    }
    
    // Voor onderzoekers
    $stmt = $conn->prepare("
        SELECT onderzoeksproject, kostenpost
        FROM kostenbewijzing_onderzoekers
        WHERE reservatie_id = ?
    ");
    $stmt->execute([$reservationId]);
    $onderzoeker_kosten = $stmt->fetch();
    if ($onderzoeker_kosten) {
        $reservation['kosten_onderzoeker'] = $onderzoeker_kosten;
    }
    
    // Software reservatie info
    $stmt = $conn->prepare("
        SELECT ontwikkelstand
        FROM reservatie_software
        WHERE reservatie_id = ?
    ");
    $stmt->execute([$reservationId]);
    $software_info = $stmt->fetch();
    if ($software_info) {
        $reservation['software_info'] = $software_info;
    }
    
    sendResponse($reservation);
}

// Nieuwe reservering aanmaken
function createReservation() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['User_ID'];
    
    // Beheerder kan reservering voor elke gebruiker aanmaken
    if ($_SESSION['Type'] == 'beheerder' && isset($data['User_ID'])) {
        $userId = $data['User_ID'];
    }
    
    if (!isset($data['Printer_ID']) || !isset($data['PRINT_START']) || !isset($data['PRINT_END'])) {
        sendResponse(["error" => "Verplichte velden ontbreken"], 400);
    }
    
    // Start- en eindtijden valideren
    $startTime = new DateTime($data['PRINT_START']);
    $endTime = new DateTime($data['PRINT_END']);
    
    if ($startTime >= $endTime) {
        sendResponse(["error" => "Eindtijd moet na starttijd zijn"], 400);
    }
    
    // Controleren of printer bestaat en beschikbaar is
    $stmt = $conn->prepare("SELECT Printer_ID, Status FROM Printer WHERE Printer_ID = ?");
    $stmt->execute([$data['Printer_ID']]);
    $printer = $stmt->fetch();
    
    if (!$printer) {
        sendResponse(["error" => "Printer niet gevonden"], 404);
    }
    
    if ($printer['Status'] != 'beschikbaar') {
        sendResponse(["error" => "Printer is niet beschikbaar voor reserveringen"], 409);
    }
    
    // Controleren op overlappende reserveringen
    $stmt = $conn->prepare("
        SELECT Reservatie_ID FROM Reservatie 
        WHERE Printer_ID = ? AND 
              ((PRINT_START <= ? AND PRINT_END >= ?) OR
               (PRINT_START <= ? AND PRINT_END >= ?) OR
               (PRINT_START >= ? AND PRINT_END <= ?))
    ");
    
    $stmt->execute([
        $data['Printer_ID'],
        $data['PRINT_START'], $data['PRINT_START'],
        $data['PRINT_END'], $data['PRINT_END'],
        $data['PRINT_START'], $data['PRINT_END']
    ]);
    
    if ($stmt->rowCount() > 0) {
        sendResponse(["error" => "Tijdslot is al gereserveerd"], 409);
    }
    
    // Volgende Reservatie_ID bepalen
    $stmt = $conn->query("SELECT MAX(Reservatie_ID) as maxId FROM Reservatie");
    $result = $stmt->fetch();
    $newReservationId = ($result['maxId'] ?? 0) + 1;
    
    // Pincode genereren
    $pincode = mt_rand(1000, 9999);
    
    // Reservering aanmaken
    $stmt = $conn->prepare("
        INSERT INTO Reservatie (Reservatie_ID, User_ID, Printer_ID, DATE_TIME_RESERVATIE, 
                               PRINT_START, PRINT_END, Comment, Pincode, filament_id, verbruik)
        VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)
    ");
    
    $success = $stmt->execute([
        $newReservationId,
        $userId,
        $data['Printer_ID'],
        $data['PRINT_START'],
        $data['PRINT_END'],
        $data['Comment'] ?? null,
        $pincode,
        $data['filament_id'] ?? null,
        $data['verbruik'] ?? null
    ]);
    
    if ($success) {
        // Extra informatie toevoegen op basis van gebruikerstype
        $userType = $_SESSION['Type'];
        
        // Voor studenten
        if ($userType == 'student' && isset($data['OPO_id'])) {
            $stmt = $conn->prepare("
                INSERT INTO kostenbewijzing_studenten (reservatie_id, OPO_id, eigen_rekening)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $newReservationId,
                $data['OPO_id'],
                $data['eigen_rekening'] ?? 0
            ]);
        }
        
        // Voor onderzoekers
        if ($userType == 'onderzoeker' && isset($data['onderzoeksproject'])) {
            $stmt = $conn->prepare("
                INSERT INTO kostenbewijzing_onderzoekers (reservatie_id, onderzoeksproject, kostenpost)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $newReservationId,
                $data['onderzoeksproject'],
                $data['kostenpost'] ?? null
            ]);
        }
        
        // Voor software reservaties
        if (isset($data['ontwikkelstand'])) {
            $stmt = $conn->prepare("
                INSERT INTO reservatie_software (reservatie_id, ontwikkelstand)
                VALUES (?, ?)
            ");
            $stmt->execute([
                $newReservationId,
                $data['ontwikkelstand']
            ]);
        }
        
        // Printer status bijwerken naar 'in_gebruik' tijdens de gereserveerde periode
        $stmt = $conn->prepare("
            UPDATE Printer 
            SET Status = 'in_gebruik', LAATSTE_STATUS_CHANGE = NOW() 
            WHERE Printer_ID = ?
        ");
        $stmt->execute([$data['Printer_ID']]);
        
        sendResponse([
            "message" => "Reservering succesvol aangemaakt", 
            "Reservatie_ID" => $newReservationId,
            "Pincode" => $pincode
        ], 201);
    } else {
        sendResponse(["error" => "Reservering aanmaken mislukt"], 500);
    }
}

// Reservering bijwerken (beheerder)
function updateReservation($reservationId) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Controleren of reservering bestaat
    $stmt = $conn->prepare("SELECT * FROM Reservatie WHERE Reservatie_ID = ?");
    $stmt->execute([$reservationId]);
    $currentReservation = $stmt->fetch();
    
    if (!$currentReservation) {
        sendResponse(["error" => "Reservering niet gevonden"], 404);
    }
    
    // Update velden opbouwen
    $updateFields = [];
    $params = [];
    
    if (isset($data['User_ID'])) {
        // Controleren of gebruiker bestaat
        $stmt = $conn->prepare("SELECT User_ID FROM User WHERE User_ID = ?");
        $stmt->execute([$data['User_ID']]);
        if ($stmt->rowCount() === 0) {
            sendResponse(["error" => "Gebruiker niet gevonden"], 404);
        }
        
        $updateFields[] = "User_ID = ?";
        $params[] = $data['User_ID'];
    }
    
    if (isset($data['Printer_ID'])) {
        // Controleren of printer bestaat
        $stmt = $conn->prepare("SELECT Printer_ID FROM Printer WHERE Printer_ID = ?");
        $stmt->execute([$data['Printer_ID']]);
        if ($stmt->rowCount() === 0) {
            sendResponse(["error" => "Printer niet gevonden"], 404);
        }
        
        $updateFields[] = "Printer_ID = ?";
        $params[] = $data['Printer_ID'];
    }
    
    if (isset($data['PRINT_START'])) {
        $updateFields[] = "PRINT_START = ?";
        $params[] = $data['PRINT_START'];
    }
    
    if (isset($data['PRINT_END'])) {
        $updateFields[] = "PRINT_END = ?";
        $params[] = $data['PRINT_END'];
    }
    
    if (isset($data['Comment'])) {
        $updateFields[] = "Comment = ?";
        $params[] = $data['Comment'];
    }
    
    if (isset($data['Pincode'])) {
        $updateFields[] = "Pincode = ?";
        $params[] = $data['Pincode'];
    }
    
    if (isset($data['filament_id'])) {
        $updateFields[] = "filament_id = ?";
        $params[] = $data['filament_id'];
    }
    
    if (isset($data['verbruik'])) {
        $updateFields[] = "verbruik = ?";
        $params[] = $data['verbruik'];
    }
    
    if (empty($updateFields)) {
        sendResponse(["error" => "Geen velden om bij te werken"], 400);
    }
    
    // Controleren op overlappende reserveringen als start- of eindtijd is gewijzigd
    if ((isset($data['PRINT_START']) || isset($data['PRINT_END'])) && 
        (isset($data['Printer_ID']) || !isset($data['Printer_ID']) && $currentReservation['Printer_ID'])) {
        
        $printerId = $data['Printer_ID'] ?? $currentReservation['Printer_ID'];
        $printStart = $data['PRINT_START'] ?? $currentReservation['PRINT_START'];
        $printEnd = $data['PRINT_END'] ?? $currentReservation['PRINT_END'];
        
        $stmt = $conn->prepare("
            SELECT Reservatie_ID FROM Reservatie 
            WHERE Printer_ID = ? AND Reservatie_ID != ? AND
                  ((PRINT_START <= ? AND PRINT_END >= ?) OR
                   (PRINT_START <= ? AND PRINT_END >= ?) OR
                   (PRINT_START >= ? AND PRINT_END <= ?))
        ");
        
        $stmt->execute([
            $printerId,
            $reservationId,
            $printStart, $printStart,
            $printEnd, $printEnd,
            $printStart, $printEnd
        ]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(["error" => "Tijdslot is al gereserveerd"], 409);
        }
    }
    
    // Reservatie_ID toevoegen aan params
    $params[] = $reservationId;
    
    // Reservering bijwerken
    $stmt = $conn->prepare("UPDATE Reservatie SET " . implode(", ", $updateFields) . " WHERE Reservatie_ID = ?");
    $success = $stmt->execute($params);
    
    if ($success) {
        // Extra informatie bijwerken indien van toepassing
        
        // Voor studenten
        if (isset($data['OPO_id']) || isset($data['eigen_rekening'])) {
            // Controleren of er al een student kostenbewijs is
            $stmt = $conn->prepare("SELECT reservatie_id FROM kostenbewijzing_studenten WHERE reservatie_id = ?");
            $stmt->execute([$reservationId]);
            $hasStudentKosten = $stmt->rowCount() > 0;
            
            if ($hasStudentKosten) {
                // Bijwerken
                $kosten_update_fields = [];
                $kosten_params = [];
                
                if (isset($data['OPO_id'])) {
                    $kosten_update_fields[] = "OPO_id = ?";
                    $kosten_params[] = $data['OPO_id'];
                }
                
                if (isset($data['eigen_rekening'])) {
                    $kosten_update_fields[] = "eigen_rekening = ?";
                    $kosten_params[] = $data['eigen_rekening'] ? 1 : 0;
                }
                
                if (!empty($kosten_update_fields)) {
                    $kosten_params[] = $reservationId;
                    $stmt = $conn->prepare("UPDATE kostenbewijzing_studenten SET " . implode(", ", $kosten_update_fields) . " WHERE reservatie_id = ?");
                    $stmt->execute($kosten_params);
                }
            } else if (isset($data['OPO_id'])) {
                // Nieuw aanmaken
                $stmt = $conn->prepare("
                    INSERT INTO kostenbewijzing_studenten (reservatie_id, OPO_id, eigen_rekening)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $reservationId,
                    $data['OPO_id'],
                    $data['eigen_rekening'] ?? 0
                ]);
            }
        }
        
        // Voor onderzoekers
        if (isset($data['onderzoeksproject']) || isset($data['kostenpost'])) {
            // Controleren of er al een onderzoeker kostenbewijs is
            $stmt = $conn->prepare("SELECT reservatie_id FROM kostenbewijzing_onderzoekers WHERE reservatie_id = ?");
            $stmt->execute([$reservationId]);
            $hasOnderzoekerKosten = $stmt->rowCount() > 0;
            
            if ($hasOnderzoekerKosten) {
                // Bijwerken
                $kosten_update_fields = [];
                $kosten_params = [];
                
                if (isset($data['onderzoeksproject'])) {
                    $kosten_update_fields[] = "onderzoeksproject = ?";
                    $kosten_params[] = $data['onderzoeksproject'];
                }
                
                if (isset($data['kostenpost'])) {
                    $kosten_update_fields[] = "kostenpost = ?";
                    $kosten_params[] = $data['kostenpost'];
                }
                
                if (!empty($kosten_update_fields)) {
                    $kosten_params[] = $reservationId;
                    $stmt = $conn->prepare("UPDATE kostenbewijzing_onderzoekers SET " . implode(", ", $kosten_update_fields) . " WHERE reservatie_id = ?");
                    $stmt->execute($kosten_params);
                }
            } else if (isset($data['onderzoeksproject'])) {
                // Nieuw aanmaken
                $stmt = $conn->prepare("
                    INSERT INTO kostenbewijzing_onderzoekers (reservatie_id, onderzoeksproject, kostenpost)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $reservationId,
                    $data['onderzoeksproject'],
                    $data['kostenpost'] ?? null
                ]);
            }
        }
        
        // Voor software reservaties
        if (isset($data['ontwikkelstand'])) {
            // Controleren of er al een software reservatie is
            $stmt = $conn->prepare("SELECT reservatie_id FROM reservatie_software WHERE reservatie_id = ?");
            $stmt->execute([$reservationId]);
            $hasSoftwareInfo = $stmt->rowCount() > 0;
            
            if ($hasSoftwareInfo) {
                // Bijwerken
                $stmt = $conn->prepare("UPDATE reservatie_software SET ontwikkelstand = ? WHERE reservatie_id = ?");
                $stmt->execute([$data['ontwikkelstand'], $reservationId]);
            } else {
                // Nieuw aanmaken
                $stmt = $conn->prepare("
                    INSERT INTO reservatie_software (reservatie_id, ontwikkelstand)
                    VALUES (?, ?)
                ");
                $stmt->execute([$reservationId, $data['ontwikkelstand']]);
            }
        }
        
        sendResponse(["message" => "Reservering succesvol bijgewerkt"]);
    } else {
        sendResponse(["error" => "Reservering bijwerken mislukt"], 500);
    }
}

// Gebruiker's eigen reservering bijwerken
function updateUserReservation($userId, $reservationId) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Controleren of reservering bestaat en toebehoort aan gebruiker
    $stmt = $conn->prepare("SELECT * FROM Reservatie WHERE Reservatie_ID = ? AND User_ID = ?");
    $stmt->execute([$reservationId, $userId]);
    $currentReservation = $stmt->fetch();
    
    if (!$currentReservation) {
        sendResponse(["error" => "Reservering niet gevonden of geen toegang"], 404);
    }
    
    // Update velden opbouwen (beperkt voor normale gebruikers)
    $updateFields = [];
    $params = [];
    
    if (isset($data['Comment'])) {
        $updateFields[] = "Comment = ?";
        $params[] = $data['Comment'];
    }
    
    if (isset($data['filament_id'])) {
        $updateFields[] = "filament_id = ?";
        $params[] = $data['filament_id'];
    }
    
    // Start- en eindtijd kan alleen worden gewijzigd als de reservering nog niet is begonnen
    $now = new DateTime();
    $start = new DateTime($currentReservation['PRINT_START']);
    
    if ($start > $now) {
        if (isset($data['PRINT_START'])) {
            $updateFields[] = "PRINT_START = ?";
            $params[] = $data['PRINT_START'];
        }
        
        if (isset($data['PRINT_END'])) {
            $updateFields[] = "PRINT_END = ?";
            $params[] = $data['PRINT_END'];
        }
        
        // Controleren op overlappende reserveringen als start- of eindtijd is gewijzigd
        if (isset($data['PRINT_START']) || isset($data['PRINT_END'])) {
            $printStart = $data['PRINT_START'] ?? $currentReservation['PRINT_START'];
            $printEnd = $data['PRINT_END'] ?? $currentReservation['PRINT_END'];
            
            $stmt = $conn->prepare("
                SELECT Reservatie_ID FROM Reservatie 
                WHERE Printer_ID = ? AND Reservatie_ID != ? AND
                      ((PRINT_START <= ? AND PRINT_END >= ?) OR
                       (PRINT_START <= ? AND PRINT_END >= ?) OR
                       (PRINT_START >= ? AND PRINT_END <= ?))
            ");
            
            $stmt->execute([
                $currentReservation['Printer_ID'],
                $reservationId,
                $printStart, $printStart,
                $printEnd, $printEnd,
                $printStart, $printEnd
            ]);
            
            if ($stmt->rowCount() > 0) {
                sendResponse(["error" => "Tijdslot is al gereserveerd"], 409);
            }
            
            // Valideren dat de nieuwe starttijd voor de nieuwe eindtijd ligt
            $newStart = new DateTime($printStart);
            $newEnd = new DateTime($printEnd);
            
            if ($newStart >= $newEnd) {
                sendResponse(["error" => "Eindtijd moet na starttijd zijn"], 400);
            }
        }
    }
    
    if (empty($updateFields)) {
        sendResponse(["error" => "Geen velden om bij te werken"], 400);
    }
    
    // Reservatie_ID toevoegen aan params
    $params[] = $reservationId;
    
    // Reservering bijwerken
    $stmt = $conn->prepare("UPDATE Reservatie SET " . implode(", ", $updateFields) . " WHERE Reservatie_ID = ?");
    $success = $stmt->execute($params);
    
    if ($success) {
        // Extra informatie bijwerken indien van toepassing
        $userType = $_SESSION['Type'];
        
        // Voor studenten
        if ($userType == 'student' && (isset($data['OPO_id']) || isset($data['eigen_rekening']))) {
            // Controleren of er al een student kostenbewijs is
            $stmt = $conn->prepare("SELECT reservatie_id FROM kostenbewijzing_studenten WHERE reservatie_id = ?");
            $stmt->execute([$reservationId]);
            $hasStudentKosten = $stmt->rowCount() > 0;
            
            if ($hasStudentKosten) {
                // Bijwerken
                $kosten_update_fields = [];
                $kosten_params = [];
                
                if (isset($data['OPO_id'])) {
                    $kosten_update_fields[] = "OPO_id = ?";
                    $kosten_params[] = $data['OPO_id'];
                }
                
                if (isset($data['eigen_rekening'])) {
                    $kosten_update_fields[] = "eigen_rekening = ?";
                    $kosten_params[] = $data['eigen_rekening'] ? 1 : 0;
                }
                
                if (!empty($kosten_update_fields)) {
                    $kosten_params[] = $reservationId;
                    $stmt = $conn->prepare("UPDATE kostenbewijzing_studenten SET " . implode(", ", $kosten_update_fields) . " WHERE reservatie_id = ?");
                    $stmt->execute($kosten_params);
                }
            } else if (isset($data['OPO_id'])) {
                // Nieuw aanmaken
                $stmt = $conn->prepare("
                    INSERT INTO kostenbewijzing_studenten (reservatie_id, OPO_id, eigen_rekening)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $reservationId,
                    $data['OPO_id'],
                    $data['eigen_rekening'] ?? 0
                ]);
            }
        }
        
        // Voor onderzoekers
        if ($userType == 'onderzoeker' && (isset($data['onderzoeksproject']) || isset($data['kostenpost']))) {
            // Controleren of er al een onderzoeker kostenbewijs is
            $stmt = $conn->prepare("SELECT reservatie_id FROM kostenbewijzing_onderzoekers WHERE reservatie_id = ?");
            $stmt->execute([$reservationId]);
            $hasOnderzoekerKosten = $stmt->rowCount() > 0;
            
            if ($hasOnderzoekerKosten) {
                // Bijwerken
                $kosten_update_fields = [];
                $kosten_params = [];
                
                if (isset($data['onderzoeksproject'])) {
                    $kosten_update_fields[] = "onderzoeksproject = ?";
                    $kosten_params[] = $data['onderzoeksproject'];
                }
                
                if (isset($data['kostenpost'])) {
                    $kosten_update_fields[] = "kostenpost = ?";
                    $kosten_params[] = $data['kostenpost'];
                }
                
                if (!empty($kosten_update_fields)) {
                    $kosten_params[] = $reservationId;
                    $stmt = $conn->prepare("UPDATE kostenbewijzing_onderzoekers SET " . implode(", ", $kosten_update_fields) . " WHERE reservatie_id = ?");
                    $stmt->execute($kosten_params);
                }
            } else if (isset($data['onderzoeksproject'])) {
                // Nieuw aanmaken
                $stmt = $conn->prepare("
                    INSERT INTO kostenbewijzing_onderzoekers (reservatie_id, onderzoeksproject, kostenpost)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $reservationId,
                    $data['onderzoeksproject'],
                    $data['kostenpost'] ?? null
                ]);
            }
        }
        
        // Voor software reservaties
        if (isset($data['ontwikkelstand'])) {
            // Controleren of er al een software reservatie is
            $stmt = $conn->prepare("SELECT reservatie_id FROM reservatie_software WHERE reservatie_id = ?");
            $stmt->execute([$reservationId]);
            $hasSoftwareInfo = $stmt->rowCount() > 0;
            
            if ($hasSoftwareInfo) {
                // Bijwerken
                $stmt = $conn->prepare("UPDATE reservatie_software SET ontwikkelstand = ? WHERE reservatie_id = ?");
                $stmt->execute([$data['ontwikkelstand'], $reservationId]);
            } else {
                // Nieuw aanmaken
                $stmt = $conn->prepare("
                    INSERT INTO reservatie_software (reservatie_id, ontwikkelstand)
                    VALUES (?, ?)
                ");
                $stmt->execute([$reservationId, $data['ontwikkelstand']]);
            }
        }
        
        sendResponse(["message" => "Reservering succesvol bijgewerkt"]);
    } else {
        sendResponse(["error" => "Reservering bijwerken mislukt"], 500);
    }
}

// Reservering verwijderen (beheerder)
function deleteReservation($reservationId) {
    global $conn;
    
    // Controleren of reservering bestaat
    $stmt = $conn->prepare("SELECT * FROM Reservatie WHERE Reservatie_ID = ?");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        sendResponse(["error" => "Reservering niet gevonden"], 404);
    }
    
    // Gerelateerde records verwijderen
    try {
        $conn->beginTransaction();
        
        // Kostenbewijs studenten verwijderen
        $stmt = $conn->prepare("DELETE FROM kostenbewijzing_studenten WHERE reservatie_id = ?");
        $stmt->execute([$reservationId]);
        
        // Kostenbewijs onderzoekers verwijderen
        $stmt = $conn->prepare("DELETE FROM kostenbewijzing_onderzoekers WHERE reservatie_id = ?");
        $stmt->execute([$reservationId]);
        
        // Software reservatie verwijderen
        $stmt = $conn->prepare("DELETE FROM reservatie_software WHERE reservatie_id = ?");
        $stmt->execute([$reservationId]);
        
        // Reservering verwijderen
        $stmt = $conn->prepare("DELETE FROM Reservatie WHERE Reservatie_ID = ?");
        $stmt->execute([$reservationId]);
        
        $conn->commit();
        
        // Update printer status indien nodig
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM Reservatie 
            WHERE Printer_ID = ? AND PRINT_START <= NOW() AND PRINT_END >= NOW()
        ");
        $stmt->execute([$reservation['Printer_ID']]);
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            // Geen actieve reserveringen meer, zet printer terug op beschikbaar
            $stmt = $conn->prepare("
                UPDATE Printer 
                SET Status = 'beschikbaar', LAATSTE_STATUS_CHANGE = NOW() 
                WHERE Printer_ID = ?
            ");
            $stmt->execute([$reservation['Printer_ID']]);
        }
        
        sendResponse(["message" => "Reservering succesvol verwijderd"]);
    } catch (Exception $e) {
        $conn->rollBack();
        sendResponse(["error" => "Reservering verwijderen mislukt: " . $e->getMessage()], 500);
    }
}

// Gebruiker's eigen reservering verwijderen
function deleteUserReservation($userId, $reservationId) {
    global $conn;
    
    // Controleren of reservering bestaat en toebehoort aan gebruiker
    $stmt = $conn->prepare("SELECT * FROM Reservatie WHERE Reservatie_ID = ? AND User_ID = ?");
    $stmt->execute([$reservationId, $userId]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        sendResponse(["error" => "Reservering niet gevonden of geen toegang"], 404);
    }
    
    // Controleren of de reservering al is begonnen
    $now = new DateTime();
    $start = new DateTime($reservation['PRINT_START']);
    
    if ($start <= $now) {
        sendResponse(["error" => "Kan geen reservering annuleren die al is begonnen"], 400);
    }
    
    // Gerelateerde records verwijderen
    try {
        $conn->beginTransaction();
        
        // Kostenbewijs studenten verwijderen
        $stmt = $conn->prepare("DELETE FROM kostenbewijzing_studenten WHERE reservatie_id = ?");
        $stmt->execute([$reservationId]);
        
        // Kostenbewijs onderzoekers verwijderen
        $stmt = $conn->prepare("DELETE FROM kostenbewijzing_onderzoekers WHERE reservatie_id = ?");
        $stmt->execute([$reservationId]);
        
        // Software reservatie verwijderen
        $stmt = $conn->prepare("DELETE FROM reservatie_software WHERE reservatie_id = ?");
        $stmt->execute([$reservationId]);
        
        // Reservering verwijderen
        $stmt = $conn->prepare("DELETE FROM Reservatie WHERE Reservatie_ID = ?");
        $stmt->execute([$reservationId]);
        
        $conn->commit();
        
        sendResponse(["message" => "Reservering succesvol geannuleerd"]);
    } catch (Exception $e) {
        $conn->rollBack();
        sendResponse(["error" => "Reservering annuleren mislukt: " . $e->getMessage()], 500);
    }
}
?>