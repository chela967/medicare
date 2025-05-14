<?php
// appointments.php (Patient's appointments list)
ob_start(); // Start output buffering
session_start();
require_once __DIR__ . '/config.php'; // For $conn, set_flash_message (if used)
require_once __DIR__ . '/functions.php'; // For helper functions like getStatusBadgeClass (if you have one)

// --- Authentication: Ensure a patient is logged in ---
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    if (function_exists('set_flash_message')) {
        set_flash_message('Please log in to view your appointments.', 'warning');
    } else {
        $_SESSION['flash_messages'][] = ['message' => 'Please log in to view your appointments.', 'type' => 'warning'];
    }
    header("Location: auth.php"); // Your login page
    exit();
}

// Ensure the user is a patient. If doctors have a different appointments list, redirect them.
if ($_SESSION['user']['role'] !== 'patient') {
    // Optional: Redirect doctors to their own dashboard/appointment list
    if ($_SESSION['user']['role'] === 'doctor') {
        // Assuming doctors have their own appointment overview, e.g., in a doctor/ folder
        header("Location: doctor/appointments.php");
        exit();
    }
    // For any other role that isn't patient, deny access or redirect appropriately
    if (function_exists('set_flash_message')) {
        set_flash_message('This page is for patient appointments.', 'info');
    } else {
        $_SESSION['flash_messages'][] = ['message' => 'This page is for patient appointments.', 'type' => 'info'];
    }
    header("Location: dashboard.php"); // A generic dashboard or home page
    exit();
}

$patient_user_id = (int) $_SESSION['user']['id'];
$page_title = "My Appointments";
$upcoming_appointments = [];
$past_appointments = [];
$page_error = null;

// --- Check DB Connection ---
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    $page_error = "Database connection error. Please try again later.";
    error_log("Patient Appointments List Error: Database connection failed.");
} else {
    try {
        $current_datetime = date('Y-m-d H:i:s');
        $current_date = date('Y-m-d');

        // Fetch appointments for the logged-in patient
        // We join with users (for doctor's name) and doctors (for specialty_id) and specialties
        $sql = "SELECT
                    a.id AS appointment_id, a.appointment_date, a.appointment_time,
                    a.appointment_type, a.status AS appointment_status,
                    doc_user.name AS doctor_name,
                    s.name AS doctor_specialty
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.id
                JOIN users doc_user ON d.user_id = doc_user.id
                JOIN specialties s ON d.specialty_id = s.id
                WHERE a.patient_id = ?
                ORDER BY a.appointment_date DESC, a.appointment_time DESC"; // Order to easily separate later

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new mysqli_sql_exception("Database prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $patient_user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($appointment = $result->fetch_assoc()) {
            // Combine date and time for comparison
            $appointment_datetime_str = $appointment['appointment_date'] . ' ' . $appointment['appointment_time'];
            $appointment_timestamp = strtotime($appointment_datetime_str);
            $current_timestamp_val = strtotime($current_datetime);

            if ($appointment_timestamp >= $current_timestamp_val && !in_array($appointment['appointment_status'], ['completed', 'cancelled', 'no_show'])) {
                $upcoming_appointments[] = $appointment;
            } else {
                $past_appointments[] = $appointment;
            }
        }
        $stmt->close();

        // Further sort upcoming appointments by soonest first
        usort($upcoming_appointments, function ($a, $b) {
            $datetimeA = strtotime($a['appointment_date'] . ' ' . $a['appointment_time']);
            $datetimeB = strtotime($b['appointment_date'] . ' ' . $b['appointment_time']);
            return $datetimeA <=> $datetimeB;
        });

    } catch (mysqli_sql_exception $e) {
        error_log("Patient Appointments List Fetch Error: " . $e->getMessage());
        $page_error = "Error loading your appointments.";
    }
}

// Function to get Bootstrap badge class based on status (can be in functions.php)
if (!function_exists('getStatusBadgeClass')) {
    function getStatusBadgeClass($status)
    {
        $status_lower = strtolower($status ?? '');
        switch ($status_lower) {
            case 'scheduled':
                return 'primary';
            case 'confirmed':
                return 'info'; // Example, if you use 'confirmed'
            case 'completed':
                return 'success';
            case 'cancelled':
                return 'danger';
            case 'no_show':
                return 'dark';
            case 'pending':
                return 'warning'; // e.g. payment pending
            default:
                return 'secondary';
        }
    }
}


require_once __DIR__ . '/header.php';
?>

<div class="container py-4 my-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <h1 class="h2 mb-2 mb-md-0"><?= htmlspecialchars($page_title) ?></h1>
        <a href="book_appointment.php" class="btn btn-primary">
            <i class="fas fa-plus-circle me-1"></i> Book New Appointment
        </a>
    </div>

    <?php if (isset($_SESSION['flash_messages'])): ?>
        <?php foreach ($_SESSION['flash_messages'] as $flash_message): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_message['type']) ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($flash_message['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
        <?php unset($_SESSION['flash_messages']); ?>
    <?php endif; ?>

    <?php if ($page_error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($page_error) ?></div>
    <?php endif; ?>

    <h3 class="mb-3 mt-4">Upcoming Appointments</h3>
    <?php if (empty($upcoming_appointments) && !$page_error): ?>
        <div class="alert alert-info">You have no upcoming appointments.</div>
    <?php elseif (!empty($upcoming_appointments)): ?>
        <div class="list-group shadow-sm">
            <?php foreach ($upcoming_appointments as $appointment): ?>
                <div class="list-group-item list-group-item-action flex-column align-items-start py-3 px-3">
                    <div class="d-flex w-100 justify-content-between">
                        <h5 class="mb-1">Dr. <?= htmlspecialchars($appointment['doctor_name']) ?>
                            <small
                                class="text-muted d-block d-sm-inline">(<?= htmlspecialchars($appointment['doctor_specialty']) ?>)</small>
                        </h5>
                        <small class="text-muted"><?= date('D, M j, Y', strtotime($appointment['appointment_date'])) ?></small>
                    </div>
                    <p class="mb-1">
                        Time: <?= date('g:i A', strtotime($appointment['appointment_time'])) ?> |
                        Type: <span
                            class="badge bg-<?= ($appointment['appointment_type'] ?? 'physical') === 'online' ? 'info' : 'secondary' ?>"><?= ucfirst(htmlspecialchars($appointment['appointment_type'] ?? 'Physical')) ?></span>
                        |
                        Status: <span
                            class="badge bg-<?= getStatusBadgeClass($appointment['appointment_status']) ?>"><?= ucfirst(htmlspecialchars($appointment['appointment_status'])) ?></span>
                    </p>
                    <div class="mt-2">
                        <a href="appointment_details.php?id=<?= $appointment['appointment_id'] ?>"
                            class="btn btn-sm btn-outline-primary me-2">
                            <i class="fas fa-eye me-1"></i> View Details
                        </a>
                        <a href="message_doctor.php?appointment_id=<?= $appointment['appointment_id'] ?>"
                            class="btn btn-sm btn-outline-info me-2">
                            <i class="fas fa-comments me-1"></i> Message Doctor
                        </a>
                        <?php if (in_array($appointment['appointment_status'], ['scheduled', 'confirmed', 'pending'])): // Allow cancellation for certain statuses ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h3 class="mb-3 mt-5">Past Appointments</h3>
    <?php if (empty($past_appointments) && !$page_error): ?>
        <div class="alert alert-secondary">You have no past appointments.</div>
    <?php elseif (!empty($past_appointments)): ?>
        <div class="list-group shadow-sm">
            <?php foreach ($past_appointments as $appointment): ?>
                <div class="list-group-item list-group-item-action flex-column align-items-start py-3 px-3 bg-light">
                    <div class="d-flex w-100 justify-content-between">
                        <h5 class="mb-1">Dr. <?= htmlspecialchars($appointment['doctor_name']) ?>
                            <small
                                class="text-muted d-block d-sm-inline">(<?= htmlspecialchars($appointment['doctor_specialty']) ?>)</small>
                        </h5>
                        <small class="text-muted"><?= date('D, M j, Y', strtotime($appointment['appointment_date'])) ?></small>
                    </div>
                    <p class="mb-1">
                        Time: <?= date('g:i A', strtotime($appointment['appointment_time'])) ?> |
                        Type: <span
                            class="badge bg-<?= ($appointment['appointment_type'] ?? 'physical') === 'online' ? 'info' : 'secondary' ?>"><?= ucfirst(htmlspecialchars($appointment['appointment_type'] ?? 'Physical')) ?></span>
                        |
                        Status: <span
                            class="badge bg-<?= getStatusBadgeClass($appointment['appointment_status']) ?>"><?= ucfirst(htmlspecialchars($appointment['appointment_status'])) ?></span>
                    </p>
                    <div class="mt-2">
                        <a href="appointment_details.php?id=<?= $appointment['appointment_id'] ?>"
                            class="btn btn-sm btn-outline-secondary me-2">
                            <i class="fas fa-eye me-1"></i> View Details
                        </a>
                        <a href="message_doctor.php?appointment_id=<?= $appointment['appointment_id'] ?>"
                            class="btn btn-sm btn-outline-info">
                            <i class="fas fa-comments me-1"></i> View Messages
                        </a>
                        <?php if ($appointment['appointment_status'] === 'completed'): // Example: Link to leave review ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/footer.php';
ob_end_flush(); // Send output buffer
?>