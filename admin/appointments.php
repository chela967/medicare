<?php
session_start();
// Assuming config.php, header.php, footer.php are one level up
require_once __DIR__ . '/../config.php'; // Provides $conn

// --- Authentication & Authorization ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Access denied. Please log in as an admin.'];
    header("Location: ../auth.php"); // Adjust path if auth.php is elsewhere
    exit();
}

$page_title = "Manage Appointments";
$db_error = null;
$appointments = [];

// --- Data Fetching ---
// Check DB connection first
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    $db_error = "Database connection error. Please check configuration.";
    error_log("Admin Appointments Error: \$conn object not available or invalid after including config.php.");
} else {
    try {
        // Fetch all appointments with patient and doctor names
        $sql = "SELECT
                    a.id, a.appointment_date, a.appointment_time, a.status,
                    a.reason, a.consultation_fee, a.payment_status,
                    pat.name as patient_name,
                    doc_user.name as doctor_name,
                    s.name as specialty_name
                FROM appointments a
                JOIN users pat ON a.patient_id = pat.id -- Join users table for patient name
                JOIN doctors doc ON a.doctor_id = doc.id
                JOIN users doc_user ON doc.user_id = doc_user.id -- Join users table for doctor name
                JOIN specialties s ON doc.specialty_id = s.id
                ORDER BY a.appointment_date DESC, a.appointment_time DESC"; // Show most recent first

        $result = $conn->query($sql);

        if ($result) {
            $appointments = $result->fetch_all(MYSQLI_ASSOC);
            $result->free(); // Free result set
        } else {
            // Throw exception if query fails
            throw new mysqli_sql_exception("Query failed (fetch appointments): " . $conn->error);
        }

    } catch (mysqli_sql_exception $e) {
        error_log("Admin Appointments Fetch Error: " . $e->getMessage());
        $db_error = "Error loading appointment data: " . $e->getMessage(); // Consider showing a generic message in production
    } catch (Exception $e) {
        error_log("Admin Appointments General Error: " . $e->getMessage());
        $db_error = "An unexpected error occurred while loading appointments.";
    }
} // End DB connection check

// --- Include Header ---
// Assuming header.php is one level up
require_once __DIR__ . '/../header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
         <h1>Manage All Appointments</h1>
         </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_message']['type']) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash_message']['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>
    <?php if ($db_error): ?>
         <div class="alert alert-danger" role="alert">
             Database Error: <?= htmlspecialchars($db_error) ?>
         </div>
    <?php endif; ?>

    <div class="card shadow-sm">
         <div class="card-header">
            <h5 class="mb-0">Appointment List (<?= count($appointments) ?> Total)</h5>
            </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Specialty</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Fee (UGX)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($appointments)): ?>
                            <?php foreach ($appointments as $appt): ?>
                                <tr>
                                    <td><?= $appt['id'] ?></td>
                                    <td><?= htmlspecialchars($appt['patient_name']) ?></td>
                                    <td>Dr. <?= htmlspecialchars($appt['doctor_name']) ?></td>
                                    <td><?= htmlspecialchars($appt['specialty_name']) ?></td>
                                    <td><?= date('M j, Y', strtotime($appt['appointment_date'])) ?></td>
                                    <td><?= date('g:i A', strtotime($appt['appointment_time'])) ?></td>
                                    <td>
                                        <?php // Status Badge Logic
                                            $status_color = 'secondary';
                                            switch (strtolower($appt['status'])) {
                                                case 'scheduled': $status_color = 'primary'; break;
                                                case 'confirmed': $status_color = 'info'; break;
                                                case 'completed': $status_color = 'success'; break;
                                                case 'cancelled': $status_color = 'danger'; break;
                                                case 'pending': $status_color = 'warning'; break;
                                                case 'no_show': $status_color = 'dark'; break;
                                            }
                                        ?>
                                        <span class="badge bg-<?= $status_color ?>">
                                            <?= ucfirst(htmlspecialchars($appt['status'])) ?>
                                        </span>
                                    </td>
                                     <td>
                                        <?php // Payment Status Badge Logic
                                            $pay_status_color = 'warning'; // Default pending
                                            if (strtolower($appt['payment_status']) === 'paid') {
                                                $pay_status_color = 'success';
                                            } elseif (strtolower($appt['payment_status']) === 'failed') {
                                                $pay_status_color = 'danger';
                                            } elseif (strtolower($appt['payment_status']) === 'refunded') {
                                                 $pay_status_color = 'secondary';
                                            }
                                        ?>
                                        <span class="badge bg-<?= $pay_status_color ?>">
                                            <?= ucfirst(htmlspecialchars($appt['payment_status'] ?? 'pending')) ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?= number_format($appt['consultation_fee'], 2) ?></td>
                                    <td>
                                        <a href="admin_appointment_details.php?id=<?= $appt['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted">No appointments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
             </div>
    </div>
</div>

<?php
// Assuming footer.php is one level up
require_once __DIR__ . '/../footer.php';
?>
