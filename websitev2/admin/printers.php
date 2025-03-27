<?php
session_start();
require_once '../config.php';

// Ensure user is admin
requireAdmin();

$currentPage = 'admin';
$pageTitle = 'Printers Beheren - Admin';

// Process deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $printerId = $_GET['delete'];
    
    // Check if printer has any reservations
    $stmt = $conn->prepare("SELECT COUNT(*) FROM reservations WHERE printer_id = ?");
    $stmt->execute([$printerId]);
    $hasReservations = $stmt->fetchColumn() > 0;
    
    if ($hasReservations) {
        setFlashMessage('Kan printer niet verwijderen: er zijn actieve reserveringen voor deze printer.', 'danger');
    } else {
        $stmt = $conn->prepare("DELETE FROM printers WHERE id = ?");
        $result = $stmt->execute([$printerId]);
        
        if ($result) {
            setFlashMessage('Printer succesvol verwijderd.', 'success');
        } else {
            setFlashMessage('Fout bij het verwijderen van de printer.', 'danger');
        }
    }
    redirect('admin/printers.php');
}

// Get all printers
$stmt = $conn->query("SELECT * FROM printers ORDER BY name");
$printers = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Printers Beheren</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Printers</li>
            </ol>
        </nav>
    </div>
    
    <div class="mb-4">
        <a href="add-printer.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Nieuwe Printer Toevoegen
        </a>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Naam</th>
                            <th>Model</th>
                            <th>Locatie</th>
                            <th>Kleur</th>
                            <th>Status</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($printers) > 0): ?>
                            <?php foreach ($printers as $printer): ?>
                                <tr>
                                    <td><?php echo $printer['id']; ?></td>
                                    <td><?php echo htmlspecialchars($printer['name']); ?></td>
                                    <td><?php echo htmlspecialchars($printer['model']); ?></td>
                                    <td><?php echo htmlspecialchars($printer['location'] ?? '-'); ?></td>
                                    <td><?php echo $printer['color_capability'] ? 'Ja' : 'Nee'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            if ($printer['status'] === 'available') echo 'success';
                                            elseif ($printer['status'] === 'maintenance') echo 'warning';
                                            else echo 'danger';
                                        ?>">
                                            <?php 
                                                if ($printer['status'] === 'available') echo 'Beschikbaar';
                                                elseif ($printer['status'] === 'maintenance') echo 'Onderhoud';
                                                else echo 'Niet Beschikbaar';
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit-printer.php?id=<?php echo $printer['id']; ?>" class="btn btn-sm btn-primary me-1">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $printer['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        
                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $printer['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $printer['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $printer['id']; ?>">Bevestig Verwijdering</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Weet je zeker dat je de printer "<?php echo htmlspecialchars($printer['name']); ?>" wilt verwijderen?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                                                        <a href="printers.php?delete=<?php echo $printer['id']; ?>" class="btn btn-danger">Verwijderen</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Geen printers gevonden.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>