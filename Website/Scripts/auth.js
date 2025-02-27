// auth.js
const VALID_CREDENTIALS = {
    username: 'student',
    password: 'vives'
};

function checkAuth() {
    return localStorage.getItem('isLoggedIn') === 'true';
}

function login(username, password) {
    if (username === VALID_CREDENTIALS.username && 
        password === VALID_CREDENTIALS.password) {
        localStorage.setItem('isLoggedIn', 'true');
        return true;
    }
    return false;
}

function logout() {
    localStorage.removeItem('isLoggedIn');
    window.location.href = 'index.php';
}

function protectRoute() {
    const protectedPages = ['mijnKalender.php', 'reservatie.php'];
    const currentPage = window.location.pathname.split('/').pop();
    
    if (protectedPages.includes(currentPage) && !checkAuth()) {
        localStorage.setItem('intendedUrl', currentPage);
        window.location.href = 'index.php';
    }
}

// Run route protection on page load
document.addEventListener('DOMContentLoaded', protectRoute);