<?php
require_once __DIR__ . '/../config.php'; // This should handle session initialization
require_once __DIR__ . '/../functions.php';

// Authentication check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
    header("Location: " . BASE_URL . "/auth.php");
}


$doctor_id = getDoctorIdByUserId($_SESSION['user']['id']);
if (!$doctor_id) {
    header("Location: " . BASE_URL . "/auth.php");
    exit();
}

// Get filter parameters
$date_filter = $_GET['date'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Handle status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $appointment_id = (int) $_POST['appointment_id'];
        $new_status = $_POST['status'];

        // Validate allowed status transitions
        $allowed_statuses = ['scheduled', 'completed', 'cancelled', 'no_show'];
        if (in_array($new_status, $allowed_statuses)) {
            $stmt = $conn->prepare("
                UPDATE appointments 
                SET status = ? 
                WHERE id = ? AND doctor_id = ?
            ");
            $stmt->bind_param("sii", $new_status, $appointment_id, $doctor_id);
            $stmt->execute();

            $_SESSION['success_message'] = "Appointment status updated";
            header("Location: appointments.php");
            exit();
        }
    }
}

// Build SQL query with filters
$query = "
    SELECT a.*, u.name as patient_name, u.phone as patient_phone
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ?
";

$params = [$doctor_id];
$types = "i";

if (!empty($date_filter) && $date_filter !== 'all') {
    $query .= " AND a.appointment_date = ?";
    $params[] = $date_filter;
    $types .= "s";
}

if ($status_filter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search_query)) {
    $query .= " AND (u.name LIKE ? OR u.phone LIKE ? OR a.reason LIKE ?)";
    $search_term = "%$search_query%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

// Execute query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get available dates for filter
$date_stmt = $conn->prepare("
    SELECT DISTINCT appointment_date 
    FROM appointments 
    WHERE doctor_id = ? 
    ORDER BY appointment_date DESC
    LIMIT 30
");
$date_stmt->bind_param("i", $doctor_id);
$date_stmt->execute();
$available_dates = $date_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Page title and header
$page_title = "My Appointments - Medicare";
include '../header.php';
?>
<style>
    /* Appointment Dashboard Styles */
    .appointment-card {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }

    .appointment-card:hover {
        transform: translateY(-2px);
    }

    /* Status badges */
    .status-badge {
        font-size: 0.8rem;
        padding: 5px 10px;
        border-radius: 50px;
        font-weight: 500;
    }

    .badge-scheduled {
        background-color: #3498db;
        color: white;
    }

    .badge-completed {
        background-color: #2ecc71;
        color: white;
    }

    .badge-cancelled {
        background-color: #e74c3c;
        color: white;
    }

    .badge-no_show {
        background-color: #f39c12;
        color: white;
    }

    /* Table styles */
    .appointment-table {
        font-size: 0.9rem;
    }

    .appointment-table th {
        background-color: #f8f9fa;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .appointment-table tr:hover {
        background-color: #f8f9fa;
    }

    /* Action buttons */
    .action-btn {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin: 2px;
    }

    .action-btn:hover {
        transform: scale(1.1);
    }

    /* Filter section */
    .filter-card {
        background-color: #f8f9fa;
        border: none;
        border-radius: 10px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .table-responsive {
            border: 0;
        }

        .table-responsive table {
            width: 100%;
        }

        .table-responsive thead {
            display: none;
        }

        .table-responsive tr {
            display: block;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }

        .table-responsive td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid #dee2e6;
        }

        .table-responsive td::before {
            content: attr(data-label);
            font-weight: bold;
            margin-right: 1rem;
        }

        .action-btns {
            justify-content: flex-end;
        }
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Appointments</h2>
        <a href="schedule.php" class="btn btn-outline-primary">
            <i class="fas fa-calendar-plus"></i> Manage Availability
        </a>
    </div>

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="date" class="form-label">Date</label>
                    <select id="date" name="date" class="form-select">
                        <option value="all">All Dates</option>
                        <?php foreach ($available_dates as $date): ?>
                            <option value="<?= $date['appointment_date'] ?>" <?= $date['appointment_date'] === $date_filter ? 'selected' : '' ?>>
                                <?= date('M j, Y', strtotime($date['appointment_date'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="scheduled" <?= $status_filter === 'scheduled' ? 'selected' : '' ?>>Scheduled
                        </option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed
                        </option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled
                        </option>
                        <option value="no_show" <?= $status_filter === 'no_show' ? 'selected' : '' ?>>No Show</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                        placeholder="Patient name, phone or reason" value="<?= htmlspecialchars($search_query) ?>">
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Appointments Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($appointments)): ?>
                <div class="alert alert-info">No appointments found matching your criteria.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th>Reason</th>
                                <th>Fee</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appt): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($appt['appointment_date'])) ?></td>
                                    <td><?= date('g:i A', strtotime($appt['appointment_time'])) ?></td>
                                    <td><?= htmlspecialchars($appt['patient_name']) ?></td>
                                    <td><?= htmlspecialchars($appt['patient_phone']) ?></td>
                                    <td><?= htmlspecialchars($appt['reason']) ?></td>
                                    <td>UGX <?= number_format($appt['consultation_fee']) ?></td>
                                    <td>
                                        <span class="badge bg-<?=
                                            $appt['status'] === 'completed' ? 'success' :
                                            ($appt['status'] === 'cancelled' ? 'danger' :
                                                ($appt['status'] === 'no_show' ? 'warning' : 'primary'))
                                            ?>">
                                            <?= ucfirst($appt['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($appt['status'] === 'scheduled'): ?>
                                                <a href="consultation.php?id=<?= $appt['id'] ?>" class="btn btn-primary"
                                                    title="Start Consultation">
                                                    <i class="fas fa-stethoscope"></i>
                                                </a>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal"
                                                data-bs-target="#statusModal<?= $appt['id'] ?>" title="Change Status">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <a href="patient.php?id=<?= $appt['patient_id'] ?>" class="btn btn-outline-info"
                                                title="View Patient">
                                                <i class="fas fa-user"></i>
                                            </a>
                                        </div>

                                        <!-- Status Change Modal -->
                                        <div class="modal fade" id="statusModal<?= $appt['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post">
                                                        <input type="hidden" name="appointment_id" value="<?= $appt['id'] ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Update Appointment Status</h5>
                                                            <button type="button" class="btn-close"
                                                                data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Current Status</label>
                                                                <input type="text" class="form-control"
                                                                    value="<?= ucfirst($appt['status']) ?>" readonly>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="status" class="form-label">New Status</label>
                                                                <select class="form-select" name="status" required>
                                                                    <option value="scheduled" <?= $appt['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                                                    <option value="completed" <?= $appt['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                                    <option value="cancelled" <?= $appt['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                                    <option value="no_show" <?= $appt['status'] === 'no_show' ? 'selected' : '' ?>>No Show</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" name="update_status"
                                                                class="btn btn-primary">Update Status</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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

<?php include '../footer.php'; ?>