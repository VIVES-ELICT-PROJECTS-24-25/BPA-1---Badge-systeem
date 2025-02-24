// script.js
// Bijhouden van laatste gelezen RFID waarde om duplicaten te vermijden
let lastRfidTimestamp = '';
let scanInProgress = false;

// Formulier inzending afhandelen
document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const statusElement = document.getElementById('status');
    statusElement.innerHTML = 'Status: Bezig met opslaan...';
    
    // Formuliergegevens verzamelen
    const formData = new FormData();
    formData.append('rfidValue', document.getElementById('rfidValue').value);
    formData.append('voornaam', document.getElementById('voornaam').value);
    formData.append('naam', document.getElementById('naam').value);
    formData.append('rnummer', document.getElementById('rnummer').value);
    formData.append('email', document.getElementById('email').value);
    
    // Verstuur naar PHP backend
    fetch('save.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusElement.innerHTML = 'Status: ' + data.message;
            // Optioneel: formulier resetten
            document.getElementById('userForm').reset();
            document.getElementById('rfidValue').value = '';
        } else {
            statusElement.innerHTML = 'Status: Fout: ' + data.message;
        }
    })
    .catch(error => {
        console.error('Fout bij opslaan:', error);
        statusElement.innerHTML = 'Status: Er is een fout opgetreden bij het opslaan.';
    });
});

function scanRFID() {
    if (scanInProgress) return;
    
    scanInProgress = true;
    const statusElement = document.getElementById('status');
    statusElement.innerHTML = 'Status: Bezig met scannen...';
    
    // Referentie naar de RFID node in Firebase
    const rfidRef = database.ref('rfid_latest_scan');
    
    // Eenmalig de huidige waarde ophalen
    rfidRef.once('value')
        .then((snapshot) => {
            const data = snapshot.val();
            
            if (data && data.timestamp !== lastRfidTimestamp) {
                // Nieuwe RFID waarde gevonden
                lastRfidTimestamp = data.timestamp;
                
                // Plaats de waarde in het tekstveld
                document.getElementById('rfidValue').value = data.id || '';
                
                statusElement.innerHTML = 'Status: Nieuwe scan gedetecteerd!';
            } else {
                statusElement.innerHTML = 'Status: Geen nieuwe scan gevonden. Probeer opnieuw.';
            }
            
            scanInProgress = false;
        })
        .catch((error) => {
            console.error('Fout bij scannen:', error);
            statusElement.innerHTML = 'Status: Fout bij scannen. Zie console voor details.';
            scanInProgress = false;
        });
}

// Wacht op een nieuw RFID evenement (niet de bestaande data)
function setupRfidListener() {
    const rfidRef = database.ref('rfid_latest_scan');
    
    // Eenmalig de huidige timestamp opslaan om alleen nieuwe scans te detecteren
    rfidRef.once('value', (snapshot) => {
        const data = snapshot.val();
        if (data && data.timestamp) {
            lastRfidTimestamp = data.timestamp;
            console.log('Initiële RFID timestamp geregistreerd:', lastRfidTimestamp);
        }
    });
}

// Bij het laden van de pagina
document.addEventListener('DOMContentLoaded', setupRfidListener);