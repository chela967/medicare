<?php
// my_consultations.php - View all doctor communications

// 1. Authentication & Setup
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    header("Location: ../auth.php");
    exit;
}

$patient_id = $_SESSION['user']['id'];
$patient_name = $_SESSION['user']['name'];
$page_title = "My Consultations";
require_once __DIR__ . '/header.php';

// 2. Fetch Consultations with Search/Pagination
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$consultations = [];
$total_consultations = 0;
$unread_count = 0;

try {
    if (!isset($mysqli)) {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($mysqli->connect_error) {
            throw new Exception("Database connection failed: " . $mysqli->connect_error);
        }
    }

    // Count total consultations
    $count_sql = "SELECT COUNT(*) as total 
                  FROM appointments a
                  JOIN doctors doc ON a.doctor_id = doc.id
                  JOIN users d ON doc.user_id = d.id
                  JOIN specialties s ON doc.specialty_id = s.id
                  WHERE a.patient_id = ? 
                  AND a.consultation_notes IS NOT NULL
                  AND (d.name LIKE ? OR s.name LIKE ? OR a.consultation_notes LIKE ?)";

    $stmt = $mysqli->prepare($count_sql);
    $search_param = "%$search%";
    $stmt->bind_param("isss", $patient_id, $search_param, $search_param, $search_param);
    $stmt->execute();
    $total_consultations = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Fetch paginated consultations
    $sql = "SELECT a.id, a.appointment_date, a.appointment_time, 
                   a.consultation_notes, a.notify_patient,
                   d.name as doctor_name, s.name as specialty
            FROM appointments a
            JOIN doctors doc ON a.doctor_id = doc.id
            JOIN users d ON doc.user_id = d.id
            JOIN specialties s ON doc.specialty_id = s.id
            WHERE a.patient_id = ? 
            AND a.consultation_notes IS NOT NULL
            AND (d.name LIKE ? OR s.name LIKE ? OR a.consultation_notes LIKE ?)
            ORDER BY a.appointment_date DESC
            LIMIT ? OFFSET ?";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("isssii", $patient_id, $search_param, $search_param, $search_param, $per_page, $offset);
    $stmt->execute();
    $consultations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Count unread messages
    $unread_sql = "SELECT COUNT(*) as unread 
                   FROM appointments 
                   WHERE patient_id = ? AND notify_patient = 1";
    $stmt = $mysqli->prepare($unread_sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $unread_count = $stmt->get_result()->fetch_assoc()['unread'];
    $stmt->close();

    // Mark as read when viewing all
    if ($page === 1 && empty($search)) {
        $mysqli->query("UPDATE appointments SET notify_patient = 0 WHERE patient_id = $patient_id");
    }

} catch (Exception $e) {
    error_log("Consultations Error: " . $e->getMessage());
    $error = "Could not load consultations. Please try again.";
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">My Consultations</h1>
        <a href="patient_dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Search and Filter Bar -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search"
                            placeholder="Search by doctor, specialty or notes..."
                            value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-end">
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger align-self-center me-3">
                                <?= $unread_count ?> unread
                            </span>
                        <?php endif; ?>
                        <a href="patient_dashboard.php" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i> New Appointment
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Consultations List -->
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (!empty($consultations)): ?>
                <div class="list-group">
                    <?php foreach ($consultations as $consult): ?>
                        <div class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="me-3">
                                    <h5 class="mb-1">Dr. <?= htmlspecialchars($consult['doctor_name']) ?></h5>
                                    <small class="text-muted">
                                        <?= date('M j, Y', strtotime($consult['appointment_date'])) ?> at
                                        <?= date('g:i A', strtotime($consult['appointment_time'])) ?>
                                    </small>
                                    <div class="mt-2">
                                        <span class="badge bg-info">
                                            <?= htmlspecialchars($consult['specialty']) ?>
                                        </span>
                                        <?php if ($consult['notify_patient']): ?>
                                            <span class="badge bg-danger ms-2">New</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="btn-group">
                                    <a href="consultation_details.php?id=<?= $consult['id'] ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                    <button class="btn btn-sm btn-outline-secondary print-notes"
                                        data-notes="<?= htmlspecialchars($consult['consultation_notes']) ?>"
                                        data-doctor="Dr. <?= htmlspecialchars($consult['doctor_name']) ?>"
                                        data-date="<?= date('M j, Y', strtotime($consult['appointment_date'])) ?>">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-2 text-truncate text-muted">
                                <?= htmlspecialchars(substr($consult['consultation_notes'], 0, 100)) ?>...
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= ceil($total_consultations / $per_page); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < ceil($total_consultations / $per_page)): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
                                    Next
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-comment-slash fa-4x text-muted mb-4"></i>
                    <h3>No Consultations Found</h3>
                    <p class="text-muted">
                        <?= empty($search) ? 'You have no consultation notes yet.' : 'No results match your search.' ?>
                    </p>
                    <a href="appointment.php" class="btn btn-primary mt-3">
                        <i class="fas fa-calendar-plus me-1"></i> Book Appointment
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Print Modal (same as dashboard) -->
<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
    <!-- Modal content from dashboard -->
</div>

<script>
    // Print functionality (same as dashboard)
    document.querySelectorAll('.print-notes').forEach(btn => {
        btn.addEventListener('click', function () {
            // Same implementation as dashboard
        });
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>