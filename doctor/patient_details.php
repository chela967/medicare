<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// --- Authentication Check ---
if (!isset($_SESSION['user'])) {
    header("Location: " . BASE_URL . "/auth.php");
    exit();
}

// --- Database Connection Check ---
if (!isset($conn) || !$conn instanceof mysqli) {
    die("Database connection error. Please try again later.");
}

// --- Get Patient ID from URL ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Invalid patient ID.'];
    header("Location: " . ($_SESSION['user']['role'] === 'doctor' ? 'patients.php' : '../index.php'));
    exit();
}
$patient_id = (int) $_GET['id'];

// --- Check Authorization ---
if ($_SESSION['user']['role'] === 'doctor') {
    // Verify doctor has this patient
    $stmt = $conn->prepare("SELECT 1 FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1");
    $doctor_id = getDoctorIdByUserId($_SESSION['user']['id'], $conn);
    $stmt->bind_param("ii", $patient_id, $doctor_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'You are not authorized to view this patient.'];
        header("Location: patients.php");
        exit();
    }
} elseif ($_SESSION['user']['role'] === 'patient' && $_SESSION['user']['id'] != $patient_id) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'You can only view your own records.'];
    header("Location: ../index.php");
    exit();
}

// --- Fetch Patient Details ---
$sql = "
    SELECT 
        u.id, u.name, u.email, u.phone, u.created_at as registered_on,
        p.dob, p.gender, p.address, p.city, p.country, p.postal_code, 
        p.blood_group, p.allergies, p.medical_history, p.profile_picture
    FROM users u
    LEFT JOIN patients p ON u.id = p.user_id
    WHERE u.id = ? AND u.role = 'patient'
    LIMIT 1
";

$patient = null;
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    if ($stmt->execute()) {
        $patient = $stmt->get_result()->fetch_assoc();
    }
    $stmt->close();
}

if (!$patient) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Patient not found.'];
    header("Location: " . ($_SESSION['user']['role'] === 'doctor' ? 'patients.php' : '../index.php'));
    exit();
}

// --- Fetch Patient Appointments ---
$appointments = [];
$sql_appointments = "
    SELECT a.id, a.appointment_date, a.appointment_time, a.reason, a.status,
           a.consultation_notes, a.meeting_link,
           d.name as doctor_name, d.specialty as doctor_specialty
    FROM appointments a
    JOIN users d ON a.doctor_id = d.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
";

$stmt = $conn->prepare($sql_appointments);
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    if ($stmt->execute()) {
        $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

// --- Fetch Patient Prescriptions ---
$prescriptions = [];
$sql_prescriptions = "
    SELECT p.id, p.date_prescribed, p.medication, p.dosage, p.frequency,
           p.duration, p.instructions, p.refills,
           d.name as doctor_name
    FROM prescriptions p
    JOIN users d ON p.doctor_id = d.id
    WHERE p.patient_id = ?
    ORDER BY p.date_prescribed DESC
";

$stmt = $conn->prepare($sql_prescriptions);
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    if ($stmt->execute()) {
        $prescriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

// --- Set Page Title ---
$page_title = "Patient Details - " . htmlspecialchars($patient['name']);
include __DIR__ . '/../header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <?php if ($_SESSION['user']['role'] === 'doctor'): ?>
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="patients.php">Patients</a></li>
            <?php else: ?>
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="profile.php">My Profile</a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page">Patient Details</li>
        </ol>
    </nav>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= $_SESSION['flash_message']['type'] ?> alert-dismissible fade show">
            <?= $_SESSION['flash_message']['text'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Patient Profile Column -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i> Patient Profile</h5>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($patient['profile_picture'])): ?>
                        <img src="../uploads/patients/<?= htmlspecialchars($patient['profile_picture']) ?>"
                            class="rounded-circle mb-3" width="150" height="150" alt="Patient Photo">
                    <?php else: ?>
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mb-3"
                            style="width: 150px; height: 150px; margin: 0 auto;">
                            <i class="fas fa-user fa-4x text-secondary"></i>
                        </div>
                    <?php endif; ?>

                    <h4><?= htmlspecialchars($patient['name']) ?></h4>
                    <p class="text-muted">Patient since <?= date('M Y', strtotime($patient['registered_on'])) ?></p>

                    <hr>

                    <div class="text-start">
                        <p><strong>Email:</strong> <?= htmlspecialchars($patient['email']) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($patient['phone'] ?? 'Not provided') ?></p>

                        <?php if (!empty($patient['dob'])): ?>
                            <p><strong>Date of Birth:</strong> <?= date('M j, Y', strtotime($patient['dob'])) ?>
                                (<?= calculateAge($patient['dob']) ?> years)</p>
                        <?php endif; ?>

                        <?php if (!empty($patient['gender'])): ?>
                            <p><strong>Gender:</strong> <?= ucfirst(htmlspecialchars($patient['gender'])) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($patient['blood_group'])): ?>
                            <p><strong>Blood Group:</strong> <?= htmlspecialchars($patient['blood_group']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Medical Information Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-heartbeat me-2"></i> Medical Information</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($patient['allergies'])): ?>
                        <h6 class="text-danger"><i class="fas fa-allergy me-2"></i>Allergies</h6>
                        <p><?= nl2br(htmlspecialchars($patient['allergies'])) ?></p>
                        <hr>
                    <?php endif; ?>

                    <?php if (!empty($patient['medical_history'])): ?>
                        <h6><i class="fas fa-history me-2"></i>Medical History</h6>
                        <p><?= nl2br(htmlspecialchars($patient['medical_history'])) ?></p>
                    <?php else: ?>
                        <p class="text-muted">No medical history recorded.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Content Column -->
        <div class="col-md-8">
            <!-- Address Information Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i> Address Information</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($patient['address']) || !empty($patient['city'])): ?>
                        <address>
                            <?= !empty($patient['address']) ? htmlspecialchars($patient['address']) . '<br>' : '' ?>
                            <?= !empty($patient['city']) ? htmlspecialchars($patient['city']) . ', ' : '' ?>
                            <?= !empty($patient['country']) ? htmlspecialchars($patient['country']) . '<br>' : '' ?>
                            <?= !empty($patient['postal_code']) ? htmlspecialchars($patient['postal_code']) : '' ?>
                        </address>
                    <?php else: ?>
                        <p class="text-muted">No address information provided.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Appointments Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i> Appointments</h5>
                        <?php if ($_SESSION['user']['role'] === 'doctor'): ?>
                            <a href="new_appointment.php?patient_id=<?= $patient_id ?>" class="btn btn-sm btn-light">
                                <i class="fas fa-plus me-1"></i> New Appointment
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($appointments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Doctor</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appt): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($appt['appointment_date'])) ?></td>
                                            <td>Dr. <?= htmlspecialchars($appt['doctor_name']) ?></td>
                                            <td><?= htmlspecialchars($appt['reason']) ?></td>
                                            <td>
                                                <span class="badge bg-<?=
                                                    $appt['status'] === 'completed' ? 'success' :
                                                    ($appt['status'] === 'cancelled' ? 'danger' : 'primary')
                                                    ?>">
                                                    <?= ucfirst($appt['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="consultation.php?id=<?= $appt['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No appointments found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Prescriptions Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-prescription me-2"></i> Prescriptions</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($prescriptions)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Medication</th>
                                        <th>Dosage</th>
                                        <th>Doctor</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prescriptions as $rx): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($rx['date_prescribed'])) ?></td>
                                            <td><?= htmlspecialchars($rx['medication']) ?></td>
                                            <td><?= htmlspecialchars($rx['dosage']) ?></td>
                                            <td>Dr. <?= htmlspecialchars($rx['doctor_name']) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info view-prescription"
                                                    data-rxid="<?= $rx['id'] ?>">
                                                    <i class="fas fa-info-circle"></i> Details
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No prescriptions found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Prescription Details Modal -->
<div class="modal fade" id="prescriptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Prescription Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="prescriptionDetails">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary print-prescription">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>

<script>
    // AJAX for loading prescription details
    document.querySelectorAll('.view-prescription').forEach(button => {
        button.addEventListener('click', function () {
            const rxId = this.getAttribute('data-rxid');
            fetch(`get_prescription.php?id=${rxId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('prescriptionDetails').innerHTML = data;
                    const modal = new bootstrap.Modal(document.getElementById('prescriptionModal'));
                    modal.show();
                });
        });
    });

    // Print functionality
    document.querySelector('.print-prescription')?.addEventListener('click', function () {
        const printContent = document.getElementById('prescriptionDetails').innerHTML;
        const originalContent = document.body.innerHTML;
        document.body.innerHTML = printContent;
        window.print();
        document.body.innerHTML = originalContent;
    });
</script>