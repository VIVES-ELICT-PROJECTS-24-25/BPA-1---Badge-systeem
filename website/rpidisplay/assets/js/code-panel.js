// Code panel functionality
document.addEventListener('DOMContentLoaded', function () {
    console.log('Code panel initialized');

    // UI elements
    const codeValue = document.getElementById('code-value');
    const codeDots = document.querySelectorAll('.code-dot');
    const keypadButtons = document.querySelectorAll('.keypad-btn');
    const clearButton = document.getElementById('clear-btn');
    const submitButton = document.getElementById('submit-btn');
    const statusMessage = document.getElementById('status-message');
    const userInfoContainer = document.getElementById('user-info-container');
    const codeContainer = document.querySelector('.code-container');

    // Verberg debug output tekst indien aanwezig
    document.querySelectorAll('body > *').forEach(node => {
        if (node.nodeType === Node.TEXT_NODE && node.textContent && node.textContent.includes('Current Date')) {
            node.textContent = '';
        }
    });

    let currentCode = '';
    const codeLength = 6;

    // Add event listeners to keypad buttons
    keypadButtons.forEach(button => {
        if (!button.classList.contains('clear-btn') && !button.classList.contains('submit-btn')) {
            button.addEventListener('click', function () {
                const digit = this.getAttribute('data-value');
                handleDigitInput(digit);
            });
        }
    });

    // Clear button functionality
    clearButton.addEventListener('click', function () {
        currentCode = currentCode.slice(0, -1); // Remove last digit
        updateCodeDisplay();
    });

    // Submit button functionality
    submitButton.addEventListener('click', function () {
        if (currentCode.length === codeLength) {
            verifyCode(currentCode);
        } else {
            showStatus('Voer een volledige 6-cijferige code in', 'error');
        }
    });

    // Handle keyboard input
    document.addEventListener('keydown', function (e) {
        if (e.key >= '0' && e.key <= '9') {
            handleDigitInput(e.key);
        } else if (e.key === 'Backspace') {
            currentCode = currentCode.slice(0, -1);
            updateCodeDisplay();
        } else if (e.key === 'Enter') {
            if (currentCode.length === codeLength) {
                verifyCode(currentCode);
            } else {
                showStatus('Voer een volledige 6-cijferige code in', 'error');
            }
        }
    });

    // Function to handle digit input
    function handleDigitInput(digit) {
        if (currentCode.length < codeLength) {
            currentCode += digit;
            updateCodeDisplay();

            // Auto-submit when code length is reached
            if (currentCode.length === codeLength) {
                setTimeout(() => {
                    verifyCode(currentCode);
                }, 300);
            }
        }
    }

    // Function to update code display - Toon cijfers in plaats van dots
    function updateCodeDisplay() {
        // Toon de cijfers direct in de code-value
        codeValue.textContent = currentCode;

        // Verwijder de cursor als de code volledig is
        if (currentCode.length === codeLength) {
            codeValue.classList.add('complete');
        } else {
            codeValue.classList.remove('complete');
        }
    }

    // Function to show status message
    function showStatus(message, type) {
        statusMessage.innerHTML = message;
        statusMessage.className = `status-message ${type}`;
    }

    // Function to verify the code
    function verifyCode(code) {
        showStatus('<i class="fas fa-spinner fa-spin"></i> Code wordt gecontroleerd...', 'loading');

        console.log('Verifying code:', code);

        fetch('verify_code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ code: code })
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
                console.log('Code verification response:', data);

                if (data.success) {
                    // Code is valid
                    handleSuccessfulVerification(data);
                } else {
                    // Code verification failed
                    showStatus(`<i class="fas fa-exclamation-circle"></i> ${data.message}`, 'error');

                    // Reset code after showing the error
                    setTimeout(() => {
                        currentCode = '';
                        updateCodeDisplay();
                        showStatus('Voer een 6-cijferige code in', '');
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error verifying code:', error);
                showStatus(`<i class="fas fa-exclamation-circle"></i> Fout bij verificatie: ${error.message}`, 'error');

                // Reset after 3 seconds
                setTimeout(() => {
                    currentCode = '';
                    updateCodeDisplay();
                    showStatus('Voer een 6-cijferige code in', '');
                }, 3000);
            });
    }

    // Function to handle successful verification
    function handleSuccessfulVerification(data) {
        // Show success message
        showStatus(`<i class="fas fa-check-circle"></i> Code geaccepteerd!`, 'success');

        // Wait a moment before showing reservations
        setTimeout(() => {
            // Store user data in session
            storeSession(data.user || data.reservation)
                .then(() => {
                    displayReservation(data.reservation);
                })
                .catch(error => {
                    console.error('Session storage error:', error);
                    showStatus(`<i class="fas fa-exclamation-circle"></i> Sessie fout: ${error.message}`, 'error');
                });
        }, 1500);
    }

    // Function to store session data
    function storeSession(userData) {
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

    // Function to display reservation - gecontroleerd op print_started status
    function displayReservation(reservation) {
        // Hide code container
        codeContainer.style.display = 'none';

        // Verberg de h1 titel
        const h1Element = document.querySelector('h1');
        if (h1Element) h1Element.style.display = 'none';

        const startTime = new Date(reservation.PRINT_START);
        const endTime = new Date(reservation.PRINT_END);
        const now = new Date();
        const isActive = now >= startTime && now <= endTime;
        const isPrintStarted = reservation.print_started === 1 || reservation.print_started === true;

        // Cre�er welkomst element in de header als het nog niet bestaat
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

        // Wis de tijdelijke toegang tekst
        welcomeText.innerHTML = ``;

        // Haal printer naam in plaats van ID
        const printerName = reservation.Versie_Toestel || `Printer #${reservation.Printer_ID}`;

        const reservationHTML = `
        <div class="reservations-container">
            <h3 style="text-align: center; width: 100%; display: block; margin-left: auto; margin-right: auto;">Je actieve reservering</h3>
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
        </div>
    `;

        // Display the reservation
        userInfoContainer.innerHTML = reservationHTML + `
        <div class="action-buttons">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Uitloggen
            </a>
        </div>
    `;
        userInfoContainer.style.display = 'block';

        // Verwijder de actie buttons container van de code pagina
        const codeActionButtons = document.querySelector('.code-action-buttons');
        if (codeActionButtons) {
            codeActionButtons.style.display = 'none';
        }

        // Add event listener for the Start Print button only if it exists
        const startPrintButton = document.querySelector('.start-print-btn');
        if (startPrintButton) {
            startPrintButton.addEventListener('click', function (e) {
                e.preventDefault();
                const reservationId = this.getAttribute('data-id');
                startPrint(reservationId);
            });
        }
    }

    // Function to start print for a reservation
    function startPrint(reservationId) {
        console.log('Starting print for reservation:', reservationId);

        // Toon laadmelding
        const actionButton = document.querySelector(`.start-print-btn[data-id="${reservationId}"]`);
        if (actionButton) {
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
                if (data.success) {
                    // Update UI om te tonen dat de print actief is
                    const reservationCard = document.querySelector(`.reservation-card[data-id="${reservationId}"]`);
                    if (reservationCard) {
                        const actionsDiv = reservationCard.querySelector('.reservation-actions');
                        if (actionsDiv) {
                            actionsDiv.innerHTML = `
                            <div class="print-status active">
                                <i class="fas fa-print"></i> Print actief
                            </div>
                        `;
                        }
                    }

                    // Toon succesbericht
                    const successMsg = document.createElement('div');
                    successMsg.className = 'status-message success';
                    successMsg.innerHTML = `
                    <i class="fas fa-check-circle"></i>
                    <p>Print succesvol gestart! De printer wordt nu ingeschakeld.</p>
                `;

                    // Voeg het bericht toe boven de reserveringskaart
                    const reservationsContainer = document.querySelector('.reservations-container');
                    if (reservationsContainer) {
                        reservationsContainer.insertBefore(successMsg, reservationsContainer.firstChild);

                        // Laat het bericht na 8 seconden verdwijnen
                        setTimeout(() => {
                            successMsg.style.opacity = '0';
                            setTimeout(() => {
                                successMsg.remove();
                            }, 500);
                        }, 8000);
                    }
                } else {
                    // Toon foutmelding
                    alert('Fout bij het starten van de print: ' + data.message);

                    // Reset de knop
                    if (actionButton) {
                        actionButton.innerHTML = originalText;
                        actionButton.disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Error starting print:', error);
                alert('Er is een fout opgetreden bij het starten van de print: ' + error.message);

                // Reset de knop
                if (actionButton) {
                    actionButton.innerHTML = originalText;
                    actionButton.disabled = false;
                }
            });
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

    // Verberg debug output in hele pagina
    function hideDebugOutput() {
        const bodyNodes = document.body.childNodes;
        for (let i = 0; i < bodyNodes.length; i++) {
            const node = bodyNodes[i];
            if (node.nodeType === Node.TEXT_NODE && node.textContent && node.textContent.includes('Current Date')) {
                node.textContent = '';
            }
        }
    }

    // Direct uitvoeren
    hideDebugOutput();
});