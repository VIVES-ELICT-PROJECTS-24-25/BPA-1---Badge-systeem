// navigation.js
function updateNavigation() {
    const isLoggedIn = checkAuth();
    const navMenu = document.querySelector('.nav-menu');
    
    if (!navMenu) return;

    const reservatieLink = navMenu.querySelector('a[href="reservatie.html"]')?.parentElement;
    const kalenderLink = navMenu.querySelector('a[href="mijnKalender.html"]')?.parentElement;
    const logoutLink = navMenu.querySelector('a[href="uitlog.html"]')?.parentElement;
    
    if (isLoggedIn) {
        reservatieLink?.classList.remove('disabled');
        kalenderLink?.classList.remove('disabled');
        logoutLink?.style.display = 'list-item';
    } else {
        reservatieLink?.classList.add('disabled');
        kalenderLink?.classList.add('disabled');
        logoutLink?.style.display = 'none';
    }
}

// Update navigation when page loads
document.addEventListener('DOMContentLoaded', updateNavigation);