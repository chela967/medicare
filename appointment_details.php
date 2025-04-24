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

$appointment_id = (int)$_GET['id'];
$patient_id = $_SESSION['user']['id'];
$appointment = null;

// Fetch appointment details
$sql = "SELECT a.*, d.name as doctor_name, s.name as specialty, 
               u.email as doctor_email, u.phone as doctor_phone,
               p.name as patient_name, p.email as patient_email
        FROM appointments a
        JOIN doctors doc ON a.doctor_id = doc.id
        JOIN users d ON doc.user_id = d.id
        JOIN specialties s ON doc.specialty_id = s.id
        JOIN users p ON a.patient_id = p.id
        WHERE a.id = ? AND a.patient_id = ?";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $appointment_id, $patient_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appointment) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Appointment not found'];
    header("Location: my_appointments.php");
    exit;
}

$page_title = "Appointment Details";
require_once __DIR__ . '/../header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Appointment Details</h1>
        <a href="my_appointments.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Appointments
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
                        <dd class="col-sm-8"><?= date('l, F j, Y', strtotime($appointment['appointment_date'])) ?></dd>

                        <dt class="col-sm-4">Time</dt>
                        <dd class="col-sm-8"><?= date('g:i A', strtotime($appointment['appointment_time'])) ?></dd>

                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-<?= 
                                $appointment['status'] === 'scheduled' ? 'primary' : 
                                ($appointment['status'] === 'completed' ? 'success' : 'danger')
                            ?>">
                                <?= ucfirst($appointment['status']) ?>
                            </span>
                        </dd>

                        <dt class="col-sm-4">Reason</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($appointment['reason']) ?></dd>

                        <dt class="col-sm-4">Fee</dt>
                        <dd class="col-sm-8">UGX <?= number_format($appointment['consultation_fee']) ?></dd>

                        <?php if (!empty($appointment['meeting_link'])): ?>
                            <dt class="col-sm-4">Meeting Link</dt>
                            <dd class="col-sm-8">
                                <a href="<?= htmlspecialchars($appointment['meeting_link']) ?>" target="_blank" class="btn btn-sm btn-success">
                                    <i class="fas fa-video me-1"></i> Join Meeting
                                </a>
                            </dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-user-md me-2"></i> Doctor Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Name</dt>
                        <dd class="col-sm-8">Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></dd>

                        <dt class="col-sm-4">Specialty</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($appointment['specialty']) ?></dd>

                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($appointment['doctor_email']) ?></dd>

                        <dt class="col-sm-4">Phone</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($appointment['doctor_phone']) ?></dd>
                    </dl>

                    <div class="d-grid gap-2 mt-3">
                        <a href="message_doctor.php?appointment_id=<?= $appointment['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-envelope me-1"></i> Message Doctor
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($appointment['status'] === 'completed' && !empty($appointment['consultation_notes'])): ?>
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-notes-medical me-2"></i> Consultation Notes</h5>
                    </div>
                    <div class="card-body">
                        <div class="consultation-notes p-3 bg-light rounded">
                            <?= nl2br(htmlspecialchars($appointment['consultation_notes'])) ?>
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-outline-secondary print-notes" 
                                    data-notes="<?= htmlspecialchars($appointment['consultation_notes']) ?>">
                                <i class="fas fa-print me-1"></i> Print Notes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($appointment['status'] === 'scheduled'): ?>
            <div class="col-12">
                <div class="card shadow-sm border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Cancel Appointment</h5>
                    </div>
                    <div class="card-body">
                        <p>You can cancel this appointment up to 24 hours before the scheduled time.</p>
                        <a href="cancel_appointment.php?id=<?= $appointment['id'] ?>" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i> Cancel Appointment
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Print Modal -->
<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
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
document.querySelector('.print-notes')?.addEventListener('click', function() {
    const notes = this.getAttribute('data-notes');
    const printContent = `
        <h4>Consultation Notes</h4>
        <hr>
        <div class="mb-3">
            <p><strong>Patient:</strong> <?= htmlspecialchars($appointment['patient_name']) ?></p>
            <p><strong>Doctor:</strong> Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></p>
            <p><strong>Date:</strong> <?= date('M j, Y', strtotime($appointment['appointment_date'])) ?></p>
        </div>
        <div class="notes-content">${notes.replace(/\n/g, '<br>')}</div>
    `;
    
    document.getElementById('printContent').innerHTML = printContent;
    const printModal = new bootstrap.Modal(document.getElementById('printModal'));
    printModal.show();
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>