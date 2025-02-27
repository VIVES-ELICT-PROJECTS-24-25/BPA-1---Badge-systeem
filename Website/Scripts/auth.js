document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('loginForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        
        const formData = new FormData();
        formData.append('username', username);
        formData.append('password', password);

        try {
            const response = await fetch('checkLogin.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                localStorage.setItem('isLoggedIn', 'true');
                window.location.href = localStorage.getItem('intendedUrl') || 'mijnKalender.php';
            } else {
                alert('Ongeldige inloggegevens');
            }
        } catch (error) {
            console.error('Fout bij inloggen:', error);
            alert('Er is een fout opgetreden. Probeer opnieuw.');
        }
    });
});
