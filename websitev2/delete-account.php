<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

// Controleer of gebruiker is ingelogd
if (!isset($_SESSION['User_ID'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['User_ID'];
$error = '';

// Alleen verwerken als het een POST request is
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password_confirm'] ?? '';
    
    if (empty($password)) {
        $error = 'Wachtwoord is verplicht om je account te verwijderen.';
    } else {
        // Controleer wachtwoord
        $stmt = $conn->prepare("SELECT Wachtwoord FROM User WHERE User_ID = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['Wachtwoord'])) {
            $error = 'Wachtwoord is onjuist.';
        } else {
            try {
                // Begin een transactie
                $conn->beginTransaction();
                
                // Update status van actieve reserveringen naar geannuleerd
                $stmt = $conn->prepare("
                    UPDATE Reservatie
                    SET Status = 'geannuleerd', DATE_TIME_Annulatie = NOW()
                    WHERE User_ID = ? AND Status NOT IN ('voltooid', 'geannuleerd')
                ");
                $stmt->execute([$userId]);
                
                // Update printers die gereserveerd waren
                $stmt = $conn->prepare("
                    UPDATE Printer p
                    JOIN Reservatie r ON p.Printer_ID = r.Printer_ID
                    SET p.Status = 'beschikbaar'
                    WHERE r.User_ID = ? AND r.Status = 'geannuleerd' AND p.Status = 'in_gebruik'
                ");
                $stmt->execute([$userId]);
                
                // Verwijder gebruiker
                $stmt = $conn->prepare("DELETE FROM User WHERE User_ID = ?");
                $stmt->execute([$userId]);
                
                // Commit de transactie
                $conn->commit();
                
                // Sessie beëindigen
                session_unset();
                session_destroy();
                
                // Redirect naar homepage met bericht
                header('Location: index.php?deleted=true');
                exit;
                
            } catch (PDOException $e) {
                // Rollback bij fouten
                $conn->rollBack();
                $error = 'Er is een fout opgetreden bij het verwijderen van je account: ' . $e->getMessage();
            }
        }
    }
}

// Als er een fout is, toon dan de foutmelding en ga terug naar profiel
if ($error) {
    $_SESSION['account_delete_error'] = $error;
    header('Location: profile.php#danger-zone');
    exit;
}
?>