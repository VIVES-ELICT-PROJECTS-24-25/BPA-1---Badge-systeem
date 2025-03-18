document.addEventListener('DOMContentLoaded', function() {
    // Tab navigation
    const tabs = document.querySelectorAll('.sidebar li');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and content
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Add active class to current tab and content
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Load initial dashboard data
    loadDashboardStats();
    loadRecentReservations();
    loadAllPrinters();
    loadAllUsers();
    loadAllReservations();
    
    // Initialize modals
    setupModals();
    
    // Initialize form handlers
    setupPrinterForm();
    setupUserForm();
    setupReservationForm();
});

// ============== Dashboard Functions ==============
function loadDashboardStats() {
    // Fetch printer count
    fetch('printer_api.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 200) {
                document.getElementById('printer-count').textContent = data.data.length || 0;
            }
        })
        .catch(error => console.error('Error fetching printer stats:', error));
    
    // Fetch user count
    fetch('gebruiker_api.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 200) {
                document.getElementById('user-count').textContent = data.data.length || 0;
            }
        })
        .catch(error => console.error('Error fetching user stats:', error));
    
    // Fetch active reservations
    fetch('reservatie_api.php?action=active')
        .then(response => response.json())
        .then(data => {
            if (data.status === 200) {
                document.getElementById('active-reservations').textContent = data.data.count || 0;
            }
        })
        .catch(error => console.error('Error fetching active reservations:', error));
}

function loadRecentReservations() {
    fetch('reservatie_api.php?action=recent')
        .then(response => response.json())
        .then(data => {
            if (data.status === 200) {
                const tbody = document.querySelector('#recent-reservations tbody');
                tbody.innerHTML = '';
                
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6">Geen recente reserveringen</td></tr>';
                    return;
                }
                
                data.data.forEach(reservation => {
                    const now = new Date();
                    const startTime = new Date(reservation.Pr_Start);
                    const endTime = new Date(reservation.Pr_End);
                    
                    let status;
                    let statusClass;
                    
                    if (now < startTime) {
                        status = 'Gepland';
                        statusClass = 'status-reserved';
                    } else if (now >= startTime && now <= endTime) {
                        status = 'Actief';
                        statusClass = 'status-available';
                    } else {
                        status = 'Voltooid';
                        statusClass = 'status-unavailable';
                    }
                    
                    const row = `
                        <tr>
                            <td>${reservation.Reservatie_ID}</td>
                            <td>${reservation.GebruikerNaam || 'Onbekend'}</td>
                            <td>${reservation.PrinterNaam || 'Onbekend'}</td>
                            <td>${formatDateTime(reservation.Pr_Start)}</td>
                            <td>${formatDateTime(reservation.Pr_End)}</td>
                            <td><span class="status-badge ${statusClass}">${status}</span></td>
                        </tr>
                    `;
                    
                    tbody.innerHTML += row;
                });
            }
        })
        .catch(error => console.error('Error fetching recent reservations:', error));
}

// ============== Printer Functions ==============
function loadAllPrinters() {
    fetch('printer_api.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 200) {
                const tbody = document.querySelector('#printers-table tbody');
                tbody.innerHTML = '';
                
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6">Geen printers gevonden</td></tr>';
                    return;
                }
                
                data.data.forEach(printer => {
                    let statusClass = '';
                    
                    switch (printer.Status) {
                        case 'Beschikbaar':
                            statusClass = 'status-available';
                            break;
                        case 'In Onderhoud':
                            statusClass = 'status-maintenance';
                            break;
                        case 'Gereserveerd':
                            statusClass = 'status-reserved';
                            break;
                        case 'Buiten Dienst':
                            statusClass = 'status-unavailable';
                            break;
                    }
                    
                    const row = `
                        <tr>
                            <td>${printer.Printer_ID}</td>
                            <td>${printer.Naam}</td>
                            <td><span class="status-badge ${statusClass}">${printer.Status}</span></td>
                            <td>${formatDateTime(printer.Laatste_Status_Change)}</td>
                            <td>${printer.Info || '-'}</td>
                            <td class="action-buttons">
                                <button class="action-btn edit-btn" onclick="editPrinter(${printer.Printer_ID})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn delete-btn" onclick="confirmDeletePrinter(${printer.Printer_ID}, '${printer.Naam}')">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    
                    tbody.innerHTML += row;
                });
                
                // Also populate printer select for reservations
                const printerSelect = document.getElementById('printer-select');
                if (printerSelect) {
                    printerSelect.innerHTML = '<option value="">Selecteer printer</option>';
                    
                    data.data.filter(printer => printer.Status === 'Beschikbaar')
                        .forEach(printer => {
                            printerSelect.innerHTML += `<option value="${printer.Printer_ID}">${printer.Naam}</option>`;
                        });
                }
            }
        })
        .catch(error => console.error('Error fetching printers:', error));
}

function setupPrinterForm() {
    const addPrinterBtn = document.getElementById('add-printer-btn');
    const printerForm = document.getElementById('printer-form');
    const cancelPrinterBtn = document.getElementById('cancel-printer');
    
    // Add printer button
    if (addPrinterBtn) {
        addPrinterBtn.addEventListener('click', function() {
            document.getElementById('printer-modal-title').textContent = 'Printer Toevoegen';
            document.getElementById('printer-id').value = '';
            printerForm.reset();
            openModal('printer-modal');
        });
    }
    
    // Cancel button
    if (cancelPrinterBtn) {
        cancelPrinterBtn.addEventListener('click', function() {
            closeModal('printer-modal');
        });
    }
    
    // Form submission
    if (printerForm) {
        printerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const printerId = document.getElementById('printer-id').value;
            const printerData = {
                Naam: document.getElementById('printer-name').value,
                Status: document.getElementById('printer-status').value,
                Info: document.getElementById('printer-info').value
            };
            
            if (printerId) {
                // Update existing printer
                updatePrinter(printerId, printerData);
            } else {
                // Create new printer
                createPrinter(printerData);
            }
        });
    }
}

function createPrinter(printerData) {
    fetch('printer_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(printerData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 201) {
            showNotification('Printer succesvol toegevoegd', 'success');
            closeModal('printer-modal');
            loadAllPrinters();
            loadDashboardStats();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error creating printer:', error);
        showNotification('Er is een fout opgetreden', 'error');
    });
}

function editPrinter(printerId) {
    fetch(`printer_api.php?id=${printerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 200) {
                const printer = data.data;
                
                document.getElementById('printer-modal-title').textContent = 'Printer Bewerken';
                document.getElementById('printer-id').value = printer.Printer_ID;
                document.getElementById('printer-name').value = printer.Naam;
                document.getElementById('printer-status').value = printer.Status;
                document.getElementById('printer-info').value = printer.Info || '';
                
                openModal('printer-modal');
            } else {
                showNotification('Printer niet gevonden', 'error');
            }
        })
        .catch(error => {
            console.error('Error fetching printer details:', error);
            showNotification('Er is een fout opgetreden', 'error');
        });
}

function updatePrinter(printerId, printerData) {
    fetch(`printer_api.php?id=${printerId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(printerData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 200) {
            showNotification('Printer succesvol bijgewerkt', 'success');
            closeModal('printer-modal');
            loadAllPrinters();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error updating printer:', error);
        showNotification('Er is een fout opgetreden', 'error');
    });
}

function confirmDeletePrinter(printerId, printerName) {
    document.getElementById('confirmation-message').textContent = `Weet je zeker dat je de printer "${printerName}" wilt verwijderen?`;
    
    const confirmDeleteBtn = document.getElementById('confirm-delete');
    confirmDeleteBtn.onclick = function() {
        deletePrinter(printerId);
        closeModal('confirmation-modal');
    };
    
    openModal('confirmation-modal');
}

function deletePrinter(printerId) {
    fetch(`printer_api.php?id=${printerId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 200) {
            showNotification('Printer succesvol verwijderd', 'success');
            loadAllPrinters();
            loadDashboardStats();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error deleting printer:', error);
        showNotification('Er is een fout opgetreden', 'error');
    });
}

// ============== User Functions ==============
function loadAllUsers() {
    fetch('gebruiker_api.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 200) {
                const tbody = document.querySelector('#users-table tbody');
                tbody.innerHTML = '';
                
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7">Geen gebruikers gevonden</td></tr>';
                    return;
                }
                
                data.data.forEach(user => {
                    const row = `
                        <tr>
                            <td>${user.User_ID}</td>
                            <td>${user.Voornaam} ${user.Naam}</td>
                            <td>${user.Email}</td>
                            <td>${user.rol}</td>
                            <td>${formatDate(user.Aanmaak_Acc)}</td>
                            <td>${user.Laatste_Aanmeld ? formatDateTime(user.Laatste_Aanmeld) : '-'}</td>
                            <td class="action-buttons">
                                <button class="action-btn edit-btn" onclick="editUser(${user.User_ID})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn delete-btn" onclick="confirmDeleteUser(${user.User_ID}, '${user.Voornaam} ${user.Naam}')">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    
                    tbody.innerHTML += row;
                });
                
                // Also populate user select for reservations
                const userSelect = document.getElementById('user-select');
                if (userSelect) {
                    userSelect.innerHTML = '<option value="">Selecteer gebruiker</option>';
                    
                    data.data.forEach(user => {
                        userSelect.innerHTML += `<option value="${user.User_ID}">${user.Voornaam} ${user.Naam}</option>`;
                    });
                }
            }
        })
        .catch(error => console.error('Error fetching users:', error));
}

function setupUserForm() {
    const addUserBtn = document.getElementById('add-user-btn');
    const userForm = document.getElementById('user-form');
    const cancelUserBtn = document.getElementById('cancel-user');
    
    // Add user button
    if (addUserBtn) {
        addUserBtn.addEventListener('click', function() {
            document.getElementById('user-modal-title').textContent = 'Gebruiker Toevoegen';
            document.getElementById('user-id').value = '';
            userForm.reset();
            document.getElementById('user-ww').required = true;
            openModal('user-modal');
        });
    }
    
    // Cancel button
    if (cancelUserBtn) {
        cancelUserBtn.addEventListener('click', function() {
            closeModal('user-modal');
        });
    }
    
    // Form submission
    if (userForm) {
        userForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const userId = document.getElementById('user-id').value;
            const userData = {
                Voornaam: document.getElementById('user-voornaam').value,
                Naam: document.getElementById('user-naam').value,
                Email: document.getElementById('user-email').value,
                Telnr: document.getElementById('user-telnr').value,
                rol: document.getElementById('user-role').value
            };
            
            const password = document.getElementById('user-ww').value;
            if (password) {
                userData.WW = password;
            }
            
            // Create Vives entry if provided
            const vivesNr = document.getElementById('user-vives-nr').value;
            if (vivesNr) {
                // This is simplified - in a real app you'd first check if the Vives entry exists
                // and then associate the user with it
                userData.Vives_Info_ID = 1; // Placeholder
            }
            
            if (userId) {
                // Update existing user
                updateUser(userId, userData);
            } else {
                // Create new user
                createUser(userData);
            }
        });
    }
}

function createUser(userData) {
    fetch('gebruiker_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(userData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 201) {
            showNotification('Gebruiker succesvol toegevoegd', 'success');
            closeModal('user-modal');
            loadAllUsers();
            loadDashboardStats();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error creating user:', error);
        showNotification('Er is een fout opgetreden', 'error');
    });
}

function editUser(userId) {
    fetch(`gebruiker_api.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 200) {
                const user = data.data;
                
                document.getElementById('user-modal-title').textContent = 'Gebruiker Bewerken';
                document.getElementById('user-id').value = user.User_ID;
                document.getElementById('user-voornaam').value = user.Voornaam;
                document.getElementById('user-naam').value = user.Naam;
                document.getElementById('user-email').value = user.Email;
                document.getElementById('user-telnr').value = user.Telnr || '';
                document.getElementById('user-role').value = user.rol;
                
                // Password is not required when editing
                document.getElementById('user-ww').required = false;
                
                openModal('user-modal');
            } else {
                showNotification('Gebruiker niet gevonden', 'error');
            }
        })
        .catch(error => {
            console.error('Error fetching user details:', error);
            showNotification('Er is een fout opgetreden', 'error');
        });
}

function updateUser(userId, userData) {
    fetch(`gebruiker_api.php?id=${userId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(userData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 200) {
            showNotification('Gebruiker succesvol bijgewerkt', 'success');
            closeModal('user-modal');
            loadAllUsers();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error updating user:', error);
        showNotification('Er is een fout opgetreden', 'error');
    });
}

function confirmDeleteUser(userId, userName) {
    document.getElementById('confirmation-message').textContent = `Weet je zeker dat je de gebruiker "${userName}" wilt verwijderen?`;
    
    const confirmDeleteBtn = document.getElementById('confirm-delete');
    confirmDeleteBtn.onclick = function() {
        deleteUser(userId);
        closeModal('confirmation-modal');
    };
    
    openModal('confirmation-modal');
}

function deleteUser(userId) {
    fetch(`gebruiker_api.php?id=${userId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 200) {
            showNotification('Gebruiker succesvol verwijderd', 'success');
            loadAllUsers();
            loadDashboardStats();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error deleting user:', error);
        showNotification('Er is een fout opgetreden', 'error');
    });
}

// ============== Reservation Functions ==============
function loadAllReservations() {
    fetch('reservatie_api.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 200) {
                const tbody = document.querySelector('#reservations-table tbody');
                tbody.innerHTML = '';
                
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8">Geen reserveringen gevonden</td></tr>';
                    return;
                }
                
                data.data.forEach(reservation => {
                    const row = `
                        <tr>
                            <td>${reservation.Reservatie_ID}</td>
                            <td>${reservation.GebruikerNaam || 'Onbekend'}</td>
                            <td>${reservation.PrinterNaam || 'Onbekend'}</td>
                            <td>${formatDateTime(reservation.Date_Time_res)}</td>
                            <td>${formatDateTime(reservation.Pr_Start)}</td>
                            <td>${formatDateTime(reservation.Pr_End)}</td>
                            <td>${reservation.Pin}</td>
                            <td class="action-buttons">
                                <button class="action-btn edit-btn" onclick="editReservation(${reservation.Reservatie_ID})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn delete-btn" onclick="confirmDeleteReservation(${reservation.Reservatie_ID})">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    
                    tbody.innerHTML += row;
                });
            }
        })
        .catch(error => console.error('Error fetching reservations:', error));
}

function setupReservationForm() {
    const addReservationBtn = document.getElementById('add-reservation-btn');
    const reservationForm = document.getElementById('reservation-form');
    const cancelReservationBtn = document.getElementById('cancel-reservation');
    
    // Generate random PIN button
    const pinInput = document.getElementById('pin-code');
    if (pinInput) {
        pinInput.addEventListener('dblclick', function() {
            this.value = generateRandomPin();
        });
    }
    
    // Add reservation button
    if (addReservationBtn) {
        addReservationBtn.addEventListener('click', function() {
            document.getElementById('reservation-modal-title').textContent = 'Reservering Toevoegen';
            document.getElementById('reservation-id').value = '';
            reservationForm.reset();
            
            // Set default start and end times
            const now = new Date();
            const startTime = new Date(now.getTime() + 60 * 60 * 1000); // 1 hour from now
            const endTime = new Date(now.getTime() + 3 * 60 * 60 * 1000); // 3 hours from now
            
            document.getElementById('start-datetime').value = formatDateTimeForInput(startTime);
            document.getElementById('end-datetime').value = formatDateTimeForInput(endTime);
            
            // Generate random PIN
            document.getElementById('pin-code').value = generateRandomPin();
            
            openModal('reservation-modal');
        });
    }
    
    // Cancel button
    if (cancelReservationBtn) {
        cancelReservationBtn.addEventListener('click', function() {
            closeModal('reservation-modal');
        });
    }
    
    // Form submission
    if (reservationForm) {
        reservationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const reservationId = document.getElementById('reservation-id').value;
            const reservationData = {
                User_ID: document.getElementById('user-select').value,
                Printer_ID: document.getElementById('printer-select').value,
                Pr_Start: document.getElementById('start-datetime').value,
                Pr_End: document.getElementById('end-datetime').value,
                Pin: document.getElementById('pin-code').value,
                Filament_Kleur: document.getElementById('filament-color').value,
                Filament_Type: document.getElementById('filament-type').value,
                Comment: document.getElementById('comment').value
            };
            
            if (reservationId) {
                // Update existing reservation
                updateReservation(reservationId, reservationData);
            } else {
                // Create new reservation
                createReservation(reservationData);
            }
        });
    }
}

function createReservation(reservationData) {
    fetch('reservatie_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(reservationData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 201) {
            showNotification('Reservering succesvol toegevoegd', 'success');
            closeModal('reservation-modal');
            loadAllReservations();
            loadRecentReservations();
            loadAllPrinters(); // Reload printers to reflect status changes
            loadDashboardStats();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error creating reservation:', error);
        showNotification('Er is een fout opgetreden', 'error');
    });
}

function editReservation(reservationId) {
    fetch(`reservatie_api.php?id=${reservationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 200) {
                const reservation = data.data;
                
                document.getElementById('reservation-modal-title').textContent = 'Reservering Bewerken';
                document.getElementById('reservation-id').value = reservation.Reservatie_ID;
                document.getElementById('user-select').value = reservation.User_ID;
                document.getElementById('printer-select').value = reservation.Printer_ID;
                document.getElementById('start-datetime').value = formatDateTimeForInput(new Date(reservation.Pr_Start));
                document.getElementById('end-datetime').value = formatDateTimeForInput(new Date(reservation.Pr_End));
                document.getElementById('pin-code').value = reservation.Pin;
                document.getElementById('filament-color').value = reservation.Filament_Kleur || '';
                document.getElementById('filament-type').value = reservation.Filament_Type || '';
                document.getElementById('comment').value = reservation.Comment || '';
                
                openModal('reservation-modal');
            } else {
                showNotification('Reservering niet gevonden', 'error');
            }
        })
        .catch(error => {
            console.error('Error fetching reservation details:', error);
            showNotification('Er is een fout opgetreden', 'error');
        });
}

function updateReservation(reservationId, reservationData) {
    fetch(`reservatie_api.php?id=${reservationId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(reservationData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 200) {
            showNotification('Reservering succesvol bijgewerkt', 'success');
            closeModal('reservation-modal');
            loadAllReservations();
            loadRecentReservations();
            loadAllPrinters(); // Reload printers to reflect status changes
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error updating reservation:', error);
        showNotification('Er is een fout opgetreden', 'error');
    });
}

function confirmDeleteReservation(reservationId) {
    document.getElementById('confirmation-message').textContent = `Weet je zeker dat je deze reservering wilt verwijderen?`;
    
    const confirmDeleteBtn = document.getElementById('confirm-delete');
    confirmDeleteBtn.onclick = function() {
        deleteReservation(reservationId);
        closeModal('confirmation-modal');
    };
    
    openModal('confirmation-modal');
}

function deleteReservation(reservationId) {
    fetch(`reservatie_api.php?id=${reservationId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 200) {
            showNotification('Reservering succesvol verwijderd', 'success');
            loadAllReservations();
            loadRecentReservations();
            loadAllPrinters(); // Reload printers to reflect status changes
            loadDashboardStats();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error deleting reservation:', error);
        showNotification('Er is een fout opgetreden', 'error');
    });
}

// ============== Utility Functions ==============
function setupModals() {
    // Get all close buttons and add event listeners
    document.querySelectorAll('.close').forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) modal.style.display = 'none';
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
    
    // Cancel delete button
    const cancelDeleteBtn = document.getElementById('cancel-delete');
    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', function() {
            closeModal('confirmation-modal');
        });
    }
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'block';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('nl-NL');
}

function formatDateTime(dateTimeStr) {
    if (!dateTimeStr) return '-';
    const date = new Date(dateTimeStr);
    return date.toLocaleDateString('nl-NL') + ' ' + date.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
}

function formatDateTimeForInput(date) {
    return date.toISOString().slice(0, 16);
}

function generateRandomPin() {
    // Generate 8 random digits
    let pin = '';
    for (let i = 0; i < 8; i++) {
        pin += Math.floor(Math.random() * 10);
    }
    return pin;
}

function showNotification(message, type = 'info') {
    // Create notification element if it doesn't exist
    let notification = document.querySelector('.notification');
    
    if (!notification) {
        notification = document.createElement('div');
        notification.className = 'notification';
        document.body.appendChild(notification);
    }
    
    // Set message and type
    notification.textContent = message;
    notification.className = `notification notification-${type}`;
    
    // Show notification
    notification.style.display = 'block';
    
    // Hide after 3 seconds
    setTimeout(() => {
        notification.style.display = 'none';
    }, 3000);
}