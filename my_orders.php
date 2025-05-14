<?php
// my_orders.php - Patient Order History
session_start();
require_once __DIR__ . '/config.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// Check user role
$user_role = $_SESSION['user']['role'] ?? '';
if ($user_role !== 'patient') {
    header("Location: unauthorized.php");
    exit;
}

$patient_id = (int) ($_SESSION['user']['id'] ?? 0);
$patient_name = htmlspecialchars($_SESSION['user']['name'] ?? '');
$page_title = "My Orders";
require_once __DIR__ . '/header.php';

// Initialize variables with defaults
$status = 'all';
$page = 1;
$per_page = 10;
$orders = [];
$total_orders = 0;
$error = '';

// Safe input handling
if (isset($_GET['status']) && in_array($_GET['status'], ['all', 'pending', 'processing', 'completed', 'cancelled'])) {
    $status = $_GET['status'];
}

if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $page = max(1, (int) $_GET['page']);
}

$offset = ($page - 1) * $per_page;

try {
    // Get total count of orders
    $count_sql = "SELECT COUNT(*) as total FROM orders WHERE user_id = ?";
    $params = [$patient_id];
    $types = "i";

    if ($status !== 'all') {
        $count_sql .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }

    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_data = $result->fetch_assoc();
    $total_orders = $total_data['total'] ?? 0;
    $stmt->close();

    // Get paginated orders
    $sql = "SELECT 
                o.id, 
                o.created_at as order_date, 
                o.total_amount, 
                o.status,
                pm.name as payment_method, 
                pm.icon as payment_icon,
                COUNT(oi.id) as item_count,
                GROUP_CONCAT(m.name SEPARATOR ', ') as items
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN medicines m ON oi.medicine_id = m.id
            LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id
            WHERE o.user_id = ?";

    if ($status !== 'all') {
        $sql .= " AND o.status = ?";
    }

    $sql .= " GROUP BY o.id
              ORDER BY o.created_at DESC
              LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);

    if ($status === 'all') {
        $stmt->bind_param("iii", $patient_id, $per_page, $offset);
    } else {
        $stmt->bind_param("isii", $patient_id, $status, $per_page, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    error_log("Orders Error: " . $e->getMessage());
    $error = "Could not load orders. Please try again later.";
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">My Orders</h1>
        <a href="pharmacy.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> New Order
        </a>
    </div>

    <?php if (!empty($error)): ?>
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
                                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed
                                </option>
                                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled
                                </option>
                            </select>
                        </div>
                        <div class="col-4">
                            <a href="my_orders.php" class="btn btn-outline-secondary w-100">Reset</a>
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
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Payment</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                // Set defaults for all order fields
                                $order_id = $order['id'] ?? 0;
                                $order_date = $order['order_date'] ?? date('Y-m-d H:i:s');
                                $item_count = $order['item_count'] ?? 0;
                                $items = $order['items'] ?? 'No items';
                                $payment_method = $order['payment_method'] ?? 'Not specified';
                                $payment_icon = $order['payment_icon'] ?? 'fas fa-question-circle';
                                $total_amount = $order['total_amount'] ?? 0;
                                $status = $order['status'] ?? 'unknown';
                                ?>
                                <tr>
                                    <td>#<?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= date('M j, Y', strtotime($order_date)) ?></td>
                                    <td>
                                        <?php if ($item_count > 0): ?>
                                            <span title="<?= htmlspecialchars($items) ?>">
                                                <?= $item_count ?> item<?= $item_count > 1 ? 's' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No items</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <i class="<?= htmlspecialchars($payment_icon) ?> me-1"></i>
                                        <?= htmlspecialchars($payment_method) ?>
                                    </td>
                                    <td>UGX <?= number_format($total_amount, 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?=
                                            match ($status) {
                                                'pending' => 'warning',
                                                'processing' => 'info',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            }
                                            ?>">
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="order_details.php?id=<?= $order_id ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i> Details
                                            </a>
                                            <?php if (in_array($status, ['pending', 'processing'])): ?>
                                                <a href="cancel_order.php?id=<?= $order_id ?>" class="btn btn-outline-danger"
                                                    onclick="return confirm('Are you sure you want to cancel this order?')">
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
                <?php if ($total_orders > $per_page): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= $status ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $total_pages = ceil($total_orders / $per_page);
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&status=' . $status . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&status=<?= $status ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor;

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&status=' . $status . '">' . $total_pages . '</a></li>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= $status ?>">
                                        Next
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-4x text-muted mb-4"></i>
                    <h3>No Orders Found</h3>
                    <p class="text-muted">
                        <?= $status === 'all' ? 'You have no orders yet.' : "No $status orders found." ?>
                    </p>
                    <a href="pharmacy.php" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-1"></i> Shop Now
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>