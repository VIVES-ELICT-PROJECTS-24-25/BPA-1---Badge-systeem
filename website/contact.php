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

$currentPage = 'contact';
$pageTitle = 'Contact - 3D Printer Reserveringssysteem';

$success = '';
$error = '';

// Form handling with PHPMailer
if (isset($_POST['submit_contact'])) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = "Alle velden zijn verplicht.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Vul een geldig e-mailadres in.";
    } else {
        // Huidige datum en tijd
        $currentDateTime = date('d-m-Y H:i');
        
        try {
            // 1. MAIL NAAR BEHEERDER
            $mailAdmin = new PHPMailer(true);
            
            // Server settings
            $mailAdmin->isSMTP();
            $mailAdmin->Host       = 'smtp-auth.mailprotect.be';
            $mailAdmin->SMTPAuth   = true;
            $mailAdmin->Username   = 'reservaties@3dprintersmaaklabvives.be';
            $mailAdmin->Password   = '9ke53d3w2ZP64ik76qHe';
            $mailAdmin->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mailAdmin->Port       = 587;
            $mailAdmin->CharSet    = 'UTF-8';
            
            // Recipients
            $mailAdmin->setFrom('reservaties@3dprintersmaaklabvives.be', '3D Printers Maaklab VIVES');
            $mailAdmin->addAddress('info@3dprintersmaaklabvives.be', 'Beheerder');
            $mailAdmin->addReplyTo($email, $name);
            
            // Content
            $mailAdmin->isHTML(true);
            $mailAdmin->Subject = "Contact formulier: " . $subject;
            
            // Email content met verbeterde opmaak
            $mailContentAdmin = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333333;
                        max-width: 600px;
                        margin: 0 auto;
                    }
                    .email-container {
                        border: 1px solid #dddddd;
                        border-radius: 5px;
                        overflow: hidden;
                    }
                    .email-header {
                        background-color: #0056b3;
                        color: white;
                        padding: 20px;
                        text-align: center;
                    }
                    .email-body {
                        padding: 20px;
                        background-color: #f9f9f9;
                    }
                    .email-footer {
                        background-color: #f1f1f1;
                        padding: 15px;
                        text-align: center;
                        font-size: 12px;
                        color: #666666;
                    }
                    .contact-info {
                        background-color: white;
                        border-radius: 5px;
                        padding: 15px;
                        margin-bottom: 15px;
                        border-left: 4px solid #0056b3;
                    }
                    .message-box {
                        background-color: white;
                        border-radius: 5px;
                        padding: 15px;
                        border-left: 4px solid #28a745;
                    }
                    h2 {
                        color: #0056b3;
                        margin-top: 0;
                    }
                    .info-row {
                        margin-bottom: 10px;
                    }
                    .label {
                        font-weight: bold;
                        color: #555555;
                    }
                    .timestamp {
                        color: #888888;
                        font-size: 12px;
                        margin-top: 20px;
                        text-align: right;
                    }
                </style>
            </head>
            <body>
                <div class="email-container">
                    <div class="email-header">
                        <h1>Nieuw Contactformulier Bericht</h1>
                    </div>
                    <div class="email-body">
                        <div class="contact-info">
                            <h2>Contactgegevens</h2>
                            <div class="info-row">
                                <span class="label">Naam:</span> ' . htmlspecialchars($name) . '
                            </div>
                            <div class="info-row">
                                <span class="label">E-mail:</span> ' . htmlspecialchars($email) . '
                            </div>
                            <div class="info-row">
                                <span class="label">Onderwerp:</span> ' . htmlspecialchars($subject) . '
                            </div>
                        </div>
                        
                        <div class="message-box">
                            <h2>Bericht</h2>
                            <p>' . nl2br(htmlspecialchars($message)) . '</p>
                        </div>
                        
                        <div class="timestamp">
                            Verzonden op: ' . $currentDateTime . '
                        </div>
                    </div>
                    <div class="email-footer">
                        <p>Dit bericht is verzonden via het contactformulier op de website van 3D Printers Maaklab VIVES.</p>
                        <p>&copy; ' . date('Y') . ' Maaklab VIVES - Alle rechten voorbehouden</p>
                    </div>
                </div>
            </body>
            </html>';
            
            $mailAdmin->Body = $mailContentAdmin;
            
            // Plain text alternative
            $plainTextAdmin = "NIEUW CONTACTFORMULIER BERICHT\n\n";
            $plainTextAdmin .= "CONTACTGEGEVENS:\n";
            $plainTextAdmin .= "Naam: " . $name . "\n";
            $plainTextAdmin .= "E-mail: " . $email . "\n";
            $plainTextAdmin .= "Onderwerp: " . $subject . "\n\n";
            $plainTextAdmin .= "BERICHT:\n" . $message . "\n\n";
            $plainTextAdmin .= "Verzonden op: " . $currentDateTime . "\n\n";
            $plainTextAdmin .= "Dit bericht is verzonden via het contactformulier op de website van 3D Printers Maaklab VIVES.";
            
            $mailAdmin->AltBody = $plainTextAdmin;
            
            // Send the email to admin
            $mailAdmin->send();
            
            // 2. BEVESTIGINGSMAIL NAAR AFZENDER
            $mailConfirmation = new PHPMailer(true);
            
            // Server settings (hergebruik dezelfde instellingen)
            $mailConfirmation->isSMTP();
            $mailConfirmation->Host       = 'smtp-auth.mailprotect.be';
            $mailConfirmation->SMTPAuth   = true;
            $mailConfirmation->Username   = 'reservaties@3dprintersmaaklabvives.be';
            $mailConfirmation->Password   = '9ke53d3w2ZP64ik76qHe';
            $mailConfirmation->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mailConfirmation->Port       = 587;
            $mailConfirmation->CharSet    = 'UTF-8';
            
            // Recipients voor bevestigingsmail
            $mailConfirmation->setFrom('reservaties@3dprintersmaaklabvives.be', '3D Printers Maaklab VIVES');
            $mailConfirmation->addAddress($email, $name); // Stuur naar de persoon die het contactformulier heeft ingevuld
            
            // Content
            $mailConfirmation->isHTML(true);
            $mailConfirmation->Subject = "Bevestiging: Uw bericht aan 3D Printers Maaklab VIVES";
            
            // Email content voor bevestigingsmail
            $mailContentConfirmation = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333333;
                        max-width: 600px;
                        margin: 0 auto;
                    }
                    .email-container {
                        border: 1px solid #dddddd;
                        border-radius: 5px;
                        overflow: hidden;
                    }
                    .email-header {
                        background-color: #28a745;
                        color: white;
                        padding: 20px;
                        text-align: center;
                    }
                    .email-body {
                        padding: 20px;
                        background-color: #f9f9f9;
                    }
                    .email-footer {
                        background-color: #f1f1f1;
                        padding: 15px;
                        text-align: center;
                        font-size: 12px;
                        color: #666666;
                    }
                    .thank-you {
                        background-color: white;
                        border-radius: 5px;
                        padding: 15px;
                        margin-bottom: 15px;
                        border-left: 4px solid #28a745;
                    }
                    .message-summary {
                        background-color: white;
                        border-radius: 5px;
                        padding: 15px;
                        margin-bottom: 15px;
                        border-left: 4px solid #0056b3;
                    }
                    .next-steps {
                        background-color: white;
                        border-radius: 5px;
                        padding: 15px;
                        border-left: 4px solid #ffc107;
                    }
                    h2 {
                        color: #0056b3;
                        margin-top: 0;
                    }
                    .info-row {
                        margin-bottom: 10px;
                    }
                    .label {
                        font-weight: bold;
                        color: #555555;
                    }
                    .timestamp {
                        color: #888888;
                        font-size: 12px;
                        margin-top: 20px;
                        text-align: right;
                    }
                    .logo {
                        max-width: 150px;
                        margin: 0 auto 10px auto;
                        display: block;
                    }
                </style>
            </head>
            <body>
                <div class="email-container">
                    <div class="email-header">
                        <h1>Bevestiging Ontvangst</h1>
                    </div>
                    <div class="email-body">
                        <div class="thank-you">
                            <h2>Beste ' . htmlspecialchars($name) . ',</h2>
                            <p>Hartelijk dank voor uw bericht aan 3D Printers Maaklab VIVES. Wij hebben uw aanvraag in goede orde ontvangen en zullen zo spoedig mogelijk contact met u opnemen.</p>
                        </div>
                        
                        <div class="message-summary">
                            <h2>Samenvatting van uw bericht</h2>
                            <div class="info-row">
                                <span class="label">Naam:</span> ' . htmlspecialchars($name) . '
                            </div>
                            <div class="info-row">
                                <span class="label">E-mail:</span> ' . htmlspecialchars($email) . '
                            </div>
                            <div class="info-row">
                                <span class="label">Onderwerp:</span> ' . htmlspecialchars($subject) . '
                            </div>
                            <div class="info-row">
                                <span class="label">Bericht:</span>
                                <p>' . nl2br(htmlspecialchars($message)) . '</p>
                            </div>
                        </div>
                        
                        <div class="next-steps">
                            <h2>Wat kunt u verwachten?</h2>
                            <p>Onze medewerkers streven ernaar om binnen 2 werkdagen te reageren op uw bericht.</p>
                        </div>
                        
                        <div class="timestamp">
                            Verzonden op: ' . $currentDateTime . '
                        </div>
                    </div>
                    <div class="email-footer">
                        <p>Dit is een automatisch gegenereerde e-mail, u kunt deze e-mail niet beantwoorden.</p>
                        <p>Bezoek onze website: <a href="https://3dprintersmaaklabvives.be">3dprintersmaaklabvives.be</a></p>
                        <p>&copy; ' . date('Y') . ' Maaklab VIVES - Alle rechten voorbehouden</p>
                    </div>
                </div>
            </body>
            </html>';
            
            $mailConfirmation->Body = $mailContentConfirmation;
            
            // Plain text alternative voor bevestigingsmail
            $plainTextConfirmation = "BEVESTIGING VAN UW BERICHT AAN 3D PRINTERS MAAKLAB VIVES\n\n";
            $plainTextConfirmation .= "Beste " . $name . ",\n\n";
            $plainTextConfirmation .= "Hartelijk dank voor uw bericht. Wij hebben uw aanvraag in goede orde ontvangen en zullen zo spoedig mogelijk contact met u opnemen.\n\n";
            $plainTextConfirmation .= "SAMENVATTING VAN UW BERICHT:\n";
            $plainTextConfirmation .= "Naam: " . $name . "\n";
            $plainTextConfirmation .= "E-mail: " . $email . "\n";
            $plainTextConfirmation .= "Onderwerp: " . $subject . "\n";
            $plainTextConfirmation .= "Bericht:\n" . $message . "\n\n";
            $plainTextConfirmation .= "WAT KUNT U VERWACHTEN?\n";
            $plainTextConfirmation .= "Onze medewerkers streven ernaar om binnen 2 werkdagen te reageren op uw bericht.\n";
            $plainTextConfirmation .= "Verzonden op: " . $currentDateTime . "\n\n";
            $plainTextConfirmation .= "Dit is een automatisch gegenereerde e-mail, u kunt deze e-mail niet beantwoorden.\n";
            $plainTextConfirmation .= "Bezoek onze website: https://3dprintersmaaklabvives.be\n\n";
            $plainTextConfirmation .= "© " . date('Y') . " Maaklab VIVES - Alle rechten voorbehouden";
            
            $mailConfirmation->AltBody = $plainTextConfirmation;
            
            // Send the confirmation email
            $mailConfirmation->send();
            
            $success = "Je bericht is verzonden. We hebben een bevestiging gestuurd naar je e-mailadres.";
        } catch (Exception $e) {
            $error = "Er is een probleem opgetreden bij het verzenden van je bericht: " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">Neem Contact Op</h1>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Contactformulier</h5>
                    <form method="post" action="contact.php" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label">Naam *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback">
                                Vul alstublieft uw naam in.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mailadres *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="invalid-feedback">
                                Vul alstublieft een geldig e-mailadres in.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="subject" class="form-label">Onderwerp *</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                            <div class="invalid-feedback">
                                Vul alstublieft een onderwerp in.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Bericht *</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            <div class="invalid-feedback">
                                Vul alstublieft uw bericht in.
                            </div>
                        </div>
                        <button type="submit" name="submit_contact" class="btn btn-primary">Verzenden</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Contactgegevens</h5>
                    <p><i class="fas fa-map-marker-alt me-2"></i> Universiteitslaan 2, 8500 Kortrijk</p>
                    <p><i class="fas fa-envelope me-2"></i> info@3dprintersmaaklabvives.be</p>
 		    <p><i class="fas fa-file-pdf me-2"></i> <a href="afspraken_studenten.pdf" target="_blank">Afspraken voor Studenten</a></p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Locatie</h5>
                    <div class="ratio ratio-16x9">
                        <iframe width="100%" height="600" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?width=100%25&amp;height=600&amp;hl=en&amp;q=Universiteitslaan%202,%208500%20Kortrijk+(Maaklab%20Vives)&amp;t=&amp;z=15&amp;ie=UTF8&amp;iwloc=B&amp;output=embed"><a href="https://www.gps.ie/collections/drones/">gps drone</a></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Veelgestelde Vragen</h5>
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    Hoe kan ik een 3D printer reserveren?
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Om een 3D printer te reserveren, moet je eerst een account aanmaken of inloggen. Daarna kun je via de printerpagina of kalender een beschikbare printer selecteren en een reservering maken voor je gewenste tijdslot.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    Wat zijn de kosten voor het gebruik van een 3D printer?
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    De kosten variëren afhankelijk van het type printer, printmateriaal en printtijd. Neem contact op met ons voor specifieke prijsinformatie voor jouw project.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    Kan ik een gemaakte reservering wijzigen of annuleren?
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Ja, je kunt je reserveringen bekijken, wijzigen of annuleren via de 'Mijn Reserveringen' pagina in je account. Houd er rekening mee dat wijzigingen of annuleringen uiterlijk 24 uur voor de gereserveerde tijd moeten worden gedaan.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<?php include 'includes/footer.php'; ?>