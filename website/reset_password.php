<?php
require_once 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'vendor/autoload.php'; // Adjust path to your PHPMailer installation

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($token) || empty($password) || empty($confirm_password)) {
        $error = 'Vul alle velden in.';
    } elseif ($password !== $confirm_password) {
        $error = 'De wachtwoorden komen niet overeen.';
    } else {
        // Zoek de token in de database
        $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if ($reset) {
            // Update het wachtwoord van de gebruiker
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE User SET Wachtwoord = ? WHERE Emailadres = ?");
            $stmt->execute([$hashed_password, $reset['email']]);

            // Verwijder de reset token
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);

            // Verstuur een bevestigingsemail
            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp-auth.mailprotect.be';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'reservaties@3dprintersmaaklabvives.be';
                $mail->Password   = '9ke53d3w2ZP64ik76qHe';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                // Recipients
                $mail->setFrom('reservaties@3dprintersmaaklabvives.be', '3D Printer Reserveringssysteem');
                $mail->addAddress($reset['email']);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Wachtwoord succesvol gewijzigd';
                $mail->Body    = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; }
                            .container { padding: 20px; }
                            .header { background: #007bff; color: #fff; padding: 10px; text-align: center; }
                            .content { margin: 20px 0; }
                            .footer { text-align: center; padding: 10px; font-size: 12px; color: #777; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>Wachtwoord succesvol gewijzigd</h1>
                            </div>
                            <div class='content'>
                                <p>Beste gebruiker,</p>
                                <p>Je wachtwoord is succesvol gewijzigd. Je kunt nu inloggen met je nieuwe wachtwoord.</p>
                                <p>Als je deze wijziging niet hebt aangevraagd, neem dan onmiddellijk contact op met onze ondersteuning.</p>
                                <p>Met vriendelijke groet,<br>Het 3D Printer Reserveringssysteem Team</p>
                            </div>
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " 3D Printer Reserveringssysteem. Alle rechten voorbehouden.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";

                $mail->send();
                $error = 'Je wachtwoord is succesvol gewijzigd. Je kunt nu inloggen.';
            } catch (Exception $e) {
                $error = 'Wachtwoord gewijzigd, maar er was een probleem met het versturen van de bevestigingsmail. Probeer later opnieuw.';
            }
        } else {
            $error = 'Ongeldige of verlopen token.';
        }
    }
} elseif (isset($_GET['email'])) {
    $email = trim($_GET['email'] ?? '');
    if (empty($email)) {
        $error = 'Vul je e-mailadres in.';
    } else {
        // Genereren van een reset token
        $token = bin2hex(random_bytes(50));
        // Verwijder bestaande tokens voor dit e-mailadres
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);
        // Opslaan van de nieuwe reset token
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
        $stmt->execute([$email, $token]);

        // Verstuur de reset token via e-mail
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp-auth.mailprotect.be';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'reservaties@3dprintersmaaklabvives.be';
            $mail->Password   = '9ke53d3w2ZP64ik76qHe';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            // Recipients
            $mail->setFrom('reservaties@3dprintersmaaklabvives.be', '3D Printer Reserveringssysteem');
            $mail->addAddress($email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Wachtwoord reset aanvragen';
            $mail->Body    = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { padding: 20px; }
                        .header { background: #007bff; color: #fff; padding: 10px; text-align: center; }
                        .content { margin: 20px 0; }
                        .footer { text-align: center; padding: 10px; font-size: 12px; color: #777; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Wachtwoord reset aanvragen</h1>
                        </div>
                        <div class='content'>
                            <p>Beste gebruiker,</p>
                            <p>Klik op de volgende link om je wachtwoord te resetten:</p>
                            <p><a href='http://3dprintersmaaklabvives.be/reset_password.php?token=$token'>Wachtwoord resetten</a></p>
                            <p>Als je deze aanvraag niet hebt gedaan, negeer deze e-mail dan.</p>
                            <p>Met vriendelijke groet,<br>Het 3D Printer Reserveringssysteem Team</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " 3D Printer Reserveringssysteem. Alle rechten voorbehouden.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            $mail->send();
            $error = 'Er is een e-mail verzonden met instructies om je wachtwoord te resetten.';
        } catch (Exception $e) {
            $error = 'Er is iets fout gegaan bij het versturen van de e-mail. Probeer het later opnieuw.';
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h1 class="text-center mb-4">Reset Wachtwoord</h1>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="post" action="reset_password.php">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Nieuw wachtwoord</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Bevestig nieuw wachtwoord</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">Reset wachtwoord</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>