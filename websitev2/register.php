<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$currentPage = 'register';
$pageTitle = 'Registreren - 3D Printer Reserveringssysteem';

$error = '';
$success = '';

// Als gebruiker al is ingelogd, redirect naar home
if (isset($_SESSION['User_ID'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voornaam = trim($_POST['voornaam'] ?? '');
    $naam = trim($_POST['naam'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefoon = trim($_POST['telefoon'] ?? '');
    $wachtwoord = $_POST['wachtwoord'] ?? '';
    $bevestig_wachtwoord = $_POST['bevestig_wachtwoord'] ?? '';
    $type = $_POST['type'] ?? 'student'; // Default naar student
    
    // Validatie van verplichte velden
    if (empty($voornaam) || empty($naam) || empty($email) || empty($wachtwoord)) {
        $error = 'Alle verplichte velden moeten ingevuld zijn.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Voer een geldig e-mailadres in.';
    } elseif ($wachtwoord !== $bevestig_wachtwoord) {
        $error = 'Wachtwoorden komen niet overeen.';
    } elseif (strlen($wachtwoord) < 6) {
        $error = 'Wachtwoord moet minimaal 6 tekens bevatten.';
    } else {
        // Controleer of e-mail al bestaat
        $stmt = $conn->prepare("SELECT User_ID FROM User WHERE Emailadres = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'Dit e-mailadres is al in gebruik. Probeer in te loggen of gebruik een ander e-mailadres.';
        } else {
            try {
                // Genereer een nieuw User_ID (auto increment simuleren)
                $stmtMaxId = $conn->query("SELECT MAX(User_ID) as maxId FROM User");
                $result = $stmtMaxId->fetch();
                $newUserId = ($result['maxId'] ?? 0) + 1;
                
                // Wachtwoord hashen
                $hashedPassword = password_hash($wachtwoord, PASSWORD_DEFAULT);
                
                // Gebruiker aanmaken
                $stmt = $conn->prepare("
                    INSERT INTO User (User_ID, Voornaam, Naam, Emailadres, Telefoon, Wachtwoord, 
                                    Type, AanmaakAccount, LaatsteAanmelding, HuidigActief)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1)
                ");
                
                $stmt->execute([
                    $newUserId,
                    $voornaam,
                    $naam,
                    $email,
                    $telefoon,
                    $hashedPassword,
                    $type
                ]);
                
                $success = 'Registratie succesvol! Je kunt nu inloggen.';
                
                // Redirect naar inlogpagina na 3 seconden
                header("refresh:3;url=login.php");
            } catch (PDOException $e) {
                $error = 'Registratie mislukt: ' . $e->getMessage();
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h1 class="text-center mb-4">Registreren</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                            <div class="mt-2">
                                <small>Je wordt doorgestuurd naar de inlogpagina...</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="post" action="register.php">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="voornaam" class="form-label">Voornaam *</label>
                                    <input type="text" class="form-control" id="voornaam" name="voornaam" required 
                                           value="<?php echo isset($_POST['voornaam']) ? htmlspecialchars($_POST['voornaam']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="naam" class="form-label">Achternaam *</label>
                                    <input type="text" class="form-control" id="naam" name="naam" required
                                           value="<?php echo isset($_POST['naam']) ? htmlspecialchars($_POST['naam']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mailadres *</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                <div class="form-text">Dit e-mailadres wordt gebruikt om in te loggen</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="telefoon" class="form-label">Telefoonnummer</label>
                                <input type="text" class="form-control" id="telefoon" name="telefoon"
                                       value="<?php echo isset($_POST['telefoon']) ? htmlspecialchars($_POST['telefoon']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="type" class="form-label">Type gebruiker *</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="student" <?php echo (isset($_POST['type']) && $_POST['type'] == 'student') ? 'selected' : ''; ?>>Student</option>
                                    <option value="onderzoeker" <?php echo (isset($_POST['type']) && $_POST['type'] == 'onderzoeker') ? 'selected' : ''; ?>>Onderzoeker</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="wachtwoord" class="form-label">Wachtwoord *</label>
                                <input type="password" class="form-control" id="wachtwoord" name="wachtwoord" required minlength="6">
                                <div class="form-text">Minimaal 6 tekens</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="bevestig_wachtwoord" class="form-label">Bevestig wachtwoord *</label>
                                <input type="password" class="form-control" id="bevestig_wachtwoord" name="bevestig_wachtwoord" required minlength="6">
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Registreren</button>
                            </div>
                            
                            <div class="text-center mt-4">
                                <p>Heb je al een account? <a href="login.php">Inloggen</a></p>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>