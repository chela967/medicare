<?php
session_start();
require_once '../config.php';    // Creates $conn
require_once '../functions.php'; // Defines functions

// --- Security Checks ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
    header("Location: ../auth.php"); // Adjust path if needed
    exit();
}

// --- Get Logged-in Doctor's ID ---
$logged_in_user_id = $_SESSION['user']['id'];
$logged_in_doctor_id = getDoctorIdByUserId($logged_in_user_id, $conn);

if (!$logged_in_doctor_id) {
    $_SESSION['error_message'] = "Could not verify doctor identity.";
    header("Location: dashboard.php");
    exit();
}

$error_messages = [];
$success_message = '';

// --- Handle Profile Update (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Nonce/CSRF Check (Recommended but not implemented here) ---

    // --- Retrieve and Sanitize Data (Example) ---
    $name = trim($_POST['name'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = trim($_POST['phone'] ?? ''); // Further phone validation needed
    $specialty_id = filter_var($_POST['specialty_id'] ?? '', FILTER_VALIDATE_INT);
    $license_number = trim($_POST['license_number'] ?? '');
    $qualifications = trim($_POST['qualifications'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $consultation_fee = filter_var($_POST['consultation_fee'] ?? '', FILTER_VALIDATE_FLOAT);
    // Availability might need specific handling (e.g., checkbox value)
    $available = isset($_POST['available']) ? 1 : 0;

    // --- Basic Validation (Example - Add more robust validation) ---
    if (empty($name))
        $error_messages[] = "Name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $error_messages[] = "Valid email is required.";
    if (empty($phone))
        $error_messages[] = "Phone number is required."; // Add pattern validation
    if ($specialty_id === false || $specialty_id <= 0)
        $error_messages[] = "Please select a valid specialty.";
    if (empty($license_number))
        $error_messages[] = "License number is required.";
    if ($consultation_fee === false || $consultation_fee < 0)
        $error_messages[] = "Invalid consultation fee.";

    // --- Password Change Handling (Example - Requires careful implementation) ---
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $password_update_data = null;

    if (!empty($new_password)) {
        if (empty($current_password)) {
            $error_messages[] = "Current password is required to set a new password.";
        } elseif ($new_password !== $confirm_password) {
            $error_messages[] = "New password and confirmation password do not match.";
        } elseif (strlen($new_password) < 8) { // Example minimum length
            $error_messages[] = "New password must be at least 8 characters long.";
        } else {
            // Verify current password against the one stored in the database (needs a function)
            // if (!verifyPassword($logged_in_user_id, $current_password, $conn)) {
            //     $error_messages[] = "Incorrect current password.";
            // } else {
            // Hash the new password
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            $password_update_data = $hashed_new_password; // Pass this to the update function
            // }
            $error_messages[] = "Password update logic needs completion (verification & hashing)."; // Placeholder
        }
    }


    // --- If No Errors, Proceed with Update ---
    if (empty($error_messages)) {
        // Prepare data array for update function
        $update_data = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'specialty_id' => $specialty_id,
            'license_number' => $license_number,
            'qualifications' => $qualifications,
            'bio' => $bio,
            'consultation_fee' => $consultation_fee,
            'available' => $available,
            'new_password_hash' => $password_update_data // Pass hashed password if changed
        ];

        // ** Call the actual update function (needs to be created in functions.php) **
        // if (updateDoctorProfile($logged_in_doctor_id, $logged_in_user_id, $update_data, $conn)) {
        $success_message = "Profile updated successfully!";
        // Store success message in session for display after redirect
        $_SESSION['success_message'] = $success_message;
        // } else {
        // $_SESSION['error_message'] = "Failed to update profile. Please try again.";
        $_SESSION['error_message'] = "Profile update failed (Update function not implemented)."; // Placeholder
        // }

        // Redirect back to profile page to prevent form resubmission
        header("Location: profile.php");
        exit();
    }
    // If errors occurred, the script continues below to display the form with errors and old data.
}

// --- Fetch Current Profile Data (for GET request or if POST had errors) ---
$profile = getDoctorProfile($logged_in_doctor_id, $conn);
$specialties = getAllSpecialties($conn);

// Check if profile fetch failed
if (!$profile) {
    // Handle error - Maybe redirect or show fatal error
    die('<div class="alert alert-danger m-4">Error: Could not load profile data.</div>');
}

// Handle session messages from previous redirects
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    // Prepend session errors to any form validation errors
    $error_messages = array_merge([$_SESSION['error_message']], $error_messages);
    unset($_SESSION['error_message']);
}


// --- Set Page Title ---
$page_title = "My Profile - Medicare";
include '../header.php'; // Include header

?>

<div class="container-fluid py-4">
    <div class="row">
        <?php include '_doctor_sidebar.php'; // Assuming you have extracted sidebar ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div
                class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">My Profile</h1>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_messages)): ?>
                <div class="alert alert-danger" role="alert">
                    <h5 class="alert-heading">Please fix the following errors:</h5>
                    <ul>
                        <?php foreach ($error_messages as $msg): ?>
                            <li><?php echo htmlspecialchars($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>


            <form action="profile.php" method="post" novalidate>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">Personal & Professional Information</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Full Name <span
                                                class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" required
                                            value="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address <span
                                                class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required
                                            value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone Number <span
                                                class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" id="phone" name="phone" required
                                            value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="specialty_id" class="form-label">Specialty <span
                                                class="text-danger">*</span></label>
                                        <select class="form-select" id="specialty_id" name="specialty_id" required>
                                            <option value="">Select Specialty...</option>
                                            <?php foreach ($specialties as $specialty): ?>
                                                <option value="<?php echo $specialty['id']; ?>" <?php echo ($profile['specialty_id'] == $specialty['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($specialty['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="license_number" class="form-label">License Number <span
                                                class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="license_number"
                                            name="license_number" required
                                            value="<?php echo htmlspecialchars($profile['license_number'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="consultation_fee" class="form-label">Consultation Fee (UGX) <span
                                                class="text-danger">*</span></label>
                                        <input type="number" step="0.01" min="0" class="form-control"
                                            id="consultation_fee" name="consultation_fee" required
                                            value="<?php echo htmlspecialchars($profile['consultation_fee'] ?? '0.00'); ?>">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="qualifications" class="form-label">Qualifications</label>
                                        <textarea class="form-control" id="qualifications" name="qualifications"
                                            rows="3"><?php echo htmlspecialchars($profile['qualifications'] ?? ''); ?></textarea>
                                        <small class="text-muted">Enter qualifications, degrees, certifications
                                            etc.</small>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="bio" class="form-label">Bio / About Me</label>
                                        <textarea class="form-control" id="bio" name="bio"
                                            rows="4"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                                        <small class="text-muted">A short description about yourself for
                                            patients.</small>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="available"
                                                name="available" value="1" <?php echo ($profile['available'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="available">Available for new
                                                appointments</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">Change Password</div>
                            <div class="card-body">
                                <p class="text-muted small">Leave fields blank to keep your current password.</p>
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password"
                                        name="current_password">
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password"
                                        name="confirm_password">
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-header">Account Status</div>
                            <div class="card-body">
                                <p>Your account status is:
                                    <span
                                        class="badge bg-<?php echo $profile['doctor_status'] === 'approved' ? 'success' : ($profile['doctor_status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($profile['doctor_status'] ?? 'Unknown'); ?>
                                    </span>
                                </p>
                                <?php if ($profile['doctor_status'] === 'pending'): ?>
                                    <p class="small text-muted">Your account is pending review by an administrator.</p>
                                <?php elseif ($profile['doctor_status'] === 'rejected'): ?>
                                    <p class="small text-muted">Your account application was rejected. Please contact
                                        support for more information.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end mt-3 mb-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i> Save Profile Changes
                    </button>
                </div>
            </form>


        </main>
    </div>
</div>

<?php include '../footer.php'; // Include footer ?>