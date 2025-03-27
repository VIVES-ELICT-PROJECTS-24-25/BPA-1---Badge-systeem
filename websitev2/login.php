<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$currentPage = 'login';
$pageTitle = 'Inloggen - 3D Printer Reserveringssysteem';

$error = '';

// DEBUG CODE - tijdelijk om gebruiker info te controleren
// echo "<pre>SESSION: "; print_r($_SESSION); echo "</pre>";

// Als gebruiker al is ingelogd, redirect naar home
if (isset($_SESSION['User_ID'])) {
    header('Location: index.php');
    exit;
}

// Redirect URL voor after login (indien van andere pagina doorverwezen)
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $wachtwoord = $_POST['wachtwoord'] ?? '';
    
    if (empty($email) || empty($wachtwoord)) {
        $error = 'Vul zowel je e-mailadres als wachtwoord in.';
    } else {
        try {
            // Gebruiker zoeken op e-mailadres
            $stmt = $conn->prepare("
                SELECT User_ID, Voornaam, Naam, Emailadres, Wachtwoord, Type 
                FROM User 
                WHERE Emailadres = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            // DEBUG LINE
            // echo "<pre>Database user data: "; print_r($user); echo "</pre>";
            
            if ($user && password_verify($wachtwoord, $user['Wachtwoord'])) {
                // Inloggen succesvol, sessie instellingen
                $_SESSION['User_ID'] = $user['User_ID'];
                $_SESSION['Voornaam'] = $user['Voornaam'];
                $_SESSION['Naam'] = $user['Naam'];
                $_SESSION['Type'] = $user['Type'];
                
                // Debug code om te zien wat er in de sessie wordt opgeslagen
                // echo "<pre>Sessie na login: "; print_r($_SESSION); echo "</pre>"; 
                // exit;
                
                // Update laatste aanmelding en actieve status
                $updateStmt = $conn->prepare("
                    UPDATE User 
                    SET LaatsteAanmelding = NOW(), HuidigActief = 1 
                    WHERE User_ID = ?
                ");
                $updateStmt->execute([$user['User_ID']]);
                
                // Doorverwijzen naar gewenste pagina
                header("Location: $redirect");
                exit;
            } else {
                $error = 'Ongeldige inloggegevens. Probeer het opnieuw.';
            }
        } catch (PDOException $e) {
            $error = 'Inloggen mislukt: ' . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h1 class="text-center mb-4">Inloggen</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="login.php<?php echo $redirect != 'index.php' ? '?redirect='.urlencode($redirect) : ''; ?>">
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mailadres</label>
                            <input type="email" class="form-control" id="email" name="email" required autofocus
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="mb-4">
                            <label for="wachtwoord" class="form-label">Wachtwoord</label>
                            <input type="password" class="form-control" id="wachtwoord" name="wachtwoord" required>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">Inloggen</button>
                        </div>
                        
                        <div class="text-center mb-3">
                            <a href="#" class="text-decoration-none">Wachtwoord vergeten?</a>
                        </div>
                        
                        <div class="text-center">
                            <p>Nog geen account? <a href="register.php">Registreren</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>