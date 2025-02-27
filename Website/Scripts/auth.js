document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');

    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(loginForm); // Zorg ervoor dat 'formData' correct is gedefinieerd

        try {
            const response = await fetch('Scripts/databaseLees.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                window.location.href = 'mijnKalender.php';
            } else {
                alert('Ongeldige gebruikersnaam of wachtwoord');
            }
        } catch (error) {
            console.error('Fout bij inloggen:', error);
        }
    });
});
