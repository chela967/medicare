<?php
session_start();
require_once __DIR__ . '/../config.php'; // Provides $conn (mysqli connection) and defines BASE_URL
require_once __DIR__ . '/../functions.php'; // Contains getDoctorIdByUserId

// --- Authentication and Role Check ---
// Ensure user is logged in and is a doctor
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
    // Redirect to login page if not a logged-in doctor
    header("Location: " . BASE_URL . "/auth.php");
    exit(); // *** ADDED exit() ***: Stop script execution after redirect
}

// --- Get Doctor ID ---
// Use a consistent variable name, e.g., $doctor_id
// Ensure $conn (mysqli connection object from config.php) is available and passed correctly
$doctor_id = getDoctorIdByUserId($_SESSION['user']['id'], $conn);

// --- Check if Doctor ID was found ---
// Check the variable you actually assigned the result to ($doctor_id)
if (!$doctor_id) {
    // Log the error for debugging
    error_log("appointments.php: Could not retrieve doctor ID for user ID: " . $_SESSION['user']['id']);
    // Redirect to login or show an error. Redirecting might be confusing if they ARE logged in.
    // Consider redirecting to the dashboard with an error message or showing an error here.
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Could not load your doctor profile. Please try again or contact support.'];
    header("Location: dashboard.php"); // Redirect back to dashboard might be better
    // header("Location: " . BASE_URL . "/auth.php"); // Original redirect
    exit();
}

// --- Check if Doctor is Approved (Optional but recommended here too) ---
// You might want to add the approval check here as well, similar to dashboard.php
/*
$doctor_data = getDoctorData($_SESSION['user']['id']); // Assuming this function exists and uses $conn if needed
if (!$doctor_data || $doctor_data['status'] !== 'approved') {
    header("Location: pending_approval.php"); // Redirect to a specific page for pending/rejected
    exit();
}
*/


// Get filter parameters
$date_filter = $_GET['date'] ?? ''; // Default to empty string instead of today? Or 'all'?
$status_filter = $_GET['status'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Handle status updates (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status']) && isset($_POST['appointment_id']) && isset($_POST['status'])) {
        $appointment_id = (int) $_POST['appointment_id'];
        $new_status = $_POST['status'];

        // Validate allowed status transitions
        $allowed_statuses = ['scheduled', 'completed', 'cancelled', 'no_show'];
        if (in_array($new_status, $allowed_statuses)) {
            // Use prepared statement to prevent SQL injection
            $stmt_update = $conn->prepare("
                UPDATE appointments
                SET status = ?
                WHERE id = ? AND doctor_id = ?
            ");
            // Check if prepare() succeeded
            if ($stmt_update) {
                $stmt_update->bind_param("sii", $new_status, $appointment_id, $doctor_id);
                if ($stmt_update->execute()) {
                    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Appointment status updated successfully.'];
                } else {
                    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Failed to update appointment status.'];
                    error_log("Error updating appointment status: " . $stmt_update->error);
                }
                $stmt_update->close();
            } else {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Database error preparing update.'];
                error_log("Error preparing appointment update statement: " . $conn->error);
            }

            // Redirect back to appointments page (with filters if possible, or just clean)
            // Consider preserving filters in redirect URL if needed
            header("Location: appointments.php");
            exit();
        } else {
            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Invalid status selected.'];
            // Redirect back or show error, prevent further processing
            header("Location: appointments.php");
            exit();
        }
    }
    // Handle other potential POST actions here if needed
}

// --- Fetch Appointments ---
// Build SQL query with filters
$query = "
    SELECT a.*, u.name as patient_name, u.phone as patient_phone
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ?
";

$params = [$doctor_id]; // Start with doctor_id
$types = "i";          // Type for doctor_id is integer

// Add filters conditionally
if (!empty($date_filter) && $date_filter !== 'all') {
    $query .= " AND a.appointment_date = ?";
    $params[] = $date_filter;
    $types .= "s"; // Date is treated as string
}

if ($status_filter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= "s"; // Status is string
}

if (!empty($search_query)) {
    $query .= " AND (u.name LIKE ? OR u.phone LIKE ? OR a.reason LIKE ?)";
    $search_term = "%" . $search_query . "%"; // Add wildcards
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss"; // Three strings for search
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

// Initialize appointments array
$appointments = [];

// Execute query using prepared statement
$stmt_select = $conn->prepare($query);
if ($stmt_select) {
    // Dynamically bind parameters
    $stmt_select->bind_param($types, ...$params); // Use splat operator (...)

    if ($stmt_select->execute()) {
        $result = $stmt_select->get_result();
        $appointments = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("Error executing appointment select statement: " . $stmt_select->error);
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Error fetching appointments.'];
    }
    $stmt_select->close();
} else {
    error_log("Error preparing appointment select statement: " . $conn->error);
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Database error preparing selection.'];
}


// --- Fetch Available Dates for Filter ---
$available_dates = [];
$sql_dates = "
    SELECT DISTINCT appointment_date
    FROM appointments
    WHERE doctor_id = ?
    ORDER BY appointment_date DESC
    LIMIT 30
";
$stmt_dates = $conn->prepare($sql_dates);
if ($stmt_dates) {
    $stmt_dates->bind_param("i", $doctor_id);
    if ($stmt_dates->execute()) {
        $result_dates = $stmt_dates->get_result();
        $available_dates = $result_dates->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("Error executing date fetch statement: " . $stmt_dates->error);
    }
    $stmt_dates->close();
} else {
    error_log("Error preparing date fetch statement: " . $conn->error);
}


// Page title and header
$page_title = "My Appointments - Medicare";
// Make sure header is included AFTER all logic/redirects
include __DIR__ . '/../header.php'; // Assuming header is in includes folder
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
        text-transform: capitalize;
        /* Display status nicely */
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

    .badge-default {
        background-color: #adb5bd;
        color: white;
    }

    /* Fallback */


    /* Table styles */
    .appointment-table {
        font-size: 0.9rem;
    }

    .appointment-table th {
        background-color: #f8f9fa;
        position: sticky;
        top: 0;
        /* Adjust based on fixed header height if necessary */
        z-index: 10;
    }

    .appointment-table tr:hover {
        background-color: #f1f1f1;
        /* Slightly different hover */
    }

    /* Action buttons */
    .action-btn-group .btn {
        margin: 0 2px;
        /* Spacing between buttons */
    }

    /* Filter section */
    .filter-card {
        background-color: #f8f9fa;
        border: none;
        border-radius: 10px;
    }

    /* Responsive adjustments for table */
    @media (max-width: 992px) {

        /* Adjust breakpoint if needed */
        .table-responsive thead {
            display: none;
            /* Hide table header */
        }

        .table-responsive tr {
            display: block;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 0.5rem;
        }

        .table-responsive td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #e9ecef;
            text-align: right;
            /* Align value to the right */
        }

        .table-responsive td:last-child {
            border-bottom: 0;
            /* Remove border on last item */
        }

        .table-responsive td::before {
            content: attr(data-label);
            /* Use data-label for heading */
            font-weight: bold;
            margin-right: 1rem;
            text-align: left;
            /* Align label to the left */
            flex-basis: 40%;
            /* Give label some space */
        }

        .action-btn-group {
            flex-basis: 60%;
            /* Give actions space */
            justify-content: flex-end;
            /* Align buttons right */
            display: flex;
        }
    }

    @media (max-width: 576px) {
        .table-responsive td {
            flex-direction: column;
            /* Stack label and value */
            align-items: flex-start;
            /* Align items left */
            text-align: left;
        }

        .table-responsive td::before {
            margin-bottom: 0.25rem;
            flex-basis: auto;
        }

        .action-btn-group {
            width: 100%;
            margin-top: 0.5rem;
            justify-content: flex-start;
            flex-basis: auto;
        }
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <h2 class="h3 mb-2 mb-md-0">My Appointments</h2>
        <a href="schedule.php" class="btn btn-outline-primary">
            <i class="fas fa-calendar-alt me-1"></i> Manage Availability
        </a>
    </div>

    <?php
    // Display flash messages if any
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        echo '<div class="alert alert-' . ($msg['type'] ?? 'info') . ' alert-dismissible fade show" role="alert">' .
            htmlspecialchars($msg['text']) .
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
            '</div>';
        unset($_SESSION['flash_message']);
    }
    ?>

    <div class="card filter-card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6">
                    <label for="date" class="form-label">Date</label>
                    <select id="date" name="date" class="form-select form-select-sm">
                        <option value="all" <?= ($date_filter === 'all' || $date_filter === '') ? 'selected' : '' ?>>All
                            Dates</option>
                        <?php foreach ($available_dates as $date): ?>
                            <option value="<?= $date['appointment_date'] ?>" <?= $date['appointment_date'] === $date_filter ? 'selected' : '' ?>>
                                <?= date('M j, Y', strtotime($date['appointment_date'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 col-sm-6">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select form-select-sm">
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

                <div class="col-md-4 col-sm-8">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control form-control-sm" id="search" name="search"
                        placeholder="Patient name, phone or reason" value="<?= htmlspecialchars($search_query) ?>">
                </div>

                <div class="col-md-2 col-sm-4">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Appointment List</h5>
        </div>
        <div class="card-body p-0"> <?/* Remove padding for table */ ?>
            <?php if (empty($appointments)): ?>
                <div class="alert alert-info m-3">No appointments found matching your criteria.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 appointment-table">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th>Reason</th>
                                <th>Fee (UGX)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appt): ?>
                                <tr>
                                    <td data-label="Date"><?= date('M j, Y', strtotime($appt['appointment_date'])) ?></td>
                                    <td data-label="Time"><?= date('g:i A', strtotime($appt['appointment_time'])) ?></td>
                                    <td data-label="Patient"><?= htmlspecialchars($appt['patient_name']) ?></td>
                                    <td data-label="Contact"><?= htmlspecialchars($appt['patient_phone']) ?></td>
                                    <td data-label="Reason"><?= htmlspecialchars(shortenText($appt['reason'] ?? '', 50)) ?></td>
                                    <td data-label="Fee"><?= number_format($appt['consultation_fee']) ?></td>
                                    <td data-label="Status">
                                        <?php
                                        $status_class = '';
                                        switch ($appt['status']) {
                                            case 'completed':
                                                $status_class = 'badge-completed';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'badge-cancelled';
                                                break;
                                            case 'no_show':
                                                $status_class = 'badge-no_show';
                                                break;
                                            case 'scheduled':
                                                $status_class = 'badge-scheduled';
                                                break;
                                            default:
                                                $status_class = 'badge-default';
                                                break;
                                        }
                                        ?>
                                        <span class="badge status-badge <?= $status_class ?>">
                                            <?= htmlspecialchars($appt['status']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="btn-group btn-group-sm action-btn-group" role="group">
                                            <?php if ($appt['status'] === 'scheduled'): ?>
                                                <a href="consultation.php?id=<?= $appt['id'] ?>" class="btn btn-primary action-btn"
                                                    title="Start Consultation">
                                                    <i class="fas fa-stethoscope"></i>
                                                </a>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-secondary action-btn" data-bs-toggle="modal"
                                                data-bs-target="#statusModal<?= $appt['id'] ?>" title="Change Status">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <a href="patient_details.php?id=<?= $appt['patient_id'] ?>"
                                                class="btn btn-info action-btn" title="View Patient Details">
                                                <i class="fas fa-user"></i>
                                            </a>
                                        </div>

                                        <div class="modal fade" id="statusModal<?= $appt['id'] ?>" tabindex="-1"
                                            aria-labelledby="statusModalLabel<?= $appt['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="appointments.php">
                                                        <?/* Ensure action points here */ ?>
                                                        <input type="hidden" name="appointment_id" value="<?= $appt['id'] ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="statusModalLabel<?= $appt['id'] ?>">
                                                                Update Status for Appt #<?= $appt['id'] ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p><strong>Patient:</strong>
                                                                <?= htmlspecialchars($appt['patient_name']) ?></p>
                                                            <p><strong>Date:</strong>
                                                                <?= date('M j, Y @ g:i A', strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time'])) ?>
                                                            </p>
                                                            <div class="mb-3">
                                                                <label for="status_<?= $appt['id'] ?>" class="form-label">New
                                                                    Status</label>
                                                                <select class="form-select" name="status"
                                                                    id="status_<?= $appt['id'] ?>" required>
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

<?php
// Helper function (if not already in functions.php)
if (!function_exists('shortenText')) {
    function shortenText($text, $maxLength)
    {
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength) . '...';
        }
        return $text;
    }
}

// Include footer AFTER all HTML output
include __DIR__ . '/../footer.php'; // Assuming footer is in includes folder
?>