<?php
// footer.php - Revised Version
?>
<footer class="bg-dark text-white py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <h3 class="h4 fw-bold mb-3"><i class="fas fa-hospital me-2"></i>Medicare</h3>
                <p>We value every human life placed in our hands and constantly work towards meeting the expectations of
                    our patients.</p>
                <div class="d-flex gap-3 mt-4">
                    <a href="#" class="text-white"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-white"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <div class="col-md-6 col-lg-2">
                <h4 class="h5 fw-bold mb-3">Quick Links</h4>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="index.php" class="text-white-50 text-decoration-none">Home</a></li>
                    <li class="mb-2"><a href="about.php" class="text-white-50 text-decoration-none">About Us</a></li>
                    <li class="mb-2"><a href="#services" class="text-white-50 text-decoration-none">Services</a></li>
                    <li class="mb-2"><a href="#doctors" class="text-white-50 text-decoration-none">Doctors</a></li>
                    <li class="mb-2"><a href="appointment.php"
                            class="text-white-50 text-decoration-none">Appointment</a></li>
                </ul>
            </div>

            <div class="col-md-6 col-lg-3">
                <h4 class="h5 fw-bold mb-3">Services</h4>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="#services" class="text-white-50 text-decoration-none">Primary
                            Healthcare</a></li>
                    <li class="mb-2"><a href="#services" class="text-white-50 text-decoration-none">Emergency Care</a>
                    </li>
                    <li class="mb-2"><a href="#services" class="text-white-50 text-decoration-none">Cardiology</a></li>
                    <li class="mb-2"><a href="#services" class="text-white-50 text-decoration-none">Dental Care</a></li>
                    <li class="mb-2"><a href="#services" class="text-white-50 text-decoration-none">Neurology</a></li>
                </ul>
            </div>

            <div class="col-lg-3">
                <h4 class="h5 fw-bold mb-3">Newsletter</h4>
                <p>Subscribe to our newsletter for the latest updates.</p>
                <form class="mt-4">
                    <div class="input-group mb-3">
                        <input type="email" class="form-control" placeholder="Your Email">
                        <button class="btn btn-primary" type="submit">Subscribe</button>
                    </div>
                </form>
            </div>
        </div>

        <hr class="my-4 bg-secondary">

        <div class="row">
            <div class="col-md-6 text-center text-md-start">
                <?php
                $current_year = date("Y"); // Get the current year
                ?>
                <p class="mb-0">&copy; <?php echo $current_year; ?> Medicare. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <ul class="list-inline mb-0">
                    <li class="list-inline-item"><a href="#" class="text-white-50 text-decoration-none">Privacy
                            Policy</a></li>
                    <li class="list-inline-item"><a href="#" class="text-white-50 text-decoration-none">Terms of
                            Service</a></li>
                    <li class="list-inline-item"><a href="#contact" class="text-white-50 text-decoration-none">Contact
                            Us</a></li>
                </ul>
            </div>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php
// Define the path relative to the web root
$jsFilePath = $_SERVER['DOCUMENT_ROOT'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/js/script.js';
$jsSrcPath = dirname($_SERVER['PHP_SELF']) . '/js/script.js';
$jsSrcPath = str_replace('\\', '/', $jsSrcPath); // Ensure forward slashes
$jsSrcPath = rtrim($jsSrcPath, '/') . '/script.js'; // Add filename
$jsSrcPath = preg_replace('#/+#', '/', $jsSrcPath); // Remove double slashes


// Check if the file exists before trying to include it
// Note: Adjust path if needed based on your actual project structure
$actualFilePathOnDisk = __DIR__ . '/js/script.js'; // Assumes js folder is sibling to footer.php

if (file_exists($actualFilePathOnDisk)) {
    // Determine correct web path relative to document root
    $webPathToScript = str_replace($_SERVER['DOCUMENT_ROOT'], '', $actualFilePathOnDisk);
    $webPathToScript = str_replace('\\', '/', $webPathToScript); // Use forward slashes for web
    echo '<script src="' . htmlspecialchars($webPathToScript) . '"></script>';
} else {
    // Optional: Log notice if file is missing
    // error_log("Notice: Custom script file not found at: " . $actualFilePathOnDisk);
    // You still need to manually create the js folder and empty script.js file
}
?>
<script>
    // Make notification clicks more responsive
    document.querySelectorAll('.list-group-item-action').forEach(item => {
        item.addEventListener('click', function (e) {
            // Add visual feedback
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 200);

            // For unread notifications, you could mark as read via AJAX
            if (this.classList.contains('bg-light')) {
                const notifId = this.dataset.id; // You'd need to add data-id attribute
                // AJAX call to mark as read could go here
            }
        });
    });
</script>
</body>

</html>