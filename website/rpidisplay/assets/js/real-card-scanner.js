// Firebase card scanner implementation
document.addEventListener('DOMContentLoaded', function () {
    console.log('Firebase card scanner initializing');

    // Direct bug fix - verwijder debug output
    document.body.childNodes.forEach(node => {
        if (node.nodeType === Node.TEXT_NODE && node.textContent && 
            node.textContent.includes('Current Date and Time')) {
            node.textContent = '';
        }
    });

    const statusMessage = document.getElementById('status-message');
    const userInfoContainer = document.getElementById('user-info-container');
    const scannerContainer = document.querySelector('.scanner-container');
    
    // Get last scanned card from local storage (with fallback to empty string)
    let lastScannedCardId = localStorage.getItem('lastScannedCard') || '';
    let lastScanTime = parseInt(localStorage.getItem('lastScanTime') || '0');
    
    // Check for recent logout
    const lastLogout = parseInt(localStorage.getItem('lastLogout') || '0');
    const timeSinceLogout = Date.now() - lastLogout;
    const wasRecentlyLoggedOut = timeSinceLogout < 5000;
    
    if (wasRecentlyLoggedOut) {
        console.log('Recent logout detected, resetting card scan state');
        // Force reset the scanner to be visible
        if (scannerContainer) scannerContainer.style.display = 'flex';
        if (userInfoContainer) userInfoContainer.style.display = 'none';
        // Clear card data
        lastScannedCardId = '';
        lastScanTime = 0;
        localStorage.removeItem('lastScannedCard');
        localStorage.removeItem('lastScanTime');
    }

    // Status update function
    function updateStatus(message, type) {
        statusMessage.innerHTML = message;
        statusMessage.className = `status-message ${type}`;
    }

    // Initialize Firebase
    initializeFirebase();

    // Function to initialize Firebase
    function initializeFirebase() {
        updateStatus('<i class="fas fa-spinner fa-pulse"></i> Verbinding maken met Firebase...', 'loading');

        fetch('get_firebase_config.php')
            .then(response => {
                console.log('Firebase config response received');
                return response.json();
            })
            .then(config => {
                if (config.error) {
                    console.error('Firebase config error:', config.error);
                    updateStatus(`<i class="fas fa-exclamation-circle"></i> Firebase fout: ${config.error}`, 'error');
                    return;
                }

                try {
                    console.log('Initializing Firebase with config:', config);
                    // Initialize Firebase with the configuration
                    if (!firebase.apps.length) {
                        firebase.initializeApp(config);
                    }
                    console.log('Firebase initialized');

                    // Start listening for RFID scans
                    listenForRfidScans();
                } catch (error) {
                    console.error('Firebase initialization error:', error);
                    updateStatus(`<i class="fas fa-exclamation-circle"></i> Firebase initialisatie fout: ${error.message}`, 'error');
                }
            })
            .catch(error => {
                console.error('Error loading Firebase config:', error);
                updateStatus(`<i class="fas fa-exclamation-circle"></i> Kan Firebase configuratie niet laden: ${error.message}`, 'error');
            });
    }

    function listenForRfidScans() {
        updateStatus('<i class="fas fa-spinner fa-pulse"></i> Wachten op kaart...', 'loading');

        console.log('Starting to listen for RFID scans');

        // Reference to rfid_latest_scan in Firebase
        const rfidRef = firebase.database().ref('rfid_latest_scan');
        
        // Store the initialization time when we start listening
        const startListeningTime = new Date();
        console.log('Listener started at:', startListeningTime.toISOString());

        // Keep track of the last processed scan timestamp to avoid duplicates
        let lastProcessedTimestamp = '';
        
        // Listen for changes
        rfidRef.on('value', (snapshot) => {
            const data = snapshot.val();
            console.log('RFID data received:', data);

            if (!data || !data.id) {
                console.log('No valid RFID data');
                return;
            }
            
            // Check if we're already viewing reservations
            if (window.isViewingReservations === true) {
                console.log('Already viewing reservations, ignoring card scan');
                return;
            }

            try {
                // Parse the timestamp from Firebase
                const scanTimestamp = data.timestamp || "";
                const scanDate = new Date(scanTimestamp);
                
                // Check if this is the same timestamp we already processed
                if (scanTimestamp === lastProcessedTimestamp) {
                    console.log('Already processed this exact timestamp:', scanTimestamp);
                    return;
                }
                
                // Check if this is a new scan (within last 10 seconds)
                const now = new Date();
                const scanAgeInSeconds = (now - scanDate) / 1000;
                const isRecentScan = scanAgeInSeconds <= 10; // Consider scans in the last 10 seconds
                
                console.log('Scan timestamp:', scanTimestamp);
                console.log('Scan age (seconds):', scanAgeInSeconds);
                
                // Only accept if it's a recent scan or it happened after we started listening
                if (isRecentScan || scanDate > startListeningTime) {
                    console.log('Valid new scan detected:', data.id);
                    lastProcessedTimestamp = scanTimestamp;
                    processCardScan(data.id);
                } else {
                    console.log('Ignoring old scan from before listener started or too old');
                }
            } catch (err) {
                console.error('Error processing scan timestamp:', err);
            }
        });
    }

    // Verwerkt een kaart scan
    function processCardScan(cardId) {
        const currentTime = Date.now();
        
        // Check for recent logout - don't process cards immediately after logout
        const lastLogout = parseInt(localStorage.getItem('lastLogout') || '0');
        const timeSinceLogout = currentTime - lastLogout;
        if (timeSinceLogout < 2000) { // Shortened to just 2 seconds
            console.log('Recent logout detected (' + Math.round(timeSinceLogout/1000) + 's ago), ignoring scan');
            
            // Show notification that scan was ignored due to recent logout
            const tempNotification = document.createElement('div');
            tempNotification.className = 'scan-notification';
            tempNotification.innerHTML = `<i class="fas fa-info-circle"></i> Scan genegeerd - zojuist uitgelogd`;
            document.body.appendChild(tempNotification);
            
            // Remove notification after a short delay
            setTimeout(() => {
                tempNotification.style.opacity = '0';
                setTimeout(() => tempNotification.remove(), 500);
            }, 2000); // Shortened notification time too
            
            return;
        }
        
        // If we're already on the reservations page, don't process the card scan
        if (window.isViewingReservations === true) {
            console.log('Already viewing reservations, ignoring card scan');
            return;
        }

        // Prevent duplicate processing (same card within 8 seconds)
        if (cardId === lastScannedCardId && currentTime - lastScanTime < 8000) {
            console.log('Duplicate scan ignored (same card within 8 sec)');
            return;
        }

        // Update tracking variables and store in localStorage for persistence
        lastScannedCardId = cardId;
        lastScanTime = currentTime;
        localStorage.setItem('lastScannedCard', cardId);
        localStorage.setItem('lastScanTime', currentTime.toString());

        // Visual feedback that scan was received
        console.log('Processing RFID scan:', cardId);
        updateStatus('<i class="fas fa-spinner fa-spin"></i> Kaart gedetecteerd, bezig met verifiëren...', 'loading');
        
        // Temporarily disable scanner to prevent double processing
        const scannerContainer = document.querySelector('.scanner-container');
        if (scannerContainer) {
            scannerContainer.classList.add('processing');
        }

        // Verify card in the database
        verifyCard(cardId);
    }

    // Verify card with the database
    function verifyCard(cardId) {
        console.log('Verifying card ID:', cardId);
        
        // If we're already on the reservations page, don't process further
        if (window.isViewingReservations === true) {
            console.log('Already viewing reservations, skipping verification');
            return;
        }

        // Provide the card ID in URL for easy sharing during development
        console.log('Card scan URL for sharing: ' + window.location.href + '?card=' + cardId);
        
        // Disable scanner area during verification
        const scannerArea = document.querySelector('.scanner-area');
        if (scannerArea) scannerArea.classList.add('verifying');

        fetch('verify_card.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                cardId: cardId,
                timestamp: new Date().toISOString() // Send current timestamp for additional verification
            })
        })
            .then(response => {
                console.log('Card verification response status:', response.status);

                // Check response status
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                // Try to read the response as text first
                return response.text().then(text => {
                    console.log('Raw response from verify_card.php:', text);
                    try {
                        // Try to parse as JSON
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(`Geen geldige JSON ontvangen: ${text.substring(0, 100)}...`);
                    }
                });
            })
            .then(data => {
                console.log('Card verification response:', data);

                if (data.success) {
                    // Card is valid and user found
                    handleSuccessfulScan(data.user);
                } else {
                    // Card verification failed
                    updateStatus(`<i class="fas fa-exclamation-circle"></i> ${data.message}`, 'error');

                    // Reset verifying state
                    const scannerArea = document.querySelector('.scanner-area');
                    if (scannerArea) scannerArea.classList.remove('verifying');
                    
                    // Reset processing state
                    const scannerContainer = document.querySelector('.scanner-container');
                    if (scannerContainer) scannerContainer.classList.remove('processing');

                    // Reset after 3 seconds
                    setTimeout(() => {
                        updateStatus('<i class="fas fa-spinner fa-pulse"></i> Wachten op kaart...', 'loading');
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Error verifying card:', error);
                updateStatus(`<i class="fas fa-exclamation-circle"></i> Fout bij kaartverificatie: ${error.message}`, 'error');

                // Reset verifying state
                const scannerArea = document.querySelector('.scanner-area');
                if (scannerArea) scannerArea.classList.remove('verifying');
                
                // Reset processing state
                const scannerContainer = document.querySelector('.scanner-container');
                if (scannerContainer) scannerContainer.classList.remove('processing');

                // Reset after 3 seconds
                setTimeout(() => {
                    updateStatus('<i class="fas fa-spinner fa-pulse"></i> Wachten op kaart...', 'loading');
                }, 3000);
            });
    }

    // Function to handle successful card scan
    function handleSuccessfulScan(userData) {
        console.log('Card scan successful:', userData);

        // Clear any logout state as we have a successful login now
        localStorage.removeItem('lastLogout');
        
        // If we're already viewing reservations, don't proceed with login
        if (window.isViewingReservations === true) {
            console.log('Already viewing reservations, ignoring successful card scan');
            
            // Just provide brief confirmation of scan without changing view
            const tempNotification = document.createElement('div');
            tempNotification.className = 'scan-notification';
            tempNotification.innerHTML = `<i class="fas fa-check-circle"></i> Kaart van ${userData.Voornaam} gescand, maar al ingelogd.`;
            document.body.appendChild(tempNotification);
            
            // Remove notification after a short delay
            setTimeout(() => {
                tempNotification.style.opacity = '0';
                setTimeout(() => tempNotification.remove(), 500);
            }, 3000);
            
            return;
        }

        // Show success message
        updateStatus(`<i class="fas fa-check-circle"></i> Kaart herkend! Welkom ${userData.Voornaam}!`, 'success');

        // Reset scanner container processing state
        const scannerContainer = document.querySelector('.scanner-container');
        if (scannerContainer) {
            scannerContainer.classList.remove('processing');
        }

        // Wait a moment before redirecting
        setTimeout(() => {
            // Store user data in session
            storeUserSession(userData)
                .then(() => {
                    // After successful session storage, set the viewing flag to prevent new scans
                    window.isViewingReservations = true;
                    
                    // Check user type and display appropriate dashboard
                    if (userData.Type === 'beheerder') {
                        displayAdminDashboard(userData);
                    } else {
                        // For students and researchers
                        displayUserDashboard(userData);
                    }
                })
                .catch(error => {
                    console.error('Session storage error:', error);
                    updateStatus(`<i class="fas fa-exclamation-circle"></i> Sessie fout: ${error.message}`, 'error');
                    
                    // Reset scanner container processing state on error
                    if (scannerContainer) {
                        scannerContainer.classList.remove('processing');
                    }
                    
                    // Reset after error
                    setTimeout(() => {
                        updateStatus('<i class="fas fa-spinner fa-pulse"></i> Wachten op kaart...', 'loading');
                    }, 3000);
                });
        }, 1500);
    }

    // Function to store user session
    function storeUserSession(userData) {
        return fetch('store_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ user: userData })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(`Geen geldige JSON ontvangen: ${text.substring(0, 100)}...`);
                    }
                });
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Sessie opslaan mislukt');
                }
                return data;
            });
    }

    // Function to display admin dashboard
    function displayAdminDashboard(userData) {
        const adminDashboardHTML = `
        <div class="admin-options">
            <h2 class="admin-dashboard-title">Beheerder Dashboard</h2>
            <div class="login-buttons">
                <a href="admin_printers.php" class="login-btn">
                    <i class="fas fa-print"></i>
                    <span>Printers beheren</span>
                </a>
                
                <a href="admin_cards.php" class="login-btn">
                    <i class="fas fa-id-card"></i>
                    <span>Kaart toevoegen</span>
                </a>
                
                <a href="studentcard_login.php?view_reservations=1" class="login-btn">
                    <i class="fas fa-calendar-check"></i>
                    <span>Mijn reservaties</span>
                </a>
            </div>
        </div>
    `;

        // Display the dashboard with username
        const fullName = `${userData.Voornaam} ${userData.Naam}`;
        showDashboard(adminDashboardHTML, fullName, userData);
    }

    // Function to display user dashboard with reservations
    function displayUserDashboard(userData) {
        // Fetch user's active reservations
        fetch(`get_user_reservations.php?user_id=${userData.User_ID}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(`Geen geldige JSON ontvangen: ${text.substring(0, 100)}...`);
                    }
                });
            })
            .then(data => {
                let userDashboardHTML = '';

                if (data.success) {
                    const reservations = data.reservations || [];
                    const now = new Date();

                    // Create user dashboard HTML - zonder de welkomstboodschap (die komt in de header)
                    userDashboardHTML = `
                    <div class="reservations-container">
                        <h3 class="text-center">Je actieve reserveringen</h3>
                `;

                    if (reservations.length === 0) {
                        userDashboardHTML += `
                        <div class="no-reservations">
                            <i class="fas fa-calendar-times fa-3x"></i>
                            <p>Je hebt geen actieve reserveringen.</p>
                        </div>
                    `;
                    } else {
                        reservations.forEach(reservation => {
                            const startTime = new Date(reservation.PRINT_START);
                            const endTime = new Date(reservation.PRINT_END);
                            const isActive = now >= startTime && now <= endTime;
                            const isUpcoming = now < startTime;
                            const isPrintStarted = reservation.print_started === 1 || reservation.print_started === true;

                            // Haal printer naam in plaats van ID
                            const printerName = reservation.Versie_Toestel || `Printer #${reservation.Printer_ID}`;

                            userDashboardHTML += `
                            <div class="reservation-card" data-id="${reservation.Reservatie_ID}">
                                <div class="reservation-header">
                                    <div class="reservation-title">Reservering #${reservation.Reservatie_ID}</div>
                                    <div class="reservation-status ${isActive ? 'status-active' : 'status-upcoming'}">
                                        ${isActive ? 'Actief' : 'Aankomend'}
                                    </div>
                                </div>
                                
                                <div class="reservation-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Printer</div>
                                        <div class="detail-value">${printerName}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Start</div>
                                        <div class="detail-value">${formatDate(startTime)}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Eind</div>
                                        <div class="detail-value">${formatDate(endTime)}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Filament</div>
                                        <div class="detail-value">${reservation.filament_type || 'eigen/geen'}</div>
                                    </div>
                                </div>
                                
                                <div class="reservation-actions">
                                    ${isActive ? 
                                        isPrintStarted ? 
                                            `<div class="print-status active"><i class="fas fa-print"></i> Print actief</div>` : 
                                            `<a href="#" class="action-btn start-print-btn" data-id="${reservation.Reservatie_ID}">Start Print</a>`
                                        : ''
                                    }
                                </div>
                            </div>
                        `;
                        });
                    }

                    userDashboardHTML += `
                    </div>
                `;

                } else {
                    userDashboardHTML = `
                    <div class="status-message error">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Er is een fout opgetreden bij het ophalen van je reserveringen: ${data.message}</p>
                    </div>
                `;
                }

                // Display the dashboard met de naam in de header
                const fullName = `${userData.Voornaam} ${userData.Naam}`;
                showDashboard(userDashboardHTML, fullName, userData);

                // Add event listeners for the Start Print buttons
                document.querySelectorAll('.start-print-btn').forEach(btn => {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        const reservationId = this.getAttribute('data-id');
                        startPrint(reservationId);
                    });
                });
            })
            .catch(error => {
                console.error('Error fetching user reservations:', error);

                // Show error dashboard zonder de user-welcome
                const errorDashboardHTML = `
                <div class="status-message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Fout bij het ophalen van reserveringen: ${error.message}</p>
                </div>
            `;

                const fullName = `${userData.Voornaam} ${userData.Naam}`;
                showDashboard(errorDashboardHTML, fullName, userData);
            });
    }

    // Function to start print for a reservation
    function startPrint(reservationId) {
        console.log('Starting print for reservation:', reservationId);

        // Voorkom dubbele aanvragen
        const actionButton = document.querySelector(`.start-print-btn[data-id="${reservationId}"]`);
        if (actionButton) {
            if (actionButton.disabled) {
                console.log('Print start already in progress, ignoring');
                return;
            }
            const originalText = actionButton.textContent;
            actionButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Bezig...';
            actionButton.disabled = true;
        }

        fetch('start_print.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ reservationId: reservationId })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(`Geen geldige JSON ontvangen: ${text.substring(0, 100)}...`);
                    }
                });
            })
            .then(data => {
                console.log('Print start response:', data);
                if (data.success) {
                    // Succes - bijwerken UI
                    showPrintStartedMessage(data);

                    // Update alle reserveringskaarten die overeenkomen met deze ID
                    document.querySelectorAll(`.reservation-card[data-id="${reservationId}"]`).forEach(card => {
                        // Vervang de start-print knop met een "actieve print" melding
                        const actionsDiv = card.querySelector('.reservation-actions');
                        if (actionsDiv) {
                            actionsDiv.innerHTML = `
                                <div class="print-status active">
                                    <i class="fas fa-print"></i> Print actief
                                </div>
                            `;
                        }
                        // Voeg een klasse toe om aan te geven dat deze actief is
                        card.classList.add('printing');
                    });
                } else {
                    // Toon foutmelding
                    showPrintErrorMessage(data.message || 'Er is een fout opgetreden bij het starten van de print');

                    // Reset de knop
                    if (actionButton) {
                        actionButton.innerHTML = originalText || 'Start Print';
                        actionButton.disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Error starting print:', error);
                showPrintErrorMessage('Er is een fout opgetreden bij het starten van de print');

                // Reset de knop
                if (actionButton) {
                    actionButton.innerHTML = originalText || 'Start Print';
                    actionButton.disabled = false;
                }
            });
    }

    // Hulpfunctie om succesmelding te tonen
    function showPrintStartedMessage(data) {
        // Voeg een succes-melding toe bovenaan de reserveringen
        const reservationsContainer = document.querySelector('.reservations-container');
        if (reservationsContainer) {
            const successMessage = document.createElement('div');
            successMessage.className = 'status-message success';
            successMessage.innerHTML = `
                <i class="fas fa-check-circle"></i>
                <p>Print succesvol gestart! De printer wordt nu ingeschakeld.</p>
            `;

            // Voeg de melding toe aan het begin van de container
            reservationsContainer.insertBefore(successMessage, reservationsContainer.firstChild);

            // Laat de melding na 8 seconden verdwijnen
            setTimeout(() => {
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    successMessage.remove();
                }, 500); // Verwijder na fade-out
            }, 8000);
        }
    }

    // Hulpfunctie om foutmelding te tonen
    function showPrintErrorMessage(errorMessage) {
        // Voeg een foutmelding toe bovenaan de reserveringen
        const reservationsContainer = document.querySelector('.reservations-container');
        if (reservationsContainer) {
            const errorMsg = document.createElement('div');
            errorMsg.className = 'status-message error';
            errorMsg.innerHTML = `
                <i class="fas fa-exclamation-circle"></i>
                <p>${errorMessage}</p>
            `;

            // Voeg de melding toe aan het begin van de container
            reservationsContainer.insertBefore(errorMsg, reservationsContainer.firstChild);

            // Laat de melding na 8 seconden verdwijnen
            setTimeout(() => {
                errorMsg.style.opacity = '0';
                setTimeout(() => {
                    errorMsg.remove();
                }, 500); // Verwijder na fade-out
            }, 8000);
        }
    }

    // Helper function to show dashboard - verplaatst welkomstboodschap naar header
    function showDashboard(dashboardHTML, userName, userData) {
        // Hide scanner container
        scannerContainer.style.display = 'none';

        // Verberg de h1 titel volledig
        const h1Title = document.querySelector('h1');
        if (h1Title) {
            h1Title.style.display = 'none';
        }

        // Cre er welkomst element in de header als het nog niet bestaat
        let welcomeText = document.querySelector('.welcome-text');
        if (!welcomeText) {
            welcomeText = document.createElement('div');
            welcomeText.className = 'welcome-text';

            // Haal het logo element op
            const logoElement = document.querySelector('header .logo');

            // Voeg het welkomst element toe na het logo
            if (logoElement && logoElement.parentNode) {
                logoElement.parentNode.insertBefore(welcomeText, logoElement.nextSibling);
            }
        }

        // Update welkomstboodschap in de header
        welcomeText.innerHTML = `<strong>Welkom ${userName}</strong>`;

        // Show user info container zonder de welkomstboodschap in de content
        // Verwijder de user-welcome div uit dashboardHTML als die bestaat
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = dashboardHTML;
        const userWelcome = tempDiv.querySelector('.user-welcome');
        if (userWelcome) {
            userWelcome.remove();
        }

        // Update de container met de aangepaste HTML
        // Check if this is the admin dashboard itself
        const isAdminDashboard = tempDiv.querySelector('.admin-dashboard-title') !== null;
        
        // Show back button only for admin users who are NOT on the main admin dashboard
        const isAdmin = userData && userData.Type === 'beheerder';
        const showBackButton = isAdmin && !isAdminDashboard;
        
        userInfoContainer.innerHTML = tempDiv.innerHTML + `
            <div class="action-buttons">
                ${showBackButton ? `
                <a href="index.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Terug
                </a>
                ` : ''}
                <a href="javascript:void(0)" onclick="recordLogout()" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Uitloggen
                </a>
            </div>
        `;
        userInfoContainer.style.display = 'block';

        // Verwijder de actie buttons container van de scanner pagina
        const scannerActionButtons = document.querySelector('.scanner-action-buttons');
        if (scannerActionButtons) {
            scannerActionButtons.style.display = 'none';
        }
    }

    // Helper function to format date
    function formatDate(date) {
        return date.toLocaleString('nl-BE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Extra functie om debug output te verwijderen
    function removeDebugOutput() {
        const nodes = document.body.childNodes;
        for (let i = 0; i < nodes.length; i++) {
            const node = nodes[i];
            if (node.nodeType === Node.TEXT_NODE && 
                node.textContent && 
                node.textContent.includes('Current Date and Time')) {
                node.textContent = '';
            }
        }
    }
    
    // Direct uitvoeren bij laden
    removeDebugOutput();
    
    // En ook na een korte vertraging voor het geval de output later wordt toegevoegd
    setTimeout(removeDebugOutput, 200);
});