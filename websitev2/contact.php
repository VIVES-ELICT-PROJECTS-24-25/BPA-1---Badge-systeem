<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$currentPage = 'contact';
$pageTitle = 'Contact - 3D Printer Reserveringssysteem';

$success = '';
$error = '';

// Add this at the top of contact.php
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
        // Email headers
        $headers = "From: " . $email . "\r\n";
        $headers .= "Reply-To: " . $email . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        // Email content
        $mail_content = "<p><strong>Naam:</strong> " . htmlspecialchars($name) . "</p>";
        $mail_content .= "<p><strong>E-mail:</strong> " . htmlspecialchars($email) . "</p>";
        $mail_content .= "<p><strong>Bericht:</strong></p>";
        $mail_content .= "<p>" . nl2br(htmlspecialchars($message)) . "</p>";
        
        // Recipient email (change this to the actual contact email)
        $to = "larsvandekerkhove@gmail.com";
        
        // Send the email
        if (mail($to, "Contact formulier: " . $subject, $mail_content, $headers)) {
            $success = "Je bericht is verzonden. Wij nemen zo snel mogelijk contact met je op.";
        } else {
            $error = "Er is een probleem opgetreden bij het verzenden van je bericht. Probeer het later opnieuw.";
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
                        <button type="submit" class="btn btn-primary">Verzenden</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Contactgegevens</h5>
                    <p><i class="fas fa-map-marker-alt me-2"></i> Universiteitslaan 2, 8500 Kortrijk</p>
                    <p><i class="fas fa-phone me-2"></i> +32 2 123 45 67</p>
                    <p><i class="fas fa-envelope me-2"></i> info@3dprintersmaaklabvives.be</p>
                    <p><i class="fas fa-clock me-2"></i> Donderdag: 14:00 - 18:00</p>
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