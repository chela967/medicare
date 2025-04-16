<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Admin authentication check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: /medicare/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: pending_doctors.php");
    exit();
}

$doctor_id = (int) $_GET['id'];
$doctor = null;

try {
    $stmt = $conn->prepare("
        SELECT d.*, u.name, u.email, u.phone, s.name AS specialty
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        JOIN specialties s ON d.specialty_id = s.id
        WHERE d.id = ?
    ");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor = $result->fetch_assoc();
} catch (Exception $e) {
    error_log("Error fetching doctor details: " . $e->getMessage());
    $_SESSION['error'] = "Error loading doctor details";
    header("Location: pending_doctors.php");
    exit();
}

if (!$doctor) {
    $_SESSION['error'] = "Doctor not found";
    header("Location: pending_doctors.php");
    exit();
}

$page_title = "View Doctor: " . htmlspecialchars($doctor['name']);
include __DIR__ . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Doctor Details</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Personal Information</h5>
                            <p><strong>Name:</strong> <?= htmlspecialchars($doctor['name']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($doctor['email']) ?></p>
                            <p><strong>Phone:</strong> <?= htmlspecialchars($doctor['phone']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Professional Information</h5>
                            <p><strong>Specialty:</strong> <?= htmlspecialchars($doctor['specialty']) ?></p>
                            <p><strong>License Number:</strong> <?= htmlspecialchars($doctor['license_number']) ?></p>
                            <p><strong>Status:</strong>
                                <span class="badge bg-<?= $doctor['status'] === 'approved' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($doctor['status']) ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <h5>Qualifications</h5>
                            <div class="border p-3 bg-light">
                                <?= !empty($doctor['qualifications']) ? nl2br(htmlspecialchars($doctor['qualifications'])) : 'Not specified' ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($doctor['verification_docs'])): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <h5>Verification Documents</h5>
                                <div class="border p-3 bg-light">
                                    <a href="/medicare/uploads/doctor_docs/<?= htmlspecialchars($doctor['verification_docs']) ?>"
                                        target="_blank" class="btn btn-primary">
                                        <i class="fas fa-file-pdf"></i> View Document
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row mt-4">
                        <div class="col-12 d-flex justify-content-between">
                            <a href="pending_doctors.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                            <div>
                                <?php if ($doctor['status'] === 'pending'): ?>
                                    <a href="/medicare/admin/approve_doctor.php?id=<?= $doctor['id'] ?>"
                                        class="btn btn-success">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="/medicare/admin/reject_doctor.php?id=<?= $doctor['id'] ?>"
                                        class="btn btn-danger">
                                        <i class="fas fa-times"></i> Reject
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>