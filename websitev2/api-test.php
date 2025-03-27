<?php
header('Content-Type: application/json');

// Test of API bestanden bestaan
$apiFiles = [
    'config.php',
    'users.php',
    'printers.php',
    'reservations.php',
    'filaments.php',
    'stats.php',
    'opos.php',
    'opleidingen.php',
    'lokalen.php'
];

$results = [];
foreach ($apiFiles as $file) {
    $path = 'api/' . $file;
    $results[$file] = file_exists($path) ? 'Bestand gevonden' : 'Bestand ontbreekt';
}

// Test database verbinding
try {
    $host = "localhost";
    $password = "kS8M607q97p82Gs079Ck";
    $username = "ID462020_badgesysteem";
    $dbname = "ID462020_badgesysteem";
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $results['database'] = 'Verbinding succesvol';
    
    // Test een eenvoudige query om te zien of tabellen bestaan
    $tables = ['User', 'Printer', 'Reservatie', 'Filament', 'OPOs', 'opleidingen', 'Lokalen'];
    $tableResults = [];
    
    foreach ($tables as $table) {
        try {
            $stmt = $conn->query("SELECT 1 FROM $table LIMIT 1");
            $tableResults[$table] = $stmt ? 'Tabel bestaat' : 'Tabel fout';
        } catch (PDOException $e) {
            $tableResults[$table] = 'Tabel niet gevonden: ' . $e->getMessage();
        }
    }
    
    $results['tables'] = $tableResults;
    
} catch (PDOException $e) {
    $results['database'] = 'Verbinding mislukt: ' . $e->getMessage();
}

echo json_encode(['status' => 'Test voltooid', 'results' => $results], JSON_PRETTY_PRINT);
?>