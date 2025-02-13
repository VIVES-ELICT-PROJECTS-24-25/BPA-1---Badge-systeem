document.getElementById("loginForm").addEventListener("submit", function(event) {
    event.preventDefault();
    
    const username = document.getElementById("username").value;
    const password = document.getElementById("password").value;

    if (username === "admin" && password === "1234") {
        alert("Inloggen succesvol!");
        window.location.href = "reservatie.html";
    } else {
        alert("Ongeldige gebruikersnaam of wachtwoord.");
    }
});
