<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

//session_start();

$page_title = "Doctor Registration - Medicare";
$error = '';
$success = '';

/**
 * Logs actions to the admin logs table
 */
/*function logAdminAction($admin_id, $action) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action) VALUES (?, ?)");
        $stmt->bind_param("is", $admin_id, $action);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
        return false;
    }
}*

/**
 * Handles file upload with validation
 */
function handleFileUpload($file) {
    $result = ['error' => null, 'filename' => ''];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = "File upload error. Please try again.";
        return $result;
    }

    $upload_dir = __DIR__ . '/../uploads/doctor_docs/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $result['error'] = "System error. Please contact support.";
            return $result;
        }
    }
    
    // Check directory is writable
    if (!is_writable($upload_dir)) {
        $result['error'] = "System error. Please contact support.";
        return $result;
    }

    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
    
    // Validate file extension
    if (!in_array($file_ext, $allowed_ext)) {
        $result['error'] = "Only PDF, JPG, JPEG, PNG files are allowed";
        return $result;
    }

    // Get MIME type for additional validation
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $file_mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'application/pdf',
        'image/jpeg',
        'image/png'
    ];
    
    if (!in_array($file_mime, $allowed_mimes)) {
        $result['error'] = "Invalid file type detected";
        return $result;
    }

    // Generate unique filename
    $filename = "docs_" . time() . "_" . bin2hex(random_bytes(4)) . ".$file_ext";
    $target_file = $upload_dir . $filename;
    
    // Check file size (max 5MB)
    if ($file['size'] > 5000000) {
        $result['error'] = "File is too large (max 5MB allowed)";
        return $result;
    }

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $result['filename'] = $filename;
    } else {
        $result['error'] = "Failed to save uploaded file. Please try again.";
    }
    
    return $result;
}

/**
 * Sends registration confirmation email
 */
function sendDoctorConfirmationEmail($email, $name) {
    $to = $email;
    $subject = "Your Medicare Doctor Registration";
    $message = "Dear Dr. $name,\n\n";
    $message .= "Thank you for registering with Medicare.\n\n";
    $message .= "Your application is currently under review by our admin team. ";
    $message .= "You will receive another email once your account has been approved.\n\n";
    $message .= "If you have any questions, please contact our support team.\n\n";
    $message .= "Best regards,\nThe Medicare Team";
    
    $headers = "From: no-reply@medicare.com" . "\r\n";
    
    // In production, use a proper mailer library like PHPMailer
    @mail($to, $subject, $message, $headers);
}

// Fetch specialties for dropdown
$specialties = [];
try {
    $result = $conn->query("SELECT id, name FROM specialties ORDER BY name");
    $specialties = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = "Error loading specialties. Please try again later.";
    error_log("Specialty load error: " . $e->getMessage());
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Sanitize inputs
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $password = sanitizeInput($_POST['password']);
    $phone = sanitizeInput($_POST['phone']);
    $license = sanitizeInput($_POST['license']);
    $specialty_id = (int)$_POST['specialty'];
    $qualifications = sanitizeInput($_POST['qualifications']);

    // Validate inputs
    $errors = [];
    if (empty($name)) $errors[] = "Full name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($password)) $errors[] = "Password is required";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (!validatePhoneNumber($phone)) $errors[] = "Invalid phone number format";
    if (empty($license)) $errors[] = "License number is required";
    if (empty($specialty_id)) $errors[] = "Specialty is required";
    if (emailExists($email)) $errors[] = "Email already registered";

    // Handle file upload if no errors
    $docs_path = '';
    if (empty($errors)) {
        if (isset($_FILES['verification_docs']) && $_FILES['verification_docs']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handleFileUpload($_FILES['verification_docs']);
            if ($upload_result['error']) {
                $errors[] = $upload_result['error'];
            } else {
                $docs_path = $upload_result['filename'];
            }
        } else {
            $errors[] = "Verification documents are required";
        }
    }

    // Proceed with registration if no errors
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Create user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, role, status) VALUES (?, ?, ?, ?, 'doctor', 'active')");
            $stmt->bind_param("ssss", $name, $email, $hashed_password, $phone);
            $stmt->execute();
            $user_id = $conn->insert_id;

            // Create doctor profile
            $stmt = $conn->prepare("INSERT INTO doctors (user_id, specialty_id, license_number, qualifications, verification_docs, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("iisss", $user_id, $specialty_id, $license, $qualifications, $docs_path);
            $stmt->execute();

            // Log admin notification (admin_id 0 = system)
            logAdminAction(0, "New doctor registration: $name ($email) - Requires approval");
            
            // Send confirmation email
            sendDoctorConfirmationEmail($email, $name);

            $conn->commit();
            $success = "Your registration has been submitted for admin approval. You will receive an email once your account is approved.";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Registration failed. Please try again. If the problem persists, contact support.";
            error_log("Registration error: " . $e->getMessage());
            
            // Clean up uploaded file if registration failed
            if (!empty($docs_path)) {
                $upload_dir = __DIR__ . '/../uploads/doctor_docs/';
                @unlink($upload_dir . $docs_path);
            }
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

include __DIR__ . '/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">Doctor Registration</h2>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                        <div class="text-center mt-4">
                            <a href="login.php" class="btn btn-primary">Return to Login</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data" id="doctorRegForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name*</label>
                                    <input type="text" class="form-control" name="name" required 
                                        value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Email*</label>
                                    <input type="email" class="form-control" name="email" required
                                        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Password* (min 8 characters)</label>
                                    <input type="password" class="form-control" name="password" required minlength="8"
                                        id="regPassword">
                                    <div class="password-strength mt-2">
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <small class="text-muted">Password strength: <span id="strengthText">Weak</span></small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Phone Number*</label>
                                    <input type="tel" class="form-control" name="phone" required
                                        value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                                    <small class="text-muted">Format: 0712345678</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Medical License Number*</label>
                                    <input type="text" class="form-control" name="license" required
                                        value="<?= isset($_POST['license']) ? htmlspecialchars($_POST['license']) : '' ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Specialty*</label>
                                    <select class="form-select" name="specialty" required>
                                        <option value="">Select Specialty</option>
                                        <?php foreach ($specialties as $s): ?>
                                            <option value="<?= $s['id'] ?>"
                                                <?= (isset($_POST['specialty'])) && $_POST['specialty'] == $s['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($s['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Qualifications/Certifications</label>
                                    <textarea class="form-control" name="qualifications" rows="3"><?= 
                                        isset($_POST['qualifications']) ? htmlspecialchars($_POST['qualifications']) : '' 
                                    ?></textarea>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Verification Documents*</label>
                                    <input type="file" class="form-control" name="verification_docs" required
                                        accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">Upload license copy, ID, or other credentials (PDF or images, max 5MB)</small>
                                </div>

                                <div class="col-12 mt-4">
                                    <button type="submit" name="register" class="btn btn-primary w-100 py-2">
                                        <i class="fas fa-user-md me-2"></i> Submit Registration
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password strength indicator
document.getElementById('regPassword').addEventListener('input', function() {
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

// Form validation
document.getElementById('doctorRegForm').addEventListener('submit', function(e) {
    const password = document.getElementById('regPassword').value;
    if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters');
    }
});
</script>

<?php require_once 'footer.php'; ?>