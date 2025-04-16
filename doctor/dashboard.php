<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
    header("Location: ../auth.php");
    exit();
}

// Verify doctor is approved
require_once '../functions.php';
$doctor = getDoctorData($_SESSION['user']['id']);
if (!$doctor || $doctor['status'] !== 'approved') {
    header("Location: pending_approval.php");
    exit();
}
// Check if user is logged in and is a doctor
if (!isset($_SESSION['user'])) {
    header("Location: ../auth.php");
    exit();
}

// Get doctor details
$doctor_id = getDoctorIdByUserId($_SESSION['user']['id']);
if (!$doctor_id) {
    header("Location: ../auth.php");
    exit();
}

$page_title = "Doctor Dashboard - Medicare";
include '../header.php';

// Get doctor-specific data
$doctor = getDoctorDetails($doctor_id);
$today_appointments = getDoctorAppointments($doctor_id, date('Y-m-d'));
$upcoming_appointments = getUpcomingAppointments($doctor_id, 5);
$patient_count = getDoctorPatientCount($doctor_id);
?>
<?php
// Check for approval notification
$doctor_id = getDoctorIdByUserId($_SESSION['user']['id']);
$doctor = getDoctorDetails($doctor_id);

if ($doctor['status'] === 'approved' && !isset($_SESSION['seen_approval_notice'])) {
    echo '<div class="alert alert-success alert-dismissible fade show">
            <h4><i class="fas fa-check-circle"></i> Account Approved!</h4>
            <p>Your doctor account has been approved. You now have full access to all features.</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    $_SESSION['seen_approval_notice'] = true;
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 d-md-block bg-primary sidebar collapse">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <div class="user-avatar-lg mb-3 mx-auto bg-white text-primary">
                        <?php echo strtoupper(substr($_SESSION['user']['name'], 0, 1)); ?>
                    </div>
                    <h5 class="text-white">Dr. <?php echo htmlspecialchars($_SESSION['user']['name']); ?></h5>
                    <span
                        class="badge bg-light text-primary"><?php echo htmlspecialchars($doctor['specialty']); ?></span>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active text-white" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="appointments.php">
                            <i class="fas fa-calendar-check me-2"></i> Appointments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="patients.php">
                            <i class="fas fa-users me-2"></i> My Patients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="schedule.php">
                            <i class="fas fa-calendar-alt me-2"></i> Schedule
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="prescriptions.php">
                            <i class="fas fa-prescription me-2"></i> Prescriptions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="profile.php">
                            <i class="fas fa-user-cog me-2"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <a class="nav-link text-white" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div
                class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Doctor Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-calendar-plus me-1"></i> New Appointment
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Today's Appointments</h5>
                            <h2 class="card-text"><?php echo count($today_appointments); ?></h2>
                            <a href="appointments.php?date=<?php echo date('Y-m-d'); ?>" class="text-white">View
                                Today</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Patients</h5>
                            <h2 class="card-text"><?php echo $patient_count; ?></h2>
                            <a href="patients.php" class="text-white">View All</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Consultation Fee</h5>
                            <h2 class="card-text">UGX <?php echo number_format($doctor['consultation_fee']); ?></h2>
                            <a href="profile.php#fees" class="text-white">Update Fee</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Appointments -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5>Today's Appointments (<?php echo date('l, F j, Y'); ?>)</h5>
                        <a href="appointments.php?date=<?php echo date('Y-m-d'); ?>"
                            class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($today_appointments)): ?>
                        <div class="alert alert-info">No appointments scheduled for today.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Patient</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($today_appointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></td>
                                            <td>
                                                <a href="patient.php?id=<?php echo $appointment['patient_id']; ?>">
                                                    <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['reason']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php
                                                echo $appointment['status'] == 'completed' ? 'success' :
                                                    ($appointment['status'] == 'cancelled' ? 'danger' : 'warning');
                                                ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="consultation.php?id=<?php echo $appointment['id']; ?>"
                                                        class="btn btn-sm btn-primary">
                                                        <i class="fas fa-stethoscope"></i> Consult
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                                        data-bs-target="#notesModal<?php echo $appointment['id']; ?>">
                                                        <i class="fas fa-notes-medical"></i>
                                                    </button>
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

            <!-- Upcoming Appointments -->
            <div class="card">
                <div class="card-header">
                    <h5>Upcoming Appointments</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_appointments)): ?>
                        <div class="alert alert-info">No upcoming appointments.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Patient</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_appointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></td>
                                            <td><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                            <td><?php echo shortenText(htmlspecialchars($appointment['reason']), 30); ?></td>
                                            <td>
                                                <a href="appointment.php?id=<?php echo $appointment['id']; ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    View
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
        </main>
    </div>
</div>

<?php include '../footer.php'; ?>