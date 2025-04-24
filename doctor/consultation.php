<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// --- Authentication and Role Check ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
    header("Location: " . (defined('BASE_URL') ? BASE_URL : '') . "/auth.php");
    exit();
}

// --- Database Connection Check ---
if (!isset($conn) || !$conn instanceof mysqli) {
    error_log("consultation.php: MySQLi connection object (\$conn) not available from config.php.");
    die("Database connection is not available. Please check configuration.");
}

// --- Get Logged-in Doctor ID ---
$doctor_id = getDoctorIdByUserId($_SESSION['user']['id'], $conn);
if (!$doctor_id) {
    error_log("consultation.php: Could not retrieve doctor ID for user ID: " . $_SESSION['user']['id']);
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Could not load your doctor profile.'];
    header("Location: dashboard.php");
    exit();
}

// --- Get Appointment ID from URL ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Invalid appointment ID specified.'];
    header("Location: appointments.php");
    exit();
}
$appointment_id = (int) $_GET['id'];

// --- Fetch Appointment and Patient Details ---
$sql = "
    SELECT
        a.*,
        p_user.name as patient_name,
        p_user.email as patient_email,
        p_user.phone as patient_phone,
        pat.dob as patient_dob,
        pat.gender as patient_gender,
        pat.allergies as patient_allergies
    FROM appointments a
    JOIN users p_user ON a.patient_id = p_user.id
    LEFT JOIN patients pat ON p_user.id = pat.user_id
    WHERE a.id = ? AND a.doctor_id = ?
    LIMIT 1
";

$appointment = null;
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $appointment_id, $doctor_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc();
    }
    $stmt->close();
}

if (!$appointment) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Appointment not found or you do not have permission to view it.'];
    header("Location: appointments.php");
    exit();
}

// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle meeting link generation
    if (isset($_POST['generate_meeting'])) {
        $meeting_link = generateGoogleMeetLink();
        $meeting_id = "medicare-" . bin2hex(random_bytes(4));
        
        // Store in database
        $update_sql = "UPDATE appointments SET meeting_link = ?, meeting_id = ? WHERE id = ? AND doctor_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssii", $meeting_link, $meeting_id, $appointment_id, $doctor_id);
        
        if ($stmt->execute()) {
            // Schedule email notification
            $notification_time = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']) - (30 * 60);
            $email_subject = "Your Upcoming Consultation with Dr. " . $_SESSION['user']['name'];
            $email_message = createEmailTemplate($appointment, $meeting_link);
            
            $send_time = date('Y-m-d H:i:s', $notification_time);
            $stmt->bind_param("ssss", $appointment['patient_email'], $email_subject, $email_message, $send_time);
            $stmt->execute();
            
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Meeting link generated and patient notified!'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Failed to generate meeting link.'];
        }
    }
    
    // Handle consultation notes submission
    if (isset($_POST['save_consultation'])) {
        $consultation_notes = trim($_POST['consultation_notes'] ?? '');
        $new_status = 'completed';
        
        if (!empty($consultation_notes)) {
            $update_sql = "UPDATE appointments SET consultation_notes = ?, status = ? WHERE id = ? AND doctor_id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ssii", $consultation_notes, $new_status, $appointment_id, $doctor_id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Consultation notes saved and appointment marked as completed.'];
                header("Location: appointments.php");
                exit();
            } else {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Failed to save consultation notes.'];
            }
        } else {
            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Consultation notes cannot be empty.'];
        }
    }
    
    header("Location: consultation.php?id=" . $appointment_id);
    exit();
}

// --- Generate WhatsApp Link ---
$patient_phone_cleaned = preg_replace('/[^0-9]/', '', $appointment['patient_phone'] ?? '');
if (strlen($patient_phone_cleaned) == 10 && substr($patient_phone_cleaned, 0, 1) === '0') {
    $patient_phone_whatsapp = "256" . substr($patient_phone_cleaned, 1);
} else {
    $patient_phone_whatsapp = $patient_phone_cleaned;
}

$whatsapp_message = "Hello " . htmlspecialchars($appointment['patient_name']) . ",\n\n";
$whatsapp_message .= "Your consultation link with Dr. " . htmlspecialchars($_SESSION['user']['name']) . ":\n";
$whatsapp_message .= ($appointment['meeting_link'] ?? 'No link generated yet') . "\n\n";
$whatsapp_message .= "Scheduled for: " . date('M j, Y @ g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])) . "\n";
$whatsapp_message .= "Please join a few minutes early.\n\n";
$whatsapp_message .= "Regards,\nMedicare Team";

$whatsapp_url = "https://wa.me/" . $patient_phone_whatsapp . "?text=" . urlencode($whatsapp_message);

// --- Page Setup ---
$page_title = "Consultation - Appt #" . $appointment_id;
include __DIR__ . '/../header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="appointments.php">Appointments</a></li>
            <li class="breadcrumb-item active">Consultation #<?= $appointment_id ?></li>
        </ol>
    </nav>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= $_SESSION['flash_message']['type'] ?> alert-dismissible fade show">
            <?= $_SESSION['flash_message']['text'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Patient Information Column -->
        <div class="col-md-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Patient Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Name:</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($appointment['patient_name']) ?></dd>
                        
                        <dt class="col-sm-4">Contact:</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($appointment['patient_phone'] ?? 'N/A') ?></dd>
                        
                        <dt class="col-sm-4">Email:</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($appointment['patient_email'] ?? 'N/A') ?></dd>
                        
                        <?php if (!empty($appointment['patient_dob'])): ?>
                            <dt class="col-sm-4">Age:</dt>
                            <dd class="col-sm-8"><?= calculateAge($appointment['patient_dob']) ?> years</dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Meeting Information Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-video me-2"></i>Consultation Meeting</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($appointment['meeting_link'])): ?>
                        <form method="POST">
                            <p class="text-muted">No meeting link has been generated yet.</p>
                            <button type="submit" name="generate_meeting" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-1"></i> Generate Google Meet Link
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label"><strong>Meeting Link:</strong></label>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" value="<?= htmlspecialchars($appointment['meeting_link']) ?>" readonly>
                                <button class="btn btn-outline-secondary" onclick="copyToClipboard('<?= htmlspecialchars($appointment['meeting_link']) ?>')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <a href="<?= htmlspecialchars($appointment['meeting_link']) ?>" target="_blank" class="btn btn-success">
                                <i class="fas fa-video me-1"></i> Start Meeting
                            </a>
                        </div>
                        
                        <?php if (!empty($appointment['patient_phone'])): ?>
                            <div class="mt-3">
                                <a href="<?= $whatsapp_url ?>" target="_blank" class="btn btn-success whatsapp-btn">
                                    <i class="fab fa-whatsapp me-1"></i> Send via WhatsApp
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            An email reminder with this link will be sent automatically 30 minutes before the appointment.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Consultation Notes Column -->
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-notes-medical me-2"></i>Consultation Notes</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="consultation_notes" class="form-label"><strong>Clinical Notes:</strong></label>
                            <textarea class="form-control" id="consultation_notes" name="consultation_notes" rows="12"
                                <?= $appointment['status'] === 'completed' ? 'readonly' : 'required' ?>
                            ><?= htmlspecialchars($appointment['consultation_notes'] ?? '') ?></textarea>
                        </div>
                        
                        <?php if ($appointment['status'] !== 'completed'): ?>
                            <button type="submit" name="save_consultation" class="btn btn-success">
                                <i class="fas fa-save me-1"></i> Save & Complete Consultation
                            </button>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                This consultation was completed on <?= date('M j, Y', strtotime($appointment['updated_at'])) ?>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('Meeting link copied to clipboard!');
    }, function(err) {
        console.error('Could not copy text: ', err);
    });
}
</script>

<?php
// Helper functions
function generateGoogleMeetLink() {
    $random_id = bin2hex(random_bytes(8));
    return "https://meet.google.com/" . substr($random_id, 0, 3) . "-" . substr($random_id, 3, 4) . "-" . substr($random_id, 7, 3);
}

function createEmailTemplate($appointment, $meeting_link) {
    return "
    <html>
    <body>
        <h3>Upcoming Consultation Reminder</h3>
        <p>Dear {$appointment['patient_name']},</p>
        <p>Your consultation with Dr. {$_SESSION['user']['name']} is scheduled for:</p>
        <p><strong>Date:</strong> " . date('F j, Y', strtotime($appointment['appointment_date'])) . "</p>
        <p><strong>Time:</strong> " . date('g:i A', strtotime($appointment['appointment_time'])) . "</p>
        <p>Please join using this link:</p>
        <p><a href='$meeting_link'>$meeting_link</a></p>
        <p>Best regards,<br>Medicare Team</p>
    </body>
    </html>
    ";
}

include __DIR__ . '/../footer.php';
?>