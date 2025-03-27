<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$currentPage = 'home';
$pageTitle = 'Home - 3D Printer Reserveringssysteem';

// Get available printers for homepage
$stmt = $conn->query("SELECT * FROM Printer ORDER BY Versie_Toestel");
$availablePrinters = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="hero-section text-center">
    <div class="container">
        <h1>Welkom bij het 3D Printer Reserveringssysteem</h1>
        <p class="lead mb-4">Reserveer eenvoudig en snel een 3D printer voor jouw projecten.</p>
        <?php if (!isLoggedIn()): ?>
            <div class="mt-4">
                <a href="register.php" class="btn btn-primary btn-lg me-2">Registreren</a>
                <a href="login.php" class="btn btn-outline-light btn-lg">Inloggen</a>
            </div>
        <?php else: ?>
            <div class="mt-4">
                <a href="printers.php" class="btn btn-primary btn-lg me-2">Bekijk Printers</a>
                <a href="calendar.php" class="btn btn-outline-light btn-lg">Bekijk Kalender</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <div class="row text-center mb-5">
        <div class="col-md-4 mb-4">
            <div class="card feature-card h-100">
                <div class="card-body">
                    <div class="feature-icon">
                        <i class="fas fa-print"></i>
                    </div>
                    <h3 class="card-title">Diverse Printers</h3>
                    <p class="card-text">Kies uit verschillende 3D printers met diverse specificaties voor jouw specifieke project.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card feature-card h-100">
                <div class="card-body">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="card-title">Eenvoudig Reserveren</h3>
                    <p class="card-text">Bekijk beschikbaarheid en maak snel en eenvoudig een reservering voor jouw gewenste tijdslot.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card feature-card h-100">
                <div class="card-body">
                    <div class="feature-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3 class="card-title">Persoonlijk Dashboard</h3>
                    <p class="card-text">Beheer eenvoudig al je reserveringen en bekijk je geschiedenis in je persoonlijke account.</p>
                </div>
            </div>
        </div>
    </div>

    <h2 class="text-center mb-4">Beschikbare Printers</h2>
    <div class="row">
        <?php if (count($availablePrinters) > 0): ?>
            <?php foreach ($availablePrinters as $printer): ?>
                <div class="col-md-4 mb-4">
                    <div class="card printer-card">
                        <div class="position-relative">
                            <img src="assets/img/printer-<?php echo $printer['id']; ?>.jpg" alt="<?php echo htmlspecialchars($printer['name']); ?>" class="card-img-top" onerror="this.src='assets/img/printer-default.jpg'">
                            <span class="printer-status status-available">Beschikbaar</span>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($printer['name']); ?></h5>
                            <p class="card-text">
                                <strong>Model:</strong> <?php echo htmlspecialchars($printer['model']); ?><br>
                                <strong>Kleur:</strong> <?php echo $printer['color_capability'] ? 'Ja' : 'Nee'; ?>
                            </p>
                            <a href="printer-details.php?id=<?php echo $printer['id']; ?>" class="btn btn-primary">Bekijk Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center">
                <p>Momenteel zijn er geen printers beschikbaar. Bekijk de <a href="calendar.php">kalender</a> voor toekomstige beschikbaarheid.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="text-center mt-4">
        <a href="printers.php" class="btn btn-outline-primary">Bekijk Alle Printers</a>
    </div>

    <div class="row mt-5">
        <div class="col-md-6">
            <h2>Hoe het werkt</h2>
            <ol class="mt-4">
                <li class="mb-3">Maak een account aan of log in met je bestaande account.</li>
                <li class="mb-3">Bekijk de beschikbare printers en hun specificaties.</li>
                <li class="mb-3">Controleer de beschikbaarheid in de kalender.</li>
                <li class="mb-3">Maak een reservering voor je gewenste tijdslot.</li>
                <li class="mb-3">Beheer je reserveringen in je persoonlijke dashboard.</li>
            </ol>
        </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>