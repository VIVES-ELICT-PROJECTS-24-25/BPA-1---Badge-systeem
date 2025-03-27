<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

// Zorgen dat alleen beheerders toegang hebben
ensureAdmin();

// HTTP methode en route ophalen
$method = $_SERVER['REQUEST_METHOD'];
$route = isset($_GET['route']) ? $_GET['route'] : '';

// Router
switch ($method) {
    case 'GET':
        if ($route == 'dashboard') {
            getDashboardStats();
        } elseif ($route == 'printer-usage') {
            getPrinterUsage();
        } elseif ($route == 'filament-usage') {
            getFilamentUsage();
        } elseif ($route == 'user-stats') {
            getUserStats();
        } elseif ($route == 'time-distribution') {
            getTimeDistribution();
        } else {
            sendResponse(["error" => "Route niet gevonden"], 404);
        }
        break;
    default:
        sendResponse(["error" => "Methode niet toegestaan"], 405);
}

// Dashboard statistieken ophalen
function getDashboardStats() {
    global $conn;
    
    // Totaal aantal printers
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM Printer");
    $totalPrinters = $stmt->fetch()['total'];
    
    // Aantal beschikbare printers
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM Printer WHERE Status = 'beschikbaar'");
    $availablePrinters = $stmt->fetch()['total'];
    
    // Aantal printers in onderhoud
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM Printer WHERE Status = 'onderhoud'");
    $maintenancePrinters = $stmt->fetch()['total'];
    
    // Aantal printers in gebruik
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM Printer WHERE Status = 'in_gebruik'");
    $inUsePrinters = $stmt->fetch()['total'];
    
    // Totaal aantal gebruikers
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM User");
    $totalUsers = $stmt->fetch()['total'];
    
    // Aantal actieve gebruikers
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM User WHERE HuidigActief = 1");
    $activeUsers = $stmt->fetch()['total'];
    
    // Gebruikers per type
    $stmt = $conn->query("SELECT Type, COUNT(*) AS count FROM User GROUP BY Type");
    $userTypes = $stmt->fetchAll();
    
    // Totaal aantal reserveringen
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM Reservatie");
    $totalReservations = $stmt->fetch()['total'];
    
    // Reserveringen vandaag
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total FROM Reservatie 
        WHERE DATE(DATE_TIME_RESERVATIE) = CURDATE()
    ");
    $stmt->execute();
    $todayReservations = $stmt->fetch()['total'];
    
    // Aankomende reserveringen
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total FROM Reservatie 
        WHERE PRINT_START > NOW()
    ");
    $stmt->execute();
    $upcomingReservations = $stmt->fetch()['total'];
    
    // Actieve reserveringen
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total FROM Reservatie 
        WHERE PRINT_START <= NOW() AND PRINT_END >= NOW()
    ");
    $stmt->execute();
    $activeReservations = $stmt->fetch()['total'];
    
    sendResponse([
        "printers" => [
            "total" => $totalPrinters,
            "available" => $availablePrinters,
            "maintenance" => $maintenancePrinters,
            "in_use" => $inUsePrinters
        ],
        "users" => [
            "total" => $totalUsers,
            "active" => $activeUsers,
            "by_type" => $userTypes
        ],
        "reservations" => [
            "total" => $totalReservations,
            "today" => $todayReservations,
            "upcoming" => $upcomingReservations,
            "active" => $activeReservations
        ]
    ]);
}

// Printer gebruiksstatistieken ophalen
function getPrinterUsage() {
    global $conn;
    
    // Meest gebruikte printers
    $stmt = $conn->query("
        SELECT p.Printer_ID, p.Versie_Toestel, COUNT(r.Reservatie_ID) AS reservation_count
        FROM Printer p
        LEFT JOIN Reservatie r ON p.Printer_ID = r.Printer_ID
        GROUP BY p.Printer_ID
        ORDER BY reservation_count DESC
    ");
    
    $printerUsage = $stmt->fetchAll();
    
    // Gemiddelde duur van printopdrachten per printer
    $stmt = $conn->query("
        SELECT p.Printer_ID, p.Versie_Toestel, 
               AVG(TIMESTAMPDIFF(MINUTE, r.PRINT_START, r.PRINT_END)) / 60 AS avg_duration_hours
        FROM Printer p
        JOIN Reservatie r ON p.Printer_ID = r.Printer_ID
        GROUP BY p.Printer_ID
        ORDER BY avg_duration_hours DESC
    ");
    
    $printerDuration = $stmt->fetchAll();
    
    // Totale gebruiksduur per printer
    $stmt = $conn->query("
        SELECT p.Printer_ID, p.Versie_Toestel, 
               SUM(TIMESTAMPDIFF(MINUTE, r.PRINT_START, r.PRINT_END)) / 60 AS total_hours
        FROM Printer p
        JOIN Reservatie r ON p.Printer_ID = r.Printer_ID
        GROUP BY p.Printer_ID
        ORDER BY total_hours DESC
    ");
    
    $printerTotalHours = $stmt->fetchAll();
    
    sendResponse([
        "most_used" => $printerUsage,
        "avg_duration" => $printerDuration,
        "total_hours" => $printerTotalHours
    ]);
}

// Filament gebruiksstatistieken ophalen
function getFilamentUsage() {
    global $conn;
    
    // Filament gebruik per type
    $stmt = $conn->query("
        SELECT f.Type, COUNT(r.Reservatie_ID) AS usage_count, SUM(r.verbruik) AS total_usage
        FROM Filament f
        JOIN Reservatie r ON f.id = r.filament_id
        GROUP BY f.Type
        ORDER BY usage_count DESC
    ");
    
    $filamentByType = $stmt->fetchAll();
    
    // Filament gebruik per kleur
    $stmt = $conn->query("
        SELECT f.Kleur, COUNT(r.Reservatie_ID) AS usage_count, SUM(r.verbruik) AS total_usage
        FROM Filament f
        JOIN Reservatie r ON f.id = r.filament_id
        GROUP BY f.Kleur
        ORDER BY usage_count DESC
    ");
    
    $filamentByColor = $stmt->fetchAll();
    
    // Filament gebruik per type en kleur
    $stmt = $conn->query("
        SELECT f.Type, f.Kleur, COUNT(r.Reservatie_ID) AS usage_count, SUM(r.verbruik) AS total_usage
        FROM Filament f
        JOIN Reservatie r ON f.id = r.filament_id
        GROUP BY f.Type, f.Kleur
        ORDER BY usage_count DESC
    ");
    
    $filamentByTypeAndColor = $stmt->fetchAll();
    
    // Filament verbruik over tijd (per maand)
    $stmt = $conn->query("
        SELECT DATE_FORMAT(r.PRINT_START, '%Y-%m') AS month, 
               SUM(r.verbruik) AS total_usage
        FROM Reservatie r
        WHERE r.filament_id IS NOT NULL AND r.verbruik IS NOT NULL
        GROUP BY month
        ORDER BY month
    ");
    
    $filamentUsageOverTime = $stmt->fetchAll();
    
    sendResponse([
        "by_type" => $filamentByType,
        "by_color" => $filamentByColor,
        "by_type_and_color" => $filamentByTypeAndColor,
        "over_time" => $filamentUsageOverTime
    ]);
}

// Gebruikersstatistieken ophalen
function getUserStats() {
    global $conn;
    
    // Gebruikers met meeste reserveringen
    $stmt = $conn->query("
        SELECT u.User_ID, u.Voornaam, u.Naam, u.Type, COUNT(r.Reservatie_ID) AS reservation_count
        FROM User u
        LEFT JOIN Reservatie r ON u.User_ID = r.User_ID
        GROUP BY u.User_ID
        ORDER BY reservation_count DESC
        LIMIT 10
    ");
    
    $mostActiveUsers = $stmt->fetchAll();
    
    // Reserveringen per gebruikerstype
    $stmt = $conn->query("
        SELECT u.Type, COUNT(r.Reservatie_ID) AS reservation_count
        FROM User u
        JOIN Reservatie r ON u.User_ID = r.User_ID
        GROUP BY u.Type
        ORDER BY reservation_count DESC
    ");
    
    $reservationsByUserType = $stmt->fetchAll();
    
    // Reserveringen per opleiding (voor studenten)
    $stmt = $conn->query("
        SELECT o.naam AS opleiding_naam, COUNT(r.Reservatie_ID) AS reservation_count
        FROM opleidingen o
        JOIN Vives v ON o.id = v.opleiding_id
        JOIN Reservatie r ON v.User_ID = r.User_ID
        GROUP BY o.id
        ORDER BY reservation_count DESC
    ");
    
    $reservationsByOpleiding = $stmt->fetchAll();
    
    // OPO's met meeste reserveringen
    $stmt = $conn->query("
        SELECT o.naam AS opo_naam, COUNT(ks.reservatie_id) AS reservation_count
        FROM OPOs o
        JOIN kostenbewijzing_studenten ks ON o.id = ks.OPO_id
        GROUP BY o.id
        ORDER BY reservation_count DESC
    ");
    
    $reservationsByOPO = $stmt->fetchAll();
    
    // Onderzoeksprojecten met meeste reserveringen
    $stmt = $conn->query("
        SELECT ko.onderzoeksproject, COUNT(ko.reservatie_id) AS reservation_count
        FROM kostenbewijzing_onderzoekers ko
        GROUP BY ko.onderzoeksproject
        ORDER BY reservation_count DESC
    ");
    
    $reservationsByProject = $stmt->fetchAll();
    
    sendResponse([
        "most_active_users" => $mostActiveUsers,
        "by_user_type" => $reservationsByUserType,
        "by_opleiding" => $reservationsByOpleiding,
        "by_opo" => $reservationsByOPO,
        "by_project" => $reservationsByProject
    ]);
}

// Tijdsverdeling van reserveringen ophalen
function getTimeDistribution() {
    global $conn;
    
    // Reserveringen per dag van de week
    $stmt = $conn->query("
        SELECT DAYOFWEEK(PRINT_START) AS day_of_week, COUNT(*) AS count
        FROM Reservatie
        GROUP BY day_of_week
        ORDER BY day_of_week
    ");
    
    $reservationsByDayOfWeek = $stmt->fetchAll();
    
    // Reserveringen per uur van de dag
    $stmt = $conn->query("
        SELECT HOUR(PRINT_START) AS hour_of_day, COUNT(*) AS count
        FROM Reservatie
        GROUP BY hour_of_day
        ORDER BY hour_of_day
    ");
    
    $reservationsByHour = $stmt->fetchAll();
    
    // Reserveringen per maand
    $stmt = $conn->query("
        SELECT MONTH(PRINT_START) AS month, COUNT(*) AS count
        FROM Reservatie
        GROUP BY month
        ORDER BY month
    ");
    
    $reservationsByMonth = $stmt->fetchAll();
    
    // Gemiddelde duur van reserveringen per dag van de week
    $stmt = $conn->query("
        SELECT DAYOFWEEK(PRINT_START) AS day_of_week, 
               AVG(TIMESTAMPDIFF(MINUTE, PRINT_START, PRINT_END)) / 60 AS avg_duration_hours
        FROM Reservatie
        GROUP BY day_of_week
        ORDER BY day_of_week
    ");
    
    $durationByDayOfWeek = $stmt->fetchAll();
    
    // Gemiddelde duur van reserveringen per uur van de dag
    $stmt = $conn->query("
        SELECT HOUR(PRINT_START) AS hour_of_day, 
               AVG(TIMESTAMPDIFF(MINUTE, PRINT_START, PRINT_END)) / 60 AS avg_duration_hours
        FROM Reservatie
        GROUP BY hour_of_day
        ORDER BY hour_of_day
    ");
    
    $durationByHour = $stmt->fetchAll();
    
    // Gebruikspercentage per tijdslot
    $stmt = $conn->query("
        SELECT 
            HOUR(PRINT_START) AS hour_of_day,
            DAYOFWEEK(PRINT_START) AS day_of_week,
            COUNT(*) AS reservation_count
        FROM Reservatie
        GROUP BY day_of_week, hour_of_day
        ORDER BY day_of_week, hour_of_day
    ");
    
    $usageByTimeSlot = $stmt->fetchAll();
    
    sendResponse([
        "by_day_of_week" => $reservationsByDayOfWeek,
        "by_hour" => $reservationsByHour,
        "by_month" => $reservationsByMonth,
        "duration_by_day" => $durationByDayOfWeek,
        "duration_by_hour" => $durationByHour,
        "by_time_slot" => $usageByTimeSlot
    ]);
}
?>