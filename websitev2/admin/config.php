<?php
// Menu items array aanpassen
$menu_items = [
    ['url' => 'index.php', 'name' => 'Dashboard', 'icon' => 'dashboard'],
    ['url' => 'gebruikers.php', 'name' => 'Gebruikers', 'icon' => 'users'],
    ['url' => 'printers.php', 'name' => 'Printers', 'icon' => 'print'],
    ['url' => 'reserveringen.php', 'name' => 'Reserveringen', 'icon' => 'calendar'],
    ['url' => 'filament.php', 'name' => 'Filament', 'icon' => 'box'],
    // Voeg hier het nieuwe Shelly Control menu-item toe
    ['url' => 'shelly-control.php', 'name' => 'Shelly Control', 'icon' => 'plug'],
    ['url' => '../index.php', 'name' => 'Terug naar de site', 'icon' => 'home'],
    ['url' => 'logout.php', 'name' => 'Uitloggen', 'icon' => 'logout']
];
?>