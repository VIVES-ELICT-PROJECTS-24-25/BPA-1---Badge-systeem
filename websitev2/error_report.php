<?php
// Tijdelijk fouten tonen voor debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>PHP Info en Error Rapport</h1>";
echo "<h2>PHP Versie: " . phpversion() . "</h2>";

// Test database verbinding
echo "<h2>Database Verbinding Test:</h2>";
try {
// Database configuration
$host = "ID462020_badgesysteem.db.webhosting.be";
$dbname = "ID462020_badgesysteem";
$username = "ID462020_badgesysteem";
$password = "kS8M607q97p82Gs079Ck";
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color:green'>Database verbinding succesvol!</p>";
    
    // Test query voor elke tabel
    $tables = ['User', 'Printer', 'Reservatie', 'Filament', 'OPOs', 'opleidingen', 'Lokalen'];
    
    echo "<h3>Tabellen controle:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        try {
            $stmt = $conn->query("SELECT 1 FROM $table LIMIT 1");
            echo "<li style='color:green'>Tabel $table bestaat en is toegankelijk</li>";
        } catch (PDOException $e) {
            echo "<li style='color:red'>Fout bij tabel $table: " . $e->getMessage() . "</li>";
        }
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Database verbinding mislukt: " . $e->getMessage() . "</p>";
}

// Controleer bestandsstructuur
echo "<h2>Bestandsstructuur:</h2>";

$critical_files = [
    'config.php',
    'index.php',
    'login.php',
    'register.php',
    'reservations.php',
    'api/config.php',
    'includes/header.php',
    'includes/footer.php',
    'admin/dashboard.php'
];

echo "<ul>";
foreach ($critical_files as $file) {
    if (file_exists($file)) {
        echo "<li style='color:green'>$file - Bestaat</li>";
    } else {
        echo "<li style='color:red'>$file - Ontbreekt!</li>";
    }
}
echo "</ul>";

// Toon gedetailleerde PHP info
echo "<h2>PHP Info:</h2>";
phpinfo();
?>