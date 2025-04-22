<?php
// 1. Start the session FIRST
session_start();

// 2. Include configuration and functions
// Ensure these paths are correct from the 'doctor' directory
require_once __DIR__ . '/../config.php';    // Creates $conn and potentially BASE_URL
require_once __DIR__ . '/../functions.php'; // Defines getDoctorIdByUserId etc.

// 3. Authentication and Role Check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
    // Redirect to login if not logged in as a doctor
    // Use BASE_URL if defined in config.php, otherwise use relative path
    $redirect_url = defined('BASE_URL') ? BASE_URL . "/auth.php" : "../auth.php";
    header("Location: " . $redirect_url);
    exit();
}

// 4. Get Logged-in Doctor's ID (passing $conn)
$logged_in_user_id = $_SESSION['user']['id'];
$doctor_id = getDoctorIdByUserId($logged_in_user_id, $conn); // Pass $conn

// 5. !! IMPORTANT: Check if Doctor ID was found !!
if (!$doctor_id) {
    // Handle the error: Doctor ID lookup failed.
    error_log("Patients Page Error: Could not retrieve doctor ID for user ID: " . $logged_in_user_id);
    // Set a session message and redirect, or die with an error
    $_SESSION['error_message'] = "Error: Unable to load doctor information.";
    header("Location: dashboard.php"); // Redirect to dashboard or an error page
    exit();
}

// Get filter and search parameters
$search_query = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'last_visit'; // Default sort
$sort_order = $_GET['order'] ?? 'desc'; // Default order

// Validate sort parameters to prevent SQL injection via column names
$allowed_sort_columns = ['name', 'last_visit', 'appointment_count', 'joined_date']; // Whitelist allowed columns
$sort_column_map = [ // Map query param to actual SQL column/alias
    'name' => 'u.name',
    'last_visit' => 'last_visit',
    'appointment_count' => 'appointment_count',
    'joined_date' => 'joined_date'
];
$sort_by_sql = isset($sort_column_map[$sort_by]) ? $sort_column_map[$sort_by] : 'last_visit'; // Default if invalid
$sort_order_sql = strtolower($sort_order) === 'asc' ? 'ASC' : 'DESC'; // Validate sort order


// Build the SQL query (Your existing logic seems good)
$query = "
    SELECT
        u.id,
        u.name,
        u.email,
        u.phone,
        u.created_at as joined_date,
        COUNT(a.id) as appointment_count,
        MAX(a.appointment_date) as last_visit
        /* Removed GROUP_CONCAT for simplicity in this example,
           re-add if needed, but the logic below doesn't use 'statuses' field */
    FROM users u
    JOIN appointments a ON u.id = a.patient_id
    WHERE a.doctor_id = ?
";

$params = [$doctor_id];
$types = "i";

// Add search condition
if (!empty($search_query)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_term = "%" . $search_query . "%"; // Add wildcards here
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

$query .= " GROUP BY u.id"; // Group by user ID to get per-patient stats
$query .= " ORDER BY " . $sort_by_sql . " " . $sort_order_sql; // Use validated sort parameters

// Execute query (Your existing logic seems correct)
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("MySQLi prepare failed in patients.php: (" . $conn->errno . ") " . $conn->error);
    die("Error preparing patient query."); // Or handle more gracefully
}

// Use splat operator (...) for variable number of parameters
if (!$stmt->bind_param($types, ...$params)) {
    error_log("MySQLi bind_param failed in patients.php: (" . $stmt->errno . ") " . $stmt->error);
    die("Error binding parameters.");
}

if (!$stmt->execute()) {
    error_log("MySQLi execute failed in patients.php: (" . $stmt->errno . ") " . $stmt->error);
    die("Error executing query.");
}

$result = $stmt->get_result();
if (!$result) {
    error_log("MySQLi get_result failed in patients.php: (" . $stmt->errno . ") " . $stmt->error);
    die("Error getting results.");
}

$patients = $result->fetch_all(MYSQLI_ASSOC); // Fetch all results
$stmt->close();


// --- Page title and header ---
$page_title = "My Patients - Medicare";
// Note: header.php should ideally be included AFTER all data fetching and logic,
// unless it contains essential setup needed beforehand.
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
        /* Applied to table rows now */
        background-color: #f8f9fa;
        /* Lighter background on hover */
    }

    .patient-avatar {
        width: 50px;
        /* Adjusted size */
        height: 50px;
        background-color: #e9f5ff;
        color: #0d6efd;
        /* Updated primary color */
        font-size: 20px;
        /* Adjusted size */
        font-weight: bold;
    }

    .last-visit {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .badge-pill {
        /* Bootstrap 5 uses rounded-pill */
        padding: 0.35em 0.65em;
        font-weight: 500;
    }

    /* Responsive table */
    @media (max-width: 768px) {

        .table-responsive td,
        .table-responsive th {
            /* Apply to th too */
            padding: 0.75rem 0.5rem;
            font-size: 0.9rem;
            /* Adjust font size */
        }

        .patient-actions {
            display: flex;
            /* Use flex for better control */
            flex-direction: column;
            /* Stack buttons vertically */
            align-items: flex-end;
            /* Align to the right */
        }

        .patient-actions .btn {
            margin-bottom: 5px;
            width: 100%;
            /* Make buttons full width on small screens */
            text-align: center;
        }

        .patient-avatar {
            /* Smaller avatar on mobile */
            width: 40px;
            height: 40px;
            font-size: 16px;
            margin-right: 0.5rem !important;
            /* Adjust spacing */
        }
    }
</style>

<div class="container-fluid py-4">
    <div class="row">
        <?php // include '_doctor_sidebar.php'; ?>
        <main class="col-md-12 ms-sm-auto col-lg-12 px-md-4">
            <div
                class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2">My Patients</h1>
                <div class="d-flex flex-wrap"> <button class="btn btn-sm btn-outline-secondary me-2 mb-2"
                        data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="fas fa-download me-1"></i> Export List
                    </button>
                    <form method="get" action="patients.php" class="d-flex mb-2" id="searchForm">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_by) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order) ?>">
                        <div class="input-group" style="max-width: 300px;">
                            <input type="search" id="searchInput" name="search" class="form-control form-control-sm"
                                placeholder="Search name, email, phone..."
                                value="<?= htmlspecialchars($search_query) ?>" aria-label="Search patients">
                            <button class="btn btn-sm btn-outline-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php // Display feedback messages
            if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card text-bg-primary h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-users me-2"></i>Total Patients</h5>
                            <h2 class="card-text display-6"><?= count($patients) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card text-bg-success h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-user-clock me-2"></i>Active Patients</h5>
                            <h2 class="card-text display-6">
                                <?php
                                // Filter patients seen in the last 30 days
                                $active_patients = array_filter($patients, function ($p) {
                                    // Ensure last_visit is not null before comparing
                                    return !empty($p['last_visit']) && strtotime($p['last_visit']) > strtotime('-30 days');
                                });
                                echo count($active_patients);
                                ?>
                            </h2>
                            <small>Seen in last 30 days</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card text-bg-info h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-calendar-check me-2"></i>Avg. Visits</h5>
                            <h2 class="card-text display-6">
                                <?php
                                // Calculate average visits per patient
                                $total_visits = array_sum(array_column($patients, 'appointment_count'));
                                $total_patients_count = count($patients);
                                echo $total_patients_count > 0 ? round($total_visits / $total_patients_count, 1) : 0;
                                ?>
                            </h2>
                            <small>Total visits / total patients</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Patient Records</h5>
                    <span class="text-muted small">Showing <?= count($patients) ?> results</span>
                </div>
                <div class="card-body p-0"> <?php if (empty($patients)): ?>
                        <div class="alert alert-info m-3">
                            <?php if (!empty($search_query)): ?>
                                No patients found matching your search criteria
                                "<strong><?= htmlspecialchars($search_query) ?></strong>".
                            <?php else: ?>
                                No patients found. Patients appear here after their first appointment with you.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <?php
                                        function renderSortLink($current_sort, $current_order, $sort_key, $label, $search_query)
                                        {
                                            $order = ($current_sort === $sort_key && $current_order === 'asc') ? 'desc' : 'asc';
                                            $icon = '';
                                            if ($current_sort === $sort_key) {
                                                $icon = $current_order === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
                                            }
                                            $url = "?sort=$sort_key&order=$order&search=" . urlencode($search_query);
                                            echo "<th><a href=\"$url\" class=\"text-decoration-none text-dark\">$label";
                                            if ($icon)
                                                echo " <i class=\"fas $icon ms-1\"></i>";
                                            echo "</a></th>";
                                        }
                                        renderSortLink($sort_by, $sort_order, 'name', 'Patient', $search_query);
                                        ?>
                                        <th>Contact</th>
                                        <?php renderSortLink($sort_by, $sort_order, 'appointment_count', 'Visits', $search_query); ?>
                                        <?php renderSortLink($sort_by, $sort_order, 'last_visit', 'Last Visit', $search_query); ?>
                                        <?php renderSortLink($sort_by, $sort_order, 'joined_date', 'Joined', $search_query); ?>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patients as $patient): ?>
                                        <tr class="patient-row">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div
                                                        class="patient-avatar rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0">
                                                        <?= htmlspecialchars(strtoupper(substr($patient['name'], 0, 1))) ?>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($patient['name']) ?></strong>
                                                        <div class="text-muted small d-block d-md-none">ID:
                                                            <?= $patient['id'] ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></div>
                                                <div class="text-muted small">
                                                    <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></div>
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="badge bg-secondary rounded-pill"><?= $patient['appointment_count'] ?></span>
                                            </td>
                                            <td>
                                                <?php if ($patient['last_visit']): ?>
                                                    <div class="small"><?= date('M j, Y', strtotime($patient['last_visit'])) ?>
                                                    </div>
                                                    <div class="last-visit">
                                                        <?php
                                                        $days_ago = round((time() - strtotime($patient['last_visit'])) / (60 * 60 * 24));
                                                        echo $days_ago <= 0 ? 'Today' : $days_ago . ' days ago';
                                                        ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <?= date('M j, Y', strtotime($patient['joined_date'])) ?>
                                                </div>
                                            </td>
                                            <td class="text-end patient-actions">
                                                <a href="patient_details.php?id=<?= $patient['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary me-1 mb-1"
                                                    title="View Patient Details">
                                                    <i class="fas fa-eye"></i> <span class="d-none d-lg-inline">View</span>
                                                </a>
                                                <a href="appointments.php?patient_id=<?= $patient['id'] ?>"
                                                    class="btn btn-sm btn-outline-secondary me-1 mb-1"
                                                    title="View Appointment History">
                                                    <i class="fas fa-calendar-alt"></i> <span
                                                        class="d-none d-lg-inline">History</span>
                                                </a>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div> <?php if (!empty($patients)): // Optional: Add card footer for showing count ?>
                    <div class="card-footer text-muted small">
                        Displaying <?= count($patients) ?> patient records.
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Patient Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="export_patients.php" method="post">
                <div class="modal-body">
                    <p>Select the format and data to include in the export.</p>
                    <div class="mb-3">
                        <label for="exportFormat" class="form-label">Format</label>
                        <select class="form-select" name="format" id="exportFormat" required>
                            <option value="csv" selected>CSV (Comma Separated Values)</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_by) ?>">
                    <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order) ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-download me-1"></i> Export
                        Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>