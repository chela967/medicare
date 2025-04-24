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

// Include database configuration or connection if needed globally
// require_once __DIR__ . '/../config/database.php'; // Example path

// Set default page title if not defined by the including page
$page_title = $page_title ?? 'Medicare - Quality Healthcare';

// Detect current section based on URL path
$is_admin = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
$is_doctor = strpos($_SERVER['REQUEST_URI'], '/doctor/') !== false;
$is_auth = (basename($_SERVER['PHP_SELF']) === 'auth.php'); // Check if on auth page

// Utility function for generating URLs (optional but recommended)
function base_url($path = '')
{
    // Adjust this logic based on your server setup (e.g., http/https, domain)
    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    // If your project is in a subdirectory, add it here:
    // $base .= '/medicare'; // Example subdirectory
    return $base . '/' . ltrim($path, '/');
}

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
            /* Add padding for fixed navbar */
            padding-top: 70px;
        }

        /* Base Navigation Styles */
        .navbar {
            background-color: #0d6efd;
            /* Default blue */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 0.5rem 0;
            /* Adjust padding */
            transition: background-color 0.3s ease;
        }

        /* Admin-specific Navbar styles */
        <?php if ($is_admin): ?>
            .navbar {
                background-color: #2c3e50 !important;
                /* Dark blue-grey for admin */
                border-bottom: 3px solid #3498db;
                /* Lighter blue accent */
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

        /* Doctor-specific Navbar styles (Example) */
        <?php if ($is_doctor): ?>
            .navbar {
                background-color: #16a085 !important;
                /* Teal for doctors */
                border-bottom: 3px solid #1abc9c;
            }

        <?php endif; ?>

        /* Common navigation link styles */
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
            /* Default blue */
            border-radius: 50%;
            display: inline-flex;
            /* Changed to inline-flex */
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
            margin-right: 8px;
            /* Add spacing */
        }

        /* Adjust avatar colors based on role */
        <?php if ($is_admin): ?>
            .user-avatar {
                background-color: #3498db;
                /* Admin accent blue */
                color: white;
            }

        <?php elseif ($is_doctor): ?>
            .user-avatar {
                background-color: #1abc9c;
                /* Doctor accent teal */
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
            /* Ensure icons align */
            text-align: center;
        }

        /* Content wrapper to prevent overlap with fixed navbar */
        .content-wrapper {
            padding-top: 20px;
            /* Space below navbar */
            padding-bottom: 40px;
            /* Space at bottom */
        }

        /* Doctor specific main content spacing */
        <?php if ($is_doctor): ?>
            .doctor-main {
                /* Adjust if you add a fixed sidebar */
                margin-left: 0;
                /* Assuming no fixed sidebar for now */
                padding: 20px;
            }

            @media (min-width: 992px) {
                /* Example: Add margin if sidebar exists on larger screens */
                /* .doctor-main { margin-left: 250px; } */
            }

        <?php endif; ?>

        /* E-Pharmacy Card Styles (as previously included) */
        .medicine-card {
            transition: all 0.3s ease;
            opacity: 1 !important;
            /* Force opacity if needed */
        }

        .medicine-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        @media (prefers-reduced-motion: reduce) {
            .medicine-card {
                transition: none;
            }
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <?php
            // Determine base URL based on role for the logo link
            $home_url = base_url('medicare/index.php'); // Default for patients/public
            if ($is_admin) {
                $home_url = base_url('medicare/admin/dashboard.php');
            } elseif ($is_doctor) {
                $home_url = base_url('medicare/doctor/dashboard.php'); // Assuming doctor dashboard exists
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

                    <?php if ($is_admin): ?>
                        <li class="nav-item admin-nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? ' active' : '' ?>"
                                href="<?= base_url('admin/dashboard.php') ?>">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item admin-nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'pending_doctors.php' ? ' active' : '' ?>"
                                href="<?= base_url('admin/pending_doctors.php') ?>">
                                <i class="fas fa-user-md me-1"></i> Doctor Approvals
                            </a>
                        </li>
                        <li class="nav-item admin-nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'manage_users.php' ? ' active' : '' ?>"
                                href="<?= base_url('admin/manage_users.php') ?>">
                                <i class="fas fa-users-cog me-1"></i> Manage Users
                            </a>
                        </li>
                        <?/* Add other admin links */ ?>

                    <?php elseif ($is_doctor): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? ' active' : '' ?>"
                                href="<?= base_url('medicare/doctor/dashboard.php') ?>">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'my_appointments.php' ? ' active' : '' ?>"
                                href="<?= base_url('medicare/doctor/my_appointments.php') ?>">
                                <i class="fas fa-calendar-alt me-1"></i> Appointments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'my_patients.php' ? ' active' : '' ?>"
                                href="<?= base_url('medicare/doctor/my_patients.php') ?>">
                                <i class="fas fa-users me-1"></i> Patients
                            </a>
                        </li>
                        <?/* Add other doctor links */ ?>

                    <?php else: // Public / Patient Navigation ?>
                        <li class="nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? ' active' : '' ?>"
                                href="<?= base_url('medicare/index.php') ?>">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'find_doctor.php' ? ' active' : '' ?>"
                                href="<?= base_url('medicare/find_doctor.php') ?>">Find a Doctor</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'appointment.php' ? ' active' : '' ?>"
                                href="<?= base_url('medicare/appointment.php') ?>">Appointments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'epharmacy.php' ? ' active' : '' ?>"
                                href="<?= base_url('medicare/epharmacy.php') ?>">E-Pharmacy</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'contact.php' ? ' active' : '' ?>"
                                href="<?= base_url('contact.php') ?>">Contact Us</a>
                        </li>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user']) && !$is_auth): // Show dropdown if logged in AND not on auth page ?>
                        <li class="nav-item dropdown ms-lg-3">
                            <a class="nav-link dropdown-toggle d-flex align-items-center p-2" href="#" id="profileDropdown"
                                role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="user-avatar">
                                    <?php // Display first letter of name, fallback to 'U' ?>
                                    <?= htmlspecialchars(strtoupper(substr($_SESSION['user']['name'] ?? 'User', 0, 1))) ?>
                                </span>
                                <span class="d-none d-lg-inline ms-1"> <?/* Show name only on large screens */ ?>
                                    <?= htmlspecialchars($_SESSION['user']['name'] ?? 'Account') ?>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="profileDropdown">

                                <?php // Admin Panel Link (only if admin and not already in admin area) ?>
                                <?php if ($_SESSION['user']['role'] === 'admin' && !$is_admin): ?>
                                    <li><a class="dropdown-item" href="<?= base_url('admin/dashboard.php') ?>">
                                            <i class="fas fa-cog me-2"></i> Admin Panel
                                        </a></li>
                                <?php endif; ?>

                                <?php // Doctor Dashboard Link (only if doctor and not already in doctor area) ?>
                                <?php if ($_SESSION['user']['role'] === 'doctor' && !$is_doctor): ?>
                                    <li><a class="dropdown-item" href="<?= base_url('medicare/doctor/dashboard.php') ?>">
                                            <i class="fas fa-stethoscope me-2"></i> Doctor Dashboard
                                        </a></li>
                                <?php endif; ?>

                                <?php // Patient Dashboard/Account Links (only if patient) ?>
                                <?php if ($_SESSION['user']['role'] === 'patient'): ?>
                                    <li><a class="dropdown-item" href="<?= base_url('medicare/patient_dashboard.php') ?>">
                                            <i class="fas fa-tachometer-alt me-2"></i> My Dashboard
                                        </a></li>
                                    <li><a class="dropdown-item" href="<?= base_url('medicare/my_appointments.php') ?>">
                                            <i class="fas fa-calendar-check me-2"></i> My Appointments
                                        </a></li>
                                    <li><a class="dropdown-item" href="<?= base_url('medicare/my_prescriptions.php') ?>">
                                            <i class="fas fa-file-prescription me-2"></i> My Prescriptions
                                        </a></li>
                                    <li><a class="dropdown-item" href="<?= base_url('medicare/my_orders.php') ?>">
                                            <i class="fas fa-shopping-basket me-2"></i> My Orders (Pharmacy)
                                        </a></li>
                                    <?php // Add more patient-specific links here ?>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                <?php endif; ?>

                                <?php // General links for all logged-in users ?>
                                <li><a class="dropdown-item" href="<?= base_url('profile.php') ?>">
                                        <i class="fas fa-user-edit me-2"></i> Edit Profile
                                    </a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item text-danger" href="<?= base_url('medicare/logout.php') ?>">
                                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                                    </a></li>
                            </ul>
                        </li>
                    <?php elseif (!$is_auth): // Show Login button if not logged in AND not on auth page ?>
                        <li class="nav-item ms-lg-3">
                            <a class="btn btn-outline-light" href="<?= base_url('medicare/auth.php') ?>">
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
        // Optional: Display session flash messages (e.g., for login success/failure)
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']); // Clear after displaying
            $alert_type = $message['type'] ?? 'info'; // Default to info
            echo "<div class='alert alert-{$alert_type} alert-dismissible fade show' role='alert'>";
            echo htmlspecialchars($message['text']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo "</div>";
        }
        ?>
        ```