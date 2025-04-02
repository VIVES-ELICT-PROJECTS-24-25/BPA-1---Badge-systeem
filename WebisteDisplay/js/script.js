// Firebase configuration
let firebaseConfig;
let currentCode = '';
let currentUser = null;
let connectionStatus = {
    firebase: false,
    database: false
};

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
    
    document.body.prepend(errorDiv);
}

// Page Navigation Functions
function showWelcomePage() {
    document.getElementById('welcomePage').classList.remove('hidden');
    document.getElementById('scanPage').classList.add('hidden');
    document.getElementById('codePage').classList.add('hidden');
    document.getElementById('reservationsPage').classList.add('hidden');
    
    // Reset status messages
    document.getElementById('scanStatus').textContent = '';
    document.getElementById('codeStatus').textContent = '';
    
    // Reset code input
    currentCode = '';
    updateCodeDisplay();
}

function showScanPage() {
    document.getElementById('welcomePage').classList.add('hidden');
    document.getElementById('scanPage').classList.remove('hidden');
    document.getElementById('codePage').classList.add('hidden');
    document.getElementById('reservationsPage').classList.add('hidden');
    
    // Check connections before proceeding
    if (!connectionStatus.firebase) {
        document.getElementById('scanStatus').textContent = 'Geen verbinding met Firebase. Kan kaart niet scannen.';
        return;
    }
    
    if (!connectionStatus.database) {
        document.getElementById('scanStatus').textContent = 'Geen verbinding met de database. Kan gebruiker niet verifiëren.';
        return;
    }
    
    document.getElementById('scanStatus').textContent = 'Klaar om te scannen. Houd je kaart bij de lezer...';
}

function showCodePage() {
    document.getElementById('welcomePage').classList.add('hidden');
    document.getElementById('scanPage').classList.add('hidden');
    document.getElementById('codePage').classList.remove('hidden');
    document.getElementById('reservationsPage').classList.add('hidden');
    
    // Reset code
    currentCode = '';
    updateCodeDisplay();
    
    // Check database connection
    if (!connectionStatus.database) {
        document.getElementById('codeStatus').textContent = 'Geen verbinding met de database. Kan code niet verifiëren.';
    }
}

function showReservationsPage(user) {
    document.getElementById('welcomePage').classList.add('hidden');
    document.getElementById('scanPage').classList.add('hidden');
    document.getElementById('codePage').classList.add('hidden');
    document.getElementById('reservationsPage').classList.remove('hidden');
    
    // Update user information
    document.getElementById('userName').textContent = user.Voornaam;
    
    // Load user's reservations
    loadReservations(user.User_ID);
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

function showScanPage() {
    document.getElementById('welcomePage').classList.add('hidden');
    document.getElementById('scanPage').classList.remove('hidden');
    document.getElementById('codePage').classList.add('hidden');
    document.getElementById('reservationsPage').classList.add('hidden');
    
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
}

// API Functions
function verifyRFIDCard(cardId) {
    const scanStatus = document.getElementById('scanStatus');
    
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
                showReservationsPage(data.user);
            }, 2000); // Iets langere vertraging zodat gebruiker het kaartnummer kan zien
        } else {
            scanStatus.innerHTML = `Kaart <strong>${cardId}</strong> niet herkend.<br>${data.message || 'Probeer opnieuw.'}`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        scanStatus.innerHTML = `Kaart <strong>${cardId}</strong> gescand.<br>Er is een fout opgetreden: ${error.message}<br>Probeer opnieuw.`;
    });
}

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
                showReservationsPage(data.user);
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
    
    // Make an AJAX request to get the user's reservations
    fetch(`php/get_reservations.php?userId=${userId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.reservations.length > 0) {
            let reservationsHTML = '';
            
            data.reservations.forEach(reservation => {
                reservationsHTML += `
                    <div class="reservation-card">
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

function formatDateTime(dateTimeStr) {
    if (!dateTimeStr) return 'N/A';
    
    const date = new Date(dateTimeStr);
    return `${date.toLocaleDateString('nl-NL')} ${date.toLocaleTimeString('nl-NL', {hour: '2-digit', minute: '2-digit'})}`;
}

function logout() {
    currentUser = null;
    showWelcomePage();
}

// Initialize the application
document.addEventListener('DOMContentLoaded', () => {
    initFirebase();
    showWelcomePage();
});