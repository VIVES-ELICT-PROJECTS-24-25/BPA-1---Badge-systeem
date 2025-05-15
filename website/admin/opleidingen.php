<?php
// Admin toegang controle
require_once 'admin.php';

$pageTitle = 'Opleidingen Beheer - 3D Printer Reserveringssysteem';
$currentPage = 'admin-opleidingen';

// Verwerken van formulier inzendingen
$successMessage = '';
$errorMessage = '';

// Opleiding toevoegen
if (isset($_POST['add_opleiding'])) {
    $naam = trim($_POST['naam']);
    
    if (empty($naam)) {
        $errorMessage = 'Naam van de opleiding is verplicht.';
    } else {
        try {
            // Controleer of de opleiding al bestaat
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM opleidingen WHERE naam = ?");
            $stmt->execute([$naam]);
            
            if ($stmt->fetch()['count'] > 0) {
                $errorMessage = 'Een opleiding met deze naam bestaat al.';
            } else {
                // Hoogste ID ophalen voor het maken van een nieuwe
                $stmt = $conn->query("SELECT MAX(id) as maxId FROM opleidingen");
                $result = $stmt->fetch();
                $newId = ($result['maxId'] ?? 0) + 1;
                
                // Nieuwe opleiding toevoegen
                $stmt = $conn->prepare("
                    INSERT INTO opleidingen (id, naam) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$newId, $naam]);
                
                $successMessage = "Opleiding \"$naam\" is succesvol toegevoegd.";
            }
        } catch (PDOException $e) {
            $errorMessage = 'Er is een fout opgetreden: ' . $e->getMessage();
        }
    }
}

// Opleiding bewerken
if (isset($_POST['edit_opleiding'])) {
    $id = (int)$_POST['id'];
    $naam = trim($_POST['naam']);
    
    if (empty($naam)) {
        $errorMessage = 'Naam van de opleiding is verplicht.';
    } else {
        try {
            // Controleer of de naam al door een andere opleiding wordt gebruikt
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM opleidingen WHERE naam = ? AND id != ?");
            $stmt->execute([$naam, $id]);
            
            if ($stmt->fetch()['count'] > 0) {
                $errorMessage = 'Een andere opleiding met deze naam bestaat al.';
            } else {
                // Opleiding updaten
                $stmt = $conn->prepare("
                    UPDATE opleidingen 
                    SET naam = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$naam, $id]);
                
                $successMessage = "Opleiding \"$naam\" is succesvol bijgewerkt.";
            }
        } catch (PDOException $e) {
            $errorMessage = 'Er is een fout opgetreden: ' . $e->getMessage();
        }
    }
}

// Opleiding verwijderen
if (isset($_POST['delete_opleiding'])) {
    $id = (int)$_POST['id'];
    
    try {
        // Controleer of de opleiding in gebruik is door studenten
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Vives WHERE opleiding_id = ?");
        $stmt->execute([$id]);
        $vivesCount = $stmt->fetch()['count'];
        
        if ($vivesCount > 0) {
            $errorMessage = 'Deze opleiding kan niet worden verwijderd omdat deze nog in gebruik is door: ' . $vivesCount . ' student(en)';
        } else {
            // Opleiding verwijderen
            $stmt = $conn->prepare("DELETE FROM opleidingen WHERE id = ?");
            $stmt->execute([$id]);
            
            $successMessage = "Opleiding is succesvol verwijderd.";
        }
    } catch (PDOException $e) {
        $errorMessage = 'Er is een fout opgetreden: ' . $e->getMessage();
    }
}

// Alle opleidingen ophalen
try {
    $stmt = $conn->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM Vives WHERE opleiding_id = o.id) as students_count
        FROM opleidingen o
        ORDER BY o.naam
    ");
    $stmt->execute();
    $opleidingen = $stmt->fetchAll();
} catch (PDOException $e) {
    $errorMessage = 'Fout bij ophalen van opleidingen: ' . $e->getMessage();
    $opleidingen = [];
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="d-flex justify-content-center mb-4">
                        <a href="../index.php" class="text-white text-decoration-none">
                            <span class="fs-4">3D Printer Admin</span>
                        </a>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>
                                Gebruikers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="printers.php">
                                <i class="fas fa-print me-2"></i>
                                Printers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reservations.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Reserveringen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="filaments.php">
                                <i class="fas fa-layer-group me-2"></i>
                                Filament
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="openingsuren.php">
                                <i class="fas fa-clock me-2"></i>
                                Openingsuren
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="opleidingen.php">
                                <i class="fas fa-graduation-cap me-2"></i>
                                Opleidingen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="feedback.php">
                                <i class="fas fa-comments me-2"></i>
                                Feedback
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home me-2"></i>
                                Terug naar site
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Uitloggen
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Opleidingen Beheer</h1>
                </div>
                
                <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $successMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $errorMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Nieuwe opleiding toevoegen -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Nieuwe Opleiding Toevoegen</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="naam" class="form-label">Naam <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="naam" name="naam" required>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" name="add_opleiding" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-1"></i> Toevoegen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Overzicht van opleidingen -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Beschikbare Opleidingen</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($opleidingen)): ?>
                            <p class="text-muted">Er zijn nog geen opleidingen toegevoegd. Gebruik het formulier hierboven om een nieuwe opleiding toe te voegen.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="opleidingenTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Naam</th>
                                            <th>Studenten</th>
                                            <th>Acties</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($opleidingen as $opleiding): ?>
                                            <tr>
                                                <td><?php echo $opleiding['id']; ?></td>
                                                <td><?php echo htmlspecialchars($opleiding['naam']); ?></td>
                                                <td>
                                                    <?php if ($opleiding['students_count'] > 0): ?>
                                                        <span class="badge bg-success"><?php echo $opleiding['students_count']; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-primary edit-btn" data-bs-toggle="modal" data-bs-target="#editModal" 
                                                                data-id="<?php echo $opleiding['id']; ?>"
                                                                data-naam="<?php echo htmlspecialchars($opleiding['naam']); ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger delete-btn" data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                                data-id="<?php echo $opleiding['id']; ?>"
                                                                data-naam="<?php echo htmlspecialchars($opleiding['naam']); ?>"
                                                                data-inuse="<?php echo ($opleiding['students_count'] > 0) ? '1' : '0'; ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Opleiding bewerken</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_naam" class="form-label">Naam <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_naam" name="naam" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" name="edit_opleiding" class="btn btn-primary">Wijzigingen opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Opleiding verwijderen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Weet je zeker dat je deze opleiding wilt verwijderen?</p>
                    <p id="delete_info" class="fw-bold"></p>
                    <div id="delete_warning" class="alert alert-danger d-none">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Deze opleiding kan niet worden verwijderd omdat deze in gebruik is door studenten.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <form method="post" action="">
                        <input type="hidden" name="id" id="delete_id">
                        <button type="submit" name="delete_opleiding" id="confirm_delete" class="btn btn-danger">Verwijderen</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Initialiseer DataTables
        $('#opleidingenTable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/nl-NL.json'
            },
            pageLength: 15,
            responsive: true
        });
        
        // Bewerk modal vullen
        $('.edit-btn').click(function() {
            var id = $(this).data('id');
            var naam = $(this).data('naam');
            
            $('#edit_id').val(id);
            $('#edit_naam').val(naam);
        });
        
        // Verwijder modal vullen
        $('.delete-btn').click(function() {
            var id = $(this).data('id');
            var naam = $(this).data('naam');
            var inUse = $(this).data('inuse') === '1';
            
            $('#delete_id').val(id);
            $('#delete_info').text('ID: ' + id + ' - ' + naam);
            
            if (inUse) {
                $('#delete_warning').removeClass('d-none');
                $('#confirm_delete').prop('disabled', true);
            } else {
                $('#delete_warning').addClass('d-none');
                $('#confirm_delete').prop('disabled', false);
            }
        });
    });
    </script>
</body>
</html>