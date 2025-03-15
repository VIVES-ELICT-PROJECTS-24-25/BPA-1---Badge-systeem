// navigation.js
function updateNavigation() {
    const isLoggedIn = checkAuth();
    const navMenu = document.querySelector('.nav-menu');
    
    if (!navMenu) return;

    const reservatieLink = navMenu.querySelector('a[href="reservatie.php"]')?.parentElement;
    const kalenderLink = navMenu.querySelector('a[href="mijnKalender.php"]')?.parentElement;
    const logoutLink = navMenu.querySelector('a[href="uitlog.php"]')?.parentElement;
    
    if (isLoggedIn) {
        if (reservatieLink) reservatieLink.classList.remove('disabled');
        if (kalenderLink) kalenderLink.classList.remove('disabled');
        if (logoutLink) logoutLink.style.display = 'list-item';
    } else {
        if (reservatieLink) reservatieLink.classList.add('disabled');
        if (kalenderLink) kalenderLink.classList.add('disabled');
        if (logoutLink) logoutLink.style.display = 'none';
    }
}

// Update navigation when page loads
document.addEventListener('DOMContentLoaded', updateNavigation);