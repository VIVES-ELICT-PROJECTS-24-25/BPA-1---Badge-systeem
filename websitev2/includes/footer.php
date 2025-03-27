    </div>
    
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>3D Printer Reserveringssysteem</h5>
                    <p>Een handige tool om 3D printers te reserveren voor uw projecten.</p>
                </div>
                <div class="col-md-4">
                    <h5>Snelle Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Home</a></li>
                        <li><a href="printers.php" class="text-white">Printers</a></li>
                        <li><a href="calendar.php" class="text-white">Kalender</a></li>
                        <li><a href="contact.php" class="text-white">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact</h5>
                    <address>
                        <p><i class="fas fa-map-marker-alt"></i> Doorniksesteenweg 145, 8500 Kortrijk</p>
                        <p><i class="fas fa-phone"></i> +32 2 123 45 67</p>
                        <p><i class="fas fa-envelope"></i> info@3dprintersmaaklabvives.be</p>
                    </address>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> 3D Printer Reserveringssysteem. Alle rechten voorbehouden.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <script src="assets/js/main.js"></script>
    <?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>