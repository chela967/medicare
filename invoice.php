<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    header("Location: ../auth.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my_orders.php");
    exit;
}

$order_id = (int) $_GET['id'];
$patient_id = $_SESSION['user']['id'];

// Fetch order details
$sql = "SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
               a.name as delivery_area, DATE_FORMAT(o.created_at, '%M %e, %Y') as order_date
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN delivery_areas a ON o.delivery_area_id = a.id
        WHERE o.id = ? AND o.user_id = ?";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $order_id, $patient_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Order not found'];
    header("Location: my_orders.php");
    exit;
}

// Fetch order items
$sql = "SELECT oi.*, p.name as product_name, p.description
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "Invoice #" . $order['id'];
require_once __DIR__ . '/../header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Invoice #<?= $order['id'] ?></h1>
        <div>
            <button onclick="window.print()" class="btn btn-outline-primary me-2">
                <i class="fas fa-print me-1"></i> Print
            </button>
            <a href="order_details.php?id=<?= $order['id'] ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Order
            </a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Medicare Pharmacy</h5>
                    <address>
                        123 Health Street<br>
                        Kabale, Uganda<br>
                        Phone: +256 123 456 789<br>
                        Email: info@medicare.com
                    </address>
                </div>
                <div class="col-md-6 text-md-end">
                    <h5>Invoice To</h5>
                    <address>
                        <?= htmlspecialchars($order['customer_name']) ?><br>
                        <?= htmlspecialchars($order['delivery_address']) ?><br>
                        <?= htmlspecialchars($order['delivery_area'] ?? '') ?><br>
                        Phone: <?= htmlspecialchars($order['contact_phone']) ?>
                    </address>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-6">
                    <p><strong>Invoice #:</strong> <?= $order['id'] ?></p>
                    <p><strong>Order Date:</strong> <?= $order['order_date'] ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p><strong>Payment Method:</strong> <?= ucfirst($order['payment_method']) ?></p>
                    <p><strong>Payment Status:</strong>
                        <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                            <?= ucfirst($order['payment_status']) ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($item['description']) ?></small>
                                </td>
                                <td class="text-end">UGX <?= number_format($item['price'], 2) ?></td>
                                <td class="text-end"><?= $item['quantity'] ?></td>
                                <td class="text-end">UGX <?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end">Subtotal:</th>
                            <th class="text-end">UGX <?= number_format($order['subtotal'], 2) ?></th>
                        </tr>
                        <tr>
                            <th colspan="4" class="text-end">Delivery Fee:</th>
                            <th class="text-end">UGX <?= number_format($order['delivery_fee'], 2) ?></th>
                        </tr>
                        <tr>
                            <th colspan="4" class="text-end">Total:</th>
                            <th class="text-end">UGX <?= number_format($order['total_amount'], 2) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Payment Instructions</h5>
                    <p>Please make payment to the following account:</p>
                    <ul>
                        <li><strong>Bank:</strong> Uganda Commercial Bank</li>
                        <li><strong>Account Name:</strong> Medicare Pharmacy</li>
                        <li><strong>Account Number:</strong> 1234567890</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5>Delivery Information</h5>
                    <p>Your order will be delivered to:</p>
                    <address>
                        <?= htmlspecialchars($order['delivery_address']) ?><br>
                        <?= htmlspecialchars($order['delivery_area'] ?? '') ?>
                    </address>
                    <?php if (!empty($order['tracking_number'])): ?>
                        <p><strong>Tracking #:</strong> <?= htmlspecialchars($order['tracking_number']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @media print {
        body * {
            visibility: hidden;
        }

        .container,
        .container * {
            visibility: visible;
        }

        .container {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }

        .btn {
            display: none !important;
        }
    }
</style>

<?php require_once __DIR__ . '/../footer.php'; ?>