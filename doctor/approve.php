<?php
require_once '../../admin_functions.php';
adminOnly();

$page_title = "Doctor Approvals - Medicare";

// Fetch pending doctors
$pending_doctors = [];
$stmt = $conn->prepare("SELECT d.id, u.name, u.email, d.license_number, s.name as specialty 
                       FROM doctors d
                       JOIN users u ON d.user_id = u.id
                       LEFT JOIN specialties s ON d.specialty_id = s.id
                       WHERE d.status = 'pending'");
$stmt->execute();
$result = $stmt->get_result();
$pending_doctors = $result->fetch_all(MYSQLI_ASSOC);

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = (int) $_POST['doctor_id'];
    $action = $_POST['action'];

    if (in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE doctors SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $doctor_id);

        if ($stmt->execute()) {
            // In a real system, send email notification here
            $_SESSION['success'] = "Doctor $status successfully";
            header("Location: approve.php");
            exit();
        }
    }
}

include '../../header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Doctor Approvals</h2>
        <a href="../dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($pending_doctors)): ?>
                <div class="alert alert-info">No pending doctor registrations</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>License</th>
                                <th>Specialty</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_doctors as $doctor): ?>
                                <tr>
                                    <td><?= htmlspecialchars($doctor['name']) ?></td>
                                    <td><?= htmlspecialchars($doctor['email']) ?></td>
                                    <td><?= htmlspecialchars($doctor['license_number']) ?></td>
                                    <td><?= htmlspecialchars($doctor['specialty'] ?? 'N/A') ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="doctor_id" value="<?= $doctor['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="doctor_id" value="<?= $doctor['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                            <a href="view.php?id=<?= $doctor['id'] ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
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
</div>

<?php include '../../footer.php'; ?>