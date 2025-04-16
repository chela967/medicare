<?php
$page_title = "Pending Doctor Approvals";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Admin authentication
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: " . BASE_URL . "../auth.php");
    exit();
}

// Fetch pending doctors
$pending_doctors = [];
try {
    $stmt = $conn->prepare("
        SELECT d.id, d.user_id, d.license_number, d.qualifications, d.verification_docs,
               u.name, u.email, u.phone, s.name AS specialty
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        JOIN specialties s ON d.specialty_id = s.id
        WHERE d.status = 'pending'
        ORDER BY d.created_at DESC
    ");
    $stmt->execute();
    $pending_doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error fetching pending doctors: " . $e->getMessage();
}

require_once __DIR__ . '/../header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-user-clock me-2"></i>Pending Doctor Approvals</h2>
        <a href="<?= ADMIN_BASE ?>/dashboard.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <?php if (empty($pending_doctors)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> No pending doctor approvals at this time.
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Specialty</th>
                                <th>License No.</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_doctors as $doctor): ?>
                                <tr>
                                    <td><?= htmlspecialchars($doctor['name']) ?></td>
                                    <td><?= htmlspecialchars($doctor['email']) ?></td>
                                    <td><?= htmlspecialchars($doctor['specialty']) ?></td>
                                    <td><?= htmlspecialchars($doctor['license_number']) ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="<?= ADMIN_BASE ?>/view_doctor.php?id=<?= $doctor['id'] ?>"
                                                class="btn btn-sm btn-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?= ADMIN_BASE ?>/approve_doctor.php?id=<?= $doctor['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                                                class="btn btn-sm btn-success" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="<?= ADMIN_BASE ?>/reject_doctor.php?id=<?= $doctor['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                                                class="btn btn-sm btn-danger" title="Reject">
                                                <i class="fas fa-times"></i>
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
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>