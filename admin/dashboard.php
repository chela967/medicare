<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: /medicare/auth.php");
    exit();
}

$page_title = "Admin Dashboard - Medicare";
include '../header.php';

// Get statistics for dashboard
$users_count = getUsersCount();
$doctors_count = getDoctorsCount();
$appointments_count = getAppointmentsCount();
$pending_doctors = getPendingDoctors();
$recent_appointments = getRecentAppointments(5);
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <div class="user-avatar-lg mb-3 mx-auto bg-primary">
                        <?php echo strtoupper(substr($_SESSION['user']['name'], 0, 1)); ?>
                    </div>
                    <h5 class="text-white"><?php echo htmlspecialchars($_SESSION['user']['name']); ?></h5>
                    <span class="badge bg-success">Administrator</span>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="doctors.php">
                            <i class="fas fa-user-md me-2"></i> Manage Doctors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="patients.php">
                            <i class="fas fa-users me-2"></i> Manage Patients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="appointments.php">
                            <i class="fas fa-calendar-check me-2"></i> Appointments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="medicines.php">
                            <i class="fas fa-pills me-2"></i> Medicines
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-2"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <a class="nav-link text-danger" href="../logout.php">
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
                <h1 class="h2">Admin Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-sync-alt me-1"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Users</h5>
                            <h2 class="card-text"><?php echo $users_count; ?></h2>
                            <a href="patients.php" class="text-white">View All</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Doctors</h5>
                            <h2 class="card-text"><?php echo $doctors_count; ?></h2>
                            <a href="doctors.php" class="text-white">Manage</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Appointments</h5>
                            <h2 class="card-text"><?php echo $appointments_count; ?></h2>
                            <a href="appointments.php" class="text-white">View All</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h5 class="card-title">Pending Approvals</h5>
                            <h2 class="card-text"><?php echo count($pending_doctors); ?></h2>
                            <a href="pending_doctors.php?filter=pending" class="text-dark">Review</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Appointments -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Recent Appointments</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo $appointment['id']; ?></td>
                                        <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php
                                            echo $appointment['status'] == 'completed' ? 'success' :
                                                ($appointment['status'] == 'cancelled' ? 'danger' : 'warning');
                                            ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="appointment.php?id=<?php echo $appointment['id']; ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pending Doctor Approvals -->
            <?php if (!empty($pending_doctors)): ?>
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5>Pending Doctor Approvals</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Specialty</th>
                                        <th>License</th>
                                        <th>Applied On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_doctors as $doctor): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($doctor['name']); ?></td>
                                            <td><?php echo htmlspecialchars($doctor['specialty']); ?></td>
                                            <td><?php echo htmlspecialchars($doctor['license_number']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($doctor['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="approve_doctor.php?id=<?php echo $doctor['id']; ?>"
                                                        class="btn btn-sm btn-success">
                                                        <i class="fas fa-check"></i> Approve
                                                    </a>
                                                    <a href="reject_doctor.php?id=<?php echo $doctor['id']; ?>"
                                                        class="btn btn-sm btn-danger">
                                                        <i class="fas fa-times"></i> Reject
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../footer.php'; ?>