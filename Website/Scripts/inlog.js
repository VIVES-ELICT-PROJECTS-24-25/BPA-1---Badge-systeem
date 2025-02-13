// inlog.js
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const errorDiv = document.createElement('div');
    errorDiv.className = 'login-error';
    loginForm.appendChild(errorDiv);

    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        
        if (login(username, password)) {
            const intended = localStorage.getItem('intendedUrl') || 'mijnKalender.html';
            localStorage.removeItem('intendedUrl');
            window.location.href = intended;
        } else {
            errorDiv.textContent = 'Ongeldige gebruikersnaam of wachtwoord. Hint: student/vives';
            document.getElementById('password').value = '';
        }
    });
});