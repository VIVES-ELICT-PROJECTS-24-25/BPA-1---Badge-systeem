<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$currentPage = 'contact';
$pageTitle = 'Contact - 3D Printer Reserveringssysteem';

$success = '';
$error = '';

// Process contact form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $subject = sanitizeInput($_POST['subject']);
    $message = sanitizeInput($_POST['message']);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Alle velden zijn verplicht.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Voer een geldig e-mailadres in.';
    } else {
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$name, $email, $subject, $message]);
        
        if ($result) {
            $success = 'Bedankt voor je bericht! We nemen zo snel mogelijk contact met je op.';
            
            // Optional: Send email notification to admin
            // mail('admin@example.com', 'Nieuw contactformulier inzending', "Naam: $name\nEmail: $email\nOnderwerp: $subject\nBericht: $message");
        } else {
            $error = 'Er is een fout opgetreden bij het verzenden van je bericht. Probeer het later opnieuw.';
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
                    <p><i class="fas fa-map-marker-alt me-2"></i> Schoolstraat 1, 1000 Brussel</p>
                    <p><i class="fas fa-phone me-2"></i> +32 2 123 45 67</p>
                    <p><i class="fas fa-envelope me-2"></i> info@printreservation.be</p>
                    <p><i class="fas fa-clock me-2"></i> Maandag - Vrijdag: 9:00 - 17:00</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Locatie</h5>
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2519.202631192928!2d4.3517765!3d50.8465573!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x47c3c37d924ad855%3A0x2a02d9b541702d06!2sBrussel!5e0!3m2!1snl!2sbe!4v1616418408725!5m2!1snl!2sbe" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
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