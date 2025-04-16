<?php
// Verify admin access first
require_once __DIR__ . '/../../admin_functions.php';
adminOnly();

// Validate doctor ID
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    $_SESSION['error'] = "Invalid doctor ID";
    header("Location: " . BASE_URL . "/admin/doctors/approve.php");
    exit();
}

$doctor_id = (int) $_GET['id'];

// Fetch doctor data with JOIN
$stmt = $conn->prepare("SELECT 
    d.*, 
    u.name, u.email, u.phone, u.created_at as user_created, 
    s.name as specialty_name,
    (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id) as appointment_count
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN specialties s ON d.specialty_id = s.id
    WHERE d.id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    $_SESSION['error'] = "Doctor not found";
    header("Location: " . BASE_URL . "/admin/doctors/approve.php");
    exit();
}

// Set page title before including header
$page_title = "Dr. " . htmlspecialchars($doctor['name']) . " Profile";
include __DIR__ . '/../../header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/doctors/approve.php">Doctor Approvals</a></li>
            <li class="breadcrumb-item active" aria-current="page">Profile</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-user-md text-primary me-2"></i>
            Doctor Profile
        </h1>
        <a href="<?= BASE_URL ?>/admin/doctors/approve.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    <!-- Status Alert -->
    <?php if ($doctor['status'] === 'pending'): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="fas fa-clock me-2"></i>
            <strong>Pending Approval</strong> - This doctor's application requires review
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Profile Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <!-- Doctor Avatar -->
                <div class="col-md-3 text-center mb-3 mb-md-0">
                    <div class="avatar-wrapper bg-light p-4 rounded-circle d-inline-block">
                        <i class="fas fa-user-md fa-4x text-primary"></i>
                    </div>
                    <h4 class="mt-3 mb-0">Dr. <?= htmlspecialchars($doctor['name']) ?></h4>
                    <p class="text-muted"><?= htmlspecialchars($doctor['specialty_name'] ?? 'General Practitioner') ?>
                    </p>

                    <div class="badge bg-<?=
                        $doctor['status'] === 'approved' ? 'success' :
                        ($doctor['status'] === 'rejected' ? 'danger' : 'warning')
                        ?>">
                        <?= ucfirst($doctor['status']) ?>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="col-md-9">
                    <div class="row">
                        <div class="col-md-4 border-end">
                            <div class="text-center p-2">
                                <div class="text-muted small">Contact</div>
                                <div><?= htmlspecialchars($doctor['email']) ?></div>
                                <div><?= htmlspecialchars($doctor['phone']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4 border-end">
                            <div class="text-center p-2">
                                <div class="text-muted small">Appointments</div>
                                <div class="h4"><?= $doctor['appointment_count'] ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-2">
                                <div class="text-muted small">Member Since</div>
                                <div><?= date('M j, Y', strtotime($doctor['user_created'])) ?></div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Action Buttons -->
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <?php if ($doctor['status'] === 'pending'): ?>
                            <form method="POST" action="approve.php" class="d-inline">
                                <input type="hidden" name="doctor_id" value="<?= $doctor['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-check me-1"></i> Approve
                                </button>
                            </form>
                            <form method="POST" action="approve.php" class="d-inline">
                                <input type="hidden" name="doctor_id" value="<?= $doctor['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-times me-1"></i> Reject
                                </button>
                            </form>
                        <?php endif; ?>

                        <a href="edit.php?id=<?= $doctor['id'] ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit me-1"></i> Edit
                        </a>

                        <?php if ($doctor['verification_docs']): ?>
                            <a href="<?= BASE_URL ?>/uploads/doctor_docs/<?= htmlspecialchars($doctor['verification_docs']) ?>"
                                target="_blank" class="btn btn-info btn-sm">
                                <i class="fas fa-file-pdf me-1"></i> View Docs
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Information Tabs -->
    <div class="card shadow-sm">
        <div class="card-body">
            <ul class="nav nav-tabs mb-4" id="doctorTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="professional-tab" data-bs-toggle="tab"
                        data-bs-target="#professional" type="button">
                        Professional Info
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="bio-tab" data-bs-toggle="tab" data-bs-target="#bio" type="button">
                        Biography
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity"
                        type="button">
                        Activity
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="doctorTabsContent">
                <!-- Professional Info Tab -->
                <div class="tab-pane fade show active" id="professional" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3"><i class="fas fa-id-card text-primary me-2"></i>Credentials</h5>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th width="40%">License Number</th>
                                    <td><?= htmlspecialchars($doctor['license_number']) ?></td>
                                </tr>
                                <tr>
                                    <th>Specialty</th>
                                    <td><?= htmlspecialchars($doctor['specialty_name'] ?? 'Not specified') ?></td>
                                </tr>
                                <tr>
                                    <th>Consultation Fee</th>
                                    <td>$<?= number_format($doctor['consultation_fee'], 2) ?></td>
                                </tr>
                                <tr>
                                    <th>Availability</th>
                                    <td>
                                        <?= $doctor['available'] ?
                                            '<span class="badge bg-success">Available</span>' :
                                            '<span class="badge bg-secondary">Unavailable</span>' ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5 class="mb-3"><i class="fas fa-graduation-cap text-primary me-2"></i>Qualifications</h5>
                            <?php if (!empty($doctor['qualifications'])): ?>
                                <div class="bg-light p-3 rounded">
                                    <?= nl2br(htmlspecialchars($doctor['qualifications'])) ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    No qualifications provided
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Biography Tab -->
                <div class="tab-pane fade" id="bio" role="tabpanel">
                    <?php if (!empty($doctor['bio'])): ?>
                        <div class="bg-light p-4 rounded">
                            <?= nl2br(htmlspecialchars($doctor['bio'])) ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No biography available
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Activity Tab -->
                <div class="tab-pane fade" id="activity" role="tabpanel">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Appointment history and activity statistics will be displayed here
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../footer.php';
?>