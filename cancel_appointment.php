<?php
session_start();
require_once __DIR__ . '/config.php'; // Provides $conn

// Authentication Check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Please log in.'];
    header("Location: auth.php"); // Adjust path if needed
    exit;
}

// Validate Appointment ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Invalid appointment ID.'];
    header("Location: my_appointments.php");
    exit;
}

$appointment_id = (int) $_GET['id'];
$patient_id = $_SESSION['user']['id'];
$appointment = null; // Initialize appointment variable
$error = null; // Initialize error variable

// Check DB connection ($conn should be set by config.php)
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    // Log the error and stop, or set an error message to display
    error_log("Cancel Appointment Error: \$conn object not available or invalid after including config.php.");
    // Setting an error message to display in the HTML below
    $error = "Database connection error. Please try again later.";
    // We might not want to die() here so the page structure still loads with the error
} else {
    // --- Verify appointment belongs to patient and is cancellable ---
    // Check if status is 'scheduled' (or other cancellable statuses like 'confirmed')
    // Optional: Add time constraint (e.g., cannot cancel within 24 hours)
    $sql_check = "SELECT a.id, a.appointment_date, a.appointment_time, a.reason, a.status
                  FROM appointments a
                  WHERE a.id = ? AND a.patient_id = ?
                  AND a.status IN ('scheduled', 'confirmed', 'pending') -- Add statuses that are allowed to be cancelled
                 ";
    /* --- Optional: Add time constraint ---
    AND CONCAT(a.appointment_date, ' ', a.appointment_time) > DATE_ADD(NOW(), INTERVAL 24 HOUR)
    */

    // **** CHANGED: Use $conn ****
    $stmt_check = $conn->prepare($sql_check);

    if (!$stmt_check) {
        error_log("Prepare failed (check appointment): " . $conn->error);
        $error = "Error preparing appointment check.";
    } else {
        $stmt_check->bind_param("ii", $appointment_id, $patient_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $appointment = $result_check->fetch_assoc();
        $stmt_check->close();

        if (!$appointment) {
            // Appointment doesn't exist, doesn't belong to user, or is not in a cancellable state/timeframe
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'This appointment cannot be cancelled or does not exist.'];
            header("Location: my_appointments.php");
            exit;
        }
    }


    // --- Handle form submission ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $appointment && !$error) { // Proceed only if appointment is valid and no DB error
        $cancellation_reason = trim($_POST['cancellation_reason'] ?? '');

        if (empty($cancellation_reason)) {
            $error = "Please provide a cancellation reason";
        } else {
            // Start transaction
            // **** CHANGED: Use $conn ****
            $conn->begin_transaction();

            try {
                // Update appointment status
                // !! IMPORTANT: Assumes 'cancellation_reason' (TEXT) and 'cancelled_at' (DATETIME) columns exist !!
                $update_sql = "UPDATE appointments
                               SET status = 'cancelled', cancellation_reason = ?, cancelled_at = NOW()
                               WHERE id = ? AND patient_id = ?"; // Add patient_id for extra safety

                // **** CHANGED: Use $conn ****
                $stmt_update = $conn->prepare($update_sql);
                if (!$stmt_update)
                    throw new Exception("Prepare failed (update status): " . $conn->error);

                $stmt_update->bind_param("sii", $cancellation_reason, $appointment_id, $patient_id);
                if (!$stmt_update->execute())
                    throw new Exception("Execute failed (update status): " . $stmt_update->error);
                $stmt_update->close();

                // Send notification to doctor
                // Ensure 'notifications' table and columns exist as expected
                $notification_sql = "INSERT INTO notifications (user_id, title, message, type, created_at)
                                     SELECT doc.user_id, 'Appointment Cancelled',
                                            CONCAT('Appointment with ', p.name, ' on ',
                                            DATE_FORMAT(a.appointment_date, '%M %e, %Y'), ' at ',
                                            TIME_FORMAT(a.appointment_time, '%h:%i %p'), ' was cancelled by the patient.'),
                                            'appointment', NOW()
                                     FROM appointments a
                                     JOIN users p ON a.patient_id = p.id
                                     JOIN doctors doc ON a.doctor_id = doc.id
                                     WHERE a.id = ?";

                // **** CHANGED: Use $conn ****
                $stmt_notify = $conn->prepare($notification_sql);
                if (!$stmt_notify)
                    throw new Exception("Prepare failed (insert notification): " . $conn->error);

                $stmt_notify->bind_param("i", $appointment_id);
                if (!$stmt_notify->execute())
                    throw new Exception("Execute failed (insert notification): " . $stmt_notify->error);
                $stmt_notify->close();

                // Commit transaction
                // **** CHANGED: Use $conn ****
                $conn->commit();

                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'text' => 'Appointment cancelled successfully'
                ];
                header("Location: my_appointments.php");
                exit;

            } catch (Exception $e) {
                // **** CHANGED: Use $conn ****
                $conn->rollback();
                error_log("Cancellation Error: " . $e->getMessage());
                $error = "Failed to cancel appointment due to an error. Please try again."; // User-friendly error
            }
        }
    }
} // End DB connection check

$page_title = "Cancel Appointment";
// Adjust path if header is not in parent directory
require_once __DIR__ . '/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">

            <?php if (isset($_SESSION['flash_message'])): // Display flash message if redirected here ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_message']['type']) ?> alert-dismissible fade show"
                    role="alert">
                    <?= htmlspecialchars($_SESSION['flash_message']['text']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
            <?php endif; ?>

            <?php if (!$appointment && !$error): // Handle case where appointment wasn't found initially but no DB error ?>
                <div class="alert alert-danger">Could not load appointment details. It may have already been cancelled or
                    changed.</div>
                <a href="my_appointments.php" class="btn btn-primary"><i class="fas fa-arrow-left me-1"></i> Back to
                    Appointments</a>
            <?php elseif ($appointment): // Display cancellation form only if appointment is valid ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-times-circle me-2"></i> Cancel Appointment</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): // Display errors generated during POST or DB connection ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <div class="alert alert-warning">
                            <h5 class="alert-heading">Confirm Cancellation For:</h5>
                            <p class="mb-1">
                                <strong>Date:</strong>
                                <?= date('l, F j, Y', strtotime($appointment['appointment_date'])) ?>
                            </p>
                            <p class="mb-1">
                                <strong>Time:</strong> <?= date('g:i A', strtotime($appointment['appointment_time'])) ?>
                            </p>
                            <?php if (!empty($appointment['reason'])): ?>
                                <p class="mb-0">
                                    <strong>Original Reason:</strong> <?= htmlspecialchars($appointment['reason']) ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <form method="POST" action="cancel_appointment.php?id=<?= $appointment_id ?>">
                            <div class="mb-3">
                                <label for="cancellation_reason" class="form-label">
                                    <strong>Reason for Cancellation <span class="text-danger">*</span></strong>
                                </label>
                                <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="4"
                                    required
                                    placeholder="Please explain briefly why you need to cancel this appointment. This will be shared with the clinic/doctor."></textarea>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="my_appointments.php" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-arrow-left me-1"></i> Don't Cancel
                                </a>
                                <button type="submit" class="btn btn-danger"
                                    onclick="return confirm('Are you sure you want to permanently cancel this appointment?');">
                                    <i class="fas fa-times me-1"></i> Confirm Cancellation
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($error): // Display only the DB connection error if appointment couldn't be loaded ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <a href="my_appointments.php" class="btn btn-primary"><i class="fas fa-arrow-left me-1"></i> Back to
                    Appointments</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Adjust path if footer is not in parent directory
require_once __DIR__ . '/footer.php';
?>