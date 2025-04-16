<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Authentication and doctor verification


$doctor_id = getDoctorIdByUserId($_SESSION['user']['id']);
if (!$doctor_id) {
    header("Location: " . BASE_URL . "/auth.php");
    exit();
}

// Get filter and search parameters
$search_query = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'last_visit';
$sort_order = $_GET['order'] ?? 'desc';

// Validate sort parameters
$allowed_sorts = ['name', 'last_visit', 'appointment_count'];
$sort_by = in_array($sort_by, $allowed_sorts) ? $sort_by : 'last_visit';
$sort_order = $sort_order === 'asc' ? 'asc' : 'desc';

// Build the SQL query
$query = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.phone,
        u.created_at as joined_date,
        COUNT(a.id) as appointment_count,
        MAX(a.appointment_date) as last_visit,
        GROUP_CONCAT(DISTINCT a.status ORDER BY a.appointment_date DESC SEPARATOR ', ') as statuses
    FROM users u
    JOIN appointments a ON u.id = a.patient_id
    WHERE a.doctor_id = ?
";

$params = [$doctor_id];
$types = "i";

if (!empty($search_query)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search_query%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

$query .= " GROUP BY u.id";
$query .= " ORDER BY $sort_by $sort_order";

// Execute query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Page title and header
$page_title = "My Patients - Medicare";
include '../header.php';
?>

<style>
    /* Patient Dashboard Styles */
    .patient-card {
        border-radius: 10px;
        transition: all 0.3s ease;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }

    .patient-card:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .patient-avatar {
        width: 60px;
        height: 60px;
        background-color: #e9f5ff;
        color: #3a86ff;
        font-size: 24px;
        font-weight: bold;
    }

    .last-visit {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .badge-pill {
        border-radius: 50px;
        padding: 5px 10px;
        font-weight: 500;
    }

    /* Responsive table */
    @media (max-width: 768px) {
        .table-responsive td {
            padding: 0.75rem 0.5rem;
        }

        .patient-actions {
            flex-wrap: wrap;
        }

        .patient-actions .btn {
            margin-bottom: 5px;
        }
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Patients</h2>
        <div class="d-flex">
            <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#exportModal">
                <i class="fas fa-download me-1"></i> Export
            </button>
            <div class="input-group" style="width: 250px;">
                <input type="text" id="searchInput" class="form-control" placeholder="Search patients..."
                    value="<?= htmlspecialchars($search_query) ?>">
                <button class="btn btn-primary" id="searchButton">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Patients</h5>
                    <h2 class="card-text"><?= count($patients) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Active Patients</h5>
                    <h2 class="card-text">
                        <?= count(array_filter($patients, fn($p) => strtotime($p['last_visit']) > strtotime('-30 days'))) ?>
                    </h2>
                    <small>Seen in last 30 days</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Avg. Visits</h5>
                    <h2 class="card-text">
                        <?= count($patients) ? round(array_sum(array_column($patients, 'appointment_count')) / count($patients), 1) : 0 ?>
                    </h2>
                    <small>Per patient</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Patients Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($patients)): ?>
                <div class="alert alert-info">No patients found. You'll see patients here after they book appointments with
                    you.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>
                                    <a
                                        href="?sort=name&order=<?= $sort_by === 'name' && $sort_order === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_query) ?>">
                                        Patient
                                        <?php if ($sort_by === 'name'): ?>
                                            <i class="fas fa-sort-<?= $sort_order === 'asc' ? 'up' : 'down' ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Contact</th>
                                <th class="text-center">
                                    <a
                                        href="?sort=appointment_count&order=<?= $sort_by === 'appointment_count' && $sort_order === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_query) ?>">
                                        Visits
                                        <?php if ($sort_by === 'appointment_count'): ?>
                                            <i class="fas fa-sort-<?= $sort_order === 'asc' ? 'up' : 'down' ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a
                                        href="?sort=last_visit&order=<?= $sort_by === 'last_visit' && $sort_order === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_query) ?>">
                                        Last Visit
                                        <?php if ($sort_by === 'last_visit'): ?>
                                            <i class="fas fa-sort-<?= $sort_order === 'asc' ? 'up' : 'down' ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                                <tr class="patient-card">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="patient-avatar rounded-circle d-flex align-items-center justify-content-center me-3">
                                                <?= strtoupper(substr($patient['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($patient['name']) ?></strong>
                                                <div class="text-muted small">Joined:
                                                    <?= date('M Y', strtotime($patient['joined_date'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($patient['email']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($patient['phone']) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary rounded-pill"><?= $patient['appointment_count'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($patient['last_visit']): ?>
                                            <div><?= date('M j, Y', strtotime($patient['last_visit'])) ?></div>
                                            <div class="last-visit">
                                                <?= round((time() - strtotime($patient['last_visit'])) / (60 * 60 * 24)) ?> days ago
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (strpos($patient['statuses'], 'completed') !== false): ?>
                                            <span class="badge bg-success badge-pill">Active</span>
                                        <?php elseif (strpos($patient['statuses'], 'scheduled') !== false): ?>
                                            <span class="badge bg-info badge-pill">Upcoming</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary badge-pill">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end patient-actions">
                                        <a href="patient.php?id=<?= $patient['id'] ?>"
                                            class="btn btn-sm btn-outline-primary me-1">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        <a href="appointments.php?patient_id=<?= $patient['id'] ?>"
                                            class="btn btn-sm btn-outline-secondary me-1">
                                            <i class="fas fa-calendar me-1"></i> History
                                        </a>
                                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal"
                                            data-bs-target="#messageModal<?= $patient['id'] ?>">
                                            <i class="fas fa-envelope me-1"></i> Message
                                        </button>
                                    </td>
                                </tr>

                                <!-- Message Modal -->
                                <div class="modal fade" id="messageModal<?= $patient['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Message <?= htmlspecialchars($patient['name']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="send_message.php" method="post">
                                                <input type="hidden" name="patient_id" value="<?= $patient['id'] ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Subject</label>
                                                        <input type="text" class="form-control" name="subject" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Message</label>
                                                        <textarea class="form-control" rows="5" name="message"
                                                            required></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Send Message</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export Patient Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="export_patients.php" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Format</label>
                        <select class="form-select" name="format" required>
                            <option value="csv">CSV (Excel)</option>
                            <option value="pdf">PDF Document</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Include</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include[]" value="contact"
                                id="includeContact" checked>
                            <label class="form-check-label" for="includeContact">Contact Information</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include[]" value="appointments"
                                id="includeAppointments" checked>
                            <label class="form-check-label" for="includeAppointments">Appointment History</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Export Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Simple search functionality
    document.getElementById('searchButton').addEventListener('click', function () {
        const searchValue = document.getElementById('searchInput').value;
        window.location.href = `patients.php?search=${encodeURIComponent(searchValue)}`;
    });

    document.getElementById('searchInput').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            document.getElementById('searchButton').click();
        }
    });
</script>

<?php include '../footer.php'; ?>