<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

// Schakel foutrapportage in tijdens ontwikkeling
ini_set('display_errors', 1);
error_reporting(E_ALL);

$currentPage = 'feedback';
$pageTitle = 'Feedback - 3D Printer Reserveringssysteem';

// Variabelen initialiseren
$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$reservation = null;

// Controleer of de token geldig is
if (!empty($token)) {
    try {
        // Gecorrigeerde query - nu met p.Printer_ID in plaats van p.ID
        $stmt = $conn->prepare("
            SELECT r.*, u.Voornaam, u.Naam, p.Versie_Toestel  
            FROM Reservatie r
            JOIN User u ON r.User_ID = u.User_ID
            JOIN Printer p ON r.Printer_ID = p.Printer_ID
            WHERE r.feedback_token = ? AND r.print_completed = 1
        ");
        $stmt->execute([$token]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            $error = 'Ongeldige of verlopen token. Controleer uw e-mail voor de juiste link.';
        } elseif (isset($reservation['feedback_gegeven']) && $reservation['feedback_gegeven'] == 1) {
            $success = 'U heeft al feedback gegeven voor deze print. Bedankt!';
            $reservation = null; // Formulier niet tonen
        }
    } catch (PDOException $e) {
        $error = 'Database fout: ' . $e->getMessage();
    }
} else {
    $error = 'Geen token opgegeven. Gebruik de link uit uw e-mail.';
}

// Verwerk het formulier als het wordt ingediend
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reservation) {
    $feedback_tekst = $_POST['feedback_tekst'] ?? '';
    $feedback_print_kwaliteit = intval($_POST['print_kwaliteit'] ?? 0);
    $feedback_gebruiksgemak = intval($_POST['gebruiksgemak'] ?? 0);
    
    // Validatie
    if (empty($feedback_tekst)) {
        $error = 'Voer a.u.b. een feedbacktekst in.';
    } elseif ($feedback_print_kwaliteit < 1 || $feedback_print_kwaliteit > 5) {
        $error = 'Selecteer een geldige waarde voor print kwaliteit (1-5).';
    } elseif ($feedback_gebruiksgemak < 1 || $feedback_gebruiksgemak > 5) {
        $error = 'Selecteer een geldige waarde voor gebruiksgemak (1-5).';
    } else {
        try {
            // Update de reservering met feedback
            $stmt = $conn->prepare("
                UPDATE Reservatie 
                SET feedback_gegeven = 1, 
                    feedback_tekst = ?, 
                    feedback_print_kwaliteit = ?, 
                    feedback_gebruiksgemak = ?,
                    feedback_datum = NOW()
                WHERE Reservatie_ID = ?
            ");
            $stmt->execute([
                $feedback_tekst, 
                $feedback_print_kwaliteit, 
                $feedback_gebruiksgemak, 
                $reservation['Reservatie_ID']
            ]);
            
            $success = 'Bedankt voor uw feedback! Uw input helpt ons het Maaklab te verbeteren.';
            $reservation = null; // Formulier niet meer tonen
        } catch (PDOException $e) {
            $error = 'Fout bij het opslaan van feedback: ' . $e->getMessage();
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
                    <h1 class="text-center mb-4">3D Print Feedback</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                            <div class="mt-3 text-center">
                                <a href="index.php" class="btn btn-primary">Terug naar startpagina</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($reservation): ?>
                        <div class="bg-light p-3 rounded mb-4">
                            <h5>Printgegevens</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Printer:</strong> <?php echo htmlspecialchars($reservation['Versie_Toestel']); ?></p>
                                    <p class="mb-1"><strong>Gebruiker:</strong> <?php echo htmlspecialchars($reservation['Voornaam'] . ' ' . $reservation['Naam']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Datum:</strong> <?php echo isset($reservation['print_end_time']) ? date('d-m-Y', strtotime($reservation['print_end_time'])) : date('d-m-Y'); ?></p>
                                    <p class="mb-1"><strong>Reservering #:</strong> <?php echo $reservation['Reservatie_ID']; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <form method="post" action="feedback.php?token=<?php echo htmlspecialchars($token); ?>">
                            <div class="mb-4">
                                <label class="form-label">Print kwaliteit beoordeling: *</label>
                                <div class="d-flex justify-content-between mb-2">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="print_kwaliteit" id="quality-1" value="1" required>
                                        <label class="form-check-label" for="quality-1">1 (Slecht)</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="print_kwaliteit" id="quality-2" value="2">
                                        <label class="form-check-label" for="quality-2">2</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="print_kwaliteit" id="quality-3" value="3">
                                        <label class="form-check-label" for="quality-3">3</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="print_kwaliteit" id="quality-4" value="4">
                                        <label class="form-check-label" for="quality-4">4</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="print_kwaliteit" id="quality-5" value="5">
                                        <label class="form-check-label" for="quality-5">5 (Uitstekend)</label>
                                    </div>
                                </div>
                                <div class="form-text">Hoe tevreden bent u over de kwaliteit van uw print?</div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Gebruiksgemak reserveringssysteem: *</label>
                                <div class="d-flex justify-content-between mb-2">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="gebruiksgemak" id="usability-1" value="1" required>
                                        <label class="form-check-label" for="usability-1">1 (Slecht)</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="gebruiksgemak" id="usability-2" value="2">
                                        <label class="form-check-label" for="usability-2">2</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="gebruiksgemak" id="usability-3" value="3">
                                        <label class="form-check-label" for="usability-3">3</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="gebruiksgemak" id="usability-4" value="4">
                                        <label class="form-check-label" for="usability-4">4</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="gebruiksgemak" id="usability-5" value="5">
                                        <label class="form-check-label" for="usability-5">5 (Uitstekend)</label>
                                    </div>
                                </div>
                                <div class="form-text">Hoe beoordeelt u het gebruiksgemak van het reserveringssysteem?</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="feedback_tekst" class="form-label">Uw feedback: *</label>
                                <textarea class="form-control" id="feedback_tekst" name="feedback_tekst" rows="5" required 
                                  placeholder="Deel uw ervaring, suggesties, of eventuele problemen die u heeft ondervonden..."></textarea>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Verstuur feedback</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>