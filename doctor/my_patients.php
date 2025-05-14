<?php
session_start();
// Assuming config.php, functions.php, header.php, footer.php are one level up
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
    error_log("My Patients Error: Could not retrieve doctor profile ID for user ID: " . $doctor_user_id);
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Could not load your doctor profile.'];
    header("Location: dashboard.php"); // Redirect to doctor dashboard
    exit();
}

// --- Initialize Variables ---
$patients = [];
$page_error = null;

// --- Check DB Connection ---
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    $page_error = "Database connection error.";
    error_log("Doctor My Patients Error: \$conn object not available.");
} else {
    // --- Fetch Unique Patients associated with this Doctor ---
    try {
        // Query remains the same, fetching unique patients and their last appointment date with this doctor
        $sql = "SELECT
                    u.id as patient_user_id,
                    u.name as patient_name,
                    u.email as patient_email,
                    u.phone as patient_phone,
                    MAX(a.appointment_date) as last_appointment_date
                FROM appointments a
                JOIN users u ON a.patient_id = u.id
                WHERE a.doctor_id = ?
                GROUP BY u.id, u.name, u.email, u.phone
                ORDER BY u.name ASC";

        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new mysqli_sql_exception("Prepare failed (fetch patients): " . $conn->error);

        $stmt->bind_param("i", $doctor_profile_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $patients = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

    } catch (mysqli_sql_exception $e) {
        error_log("Doctor My Patients Fetch Error: " . $e->getMessage());
        $page_error = "Error loading patient list.";
    }
} // End DB connection check

$page_title = "My Patients";
// Assuming header.php is one level up
require_once __DIR__ . '/../header.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-users me-2"></i>My Patients</h1>
    </div>

    <?php if ($page_error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($page_error) ?></div>
    <?php endif; ?>
    <?php
    if (isset($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])) {
        $flash_type = htmlspecialchars($_SESSION['flash_message']['type'] ?? 'info');
        $flash_text = htmlspecialchars($_SESSION['flash_message']['text'] ?? 'Notice!');
        ?>
        <div class="alert alert-<?= $flash_type ?> alert-dismissible fade show" role="alert">
            <?= $flash_text ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php
        unset($_SESSION['flash_message']);
    }
    ?>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Patient List (<?= count($patients) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($patients) && !$page_error): ?>
                <div class="alert alert-info text-center">You do not have any patients associated with your appointments
                    yet.</div>
            <?php elseif (!empty($patients)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Last Appointment Date (with you)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td><?= htmlspecialchars($patient['patient_name']) ?></td>
                                    <td><?= htmlspecialchars($patient['patient_email']) ?></td>
                                    <td><?= htmlspecialchars($patient['patient_phone'] ?? 'N/A') ?></td>
                                    <td><?= date('M j, Y', strtotime($patient['last_appointment_date'])) ?></td>
                                    <td>
                                        <a href="patient_details.php?id=<?= $patient['patient_user_id'] ?>"
                                            class="btn btn-sm btn-outline-info" title="View Patient Details">
                                            <i class="fas fa-user"></i> <span class="d-none d-md-inline">Details</span>
                                        </a>
                                        <a href="appointments.php?search=<?= urlencode($patient['patient_name']) ?>"
                                            class="btn btn-sm btn-outline-primary ms-1" title="View Appointments & Messages">
                                            <i class="fas fa-calendar-check"></i> <span
                                                class="d-none d-md-inline">Appointments</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
<?php
// Assuming footer.php is one level up
require_once __DIR__ . '/../footer.php';
?>