<form id="registrationForm">
    <label for="firstname">Voornaam:</label>
    <input type="text" id="firstname" name="firstname" required>
    
    <label for="lastname">Achternaam:</label>
    <input type="text" id="lastname" name="lastname" required>
    
    <label for="email">E-mailadres:</label>
    <input type="email" id="email" name="email" required>
    
    <label for="studierichting">Studierichting:</label>
    <input type="text" id="studierichting" name="studierichting" required>
    
    <label for="password">Wachtwoord:</label>
    <input type="password" id="password" name="password" required>
    
    <label for="confirmPassword">Bevestig wachtwoord:</label>
    <input type="password" id="confirmPassword" name="confirmPassword" required>
    
    <button type="submit">Registreren</button>
</form>

<script>
document.getElementById('registrationForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    if (password !== confirmPassword) {
        alert('Wachtwoorden komen niet overeen');
        return;
    }

    const formData = new FormData(this);

    try {
        const response = await fetch('Scripts/databaseSave.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) throw new Error(`Fout: ${response.statusText}`);

        const result = await response.text();
        alert(result);
    } catch (error) {
        alert('Registratie mislukt. Probeer opnieuw.');
        console.error(error);
    }
});
</script>
