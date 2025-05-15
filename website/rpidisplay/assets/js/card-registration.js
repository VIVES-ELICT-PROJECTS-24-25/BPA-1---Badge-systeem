// Card registration functionality
document.addEventListener('DOMContentLoaded', function () {
    console.log('Card registration system initializing');

    // Remove any debug text at top of page
    document.body.childNodes.forEach(node => {
        if (node.nodeType === Node.TEXT_NODE && node.textContent && node.textContent.includes('Current Date')) {
            node.textContent = '';
        }
    });

    // UI elements
    const statusMessage = document.getElementById('status-message');
    const userInfoContainer = document.getElementById('user-info-container');
    const scannerContainer = document.querySelector('.scanner-container');
    const cardRegistrationContainer = document.getElementById('card-registration-container');
    const actionButtonsContainer = document.querySelector('.action-buttons');
    
    let lastScannedCardId = '';
    let lastScanTime = 0;

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
                    console.log('Initializing Firebase with config');
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

    // Function to listen for RFID scans
    function listenForRfidScans() {
        updateStatus('<i class="fas fa-spinner fa-pulse"></i> Scan een kaart om toe te voegen...', 'loading');

        console.log('Starting to listen for RFID scans');

        // Reference to rfid_latest_scan in Firebase
        const rfidRef = firebase.database().ref('rfid_latest_scan');

        // Record the current time when we start listening
        const startListeningTime = Date.now();

        // Listen for changes
        rfidRef.on('value', (snapshot) => {
            const data = snapshot.val();
            console.log('RFID data received:', data);

            if (!data || !data.id) {
                console.log('No valid RFID data');
                return;
            }

            // Check if this is a new scan after starting the listener
            const scanTimestamp = new Date(data.timestamp || "").getTime();

            // Only accept if scan happened AFTER we started listening
            if (!isNaN(scanTimestamp) && scanTimestamp > startListeningTime) {
                console.log('New scan detected after listener started:', data.id);
                processCardScan(data.id);
            } else {
                console.log('Ignoring old scan from before listener started');
            }
        });
    }

    // Process a card scan
    function processCardScan(cardId) {
        const currentTime = Date.now();

        // Prevent duplicate processing (same card within 5 seconds)
        if (cardId === lastScannedCardId && currentTime - lastScanTime < 5000) {
            console.log('Duplicate scan ignored');
            return;
        }

        lastScannedCardId = cardId;
        lastScanTime = currentTime;

        console.log('Processing RFID scan:', cardId);
        updateStatus('<i class="fas fa-spinner fa-spin"></i> Kaart gedetecteerd, bezig met verifiëren...', 'loading');

        // Verify card in the admin system
        verifyCardAdmin(cardId);
    }

    // Verify card with the admin verification endpoint
    function verifyCardAdmin(cardId) {
        console.log('Verifying card ID in admin system:', cardId);

        fetch('verify_card_admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                type: 'verifyCard',
                cardId: cardId 
            })
        })
        .then(response => {
            console.log('Card admin verification response status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return response.json();
        })
        .then(data => {
            console.log('Card admin verification response:', data);

            if (data.success) {
                if (data.exists) {
                    // Card already exists in the system
                    showExistingUserInfo(data.user, cardId);
                } else {
                    // Card doesn't exist - proceed to VIVES ID input
                    redirectToVivesIdInput(cardId);
                }
            } else {
                // Verification failed
                updateStatus(`<i class="fas fa-exclamation-circle"></i> ${data.message}`, 'error');
                
                // Reset after 3 seconds
                setTimeout(() => {
                    updateStatus('<i class="fas fa-spinner fa-pulse"></i> Scan een kaart om toe te voegen...', 'loading');
                }, 3000);
            }
        })
        .catch(error => {
            console.error('Error verifying card in admin system:', error);
            updateStatus(`<i class="fas fa-exclamation-circle"></i> Fout bij kaartverificatie: ${error.message}`, 'error');
            
            // Reset after 3 seconds
            setTimeout(() => {
                updateStatus('<i class="fas fa-spinner fa-pulse"></i> Scan een kaart om toe te voegen...', 'loading');
            }, 3000);
        });
    }

    // Show info for existing user with this card
    function showExistingUserInfo(userData, cardId) {
        console.log('Showing existing user info:', userData);

        // Hide scanner container
        scannerContainer.style.display = 'none';

        // Create user info card with two columns
        const userInfoHTML = `
            <div class="user-info-card">
                <div class="user-info-header">
                    <h3>Kaart reeds geregistreerd</h3>
                    <div class="status-badge registered">Geregistreerd</div>
                </div>
                <div class="user-info-body">
                    <div class="user-details">
                        <div class="detail-column">
                            <div class="detail-row">
                                <div class="detail-label">Kaart ID:</div>
                                <div class="detail-value">${cardId}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Naam:</div>
                                <div class="detail-value">${userData.Voornaam} ${userData.Naam}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">VIVES nr:</div>
                                <div class="detail-value">${userData.Vives_id}</div>
                            </div>
                        </div>
                        <div class="detail-column">
                            <div class="detail-row">
                                <div class="detail-label">E-mail:</div>
                                <div class="detail-value">${userData.Emailadres || 'Niet beschikbaar'}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Opleiding:</div>
                                <div class="detail-value">${userData.opleiding_naam || 'Niet beschikbaar'}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Type:</div>
                                <div class="detail-value">${userData.Type || userData.user_type || 'Niet gespecificeerd'}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Display user info
        userInfoContainer.innerHTML = userInfoHTML;
        userInfoContainer.style.display = 'block';

        // Add "Scan new card" button to action buttons container
        if (actionButtonsContainer) {
            // First remove any existing scan button
            const existingScanButton = document.getElementById('scan-new-card-btn');
            if (existingScanButton) {
                existingScanButton.remove();
            }
            
            // Create new scan button
            const scanNewCardBtn = document.createElement('a');
            scanNewCardBtn.href = '#';
            scanNewCardBtn.className = 'scan-new-card-btn';
            scanNewCardBtn.innerHTML = '<i class="fas fa-id-card"></i> Nieuwe kaart';
            scanNewCardBtn.id = 'scan-new-card-btn';
            
            // First child is empty div for spacing, add before the logout button
            actionButtonsContainer.insertBefore(scanNewCardBtn, actionButtonsContainer.children[1]);
            
            // Add event listener
            document.getElementById('scan-new-card-btn').addEventListener('click', function(e) {
                e.preventDefault();
                // Hide user info and show scanner again
                userInfoContainer.style.display = 'none';
                scannerContainer.style.display = 'block';
                updateStatus('<i class="fas fa-spinner fa-pulse"></i> Scan een kaart om toe te voegen...', 'loading');
                
                // Remove scan new button
                this.remove();
            });
        }
    }

    // Redirect to VIVES ID input page
    function redirectToVivesIdInput(cardId) {
        console.log('Redirecting to VIVES ID input with card ID:', cardId);
        window.location.href = `vives_id_input.php?card_id=${encodeURIComponent(cardId)}`;
    }
});