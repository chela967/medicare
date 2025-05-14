<?php
// my_appointments.php - View all patient appointments

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    header("Location: ../auth.php");
    exit;
}

$patient_id = $_SESSION['user']['id'];
$patient_name = $_SESSION['user']['name'];
$page_title = "My Appointments";
require_once __DIR__ . '/header.php';

// Filter and pagination
$status = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$appointments = [];
$total_appointments = 0;

try {
    if (!isset($mysqli)) {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($mysqli->connect_error) {
            throw new Exception("Database connection failed: " . $mysqli->connect_error);
        }
    }

    // First, check for any scheduled appointments that have passed and mark them as no_show
    $update_sql = "UPDATE appointments 
                   SET status = 'no_show', 
                       notes = CONCAT(IFNULL(notes, ''), '\nAutomatically marked as no show - patient did not attend')
                   WHERE patient_id = ? 
                   AND status = 'scheduled'
                   AND CONCAT(appointment_date, ' ', appointment_time) < NOW()";

    $stmt = $mysqli->prepare($update_sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $stmt->close();

    // Base SQL with status filter
    $status_condition = $status === 'all' ? "" : "AND a.status = ?";

    // Count total
    $count_sql = "SELECT COUNT(*) as total 
                  FROM appointments a
                  JOIN doctors doc ON a.doctor_id = doc.id
                  JOIN users d ON doc.user_id = d.id
                  JOIN specialties s ON doc.specialty_id = s.id
                  WHERE a.patient_id = ? $status_condition";

    $stmt = $mysqli->prepare($count_sql);
    if ($status === 'all') {
        $stmt->bind_param("i", $patient_id);
    } else {
        $stmt->bind_param("is", $patient_id, $status);
    }
    $stmt->execute();
    $total_appointments = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Fetch paginated
    $sql = "SELECT a.id, a.appointment_date, a.appointment_time, a.status,
                   a.consultation_fee, a.consultation_notes, a.meeting_link,
                   d.name as doctor_name, s.name as specialty,
                   CASE 
                       WHEN a.status = 'scheduled' AND CONCAT(a.appointment_date, ' ', a.appointment_time) < NOW() 
                       THEN 'no_show'
                       ELSE a.status
                   END as display_status
            FROM appointments a
            JOIN doctors doc ON a.doctor_id = doc.id
            JOIN users d ON doc.user_id = d.id
            JOIN specialties s ON doc.specialty_id = s.id
            WHERE a.patient_id = ? $status_condition
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT ? OFFSET ?";

    $stmt = $mysqli->prepare($sql);
    if ($status === 'all') {
        $stmt->bind_param("iii", $patient_id, $per_page, $offset);
    } else {
        $stmt->bind_param("isii", $patient_id, $status, $per_page, $offset);
    }
    $stmt->execute();
    $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    error_log("Appointments Error: " . $e->getMessage());
    $error = "Could not load appointments. Please try again.";
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">My Appointments</h1>
        <a href="appointment.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> New Appointment
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <form method="get" class="row g-2">
                        <div class="col-8">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>Scheduled
                                </option>
                                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed
                                </option>
                                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled
                                </option>
                                <option value="no_show" <?= $status === 'no_show' ? 'selected' : '' ?>>No Show</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <a href="my_appointments.php" class="btn btn-outline-secondary w-100">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end">
                        <a href="patient_dashboard.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointments Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (!empty($appointments)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Doctor</th>
                                <th>Specialty</th>
                                <th>Status</th>
                                <th>Fee</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appt):
                                $effective_status = $appt['display_status'] ?? $appt['status'];
                                $is_past = strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']) < time();
                                ?>
                                <tr>
                                    <td>
                                        <?= date('M j, Y', strtotime($appt['appointment_date'])) ?><br>
                                        <small
                                            class="text-muted"><?= date('g:i A', strtotime($appt['appointment_time'])) ?></small>
                                        <?php if ($is_past && $effective_status === 'scheduled'): ?>
                                            <span class="badge bg-warning text-dark mt-1">Missed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>Dr. <?= htmlspecialchars($appt['doctor_name']) ?></td>
                                    <td><?= htmlspecialchars($appt['specialty']) ?></td>
                                    <td>
                                        <span class="badge bg-<?=
                                            $effective_status === 'scheduled' ? 'primary' :
                                            ($effective_status === 'completed' ? 'success' :
                                                ($effective_status === 'cancelled' ? 'danger' :
                                                    ($effective_status === 'no_show' ? 'warning' : 'secondary')))
                                            ?>">
                                            <?= ucfirst($effective_status) ?>
                                            <?php if ($effective_status === 'scheduled' && $is_past): ?>
                                                (Missed)
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>UGX <?= number_format($appt['consultation_fee']) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="appointment_details.php?id=<?= $appt['id'] ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($effective_status === 'scheduled' && !$is_past): ?>
                                                <a href="cancel_appointment.php?id=<?= $appt['id'] ?>"
                                                    class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($appt['meeting_link']) && $effective_status === 'scheduled' && !$is_past): ?>
                                                <a href="<?= htmlspecialchars($appt['meeting_link']) ?>" target="_blank"
                                                    class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-video"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= $status ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= ceil($total_appointments / $per_page); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&status=<?= $status ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < ceil($total_appointments / $per_page)): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= $status ?>">
                                    Next
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                    <h3>No Appointments Found</h3>
                    <p class="text-muted">
                        <?= $status === 'all' ? 'You have no appointments yet.' : "No $status appointments found." ?>
                    </p>
                    <a href="appointment.php" class="btn btn-primary mt-3">
                        <i class="fas fa-calendar-plus me-1"></i> Book Appointment
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>