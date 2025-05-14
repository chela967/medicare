<?php
session_start();
// Assuming config.php and functions.php are one level up
require_once __DIR__ . '/../config.php'; // Provides $conn
require_once __DIR__ . '/../functions.php'; // Provides getDoctorIdByUserId

// --- Authentication & Authorization ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Access denied. Please log in as a doctor.'];
    header("Location: ../auth.php"); // Adjust path if needed
    exit();
}

$doctor_user_id = $_SESSION['user']['id'];

// --- Get Doctor's internal ID from doctors table ---
// Ensure getDoctorIdByUserId correctly uses $conn.
$doctor_profile_id = getDoctorIdByUserId($doctor_user_id, $conn);
if (!$doctor_profile_id) {
    error_log("Doctor Appt Details Error: Could not retrieve doctor profile ID for user ID: " . $doctor_user_id);
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Could not load your doctor profile.'];
    header("Location: dashboard.php"); // Redirect to doctor dashboard
    exit();
}

// --- Validate Appointment ID from GET parameter ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Invalid appointment ID specified.'];
    header("Location: appointments.php"); // Redirect to doctor's appointment list
    exit;
}
$appointment_id = (int) $_GET['id'];

// --- Initialize Variables ---
$appointment = null;
$page_error = null;
$success_message = null;
$error_message = null;

// --- Check DB Connection ---
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    $page_error = "Database connection error.";
    error_log("Doctor Appt Details Error: \$conn object not available.");
} else {

    // --- Handle POST Actions (Update Status, Add Notes) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action_appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);

        // Ensure action is for the correct appointment ID
        if ($action_appointment_id === $appointment_id) {

            // --- Update Status ---
            if (isset($_POST['update_status']) && isset($_POST['new_status'])) {
                $new_status = $_POST['new_status'];
                $allowed_statuses = ['scheduled', 'completed', 'cancelled', 'no_show', 'confirmed']; // Add 'confirmed' etc. if used
                if (in_array($new_status, $allowed_statuses)) {
                    try {
                        $sql_update = "UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?";
                        $stmt_update = $conn->prepare($sql_update);
                        if (!$stmt_update)
                            throw new mysqli_sql_exception("Prepare failed (update status): " . $conn->error);
                        $stmt_update->bind_param("sii", $new_status, $appointment_id, $doctor_profile_id);
                        if ($stmt_update->execute()) {
                            $success_message = "Appointment status updated to '" . htmlspecialchars($new_status) . "'.";
                            // --- Notify Patient of Status Change ---
                            // Fetch patient ID first (needed if not already fetched)
                            $stmt_get_pat = $conn->prepare("SELECT patient_id FROM appointments WHERE id = ?");
                            $patient_id_to_notify = null;
                            if ($stmt_get_pat) {
                                $stmt_get_pat->bind_param("i", $appointment_id);
                                $stmt_get_pat->execute();
                                $res_pat = $stmt_get_pat->get_result();
                                if ($row_pat = $res_pat->fetch_assoc()) {
                                    $patient_id_to_notify = $row_pat['patient_id'];
                                }
                                $stmt_get_pat->close();
                            }

                            if ($patient_id_to_notify) {
                                $notification_title = "Appointment Status Update";
                                $notification_message = "The status of your appointment (#{$appointment_id}) on " . date('M j') . " with Dr. " . $_SESSION['user']['name'] . " has been updated to: " . ucfirst($new_status);
                                $notification_link = "appointment_details.php?id=" . $appointment_id; // Link for patient
                                $notification_type = 'appointment';

                                $link_column_exists = false; // Check if link column exists
                                $result_check = $conn->query("SHOW COLUMNS FROM `notifications` LIKE 'link'");
                                if ($result_check && $result_check->num_rows > 0) {
                                    $link_column_exists = true;
                                }
                                if ($result_check)
                                    $result_check->free();

                                if ($link_column_exists) {
                                    $sql_notify = "INSERT INTO notifications (user_id, title, message, type, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
                                    $stmt_notify = $conn->prepare($sql_notify);
                                    if ($stmt_notify)
                                        $stmt_notify->bind_param("issss", $patient_id_to_notify, $notification_title, $notification_message, $notification_type, $notification_link);
                                } else {
                                    $sql_notify = "INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())";
                                    $stmt_notify = $conn->prepare($sql_notify);
                                    if ($stmt_notify)
                                        $stmt_notify->bind_param("isss", $patient_id_to_notify, $notification_title, $notification_message, $notification_type);
                                }
                                if ($stmt_notify && !$stmt_notify->execute()) {
                                    error_log("Failed to create patient notification: " . $stmt_notify->error);
                                }
                                if ($stmt_notify)
                                    $stmt_notify->close();
                            }
                            // --- End Notify Patient ---
                        } else {
                            throw new mysqli_sql_exception("Execute failed (update status): " . $stmt_update->error);
                        }
                        $stmt_update->close();
                    } catch (mysqli_sql_exception $e) {
                        error_log("Doctor Update Status Error: " . $e->getMessage());
                        $error_message = "Database error updating status.";
                    }
                } else {
                    $error_message = "Invalid status selected.";
                }
            }

            // --- Add/Update Consultation Notes ---
            elseif (isset($_POST['save_notes'])) {
                $consultation_notes = trim($_POST['consultation_notes'] ?? '');
                $notify_patient_flag = isset($_POST['notify_patient']) ? 1 : 0; // Check if checkbox was checked

                try {
                    $sql_notes = "UPDATE appointments SET consultation_notes = ?, notify_patient = ? WHERE id = ? AND doctor_id = ?";
                    $stmt_notes = $conn->prepare($sql_notes);
                    if (!$stmt_notes)
                        throw new mysqli_sql_exception("Prepare failed (save notes): " . $conn->error);
                    $stmt_notes->bind_param("siii", $consultation_notes, $notify_patient_flag, $appointment_id, $doctor_profile_id);
                    if ($stmt_notes->execute()) {
                        $success_message = "Consultation notes saved successfully.";
                        if ($notify_patient_flag) {
                            $success_message .= " Patient will be notified.";
                            // Optional: Could also insert a specific notification here if desired,
                            // but the flag itself is the primary mechanism in the dashboard example.
                        }
                    } else {
                        throw new mysqli_sql_exception("Execute failed (save notes): " . $stmt_notes->error);
                    }
                    $stmt_notes->close();
                } catch (mysqli_sql_exception $e) {
                    error_log("Doctor Save Notes Error: " . $e->getMessage());
                    $error_message = "Database error saving notes.";
                }
            }
        } else {
            $error_message = "Action mismatch. Please try again.";
        }
    } // End POST handling

    // --- Fetch Appointment Details (Fetch AFTER potential updates) ---
    try {
        $sql = "SELECT a.*,
                       pat_user.name as patient_name,
                       pat_user.email as patient_email,
                       pat_user.phone as patient_phone,
                       p.dob as patient_dob, p.gender as patient_gender, p.address as patient_address,
                       p.blood_group as patient_blood_group, p.allergies as patient_allergies,
                       p.medical_history as patient_medical_history
                FROM appointments a
                JOIN users pat_user ON a.patient_id = pat_user.id
                LEFT JOIN patients p ON pat_user.id = p.user_id -- Left join in case patient details aren't filled
                WHERE a.id = ? AND a.doctor_id = ?"; // Verify doctor owns this appointment

        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new mysqli_sql_exception("Prepare failed (fetch details): " . $conn->error);

        $stmt->bind_param("ii", $appointment_id, $doctor_profile_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc(); // Fetch the potentially updated data
        $stmt->close();

        if (!$appointment) {
            // If still not found after potential POST, redirect
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Appointment not found or access denied.'];
            header("Location: appointments.php");
            exit;
        }

    } catch (mysqli_sql_exception $e) {
        error_log("Doctor Appt Details Fetch Error: " . $e->getMessage());
        $page_error = "Error loading appointment details.";
        $appointment = null; // Clear data on error
    }
} // End DB connection check

$page_title = $appointment ? "Appointment: " . htmlspecialchars($appointment['patient_name']) . " (" . date('M j, Y', strtotime($appointment['appointment_date'])) . ")" : "Appointment Not Found";
// Assuming header.php is one level up
require_once __DIR__ . '/../header.php';
?>

<div class="container py-4">

    <?php if ($page_error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($page_error) ?></div> <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success_message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"
                aria-label="Close"></button></div> <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error_message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"
                aria-label="Close"></button></div> <?php endif; ?>
    <?php // Flash messages from redirects
    if (isset($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])) {
        $flash_type = htmlspecialchars($_SESSION['flash_message']['type'] ?? 'info');
        $flash_text = htmlspecialchars($_SESSION['flash_message']['text'] ?? 'Notice!');
        echo "<div class='alert alert-{$flash_type} alert-dismissible fade show' role='alert'>{$flash_text}<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        unset($_SESSION['flash_message']);
    }
    ?>

    <?php if ($appointment): // Only display if appointment data loaded ?>
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <h1 class="h2 mb-2 mb-md-0">Appointment #<?= $appointment['id'] ?> Details</h1>
            <a href="appointments.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Appointments List
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i> Patient Information</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Name</dt>
                            <dd class="col-sm-8"><a
                                    href="patient_details.php?id=<?= $appointment['patient_id'] ?>"><?= htmlspecialchars($appointment['patient_name']) ?></a>
                            </dd>

                            <dt class="col-sm-4">Phone</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($appointment['patient_phone'] ?? 'N/A') ?></dd>

                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($appointment['patient_email'] ?? 'N/A') ?></dd>

                            <dt class="col-sm-4">Gender</dt>
                            <dd class="col-sm-8">
                                <?= !empty($appointment['patient_gender']) ? ucfirst(htmlspecialchars($appointment['patient_gender'])) : 'N/A' ?>
                            </dd>

                            <dt class="col-sm-4">Date of Birth</dt>
                            <dd class="col-sm-8">
                                <?= !empty($appointment['patient_dob']) && $appointment['patient_dob'] != '0000-00-00' ? date('M j, Y', strtotime($appointment['patient_dob'])) : 'N/A' ?>
                            </dd>

                            <dt class="col-sm-4">Blood Group</dt>
                            <dd class="col-sm-8">
                                <?= !empty($appointment['patient_blood_group']) ? htmlspecialchars($appointment['patient_blood_group']) : 'N/A' ?>
                            </dd>

                            <dt class="col-sm-4">Allergies</dt>
                            <dd class="col-sm-8">
                                <?= !empty($appointment['patient_allergies']) ? nl2br(htmlspecialchars($appointment['patient_allergies'])) : 'None reported' ?>
                            </dd>

                            <dt class="col-sm-4">Medical History</dt>
                            <dd class="col-sm-8">
                                <?= !empty($appointment['patient_medical_history']) ? nl2br(htmlspecialchars($appointment['patient_medical_history'])) : 'None reported' ?>
                            </dd>
                        </dl>
                    </div>
                </div>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-calendar-day me-2"></i> Appointment Details</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Date & Time</dt>
                            <dd class="col-sm-8"><?= date('l, M j, Y', strtotime($appointment['appointment_date'])) ?> at
                                <?= date('g:i A', strtotime($appointment['appointment_time'])) ?></dd>

                            <dt class="col-sm-4">Type</dt>
                            <dd class="col-sm-8"><span
                                    class="badge bg-<?= ($appointment['appointment_type'] ?? 'physical') === 'online' ? 'info' : 'secondary' ?>"><i
                                        class="fas <?= ($appointment['appointment_type'] ?? 'physical') === 'online' ? 'fa-video' : 'fa-hospital' ?> me-1"></i>
                                    <?= ucfirst(htmlspecialchars($appointment['appointment_type'] ?? 'physical')) ?></span>
                            </dd>

                            <dt class="col-sm-4">Reason</dt>
                            <dd class="col-sm-8">
                                <?= !empty($appointment['reason']) ? nl2br(htmlspecialchars($appointment['reason'])) : 'N/A' ?>
                            </dd>

                            <dt class="col-sm-4">Current Status</dt>
                            <dd class="col-sm-8">
                                <?php
                                $status_color = 'secondary';
                                switch (strtolower($appointment['status'])) { /* ... status cases ... */
                                    case 'scheduled':
                                        $status_color = 'primary';
                                        break;
                                    case 'confirmed':
                                        $status_color = 'info';
                                        break;
                                    case 'completed':
                                        $status_color = 'success';
                                        break;
                                    case 'cancelled':
                                        $status_color = 'danger';
                                        break;
                                    case 'pending':
                                        $status_color = 'warning';
                                        break;
                                    case 'no_show':
                                        $status_color = 'dark';
                                        break;
                                }
                                ?>
                                <span
                                    class="badge bg-<?= $status_color ?>"><?= ucfirst(htmlspecialchars($appointment['status'])) ?></span>
                            </dd>
                            <?php if ($appointment['status'] === 'cancelled' && !empty($appointment['cancellation_reason'])): ?>
                                <dt class="col-sm-4">Cancellation Reason</dt>
                                <dd class="col-sm-8 text-danger"><?= htmlspecialchars($appointment['cancellation_reason']) ?>
                                </dd>
                                <dt class="col-sm-4">Cancelled At</dt>
                                <dd class="col-sm-8 text-danger">
                                    <?= !empty($appointment['cancelled_at']) ? date('M j, Y g:i A', strtotime($appointment['cancelled_at'])) : 'N/A' ?>
                                </dd>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i> Doctor Actions</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="appointment_details.php?id=<?= $appointment_id ?>" class="mb-3">
                            <input type="hidden" name="appointment_id" value="<?= $appointment_id ?>">
                            <label for="new_status" class="form-label fw-bold">Update Appointment Status:</label>
                            <div class="input-group">
                                <select class="form-select" name="new_status" id="new_status" required>
                                    <option value="scheduled" <?= $appointment['status'] == 'scheduled' ? 'selected' : '' ?>>
                                        Scheduled</option>
                                    <option value="confirmed" <?= $appointment['status'] == 'confirmed' ? 'selected' : '' ?>>
                                        Confirmed</option>
                                    <option value="completed" <?= $appointment['status'] == 'completed' ? 'selected' : '' ?>>
                                        Completed</option>
                                    <option value="cancelled" <?= $appointment['status'] == 'cancelled' ? 'selected' : '' ?>>
                                        Cancelled by Clinic</option>
                                    <option value="no_show" <?= $appointment['status'] == 'no_show' ? 'selected' : '' ?>>
                                        Patient No-Show</option>
                                    <option value="pending" <?= $appointment['status'] == 'pending' ? 'selected' : '' ?>>
                                        Pending</option>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                            </div>
                        </form>
                        <hr>
                        <div class="d-grid">
                            <a href="message_patient.php?appointment_id=<?= $appointment['id'] ?>"
                                class="btn btn-info text-white">
                                <i class="fas fa-comments me-1"></i> Message Patient
                            </a>
                        </div>
                        <?php if ($appointment['appointment_type'] === 'online' && $appointment['status'] === 'scheduled'): ?>
                            <hr>
                            <div class="d-grid">
                                <a href="consultation.php?id=<?= $appointment['id'] ?>" class="btn btn-success">
                                    <i class="fas fa-video me-1"></i> Start Online Consultation
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-notes-medical me-2"></i> Consultation Notes</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="appointment_details.php?id=<?= $appointment_id ?>">
                            <input type="hidden" name="appointment_id" value="<?= $appointment_id ?>">
                            <div class="mb-3">
                                <label for="consultation_notes" class="form-label">Add or Edit Notes:</label>
                                <textarea class="form-control" name="consultation_notes" id="consultation_notes" rows="6"
                                    placeholder="Enter consultation summary, findings, prescriptions, follow-up instructions..."><?= htmlspecialchars($appointment['consultation_notes'] ?? '') ?></textarea>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" value="1" id="notify_patient"
                                    name="notify_patient" <?= ($appointment['notify_patient'] ?? 0) == 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notify_patient">
                                    Notify patient about these notes (shows as 'New' on their dashboard)
                                </label>
                            </div>
                            <button type="submit" name="save_notes" class="btn btn-success">
                                <i class="fas fa-save me-1"></i> Save Notes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif (!$page_error): // Show 'not found' only if no DB error ?>
        <div class="alert alert-warning">Appointment not found or you do not have permission to view it.</div>
        <a href="appointments.php" class="btn btn-primary"><i class="fas fa-arrow-left me-1"></i> Back to Appointments</a>
    <?php endif; ?>

</div>
<?php
// Assuming footer.php is one level up
require_once __DIR__ . '/../footer.php';
?>