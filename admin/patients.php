<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../auth.php");
    exit();
}

$page_title = "Manage Patients - Medicare";
include '../header.php';
require_once '../functions.php';

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$patients = $search ? searchPatients($search) : getAllPatients();

// Handle patient deletion
if (isset($_GET['delete'])) {
    $patient_id = intval($_GET['delete']);
    if (deletePatient($patient_id)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Patient deleted successfully'];
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to delete patient'];
    }
    header("Location: patients.php");
    exit();
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Admin Sidebar -->
        <div class="col-md-3 col-lg-2 d-md-block bg-primary sidebar collapse">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <div class="user-avatar-lg mb-3 mx-auto bg-white text-primary">
                        <?php echo strtoupper(substr($_SESSION['user']['name'], 0, 1)); ?>
                    </div>
                    <h5 class="text-white"><?php echo htmlspecialchars($_SESSION['user']['name']); ?></h5>
                    <span class="badge bg-light text-primary">Administrator</span>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active text-white" href="patients.php">
                            <i class="fas fa-users me-2"></i> Patients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="doctors.php">
                            <i class="fas fa-user-md me-2"></i> Doctors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="appointments.php">
                            <i class="fas fa-calendar-check me-2"></i> Appointments
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
                <h1 class="h2">Manage Patients</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="add_patient.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i> Add New Patient
                    </a>
                </div>
            </div>

            <!-- Display messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message']['type']; ?> alert-dismissible fade show">
                    <?php echo $_SESSION['message']['text']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <!-- Search and Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-8">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Search patients..."
                                    value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="patients.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" name="status">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo isset($_GET['status']) && $_GET['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Patients Table -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Patient Records</h5>
                        <span class="badge bg-primary">Total: <?php echo count($patients); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($patients)): ?>
                        <div class="alert alert-info">No patients found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patients as $patient): ?>
                                        <tr>
                                            <td><?php echo $patient['id']; ?></td>
                                            <td>
                                                <a href="patient_details.php?id=<?php echo $patient['id']; ?>">
                                                    <?php echo htmlspecialchars($patient['name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                            <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                            <td>
                                                <span
                                                    class="badge bg-<?php echo $patient['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($patient['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($patient['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit_patient.php?id=<?php echo $patient['id']; ?>"
                                                        class="btn btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="patient_details.php?id=<?php echo $patient['id']; ?>"
                                                        class="btn btn-outline-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="patients.php?delete=<?php echo $patient['id']; ?>"
                                                        class="btn btn-outline-danger"
                                                        onclick="return confirm('Are you sure you want to delete this patient?');">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
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
        </main>
    </div>
</div>

<?php include '../footer.php'; ?>