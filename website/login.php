<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$currentPage = 'login';
$pageTitle = 'Inloggen - 3D Printer Reserveringssysteem';

$error = '';

// Als gebruiker al is ingelogd, redirect naar home
if (isset($_SESSION['User_ID'])) {
    header('Location: index.php');
    exit;
}

// Redirect URL voor after login (indien van andere pagina doorverwezen)
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_password'])) {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $error = 'Vul je e-mailadres in.';
        } else {
            // Verstuur verzoek naar reset_password.php
            header("Location: reset_password.php?email=" . urlencode($email));
            exit;
        }
    } else {
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

                if ($user && password_verify($wachtwoord, $user['Wachtwoord'])) {
                    // Check of gebruiker een onderzoeker is en zo ja, of deze is goedgekeurd
                    if ($user['Type'] === 'onderzoeker') {
                        $approvalStmt = $conn->prepare("
                            SELECT Goedgekeurd 
                            FROM Onderzoeker_Goedkeuring 
                            WHERE User_ID = ?
                        ");
                        $approvalStmt->execute([$user['User_ID']]);
                        $approval = $approvalStmt->fetch();

                        if (!$approval || $approval['Goedgekeurd'] != 1) {
                            $error = 'Je account is nog niet goedgekeurd door een beheerder. Controleer je e-mail of neem contact op met de beheerder.';
                            include 'includes/header.php';
                            ?>
                            <div class="container">
                                <div class="row justify-content-center">
                                    <div class="col-md-8 col-lg-5">
                                        <div class="card shadow-sm">
                                            <div class="card-body p-4 p-md-5">
                                                <h1 class="text-center mb-4">Inloggen</h1>

                                                <div class="alert alert-warning">
                                                    <h5 class="alert-heading">Account in afwachting van goedkeuring</h5>
                                                    <p><?php echo $error; ?></p>
                                                    <hr>
                                                    <p class="mb-0">Zodra je account is goedgekeurd, ontvang je een bevestigingsmail.</p>
                                                </div>

                                                <div class="text-center mt-4">
                                                    <a href="index.php" class="btn btn-primary">Terug naar homepage</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                            include 'includes/footer.php';
                            exit;
                        }
                    }

                    // Inloggen succesvol, sessie instellingen
                    $_SESSION['User_ID'] = $user['User_ID'];
                    $_SESSION['Voornaam'] = $user['Voornaam'];
                    $_SESSION['Naam'] = $user['Naam'];
                    $_SESSION['Type'] = $user['Type'];

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
                            <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">Wachtwoord vergeten?</a>
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

<!-- Modal voor wachtwoord reset -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="forgotPasswordModalLabel">Wachtwoord vergeten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="login.php">
                    <div class="mb-3">
                        <label for="resetEmail" class="form-label">E-mailadres</label>
                        <input type="email" class="form-control" id="resetEmail" name="email" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary">Verstuur reset link</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>