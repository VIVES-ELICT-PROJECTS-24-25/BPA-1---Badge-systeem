let scanTimeoutId = null;
const SCAN_TIMEOUT = 30000; // 30 seconden in milliseconden

// Firebase configuration
let firebaseConfig;
let currentCode = '';
let currentUser = null;
let connectionStatus = {
    firebase: false,
    database: false
};

// Initialize the application
document.addEventListener('DOMContentLoaded', () => {
    initClock();
    initFirebase();
    showWelcomePage();
});

// Initialize clock
function initClock() {
    updateClock();
    setInterval(updateClock, 1000);
}

// Update clock
function updateClock() {
    const now = new Date();
    const timeElement = document.getElementById('current-time');
    const dateElement = document.getElementById('current-date');
    
    // Format time: HH:MM:SS
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    timeElement.textContent = `${hours}:${minutes}:${seconds}`;
    
    // Format date: DD-MM-YYYY
    const day = String(now.getDate()).padStart(2, '0');
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const year = now.getFullYear();
    dateElement.textContent = `${day}-${month}-${year}`;
}

// Initialize Firebase
function initFirebase() {
    // Check if Firebase is already initialized
    if (firebase.apps.length) {
        console.log("Firebase was already initialized");
        return;
    }
    
    try {
        // Haal Firebase config op van de server om gevoelige gegevens te beschermen
        fetch('php/get_firebase_config.php')
        .then(response => response.json())
        .then(config => {
            if (config.error) {
                throw new Error(config.error);
            }
            
            // Initialiseer Firebase met de config van de server
            firebase.initializeApp(config);
            console.log("Firebase initialized successfully");
            
            // Set up Firebase connection monitoring
            const connectedRef = firebase.database().ref(".info/connected");
            connectedRef.on("value", (snap) => {
                connectionStatus.firebase = snap.val() === true;
                console.log("Firebase connection status:", connectionStatus.firebase);
                
                if (!connectionStatus.firebase) {
                    showConnectionError("Verbinding met Firebase verloren. Probeer opnieuw te laden.");
                } else {
                    // Remove error message if it exists
                    const existingErrors = document.querySelectorAll('.connection-error');
                    existingErrors.forEach(elem => elem.remove());
                }
            });
            
            // Set up listener for RFID card scans
            const rfidRef = firebase.database().ref('rfid_latest_scan');
            console.log("Monitoring Firebase path: rfid_latest_scan");
            
            rfidRef.on('value', (snapshot) => {
                const rfidData = snapshot.val();
                console.log("RFID data received:", rfidData);
                
                if (rfidData && rfidData.id) {
                    console.log("Card ID detected:", rfidData.id);
                    const cardId = rfidData.id;
                    
                    // Alleen verwerken als we op de scan pagina zijn
                    const scanPage = document.getElementById('scanPage');
                    if (!scanPage.classList.contains('hidden')) {
                        verifyRFIDCard(cardId);
                    }
                }
            });
            
            // Connection is successful, check database connection
            checkDatabaseConnection();
            
        })
        .catch(error => {
            connectionStatus.firebase = false;
            console.error("Error getting Firebase config:", error);
            showConnectionError("Firebase configuratie kon niet worden geladen: " + error.message);
        });
    } catch (error) {
        connectionStatus.firebase = false;
        console.error("Firebase initialization error:", error);
        showConnectionError("Firebase initialisatie mislukt: " + error.message);
    }
}

// Check database connection
function checkDatabaseConnection() {
    fetch('php/check_connection.php')
    .then(response => response.json())
    .then(data => {
        connectionStatus.database = data.databaseConnected;
        if (!data.databaseConnected) {
            showConnectionError("Database verbinding mislukt: " + data.databaseError);
        }
        if (!data.firebaseConnected) {
            showConnectionError("Firebase verbinding mislukt: " + data.firebaseError);
        }
    })
    .catch(error => {
        connectionStatus.database = false;
        showConnectionError("Kan verbindingsstatus niet controleren. Server mogelijk offline.");
    });
}

// Show connection error
function showConnectionError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'connection-error';
    errorDiv.innerHTML = `
        <div class="error-icon">⚠️</div>
        <div class="error-message">${message}</div>
        <button onclick="location.reload()">Probeer opnieuw</button>
    `;
    
    // Remove any existing error messages
    const existingErrors = document.querySelectorAll('.connection-error');
    existingErrors.forEach(elem => elem.remove());
    
    document.body.insertBefore(errorDiv, document.body.firstChild);
}

// Page Navigation Functions
function showWelcomePage() {
    // Hide all containers
    hideAllContainers();
    document.getElementById('welcomePage').classList.remove('hidden');
    
    // Reset status messages
    document.getElementById('scanStatus').textContent = '';
    document.getElementById('codeStatus').textContent = '';
    
    // Reset code input
    currentCode = '';
    updateCodeDisplay();
}

function showScanPage() {
    // Hide all containers
    hideAllContainers();
    document.getElementById('scanPage').classList.remove('hidden');
    
    // Check connections before proceeding
    if (!connectionStatus.firebase) {
        document.getElementById('scanStatus').textContent = 'Geen verbinding met Firebase. Kan kaart niet scannen.';
        return;
    }
    
    if (!connectionStatus.database) {
        document.getElementById('scanStatus').textContent = 'Geen verbinding met de database. Kan gebruiker niet verifiëren.';
        return;
    }
    
    // Toon scanning animatie en bericht
    document.getElementById('scanStatus').textContent = 'Klaar om te scannen. Houd je kaart bij de lezer...';
    document.getElementById('scanAnimation').classList.add('scanning');
    
    // Start timeout om terug te gaan naar hoofdscherm na 30 seconden
    clearTimeout(scanTimeoutId); // Clear any existing timeout
    scanTimeoutId = setTimeout(() => {
        document.getElementById('scanStatus').textContent = 'Timeout: Geen kaart gescand binnen 30 seconden.';
        setTimeout(() => {
            showWelcomePage();
        }, 2000);
    }, SCAN_TIMEOUT);
}

function showReservationsPage(user) {
    // Hide all containers
    hideAllContainers();
    document.getElementById('reservationsPage').classList.remove('hidden');
    
    // Update user information
    document.getElementById('userName').textContent = user.Voornaam;
    
    // Load user's reservations
    loadReservations(user.User_ID);
}

function showAdminDashboard(user) {
    // Hide all containers
    hideAllContainers();
    document.getElementById('adminDashboard').classList.remove('hidden');
    
    // Update admin name
    document.getElementById('adminName').textContent = user.Voornaam;
}

function showAdminSection(section) {
    // Hide all containers
    hideAllContainers();
    
    switch(section) {
        case 'printers':
            document.getElementById('adminPrinters').classList.remove('hidden');
            loadPrinters();
            break;
        case 'cards':
            document.getElementById('adminCards').classList.remove('hidden');
            loadCards();
            break;
        case 'users':
            document.getElementById('adminUsers').classList.remove('hidden');
            loadUsers();
            break;
        case 'reservations':
            document.getElementById('adminReservations').classList.remove('hidden');
            loadAllReservations();
            break;
        case 'reports':
            document.getElementById('adminReports').classList.remove('hidden');
            break;
        case 'settings':
            document.getElementById('adminSettings').classList.remove('hidden');
            loadSettings();
            break;
        default:
            document.getElementById('adminDashboard').classList.remove('hidden');
    }
}

function backToAdminDashboard() {
    showAdminDashboard(currentUser);
}

// Helper function to hide all containers
function hideAllContainers() {
    const containers = document.querySelectorAll('.container');
    containers.forEach(container => {
        container.classList.add('hidden');
    });
}

// Code Entry Functions
function addDigit(digit) {
    if (currentCode.length < 6) {
        currentCode += digit;
        updateCodeDisplay();
    }
}

function clearCode() {
    currentCode = '';
    updateCodeDisplay();
}

function updateCodeDisplay() {
    const displayElement = document.getElementById('codeInput');
    let displayText = '';
    
    for (let i = 0; i < 6; i++) {
        if (i < currentCode.length) {
            displayText += currentCode[i];
        } else {
            displayText += '_';
        }
    }
    
    displayElement.textContent = displayText;
}

function submitCode() {
    if (currentCode.length === 6) {
        // Call the server to verify the code
        verifyCode(currentCode);
    } else {
        document.getElementById('codeStatus').textContent = 'Voer een 6-cijferige code in.';
    }
}

// API Functions
function verifyRFIDCard(cardId) {

    // Clear scan timeout
    clearTimeout(scanTimeoutId);
    
    const scanStatus = document.getElementById('scanStatus');
    const scanAnimation = document.getElementById('scanAnimation');
    
    // Stop scanning animatie
    scanAnimation.classList.remove('scanning');
    
    // Toon het kaartnummer tijdelijk
    scanStatus.innerHTML = `Kaart gescand: <strong>${cardId}</strong><br>Kaart wordt geverifieerd...`;
    
    // Make an AJAX request to the server to verify the card
    fetch('php/verify_card.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ cardId: cardId })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            scanStatus.innerHTML = `Kaart <strong>${cardId}</strong> succesvol herkend!<br>Welkom, ${data.user.Voornaam}!`;
            currentUser = data.user;
            
            setTimeout(() => {
                // Redirect based on user type
                if (currentUser.Type === 'beheerder') {
                    showAdminDashboard(currentUser);
                } else {
                    showReservationsPage(currentUser);
                }
            }, 2000);
        } else {
            scanStatus.innerHTML = `Kaart <strong>${cardId}</strong> niet herkend.<br>${data.message || 'Probeer opnieuw.'}`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        scanStatus.innerHTML = `Kaart <strong>${cardId}</strong> gescand.<br>Er is een fout opgetreden: ${error.message}<br>Probeer opnieuw.`;
    });
}

function startPrinting(reservationId, printerId) {
    const button = event.target;
    const originalText = button.textContent;
    
    // Toon spinner in de knop
    button.innerHTML = '<div class="spinner"></div> Bezig...';
    button.disabled = true;
    
    fetch('php/start_printing.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            reservationId: reservationId,
            printerId: printerId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.textContent = '✓ Printer geactiveerd!';
            button.style.backgroundColor = '#27ae60';
            
            // Voeg een notificatie toe over printer activatie
            const card = button.closest('.reservation-card');
            const notification = document.createElement('div');
            notification.className = 'status-message';
            notification.innerHTML = `<strong>Succes!</strong> De printer is succesvol geactiveerd.`;
            card.appendChild(notification);
        } else {
            button.textContent = '✗ Fout bij activeren';
            button.style.backgroundColor = '#e74c3c';
            console.error('Error activating printer:', data.message);
        }
    })
    .catch(error => {
        button.textContent = '✗ Fout bij activeren';
        button.style.backgroundColor = '#e74c3c';
        console.error('Error:', error);
    });
}

// Functies voor het beheren van kaarten

// Update de editCard functie
function editCard(userId) {
    // Laad bestaande kaartgegevens
    fetch(`php/admin/get_card_details.php?userId=${userId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Maak een modal voor het bewerken van de kaart
            const cardModal = document.createElement('div');
            cardModal.className = 'modal-backdrop';
            cardModal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Kaart beheren voor ${data.card.Voornaam}</h2>
                        <span class="close-modal" onclick="closeModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form id="editCardForm" class="card-linking-form">
                            <div class="form-group">
                                <label for="rfid-number">RFID Kaartnummer:</label>
                                <input type="text" id="rfid-number" class="form-control" value="${data.card.rfidkaartnr || ''}">
                            </div>
                            <div class="form-group">
                                <label for="vives-id">VIVES ID:</label>
                                <input type="text" id="vives-id" class="form-control" value="${data.card.Vives_id || ''}">
                            </div>
                            <div class="form-group">
                                <label for="student-type">Type:</label>
                                <select id="student-type" class="form-control">
                                    <option value="student" ${data.card.Type === 'student' ? 'selected' : ''}>Student</option>
                                    <option value="medewerker" ${data.card.Type === 'medewerker' ? 'selected' : ''}>Medewerker</option>
                                    <option value="onderzoeker" ${data.card.Type === 'onderzoeker' ? 'selected' : ''}>Onderzoeker</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="opleiding">Opleiding:</label>
                                <select id="opleiding" class="form-control">
                                    <option value="">Selecteer opleiding...</option>
                                    ${data.opleidingen.map(opl => 
                                        `<option value="${opl.id}" ${data.card.opleiding_id == opl.id ? 'selected' : ''}>${opl.naam}</option>`
                                    ).join('')}
                                </select>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="action-btn" onclick="scanNewCard(${userId})">Nieuwe kaart scannen</button>
                                <button type="button" class="action-btn" onclick="saveCardChanges(${userId})">Wijzigingen opslaan</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.appendChild(cardModal);
        } else {
            alert('Kan kaartgegevens niet laden: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Er is een fout opgetreden bij het laden van de kaartgegevens.');
    });
}

// Functie om een nieuwe kaart te scannen
function scanNewCard(userId) {
    // Toon een scanning overlay
    const scanModal = document.createElement('div');
    scanModal.className = 'modal-backdrop';
    scanModal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Scan nieuwe kaart</h2>
                <span class="close-modal" onclick="closeScanModal()">&times;</span>
            </div>
            <div class="modal-body text-center">
                <div class="scan-animation" id="modalScanAnimation">
                    <div class="scan-area"></div>
                </div>
                <p class="scan-instruction">Houd de studentenkaart bij de scanner...</p>
                <div id="scanResult" class="status-message"></div>
                <button class="back-btn" onclick="closeScanModal()">Annuleren</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(scanModal);
    
    // Start scanning animatie
    document.getElementById('modalScanAnimation').classList.add('scanning');
    
    // Stel een Firebase listener in voor nieuwe scans
    const cardRef = firebase.database().ref('rfid_latest_scan');
    
    // Je kunt deze variabele gebruiken om de listener later te verwijderen
    const scanListener = cardRef.on('value', (snapshot) => {
        const scanData = snapshot.val();
        if (scanData && scanData.id) {
            // Toon het gescande kaart ID
            document.getElementById('scanResult').innerHTML = `Kaart gescand: <strong>${scanData.id}</strong>`;
            
            // Vul het kaart ID in in het formulier
            document.getElementById('rfid-number').value = scanData.id;
            
            // Stop de animatie
            document.getElementById('modalScanAnimation').classList.remove('scanning');
            
            // Verwijder de listener
            cardRef.off('value', scanListener);
            
            // Sluit het scan modal na 2 seconden
            setTimeout(() => {
                closeScanModal();
            }, 2000);
        }
    });
    
    // Timeout na 30 seconden
    setTimeout(() => {
        if (document.getElementById('scanResult').innerHTML === '') {
            document.getElementById('scanResult').textContent = 'Timeout: Geen kaart gescand binnen 30 seconden.';
            document.getElementById('modalScanAnimation').classList.remove('scanning');
            
            // Verwijder de listener
            cardRef.off('value', scanListener);
            
            // Sluit het scan modal na 2 seconden
            setTimeout(() => {
                closeScanModal();
            }, 2000);
        }
    }, 30000);
}

// Sluiten van de scan modal
function closeScanModal() {
    const scanModal = document.querySelector('.modal-backdrop');
    if (scanModal) {
        document.body.removeChild(scanModal);
    }
}

// Sluiten van de edit modal
function closeModal() {
    const modal = document.querySelector('.modal-backdrop');
    if (modal) {
        document.body.removeChild(modal);
    }
}

// Functie om wijzigingen op te slaan
function saveCardChanges(userId) {
    const rfidNumber = document.getElementById('rfid-number').value;
    const vivesId = document.getElementById('vives-id').value;
    const studentType = document.getElementById('student-type').value;
    const opleidingId = document.getElementById('opleiding').value;
    
    fetch('php/admin/update_card.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            userId: userId,
            rfidkaartnr: rfidNumber,
            vivesId: vivesId,
            type: studentType,
            opleidingId: opleidingId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Kaartgegevens succesvol bijgewerkt!');
            closeModal();
            // Herlaad de kaarten lijst
            loadCards();
        } else {
            alert('Fout bij bijwerken kaartgegevens: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Er is een fout opgetreden bij het bijwerken van de kaartgegevens.');
    });
}

// Extra CSS voor modals
document.head.insertAdjacentHTML('beforeend', `
    <style>
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #f8f9fa;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.5em;
            color: #2c3e50;
        }
        
        .close-modal {
            font-size: 1.8em;
            color: #7f8c8d;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #2c3e50;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .scan-instruction {
            margin: 20px 0;
            font-size: 1.2em;
            color: #2c3e50;
        }
    </style>
`);


function verifyCode(code) {
    const codeStatus = document.getElementById('codeStatus');
    codeStatus.textContent = 'Code wordt geverifieerd...';
    
    // Make an AJAX request to the server to verify the code
    fetch('php/verify_code.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ code: code })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            codeStatus.textContent = 'Code geaccepteerd!';
            currentUser = data.user;
            
            setTimeout(() => {
                // Redirect based on user type
                if (currentUser.Type === 'beheerder') {
                    showAdminDashboard(currentUser);
                } else {
                    showReservationsPage(currentUser);
                }
            }, 1000);
        } else {
            codeStatus.textContent = data.message || 'Ongeldige code. Probeer opnieuw.';
        }
    })
    .catch(error => {
        codeStatus.textContent = 'Er is een fout opgetreden. Probeer opnieuw.';
        console.error('Error:', error);
    });
}

function loadReservations(userId) {
    const reservationsList = document.getElementById('reservationsList');
    reservationsList.innerHTML = '<p>Reserveringen worden geladen...</p>';
    
    // Get current date and time
    const now = new Date();
    
    // Make an AJAX request to get the user's reservations
    fetch(`php/get_reservations.php?userId=${userId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.reservations.length > 0) {
            let reservationsHTML = '';
            
            data.reservations.forEach(reservation => {
                // Check if this reservation is active now
                const startTime = new Date(reservation.PRINT_START);
                const endTime = new Date(reservation.PRINT_END);
                const isActiveNow = now >= startTime && now <= endTime;
                
                reservationsHTML += `
                    <div class="reservation-card ${isActiveNow ? 'active-now' : ''}">
                        <h3>Reservering #${reservation.Reservatie_ID}</h3>
                        <div class="reservation-details">
                            <div class="detail-item">
                                <span class="detail-label">Printer:</span> ${reservation.printer_name || 'N/A'}
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Start:</span> ${formatDateTime(reservation.PRINT_START)}
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Einde:</span> ${formatDateTime(reservation.PRINT_END)}
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Filament:</span> ${reservation.filament_type || 'N/A'} - ${reservation.filament_color || 'N/A'}
                            </div>
                        </div>
                        ${isActiveNow ? `
                        <button class="start-print-btn" onclick="startPrinting(${reservation.Reservatie_ID}, ${reservation.Printer_ID})">
                            Start Printen
                        </button>
                        ` : ''}
                    </div>
                `;
            });
            
            reservationsList.innerHTML = reservationsHTML;
        } else {
            reservationsList.innerHTML = '<p>Je hebt geen actieve reserveringen.</p>';
        }
    })
    .catch(error => {
        reservationsList.innerHTML = '<p>Er is een fout opgetreden bij het laden van reserveringen.</p>';
        console.error('Error:', error);
    });
}

// Admin functions
function loadPrinters() {
    const printersList = document.getElementById('printersList');
    printersList.innerHTML = '<p>Printers worden geladen...</p>';
    
    fetch('php/admin/get_printers.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.printers.length > 0) {
            let printersHTML = `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Versie</th>
                            <th>Status</th>
                            <th>Software</th>
                            <th>Actie</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            data.printers.forEach(printer => {
                const isActive = printer.Status === 'in_gebruik' || printer.Status === 'beschikbaar';
                const statusClass = isActive ? 'status-active' : 'status-inactive';
                const statusToggleText = isActive ? 'Uitschakelen' : 'Inschakelen';
                
                printersHTML += `
                    <tr>
                        <td>${printer.Printer_ID}</td>
                        <td>${printer.Versie_Toestel || 'N/A'}</td>
                        <td><span class="${statusClass}">${printer.Status || 'N/A'}</span></td>
                        <td>${printer.Software || 'N/A'}</td>
                        <td>
                            <button class="small-btn ${isActive ? 'btn-danger' : 'btn-success'}" 
                                    onclick="togglePrinterStatus(${printer.Printer_ID}, '${printer.Status}')">
                                ${statusToggleText}
                            </button>
                            <span class="action-icon" onclick="editPrinter(${printer.Printer_ID})">✏️</span>
                            <span class="action-icon delete-icon" onclick="deletePrinter(${printer.Printer_ID})">🗑️</span>
                        </td>
                    </tr>
                `;
            });
            
            printersHTML += `
                    </tbody>
                </table>
            `;
            
            printersList.innerHTML = printersHTML;
        } else {
            printersList.innerHTML = '<p>Geen printers gevonden.</p>';
        }
    })
    .catch(error => {
        printersList.innerHTML = '<p>Er is een fout opgetreden bij het laden van printers.</p>';
        console.error('Error:', error);
    });
}

// Functie om printer status te wisselen
function togglePrinterStatus(printerId, currentStatus) {
    const newStatus = currentStatus === 'beschikbaar' || currentStatus === 'in_gebruik' ? 'onderhoud' : 'beschikbaar';
    
    fetch('php/admin/toggle_printer_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            printerId: printerId,
            newStatus: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Herlaad de printerslijst om de nieuwe status te tonen
            loadPrinters();
        } else {
            alert('Fout bij wijzigen van printerstatus: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Er is een fout opgetreden bij het wijzigen van de printerstatus.');
    });
}

function loadCards() {
    const cardsList = document.getElementById('cardsList');
    cardsList.innerHTML = '<p>Kaarten worden geladen...</p>';
    
    fetch('php/admin/get_cards.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.cards.length > 0) {
            let cardsHTML = `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Gebruiker</th>
                            <th>Kaartnummer</th>
                            <th>Vives ID</th>
                            <th>Opleiding</th>
                            <th>Type</th>
                            <th>Actie</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            data.cards.forEach(card => {
                cardsHTML += `
                    <tr>
                        <td>${card.Voornaam || 'N/A'}</td>
                        <td>${card.rfidkaartnr || 'N/A'}</td>
                        <td>${card.Vives_id || 'N/A'}</td>
                        <td>${card.opleiding_naam || 'N/A'}</td>
                        <td>${card.Type || 'N/A'}</td>
                        <td>
                            <span class="action-icon" onclick="editCard(${card.User_ID})">✏️</span>
                            <span class="action-icon delete-icon" onclick="deleteCard(${card.User_ID})">🗑️</span>
                        </td>
                    </tr>
                `;
            });
            
            cardsHTML += `
                    </tbody>
                </table>
            `;
            
            cardsList.innerHTML = cardsHTML;
        } else {
            cardsList.innerHTML = '<p>Geen kaarten gevonden.</p>';
        }
    })
    .catch(error => {
        cardsList.innerHTML = '<p>Er is een fout opgetreden bij het laden van kaarten.</p>';
        console.error('Error:', error);
    });
}

function loadUsers() {
    const usersList = document.getElementById('usersList');
    usersList.innerHTML = '<p>Gebruikers worden geladen...</p>';
    
    fetch('php/admin/get_users.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.users.length > 0) {
            let usersHTML = `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Naam</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Laatste aanmelding</th>
                            <th>Actie</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            data.users.forEach(user => {
                usersHTML += `
                    <tr>
                        <td>${user.User_ID}</td>
                        <td>${user.Voornaam} ${user.Naam}</td>
                        <td>${user.Emailadres || 'N/A'}</td>
                        <td>${user.Type || 'N/A'}</td>
                        <td>${formatDateTime(user.LaatsteAanmelding) || 'Nooit'}</td>
                        <td>
                            <span class="action-icon" onclick="editUser(${user.User_ID})">✏️</span>
                            <span class="action-icon delete-icon" onclick="deleteUser(${user.User_ID})">🗑️</span>
                        </td>
                    </tr>
                `;
            });
            
            usersHTML += `
                    </tbody>
                </table>
            `;
            
            usersList.innerHTML = usersHTML;
        } else {
            usersList.innerHTML = '<p>Geen gebruikers gevonden.</p>';
        }
    })
    .catch(error => {
        usersList.innerHTML = '<p>Er is een fout opgetreden bij het laden van gebruikers.</p>';
        console.error('Error:', error);
    });
}

function loadAllReservations() {
    const reservationsList = document.getElementById('allReservationsList');
    reservationsList.innerHTML = '<p>Reserveringen worden geladen...</p>';
    
    fetch('php/admin/get_all_reservations.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.reservations.length > 0) {
            let reservationsHTML = `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Gebruiker</th>
                            <th>Printer</th>
                            <th>Start</th>
                            <th>Einde</th>
                            <th>Pincode</th>
                            <th>Actie</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            data.reservations.forEach(reservation => {
                reservationsHTML += `
                    <tr>
                        <td>${reservation.Reservatie_ID}</td>
                        <td>${reservation.gebruiker_naam || 'N/A'}</td>
                        <td>${reservation.printer_naam || 'N/A'}</td>
                        <td>${formatDateTime(reservation.PRINT_START) || 'N/A'}</td>
                        <td>${formatDateTime(reservation.PRINT_END) || 'N/A'}</td>
                        <td>${reservation.Pincode || 'N/A'}</td>
                        <td>
                            <span class="action-icon" onclick="editReservation(${reservation.Reservatie_ID})">✏️</span>
                            <span class="action-icon delete-icon" onclick="deleteReservation(${reservation.Reservatie_ID})">🗑️</span>
                        </td>
                    </tr>
                `;
            });
            
            reservationsHTML += `
                    </tbody>
                </table>
            `;
            
            reservationsList.innerHTML = reservationsHTML;
        } else {
            reservationsList.innerHTML = '<p>Geen reserveringen gevonden.</p>';
        }
    })
    .catch(error => {
        reservationsList.innerHTML = '<p>Er is een fout opgetreden bij het laden van reserveringen.</p>';
        console.error('Error:', error);
    });
}

function loadSettings() {
    const settingsForm = document.getElementById('settingsForm');
    settingsForm.innerHTML = '<p>Instellingen worden geladen...</p>';
    
    fetch('php/admin/get_settings.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let settingsHTML = `
                <div class="form-group">
                    <label for="setting-firebase-url">Firebase Database URL</label>
                    <input type="text" id="setting-firebase-url" class="form-control" value="${data.settings.firebase_url || ''}">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="setting-scan-timeout">Scan Timeout (sec)</label>
                        <input type="number" id="setting-scan-timeout" class="form-control" value="${data.settings.scan_timeout || 30}">
                    </div>
                    <div class="form-group">
                        <label for="setting-reservations-days">Reservering Dagen</label>
                        <input type="number" id="setting-reservations-days" class="form-control" value="${data.settings.reservations_days || 7}">
                    </div>
                </div>
                <div class="form-group">
                    <label for="setting-school-name">School Naam</label>
                    <input type="text" id="setting-school-name" class="form-control" value="${data.settings.school_name || 'VIVES'}">
                </div>
            `;
            
            settingsForm.innerHTML = settingsHTML;
        } else {
            settingsForm.innerHTML = '<p>Geen instellingen gevonden.</p>';
        }
    })
    .catch(error => {
        settingsForm.innerHTML = '<p>Er is een fout opgetreden bij het laden van instellingen.</p>';
        console.error('Error:', error);
    });
}

// Admin CRUD operations (placeholders)
function addNewPrinter() {
    alert('Functionaliteit "Nieuwe Printer" wordt binnenkort toegevoegd.');
}

function editPrinter(printerId) {
    alert(`Bewerken van printer #${printerId} wordt binnenkort toegevoegd.`);
}

function deletePrinter(printerId) {
    if (confirm(`Weet je zeker dat je printer #${printerId} wilt verwijderen?`)) {
        alert('Verwijderfunctie wordt binnenkort toegevoegd.');
    }
}

function addNewCard() {
    alert('Functionaliteit "Nieuwe Kaart" wordt binnenkort toegevoegd.');
}

function editCard(userId) {
    alert(`Bewerken van kaart voor gebruiker #${userId} wordt binnenkort toegevoegd.`);
}

function deleteCard(userId) {
    if (confirm(`Weet je zeker dat je de kaart voor gebruiker #${userId} wilt verwijderen?`)) {
        alert('Verwijderfunctie wordt binnenkort toegevoegd.');
    }
}

function addNewUser() {
    alert('Functionaliteit "Nieuwe Gebruiker" wordt binnenkort toegevoegd.');
}

function editUser(userId) {
    alert(`Bewerken van gebruiker #${userId} wordt binnenkort toegevoegd.`);
}

function deleteUser(userId) {
    if (confirm(`Weet je zeker dat je gebruiker #${userId} wilt verwijderen?`)) {
        alert('Verwijderfunctie wordt binnenkort toegevoegd.');
    }
}

function addNewReservation() {
    alert('Functionaliteit "Nieuwe Reservering" wordt binnenkort toegevoegd.');
}

function editReservation(reservationId) {
    alert(`Bewerken van reservering #${reservationId} wordt binnenkort toegevoegd.`);
}

function deleteReservation(reservationId) {
    if (confirm(`Weet je zeker dat je reservering #${reservationId} wilt verwijderen?`)) {
        alert('Verwijderfunctie wordt binnenkort toegevoegd.');
    }
}

function generateReport(type) {
    alert(`Rapport "${type}" wordt binnenkort toegevoegd.`);
}

function saveSettings() {
    alert('Instellingen opslaan wordt binnenkort toegevoegd.');
}

function formatDateTime(dateTimeStr) {
    if (!dateTimeStr) return 'N/A';
    
    const date = new Date(dateTimeStr);
    return `${date.toLocaleDateString('nl-NL')} ${date.toLocaleTimeString('nl-NL', {hour: '2-digit', minute: '2-digit'})}`;
}

function logout() {
    currentUser = null;
    showWelcomePage();
}

