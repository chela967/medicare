<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: auth.php?redirect=appointments");
    exit();
}

$page_title = "Book Appointment - Medicare";
$user_id = $_SESSION['user']['id'];
$errors = [];

// Get available doctors with their specialties
$doctors = [];
$query = "SELECT d.id, u.name, s.name AS specialty, d.consultation_fee 
          FROM doctors d
          JOIN users u ON d.user_id = u.id
          JOIN specialties s ON d.specialty_id = s.id
          WHERE d.status = 'approved' AND d.available = 1";
$result = $conn->query($query);
if ($result) {
    $doctors = $result->fetch_all(MYSQLI_ASSOC);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    // Validate inputs
    $doctor_id = (int) $_POST['doctor_id'];
    $appointment_date = $conn->real_escape_string($_POST['appointment_date']);
    $appointment_time = $conn->real_escape_string($_POST['appointment_time']);
    $reason = $conn->real_escape_string(trim($_POST['reason']));

    // Basic validation
    if (empty($doctor_id))
        $errors[] = "Please select a doctor";
    if (empty($appointment_date))
        $errors[] = "Please select a date";
    if (empty($appointment_time))
        $errors[] = "Please select a time";
    if (empty($reason))
        $errors[] = "Please provide a reason for visit";

    if (empty($errors)) {
        // Get doctor's fee
        $fee_query = $conn->prepare("SELECT consultation_fee FROM doctors WHERE id = ?");
        $fee_query->bind_param("i", $doctor_id);
        $fee_query->execute();
        $fee_result = $fee_query->get_result();
        $doctor_fee = $fee_result->fetch_assoc()['consultation_fee'];

        // Create appointment
        $stmt = $conn->prepare("
            INSERT INTO appointments 
            (patient_id, doctor_id, appointment_date, appointment_time, 
             reason, consultation_fee, payment_status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");

        $stmt->bind_param(
            "iissid",
            $user_id,
            $doctor_id,
            $appointment_date,
            $appointment_time,
            $reason,
            $doctor_fee
        );

        if ($stmt->execute()) {
            $appointment_id = $conn->insert_id; // MySQLi way to get last inserted ID

            // Redirect to confirmation
            header("Location: appointment_confirmation.php?id=" . $appointment_id);
            exit();
        } else {
            $errors[] = "Failed to book appointment. Please try again. Error: " . $conn->error;
        }
    }
}

include 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">Book Appointment</h2>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="appointmentForm">
                        <!-- Doctor Selection -->
                        <div class="mb-3">
                            <label class="form-label">Select Doctor</label>
                            <select class="form-select" name="doctor_id" id="doctorSelect" required>
                                <option value="">-- Select Doctor --</option>
                                <?php if (!empty($doctors)): ?>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?= $doctor['id'] ?>" data-fee="<?= $doctor['consultation_fee'] ?>">
                                            Dr. <?= htmlspecialchars($doctor['name']) ?>
                                            (<?= htmlspecialchars($doctor['specialty']) ?>)
                                            - UGX <?= number_format($doctor['consultation_fee']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No doctors available</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Date and Time -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Appointment Date</label>
                                <input type="date" class="form-control" name="appointment_date"
                                    min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Appointment Time</label>
                                <select class="form-select" name="appointment_time" required>
                                    <option value="">-- Select Time --</option>
                                    <option value="08:00:00">8:00 AM</option>
                                    <option value="09:00:00">9:00 AM</option>
                                    <option value="10:00:00">10:00 AM</option>
                                    <option value="11:00:00">11:00 AM</option>
                                    <option value="13:00:00">1:00 PM</option>
                                    <option value="14:00:00">2:00 PM</option>
                                    <option value="15:00:00">3:00 PM</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Reason for Visit</label>
                            <textarea class="form-control" name="reason" rows="3" required></textarea>
                        </div>

                        <!-- Fee Display (dynamic with JavaScript) -->
                        <div class="alert alert-info mb-4">
                            <h5 class="mb-1">Consultation Fee: <span id="feeDisplay">UGX 0</span></h5>
                            <small class="text-muted">Payment will be processed after booking confirmation</small>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" name="book_appointment" class="btn btn-primary btn-lg">
                                <i class="fas fa-calendar-check me-2"></i> Book Appointment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Update fee display when doctor is selected
    document.getElementById('doctorSelect').addEventListener('change', function () {
        const selectedOption = this.options[this.selectedIndex];
        const fee = selectedOption.getAttribute('data-fee') || 0;
        document.getElementById('feeDisplay').textContent = `UGX ${parseInt(fee).toLocaleString()}`;
    });
</script>

<?php include 'footer.php'; ?>