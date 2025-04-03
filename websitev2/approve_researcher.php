<?php
// Belangrijk: geen output voor eventuele session_start of header wijzigingen
// Foutrapportage inschakelen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Controleer of config.php bestaat
if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    die("Config bestand niet gevonden");
}

?><!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onderzoeker Goedkeuren</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Onderzoeker Goedkeuren</h2>
                        
                        <?php
                        // Nu pas beginnen met de inhoud verwerking
                        try {
                            // Parameters direct uit $_GET ophalen
                            $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
                            $token = isset($_GET['token']) ? trim($_GET['token']) : '';
                            
                            // Debug info
                            echo "<div class='alert alert-info'>";
                            echo "Verwerking gestart voor User ID: " . $user_id . "<br>";
                            echo "Token lengte: " . strlen($token) . " tekens<br>";
                            echo "</div>";
                            
                            // Validatie
                            if ($user_id <= 0) {
                                throw new Exception("Ongeldige gebruikers-ID in URL");
                            }
                            
                            if (empty($token)) {
                                throw new Exception("Geen token gevonden in URL");
                            }
                            
                            // Zoek het record op basis van user_id
                            $stmt = $conn->prepare("SELECT * FROM Onderzoeker_Goedkeuring WHERE User_ID = ?");
                            $stmt->execute([$user_id]);
                            
                            if ($stmt->rowCount() === 0) {
                                throw new Exception("Geen goedkeuringsrecord gevonden voor gebruiker " . $user_id);
                            }
                            
                            $record = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Bekijk of de gebruiker al is goedgekeurd
                            if ($record['Goedgekeurd'] == 1) {
                                echo "<div class='alert alert-warning'>";
                                echo "<h4 class='alert-heading'>Al goedgekeurd</h4>";
                                echo "<p>Deze onderzoeker is al goedgekeurd en kan inloggen op het systeem.</p>";
                                echo "</div>";
                                
                                echo "<div class='text-center mt-4'>";
                                echo "<a href='index.php' class='btn btn-primary'>Terug naar homepage</a>";
                                echo "</div>";
                            } 
                            // Controleer de token en keur goed indien correct
                            else if (isset($record['Goedkeuringstoken']) && $record['Goedkeuringstoken'] === $token) {
                                // Token klopt, goedkeuren
                                $updateStmt = $conn->prepare("
                                    UPDATE Onderzoeker_Goedkeuring 
                                    SET Goedgekeurd = 1, GoedkeuringsDatum = NOW() 
                                    WHERE User_ID = ?
                                ");
                                $updateStmt->execute([$user_id]);
                                
                                echo "<div class='alert alert-success'>";
                                echo "<h4 class='alert-heading'>Goedkeuring succesvol!</h4>";
                                echo "<p>De onderzoeker is succesvol goedgekeurd en kan nu inloggen op het systeem.</p>";
                                echo "</div>";
                                
                                // Gebruikersgegevens ophalen voor e-mail
                                $userStmt = $conn->prepare("SELECT Voornaam, Naam, Emailadres FROM User WHERE User_ID = ?");
                                $userStmt->execute([$user_id]);
                                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                                
                                echo "<div class='alert alert-info'>";
                                echo "<p>Gebruiker: " . htmlspecialchars($user['Voornaam'] . ' ' . $user['Naam']) . "</p>";
                                echo "<p>Email: " . htmlspecialchars($user['Emailadres']) . "</p>";
                                echo "</div>";
                                
                                echo "<div class='text-center mt-4'>";
                                echo "<a href='index.php' class='btn btn-primary'>Terug naar homepage</a>";
                                echo "</div>";
                                
                                // E-mail verzenden zou hier komen, maar houden we apart voor troubleshooting
                            } else {
                                // Token komt niet overeen
                                echo "<div class='alert alert-danger'>";
                                echo "<h4 class='alert-heading'>Ongeldige token</h4>";
                                echo "<p>De beveiligingstoken in de URL komt niet overeen met de token in de database.</p>";
                                echo "</div>";
                                
                                echo "<div class='text-center mt-4'>";
                                echo "<a href='index.php' class='btn btn-primary'>Terug naar homepage</a>";
                                echo "</div>";
                            }
                            
                        } catch (Exception $e) {
                            echo "<div class='alert alert-danger'>";
                            echo "<h4 class='alert-heading'>Fout opgetreden</h4>";
                            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                            echo "</div>";
                            
                            echo "<div class='text-center mt-4'>";
                            echo "<a href='index.php' class='btn btn-primary'>Terug naar homepage</a>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>