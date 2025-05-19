// VIVES ID input functionality
document.addEventListener('DOMContentLoaded', function () {
    console.log('VIVES ID input system initializing');

    // Remove any debug text
    document.body.childNodes.forEach(node => {
        if (node.nodeType === Node.TEXT_NODE && node.textContent) {
            node.textContent = '';
        }
    });

    // UI elements
    const userInfoContainer = document.getElementById('user-info-container');
    const vivesIdContainer = document.getElementById('vives-id-container');
    const vivesIdValue = document.getElementById('vives-id-value');
    const prefixPlaceholder = document.querySelector('.prefix-placeholder');
    const numberValue = document.querySelector('.number-value');
    const prefixButtons = document.querySelectorAll('.prefix-btn');
    const keypadButtons = document.querySelectorAll('.keypad-btn');
    const clearButton = document.getElementById('clear-btn');
    const submitButton = document.getElementById('submit-btn');
    const welcomeText = document.querySelector('.welcome-text');
    const actionMiddleContainer = document.getElementById('action-middle-container');

    // Track input state
    let selectedPrefix = '';
    let enteredDigits = '';
    const requiredDigits = 7;

    // Check if card ID is available
    if (typeof scannedCardId === 'undefined' || !scannedCardId) {
        // Handle error - redirect back to admin_cards.php
        console.error('No card ID provided');
        setTimeout(function () {
            window.location.href = 'admin_cards.php';
        }, 100);
        return;
    }

    console.log('Starting VIVES ID input for card:', scannedCardId);

    // Add event listeners to prefix buttons
    prefixButtons.forEach(button => {
        button.addEventListener('click', function () {
            const prefix = this.getAttribute('data-prefix');
            selectPrefix(prefix);

            // Update active state on buttons
            prefixButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Add event listeners to keypad number buttons
    keypadButtons.forEach(button => {
        if (!button.classList.contains('clear-btn') && !button.classList.contains('submit-btn')) {
            button.addEventListener('click', function () {
                const digit = this.getAttribute('data-value');
                addDigit(digit);
            });
        }
    });

    // Clear button functionality
    clearButton.addEventListener('click', function () {
        if (enteredDigits.length > 0) {
            // Remove last digit if digits exist
            enteredDigits = enteredDigits.slice(0, -1);
            updateDisplay();
        } else if (selectedPrefix) {
            // Clear prefix if no digits but prefix is selected
            selectedPrefix = '';
            prefixPlaceholder.textContent = '?';
            prefixPlaceholder.classList.add('active');
            prefixButtons.forEach(btn => btn.classList.remove('active'));
        }

        // Update submit button state
        updateSubmitButtonState();
    });

    // Submit button functionality
    submitButton.addEventListener('click', function () {
        if (this.disabled) return;

        const vivesId = selectedPrefix + enteredDigits;
        verifyVivesId(vivesId);
    });

    // Handle keyboard input
    document.addEventListener('keydown', function (e) {
        if (e.key >= '0' && e.key <= '9') {
            addDigit(e.key);
        } else if (e.key === 'Backspace') {
            if (enteredDigits.length > 0) {
                enteredDigits = enteredDigits.slice(0, -1);
                updateDisplay();
            } else if (selectedPrefix) {
                selectedPrefix = '';
                prefixPlaceholder.textContent = '?';
                prefixPlaceholder.classList.add('active');
                prefixButtons.forEach(btn => btn.classList.remove('active'));
            }
            updateSubmitButtonState();
        } else if (e.key === 'Enter' && isInputValid()) {
            const vivesId = selectedPrefix + enteredDigits;
            verifyVivesId(vivesId);
        } else if (e.key === 'u' || e.key === 'U') {
            selectPrefix('U');
            prefixButtons[0].classList.add('active');
            prefixButtons[1].classList.remove('active');
        } else if (e.key === 'r' || e.key === 'R') {
            selectPrefix('R');
            prefixButtons[1].classList.add('active');
            prefixButtons[0].classList.remove('active');
        }
    });

    // Function to select a prefix
    function selectPrefix(prefix) {
        selectedPrefix = prefix;
        prefixPlaceholder.textContent = prefix;
        prefixPlaceholder.classList.remove('active');
        updateSubmitButtonState();
    }

    // Function to add a digit
    function addDigit(digit) {
        if (enteredDigits.length < requiredDigits) {
            enteredDigits += digit;
            updateDisplay();
            updateSubmitButtonState();
        }
    }

    // Function to update the display
    function updateDisplay() {
        numberValue.textContent = enteredDigits;
    }

    // Function to check if input is valid
    function isInputValid() {
        return selectedPrefix && enteredDigits.length === requiredDigits;
    }

    // Function to update submit button state
    function updateSubmitButtonState() {
        if (isInputValid()) {
            submitButton.disabled = false;
            submitButton.classList.add('active');
        } else {
            submitButton.disabled = true;
            submitButton.classList.remove('active');
        }
    }

    // Function to update header welcome text
    function updateWelcomeText(message) {
        if (welcomeText) {
            welcomeText.textContent = message;
        }
    }

    // Function to verify VIVES ID
    function verifyVivesId(vivesId) {
        console.log('Verifying VIVES ID:', vivesId);
        updateWelcomeText('VIVES nummer wordt gecontroleerd...');

        // Show visual indicator that verification is happening
        if (submitButton) {
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            submitButton.disabled = true;
        }

        fetch('verify_card_admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                type: 'verifyVivesId',
                vivesId: vivesId
            })
        })
            .then(response => {
                console.log('VIVES ID verification response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('VIVES ID verification response:', data);

                if (data.success) {
                    if (data.exists) {
                        // User found with this VIVES ID - DIRECTLY CALL FUNCTION
                        showUserConfirmation(data.user);
                    } else {
                        // No user found with this VIVES ID
                        updateWelcomeText('Gebruiker niet gevonden');

                        if (submitButton) {
                            submitButton.innerHTML = '<i class="fas fa-check"></i>';
                            submitButton.disabled = true;
                        }

                        // Reset after 3 seconds
                        setTimeout(() => {
                            updateWelcomeText('Voer je VIVES nummer in');
                            if (submitButton) {
                                submitButton.innerHTML = '<i class="fas fa-check"></i>';
                                updateSubmitButtonState();
                            }
                        }, 3000);
                    }
                } else {
                    // Verification failed
                    updateWelcomeText('Verificatie mislukt');

                    if (submitButton) {
                        submitButton.innerHTML = '<i class="fas fa-check"></i>';
                        submitButton.disabled = true;
                    }

                    // Reset after 3 seconds
                    setTimeout(() => {
                        updateWelcomeText('Voer je VIVES nummer in');
                        if (submitButton) {
                            submitButton.innerHTML = '<i class="fas fa-check"></i>';
                            updateSubmitButtonState();
                        }
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Error verifying VIVES ID:', error);
                updateWelcomeText('Fout bij verificatie');

                if (submitButton) {
                    submitButton.innerHTML = '<i class="fas fa-check"></i>';
                    submitButton.disabled = true;
                }

                // Reset after 3 seconds
                setTimeout(() => {
                    updateWelcomeText('Voer je VIVES nummer in');
                    if (submitButton) {
                        submitButton.innerHTML = '<i class="fas fa-check"></i>';
                        updateSubmitButtonState();
                    }
                }, 3000);
            });
    }

    // Function to show user confirmation
    function showUserConfirmation(userData) {
        console.log('Showing user confirmation:', userData);

        // First, ensure we have good data
        if (!userData || !userData.Voornaam || !userData.Naam) {
            console.error('Invalid user data received:', userData);
            updateWelcomeText('Ongeldige gebruikersgegevens ontvangen');
            return;
        }

        try {
            // Hide VIVES ID input container
            const vivesPanels = document.querySelectorAll('#vives-id-container .vives-id-input-container');
            vivesPanels.forEach(panel => {
                panel.style.display = 'none';
            });

            // Create user info confirmation card with two columns
            const userInfoHTML = `
                <div class="user-info-card">
                    <div class="user-info-header">
                        <h3>Gebruiker gevonden</h3>
                    </div>
                    <div class="user-info-body">
                        <div class="user-details">
                            <div class="detail-column">
                                <div class="detail-row">
                                    <div class="detail-label">Naam:</div>
                                    <div class="detail-value">${userData.Voornaam} ${userData.Naam}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">VIVES nr:</div>
                                    <div class="detail-value">${userData.Vives_id || 'Onbekend'}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">E-mail:</div>
                                    <div class="detail-value">${userData.Emailadres || 'Niet beschikbaar'}</div>
                                </div>
                            </div>
                            <div class="detail-column">
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
                        <div class="confirmation-message">
                            <p>Is dit de juiste persoon om de kaart <strong>${scannedCardId}</strong> aan te koppelen?</p>
                        </div>
                    </div>
                </div>
            `;

            // Make sure the container exists and is visible
            if (!userInfoContainer) {
                console.error('User info container not found');
                return;
            }

            console.log('Setting user info HTML');
            userInfoContainer.innerHTML = userInfoHTML;
            userInfoContainer.style.display = 'block';

            // Move confirm/cancel buttons to the action bar
            if (actionMiddleContainer) {
                actionMiddleContainer.innerHTML = `
                    <button class="cancel-button" id="cancel-btn">
                        <i class="fas fa-times"></i> Annuleren
                    </button>
                    <button class="confirm-button" id="confirm-btn">
                        <i class="fas fa-check"></i> Bevestigen
                    </button>
                `;

                // Add event listeners for the buttons
                document.getElementById('cancel-btn').addEventListener('click', function () {
                    // Hide user info and show VIVES ID input again
                    userInfoContainer.style.display = 'none';
                    vivesPanels.forEach(panel => {
                        panel.style.display = 'flex';
                    });
                    updateWelcomeText('Voer je VIVES nummer in');

                    // Remove action buttons
                    actionMiddleContainer.innerHTML = '';

                    if (submitButton) {
                        submitButton.innerHTML = '<i class="fas fa-check"></i>';
                        updateSubmitButtonState();
                    }
                });

                document.getElementById('confirm-btn').addEventListener('click', function () {
                    // Save card to user
                    saveCardToUser(userData);
                });
            }

            console.log('User confirmation UI displayed successfully');
        } catch (error) {
            console.error('Error displaying user confirmation:', error);
            alert('Er is een fout opgetreden bij het tonen van de gebruikersgegevens: ' + error.message);
        }
    }

    // Function to save card to user
    function saveCardToUser(userData) {
        console.log('Saving card to user:', userData);

        // Show loading state
        const confirmBtn = document.getElementById('confirm-btn');
        if (confirmBtn) {
            const originalBtnText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Bezig...';
            confirmBtn.disabled = true;
        }

        // Update welcome text
        updateWelcomeText('Kaart wordt gekoppeld...');

        fetch('verify_card_admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                type: 'saveCard',
                cardId: scannedCardId,
                vivesId: userData.Vives_id,
                userId: userData.User_ID
            })
        })
            .then(response => {
                console.log('Save card response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Save card response:', data);

                if (data.success) {
                    // Card successfully saved to user
                    showSuccessMessage(userData);
                } else {
                    // Save failed
                    if (confirmBtn) {
                        confirmBtn.innerHTML = originalBtnText;
                        confirmBtn.disabled = false;
                    }
                    updateWelcomeText('Fout bij koppelen kaart');

                    // Show error message somewhere in the UI
                    const confirmationMessage = document.querySelector('.confirmation-message');
                    if (confirmationMessage) {
                        confirmationMessage.innerHTML = `
                        <p class="error-message">${data.message}</p>
                        <p>Probeer opnieuw of kies een andere gebruiker.</p>
                    `;
                    }
                }
            })
            .catch(error => {
                console.error('Error saving card:', error);
                if (confirmBtn) {
                    confirmBtn.innerHTML = originalBtnText;
                    confirmBtn.disabled = false;
                }
                updateWelcomeText('Fout bij koppelen kaart');

                // Show error message
                const confirmationMessage = document.querySelector('.confirmation-message');
                if (confirmationMessage) {
                    confirmationMessage.innerHTML = `
                    <p class="error-message">Er is een fout opgetreden: ${error.message}</p>
                    <p>Probeer opnieuw.</p>
                `;
                }
            });
    }

    // Function to show success message
    function showSuccessMessage(userData) {
        console.log('Showing success message');

        // Update welcome text
        updateWelcomeText('Kaart succesvol geregistreerd');

        // Update the user info card to show success
        const userInfoCard = document.querySelector('.user-info-card');
        if (userInfoCard) {
            userInfoCard.innerHTML = `
            <div class="user-info-header">
                <h3>Kaart succesvol geregistreerd</h3>
                <div class="status-badge success">Geregistreerd</div>
            </div>
            <div class="user-info-body">
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <p>De kaart <strong>${scannedCardId}</strong> is succesvol gekoppeld aan ${userData.Voornaam} ${userData.Naam}.</p>
                </div>
            </div>
        `;

            // Remove all action buttons - as requested
            if (actionMiddleContainer) {
                actionMiddleContainer.innerHTML = '';
            }

            // Redirect after 3 seconds to scan another card
            setTimeout(() => {
                window.location.href = 'admin_cards.php';
            }, 3000);
        }
    }
});