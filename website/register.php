<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'vendor/autoload.php'; // Zorg ervoor dat dit pad correct is

$currentPage = 'register';
$pageTitle = 'Registreren - 3D Printer Reserveringssysteem';

$error = '';
$success = '';

// Check if opleidingen need to be loaded
$query = "SELECT id, naam FROM opleidingen ORDER BY naam";
$stmt = $conn->prepare($query);
$stmt->execute();
$opleidingen = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    $vives_id = trim($_POST['vives_id'] ?? '');
    $opleiding_id = ($_POST['opleiding_id'] ?? '');
    
    // Determine user type based on email domain
    if (str_ends_with($email, '@student.vives.be')) {
        $type = 'student';
    } elseif (str_ends_with($email, '@vives.be')) {
        $type = 'docent';
    } else {
        $type = 'onderzoeker';
    }
    
    // Validatie van verplichte velden
    if (empty($voornaam) || empty($naam) || empty($email) || empty($wachtwoord)) {
        $error = 'Alle verplichte velden moeten ingevuld zijn.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Voer een geldig e-mailadres in.';
    } elseif ($wachtwoord !== $bevestig_wachtwoord) {
        $error = 'Wachtwoorden komen niet overeen.';
    } elseif (strlen($wachtwoord) < 6) {
        $error = 'Wachtwoord moet minimaal 6 tekens bevatten.';
    } elseif (($type == 'student' || $type == 'docent') && empty($vives_id)) {
        $error = 'VIVES ID is verplicht voor studenten en docenten.';
    } elseif ($type == 'student' && empty($opleiding_id)) {
        $error = 'Opleiding is verplicht voor studenten.';
    } elseif (($type == 'student' || $type == 'docent') && !preg_match('/^[RU]\d{7}$/', $vives_id)) {
        $error = 'VIVES ID moet het formaat R1234567 of U1234567 hebben.';
    } else {
        // Controleer of e-mail al bestaat
        $stmt = $conn->prepare("SELECT User_ID FROM User WHERE Emailadres = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'Dit e-mailadres is al in gebruik. Probeer in te loggen of gebruik een ander e-mailadres.';
        } else {
            try {
                // Start transaction
                $conn->beginTransaction();
                
                // Genereer een nieuw User_ID (auto increment simuleren)
                $stmtMaxId = $conn->query("SELECT MAX(User_ID) as maxId FROM User");
                $result = $stmtMaxId->fetch();
                $newUserId = ($result['maxId'] ?? 0) + 1;
                
                // Wachtwoord hashen
                $hashedPassword = password_hash($wachtwoord, PASSWORD_DEFAULT);
                
                // Gebruiker aanmaken (met HulpNodig standaard op 1)
                $stmt = $conn->prepare("
                    INSERT INTO User (User_ID, Voornaam, Naam, Emailadres, Telefoon, Wachtwoord, 
                                    Type, AanmaakAccount, LaatsteAanmelding, HuidigActief, HulpNodig)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1, 1)
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
                
                // Als het een student of docent is, voeg toe aan Vives tabel
                if ($type == 'student' || $type == 'docent') {
                    $vivesType = ($type == 'student') ? 'student' : 'medewerker';
                    $vivesOpleidingId = ($type == 'student') ? $opleiding_id : null;
                    
                    $stmt = $conn->prepare("
                        INSERT INTO Vives (User_ID, Voornaam, Vives_id, opleiding_id, Type)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $newUserId,
                        $voornaam,
                        $vives_id,
                        $vivesOpleidingId,
                        $vivesType
                    ]);
                }
                
                // Als het een onderzoeker is, genereer goedkeuringstoken en stuur e-mail
                if ($type == 'onderzoeker') {
                    // Genereer een veilige token
                    $approvalToken = bin2hex(random_bytes(32));
                    
                    // Voeg record toe aan nieuwe tabel voor goedkeuring met token
                    $stmt = $conn->prepare("
                        INSERT INTO Onderzoeker_Goedkeuring (User_ID, Goedgekeurd, AanvraagDatum, Goedkeuringstoken)
                        VALUES (?, 0, NOW(), ?)
                    ");
                    
                    $stmt->execute([$newUserId, $approvalToken]);
                    
                    // Bouw de goedkeuringslink
                    $approvalLink = 'https://3dprintersmaaklabvives.be/28_03/adminv2/approve_researcher.php?user_id=' . $newUserId . '&token=' . $approvalToken;
                    
                    // PHPMailer implementatie voor onderzoeker registratie e-mail
                    try {
                        $mail = new PHPMailer(true);
                        
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host       = 'smtp-auth.mailprotect.be';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'reservaties@3dprintersmaaklabvives.be';
                        $mail->Password   = '9ke53d3w2ZP64ik76qHe';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;
                        $mail->CharSet    = 'UTF-8';
                        
                        // Debugging opties (tijdelijk aanzetten om problemen te diagnosticeren)
                        $mail->SMTPDebug = 0; // 0 = uit, 1 = client berichten, 2 = client/server berichten
                        
                        // Recipients
                        $mail->setFrom('reservaties@3dprintersmaaklabvives.be', '3D Printer Reserveringssysteem');
                        $mail->addAddress('inschrijvingen@3dprintersmaaklabvives.be', 'VIVES 3D Printer Admin'); // Admin e-mail
                        
                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Nieuwe onderzoeker registratie: goedkeuring vereist';
                        
                        $message = '
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background-color: #00447c; color: white; padding: 15px; text-align: center; }
                                .content { padding: 20px; border: 1px solid #ddd; border-top: none; }
                                .user-info { background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #00447c; }
                                .button { display: inline-block; background-color: #28a745; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; font-weight: bold; }
                                .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
                            </style>
                        </head>
                        <body>
                            <div class="container">
                                <div class="header">
                                    <h2>3D Printer Reserveringssysteem VIVES</h2>
                                </div>
                                <div class="content">
                                    <h3>Nieuwe onderzoeker registratie</h3>
                                    <p>Er heeft zich een nieuwe gebruiker geregistreerd als onderzoeker. Deze gebruiker vereist uw goedkeuring voordat toegang wordt verleend tot het systeem.</p>
                                    
                                    <div class="user-info">
                                        <h4>Gebruikersgegevens:</h4>
                                        <p><strong>Naam:</strong> ' . htmlspecialchars($voornaam . ' ' . $naam) . '</p>
                                        <p><strong>E-mail:</strong> ' . htmlspecialchars($email) . '</p>
                                        <p><strong>Telefoon:</strong> ' . htmlspecialchars($telefoon) . '</p>
                                        <p><strong>Gebruikers-ID:</strong> ' . $newUserId . '</p>
                                        <p><strong>Registratiedatum:</strong> ' . date('d-m-Y H:i') . '</p>
                                    </div>
                                    
                                    <p>Klik op onderstaande knop om deze gebruiker goed te keuren:</p>
                                    
                                    <p style="text-align: center; margin: 30px 0;">
                                        <a href="' . $approvalLink . '" class="button">Goedkeuren</a>
                                    </p>
                                    
                                    <p>Of kopieer en plak deze link in uw browser:</p>
                                    <p style="word-break: break-all; background-color: #f5f5f5; padding: 10px; font-size: 12px;">
                                        ' . $approvalLink . '
                                    </p>
                                    
                                    <p>U kunt deze aanvraag ook goedkeuren via het administratiepaneel.</p>
                                </div>
                                <div class="footer">
                                    <p>Dit is een automatisch gegenereerd bericht van het 3D Printer Reserveringssysteem.</p>
                                    <p>&copy; ' . date('Y') . ' VIVES Hogeschool</p>
                                </div>
                            </div>
                        </body>
                        </html>';
                        
                        $mail->Body = $message;
                        $mail->AltBody = 'Nieuwe onderzoeker registratie: ' . $voornaam . ' ' . $naam . ' (' . $email . '). Goedkeuren via: ' . $approvalLink;
                        
                        $mail->send();
                    } catch (Exception $e) {
                        // Loggen van de fout maar niet de transactie laten mislukken
                        error_log("E-mail kon niet worden verzonden: {$mail->ErrorInfo}");
                        // Optioneel: foutmelding opslaan voor administratie
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                $success = 'Registratie succesvol! Je kunt nu inloggen.';
                if ($type == 'onderzoeker') {
                    $success .= ' Let op: je account moet nog goedgekeurd worden door een beheerder voordat je kunt inloggen.';
                }
                
                // Redirect naar inlogpagina na 3 seconden
                header("refresh:3;url=login.php");
            } catch (PDOException $e) {
                // Rollback in geval van fouten
                $conn->rollback();
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
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                       onchange="detectUserType()">
                                <div class="form-text">
                                    Dit e-mailadres wordt gebruikt om in te loggen en bepaalt je gebruikerstype: 
                                    @student.vives.be voor studenten, 
                                    @vives.be voor docenten, 
                                    andere domeinen voor onderzoekers.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="telefoon" class="form-label">Telefoonnummer</label>
                                <input type="text" class="form-control" id="telefoon" name="telefoon"
                                       value="<?php echo isset($_POST['telefoon']) ? htmlspecialchars($_POST['telefoon']) : ''; ?>">
                            </div>
                            
                            <div id="vivesIdContainer" class="mb-3" style="display: none;">
                                <label for="vives_id" class="form-label">VIVES ID *</label>
                                <input type="text" class="form-control" id="vives_id" name="vives_id"
                                       value="<?php echo isset($_POST['vives_id']) ? htmlspecialchars($_POST['vives_id']) : ''; ?>"
                                       placeholder="Bijv. R1234567 of U1234567">
                                <div class="form-text">R-nummer voor studenten, U-nummer voor docenten</div>
                            </div>
                            
                            <div id="opleidingContainer" class="mb-3" style="display: none;">
                                <label for="opleiding_id" class="form-label">Opleiding *</label>
                                <select class="form-select" id="opleiding_id" name="opleiding_id">
                                    <option value="">Selecteer opleiding</option>
                                    <?php foreach ($opleidingen as $opleiding): ?>
                                        <option value="<?php echo $opleiding['id']; ?>" 
                                            <?php echo (isset($_POST['opleiding_id']) && $_POST['opleiding_id'] == $opleiding['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($opleiding['naam']); ?>
                                        </option>
                                    <?php endforeach; ?>
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
                            
                            <div id="userTypeInfo" class="alert alert-info mb-4" style="display: none;">
                                <!-- User type info will be displayed here by JavaScript -->
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Registreren</button>
                            </div>
                            
                            <div class="text-center mt-4">
                                <p>Heb je al een account? <a href="login.php">Inloggen</a></p>
                            </div>
                        </form>
                        
                        <script>
                            function detectUserType() {
                                const email = document.getElementById('email').value.trim();
                                const vivesIdContainer = document.getElementById('vivesIdContainer');
                                const opleidingContainer = document.getElementById('opleidingContainer');
                                const userTypeInfo = document.getElementById('userTypeInfo');
                                
                                // Reset displays
                                vivesIdContainer.style.display = 'none';
                                opleidingContainer.style.display = 'none';
                                userTypeInfo.style.display = 'none';
                                
                                if (!email) return;
                                
                                // Detect user type based on email domain
                                if (email.endsWith('@student.vives.be')) {
                                    // Student
                                    vivesIdContainer.style.display = 'block';
                                    opleidingContainer.style.display = 'block';
                                    userTypeInfo.innerHTML = 'Je registreert als <strong>student</strong>. Vul je R-nummer en opleiding in.';
                                    userTypeInfo.style.display = 'block';
                                    document.getElementById('vives_id').setAttribute('required', 'required');
                                    document.getElementById('opleiding_id').setAttribute('required', 'required');
                                } else if (email.endsWith('@vives.be')) {
                                    // Docent
                                    vivesIdContainer.style.display = 'block';
                                    userTypeInfo.innerHTML = 'Je registreert als <strong>docent</strong>. Vul je U-nummer in.';
                                    userTypeInfo.style.display = 'block';
                                    document.getElementById('vives_id').setAttribute('required', 'required');
                                    document.getElementById('opleiding_id').removeAttribute('required');
                                } else {
                                    // Onderzoeker
                                    userTypeInfo.innerHTML = 'Je registreert als <strong>onderzoeker</strong>. Je account zal moeten worden goedgekeurd door een beheerder.';
                                    userTypeInfo.style.display = 'block';
                                    document.getElementById('vives_id').removeAttribute('required');
                                    document.getElementById('opleiding_id').removeAttribute('required');
                                }
                            }
                            
                            // Run detection on page load if email is already filled in
                            document.addEventListener('DOMContentLoaded', detectUserType);
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>