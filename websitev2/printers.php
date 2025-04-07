<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$currentPage = 'printers';
$pageTitle = 'Beschikbare Printers - 3D Printer Reserveringssysteem';

// Get all printers
$stmt = $conn->query("SELECT * FROM Printer ORDER BY Versie_Toestel");
$printers = $stmt->fetchAll();

// Haal filters op voor zoekfunctie
$search = isset($_GET['search']) ? $_GET['search'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';

// Pas de filterlogica aan naar je database structuur
$filteredPrinters = [];
foreach ($printers as $printer) {
    // Filter op zoekterm
    if (!empty($search) && stripos($printer['Versie_Toestel'], $search) === false) {
        continue;
    }
    
    // Filter op status
    if (!empty($statusFilter) && $printer['Status'] != $statusFilter) {
        continue;
    }
    
    // Filter op type (pas aan naar relevante velden in je db)
    // Bijvoorbeeld, als je een veld zoals 'Type' of 'Software' gebruikt:
    if (!empty($typeFilter) && $printer['Software'] != $typeFilter) {
        continue;
    }
    
    $filteredPrinters[] = $printer;
}

include 'includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Beschikbare 3D Printers</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Printers</li>
            </ol>
        </nav>
    </div>
    
    <!-- Filter form -->
    <div class="card mb-4">
        <div class="card-body">
            <form class="row g-3" action="printers.php" method="get">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Zoek printer..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="">Filter op Status</option>
                        <option value="beschikbaar" <?php echo $statusFilter === 'beschikbaar' ? 'selected' : ''; ?>>Beschikbaar</option>
                        <option value="in_gebruik" <?php echo $statusFilter === 'in_gebruik' ? 'selected' : ''; ?>>In Gebruik</option>
                        <option value="onderhoud" <?php echo $statusFilter === 'onderhoud' ? 'selected' : ''; ?>>Onderhoud</option>
                        <option value="defect" <?php echo $statusFilter === 'defect' ? 'selected' : ''; ?>>Defect</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="type" onchange="this.form.submit()">
                        <option value="">Filter op Type</option>
                        <option value="versie1" <?php echo $typeFilter === 'versie1' ? 'selected' : ''; ?>>Versie 1</option>
                        <option value="versie2" <?php echo $typeFilter === 'versie2' ? 'selected' : ''; ?>>Versie 2</option>
                        <option value="versie3" <?php echo $typeFilter === 'versie3' ? 'selected' : ''; ?>>Versie 3</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <a href="printers.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <?php if (empty($filteredPrinters)): ?>
        <div class="alert alert-info">
            <h4 class="alert-heading">Geen Printers Gevonden</h4>
            <p>Er zijn geen printers die voldoen aan je zoekcriteria. Probeer andere zoektermen of filters.</p>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($filteredPrinters as $printer): ?>
                <div class="col">
                    <div class="card h-100">
                        <img src="<?php echo htmlspecialchars($printer['foto']); ?>"
    			class="card-img-top" 
    			alt="<?php echo htmlspecialchars($printer['Versie_Toestel']); ?>" 
   			 onerror="this.outerHTML='<div class=\'card-img-top d-flex justify-content-center align-items-center bg-light\' style=\'height: 200px;\'><p class=\'text-muted mb-0\'>Geen afbeelding beschikbaar voor <?php echo htmlspecialchars($printer['Versie_Toestel']); ?></p></div>'">                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($printer['Versie_Toestel']); ?></h5>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-<?php 
                                    if ($printer['Status'] === 'beschikbaar') echo 'success';
                                    elseif ($printer['Status'] === 'onderhoud') echo 'warning';
                                    elseif ($printer['Status'] === 'in_gebruik') echo 'primary';
                                    else echo 'danger';
                                ?> py-2">
                                    <?php 
                                        if ($printer['Status'] === 'beschikbaar') echo 'Beschikbaar';
                                        elseif ($printer['Status'] === 'onderhoud') echo 'Onderhoud';
                                        elseif ($printer['Status'] === 'in_gebruik') echo 'In gebruik';
                                        else echo 'Niet beschikbaar';
                                    ?>
                                </span>
                                <span class="text-muted">Software: <?php echo htmlspecialchars($printer['Software'] ?? 'Niet gespecificeerd'); ?></span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-grid gap-2">
                                <a href="printer-details.php?id=<?php echo $printer['Printer_ID']; ?>" class="btn btn-outline-primary">Details Bekijken</a>
                                <?php if ($printer['Status'] === 'beschikbaar' && isset($_SESSION['User_ID'])): ?>
                                    <a href="reserve.php?printer_id=<?php echo $printer['Printer_ID']; ?>" class="btn btn-primary">
                                        <i class="fas fa-calendar-plus"></i> Reserveren
                                    </a>
                                <?php elseif ($printer['Status'] === 'beschikbaar'): ?>
                                    <a href="login.php" class="btn btn-outline-secondary">Log in om te reserveren</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Filter functionality
        const filterButtons = document.querySelectorAll('[data-filter]');
        const printerItems = document.querySelectorAll('.printer-item');
        
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Update active button
                filterButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                
                printerItems.forEach(item => {
                    if (filter === 'all') {
                        item.style.display = 'block';
                    } else if (filter === 'available') {
                        item.style.display = item.getAttribute('data-status') === 'available' ? 'block' : 'none';
                    } else if (filter === 'color') {
                        item.style.display = item.getAttribute('data-color') === 'color' ? 'block' : 'none';
                    }
                });
            });
        });
        
        // Search functionality
        const searchParam = new URLSearchParams(window.location.search).get('search');
        if (searchParam) {
            const searchLower = searchParam.toLowerCase();
            printerItems.forEach(item => {
                const itemName = item.querySelector('.card-title').textContent.toLowerCase();
                const itemDesc = item.querySelector('.card-text').textContent.toLowerCase();
                
                if (itemName.includes(searchLower) || itemDesc.includes(searchLower)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>