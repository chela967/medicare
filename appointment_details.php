<?php
// patient_appointment_details.php (e.g., located in your main application root)
session_start();
require_once __DIR__ . '/config.php'; // For $conn and set_flash_message
// require_once __DIR__ . '/functions.php'; // If you have helper functions like getStatusBadgeClass

// --- Authentication: Ensure a user is logged in ---
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    if (function_exists('set_flash_message')) {
        set_flash_message('Please log in to view your appointment details.', 'warning');
    } else {
        $_SESSION['flash_messages'][] = ['message' => 'Please log in to view your appointment details.', 'type' => 'warning'];
    }
    header("Location: auth.php"); // Your login page
    exit();
}

$current_user_id = (int) $_SESSION['user']['id'];
$current_user_role = $_SESSION['user']['role'] ?? 'patient'; // Get user role

// --- Validate Appointment ID from GET parameter ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT) || (int) $_GET['id'] <= 0) {
    if (function_exists('set_flash_message')) {
        set_flash_message('Invalid or missing appointment ID.', 'warning');
    } else {
        $_SESSION['flash_messages'][] = ['message' => 'Invalid or missing appointment ID.', 'type' => 'warning'];
    }
    header("Location: appointments.php"); // Redirect to patient's appointment list page
    exit;
}
$appointment_id_to_view = (int) $_GET['id'];

// --- Initialize Variables ---
$appointment_details = null;
$page_error = null;

// --- Check DB Connection ---
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    $page_error = "Database connection error. Please try again later.";
    error_log("Patient Appointment Details Error: Database connection failed.");
} else {
    try {
        // Fetch appointment details, including doctor's name
        $sql = "SELECT
                    a.id AS appointment_id, a.appointment_date, a.appointment_time,
                    a.appointment_type, a.reason, a.status AS appointment_status,
                    a.consultation_notes AS doctor_notes_for_patient, /* Only show if intended for patient */
                    a.meeting_link, a.patient_id, a.doctor_id,
                    doc_user.name AS doctor_name,
                    s.name AS doctor_specialty
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.id
                JOIN users doc_user ON d.user_id = doc_user.id
                JOIN specialties s ON d.specialty_id = s.id
                WHERE a.id = ?"; // We will add patient_id check after fetching

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new mysqli_sql_exception("Database prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $appointment_id_to_view);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment_details = $result->fetch_assoc();
        $stmt->close();

        if (!$appointment_details) {
            $page_error = "Appointment not found.";
        } else {
            // --- Authorization Check: Is current user the patient for this appointment? ---
            if ($appointment_details['patient_id'] != $current_user_id) {
                // If not the patient, maybe it's a doctor trying to access via patient link (less common)
                // Or simply an unauthorized access attempt.
                // For this patient-specific page, we strictly allow only the patient.
                // (Doctors should use their own doctor/appointment_details.php view)
                if (function_exists('set_flash_message')) {
                    set_flash_message('You do not have permission to view this appointment.', 'danger');
                } else {
                    $_SESSION['flash_messages'][] = ['message' => 'You do not have permission to view this appointment.', 'type' => 'danger'];
                }
                $appointment_details = null; // Clear data
                // Optionally redirect, or just show error on page
                header("Location: appointments.php"); // Redirect to patient's appointment list
                exit();
            }
            // If doctor notes should only be visible if 'notify_patient' flag is set
            // (Assuming 'notify_patient' is a column in your 'appointments' table)
            // if (isset($appointment_details['notify_patient']) && $appointment_details['notify_patient'] == 0) {
            //    $appointment_details['doctor_notes_for_patient'] = "Your doctor has not shared notes for this appointment yet.";
            // }
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Patient Appointment Details Fetch Error: " . $e->getMessage());
        $page_error = "Error loading appointment details.";
        $appointment_details = null;
    }
}

$page_title = "My Appointment Details";
if ($appointment_details && isset($appointment_details['appointment_date'])) {
    $page_title .= " - " . date('M j, Y', strtotime($appointment_details['appointment_date']));
}
require_once __DIR__ . '/header.php';
?>

<div class="container py-4 my-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3"><?= htmlspecialchars($page_title) ?></h1>
                <a href="appointments.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to My Appointments
                </a>
            </div>

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

            <?php if ($page_error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($page_error) ?></div>
            <?php endif; ?>

            <?php if ($appointment_details && !$page_error): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i> Appointment with Dr.
                            <?= htmlspecialchars($appointment_details['doctor_name']) ?></h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Doctor:</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($appointment_details['doctor_name']) ?>
                                (<?= htmlspecialchars($appointment_details['doctor_specialty']) ?>)</dd>

                            <dt class="col-sm-4">Date & Time:</dt>
                            <dd class="col-sm-8">
                                <?= date('l, F j, Y', strtotime($appointment_details['appointment_date'])) ?>
                                at <?= date('g:i A', strtotime($appointment_details['appointment_time'])) ?>
                            </dd>

                            <dt class="col-sm-4">Type:</dt>
                            <dd class="col-sm-8">
                                <span
                                    class="badge bg-<?= ($appointment_details['appointment_type'] ?? 'physical') === 'online' ? 'info' : 'secondary' ?>">
                                    <i
                                        class="fas <?= ($appointment_details['appointment_type'] ?? 'physical') === 'online' ? 'fa-video' : 'fa-hospital' ?> me-1"></i>
                                    <?= ucfirst(htmlspecialchars($appointment_details['appointment_type'] ?? 'Physical')) ?>
                                </span>
                            </dd>

                            <?php if (($appointment_details['appointment_type'] ?? '') === 'online' && !empty($appointment_details['meeting_link'])): ?>
                                <dt class="col-sm-4">Meeting Link:</dt>
                                <dd class="col-sm-8">
                                    <a href="<?= htmlspecialchars($appointment_details['meeting_link']) ?>" target="_blank"
                                        class="btn btn-sm btn-success">
                                        <i class="fas fa-video me-1"></i> Join Online Consultation
                                    </a>
                                </dd>
                            <?php endif; ?>

                            <dt class="col-sm-4">Reason for Visit:</dt>
                            <dd class="col-sm-8">
                                <?= !empty($appointment_details['reason']) ? nl2br(htmlspecialchars($appointment_details['reason'])) : 'Not specified' ?>
                            </dd>

                            <dt class="col-sm-4">Status:</dt>
                            <dd class="col-sm-8">
                                <?php
                                // Assuming getStatusBadgeClass is available from functions.php or define a simpler one here
                                $status_class = 'secondary'; // Default
                                if (function_exists('getStatusBadgeClass')) {
                                    $status_class = getStatusBadgeClass($appointment_details['appointment_status']);
                                } else {
                                    switch (strtolower($appointment_details['appointment_status'])) {
                                        case 'scheduled':
                                            $status_class = 'primary';
                                            break;
                                        case 'confirmed':
                                            $status_class = 'info';
                                            break;
                                        case 'completed':
                                            $status_class = 'success';
                                            break;
                                        case 'cancelled':
                                            $status_class = 'danger';
                                            break;
                                        case 'no_show':
                                            $status_class = 'dark';
                                            break;
                                        case 'pending':
                                            $status_class = 'warning';
                                            break;
                                    }
                                }
                                ?>
                                <span class="badge bg-<?= $status_class ?>">
                                    <?= ucfirst(htmlspecialchars($appointment_details['appointment_status'])) ?>
                                </span>
                            </dd>
                        </dl>

                        <?php if (!empty($appointment_details['doctor_notes_for_patient'])): ?>
                            <hr>
                            <h6 class="mt-3"><i class="fas fa-notes-medical me-2"></i> Doctor's Notes for You:</h6>
                            <div class="p-2 bg-light border rounded">
                                <?= nl2br(htmlspecialchars($appointment_details['doctor_notes_for_patient'])) ?>
                            </div>
                        <?php endif; ?>

                        <hr>
                        <div class="mt-3">
                            <a href="message_doctor.php?appointment_id=<?= $appointment_id_to_view ?>"
                                class="btn btn-primary">
                                <i class="fas fa-comments me-1"></i> Messages for this Appointment
                            </a>
                            <?php if ($appointment_details['appointment_status'] === 'scheduled' || $appointment_details['appointment_status'] === 'confirmed'): ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php elseif (!$page_error): ?>
                <div class="alert alert-warning">The requested appointment could not be displayed. It may have been
                    cancelled or access is restricted.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>