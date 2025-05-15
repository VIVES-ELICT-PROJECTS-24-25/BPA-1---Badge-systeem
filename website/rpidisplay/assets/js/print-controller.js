// Globale variabele om dubbele acties te voorkomen
let isPrintStartInProgress = false;

/**
 * Start een print voor een specifieke reservering
 * @param {number} reservationId - ID van de reservering
 */
function startPrint(reservationId) {
    console.log('Starting print for reservation:', reservationId);
    
    // Voorkom dubbele acties
    if (isPrintStartInProgress) {
        console.log('Print start already in progress, ignoring duplicate request');
        return;
    }
    
    isPrintStartInProgress = true;
    
    // Toon laadmelding
    const actionButton = document.querySelector(`.start-print-btn[data-id="${reservationId}"]`);
    const originalText = actionButton ? actionButton.innerHTML : '';
    
    if (actionButton) {
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
        isPrintStartInProgress = false;
        
        if (data.success) {
            // Toon succes bericht
            showPrintStartedMessage(data);
            
            // Update de UI om te tonen dat de print actief is
            updateUIForActivePrint(reservationId);
        } else {
            // Foutmelding tonen
            showPrintErrorMessage(data.message || 'Onbekende fout');
            
            // Reset de knop
            if (actionButton) {
                actionButton.innerHTML = originalText;
                actionButton.disabled = false;
            }
        }
    })
    .catch(error => {
        console.error('Error starting print:', error);
        isPrintStartInProgress = false;
        
        showPrintErrorMessage('Er is een fout opgetreden bij het starten van de print: ' + error.message);
        
        // Reset de knop
        if (actionButton) {
            actionButton.innerHTML = originalText;
            actionButton.disabled = false;
        }
    });
}

/**
 * Toon een succesbericht voor gestarte print
 * @param {object} data - Response data
 */
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

/**
 * Toon een foutmelding
 * @param {string} errorMessage - De foutmelding
 */
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

/**
 * Update de UI om te tonen dat de print actief is
 * @param {number} reservationId - ID van de reservering
 */
function updateUIForActivePrint(reservationId) {
    // Update alle reserveringskaarten die overeenkomen met de ID
    const reservationCards = document.querySelectorAll(`.reservation-card`);
    
    reservationCards.forEach(card => {
        // Controleer of dit de juiste kaart is (hetzij via data-id of door andere info)
        const cardId = card.getAttribute('data-id') || '';
        const cardTitle = card.querySelector('.reservation-title');
        const titleText = cardTitle ? cardTitle.textContent : '';
        
        if (cardId == reservationId || titleText.includes(`#${reservationId}`)) {
            // Dit is de juiste reserveringskaart
            // Vervang de start-print knop met een "actieve print" melding
            const actionsDiv = card.querySelector('.reservation-actions');
            if (actionsDiv) {
                actionsDiv.innerHTML = `
                    <div class="print-status active">
                        <i class="fas fa-print"></i> Print actief
                    </div>
                `;
            }
            
            // Voeg een klasse toe aan de kaart om aan te geven dat deze actief is
            card.classList.add('printing');
        }
    });
}

/**
 * Controleer de status van een reservering en werk de UI bij
 * @param {number} reservationId - ID van de reservering
 */
function checkPrintStatus(reservationId) {
    fetch(`check_print_status.php?id=${reservationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.is_active) {
                // Print is actief, update UI om dit te tonen
                updateUIForActivePrint(reservationId);
            }
        })
        .catch(error => console.error('Error checking print status:', error));
}

// Voeg een functie toe om de printstatussen te controleren bij het laden van de pagina
function initializePrintController() {
    // Haal alle reserveringskaarten op
    const reservationCards = document.querySelectorAll('.reservation-card');
    
    reservationCards.forEach(card => {
        // Probeer de reservering ID te krijgen
        const cardId = card.getAttribute('data-id');
        const cardTitle = card.querySelector('.reservation-title');
        const titleText = cardTitle ? cardTitle.textContent : '';
        
        let reservationId = null;
        
        if (cardId) {
            reservationId = cardId;
        } else if (titleText) {
            // Probeer ID uit de titel te halen (bijv. "Reservering #123")
            const match = titleText.match(/#(\d+)/);
            if (match && match[1]) {
                reservationId = match[1];
            }
        }
        
        if (reservationId) {
            // Controleer de status van deze reservering
            checkPrintStatus(reservationId);
            
            // Voeg eventlistener toe aan de start-print knop als die er is
            const startPrintBtn = card.querySelector('.start-print-btn');
            if (startPrintBtn) {
                // Verwijder bestaande event listeners om dubbele calls te voorkomen
                startPrintBtn.replaceWith(startPrintBtn.cloneNode(true));
                
                // Voeg nieuwe event listener toe
                card.querySelector('.start-print-btn').addEventListener('click', function(e) {
                    e.preventDefault();
                    startPrint(reservationId);
                });
            }
        }
    });
}

// Voer de initialisatie uit wanneer de pagina geladen is
document.addEventListener('DOMContentLoaded', initializePrintController);