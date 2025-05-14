<?php
session_start();
require_once __DIR__ . '/config.php';

// Enhanced error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    header("Location: auth.php");
    exit();
}

$user_id = (int) $_SESSION['user']['id'];
$db_error = null;
$errors = [];
$doctors = [];

// Database connection check
if (!isset($conn) || $conn->connect_error) {
    $db_error = "Database connection error. Please try again later.";
    error_log("DB Connection Error: " . ($conn->connect_error ?? "Unknown"));
} else {
    try {
        // Get available doctors with proper joins
        $query = "SELECT d.id, u.name, s.name AS specialty, d.consultation_fee
                  FROM doctors d
                  JOIN users u ON d.user_id = u.id
                  JOIN specialties s ON d.specialty_id = s.id
                  WHERE d.status = 'approved' AND d.available = 1
                  ORDER BY u.name ASC";

        $result = $conn->query($query);

        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }

        $doctors = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

    } catch (Exception $e) {
        error_log("Appointment Error: " . $e->getMessage());
        $db_error = "Could not load doctor information.";
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    try {
        // Validate inputs
        $doctor_id = (int) ($_POST['doctor_id'] ?? 0);
        $appointment_date = $conn->real_escape_string($_POST['appointment_date'] ?? '');
        $appointment_time = $conn->real_escape_string($_POST['appointment_time'] ?? '');
        $appointment_type = in_array($_POST['appointment_type'] ?? '', ['physical', 'online']) ? $_POST['appointment_type'] : '';
        $reason = $conn->real_escape_string(trim($_POST['reason'] ?? ''));

        // Validation
        if ($doctor_id <= 0)
            $errors[] = "Please select a doctor";
        if (empty($appointment_date))
            $errors[] = "Please select a date";
        if (empty($appointment_time))
            $errors[] = "Please select a time";
        if (empty($appointment_type))
            $errors[] = "Please select appointment type";
        if (empty($reason))
            $errors[] = "Please provide a reason";
        if (!empty($appointment_date) && strtotime($appointment_date) < strtotime('today')) {
            $errors[] = "Appointment date cannot be in the past";
        }

        if (empty($errors)) {
            $conn->begin_transaction();

            // Get doctor details
            $stmt = $conn->prepare("SELECT consultation_fee, user_id FROM doctors WHERE id = ?");
            $stmt->bind_param("i", $doctor_id);
            $stmt->execute();
            $doctor = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$doctor) {
                throw new Exception("Doctor not found");
            }

            // Create appointment
            $stmt = $conn->prepare("INSERT INTO appointments 
                (patient_id, doctor_id, appointment_date, appointment_time, 
                 appointment_type, reason, consultation_fee, status, payment_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', 'pending')");
            $stmt->bind_param(
                "iissssd",
                $user_id,
                $doctor_id,
                $appointment_date,
                $appointment_time,
                $appointment_type,
                $reason,
                $doctor['consultation_fee']
            );

            if ($stmt->execute()) {
                $appointment_id = $conn->insert_id;
                $stmt->close();

                // Create notification
                $patient_name = $_SESSION['user']['name'] ?? 'Patient';
                $message = "New appointment booked by $patient_name for " .
                    date('M j, Y @ g:i A', strtotime("$appointment_date $appointment_time"));

                $stmt = $conn->prepare("INSERT INTO notifications 
                    (user_id, title, message, type, created_at) 
                    VALUES (?, 'New Appointment', ?, 'appointment', NOW())");
                $stmt->bind_param("is", $doctor['user_id'], $message);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                header("Location: process_payment.php?type=appointment&id=$appointment_id");
                exit();
            } else {
                throw new Exception("Failed to book appointment");
            }
        }
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Booking Error: " . $e->getMessage());
        $errors[] = "Failed to book appointment. Please try again.";
    }
}

// HTML Header
$page_title = "Book Appointment";
require_once __DIR__ . '/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h2 class="text-center mb-4">Book Appointment</h2>

                    <?php if ($db_error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($db_error) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <!-- Doctor Selection -->
                        <div class="mb-3">
                            <label class="form-label">Select Doctor <span class="text-danger">*</span></label>
                            <select class="form-select" name="doctor_id" required>
                                <option value="">-- Select Doctor --</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?= $doctor['id'] ?>" data-fee="<?= $doctor['consultation_fee'] ?>"
                                        <?= ($_POST['doctor_id'] ?? '') == $doctor['id'] ? 'selected' : '' ?>>
                                        Dr. <?= htmlspecialchars($doctor['name']) ?>
                                        (<?= htmlspecialchars($doctor['specialty']) ?>)
                                        - UGX <?= number_format($doctor['consultation_fee']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Date/Time -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="appointment_date"
                                    min="<?= date('Y-m-d') ?>"
                                    value="<?= htmlspecialchars($_POST['appointment_date'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Time <span class="text-danger">*</span></label>
                                <select class="form-select" name="appointment_time" required>
                                    <option value="">-- Select Time --</option>
                                    <?php
                                    $times = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'];
                                    foreach ($times as $time):
                                        $timeValue = date("H:i:s", strtotime($time));
                                        ?>
                                        <option value="<?= $timeValue ?>" <?= ($_POST['appointment_time'] ?? '') == $timeValue ? 'selected' : '' ?>>
                                            <?= date("g:i A", strtotime($time)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Appointment Type -->
                        <div class="mb-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="appointment_type" value="physical"
                                    id="physical" <?= ($_POST['appointment_type'] ?? 'physical') == 'physical' ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="physical">Physical Visit</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="appointment_type" value="online"
                                    id="online" <?= ($_POST['appointment_type'] ?? '') == 'online' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="online">Online Consultation</label>
                            </div>
                        </div>

                        <!-- Reason -->
                        <div class="mb-3">
                            <label class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="reason" rows="3" required
                                placeholder="Briefly describe your reason..."><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
                        </div>

                        <!-- Fee Display -->
                        <div class="alert alert-info">
                            Consultation Fee: <span id="feeDisplay">UGX 0</span>
                        </div>

                        <!-- Submit -->
                        <div class="text-center mt-4">
                            <button type="submit" name="book_appointment" class="btn btn-primary btn-lg">
                                Book Appointment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const doctorSelect = document.querySelector('select[name="doctor_id"]');
        const feeDisplay = document.getElementById('feeDisplay');

        function updateFee() {
            const selectedOption = doctorSelect.options[doctorSelect.selectedIndex];
            if (selectedOption && selectedOption.value) {
                feeDisplay.textContent = 'UGX ' +
                    parseInt(selectedOption.getAttribute('data-fee')).toLocaleString();
            }
        }

        doctorSelect.addEventListener('change', updateFee);
        updateFee(); // Initial update
    });
</script>

<?php
require_once __DIR__ . '/footer.php';
?>