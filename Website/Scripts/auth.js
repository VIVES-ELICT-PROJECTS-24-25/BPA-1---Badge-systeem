document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');

    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        
        // Debug values
        console.log('Username:', username);
        console.log('Password:', password);
        
        if (!username || !password) {
            alert('Vul a.u.b. zowel een gebruikersnaam als wachtwoord in');
            return;
        }
        
        const formData = new FormData(loginForm);
        
        // Debug what's in the FormData
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }

        try {
            // Try with the correct path - check if databaseLees.php is in the root directory
            // If not, you may need to adjust this path
            const response = await fetch('databaseLees.php', {
                method: 'POST',
                body: formData
            });

            console.log('Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('Response data:', data);
            
            if (data.success) {
                window.location.href = 'mijnKalender.php';
            } else {
                alert(data.message || 'Ongeldige gebruikersnaam of wachtwoord');
            }
        } catch (error) {
            console.error('Fout bij inloggen:', error);
            alert('Er is een fout opgetreden bij het inloggen. Probeer het later opnieuw.');
        }
    });
});