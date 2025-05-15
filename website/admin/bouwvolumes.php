<?php
// Admin toegang controle
require_once 'admin.php';

$pageTitle = 'Bouwvolume beheer - 3D Printer Reserveringssysteem';
$currentPage = 'admin-bouwvolumes';

// Verwerk formulieren
$message = '';
$error = '';

// Nieuw bouwvolume toevoegen
if (isset($_POST['add_bouwvolume'])) {
    $lengte = intval($_POST['lengte'] ?? 0);
    $breedte = intval($_POST['breedte'] ?? 0);
    $hoogte = intval($_POST['hoogte'] ?? 0);
    
    if ($lengte <= 0 || $breedte <= 0 || $hoogte <= 0) {
        $error = "Alle afmetingen moeten groter zijn dan 0.";
    } else {
        try {
            // Genereer een nieuwe ID
            $stmtMaxId = $conn->query("SELECT MAX(id) as maxId FROM bouwvolume");
            $result = $stmtMaxId->fetch();
            $newId = ($result['maxId'] ?? 0) + 1;
            
            $stmt = $conn->prepare("
                INSERT INTO bouwvolume (id, lengte, breedte, hoogte) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$newId, $lengte, $breedte, $hoogte]);
            
            $message = "Nieuw bouwvolume ($lengte x $breedte x $hoogte mm) is succesvol toegevoegd.";
        } catch (PDOException $e) {
            $error = "Fout bij toevoegen: " . $e->getMessage();
        }
    }
}

// Bouwvolume bewerken
if (isset($_POST['edit_bouwvolume'])) {
    $id = intval($_POST['id']);
    $lengte = intval($_POST['lengte'] ?? 0);
    $breedte = intval($_POST['breedte'] ?? 0);
    $hoogte = intval($_POST['hoogte'] ?? 0);
    
    if ($lengte <= 0 || $breedte <= 0 || $hoogte <= 0) {
        $error = "Alle afmetingen moeten groter zijn dan 0.";
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE bouwvolume 
                SET lengte = ?, breedte = ?, hoogte = ? 
                WHERE id = ?
            ");
            $stmt->execute([$lengte, $breedte, $hoogte, $id]);
            
            $message = "Bouwvolume #$id ($lengte x $breedte x $hoogte mm) is succesvol bijgewerkt.";
        } catch (PDOException $e) {
            $error = "Fout bij bewerken: " . $e->getMessage();
        }
    }
}

// Bouwvolume verwijderen
if (isset($_POST['delete_bouwvolume']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    try {
        // Controleren of het bouwvolume in gebruik is door printers
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM Printer WHERE Bouwvolume_id = ?");
        $checkStmt->execute([$id]);
        $printerCount = $checkStmt->fetch()['count'];
        
        if ($printerCount > 0) {
            $error = "Dit bouwvolume kan niet worden verwijderd omdat het nog door $printerCount printer(s) wordt gebruikt.";
        } else {
            $stmt = $conn->prepare("DELETE FROM bouwvolume WHERE id = ?");
            $stmt->execute([$id]);
            
            $message = "Bouwvolume #$id is succesvol verwijderd.";
        }
    } catch (PDOException $e) {
        $error = "Fout bij verwijderen: " . $e->getMessage();
    }
}

// Haal alle bouwvolumes op
try {
    $stmt = $conn->prepare("
        SELECT b.*, COUNT(p.Printer_ID) as printer_count
        FROM bouwvolume b
        LEFT JOIN Printer p ON b.id = p.Bouwvolume_id
        GROUP BY b.id
        ORDER BY b.id
    ");
    $stmt->execute();
    $bouwvolumes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Fout bij ophalen bouwvolumes: " . $e->getMessage();
    $bouwvolumes = [];
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
                            <a class="nav-link active" href="bouwvolumes.php">
                                <i class="fas fa-cube me-2"></i>
                                Bouwvolumes
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
                            <a class="nav-link" href="opleidingen.php">
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
                    <h1 class="h2">Bouwvolume beheer</h1>
                </div>

                <!-- Alerts voor feedback -->
                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Nieuw bouwvolume toevoegen -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Nieuw bouwvolume toevoegen</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="lengte" class="form-label">Lengte (mm)</label>
                                    <input type="number" class="form-control" id="lengte" name="lengte" min="1" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="breedte" class="form-label">Breedte (mm)</label>
                                    <input type="number" class="form-control" id="breedte" name="breedte" min="1" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="hoogte" class="form-label">Hoogte (mm)</label>
                                    <input type="number" class="form-control" id="hoogte" name="hoogte" min="1" required>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" name="add_bouwvolume" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-1"></i> Toevoegen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bouwvolume overzicht -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Beschikbare bouwvolumes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bouwvolumes)): ?>
                            <p class="text-muted">Er zijn geen bouwvolumes gevonden. Voeg een nieuw bouwvolume toe via het formulier hierboven.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="bouwvolumeTable">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Lengte (mm)</th>
                                            <th>Breedte (mm)</th>
                                            <th>Hoogte (mm)</th>
                                            <th>Volume (cm³)</th>
                                            <th>Printers</th>
                                            <th>Acties</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bouwvolumes as $bouwvolume): ?>
                                            <?php 
                                                // Bereken volume in cm³
                                                $volume = ($bouwvolume['lengte'] * $bouwvolume['breedte'] * $bouwvolume['hoogte']) / 1000;
                                            ?>
                                            <tr>
                                                <td><?php echo $bouwvolume['id']; ?></td>
                                                <td><?php echo $bouwvolume['lengte']; ?></td>
                                                <td><?php echo $bouwvolume['breedte']; ?></td>
                                                <td><?php echo $bouwvolume['hoogte']; ?></td>
                                                <td><?php echo number_format($volume, 1); ?></td>
                                                <td>
                                                    <?php if ($bouwvolume['printer_count'] > 0): ?>
                                                        <span class="badge bg-info"><?php echo $bouwvolume['printer_count']; ?> printer(s)</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Niet in gebruik</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary edit-btn" data-bs-toggle="modal" data-bs-target="#editModal" 
                                                            data-id="<?php echo $bouwvolume['id']; ?>"
                                                            data-lengte="<?php echo $bouwvolume['lengte']; ?>"
                                                            data-breedte="<?php echo $bouwvolume['breedte']; ?>"
                                                            data-hoogte="<?php echo $bouwvolume['hoogte']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger delete-btn" data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                            data-id="<?php echo $bouwvolume['id']; ?>"
                                                            data-inuse="<?php echo $bouwvolume['printer_count'] > 0 ? '1' : '0'; ?>"
                                                            data-size="<?php echo $bouwvolume['lengte'] . ' x ' . $bouwvolume['breedte'] . ' x ' . $bouwvolume['hoogte']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
                    <h5 class="modal-title" id="editModalLabel">Bouwvolume bewerken</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_lengte" class="form-label">Lengte (mm)</label>
                            <input type="number" class="form-control" id="edit_lengte" name="lengte" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_breedte" class="form-label">Breedte (mm)</label>
                            <input type="number" class="form-control" id="edit_breedte" name="breedte" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_hoogte" class="form-label">Hoogte (mm)</label>
                            <input type="number" class="form-control" id="edit_hoogte" name="hoogte" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" name="edit_bouwvolume" class="btn btn-primary">Opslaan</button>
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
                    <h5 class="modal-title" id="deleteModalLabel">Bouwvolume verwijderen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Weet je zeker dat je dit bouwvolume wilt verwijderen?</p>
                    <p id="delete_info"></p>
                    <div id="delete_warning" class="alert alert-danger d-none">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Dit bouwvolume is momenteel in gebruik door een of meerdere printers en kan niet worden verwijderd.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <form method="post" action="">
                        <input type="hidden" name="id" id="delete_id">
                        <button type="submit" name="delete_bouwvolume" id="confirm_delete" class="btn btn-danger">Verwijderen</button>
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
        $('#bouwvolumeTable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/nl-NL.json'
            },
            pageLength: 10,
            responsive: true
        });

        // Edit modal vullen
        $('.edit-btn').click(function() {
            const id = $(this).data('id');
            const lengte = $(this).data('lengte');
            const breedte = $(this).data('breedte');
            const hoogte = $(this).data('hoogte');
            
            $('#edit_id').val(id);
            $('#edit_lengte').val(lengte);
            $('#edit_breedte').val(breedte);
            $('#edit_hoogte').val(hoogte);
        });

        // Delete modal vullen
        $('.delete-btn').click(function() {
            const id = $(this).data('id');
            const inUse = $(this).data('inuse') === '1';
            const size = $(this).data('size');
            
            $('#delete_id').val(id);
            $('#delete_info').text(`Bouwvolume #${id} (${size} mm)`);
            
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