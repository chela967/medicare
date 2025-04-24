<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    header("Location: ../auth.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my_appointments.php");
    exit;
}

$appointment_id = (int) $_GET['id'];
$patient_id = $_SESSION['user']['id'];

// Verify appointment belongs to patient and is cancellable
$sql = "SELECT a.* 
        FROM appointments a
        WHERE a.id = ? AND a.patient_id = ? AND a.status = 'scheduled'
        AND CONCAT(a.appointment_date, ' ', a.appointment_time) > DATE_ADD(NOW(), INTERVAL 24 HOUR)";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $appointment_id, $patient_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appointment) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Appointment cannot be cancelled or does not exist'];
    header("Location: my_appointments.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cancellation_reason = trim($_POST['cancellation_reason'] ?? '');

    if (empty($cancellation_reason)) {
        $error = "Please provide a cancellation reason";
    } else {
        // Start transaction
        $mysqli->begin_transaction();

        try {
            // Update appointment status
            $update_sql = "UPDATE appointments 
                           SET status = 'cancelled', cancellation_reason = ?, cancelled_at = NOW() 
                           WHERE id = ?";
            $stmt = $mysqli->prepare($update_sql);
            $stmt->bind_param("si", $cancellation_reason, $appointment_id);
            $stmt->execute();
            $stmt->close();

            // Send notification to doctor (simplified example)
            $notification_sql = "INSERT INTO notifications (user_id, title, message, type) 
                                SELECT doc.user_id, 'Appointment Cancelled', 
                                       CONCAT('Appointment with ', p.name, ' on ', 
                                       DATE_FORMAT(a.appointment_date, '%M %e, %Y'), ' at ', 
                                       TIME_FORMAT(a.appointment_time, '%h:%i %p'), ' was cancelled'),
                                       'appointment'
                                FROM appointments a
                                JOIN users p ON a.patient_id = p.id
                                JOIN doctors doc ON a.doctor_id = doc.id
                                WHERE a.id = ?";
            $stmt = $mysqli->prepare($notification_sql);
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $stmt->close();

            // Commit transaction
            $mysqli->commit();

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'text' => 'Appointment cancelled successfully'
            ];
            header("Location: my_appointments.php");
            exit;

        } catch (Exception $e) {
            $mysqli->rollback();
            error_log("Cancellation Error: " . $e->getMessage());
            $error = "Failed to cancel appointment. Please try again.";
        }
    }
}

$page_title = "Cancel Appointment";
require_once __DIR__ . '/../header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-times-circle me-2"></i> Cancel Appointment</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="alert alert-warning">
                        <h5 class="alert-heading">Appointment Details</h5>
                        <p>
                            <strong>Date:</strong>
                            <?= date('l, F j, Y', strtotime($appointment['appointment_date'])) ?><br>
                            <strong>Time:</strong> <?= date('g:i A', strtotime($appointment['appointment_time'])) ?><br>
                            <strong>Reason:</strong> <?= htmlspecialchars($appointment['reason']) ?>
                        </p>
                    </div>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="cancellation_reason" class="form-label">
                                <strong>Reason for Cancellation</strong>
                            </label>
                            <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="4"
                                required placeholder="Please explain why you're cancelling this appointment"></textarea>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="appointment_details.php?id=<?= $appointment_id ?>"
                                class="btn btn-secondary me-md-2">
                                <i class="fas fa-arrow-left me-1"></i> Go Back
                            </a>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times me-1"></i> Confirm Cancellation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>