<?php
// Strict session configuration
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'use_strict_mode' => true,
    'cookie_samesite' => 'Lax'
]);

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:;");

// Rate limiting
$rate_limit_key = 'rate_limit_' . $_SERVER['REMOTE_ADDR'];
$rate_limit = $_SESSION[$rate_limit_key] ?? 0;

if ($rate_limit > 10) {
    header('HTTP/1.1 429 Too Many Requests');
    die('Too many requests. Please try again later.');
}

$_SESSION[$rate_limit_key] = $rate_limit + 1;

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: auth.php?redirect=epharmacy");
    exit();
}

// Verify user has an ID
if (!isset($_SESSION['user']['id'])) {
    die("User ID not found in session. Please login again.");
}

// Set page title
$page_title = "E-Pharmacy - Medicare";

// Include configuration and functions
require_once 'config.php';
require_once 'functions.php';

// Initialize CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize user data with proper sanitization
$user = [
    'id' => (int) $_SESSION['user']['id'],
    'name' => htmlspecialchars($_SESSION['user']['name'] ?? 'Guest', ENT_QUOTES, 'UTF-8'),
    'email' => filter_var($_SESSION['user']['email'] ?? '', FILTER_SANITIZE_EMAIL),
    'role' => htmlspecialchars($_SESSION['user']['role'] ?? 'patient', ENT_QUOTES, 'UTF-8')
];

// Check user roles
$is_admin = checkUserRole('admin');
$is_pharmacist = checkUserRole('pharmacist');

// Get query parameters with validation
$category_id = isset($_GET['category']) ? (int) $_GET['category'] : null;
$search = isset($_GET['search']) ? trim(htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8')) : null;

// Start output buffering
ob_start();

try {
    // Get data from database
    $medicines = getMedicines($category_id, $search);
    $categories = getCategories();
    $cart = getCart($user['id']);
    $prescriptions = getUserPrescriptions($user['id']);
    
    // Calculate cart count safely
    $cart_count = is_array($cart) ? count($cart) : 0;
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    die("<div class='alert alert-danger'>Error loading pharmacy data. Please try again later.</div>");
}

// Include header
require_once 'header.php';
?>

<!-- Main Content - Centered Layout -->
<main class="container-fluid py-5 bg-light">
    <div class="row justify-content-center">
        <div class="col-xxl-10 col-12">
            <div class="e-pharmacy bg-white rounded-4 shadow-sm p-3 p-md-4">

                <!-- Pharmacy Header with ARIA labels -->
                <div class="row mb-4 align-items-center">
                    <div class="col-md-8">
                        <h1 class="display-5 fw-bold text-primary" id="main-heading">Medicare E-Pharmacy</h1>
                        <p class="lead text-muted">Order medicines online with home delivery</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-outline-primary position-relative dropdown-toggle" type="button"
                                id="cartDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true"
                                aria-label="Shopping cart">
                                <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                                <span class="d-none d-sm-inline"> Cart</span>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-badge">
                                    <?= $cart_count ?>
                                    <span class="visually-hidden">items in cart</span>
                                </span>
                            </button>

                            <ul class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="cartDropdown"
                                style="min-width: 280px; max-width: 90vw;">

                                <?php if (empty($cart)): ?>
                                    <li class="dropdown-item text-muted py-2 text-center">Your cart is empty</li>
                                <?php else: ?>
                                    <li class="dropdown-header fw-bold mb-1 px-2">Your Items</li>

                                    <div style="max-height: 50vh; overflow-y: auto;">
                                        <?php foreach ($cart as $item): ?>
                                            <li>
                                                <div class="d-flex justify-content-between align-items-center px-2 py-1">
                                                    <div class="me-3 text-truncate" style="max-width: 150px;">
                                                        <span class="d-block text-truncate"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        <small class="text-muted">Qty: <?= (int) $item['quantity'] ?></small>
                                                    </div>
                                                    <span class="text-nowrap">$<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                                                </div>
                                            </li>
                                            <li class="dropdown-divider my-1 mx-2"></li>
                                        <?php endforeach; ?>
                                    </div>

                                    <li class="d-flex justify-content-between px-2 py-1 fw-bold bg-light">
                                        <span>Total:</span>
                                        <span>$<?= number_format(array_sum(array_map(function ($item) {
                                            return $item['quantity'] * $item['price'];
                                        }, $cart)), 2) ?></span>
                                    </li>
                                    <li class="mt-2 px-2">
                                        <a href="cart.php" class="btn btn-primary w-100">
                                            <i class="fas fa-shopping-bag me-1" aria-hidden="true"></i> Checkout
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Main Content Row -->
                <div class="row">
                    <!-- Sidebar Navigation -->
                    <div class="col-lg-3 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h2 class="card-title h5">Categories</h2>
                                <nav aria-label="Medicine categories">
                                    <ul class="nav flex-column">
                                        <li class="nav-item">
                                            <a class="nav-link<?= !$category_id ? ' active fw-bold' : '' ?>" href="?">All Medicines</a>
                                        </li>
                                        <?php foreach ($categories as $category): ?>
                                            <li class="nav-item">
                                                <a class="nav-link<?= $category_id === $category['id'] ? ' active fw-bold' : '' ?>"
                                                   href="?category=<?= (int) $category['id'] ?>"
                                                   aria-current="<?= $category_id === $category['id'] ? 'page' : 'false' ?>">
                                                    <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </nav>

                                <hr>

                                <h2 class="card-title h5">Quick Links</h2>
                                <nav aria-label="Quick links">
                                    <ul class="nav flex-column">
                                        <li class="nav-item">
                                            <a class="nav-link" href="#prescriptions">
                                                <i class="fas fa-prescription me-2" aria-hidden="true"></i>My Prescriptions
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" href="orders.php">
                                                <i class="fas fa-history me-2" aria-hidden="true"></i>Order History
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" href="addresses.php">
                                                <i class="fas fa-map-marker-alt me-2" aria-hidden="true"></i>Delivery Addresses
                                            </a>
                                        </li>
                                        <?php if ($is_admin || $is_pharmacist): ?>
                                            <li class="nav-item">
                                                <a class="nav-link text-danger" href="admin/">
                                                    <i class="fas fa-lock me-2" aria-hidden="true"></i>Admin Panel
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content Area -->
                    <div class="col-lg-9">
                        <!-- Search and Filters -->
                        <div class="card mb-4 shadow-sm">
                            <div class="card-body">
                                <form class="row g-3" role="search">
                                    <div class="col-md-8">
                                        <div class="input-group">
                                            <label for="medicineSearch" class="visually-hidden">Search medicines</label>
                                            <input type="text" class="form-control" id="medicineSearch" 
                                                   placeholder="Search medicines..." name="search"
                                                   value="<?= isset($search) ? htmlspecialchars($search, ENT_QUOTES, 'UTF-8') : '' ?>"
                                                   aria-label="Search medicines">
                                            <button class="btn btn-primary" type="submit" aria-label="Search">
                                                <i class="fas fa-search" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="categoryFilter" class="visually-hidden">Filter by category</label>
                                        <select class="form-select" id="categoryFilter" name="category" onchange="this.form.submit()">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= (int) $category['id'] ?>" <?= $category_id === $category['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Medicine Catalog -->
                        <section aria-labelledby="medicines-heading">
                            <h2 id="medicines-heading" class="mb-3 h3">Available Medicines</h2>
                            <?php if (empty($medicines)): ?>
                                <div class="col-12">
                                    <div class="alert alert-info">No medicines found matching your criteria.</div>
                                </div>
                            <?php else: ?>
                                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                                    <?php foreach ($medicines as $medicine): ?>
                                        <?php
                                        $medicine_id = (int) $medicine['id'];
                                        $requires_prescription = (bool) $medicine['requires_prescription'];
                                        $in_stock = (int) $medicine['stock'] > 0;
                                        $low_stock = $in_stock && $medicine['stock'] < 10;
                                        ?>

                                        <div class="col mb-4">
                                            <div class="card h-100 shadow-sm medicine-card">
                                                <div class="badge bg-<?= $requires_prescription ? 'warning' : 'success' ?> position-absolute"
                                                     style="top: 0.5rem; right: 0.5rem">
                                                    <?= $requires_prescription ? 'Prescription' : 'OTC' ?>
                                                </div>
                                                <img src="assets/medicines/<?= htmlspecialchars($medicine['image'], ENT_QUOTES, 'UTF-8') ?>"
                                                     class="card-img-top p-3" alt="<?= htmlspecialchars($medicine['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                     onerror="this.src='assets/medicines/default.jpg'" loading="lazy">
                                                <div class="card-body">
                                                    <h3 class="card-title h5"><?= htmlspecialchars($medicine['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                                    <p class="card-subtitle mb-2 text-muted">
                                                        <?= htmlspecialchars($medicine['manufacturer'], ENT_QUOTES, 'UTF-8') ?>
                                                    </p>
                                                    <p class="card-text">
                                                        <?= shortenText(htmlspecialchars($medicine['description'], ENT_QUOTES, 'UTF-8'), 100) ?>
                                                    </p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="fw-bold text-primary">
                                                            $<?= number_format((float) $medicine['price'], 2) ?>
                                                        </span>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-secondary add-to-wishlist"
                                                                    data-id="<?= $medicine_id ?>" title="Add to wishlist"
                                                                    aria-label="Add to wishlist">
                                                                <i class="far fa-heart" aria-hidden="true"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-primary add-to-cart"
                                                                    data-id="<?= $medicine_id ?>" <?= !$in_stock ? 'disabled' : '' ?>
                                                                    aria-label="Add to cart">
                                                                <i class="fas fa-cart-plus" aria-hidden="true"></i> Add
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <?php if ($low_stock): ?>
                                                        <div class="mt-2 text-warning small">Only <?= (int) $medicine['stock'] ?> left in stock!</div>
                                                    <?php elseif (!$in_stock): ?>
                                                        <div class="mt-2 text-danger small">Out of Stock</div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="card-footer bg-white">
                                                    <a href="#" class="btn btn-sm btn-link" data-bs-toggle="modal"
                                                       data-bs-target="#medicineModal<?= $medicine_id ?>"
                                                       aria-label="View details for <?= htmlspecialchars($medicine['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                        View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Medicine Modal -->
                                        <div class="modal fade" id="medicineModal<?= $medicine_id ?>" tabindex="-1"
                                             aria-labelledby="medicineModalLabel<?= $medicine_id ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h2 class="modal-title h5" id="medicineModalLabel<?= $medicine_id ?>">
                                                            <?= htmlspecialchars($medicine['name'], ENT_QUOTES, 'UTF-8') ?>
                                                        </h2>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-4">
                                                                <img src="assets/medicines/<?= htmlspecialchars($medicine['image'], ENT_QUOTES, 'UTF-8') ?>"
                                                                     class="img-fluid rounded" alt="<?= htmlspecialchars($medicine['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                                     onerror="this.src='assets/medicines/default.jpg'" loading="lazy">
                                                            </div>
                                                            <div class="col-md-8">
                                                                <p class="text-muted h6">
                                                                    <?= htmlspecialchars($medicine['manufacturer'], ENT_QUOTES, 'UTF-8') ?>
                                                                </p>
                                                                <p><?= htmlspecialchars($medicine['description'], ENT_QUOTES, 'UTF-8') ?></p>

                                                                <h3 class="h6 mt-4">Details</h3>
                                                                <ul class="list-unstyled">
                                                                    <li><strong>Dosage:</strong>
                                                                        <?= htmlspecialchars($medicine['dosage'], ENT_QUOTES, 'UTF-8') ?></li>
                                                                    <li><strong>Side Effects:</strong>
                                                                        <?= htmlspecialchars($medicine['side_effects'], ENT_QUOTES, 'UTF-8') ?></li>
                                                                    <li><strong>Category:</strong>
                                                                        <?= htmlspecialchars($medicine['category_name'], ENT_QUOTES, 'UTF-8') ?></li>
                                                                    <li><strong>Stock:</strong>
                                                                        <?= $in_stock ? 'Available' : 'Out of Stock' ?></li>
                                                                    <?php if ($low_stock): ?>
                                                                        <li class="text-warning"><strong>Availability:</strong> Only <?= (int) $medicine['stock'] ?> left!</li>
                                                                    <?php endif; ?>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Close</button>
                                                        <button class="btn btn-primary add-to-cart" data-id="<?= $medicine_id ?>"
                                                                <?= !$in_stock ? 'disabled' : '' ?>>
                                                            <i class="fas fa-cart-plus" aria-hidden="true"></i> Add to Cart
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>

                        <!-- Prescriptions Section -->
                        <section class="card mb-4 shadow-sm" id="prescriptions" aria-labelledby="prescriptions-heading">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h2 id="prescriptions-heading" class="h3">My Prescriptions</h2>
                                    <button class="btn btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#uploadPrescriptionModal" aria-label="Upload prescription">
                                        <i class="fas fa-upload me-2" aria-hidden="true"></i>Upload Prescription
                                    </button>
                                </div>

                                <?php if (empty($prescriptions)): ?>
                                    <div class="alert alert-info">You haven't uploaded any prescriptions yet.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table" aria-describedby="prescriptions-heading">
                                            <caption class="visually-hidden">List of uploaded prescriptions</caption>
                                            <thead>
                                                <tr>
                                                    <th scope="col">Date</th>
                                                    <th scope="col">Doctor</th>
                                                    <th scope="col">Status</th>
                                                    <th scope="col">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($prescriptions as $prescription): ?>
                                                    <?php
                                                    $status_class = match ($prescription['status']) {
                                                        'approved' => 'success',
                                                        'rejected' => 'danger',
                                                        default => 'warning'
                                                    };
                                                    ?>
                                                    <tr>
                                                        <td><?= date('M d, Y', strtotime($prescription['created_at'])) ?></td>
                                                        <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $status_class ?>">
                                                                <?= ucfirst($prescription['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="view_prescription.php?id=<?= (int) $prescription['id'] ?>"
                                                               class="btn btn-sm btn-outline-primary"
                                                               aria-label="View prescription from <?= date('M d, Y', strtotime($prescription['created_at'])) ?>">
                                                                View
                                                            </a>
                                                            <?php if ($prescription['status'] === 'approved'): ?>
                                                                <button class="btn btn-sm btn-outline-success reorder-btn"
                                                                        data-id="<?= (int) $prescription['id'] ?>"
                                                                        aria-label="Reorder prescription from <?= date('M d, Y', strtotime($prescription['created_at'])) ?>">
                                                                    Reorder
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
                        </section>

                        <!-- Upload Prescription Modal -->
                        <div class="modal fade" id="uploadPrescriptionModal" tabindex="-1"
                             aria-labelledby="uploadPrescriptionModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2 class="modal-title h5" id="uploadPrescriptionModalLabel">Upload Prescription</h2>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                    </div>
                                    <form method="POST" enctype="multipart/form-data" action="upload_prescription.php"
                                          id="prescriptionForm">
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label for="doctorName" class="form-label">Doctor's Name</label>
                                                <input type="text" class="form-control" id="doctorName"
                                                       name="doctor_name" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="prescriptionFile" class="form-label">Prescription File</label>
                                                <input type="file" class="form-control" id="prescriptionFile"
                                                       name="prescription_file" accept="image/*,.pdf" required
                                                       aria-describedby="fileHelp">
                                                <div id="fileHelp" class="form-text">
                                                    Upload clear image or PDF of your prescription (max 5MB)
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="notes" class="form-label">Additional Notes</label>
                                                <textarea class="form-control" id="notes" name="notes"
                                                          rows="3"></textarea>
                                            </div>
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary"
                                                    data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary"
                                                    name="upload_prescription">Upload</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
// Include footer
require_once 'footer.php';

// Minify and output the buffered content
$content = ob_get_contents();
ob_end_clean();

// Simple HTML minification
$content = preg_replace('/>\s+</', '><', $content);
echo $content;
?>

<!-- Custom JavaScript with enhanced features -->
<script>
    // Persistent cart counter using localStorage
    function updateCartCount(count) {
        // Update all cart badges
        document.querySelectorAll('.cart-badge').forEach(badge => {
            badge.textContent = count;
        });
        
        // Store in localStorage for persistence across pages
        localStorage.setItem('cartCount', count);
    }

    // Initialize cart count from localStorage
    document.addEventListener('DOMContentLoaded', function() {
        const savedCount = localStorage.getItem('cartCount');
        if (savedCount) {
            updateCartCount(savedCount);
        }

        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    // Toast notification function
    function showToast(message, type = 'info') {
        const toastContainer = document.createElement('div');
        toastContainer.className = `toast show align-items-center text-white bg-${type} border-0`;
        toastContainer.setAttribute('role', 'alert');
        toastContainer.setAttribute('aria-live', 'assertive');
        toastContainer.setAttribute('aria-atomic', 'true');
        toastContainer.style.position = 'fixed';
        toastContainer.style.top = '20px';
        toastContainer.style.right = '20px';
        toastContainer.style.zIndex = '1100';

        toastContainer.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        document.body.appendChild(toastContainer);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            toastContainer.classList.remove('show');
            setTimeout(() => toastContainer.remove(), 300);
        }, 5000);

        // Add click to dismiss
        toastContainer.querySelector('.btn-close').addEventListener('click', () => {
            toastContainer.classList.remove('show');
            setTimeout(() => toastContainer.remove(), 300);
        });
    }

    // Add to cart with rate limiting and accessibility
    document.addEventListener('DOMContentLoaded', function () {
        let lastClickTime = 0;
        const CART_DELAY = 1000; // 1 second between clicks

        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', async function (e) {
                e.preventDefault();

                // Rate limiting
                const now = Date.now();
                if (now - lastClickTime < CART_DELAY) {
                    showToast('Please wait before adding another item', 'warning');
                    return;
                }
                lastClickTime = now;

                const medicineId = this.getAttribute('data-id');
                const button = this;
                const originalText = button.innerHTML;
                const medicineName = this.closest('.medicine-card')?.querySelector('.card-title')?.textContent || 'item';

                // Show loading state
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';
                button.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch('add_to_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            medicine_id: medicineId,
                            quantity: 1,
                            csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                        })
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.success) {
                        updateCartCount(data.cart_count);
                        showToast(`${medicineName} added to cart successfully!`, 'success');

                        // Close any open medicine modal
                        const modal = bootstrap.Modal.getInstance(button.closest('.modal'));
                        if (modal) {
                            modal.hide();
                        }
                    } else {
                        throw new Error(data.message || 'Failed to add to cart');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast(error.message || 'Network error. Please try again.', 'error');
                } finally {
                    button.disabled = false;
                    button.innerHTML = originalText;
                    button.setAttribute('aria-busy', 'false');
                }
            });
        });

        // Add to wishlist functionality
        document.querySelectorAll('.add-to-wishlist').forEach(button => {
            button.addEventListener('click', async function(e) {
                e.preventDefault();
                const medicineId = this.getAttribute('data-id');
                const button = this;
                
                try {
                    const response = await fetch('add_to_wishlist.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            medicine_id: medicineId,
                            csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showToast('Added to wishlist!', 'success');
                        button.innerHTML = '<i class="fas fa-heart" aria-hidden="true"></i>';
                        button.classList.replace('btn-outline-secondary', 'btn-outline-danger');
                    } else {
                        throw new Error(data.message || 'Failed to add to wishlist');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast(error.message || 'Error adding to wishlist', 'error');
                }
            });
        });

        // Reorder prescription functionality
        document.querySelectorAll('.reorder-btn').forEach(button => {
            button.addEventListener('click', async function () {
                const prescriptionId = this.getAttribute('data-id');
                const button = this;
                const originalText = button.innerHTML;

                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                button.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch('reorder_prescription.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            prescription_id: prescriptionId,
                            csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showToast('Prescription items added to cart!', 'success');
                        updateCartCount(data.cart_count);
                    } else {
                        throw new Error(data.message || 'Failed to reorder');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast(error.message || 'Network error. Please try again.', 'error');
                } finally {
                    button.disabled = false;
                    button.innerHTML = originalText;
                    button.setAttribute('aria-busy', 'false');
                }
            });
        });

        // Form validation for prescription upload
        const prescriptionForm = document.getElementById('prescriptionForm');
        if (prescriptionForm) {
            prescriptionForm.addEventListener('submit', function(e) {
                const fileInput = document.getElementById('prescriptionFile');
                if (fileInput.files.length > 0) {
                    const fileSize = fileInput.files[0].size / 1024 / 1024; // in MB
                    if (fileSize > 5) {
                        e.preventDefault();
                        showToast('File size must be less than 5MB', 'error');
                    }
                }
            });
        }
    });
</script>