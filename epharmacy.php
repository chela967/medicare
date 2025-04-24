<?php
// epharmacy.php - Modern UI Redesign

// Session and security headers remain the same
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'use_strict_mode' => true,
    'cookie_samesite' => 'Lax'
]);

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
ob_start();

require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header("Location: auth.php?redirect=epharmacy");
    exit();
}

// User data initialization remains the same
$user_id = (int) $_SESSION['user']['id'];
$user = [
    'id' => $user_id,
    'name' => isset($_SESSION['user']['name']) ? htmlspecialchars($_SESSION['user']['name'], ENT_QUOTES, 'UTF-8') : 'Guest',
    'email' => isset($_SESSION['user']['email']) ? filter_var($_SESSION['user']['email'], FILTER_SANITIZE_EMAIL) : '',
    'role' => isset($_SESSION['user']['role']) ? htmlspecialchars($_SESSION['user']['role'], ENT_QUOTES, 'UTF-8') : 'patient'
];

// CSRF token initialization
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Get query parameters
$category_id = isset($_GET['category']) && ctype_digit($_GET['category']) ? (int) $_GET['category'] : null;
$search = isset($_GET['search']) ? trim(htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8')) : null;

// Database queries remain the same
try {
    $medicines = function_exists('getMedicines') ? getMedicines($category_id, $search) : [];
    $categories = function_exists('getCategories') ? getCategories() : [];
    $cart = function_exists('getCart') ? getCart($user['id']) : [];
    $prescriptions = function_exists('getUserPrescriptions') ? getUserPrescriptions($user['id']) : [];

    $cart_count = count($cart);
    $no_medicines = empty($medicines);
} catch (Exception $e) {
    error_log("Database error on epharmacy.php: " . $e->getMessage());
    die("<div class='alert alert-danger'>Error loading pharmacy data. Please check logs or contact support.</div>");
}

require_once 'header.php';
?>

<!-- Modern CSS Styles -->
<style>
    :root {
        --primary-color: #3a86ff;
        --primary-light: #e6f0ff;
        --secondary-color: #8338ec;
        --accent-color: #ff006e;
        --dark-color: #1a1a2e;
        --light-color: #f8f9fa;
        --success-color: #28a745;
        --warning-color: #ffc107;
        --danger-color: #dc3545;
        --border-radius: 12px;
        --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        --transition: all 0.3s ease;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        color: #333;
        background-color: #f5f7fa;
    }

    .e-pharmacy {
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        overflow: hidden;
    }

    .pharmacy-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        padding: 2rem;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
        margin: -1rem -1rem 2rem -1rem;
    }

    .card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        transition: var(--transition);
        overflow: hidden;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
    }

    .medicine-card {
        height: 100%;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .medicine-card .card-img-top {
        height: 180px;
        object-fit: contain;
        padding: 1rem;
        background-color: var(--light-color);
    }

    .medicine-card .card-body {
        display: flex;
        flex-direction: column;
    }

    .medicine-card .card-title {
        font-weight: 600;
        color: var(--dark-color);
    }

    .medicine-card .card-text {
        color: var(--text-light);
        font-size: 0.9rem;
    }

    .medicine-card .price {
        font-weight: 700;
        color: var(--primary-color);
        font-size: 1.1rem;
    }

    .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        padding: 0.5rem 1.25rem;
        font-weight: 500;
        border-radius: var(--border-radius);
    }

    .btn-outline-primary {
        color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn-outline-primary:hover {
        background-color: var(--primary-color);
        color: white;
    }

    .badge {
        font-weight: 500;
        padding: 0.35em 0.65em;
    }

    .cart-badge {
        font-size: 0.7rem;
    }

    .dropdown-menu {
        border-radius: var(--border-radius);
        border: none;
        box-shadow: var(--box-shadow);
        padding: 0.5rem;
    }

    .dropdown-item {
        padding: 0.5rem 1rem;
        border-radius: 8px;
    }

    .nav-link.active {
        color: var(--primary-color);
        font-weight: 600;
        background-color: var(--primary-light);
        border-radius: 8px;
    }

    .search-box {
        position: relative;
    }

    .search-box .form-control {
        padding-left: 2.5rem;
        border-radius: var(--border-radius);
    }

    .search-box .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-light);
    }

    .prescription-table {
        border-radius: var(--border-radius);
        overflow: hidden;
    }

    .prescription-table th {
        background-color: var(--primary-light);
        color: var(--primary-color);
        font-weight: 600;
    }

    @media (max-width: 768px) {
        .pharmacy-header {
            text-align: center;
        }

        .cart-dropdown {
            margin-top: 1rem;
            text-align: center;
        }
    }
</style>

<main class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-xxl-10 col-12">
            <div class="e-pharmacy p-3 p-md-4">
                <!-- Modern Header Section -->
                <div class="pharmacy-header mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="display-5 fw-bold mb-2">Medicare E-Pharmacy</h1>
                            <p class="lead mb-0">Quality medicines delivered to your doorstep</p>
                        </div>
                        <div class="col-md-4 text-md-end cart-dropdown">
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-light position-relative dropdown-toggle" type="button"
                                    id="cartDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-shopping-cart me-1"></i> My Cart
                                    <span
                                        class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-badge">
                                        <?= $cart_count ?>
                                    </span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width: 300px;"
                                    aria-labelledby="cartDropdown">
                                    <div id="miniCartContent">
                                        <?php if (empty($cart)): ?>
                                            <li class="dropdown-item text-muted py-3 text-center">
                                                <i class="fas fa-shopping-cart fa-2x mb-2 text-light"></i>
                                                <p class="mb-0">Your cart is empty</p>
                                            </li>
                                        <?php else: ?>
                                            <li class="dropdown-header fw-bold mb-2 px-2">Your Cart Items</li>
                                            <div id="miniCartItemsContainer" style="max-height: 50vh; overflow-y: auto;">
                                                <?php
                                                $cart_subtotal = 0;
                                                foreach ($cart as $item):
                                                    $item_name = $item['name'] ?? 'Unknown Item';
                                                    $item_quantity = (int) ($item['quantity'] ?? 0);
                                                    $item_price = (float) ($item['price'] ?? 0);
                                                    $item_total = $item_price * $item_quantity;
                                                    $cart_subtotal += $item_total;
                                                    ?>
                                                    <li>
                                                        <div
                                                            class="d-flex justify-content-between align-items-center px-2 py-2">
                                                            <div class="me-3 text-truncate" style="max-width: 180px;">
                                                                <span
                                                                    class="d-block text-truncate fw-medium"><?= htmlspecialchars($item_name) ?></span>
                                                                <small class="text-muted">Qty: <?= $item_quantity ?> ×
                                                                    $<?= number_format($item_price, 2) ?></small>
                                                            </div>
                                                            <span
                                                                class="text-nowrap fw-bold">$<?= number_format($item_total, 2) ?></span>
                                                        </div>
                                                    </li>
                                                    <li>
                                                        <hr class="dropdown-divider my-1 mx-2">
                                                    </li>
                                                <?php endforeach; ?>
                                            </div>
                                            <li class="d-flex justify-content-between px-2 py-2 fw-bold bg-light rounded">
                                                <span>Subtotal:</span>
                                                <span
                                                    id="miniCartTotalAmount">$<?= number_format($cart_subtotal, 2) ?></span>
                                            </li>
                                            <li class="mt-2">
                                                <a href="cart.php" class="btn btn-primary w-100">
                                                    <i class="fas fa-shopping-bag me-1"></i> Proceed to Checkout
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </div>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Sidebar Categories -->
                    <div class="col-lg-3 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3"><i class="fas fa-list-alt me-2"></i>Categories</h5>
                                <ul class="nav flex-column">
                                    <li class="nav-item mb-2">
                                        <a class="nav-link<?= !$category_id ? ' active' : '' ?>" href="?">
                                            <i class="fas fa-pills me-2"></i> All Medicines
                                        </a>
                                    </li>
                                    <?php if (!empty($categories)): ?>
                                        <?php foreach ($categories as $category): ?>
                                            <li class="nav-item mb-2">
                                                <a class="nav-link<?= (isset($category['id']) && $category_id === (int) $category['id']) ? ' active' : '' ?>"
                                                    href="?category=<?= (int) ($category['id'] ?? 0) ?>">
                                                    <i class="fas fa-tag me-2"></i>
                                                    <?= htmlspecialchars($category['name'] ?? 'Unnamed Category') ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content Area -->
                    <div class="col-lg-9">
                        <!-- Search Box -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <form class="row g-3" method="GET" action="epharmacy.php">
                                    <div class="col-md-8 search-box">
                                        <i class="fas fa-search search-icon"></i>
                                        <input type="text" class="form-control"
                                            placeholder="Search medicines by name or description..." name="search"
                                            value="<?= htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if ($category_id): ?>
                                            <input type="hidden" name="category" value="<?= $category_id ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="category" onchange="this.form.submit()">
                                            <option value="">Filter by Category</option>
                                            <?php if (!empty($categories)): ?>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?= (int) ($category['id'] ?? 0) ?>"
                                                        <?= (isset($category['id']) && $category_id === (int) $category['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($category['name'] ?? 'Unnamed Category') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Medicines Section -->
                        <div class="mb-5">
                            <h3 class="mb-4 d-flex align-items-center">
                                <i class="fas fa-pills me-2 text-primary"></i>
                                <?php
                                if ($search) {
                                    echo 'Search Results for "' . htmlspecialchars($search) . '"';
                                } elseif ($category_id && !empty($categories)) {
                                    $current_category_name = 'Selected Category';
                                    foreach ($categories as $cat) {
                                        if (isset($cat['id']) && (int) $cat['id'] === $category_id) {
                                            $current_category_name = htmlspecialchars($cat['name']);
                                            break;
                                        }
                                    }
                                    echo $current_category_name . ' Medicines';
                                } else {
                                    echo 'All Available Medicines';
                                }
                                ?>
                            </h3>

                            <?php if ($no_medicines): ?>
                                <div class="card">
                                    <div class="card-body text-center py-5">
                                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                        <h4 class="text-muted">
                                            <?php
                                            if ($search) {
                                                echo 'No medicines found matching "' . htmlspecialchars($search) . '"';
                                            } elseif ($category_id) {
                                                echo 'No medicines currently available in this category';
                                            } else {
                                                echo 'No medicines currently available';
                                            }
                                            ?>
                                        </h4>
                                        <a href="?" class="btn btn-outline-primary mt-3">Browse All Medicines</a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                                    <?php foreach ($medicines as $medicine):
                                        $med_id = (int) ($medicine['id'] ?? 0);
                                        $med_name = $medicine['name'] ?? 'Unknown Medicine';
                                        $med_image = $medicine['image'] ?? 'default.jpg';
                                        $med_manufacturer = $medicine['manufacturer'] ?? 'N/A';
                                        $med_price = (float) ($medicine['price'] ?? 0);
                                        ?>
                                        <div class="col">
                                            <div class="card medicine-card h-100">
                                                <img src="assets/medicines/<?= htmlspecialchars($med_image) ?>"
                                                    class="card-img-top" alt="<?= htmlspecialchars($med_name) ?>"
                                                    onerror="this.onerror=null; this.src='assets/medicines/drug.jpg';"
                                                    loading="lazy">
                                                <div class="card-body">
                                                    <h5 class="card-title"><?= htmlspecialchars($med_name) ?></h5>
                                                    <p class="card-text text-muted small mb-2">
                                                        <?= htmlspecialchars($med_manufacturer) ?>
                                                    </p>
                                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                                        <span class="price">$<?= number_format($med_price, 2) ?></span>
                                                        <button class="btn btn-sm btn-primary add-to-cart"
                                                            data-id="<?= $med_id ?>" <?= $med_id <= 0 ? 'disabled' : '' ?>>
                                                            <i class="fas fa-cart-plus me-1"></i> Add
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Prescriptions Section -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="mb-4 d-flex align-items-center">
                                    <i class="fas fa-prescription me-2 text-primary"></i> My Prescriptions
                                </h3>

                                <?php if (empty($prescriptions)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                                        <h4 class="text-muted mb-3">No prescriptions found</h4>
                                        <a href="upload_prescription.php" class="btn btn-primary">
                                            <i class="fas fa-upload me-1"></i> Upload Prescription
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive prescription-table">
                                        <table class="table table-hover align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Doctor</th>
                                                    <th>Status</th>
                                                    <th class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($prescriptions as $prescription):
                                                    $presc_id = (int) ($prescription['id'] ?? 0);
                                                    $presc_date = $prescription['created_at'] ?? null;
                                                    $presc_doctor = $prescription['doctor_name'] ?? 'N/A';
                                                    $presc_status = $prescription['status'] ?? 'pending';

                                                    $status_color = 'warning';
                                                    if ($presc_status === 'approved')
                                                        $status_color = 'success';
                                                    elseif ($presc_status === 'rejected')
                                                        $status_color = 'danger';
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <?= $presc_date ? date('M d, Y', strtotime($presc_date)) : 'N/A' ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($presc_doctor) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $status_color ?>">
                                                                <?= ucfirst(htmlspecialchars($presc_status)) ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end">
                                                            <a href="view_prescription.php?id=<?= $presc_id ?>"
                                                                class="btn btn-sm btn-outline-primary"
                                                                title="View Prescription">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <?php if ($presc_status === 'approved'): ?>
                                                                <button class="btn btn-sm btn-outline-success ms-1 reorder-btn"
                                                                    title="Reorder Items" data-id="<?= $presc_id ?>">
                                                                    <i class="fas fa-redo"></i>
                                                                </button>
                                                            <?php endif; ?>
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
                </div>
            </div>
        </div>
    </div>
</main>

<!-- JavaScript remains exactly the same -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const csrfToken = '<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>';

        function updateMiniCart(cartItems) {
            const contentDiv = document.getElementById('miniCartContent');
            if (!contentDiv) return;

            let newHtml = '';
            let cartSubtotal = 0;

            if (!cartItems || cartItems.length === 0) {
                newHtml = '<li class="dropdown-item text-muted py-3 text-center">' +
                    '<i class="fas fa-shopping-cart fa-2x mb-2 text-light"></i>' +
                    '<p class="mb-0">Your cart is empty</p></li>';
            } else {
                newHtml += '<li class="dropdown-header fw-bold mb-2 px-2">Your Cart Items</li>' +
                    '<div id="miniCartItemsContainer" style="max-height: 50vh; overflow-y: auto;">';

                cartItems.forEach(item => {
                    const itemName = item.name || 'Unknown Item';
                    const itemQuantity = parseInt(item.quantity || 0, 10);
                    const itemPrice = parseFloat(item.price || 0);
                    const itemTotal = itemPrice * itemQuantity;
                    cartSubtotal += itemTotal;

                    newHtml += `
                    <li>
                        <div class="d-flex justify-content-between align-items-center px-2 py-2">
                            <div class="me-3 text-truncate" style="max-width: 180px;">
                                <span class="d-block text-truncate fw-medium">${escapeHtml(itemName)}</span>
                                <small class="text-muted">Qty: ${itemQuantity} × $${itemPrice.toFixed(2)}</small>
                            </div>
                            <span class="text-nowrap fw-bold">$${itemTotal.toFixed(2)}</span>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider my-1 mx-2"></li>`;
                });

                newHtml += '</div>' +
                    '<li class="d-flex justify-content-between px-2 py-2 fw-bold bg-light rounded">' +
                    '<span>Subtotal:</span>' +
                    '<span id="miniCartTotalAmount">$${cartSubtotal.toFixed(2)}</span>' +
                    '</li>' +
                    '<li class="mt-2">' +
                    '<a href="cart.php" class="btn btn-primary w-100">' +
                    '<i class="fas fa-shopping-bag me-1"></i> Proceed to Checkout</a>' +
                    '</li>';
            }

            contentDiv.innerHTML = newHtml;
        }

        function escapeHtml(unsafe) {
            if (unsafe === null || unsafe === undefined) return '';
            return unsafe.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Add to cart functionality
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', async function (event) {
                event.preventDefault();
                const medicineId = this.dataset.id;
                if (!medicineId || medicineId <= 0) {
                    console.error('Add to Cart Error: Invalid medicine ID provided.');
                    alert('Cannot add item: Invalid medicine ID.');
                    return;
                }

                const btn = this;
                const originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';

                try {
                    const response = await fetch('add_to_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({
                            medicine_id: medicineId,
                            quantity: 1
                        })
                    });

                    const contentType = response.headers.get("content-type");
                    if (!response.ok || !contentType || !contentType.includes("application/json")) {
                        let errorText = `HTTP error ${response.status}. Expected JSON response.`;
                        try { const text = await response.text(); console.error("Add to Cart - Server Response:", text); errorText = `Server error ${response.status}. Check console/logs.`; } catch (e) { }
                        throw new Error(errorText);
                    }

                    const data = await response.json();

                    if (data.success) {
                        document.querySelectorAll('.cart-badge').forEach(badge => {
                            badge.textContent = data.cart_count ?? 0;
                        });

                        updateMiniCart(data.cart_items || []);

                        btn.innerHTML = '<i class="fas fa-check"></i> Added';
                        setTimeout(() => { btn.innerHTML = originalHtml; btn.disabled = false; }, 1500);
                    } else {
                        throw new Error(data.message || 'Failed to add item to cart.');
                    }

                } catch (error) {
                    console.error('Add to Cart Fetch Error:', error);
                    alert('Error adding item: ' + error.message);
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            });
        });

        // Reorder prescription functionality
        document.querySelectorAll('.reorder-btn').forEach(button => {
            button.addEventListener('click', async function (event) {
                event.preventDefault();
                const prescriptionId = this.dataset.id;
                if (!prescriptionId || prescriptionId <= 0) {
                    console.error('Reorder Error: Invalid prescription ID provided.');
                    alert('Cannot reorder: Invalid prescription ID.');
                    return;
                }

                const btn = this;
                const originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                try {
                    const response = await fetch('reorder_prescription.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({
                            prescription_id: prescriptionId
                        })
                    });

                    const contentType = response.headers.get("content-type");
                    if (!response.ok || !contentType || !contentType.includes("application/json")) {
                        let errorText = `HTTP error ${response.status}. Expected JSON response.`;
                        try { const text = await response.text(); console.error("Reorder - Server Response:", text); errorText = `Server error ${response.status}. Check console/logs.`; } catch (e) { }
                        throw new Error(errorText);
                    }

                    const data = await response.json();

                    if (data.success) {
                        document.querySelectorAll('.cart-badge').forEach(badge => {
                            badge.textContent = data.cart_count ?? 0;
                        });

                        if (data.cart_items) {
                            updateMiniCart(data.cart_items);
                        }

                        alert('Prescription items added to your cart!');
                    } else {
                        throw new Error(data.message || 'Failed to reorder prescription items.');
                    }
                } catch (error) {
                    console.error('Reorder Fetch Error:', error);
                    alert('Error reordering prescription: ' + error.message);
                } finally {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            });
        });
    });
</script>

<?php
require_once 'footer.php';
ob_end_flush();
?>