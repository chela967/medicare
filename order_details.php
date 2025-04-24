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

$order_id = (int)$_GET['id'];
$patient_id = $_SESSION['user']['id'];
$order = null;
$items = [];

// Fetch order details
$sql = "SELECT o.*, a.name as delivery_area, 
               DATE_FORMAT(o.created_at, '%W, %M %e, %Y') as formatted_date
        FROM orders o
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
$sql = "SELECT oi.*, p.name, p.image 
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "Order Details";
require_once __DIR__ . '/../header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Order #<?= $order['id'] ?></h1>
        <div>
            <a href="invoice.php?id=<?= $order['id'] ?>" class="btn btn-outline-secondary me-2">
                <i class="fas fa-file-invoice me-1"></i> Invoice
            </a>
            <a href="my_orders.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i> Back to Orders
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Order Summary</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Order Date</dt>
                        <dd class="col-sm-8"><?= $order['formatted_date'] ?></dd>

                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-<?= 
                                $order['status'] === 'delivered' ? 'success' : 
                                ($order['status'] === 'shipped' ? 'info' : 
                                ($order['status'] === 'processing' ? 'warning' : 'secondary'))
                            ?>">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </dd>

                        <dt class="col-sm-4">Payment Method</dt>
                        <dd class="col-sm-8"><?= ucfirst($order['payment_method']) ?></dd>

                        <dt class="col-sm-4">Payment Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                <?= ucfirst($order['payment_status']) ?>
                            </span>
                        </dd>

                        <dt class="col-sm-4">Total Amount</dt>
                        <dd class="col-sm-8">UGX <?= number_format($order['total_amount'], 2) ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-truck me-2"></i> Delivery Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Delivery Address</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($order['delivery_address']) ?></dd>

                        <dt class="col-sm-4">Delivery Area</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($order['delivery_area'] ?? 'Not specified') ?></dd>

                        <dt class="col-sm-4">Contact Phone</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($order['contact_phone']) ?></dd>

                        <?php if (!empty($order['tracking_number'])): ?>
                            <dt class="col-sm-4">Tracking Number</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($order['tracking_number']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-shopping-basket me-2"></i> Order Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($item['image'])): ?>
                                                    <img src="../uploads/products/<?= htmlspecialchars($item['image']) ?>" 
                                                         alt="<?= htmlspecialchars($item['name']) ?>" 
                                                         class="rounded me-2" width="40">
                                                <?php endif; ?>
                                                <div>
                                                    <?= htmlspecialchars($item['name']) ?>
                                                    <?php if (!empty($item['prescription_required']) && $item['prescription_required']): ?>
                                                        <span class="badge bg-warning ms-2">Prescription</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>UGX <?= number_format($item['price'], 2) ?></td>
                                        <td>UGX <?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3">Subtotal</th>
                                    <td>UGX <?= number_format($order['subtotal'], 2) ?></td>
                                </tr>
                                <tr>
                                    <th colspan="3">Delivery Fee</th>
                                    <td>UGX <?= number_format($order['delivery_fee'], 2) ?></td>
                                </tr>
                                <tr>
                                    <th colspan="3">Total</th>
                                    <td>UGX <?= number_format($order['total_amount'], 2) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($order['status'] === 'pending' || $order['status'] === 'processing'): ?>
                <div class="card shadow-sm mt-4 border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Cancel Order</h5>
                    </div>
                    <div class="card-body">
                        <p>You can cancel this order before it's processed for shipping.</p>
                        <a href="cancel_order.php?id=<?= $order['id'] ?>" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i> Cancel Order
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>