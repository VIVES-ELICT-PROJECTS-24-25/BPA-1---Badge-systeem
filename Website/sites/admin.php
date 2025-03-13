<?php
// admin.php - Beheerderspagina voor MaakLab
session_start();

// Controleer of de gebruiker is ingelogd en een admin rol heeft
// Dit zou normaal gesproken worden geverifieerd met een login systeem
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Redirect naar login pagina als niet ingelogd of geen admin
    header("Location: sites/login.php");
    exit;
}

// Base URL voor API calls
$apiBaseUrl = ""; // Vul hier de basis URL in indien nodig (bv. "https://example.com/api")

// Functie om API calls te maken
function callAPI($method, $endpoint, $data = null) {
    global $apiBaseUrl;
    $url = $apiBaseUrl . $endpoint;
    
    $curl = curl_init();
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ];
    
    if ($data !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    
    curl_setopt_array($curl, $options);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

// Functie om e-mail te versturen bij wijzigingen
function sendNotificationEmail($userEmail, $userName, $subject, $message) {
    $headers = "From: maaklab@example.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $body = "
    <html>
    <head>
        <title>$subject</title>
    </head>
    <body>
        <p>Beste $userName,</p>
        <p>$message</p>
        <p>Met vriendelijke groeten,<br>MaakLab Team</p>
    </body>
    </html>
    ";
    
    return mail($userEmail, $subject, $body, $headers);
}

// Verwerk form submits
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verwerk verschillende forms op basis van de action
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            // ... (rest of the switch statement remains the same as in the original PHP file)
        }
    }
}

// Haal data op voor de pagina
$users = callAPI('GET', 'sites/gebruiker_api.php', null);
$printers = callAPI('GET', 'sites/printer_api.php', null);
$reservations = callAPI('GET', 'sites/reservatie_api.php', null);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Styles/mystyle.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Admin</title>
    <style>

    </style>
</head>
<body>   
    <nav class="navbar">
      <div class="nav-container">
          <a href="login.php" class="nav-logo">
              <img src="images/vives smile.svg" alt="Vives Logo" />
          </a>
          
          <button class="nav-toggle" aria-label="Open menu">
              <span class="hamburger"></span>
          </button>
  
          <ul class="nav-menu">
              <li class="nav-item">
                  <a href="reservatie.php" class="nav-link">
                      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="12" cy="12" r="10"/>
                          <line x1="12" y1="8" x2="12" y2="16"/>
                          <line x1="8" y1="12" x2="16" y2="12"/>
                      </svg>
                      Reserveer een printer
                  </a>
              </li>
              <li class="nav-item">
                  <a href="mijnKalender.php" class="nav-link">
                      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                          <line x1="16" y1="2" x2="16" y2="6"/>
                          <line x1="8" y1="2" x2="8" y2="6"/>
                          <line x1="3" y1="10" x2="21" y2="10"/>
                      </svg>
                        reservaties
                  </a>
              </li>
              <li class="nav-item">
                    <a href="printers.php" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9V2h12v7"/>
                            <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                            <rect x="6" y="14" width="12" height="8"/>
                        </svg>
                        Info over printers
                    </a>
                </li>
                <li class="nav-item">
                <a href="uitlog.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Log uit
                </a>
            </li>
          </ul>
      </div>
  </nav>

  <div class="container-fluid mt-4 mb-5">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">MaakLab Admin Dashboard</h1>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="agenda-tab" data-bs-toggle="tab" href="#agenda" role="tab">Agenda & Reserveringen</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="printers-tab" data-bs-toggle="tab" href="#printers" role="tab">Printers Beheren</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="users-tab" data-bs-toggle="tab" href="#users" role="tab">Gebruikers & Rollen</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="control-tab" data-bs-toggle="tab" href="#control" role="tab">Printer Besturing</a>
                    </li>
                </ul>
                
                <!-- Rest of the content from adminv1.php remains the same -->
                <!-- Tab Content -->
<div class="tab-content" id="adminTabsContent">
    <!-- Agenda & Reserveringen Tab -->
    <div class="tab-pane fade show active" id="agenda" role="tabpanel">
        <h2>Agenda & Reserveringen</h2>
        
        <div class="card mb-4">
            <div class="card-header">
                <h4>Alle Reserveringen</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Gebruiker</th>
                                <th>Printer</th>
                                <th>Reserveringstijd</th>
                                <th>Start</th>
                                <th>Einde</th>
                                <th>Filament</th>
                                <th>Opmerking</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($reservations['data']['data']) && is_array($reservations['data']['data'])): ?>
                                <?php foreach ($reservations['data']['data'] as $reservation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reservation['Reservatie_ID']); ?></td>
                                        <td><?php echo htmlspecialchars($reservation['Voornaam'] . ' ' . $reservation['Naam']); ?></td>
                                        <td><?php echo htmlspecialchars($reservation['Printer_Naam']); ?></td>
                                        <td><?php echo htmlspecialchars($reservation['Date_Time_res']); ?></td>
                                        <td><?php echo htmlspecialchars($reservation['Pr_Start']); ?></td>
                                        <td><?php echo htmlspecialchars($reservation['Pr_End']); ?></td>
                                        <td>
                                            <?php 
                                                $filament = [];
                                                if (!empty($reservation['Filament_Type'])) $filament[] = $reservation['Filament_Type'];
                                                if (!empty($reservation['Filament_Kleur'])) $filament[] = $reservation['Filament_Kleur'];
                                                echo htmlspecialchars(implode(', ', $filament));
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($reservation['Comment'] ?? ''); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editReservationModal" 
                                                data-id="<?php echo $reservation['Reservatie_ID']; ?>"
                                                data-start="<?php echo $reservation['Pr_Start']; ?>"
                                                data-end="<?php echo $reservation['Pr_End']; ?>"
                                                data-comment="<?php echo htmlspecialchars($reservation['Comment'] ?? ''); ?>">
                                                Bewerken
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                 <tr>
                                    <td colspan="9" class="text-center">Geen reserveringen gevonden</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Printers Beheren Tab -->
    <div class="tab-pane fade" id="printers" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Printers Beheren</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPrinterModal">
                Nieuwe Printer Toevoegen
            </button>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h4>Alle Printers</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Naam</th>
                                <th>Status</th>
                                <th>Laatste Status Wijziging</th>
                                <th>Info</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($printers['data']['data']) && is_array($printers['data']['data'])): ?>
                                <?php foreach ($printers['data']['data'] as $printer): ?>
                                    <?php 
                                        $statusClass = '';
                                        switch($printer['Status']) {
                                            case 'Beschikbaar':
                                                $statusClass = 'status-available';
                                                break;
                                            case 'Onderhoud':
                                                $statusClass = 'status-maintenance';
                                                break;
                                            case 'Niet Beschikbaar':
                                                $statusClass = 'status-unavailable';
                                                break;
                                        }
                                    ?>
                                    <tr class="<?php echo $statusClass; ?>">
                                        <td><?php echo htmlspecialchars($printer['Printer_ID']); ?></td>
                                        <td><?php echo htmlspecialchars($printer['Naam']); ?></td>
                                        <td><?php echo htmlspecialchars($printer['Status']); ?></td>
                                        <td><?php echo htmlspecialchars($printer['Laatste_Status_Change']); ?></td>
                                        <td><?php echo htmlspecialchars($printer['Info'] ?? ''); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editPrinterModal" 
                                                data-id="<?php echo $printer['Printer_ID']; ?>"
                                                data-name="<?php echo htmlspecialchars($printer['Naam']); ?>"
                                                data-status="<?php echo htmlspecialchars($printer['Status']); ?>"
                                                data-info="<?php echo htmlspecialchars($printer['Info'] ?? ''); ?>">
                                                Bewerken
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">Geen printers gevonden</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gebruikers & Rollen Tab -->
    <div class="tab-pane fade" id="users" role="tabpanel">
        <h2>Gebruikers & Rollen</h2>
        
        <div class="card">
            <div class="card-header">
                <h4>Alle Gebruikers</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Naam</th>
                                <th>Email</th>
                                <th>Telefoonnummer</th>
                                <th>Rol</th>
                                <th>Aangemaakt op</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($users['data']['data']) && is_array($users['data']['data'])): ?>
                                <?php foreach ($users['data']['data'] as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['User_ID']); ?></td>
                                        <td><?php echo htmlspecialchars($user['Voornaam'] . ' ' . $user['Naam']); ?></td>
                                        <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['Telnr'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($user['rol']); ?></td>
                                        <td><?php echo htmlspecialchars($user['Aanmaak_Acc']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editUserRoleModal" 
                                                data-id="<?php echo $user['User_ID']; ?>"
                                                data-name="<?php echo htmlspecialchars($user['Voornaam'] . ' ' . $user['Naam']); ?>"
                                                data-role="<?php echo htmlspecialchars($user['rol']); ?>">
                                                Rol Wijzigen
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">Geen gebruikers gevonden</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Printer Besturing Tab -->
    <div class="tab-pane fade" id="control" role="tabpanel">
        <h2>Printer Besturing</h2>
        
        <div class="row">
            <?php if (isset($printers['data']['data']) && is_array($printers['data']['data'])): ?>
                <?php foreach ($printers['data']['data'] as $printer): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5><?php echo htmlspecialchars($printer['Naam']); ?></h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Status:</strong> <?php echo htmlspecialchars($printer['Status']); ?></p>
                                <p><strong>Informatie:</strong> <?php echo htmlspecialchars($printer['Info'] ?? 'Geen informatie beschikbaar'); ?></p>
                                
                                <form method="post" action="">
                                    <input type="hidden" name="action" value="change_printer_status">
                                    <input type="hidden" name="printer_id" value="<?php echo $printer['Printer_ID']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="status_<?php echo $printer['Printer_ID']; ?>" class="form-label">Wijzig Status:</label>
                                        <select class="form-select" id="status_<?php echo $printer['Printer_ID']; ?>" name="status">
                                            <option value="Beschikbaar" <?php echo ($printer['Status'] === 'Beschikbaar') ? 'selected' : ''; ?>>Beschikbaar</option>
                                            <option value="Onderhoud" <?php echo ($printer['Status'] === 'Onderhoud') ? 'selected' : ''; ?>>Onderhoud</option>
                                            <option value="Niet Beschikbaar" <?php echo ($printer['Status'] === 'Niet Beschikbaar') ? 'selected' : ''; ?>>Niet Beschikbaar</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Status Bijwerken</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">Geen printers gevonden</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modals -->

<!-- Add Printer Modal -->
<div class="modal fade" id="addPrinterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nieuwe Printer Toevoegen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_printer">
                    
                    <div class="mb-3">
                        <label for="printer_naam" class="form-label">Printer Naam</label>
                        <input type="text" class="form-control" id="printer_naam" name="printer_naam" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="printer_status" class="form-label">Status</label>
                        <select class="form-select" id="printer_status" name="printer_status" required>
                            <option value="Beschikbaar">Beschikbaar</option>
                            <option value="Onderhoud">Onderhoud</option>
                            <option value="Niet Beschikbaar">Niet Beschikbaar</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="printer_info" class="form-label">Informatie</label>
                        <textarea class="form-control" id="printer_info" name="printer_info" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" class="btn btn-primary">Toevoegen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Printer Modal -->
<div class="modal fade" id="editPrinterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Printer Bewerken</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_printer">
                    <input type="hidden" name="printer_id" id="edit_printer_id">
                    
                    <div class="mb-3">
                        <label for="edit_printer_naam" class="form-label">Printer Naam</label>
                        <input type="text" class="form-control" id="edit_printer_naam" name="printer_naam" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_printer_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_printer_status" name="printer_status" required>
                            <option value="Beschikbaar">Beschikbaar</option>
                            <option value="Onderhoud">Onderhoud</option>
                            <option value="Niet Beschikbaar">Niet Beschikbaar</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_printer_info" class="form-label">Informatie</label>
                        <textarea class="form-control" id="edit_printer_info" name="printer_info" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" class="btn btn-primary">Opslaan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Reservation Modal -->
<div class="modal fade" id="editReservationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reservering Bewerken</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_reservation">
                    <input type="hidden" name="reservation_id" id="edit_reservation_id">
                    
                    <div class="mb-3">
                        <label for="edit_start_time" class="form-label">Starttijd</label>
                        <input type="datetime-local" class="form-control" id="edit_start_time" name="start_time" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_end_time" class="form-label">Eindtijd</label>
                        <input type="datetime-local" class="form-control" id="edit_end_time" name="end_time" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_reservation_comment" class="form-label">Opmerking</label>
                        <textarea class="form-control" id="edit_reservation_comment" name="comment" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" class="btn btn-primary">Opslaan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Role Modal -->
<div class="modal fade" id="editUserRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gebruikersrol Wijzigen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_user_role">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <p>Gebruiker: <strong id="edit_user_name"></strong></p>
                    
                    <div class="mb-3">
                        <label for="edit_user_role" class="form-label">Nieuwe Rol</label>
                        <select class="form-select" id="edit_user_role" name="role" required>
                            <option value="gebruiker">Gebruiker</option>
                            <option value="beheerder">Beheerder</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" class="btn btn-primary">Rol Wijzigen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Touchscreen Control Modal -->
<div class="modal fade" id="touchscreenControlModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Printer Touchscreen Besturing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5>Besturing</h5>
                            </div>
                            <div class="card-body printer-control-panel">
                                <div class="mb-3">
                                    <p>Printer: <strong id="touchscreen-printer-name">-</strong></p>
                                    <p>Status: <span id="touchscreen-printer-status">-</span></p>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-primary control-button" data-action="start">
                                        <i class="bi bi-play-fill"></i> Start Print
                                    </button>
                                    <button type="button" class="btn btn-warning control-button" data-action="pause">
                                        <i class="bi bi-pause-fill"></i> Pauze
                                    </button>
                                    <button type="button" class="btn btn-danger control-button" data-action="stop">
                                        <i class="bi bi-stop-fill"></i> Stop Print
                                    </button>
                                    <button type="button" class="btn btn-info control-button" data-action="home">
                                        <i class="bi bi-house-fill"></i> Home Axes
                                    </button>
                                    <button type="button" class="btn btn-secondary control-button" data-action="eject">
                                        <i class="bi bi-eject-fill"></i> Eject Filament
                                    </button>
                                </div>
                                
                                <div class="mt-3 feedback-message"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Printer Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6>Temperaturen</h6>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-danger" role="progressbar" style="width: 75%;" id="extruder-temp">
                                            Extruder: 195°C / 200°C
                                        </div>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: 60%;" id="bed-temp">
                                            Bed: 55°C / 60°C
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <h6>Voortgang Print</h6>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: 45%;" id="print-progress">
                                            45% Voltooid
                                        </div>
                                    </div>
                                    <small class="text-muted">Geschatte tijd resterend: <span id="time-remaining">1:23:45</span></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Sluiten</button>
            </div>
        </div>
    </div>
</div>

<!-- Filter Reservations Modal -->
<div class="modal fade" id="filterReservationsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reserveringen Filteren</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reservationFilterForm">
                    <div class="mb-3">
                        <label for="filter-user" class="form-label">Gebruiker</label>
                        <input type="text" class="form-control" id="filter-user" placeholder="Naam of e-mail">
                    </div>
                    
                    <div class="mb-3">
                        <label for="filter-printer" class="form-label">Printer</label>
                        <select class="form-select" id="filter-printer">
                            <option value="">Alle printers</option>
                            <?php if (isset($printers['data']['data']) && is_array($printers['data']['data'])): ?>
                                <?php foreach ($printers['data']['data'] as $printer): ?>
                                    <option value="<?php echo $printer['Printer_ID']; ?>"><?php echo htmlspecialchars($printer['Naam']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="filter-date-from" class="form-label">Vanaf datum</label>
                                <input type="date" class="form-control" id="filter-date-from">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="filter-date-to" class="form-label">Tot datum</label>
                                <input type="date" class="form-control" id="filter-date-to">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button type="button" class="btn btn-primary" id="apply-filters">Filters Toepassen</button>
            </div>
        </div>
    </div>
</div>

<script src="Scripts/algemene.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- JavaScript for modals and other functionality -->
<script>
    // Script voor het vullen van de edit printer modal
    document.querySelectorAll('[data-bs-target="#editPrinterModal"]').forEach(button => {
        button.addEventListener('click', function() {
            const printerId = this.getAttribute('data-id');
            const printerName = this.getAttribute('data-name');
            const printerStatus = this.getAttribute('data-status');
            const printerInfo = this.getAttribute('data-info');
            
            document.getElementById('edit_printer_id').value = printerId;
            document.getElementById('edit_printer_naam').value = printerName;
            document.getElementById('edit_printer_status').value = printerStatus;
            document.getElementById('edit_printer_info').value = printerInfo;
        });
    });
    
    // Script voor het vullen van de edit reservation modal
    document.querySelectorAll('[data-bs-target="#editReservationModal"]').forEach(button => {
        button.addEventListener('click', function() {
            const reservationId = this.getAttribute('data-id');
            const startTime = this.getAttribute('data-start');
            const endTime = this.getAttribute('data-end');
            const comment = this.getAttribute('data-comment');
            
            document.getElementById('edit_reservation_id').value = reservationId;
            
            // Formatteer de datumtijden voor datetime-local input (YYYY-MM-DDThh:mm)
            const formatDateTime = (dateTimeStr) => {
                const dt = new Date(dateTimeStr.replace(' ', 'T'));
                return dt.toISOString().slice(0, 16);
            };
            
            document.getElementById('edit_start_time').value = formatDateTime(startTime);
            document.getElementById('edit_end_time').value = formatDateTime(endTime);
            document.getElementById('edit_reservation_comment').value = comment;
        });
    });
    
    // Script voor het vullen van de edit user role modal
    // Script voor het vullen van de edit user role modal
    document.querySelectorAll('[data-bs-target="#editUserRoleModal"]').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            const userName = this.getAttribute('data-name');
            const userRole = this.getAttribute('data-role');
            
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_user_name').textContent = userName;
            document.getElementById('edit_user_role').value = userRole;
        });
    });
    
    // Actieve tab behouden na page refresh
    document.addEventListener('DOMContentLoaded', function() {
        // Controleer of er een opgeslagen tabvoorkeur is
        const activeTab = localStorage.getItem('activeAdminTab');
        
        if (activeTab) {
            // Activeer de opgeslagen tab
            const tab = document.querySelector(`#adminTabs a[href="${activeTab}"]`);
            if (tab) {
                new bootstrap.Tab(tab).show();
            }
        }
        
        // Sla actieve tab op bij tab wissel
        document.querySelectorAll('#adminTabs a').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(e) {
                localStorage.setItem('activeAdminTab', e.target.getAttribute('href'));
            });
        });
    });
    
    // Printer besturing functionaliteit
    document.addEventListener('DOMContentLoaded', function() {
        // Voeg hier eventuele realtime communicatie met printers toe
        // Dit zou via WebSockets of periodieke AJAX calls kunnen worden geïmplementeerd
        
        // Voorbeeld van een functie om printer status te updaten via AJAX
        function updatePrinterStatus(printerId, status) {
            const data = {
                action: 'change_printer_status',
                printer_id: printerId,
                status: status
            };
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Toon een succesmelding en update de UI
                    alert('Printer status succesvol bijgewerkt!');
                    // Hier zou je de UI kunnen bijwerken zonder page refresh
                } else {
                    alert('Fout bij bijwerken van printer status: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Er is een fout opgetreden bij het bijwerken van de printer status.');
            });
        }
        
        // Implementatie voor touchscreen bediening van printers
        const printerControls = document.querySelectorAll('.printer-control-panel');
        if (printerControls.length > 0) {
            printerControls.forEach(panel => {
                // Voeg event listeners toe voor touchscreen bediening
                panel.querySelectorAll('.control-button').forEach(button => {
                    button.addEventListener('click', function() {
                        const printerId = this.getAttribute('data-printer-id');
                        const action = this.getAttribute('data-action');
                        
                        // Hier zou je code kunnen toevoegen om commando's naar de printer te sturen
                        console.log(`Printer ${printerId}: ${action}`);
                        
                        // Toon feedback aan de gebruiker
                        const feedbackElement = panel.querySelector('.feedback-message');
                        if (feedbackElement) {
                            feedbackElement.textContent = `Commando "${action}" verzonden naar printer`;
                            feedbackElement.classList.add('text-success');
                            
                            // Verberg feedback na enkele seconden
                            setTimeout(() => {
                                feedbackElement.textContent = '';
                                feedbackElement.classList.remove('text-success');
                            }, 3000);
                        }
                    });
                });
            });
        }
    });
    
    // Functie om agenda en reserveringen te filteren
    function filterReservations() {
        const filterValue = document.getElementById('reservation-filter').value.toLowerCase();
        const reservationRows = document.querySelectorAll('#agenda table tbody tr');
        
        reservationRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(filterValue)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    // Automatische refresh van gegevens op de achtergrond (elke 5 minuten)
    setInterval(function() {
        // Hier zou je AJAX calls kunnen implementeren om de gegevens op te halen
        // zonder de pagina te verversen
        console.log('Achtergrond data refresh...');
    }, 300000); // 5 minuten in milliseconden
</script>
</body>
</html>