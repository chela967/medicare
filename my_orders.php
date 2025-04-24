<?php
// my_orders.php - Patient Order History

session_start();
require_once __DIR__ . '/config.php';

// Check if user is logged in as patient
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patient_id = $_SESSION['user']['id'];
$patient_name = $_SESSION['user']['name'];
$page_title = "My Orders";
require_once __DIR__ . '/header.php';

// Pagination and filtering
$status = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$orders = [];
$total_orders = 0;

try {
    // Establish database connection if not already done in config.php
    if (!isset($mysqli)) {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($mysqli->connect_error) {
            throw new Exception("Database connection failed: " . $mysqli->connect_error);
        }
    }

    // Status filter condition
    $status_condition = $status === 'all' ? "" : "AND o.status = ?";

    // Get total count of orders
    $count_sql = "SELECT COUNT(*) as total 
                 FROM orders o
                 JOIN patients p ON o.patient_id = p.user_id
                 WHERE o.patient_id = ? $status_condition";

    $stmt = $mysqli->prepare($count_sql);
    if ($status === 'all') {
        $stmt->bind_param("i", $patient_id);
    } else {
        $stmt->bind_param("is", $patient_id, $status);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $total_orders = $result->fetch_assoc()['total'];
    $stmt->close();

    // Get paginated orders
    $sql = "SELECT o.id, o.order_date, o.total_amount, o.status, 
                   o.prescription_id, o.delivery_address, o.payment_method,
                   p.prescription_date, d.name as doctor_name
            FROM orders o
            LEFT JOIN prescriptions p ON o.prescription_id = p.id
            LEFT JOIN doctors doc ON p.doctor_id = doc.id
            LEFT JOIN users d ON doc.user_id = d.id
            WHERE o.patient_id = ? $status_condition
            ORDER BY o.order_date DESC
            LIMIT ? OFFSET ?";

    $stmt = $mysqli->prepare($sql);
    if ($status === 'all') {
        $stmt->bind_param("iii", $patient_id, $per_page, $offset);
    } else {
        $stmt->bind_param("isii", $patient_id, $status, $per_page, $offset);
    }
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    error_log("Orders Error: " . $e->getMessage());
    $error = "Could not load orders. Please try again.";
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">My Orders</h1>
        <a href="new_order.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> New Order
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <form method="get" class="row g-2">
                        <div class="col-8">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Orders</option>
                                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Processing
                                </option>
                                <option value="shipped" <?= $status === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered
                                </option>
                                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled
                                </option>
                            </select>
                        </div>
                        <div class="col-4">
                            <a href="my_orders.php" class="btn btn-outline-secondary w-100">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end">
                        <a href="patient_dashboard.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (!empty($orders)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Prescription</th>
                                <th>Doctor</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                                    <td>
                                        <?php if ($order['prescription_id']): ?>
                                            <a href="prescription.php?id=<?= $order['prescription_id'] ?>" class="text-primary">
                                                #PR-<?= str_pad($order['prescription_id'], 4, '0', STR_PAD_LEFT) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No prescription</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $order['doctor_name'] ? 'Dr. ' . htmlspecialchars($order['doctor_name']) : 'N/A' ?>
                                    </td>
                                    <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?=
                                            $order['status'] === 'pending' ? 'warning' :
                                            ($order['status'] === 'processing' ? 'info' :
                                                ($order['status'] === 'shipped' ? 'primary' :
                                                    ($order['status'] === 'delivered' ? 'success' :
                                                        ($order['status'] === 'cancelled' ? 'danger' : 'secondary'))))
                                            ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="order_details.php?id=<?= $order['id'] ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($order['status'] === 'pending' || $order['status'] === 'processing'): ?>
                                                <a href="cancel_order.php?id=<?= $order['id'] ?>"
                                                    class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= $status ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= ceil($total_orders / $per_page); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&status=<?= $status ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < ceil($total_orders / $per_page)): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= $status ?>">
                                    Next
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-4x text-muted mb-4"></i>
                    <h3>No Orders Found</h3>
                    <p class="text-muted">
                        <?= $status === 'all' ? 'You have no orders yet.' : "No $status orders found." ?>
                    </p>
                    <a href="new_order.php" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-1"></i> Place New Order
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>