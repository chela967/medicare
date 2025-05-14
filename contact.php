<?php
// contact.php
// Start output buffering at the VERY TOP if not already done by header or config
// However, header.php provided by you already handles session_start and ob_start implicitly by being included.
// Let's ensure session is started if header.php isn't guaranteed to do it first for this page's logic.
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

require_once __DIR__ . '/config.php'; // For $conn (if saving to DB) and set_flash_message
require_once __DIR__ . '/functions.php'; // For any helper functions (ensure set_flash_message is here or in config)

$page_title = "Contact Us";
$form_values = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
$form_errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $form_errors['csrf'] = 'Invalid request. Please try submitting the form again.';
    } else {
        // 2. Sanitize and Collect Input
        $form_values['name'] = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
        $form_values['email'] = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
        $form_values['subject'] = trim(filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING));
        $form_values['message'] = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING));

        // 3. Validate Input
        if (empty($form_values['name'])) {
            $form_errors['name'] = 'Name is required.';
        }
        if (empty($form_values['email'])) {
            $form_errors['email'] = 'Email is required.';
        } elseif (!filter_var($form_values['email'], FILTER_VALIDATE_EMAIL)) {
            $form_errors['email'] = 'Invalid email format.';
        }
        if (empty($form_values['subject'])) {
            $form_errors['subject'] = 'Subject is required.';
        }
        if (empty($form_values['message'])) {
            $form_errors['message'] = 'Message is required.';
        } elseif (strlen($form_values['message']) < 10) {
            $form_errors['message'] = 'Message should be at least 10 characters long.';
        }

        // 4. If no errors, process (e.g., send email, save to DB)
        if (empty($form_errors)) {
            $to_email = 'admin@medicare.example.com'; // REPLACE WITH YOUR ACTUAL ADMIN/SUPPORT EMAIL
            $email_subject = "New Contact Form Message: " . $form_values['subject'];
            $email_body = "You have received a new message from your website contact form.\n\n";
            $email_body .= "Name: " . htmlspecialchars($form_values['name']) . "\n";
            $email_body .= "Email: " . htmlspecialchars($form_values['email']) . "\n";
            $email_body .= "Subject: " . htmlspecialchars($form_values['subject']) . "\n";
            $email_body .= "Message:\n" . htmlspecialchars($form_values['message']) . "\n";

            $headers = "From: no-reply@medicare.example.com\r\n"; // Replace with your domain
            $headers .= "Reply-To: " . htmlspecialchars($form_values['email']) . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            if (mail($to_email, $email_subject, $email_body, $headers)) {
                if (function_exists('set_flash_message')) {
                    set_flash_message('Thank you for your message! We will get back to you shortly.', 'success');
                } else {
                    $_SESSION['flash_messages'][] = ['message' => 'Thank you for your message! We will get back to you shortly.', 'type' => 'success'];
                }
                // Optionally save to database here
                // Example: saveContactMessage($conn, $form_values['name'], $form_values['email'], $form_values['subject'], $form_values['message']);

                // Clear form values after successful submission
                $form_values = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
                // Regenerate CSRF token to prevent reuse on resubmit
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $form_errors['send'] = 'Sorry, there was an error sending your message. Please try again later.';
                error_log("Contact form mail() function failed. To: $to_email, Subject: $email_subject");
            }
        }
    }
    if (!empty($form_errors) && function_exists('set_flash_message')) {
        // Consolidate form errors into a single flash message or handle them individually in the form
        // For simplicity here, just setting a generic one if any error occurred besides send error.
        if (empty($form_errors['send'])) { // if not already an explicit send error
            set_flash_message('Please correct the errors in the form.', 'danger');
        }
    } elseif (!empty($form_errors)) {
        if (empty($form_errors['send'])) {
            $_SESSION['flash_messages'][] = ['message' => 'Please correct the errors in the form.', 'type' => 'danger'];
        }
    }
}
// Regenerate CSRF token if it's not set (e.g., first page load)
// header.php already does this, but to be safe for this specific form's lifecycle:
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


require_once __DIR__ . '/header.php'; // Include header
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <h1 class="text-center mb-4 display-5 fw-bold"><?= htmlspecialchars($page_title) ?></h1>
            <p class="text-center text-muted mb-5">
                We'd love to hear from you! Whether you have a question about our services, doctors, pharmacy, or
                anything else, our team is ready to answer all your questions.
            </p>

            <?php if (isset($_SESSION['flash_messages'])): ?>
                <?php foreach ($_SESSION['flash_messages'] as $flash_message): ?>
                    <div class="alert alert-<?= htmlspecialchars($flash_message['type']) ?> alert-dismissible fade show"
                        role="alert">
                        <?= htmlspecialchars($flash_message['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endforeach; ?>
                <?php unset($_SESSION['flash_messages']); ?>
            <?php endif; ?>

            <?php if (!empty($form_errors['send'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($form_errors['send']) ?></div>
            <?php endif; ?>


            <div class="row g-lg-5">
                <div class="col-lg-5 mb-5 mb-lg-0">
                    <div class="card shadow-sm h-100">
                        <div class="card-body p-4">
                            <h4 class="card-title mb-4"><i class="fas fa-address-book me-2"></i>Our Details</h4>
                            <div class="d-flex align-items-start mb-3">
                                <i class="fas fa-map-marker-alt fa-fw text-primary mt-1 me-3"></i>
                                <div>
                                    <strong>Address:</strong><br>
                                    123 Medicare Lane<br>
                                    Kampala, Uganda
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-3">
                                <i class="fas fa-phone-alt fa-fw text-primary mt-1 me-3"></i>
                                <div>
                                    <strong>Phone:</strong><br>
                                    +256 7XX XXX XXX<br>
                                    +256 4XX XXX XXX
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-3">
                                <i class="fas fa-envelope fa-fw text-primary mt-1 me-3"></i>
                                <div>
                                    <strong>Email:</strong><br>
                                    <a href="mailto:info@medicare.example.com">info@medicare.example.com</a><br>
                                    <a href="mailto:support@medicare.example.com">support@medicare.example.com</a>
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <i class="fas fa-clock fa-fw text-primary mt-1 me-3"></i>
                                <div>
                                    <strong>Opening Hours:</strong><br>
                                    Mon - Fri: 8:00 AM - 6:00 PM<br>
                                    Sat: 9:00 AM - 1:00 PM<br>
                                    Sun: Closed
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card shadow-sm h-100">
                        <div class="card-body p-4">
                            <h4 class="card-title mb-4"><i class="fas fa-paper-plane me-2"></i>Send Us a Message</h4>
                            <form id="contactForm" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST"
                                novalidate>
                                <input type="hidden" name="csrf_token"
                                    value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                                <div class="mb-3">
                                    <label for="contactName" class="form-label">Full Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text"
                                        class="form-control <?= !empty($form_errors['name']) ? 'is-invalid' : '' ?>"
                                        id="contactName" name="name"
                                        value="<?= htmlspecialchars($form_values['name']) ?>" required>
                                    <?php if (!empty($form_errors['name'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($form_errors['name']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="contactEmail" class="form-label">Email Address <span
                                            class="text-danger">*</span></label>
                                    <input type="email"
                                        class="form-control <?= !empty($form_errors['email']) ? 'is-invalid' : '' ?>"
                                        id="contactEmail" name="email"
                                        value="<?= htmlspecialchars($form_values['email']) ?>" required>
                                    <?php if (!empty($form_errors['email'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($form_errors['email']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="contactSubject" class="form-label">Subject <span
                                            class="text-danger">*</span></label>
                                    <input type="text"
                                        class="form-control <?= !empty($form_errors['subject']) ? 'is-invalid' : '' ?>"
                                        id="contactSubject" name="subject"
                                        value="<?= htmlspecialchars($form_values['subject']) ?>" required>
                                    <?php if (!empty($form_errors['subject'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($form_errors['subject']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="contactMessage" class="form-label">Message <span
                                            class="text-danger">*</span></label>
                                    <textarea
                                        class="form-control <?= !empty($form_errors['message']) ? 'is-invalid' : '' ?>"
                                        id="contactMessage" name="message" rows="5"
                                        required><?= htmlspecialchars($form_values['message']) ?></textarea>
                                    <?php if (!empty($form_errors['message'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($form_errors['message']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-paper-plane me-2"></i> Send Message
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php'; // Include footer
// ob_end_flush(); // Call this if you started ob_start() at the very top of this file
?>