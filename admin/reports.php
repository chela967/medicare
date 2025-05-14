<?php
session_start();
require_once __DIR__ . '/../config.php'; // Provides $conn

// --- Authentication & Authorization ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Access denied. Please log in as an admin.'];
    header("Location: auth.php"); // Adjust path if needed
    exit();
}

$page_title = "Admin Reports";
// Include admin header (adjust path if necessary)
require_once __DIR__ . '/../header.php';

// --- Initialize Report Data ---
$user_stats = ['patient' => 0, 'doctor' => 0, 'admin' => 0, 'total' => 0];
$appointment_stats = [];
$order_stats = [];
$total_order_revenue = 0;
$total_appt_revenue = 0;
$low_stock_medicines = [];
$db_error = null;

// --- Fetch Report Data ---
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    try {
        // --- User Statistics ---
        $sql_users = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
        $result_users = $conn->query($sql_users);
        if ($result_users) {
            while ($row = $result_users->fetch_assoc()) {
                if (isset($user_stats[$row['role']])) {
                    $user_stats[$row['role']] = (int) $row['count'];
                }
                $user_stats['total'] += (int) $row['count'];
            }
            $result_users->free();
        } else {
            throw new mysqli_sql_exception("Query failed (fetch users): " . $conn->error);
        }

        // --- Appointment Statistics ---
        $sql_appts = "SELECT status, COUNT(*) as count FROM appointments GROUP BY status";
        $result_appts = $conn->query($sql_appts);
        if ($result_appts) {
            while ($row = $result_appts->fetch_assoc()) {
                $appointment_stats[$row['status']] = (int) $row['count'];
            }
            $result_appts->free();
        } else {
            throw new mysqli_sql_exception("Query failed (fetch appt stats): " . $conn->error);
        }

        // --- Order Statistics ---
        $sql_orders_stats = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
        $result_orders_stats = $conn->query($sql_orders_stats);
        if ($result_orders_stats) {
            while ($row = $result_orders_stats->fetch_assoc()) {
                $order_stats[$row['status']] = (int) $row['count'];
            }
            $result_orders_stats->free();
        } else {
            throw new mysqli_sql_exception("Query failed (fetch order stats): " . $conn->error);
        }

        // --- Revenue Summary ---
        // Order Revenue (adjust statuses included in revenue calculation as needed)
        $sql_order_rev = "SELECT SUM(total_amount) as total FROM orders WHERE status IN ('completed', 'delivered', 'shipped')"; // Example statuses
        $result_order_rev = $conn->query($sql_order_rev);
        if ($result_order_rev) {
            $total_order_revenue = (float) ($result_order_rev->fetch_assoc()['total'] ?? 0);
            $result_order_rev->free();
        } else {
            throw new mysqli_sql_exception("Query failed (fetch order revenue): " . $conn->error);
        }

        // Appointment Revenue (assuming 'paid' status indicates revenue)
        $sql_appt_rev = "SELECT SUM(consultation_fee) as total FROM appointments WHERE payment_status = 'paid'";
        $result_appt_rev = $conn->query($sql_appt_rev);
        if ($result_appt_rev) {
            $total_appt_revenue = (float) ($result_appt_rev->fetch_assoc()['total'] ?? 0);
            $result_appt_rev->free();
        } else {
            throw new mysqli_sql_exception("Query failed (fetch appt revenue): " . $conn->error);
        }

        // --- Low Stock Medicines ---
        $low_stock_threshold = 10; // Define threshold
        $sql_low_stock = "SELECT id, name, stock, category_id FROM medicines WHERE stock < ? ORDER BY stock ASC";
        $stmt_low_stock = $conn->prepare($sql_low_stock);
        if (!$stmt_low_stock)
            throw new mysqli_sql_exception("Prepare failed (low stock): " . $conn->error);
        $stmt_low_stock->bind_param("i", $low_stock_threshold);
        $stmt_low_stock->execute();
        $result_low_stock = $stmt_low_stock->get_result();
        $low_stock_medicines = $result_low_stock->fetch_all(MYSQLI_ASSOC);
        $stmt_low_stock->close();


    } catch (mysqli_sql_exception $e) {
        error_log("Admin Reports Database Error: " . $e->getMessage());
        $db_error = "Error generating reports: " . $e->getMessage();
    }
} else {
    $db_error = "Database connection error. Cannot generate reports.";
}
?>

<div class="container-fluid py-4">
    <h1>Admin Reports</h1>

    <?php if ($db_error): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($db_error) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <span
                                class="bg-primary text-white p-3 rounded-circle d-inline-flex align-items-center justify-content-center"
                                style="width: 50px; height: 50px;">
                                <i class="fas fa-users fa-lg"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title mb-1">Users</h5>
                            <p class="card-text mb-0 fs-4 fw-bold"><?= $user_stats['total'] ?></p>
                            <small class="text-muted">
                                P: <?= $user_stats['patient'] ?> | D: <?= $user_stats['doctor'] ?> | A:
                                <?= $user_stats['admin'] ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <span
                                class="bg-success text-white p-3 rounded-circle d-inline-flex align-items-center justify-content-center"
                                style="width: 50px; height: 50px;">
                                <i class="fas fa-calendar-check fa-lg"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title mb-1">Appointments</h5>
                            <p class="card-text mb-0 fs-4 fw-bold"><?= array_sum($appointment_stats) ?></p>
                            <small class="text-muted">
                                <?php
                                $appt_stat_str = [];
                                foreach ($appointment_stats as $status => $count) {
                                    $appt_stat_str[] = ucfirst($status) . ": " . $count;
                                }
                                echo implode(' | ', $appt_stat_str);
                                ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <span
                                class="bg-danger text-white p-3 rounded-circle d-inline-flex align-items-center justify-content-center"
                                style="width: 50px; height: 50px;">
                                <i class="fas fa-receipt fa-lg"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title mb-1">Orders</h5>
                            <p class="card-text mb-0 fs-4 fw-bold"><?= array_sum($order_stats) ?></p>
                            <small class="text-muted">
                                <?php
                                $order_stat_str = [];
                                foreach ($order_stats as $status => $count) {
                                    $order_stat_str[] = ucfirst($status) . ": " . $count;
                                }
                                echo implode(' | ', $order_stat_str);
                                ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <span
                                class="bg-warning text-dark p-3 rounded-circle d-inline-flex align-items-center justify-content-center"
                                style="width: 50px; height: 50px;">
                                <i class="fas fa-dollar-sign fa-lg"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title mb-1">Total Revenue</h5>
                            <p class="card-text mb-0 fs-4 fw-bold">UGX
                                <?= number_format($total_order_revenue + $total_appt_revenue, 2) ?>
                            </p>
                            <small class="text-muted">
                                Orders: <?= number_format($total_order_revenue, 2) ?> | Appts:
                                <?= number_format($total_appt_revenue, 2) ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">Low Stock Medicines (Less than <?= $low_stock_threshold ?> units)</h2>
        </div>
        <div class="card-body">
            <?php if (!empty($low_stock_medicines)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Current Stock</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock_medicines as $med): ?>
                                <tr class="<?= ($med['stock'] < 5) ? 'table-danger' : '' ?>"> {/* Highlight if very low */}
                                    <td><?= $med['id'] ?></td>
                                    <td><?= htmlspecialchars($med['name']) ?></td>
                                    <td class="fw-bold"><?= $med['stock'] ?></td>
                                    <td>
                                        <a href="admin_manage_medicines_orders.php#medicines-tab"
                                            class="btn btn-sm btn-outline-primary">Manage Stock</a>
                                        {/* Link directly to the other admin page */}
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle me-1"></i> All medicines have sufficient stock
                    (<?= $low_stock_threshold ?> units or more).
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
<?php
// Include admin footer (adjust path if necessary)
require_once __DIR__ . '/../footer.php';
?>