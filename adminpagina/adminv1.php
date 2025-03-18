<?php
session_start();

// Controleer of de gebruiker ingelogd is
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MaakLab Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="dashboard-container">
        <header>
            <div class="logo">
                <h1><i class="fas fa-print"></i> MaakLab Admin</h1>
            </div>
            <div class="user-info">
                <span>Welkom, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Uitloggen</a>
            </div>
        </header>
        
        <div class="content">
            <nav class="sidebar">
                <ul>
                    <li class="active" data-tab="tab-overview"><i class="fas fa-home"></i> Overzicht</li>
                    <li data-tab="tab-reservations"><i class="fas fa-calendar-alt"></i> Reserveringen</li>
                    <li data-tab="tab-printers"><i class="fas fa-print"></i> Printers</li>
                    <li data-tab="tab-users"><i class="fas fa-users"></i> Gebruikers</li>
                </ul>
            </nav>
            
            <main>
                <!-- Overzicht Tab -->
                <section id="tab-overview" class="tab-content active">
                    <h2>Dashboard Overzicht</h2>
                    
                    <div class="stats-container">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-print"></i></div>
                            <div class="stat-info">
                                <h3>Printers</h3>
                                <p id="printer-count">Laden...</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-users"></i></div>
                            <div class="stat-info">
                                <h3>Gebruikers</h3>
                                <p id="user-count">Laden...</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                            <div class="stat-info">
                                <h3>Actieve Reserveringen</h3>
                                <p id="active-reservations">Laden...</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="recent-activity">
                        <h3>Recente Reserveringen</h3>
                        <div class="table-container">
                            <table id="recent-reservations">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Gebruiker</th>
                                        <th>Printer</th>
                                        <th>Start</th>
                                        <th>Einde</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="6">Laden...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                
                <!-- Reserveringen Tab -->
                <section id="tab-reservations" class="tab-content">
                    <div class="section-header">
                        <h2>Reserveringen Beheren</h2>
                        <button id="add-reservation-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Nieuwe Reservering</button>
                    </div>
                    
                    <div class="table-container">
                        <table id="reservations-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Gebruiker</th>
                                    <th>Printer</th>
                                    <th>Reservering Datum</th>
                                    <th>Start Tijd</th>
                                    <th>Eind Tijd</th>
                                    <th>PIN</th>
                                    <th>Acties</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="8">Laden...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Reservering Modal -->
                    <div id="reservation-modal" class="modal">
                        <div class="modal-content">
                            <span class="close">&times;</span>
                            <h2 id="reservation-modal-title">Reservering Toevoegen</h2>
                            <form id="reservation-form">
                                <input type="hidden" id="reservation-id">
                                
                                <div class="form-group">
                                    <label for="user-select">Gebruiker:</label>
                                    <select id="user-select" required></select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="printer-select">Printer:</label>
                                    <select id="printer-select" required></select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="start-datetime">Start Tijd:</label>
                                    <input type="datetime-local" id="start-datetime" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="end-datetime">Eind Tijd:</label>
                                    <input type="datetime-local" id="end-datetime" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="pin-code">PIN Code (8 cijfers):</label>
                                    <input type="text" id="pin-code" pattern="[0-9]{8}" maxlength="8" placeholder="Bijv. 12345678" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="filament-color">Filament Kleur:</label>
                                    <input type="text" id="filament-color">
                                </div>
                                
                                <div class="form-group">
                                    <label for="filament-type">Filament Type:</label>
                                    <input type="text" id="filament-type">
                                </div>
                                
                                <div class="form-group">
                                    <label for="comment">Opmerking:</label>
                                    <textarea id="comment" rows="3"></textarea>
                                </div>
                                
                                <div class="form-buttons">
                                    <button type="submit" class="btn btn-primary">Opslaan</button>
                                    <button type="button" class="btn" id="cancel-reservation">Annuleren</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
                
                <!-- Printers Tab -->
                <section id="tab-printers" class="tab-content">
                    <div class="section-header">
                        <h2>Printers Beheren</h2>
                        <button id="add-printer-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Nieuwe Printer</button>
                    </div>
                    
                    <div class="table-container">
                        <table id="printers-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Naam</th>
                                    <th>Status</th>
                                    <th>Laatste Status Update</th>
                                    <th>Info</th>
                                    <th>Acties</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6">Laden...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Printer Modal -->
                    <div id="printer-modal" class="modal">
                        <div class="modal-content">
                            <span class="close">&times;</span>
                            <h2 id="printer-modal-title">Printer Toevoegen</h2>
                            <form id="printer-form">
                                <input type="hidden" id="printer-id">
                                
                                <div class="form-group">
                                    <label for="printer-name">Naam:</label>
                                    <input type="text" id="printer-name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="printer-status">Status:</label>
                                    <select id="printer-status" required>
                                        <option value="Beschikbaar">Beschikbaar</option>
                                        <option value="In Onderhoud">In Onderhoud</option>
                                        <option value="Buiten Dienst">Buiten Dienst</option>
                                        <option value="Gereserveerd">Gereserveerd</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="printer-info">Info:</label>
                                    <textarea id="printer-info" rows="3"></textarea>
                                </div>
                                
                                <div class="form-buttons">
                                    <button type="submit" class="btn btn-primary">Opslaan</button>
                                    <button type="button" class="btn" id="cancel-printer">Annuleren</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
                
                <!-- Gebruikers Tab -->
                <section id="tab-users" class="tab-content">
                    <div class="section-header">
                        <h2>Gebruikers Beheren</h2>
                        <button id="add-user-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Nieuwe Gebruiker</button>
                    </div>
                    
                    <div class="table-container">
                        <table id="users-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Naam</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Aanmaak Datum</th>
                                    <th>Laatste Login</th>
                                    <th>Acties</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7">Laden...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Gebruiker Modal -->
                    <div id="user-modal" class="modal">
                        <div class="modal-content">
                            <span class="close">&times;</span>
                            <h2 id="user-modal-title">Gebruiker Toevoegen</h2>
                            <form id="user-form">
                                <input type="hidden" id="user-id">
                                
                                <div class="form-group">
                                    <label for="user-voornaam">Voornaam:</label>
                                    <input type="text" id="user-voornaam" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="user-naam">Achternaam:</label>
                                    <input type="text" id="user-naam" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="user-email">Email:</label>
                                    <input type="email" id="user-email" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="user-telnr">Telefoonnummer:</label>
                                    <input type="text" id="user-telnr">
                                </div>
                                
                                <div class="form-group">
                                    <label for="user-ww">Wachtwoord:</label>
                                    <input type="password" id="user-ww">
                                    <small>Laat leeg om wachtwoord niet te wijzigen bij bewerken</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="user-role">Rol:</label>
                                    <select id="user-role" required>
                                        <option value="student">Student</option>
                                        <option value="docent">Docent</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="user-vives-nr">Vives Nummer:</label>
                                    <input type="text" id="user-vives-nr" placeholder="Bijv. R0996329">
                                </div>
                                
                                <div class="form-buttons">
                                    <button type="submit" class="btn btn-primary">Opslaan</button>
                                    <button type="button" class="btn" id="cancel-user">Annuleren</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            </main>
        </div>
        
        <footer>
            <p>&copy; 2025 MaakLab Badge Systeem | Ontwikkeld door Lars Van De Kerkhove</p>
        </footer>
    </div>
    
    <div id="confirmation-modal" class="modal">
        <div class="modal-content">
            <h3>Bevestig Verwijderen</h3>
            <p id="confirmation-message"></p>
            <div class="form-buttons">
                <button id="confirm-delete" class="btn btn-danger">Verwijderen</button>
                <button id="cancel-delete" class="btn">Annuleren</button>
            </div>
        </div>
    </div>

    <script src="dashboard.js"></script>
</body>
</html>