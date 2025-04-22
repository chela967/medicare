<?php
session_start();
require_once '../config.php';    // Creates $conn
require_once '../functions.php'; // Defines functions

// --- Security Checks ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
    header("Location: ../auth.php"); // Adjust path if needed
    exit();
}

// --- Get Logged-in Doctor's ID ---
$logged_in_user_id = $_SESSION['user']['id'];
$logged_in_doctor_id = getDoctorIdByUserId($logged_in_user_id, $conn);

if (!$logged_in_doctor_id) {
    $_SESSION['error_message'] = "Could not verify doctor identity.";
    header("Location: dashboard.php");
    exit();
}

$error_message = '';
$success_message = '';

// --- Handle Form Submissions (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Nonce/CSRF Check (Recommended) ---

    // Determine action (add or delete)
    $action = $_POST['action'] ?? '';

    if ($action === 'add_schedule') {
        $day = $_POST['day_of_week'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';

        // Basic Validation (Server-side)
        if (empty($day) || empty($start_time) || empty($end_time)) {
            $_SESSION['error_message'] = "Please fill in all fields for the new schedule slot.";
        } elseif (strtotime($end_time) <= strtotime($start_time)) {
            $_SESSION['error_message'] = "End time must be after start time.";
        } else {
            if (addDoctorSchedule($logged_in_doctor_id, $day, $start_time, $end_time, $conn)) {
                $_SESSION['success_message'] = "Schedule slot added successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to add schedule slot. Please check times and try again.";
            }
        }
        // Redirect to prevent form resubmission
        header("Location: schedule.php");
        exit();

    } elseif ($action === 'delete_schedule') {
        $schedule_id_to_delete = filter_var($_POST['schedule_id'] ?? '', FILTER_VALIDATE_INT);

        if ($schedule_id_to_delete) {
            if (deleteDoctorSchedule($schedule_id_to_delete, $logged_in_doctor_id, $conn)) {
                $_SESSION['success_message'] = "Schedule slot deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to delete schedule slot. It might have already been deleted or does not belong to you.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid schedule ID for deletion.";
        }
        // Redirect to prevent form resubmission
        header("Location: schedule.php");
        exit();
    }
    // --- Add logic for 'update_schedule' if implementing edit ---
}


// --- Fetch Current Schedule Data (for GET request) ---
$schedule_data = getDoctorSchedule($logged_in_doctor_id, $conn);

// Group schedule by day for easier display
$grouped_schedule = [];
foreach ($schedule_data as $slot) {
    $grouped_schedule[$slot['day_of_week']][] = $slot;
}
$days_of_week = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];


// Handle session messages from redirects
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}


// --- Set Page Title & Include Header ---
$page_title = "My Schedule - Medicare";
include '../header.php';

?>

<div class="container-fluid py-4">
    <div class="row">
        <?php include '_doctor_sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content-area">
            <div
                class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">My Weekly Schedule</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                    <i class="fas fa-plus me-1"></i> Add New Time Slot
                </button>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Current Availability</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($grouped_schedule)): ?>
                        <div class="alert alert-info">Your schedule is currently empty. Add time slots using the button
                            above.</div>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-lg-2 g-3">
                            <?php foreach ($days_of_week as $day): ?>
                                <div class="col">
                                    <div class="card h-100">
                                        <div class="card-header fw-bold text-capitalize"><?php echo $day; ?></div>
                                        <ul class="list-group list-group-flush">
                                            <?php if (isset($grouped_schedule[$day]) && !empty($grouped_schedule[$day])): ?>
                                                <?php foreach ($grouped_schedule[$day] as $slot): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span>
                                                            <i class="fas fa-clock me-2 text-muted"></i>
                                                            <?php echo $slot['start_time_formatted']; ?> -
                                                            <?php echo $slot['end_time_formatted']; ?>
                                                            <?php if (!$slot['is_available']): ?>
                                                                <span class="badge bg-secondary ms-2">Unavailable</span>
                                                            <?php endif; ?>
                                                        </span>
                                                        <span>
                                                            <button class="btn btn-sm btn-outline-secondary me-1 disabled"
                                                                title="Edit (Not Implemented)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form action="schedule.php" method="post" class="d-inline"
                                                                onsubmit="return confirm('Are you sure you want to delete this time slot?');">
                                                                <input type="hidden" name="action" value="delete_schedule">
                                                                <input type="hidden" name="schedule_id"
                                                                    value="<?php echo $slot['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                    title="Delete">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            </form>
                                                        </span>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <li class="list-group-item text-muted fst-italic">No slots scheduled.</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>


<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-labelledby="addScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addScheduleModalLabel">Add New Time Slot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="schedule.php" method="post">
                <input type="hidden" name="action" value="add_schedule">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="day_of_week" class="form-label">Day of Week <span
                                class="text-danger">*</span></label>
                        <select class="form-select" id="day_of_week" name="day_of_week" required>
                            <option value="">Select Day...</option>
                            <option value="monday">Monday</option>
                            <option value="tuesday">Tuesday</option>
                            <option value="wednesday">Wednesday</option>
                            <option value="thursday">Thursday</option>
                            <option value="friday">Friday</option>
                            <option value="saturday">Saturday</option>
                            <option value="sunday">Sunday</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_time" class="form-label">Start Time <span
                                    class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                        </div>
                    </div>
                    <div class="form-text">Ensure end time is after start time.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Slot</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php include '../footer.php'; // Include footer ?>