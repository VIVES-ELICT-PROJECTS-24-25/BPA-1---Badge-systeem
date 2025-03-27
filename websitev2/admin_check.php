<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

echo "<h1>Admin Check & Debugging Tool</h1>";

// 1. Toon huidige sessie-informatie
echo "<h2>Huidige sessiegegevens:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// 2. Controleer of gebruiker is ingelogd
if (!isset($_SESSION['User_ID'])) {
    echo "<div style='color:red'>Niet ingelogd! <a href='login.php'>Log in</a> en kom dan terug.</div>";
    exit;
}

// 3. Haal gebruikersinfo op uit database
try {
    $stmt = $conn->prepare("SELECT User_ID, Voornaam, Naam, Type FROM User WHERE User_ID = ?");
    $stmt->execute([$_SESSION['User_ID']]);
    $user = $stmt->fetch();
    
    echo "<h2>Gebruikersgegevens uit database:</h2>";
    echo "<pre>";
    print_r($user);
    echo "</pre>";
    
    // 4. Controleer of Type correct is
    if ($user['Type'] != 'beheerder') {
        echo "<div style='color:orange'>
            Let op: Je account staat niet als 'beheerder' ingesteld in de database.
            Huidige waarde: '{$user['Type']}' 
            <form method='post'>
                <input type='submit' name='make_admin' value='Maak mij beheerder'>
            </form>
        </div>";
    } else {
        echo "<div style='color:green'>Je account is ingesteld als 'beheerder' in de database.</div>";
    }
    
    // 5. Controleer sessie vs database
    if (!isset($_SESSION['Type']) || $_SESSION['Type'] != 'beheerder') {
        echo "<div style='color:red'>
            Je sessie heeft geen beheerdersstatus!
            <form method='post'>
                <input type='submit' name='fix_session' value='Fix mijn sessie'>
            </form>
        </div>";
    } else {
        echo "<div style='color:green'>Je sessie heeft beheerdersstatus.</div>";
    }
    
} catch (PDOException $e) {
    echo "<div style='color:red'>Database error: " . $e->getMessage() . "</div>";
}

// 6. Admin rechten forceren als erom gevraagd wordt
if (isset($_POST['make_admin'])) {
    try {
        $stmt = $conn->prepare("UPDATE User SET Type = 'beheerder' WHERE User_ID = ?");
        $stmt->execute([$_SESSION['User_ID']]);
        echo "<div style='color:green'>Account geüpdatet naar beheerder! Pagina vernieuwen...</div>";
        echo "<meta http-equiv='refresh' content='2'>";
    } catch (PDOException $e) {
        echo "<div style='color:red'>Update error: " . $e->getMessage() . "</div>";
    }
}

// 7. Sessie Type forceren als erom gevraagd wordt
if (isset($_POST['fix_session'])) {
    $_SESSION['Type'] = 'beheerder';
    echo "<div style='color:green'>Sessie geüpdatet! Pagina vernieuwen...</div>";
    echo "<meta http-equiv='refresh' content='2'>";
}

// 8. Admin links
echo "<h2>Admin links test:</h2>";
echo "<p>Klik op deze links om te testen of je nu toegang hebt:</p>";
echo "<ul>";
echo "<li><a href='admin/index.php' target='_blank'>Admin Dashboard</a></li>";
echo "<li><a href='admin/users.php' target='_blank'>Gebruikersbeheer</a></li>";
echo "<li><a href='admin/printers.php' target='_blank'>Printerbeheer</a></li>";
echo "</ul>";

// 9. Documentroot info en paden
echo "<h2>Serverinformatie:</h2>";
echo "<pre>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "</pre>";

// 10. Terug naar home
echo "<p><a href='index.php'>Terug naar homepage</a></p>";
?>