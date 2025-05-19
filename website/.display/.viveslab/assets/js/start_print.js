/**
 * Start Print JavaScript
 * Handles the start print functionality and Firebase updates
 */

// Start Print functie met extra parameters voor sessie behoud
function startPrint(reservationId) {
    console.log(`Start print aangeroepen voor reservering #${reservationId}`);
    
    // Voorkom dubbele aanvragen
    const startButton = document.querySelector(`.start-print-btn[data-id="${reservationId}"]`);
    if (startButton && startButton.disabled) {
        console.log('Print start already in progress');
        return;
    }
    
    // Update UI
    if (startButton) {
        startButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Bezig...';
        startButton.disabled = true;
    }
    
    // 1. Eerst directe update naar Firebase doen voor snelle respons
    updateFirebaseForPrintStart(reservationId);
    
    // 2. Dan API-aanroep naar server maken met extra parameters voor sessie behoud
    fetch('start_print.php', {
        method: 'POST',
        credentials: 'same-origin', // BELANGRIJK: Include cookies voor sessie behoud
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest' // Dit helpt de server herkennen dat het een AJAX verzoek is
        },
        body: JSON.stringify({ 
            reservationId: reservationId,
            session_id: getSessionId() // Optioneel: Verstuur sessie ID als backup
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Print start response:', data);
        
        if (data.success) {
            // Update UI - vervang knop met "Print Actief" melding
            document.querySelectorAll(`.reservation-card[data-id="${reservationId}"]`).forEach(card => {
                const actionsDiv = card.querySelector('.reservation-actions');
                if (actionsDiv) {
                    actionsDiv.innerHTML = `
                        <div class="print-status active">
                            <i class="fas fa-print"></i> Print actief
                        </div>
                    `;
                }
                card.classList.add('printing');
            });
            
            // Toon succesmelding
            showMessage('success', 'Print succesvol gestart! De printer wordt nu ingeschakeld.');
        } else {
            // Fout - reset knop en toon foutmelding
            if (startButton) {
                startButton.innerHTML = 'Start Print';
                startButton.disabled = false;
            }
            
            showMessage('error', data.message || 'Er is een fout opgetreden bij het starten van de print');
            console.error('Error details:', data);
        }
    })
    .catch(error => {
        console.error('Error starting print:', error);
        
        // Reset knop bij fout
        if (startButton) {
            startButton.innerHTML = 'Start Print';
            startButton.disabled = false;
        }
        
        showMessage('error', 'Er is een fout opgetreden bij het starten van de print');
    });
}

// Helper functie om sessie ID uit cookie te halen
function getSessionId() {
    const name = 'PHPSESSID=';
    const decodedCookie = decodeURIComponent(document.cookie);
    const cookieArray = decodedCookie.split(';');
    
    for (let i = 0; i < cookieArray.length; i++) {
        let cookie = cookieArray[i].trim();
        if (cookie.indexOf(name) === 0) {
            return cookie.substring(name.length, cookie.length);
        }
    }
    return "";
}

// Function to update Firebase for immediate Shelly activation
function updateFirebaseForPrintStart(reservationId) {
    // Check if Firebase is available
    if (!firebase || !firebase.database) {
        console.error('Firebase is not available');
        return;
    }
    
    const timestamp = new Date().toISOString();
    
    // Update Firebase direct - schrijf naar meerdere paden voor redundantie
    const updates = {};
    
    // 1. Update active_prints node
    updates[`active_prints/${reservationId}/print_started`] = true;
    updates[`active_prints/${reservationId}/start_time`] = timestamp;
    updates[`active_prints/${reservationId}/newly_added`] = true;  // Belangrijke vlag voor Python detectie
    updates[`active_prints/${reservationId}/client_initiated`] = true;
    
    // 2. Update print_start_commands node - specifiek voor directe actie
    updates[`print_start_commands/${reservationId}`] = {
        timestamp: timestamp,
        status: 'pending',
        client_initiated: true,
        browser_session_id: getSessionId()
    };
    
    // Voer de updates in één keer uit
    firebase.database().ref().update(updates)
        .then(() => {
            console.log(`Firebase succesvol bijgewerkt voor start print #${reservationId}`);
        })
        .catch(error => {
            console.error(`Firebase update fout voor #${reservationId}:`, error);
        });
}

// Helper functie voor het tonen van meldingen
function showMessage(type, text) {
    const messagesContainer = document.querySelector('.reservations-container') || document.body;
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `status-message ${type}`;
    messageDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <p>${text}</p>
    `;
    
    // Voeg de melding toe aan het begin van de container
    messagesContainer.insertBefore(messageDiv, messagesContainer.firstChild);
    
    // Laat de melding na enkele seconden verdwijnen
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        setTimeout(() => messageDiv.remove(), 500);
    }, 8000);
}