<?php
// Secure session start (MUST be at the top)
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // 1 day
        'cookie_secure' => isset($_SERVER['HTTPS']), // Use true if on HTTPS
        'cookie_httponly' => true, // Prevent JS access to session cookie
        'use_strict_mode' => true // Prevent session fixation attacks
    ]);
}

// CSRF token generation (simple example)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include database configuration if needed globally and not already included by the parent script
// If $conn is NOT guaranteed to be available from the including script, uncomment the next line
// require_once __DIR__ . '/config.php'; // Provides $conn - Adjust path if needed

// Set default page title if not defined by the including page
$page_title = $page_title ?? 'Medicare - Quality Healthcare';

// Detect current section based on URL path
$is_admin = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
$is_doctor = strpos($_SERVER['REQUEST_URI'], '/doctor/') !== false;
$is_auth = (basename($_SERVER['PHP_SELF']) === 'auth.php'); // Check if on auth page

// Utility function for generating URLs (optional but recommended)
// Ensure this reflects your actual project structure
if (!function_exists('base_url')) {
    function base_url($path = '')
    {
        // Basic example, adjust protocol, host, and base path as needed
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $base_path = '/medicare'; // IMPORTANT: Set this to your project's base directory relative to the web root
        // If project is at web root, set $base_path = '';

        // Remove leading/trailing slashes from path and base_path for clean joining
        $base_path = trim($base_path, '/');
        $path = trim($path, '/');

        $url = $protocol . $host;
        if (!empty($base_path)) {
            $url .= '/' . $base_path;
        }
        if (!empty($path)) {
            $url .= '/' . $path;
        }
        // If path is empty, return just the base URL ending with / if base_path exists
        if (empty($path) && !empty($base_path)) {
            $url .= '/';
        } elseif (empty($path) && empty($base_path)) {
            // Root directory, no trailing slash needed unless desired
        }

        return $url;
    }
}

// **** ADDED: Fetch Unread Notification Count ****
$unread_notification_count = 0;
// Check if user is logged in AND if $conn (from config.php) is available and valid
if (isset($_SESSION['user']['id']) && isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $current_user_id = $_SESSION['user']['id'];
    $sql_count = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt_count = $conn->prepare($sql_count);
    if ($stmt_count) {
        $stmt_count->bind_param("i", $current_user_id);
        if ($stmt_count->execute()) {
            $result_count = $stmt_count->get_result()->fetch_assoc();
            $unread_notification_count = $result_count ? (int) $result_count['count'] : 0;
        } else {
            error_log("Header notification count execute failed: " . $stmt_count->error);
        }
        $stmt_count->close();
    } else {
        error_log("Header notification count prepare failed: " . $conn->error);
    }
}
// **** END: Fetch Unread Notification Count ****

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            padding-top: 70px;
            /* Adjust based on final navbar height */
        }

        .navbar {
            background-color: #0d6efd;
            /* Default blue */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 0.5rem 0;
            transition: background-color 0.3s ease;
        }

        <?php if ($is_admin): ?>
            .navbar {
                background-color: #2c3e50 !important;
                border-bottom: 3px solid #3498db;
            }

            .admin-nav-item {
                border-left: 3px solid transparent;
                transition: all 0.3s ease;
            }

            .admin-nav-item:hover,
            .admin-nav-item .nav-link.active {
                border-left-color: #3498db;
                background-color: rgba(255, 255, 255, 0.1);
            }

        <?php endif; ?>
        <?php if ($is_doctor): ?>
            .navbar {
                background-color: #16a085 !important;
                border-bottom: 3px solid #1abc9c;
            }

        <?php endif; ?>
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            margin: 0 0.15rem;
            border-radius: 4px;
            font-weight: 500;
        }

        .navbar-dark .navbar-nav .nav-link:hover,
        .navbar-dark .navbar-nav .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.15);
        }

        .navbar-dark .navbar-nav .nav-link.active {
            font-weight: 600;
        }

        .navbar-brand {
            font-size: 1.4rem;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background-color: #fff;
            color: #0d6efd;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
            margin-right: 8px;
        }

        <?php if ($is_admin): ?>
            .user-avatar {
                background-color: #3498db;
                color: white;
            }

        <?php endif; ?>
        <?php if ($is_doctor): ?>
            .user-avatar {
                background-color: #1abc9c;
                color: white;
            }

        <?php endif; ?>
        .dropdown-menu {
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
            padding: 0.5rem 0;
        }

        .dropdown-item {
            padding: 0.5rem 1rem;
            color: #333;
            font-weight: 500;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #0d6efd;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
        }

        .content-wrapper {
            padding-top: 20px;
            padding-bottom: 40px;
        }

        <?php if ($is_doctor): ?>
            .doctor-main {
                padding: 20px;
            }

        <?php endif; ?>
        /* Notification Badge specific style */
        .navbar .nav-link .badge {
            font-size: 0.65em;
            /* Smaller badge */
            padding: 0.3em 0.5em;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <?php
            $home_url = base_url('index.php'); // Default for patients/public - assumes medicare is root
            if ($is_admin) {
                $home_url = base_url('admin/dashboard.php');
            } elseif ($is_doctor) {
                $home_url = base_url('doctor/dashboard.php');
            }
            ?>
            <a class="navbar-brand fw-bold" href="<?= $home_url ?>">
                <i class="fas fa-hospital-user me-2"></i>
                Medicare<?= $is_admin ? ' Admin' : ($is_doctor ? ' Doctor' : '') ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">

                    <?php // Navigation Links based on context ?>
                    <?php if ($is_admin): ?>
                        <li class="nav-item admin-nav-item"><a
                                class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? ' active' : '' ?>"
                                href="<?= base_url('admin/dashboard.php') ?>"><i class="fas fa-tachometer-alt me-1"></i>
                                Dashboard</a></li>
                        <li class="nav-item admin-nav-item"><a
                                class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'pending_doctors.php' ? ' active' : '' ?>"
                                href="<?= base_url('admin/pending_doctors.php') ?>"><i class="fas fa-user-md me-1"></i>
                                Doctor Approvals</a></li>
                        <li class="nav-item admin-nav-item"><a
                                class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'manage_users.php' ? ' active' : '' ?>"
                                href="<?= base_url('admin/manage_users.php') ?>"><i class="fas fa-users-cog me-1"></i>
                                Manage Users</a></li>
                        <li class="nav-item admin-nav-item"><a
                                class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'medicines.php' ? ' active' : '' ?>"
                                href="<?= base_url('admin/medicines.php') ?>"><i class="fas fa-pills me-1"></i> Medicines &
                                Orders</a></li>
                        <li class="nav-item admin-nav-item"><a
                                class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'appointments.php' ? ' active' : '' ?>"
                                href="<?= base_url('admin/appointments.php') ?>"><i class="fas fa-calendar-check me-1"></i>
                                All Appointments</a></li>
                        <li class="nav-item admin-nav-item"><a
                                class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? ' active' : '' ?>"
                                href="<?= base_url('admin/reports.php') ?>"><i class="fas fa-chart-bar me-1"></i>
                                Reports</a></li>
                    <?php elseif ($is_doctor): ?>
                        <li class="nav-item"><a
                                class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? ' active' : '' ?>"
                                href="<?= base_url('doctor/dashboard.php') ?>"><i class="fas fa-tachometer-alt me-1"></i>
                                Dashboard</a></li>
                        <li class="nav-item"><a
                                class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'appointments.php' ? ' active' : '' ?>"
                                href="<?= base_url('doctor/appointments.php') ?>"><i class="fas fa-calendar-alt me-1"></i>
                                Appointments</a></li>
                        <li class="nav-item"><a
                                class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'my_patients.php' ? ' active' : '' ?>"
                                href="<?= base_url('doctor/my_patients.php') ?>"><i class="fas fa-users me-1"></i>
                                Patients</a></li>
                        <li class="nav-item"><a
                                class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'schedule.php' ? ' active' : '' ?>"
                                href="<?= base_url('doctor/schedule.php') ?>"><i class="fas fa-calendar-alt me-1"></i>
                                Schedule</a></li>
                    <?php else: // Public / Patient Navigation ?>
                        <li class="nav-item"><a
                                class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? ' active' : '' ?>"
                                href="<?= base_url('index.php') ?>">Home</a></li>
                        <li class="nav-item"><a
                                class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'appointment.php' ? ' active' : '' ?>"
                                href="<?= base_url('appointment.php') ?>">Appointments</a></li>
                        <li class="nav-item"><a
                                class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'epharmacy.php' ? ' active' : '' ?>"
                                href="<?= base_url('epharmacy.php') ?>">E-Pharmacy</a></li>
                        <li class="nav-item"><a
                                class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'contact.php' ? ' active' : '' ?>"
                                href="<?= base_url('contact.php') ?>">Contact Us</a></li>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user']) && !$is_auth): ?>
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="<?= base_url('notifications.php') ?>"
                                id="notificationLink" title="Notifications">
                                <i class="fas fa-bell"></i>
                                <?php if ($unread_notification_count > 0): ?>
                                    <span
                                        class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                                        id="notificationBadge">
                                        <?= $unread_notification_count ?>
                                        <span class="visually-hidden">unread notifications</span>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['user']) && !$is_auth): // User Dropdown Menu ?>
                        <li class="nav-item dropdown ms-lg-2">
                            <a class="nav-link dropdown-toggle d-flex align-items-center p-2" href="#" id="profileDropdown"
                                role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span
                                    class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['user']['name'] ?? 'U', 0, 1))) ?></span>
                                <span
                                    class="d-none d-lg-inline ms-1"><?= htmlspecialchars($_SESSION['user']['name'] ?? 'Account') ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="profileDropdown">
                                <?php // Role-specific dashboard links ?>
                                <?php if ($_SESSION['user']['role'] === 'admin' && !$is_admin): ?>
                                    <li><a class="dropdown-item" href="<?= base_url('admin/dashboard.php') ?>"><i
                                                class="fas fa-cog me-2"></i> Admin Panel</a></li>
                                <?php endif; ?>
                                <?php if ($_SESSION['user']['role'] === 'doctor' && !$is_doctor): ?>
                                    <li><a class="dropdown-item" href="<?= base_url('doctor/dashboard.php') ?>"><i
                                                class="fas fa-stethoscope me-2"></i> Doctor Dashboard</a></li>
                                <?php endif; ?>
                                <?php if ($_SESSION['user']['role'] === 'patient'): ?>
                                    <li><a class="dropdown-item" href="<?= base_url('patient_dashboard.php') ?>"><i
                                                class="fas fa-tachometer-alt me-2"></i> My Dashboard</a></li>
                                    <li><a class="dropdown-item" href="<?= base_url('my_appointments.php') ?>"><i
                                                class="fas fa-calendar-check me-2"></i> My Appointments</a></li>
                                    <li><a class="dropdown-item" href="<?= base_url('my_orders.php') ?>"><i
                                                class="fas fa-shopping-basket me-2"></i> My Orders</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                <?php endif; ?>
                                <?php // General links ?>
                                <li><a class="dropdown-item" href="<?= base_url('profile.php') ?>"><i
                                            class="fas fa-user-edit me-2"></i> Edit Profile</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item text-danger" href="<?= base_url('logout.php') ?>"><i
                                            class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php elseif (!$is_auth): // Login Button ?>
                        <li class="nav-item ms-lg-3">
                            <a class="btn btn-outline-light" href="<?= base_url('auth.php') ?>">
                                <i class="fas fa-sign-in-alt me-1"></i> Login / Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container content-wrapper <?= $is_doctor ? 'doctor-main' : '' ?>">
        <?php
        // Display session flash messages (if any were set before header include)
        if (isset($_SESSION['page_flash_message'])) { // Use a different key if needed
            $message = $_SESSION['page_flash_message'];
            unset($_SESSION['page_flash_message']);
            $alert_type = $message['type'] ?? 'info';
            echo "<div class='alert alert-{$alert_type} alert-dismissible fade show' role='alert'>";
            echo htmlspecialchars($message['text']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo "</div>";
        }
        ?>