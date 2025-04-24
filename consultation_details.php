<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load configuration
require_once __DIR__ . '/config.php';

// Verify connection
if (!isset($mysqli)) {
    $mysqli = new mysqli('localhost', 'root', '', 'medicare');
    if ($mysqli->connect_error) {
        die("Database connection failed: " . $mysqli->connect_error);
    }
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    header("Location: ../auth.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my_consultations.php");
    exit;
}

$consultation_id = (int) $_GET['id'];
$patient_id = $_SESSION['user']['id'];
$consultation = null;

// Fetch consultation details
$sql = "SELECT a.*, d.name as doctor_name, s.name as specialty,
               d.email as doctor_email, d.phone as doctor_phone
        FROM appointments a
        JOIN doctors doc ON a.doctor_id = doc.id
        JOIN users d ON doc.user_id = d.id
        JOIN specialties s ON doc.specialty_id = s.id
        WHERE a.id = ? AND a.patient_id = ? AND a.consultation_notes IS NOT NULL";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $consultation_id, $patient_id);
$stmt->execute();
$consultation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$consultation) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Consultation not found'];
    header("Location: my_consultations.php");
    exit;
}

// Mark as read
$mysqli->query("UPDATE appointments SET notify_patient = 0 WHERE id = $consultation_id");

$page_title = "Consultation Details";
require_once __DIR__ . '/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Consultation Details</h1>
        <a href="my_consultations.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Consultations
        </a>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i> Appointment Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Date</dt>
                        <dd class="col-sm-8"><?= date('l, F j, Y', strtotime($consultation['appointment_date'])) ?></dd>

                        <dt class="col-sm-4">Time</dt>
                        <dd class="col-sm-8"><?= date('g:i A', strtotime($consultation['appointment_time'])) ?></dd>

                        <dt class="col-sm-4">Doctor</dt>
                        <dd class="col-sm-8">Dr. <?= htmlspecialchars($consultation['doctor_name']) ?></dd>

                        <dt class="col-sm-4">Specialty</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($consultation['specialty']) ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-user-md me-2"></i> Doctor Contact</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($consultation['doctor_email']) ?></dd>

                        <dt class="col-sm-4">Phone</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($consultation['doctor_phone']) ?></dd>
                    </dl>
                    <div class="d-grid gap-2 mt-3">
                        <a href="message_doctor.php?appointment_id=<?= $consultation['id'] ?>" class="btn btn-info">
                            <i class="fas fa-envelope me-1"></i> Message Doctor
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-notes-medical me-2"></i> Consultation Notes</h5>
                </div>
                <div class="card-body">
                    <div class="consultation-notes p-3 bg-light rounded mb-3">
                        <?= nl2br(htmlspecialchars($consultation['consultation_notes'])) ?>
                    </div>
                    <div class="d-flex justify-content-between">
                        <button class="btn btn-outline-primary print-notes"
                            data-notes="<?= htmlspecialchars($consultation['consultation_notes']) ?>"
                            data-doctor="Dr. <?= htmlspecialchars($consultation['doctor_name']) ?>"
                            data-date="<?= date('M j, Y', strtotime($consultation['appointment_date'])) ?>">
                            <i class="fas fa-print me-1"></i> Print Notes
                        </button>
                        <a href="prescriptions.php?appointment_id=<?= $consultation['id'] ?>"
                            class="btn btn-outline-success">
                            <i class="fas fa-prescription me-1"></i> View Prescriptions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Modal -->
<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Print Consultation Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="printContent">
                <!-- Content will be inserted here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelector('.print-notes')?.addEventListener('click', function () {
        const notes = this.getAttribute('data-notes');
        const doctor = this.getAttribute('data-doctor');
        const date = this.getAttribute('data-date');

        const printContent = `
        <div class="container">
            <div class="text-center mb-4">
                <h3>Consultation Notes</h3>
                <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Clinic Logo" style="height: 50px;" class="mb-2">
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <p><strong>Patient:</strong> <?= htmlspecialchars($_SESSION['user']['name']) ?></p>
                    <p><strong>Date of Birth:</strong> <?= !empty($_SESSION['user']['dob']) ? date('M j, Y', strtotime($_SESSION['user']['dob'])) : 'Not provided' ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Doctor:</strong> ${doctor}</p>
                    <p><strong>Date:</strong> ${date}</p>
                </div>
            </div>
            
            <hr>
            
            <h5 class="mb-3">Consultation Summary:</h5>
            <div class="border p-3">${notes.replace(/\n/g, '<br>')}</div>
            
            <div class="mt-4 text-muted small">
                <p>This document was generated on ${new Date().toLocaleDateString()}.</p>
            </div>
        </div>
    `;

        document.getElementById('printContent').innerHTML = printContent;
        const printModal = new bootstrap.Modal(document.getElementById('printModal'));
        printModal.show();
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>