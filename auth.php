<?php
// Start with output buffering and session management
if (ob_get_level() > 0) {
    ob_end_clean();
}
session_start();

// Load required files
require_once 'config.php';
require_once 'functions.php';

// Initialize variables
$page_title = "Login / Register - Medicare";
$error = '';
$success = '';
$base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];

// Debugging setup
error_log("=== AUTHENTICATION PROCESS STARTED ===");
define('DEBUG_MODE', true);  // Set to false in production

// Process login form submission
if (isset($_POST['login'])) {
    // Sanitize and validate inputs
    $email = sanitizeInput($_POST['email']);
    $password = sanitizeInput($_POST['password']);

    if (DEBUG_MODE) {
        error_log("Login attempt for email: " . $email);
    }

    // Authenticate user
    $user = authenticateUser($email, $password);

    if ($user) {
        // Check account status
        if ($user['status'] !== 'active') {
            handleError("Account not active", "login.php?error=inactive");
        }

        // Handle doctor-specific checks
        $doctor_data = [];
        if ($user['role'] === 'doctor') {
            $doctor_data = handleDoctorAuthentication($user['id']);
            if ($doctor_data === false) {
                handleError("Doctor profile missing", "login.php?error=doctor_missing");
            }

            if ($doctor_data['status'] !== 'approved') {
                $_SESSION['pending_doctor'] = true;
                redirect("medicare/doctor/pending_approval.php");
            }
        }

        // Set up user session
        setupUserSession($user, $doctor_data);

        // Determine redirect URL based on role
        $redirect_url = determineRedirectUrl($user['role']);

        // Final redirect
        redirect($redirect_url);
    } else {
        handleError("Invalid credentials", "login.php?error=invalid_credentials");
    }
}

// Process registration form submission
if (isset($_POST['register'])) {
    $registration_result = handlePatientRegistration($_POST);

    if ($registration_result['success']) {
        $success = "Registration successful! Please login.";
        if (DEBUG_MODE) {
            error_log("New patient registered: " . $_POST['email']);
        }
    } else {
        $error = $registration_result['message'];
        if (DEBUG_MODE) {
            error_log("Registration failed: " . $error);
        }
    }
}

// Display the authentication form
displayAuthForm($error, $success);

// ===== HELPER FUNCTIONS ===== //

function handleDoctorAuthentication($user_id)
{
    $doctor_data = getDoctorData($user_id);

    if (!$doctor_data) {
        if (DEBUG_MODE) {
            error_log("Doctor profile missing for user ID: $user_id");
        }
        return false;
    }

    if (DEBUG_MODE) {
        error_log("Doctor status: " . $doctor_data['status']);
    }

    return $doctor_data;
}

function setupUserSession($user, $doctor_data = [])
{
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'role' => $user['role'],
        'status' => $user['status'],
        'is_approved_doctor' => (!empty($doctor_data) && $doctor_data['status'] === 'approved'),
        'doctor_id' => $doctor_data['id'] ?? null,
        'specialty_id' => $doctor_data['specialty_id'] ?? null,
        'doctor_status' => $doctor_data['status'] ?? null,
        'last_login' => time()
    ];

    // Set individual session variables for compatibility
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['email'] = $user['email'];

    session_regenerate_id(true);

    if (DEBUG_MODE) {
        error_log("Session data: " . print_r($_SESSION['user'], true));
    }
}

function determineRedirectUrl($role)
{
    global $base_url;

    $redirectMap = [
        'admin' => $base_url . '/medicare/admin/dashboard.php',
        'doctor' => $base_url . '/medicare/doctor/dashboard.php',
        'patient' => $base_url . '/medicare/index.php'
    ];

    if (!array_key_exists($role, $redirectMap)) {
        handleError("Invalid user role", "login.php?error=invalid_role");
    }

    $redirect_url = $redirectMap[$role];

    // Handle patient-specific redirect parameter
    if ($role === 'patient' && !empty($_GET['redirect']) && is_string($_GET['redirect'])) {
        $requested = trim($_GET['redirect']);
        if (strpos($requested, '..') === false && strpos($requested, ':') === false) {
            $redirect_url = $base_url . '/' . ltrim($requested, '/');
        }
    }

    return $redirect_url;
}

function handlePatientRegistration($post_data)
{
    $errors = validateRegistration($post_data);

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode("<br>", $errors)];
    }

    $registration_result = registerPatient(
        $post_data['name'],
        $post_data['email'],
        $post_data['password'],
        $post_data['phone']
    );

    if ($registration_result) {
        sendWelcomeEmail($post_data['email'], $post_data['name']);
        return ['success' => true];
    }

    return ['success' => false, 'message' => "Registration failed. Please try again."];
}

function validateRegistration($data)
{
    $errors = [];

    // Required fields
    if (empty($data['name']))
        $errors[] = "Name is required";
    if (empty($data['email']))
        $errors[] = "Email is required";
    if (empty($data['password']))
        $errors[] = "Password is required";
    if (empty($data['confirm_password']))
        $errors[] = "Confirm password is required";
    if (empty($data['phone']))
        $errors[] = "Phone number is required";

    // Email validation
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // Password validation
    if ($data['password'] !== $data['confirm_password']) {
        $errors[] = "Passwords do not match";
    }
    if (strlen($data['password']) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    if (!preg_match('/[A-Z]/', $data['password'])) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if (!preg_match('/[0-9]/', $data['password'])) {
        $errors[] = "Password must contain at least one number";
    }

    // Check if email exists
    if (emailExists($data['email'])) {
        $errors[] = "Email already registered";
    }

    // Phone number validation
    if (!validatePhoneNumber($data['phone'])) {
        $errors[] = "Invalid phone number format";
    }

    return $errors;
}

function redirect($url)
{
    if (DEBUG_MODE) {
        error_log("Redirecting to: $url");
    }

    // Clear output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Check if headers already sent
    if (headers_sent($filename, $linenum)) {
        error_log("Headers already sent in $filename on line $linenum");
        die("Redirect failed. Output started in $filename on line $linenum");
    }

    header("Location: $url");
    exit();
}

function handleError($log_message, $redirect_url)
{
    if (DEBUG_MODE) {
        error_log("Error: $log_message");
    }
    redirect($redirect_url);
}

function displayAuthForm($error, $success)
{
    include 'header.php';
    ?>
    <section class="auth py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-sm">
                        <div class="card-body p-0">
                            <div class="row g-0">
                                <!-- Login Section -->
                                <div class="col-lg-6 p-4">
                                    <h2 class="text-center mb-4">Sign In</h2>

                                    <?php if ($error && isset($_POST['login'])): ?>
                                        <div class="alert alert-danger"><?php echo $error; ?></div>
                                    <?php endif; ?>

                                    <form method="POST" action="auth.php" id="loginForm">
                                        <input type="hidden" name="login" value="1">
                                        <div class="mb-3">
                                            <label for="loginEmail" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="loginEmail" name="email" required
                                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                        </div>
                                        <div class="mb-3 position-relative">
                                            <label for="loginPassword" class="form-label">Password</label>
                                            <input type="password" class="form-control" id="loginPassword" name="password"
                                                required>
                                            <i class="fas fa-eye-slash toggle-password"
                                                onclick="togglePassword('loginPassword')"></i>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="rememberMe"
                                                    name="remember">
                                                <label class="form-check-label" for="rememberMe">Remember me</label>
                                            </div>
                                            <a href="forgot-password.php" class="text-primary">Forgot password?</a>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100 py-2">
                                            <i class="fas fa-sign-in-alt me-2"></i> Sign In
                                        </button>
                                        <div class="text-center mt-3">
                                            <a href="register_doctor.php" class="text-primary">Are you a doctor? Register
                                                here</a>
                                        </div>
                                    </form>
                                </div>

                                <!-- Register Section -->
                                <div class="col-lg-6 bg-light p-4">
                                    <h2 class="text-center mb-4">Create Patient Account</h2>

                                    <?php if ($success): ?>
                                        <div class="alert alert-success"><?php echo $success; ?></div>
                                    <?php elseif ($error && isset($_POST['register'])): ?>
                                        <div class="alert alert-danger"><?php echo $error; ?></div>
                                    <?php endif; ?>

                                    <form method="POST" action="auth.php" id="registerForm">
                                        <input type="hidden" name="register" value="1">
                                        <div class="mb-3">
                                            <label for="regName" class="form-label">Full Name</label>
                                            <input type="text" class="form-control" id="regName" name="name" required
                                                value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="regEmail" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="regEmail" name="email" required
                                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="regPhone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="regPhone" name="phone" required
                                                value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                            <small class="text-muted">Format: 0712345678</small>
                                        </div>
                                        <div class="mb-3 position-relative">
                                            <label for="regPassword" class="form-label">Password</label>
                                            <input type="password" class="form-control" id="regPassword" name="password"
                                                required>
                                            <i class="fas fa-eye-slash toggle-password"
                                                onclick="togglePassword('regPassword')"></i>
                                            <div class="password-strength mt-1">
                                                <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                                </div>
                                                <small class="text-muted">Password strength: <span
                                                        id="strengthText">Weak</span></small>
                                            </div>
                                        </div>
                                        <div class="mb-3 position-relative">
                                            <label for="regConfirmPassword" class="form-label">Confirm Password</label>
                                            <input type="password" class="form-control" id="regConfirmPassword"
                                                name="confirm_password" required>
                                            <i class="fas fa-eye-slash toggle-password"
                                                onclick="togglePassword('regConfirmPassword')"></i>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                                            <label class="form-check-label" for="agreeTerms">
                                                I agree to the <a href="terms.php" class="text-primary">Terms of Service</a>
                                                and
                                                <a href="privacy.php" class="text-primary">Privacy Policy</a>
                                            </label>
                                        </div>
                                        <button type="submit" class="btn btn-outline-primary w-100 py-2">
                                            <i class="fas fa-user-plus me-2"></i> Register
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Toggle password visibility
        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            }
        }

        // Password strength indicator
        document.getElementById('regPassword').addEventListener('input', function () {
            const strength = calculatePasswordStrength(this.value);
            const progress = document.querySelector('.progress-bar');
            const text = document.getElementById('strengthText');

            progress.style.width = strength.percentage + '%';
            progress.className = 'progress-bar bg-' + strength.color;
            text.textContent = strength.text;
        });

        function calculatePasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            const levels = [
                { color: 'danger', text: 'Very Weak', percentage: 20 },
                { color: 'warning', text: 'Weak', percentage: 40 },
                { color: 'info', text: 'Moderate', percentage: 60 },
                { color: 'primary', text: 'Strong', percentage: 80 },
                { color: 'success', text: 'Very Strong', percentage: 100 }
            ];

            return levels[Math.min(strength, levels.length - 1)];
        }
    </script>
    <?php
    include 'footer.php';
}
?>