<?php
session_start();
// Assuming config.php is one level up
require_once __DIR__ . '/../config.php'; // Provides $conn
// Assuming functions.php is one level up and needed
require_once __DIR__ . '/../functions.php'; // Provides getDoctorIdByUserId

// --- Authentication & Authorization ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Access denied. Please log in as a doctor.'];
    header("Location: ../auth.php"); // Adjust path if needed
    exit();
}

$doctor_user_id = $_SESSION['user']['id'];
// --- Get Doctor's internal ID ---
$doctor_profile_id = getDoctorIdByUserId($doctor_user_id, $conn);
if (!$doctor_profile_id) {
    error_log("Patient Details Error: Could not retrieve doctor profile ID for user ID: " . $doctor_user_id);
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Could not load your doctor profile.'];
    header("Location: dashboard.php");
    exit();
}

// --- Validate Patient ID from GET parameter ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Invalid patient ID specified.'];
    header("Location: patients.php"); // Redirect to doctor's patient list
    exit;
}
$patient_user_id_to_view = (int) $_GET['id']; // This is the user_id of the patient

// --- Initialize Variables ---
$patient_details = null;
$patient_appointments = [];
$page_error = null;

// --- Check DB Connection ---
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    $page_error = "Database connection error.";
    error_log("Doctor Patient Details Error: \$conn object not available.");
} else {
    try {
        // --- Fetch Patient Details ---
        $sql_patient = "SELECT
                            p.id as patient_table_id, p.user_id, p.name as patient_profile_name,
                            p.dob, p.gender, p.address, p.blood_group, p.allergies, p.medical_history,
                            u.name as user_name, u.email, u.phone as user_phone, u.created_at as registration_date
                        FROM patients p
                        JOIN users u ON p.user_id = u.id
                        WHERE p.user_id = ?";

        $stmt_patient = $conn->prepare($sql_patient);
        if (!$stmt_patient)
            throw new mysqli_sql_exception("Prepare failed (fetch patient): " . $conn->error);

        $stmt_patient->bind_param("i", $patient_user_id_to_view);
        $stmt_patient->execute();
        $result_patient = $stmt_patient->get_result();
        $patient_details = $result_patient->fetch_assoc();
        $stmt_patient->close();

        if (!$patient_details) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Patient profile details not found.'];
            header("Location: patients.php");
            exit;
        }

        // --- Fetch Patient's Appointments with THIS Doctor ---
        $sql_appts = "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.reason, a.appointment_type
                      FROM appointments a
                      WHERE a.patient_id = ? AND a.doctor_id = ?
                      ORDER BY a.appointment_date DESC, a.appointment_time DESC";

        $stmt_appts = $conn->prepare($sql_appts);
        if (!$stmt_appts)
            throw new mysqli_sql_exception("Prepare failed (fetch appointments): " . $conn->error);

        $stmt_appts->bind_param("ii", $patient_user_id_to_view, $doctor_profile_id);
        $stmt_appts->execute();
        $result_appts = $stmt_appts->get_result();
        $patient_appointments = $result_appts->fetch_all(MYSQLI_ASSOC);
        $stmt_appts->close();

    } catch (mysqli_sql_exception $e) {
        error_log("Doctor Patient Details Fetch Error: " . $e->getMessage());
        $page_error = "Error loading patient data.";
        $patient_details = null;
    }
}

$page_title = "Patient Details: " . ($patient_details ? htmlspecialchars($patient_details['user_name']) : 'Not Found');
// Assuming header.php is one level up
require_once __DIR__ . '/../header.php';
?>

<div class="container py-4">

    <?php if ($page_error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($page_error) ?></div>
    <?php endif; ?>

    <?php
    if (isset($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])) {
        // Provide defaults if keys are missing
        $flash_type = htmlspecialchars($_SESSION['flash_message']['type'] ?? 'info');
        $flash_text = htmlspecialchars($_SESSION['flash_message']['text'] ?? 'Notice!'); // Default text
        ?>
        <div class="alert alert-<?= $flash_type ?> alert-dismissible fade show" role="alert">
            <?= $flash_text ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php
        unset($_SESSION['flash_message']); // Unset after displaying
    }
    ?>
    <?php if ($patient_details): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-user me-2"></i>Patient: <?= htmlspecialchars($patient_details['user_name']) ?></h1>
            <a href="patients.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Patient List
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-id-card me-2"></i> Patient Information</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Name</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($patient_details['user_name']) ?></dd>

                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($patient_details['email']) ?></dd>

                            <dt class="col-sm-4">Phone</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($patient_details['user_phone']) ?></dd>

                            <dt class="col-sm-4">Date of Birth</dt>
                            <dd class="col-sm-8">
                                <?= !empty($patient_details['dob']) && $patient_details['dob'] != '0000-00-00' ? date('M j, Y', strtotime($patient_details['dob'])) : 'N/A' ?>
                            </dd>

                            <dt class="col-sm-4">Gender</dt>
                            <dd class="col-sm-8">
                                <?= !empty($patient_details['gender']) ? ucfirst(htmlspecialchars($patient_details['gender'])) : 'N/A' ?>
                            </dd>

                            <dt class="col-sm-4">Address</dt>
                            <dd class="col-sm-8">
                                <?= !empty($patient_details['address']) ? nl2br(htmlspecialchars($patient_details['address'])) : 'N/A' ?>
                            </dd>

                            <dt class="col-sm-4">Registered</dt>
                            <dd class="col-sm-8"><?= date('M j, Y', strtotime($patient_details['registration_date'])) ?>
                            </dd>
                        </dl>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-notes-medical me-2"></i> Medical Details</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Blood Group</dt>
                            <dd class="col-sm-8">
                                <?= !empty($patient_details['blood_group']) ? htmlspecialchars($patient_details['blood_group']) : 'N/A' ?>
                            </dd>

                            <dt class="col-sm-4">Allergies</dt>
                            <dd class="col-sm-8">
                                <?= !empty($patient_details['allergies']) ? nl2br(htmlspecialchars($patient_details['allergies'])) : 'None reported' ?>
                            </dd>

                            <dt class="col-sm-4">Medical History</dt>
                            <dd class="col-sm-8">
                                <?= !empty($patient_details['medical_history']) ? nl2br(htmlspecialchars($patient_details['medical_history'])) : 'None reported' ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i> Appointment History (With You)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($patient_appointments)): ?>
                            <div class="alert alert-light text-center">No appointment history found with this patient.</div>
                        <?php else: ?>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patient_appointments as $appt): ?>
                                            <tr>
                                                <td><?= date('M j, Y', strtotime($appt['appointment_date'])) ?></td>
                                                <td><?= date('g:i A', strtotime($appt['appointment_time'])) ?></td>
                                                <td>
                                                    <span
                                                        class="badge bg-<?= ($appt['appointment_type'] ?? 'physical') === 'online' ? 'indigo' : 'info' ?>">
                                                        <?= ucfirst(htmlspecialchars($appt['appointment_type'] ?? 'physical')) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php // Status Badge Logic
                                                                $status_color = 'secondary';
                                                                switch (strtolower($appt['status'])) {
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
                                                    <span class="badge bg-<?= $status_color ?>">
                                                        <?= ucfirst(htmlspecialchars($appt['status'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="appointment_details.php?id=<?= $appt['id'] ?>"
                                                        class="btn btn-xs btn-outline-primary" title="View Appointment Details">
                                                        <i class="fas fa-eye"></i>
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
        </div>

    <?php elseif (!$page_error): // Show 'not found' only if no DB error ?>
        <div class="alert alert-warning">Patient details could not be loaded.</div>
        <a href="patients.php" class="btn btn-primary"><i class="fas fa-arrow-left me-1"></i> Back to Patient List</a>
    <?php endif; ?>

</div>
<?php
// Assuming footer.php is one level up
require_once __DIR__ . '/../footer.php';
?>