<?php
// Secure session start (MUST be at the top)
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set default page title if not defined
$page_title = $page_title ?? 'Medicare - Quality Healthcare';

// Detect current section
$is_admin = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
$is_doctor = strpos($_SERVER['REQUEST_URI'], '/doctor/') !== false;
$is_auth = (basename($_SERVER['PHP_SELF']) === 'auth.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        /* Base Navigation Styles */
        .navbar {
            background-color: #0d6efd;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 0.5rem 0;
        }

        /* Admin-specific styles */
        <?php if ($is_admin): ?>
            .navbar {
                background-color: #2c3e50 !important;
                border-bottom: 3px solid #3498db;
            }

            .admin-nav-item {
                border-left: 3px solid transparent;
                transition: all 0.3s ease;
            }

            .admin-nav-item:hover {
                border-left-color: #3498db;
                background-color: rgba(255, 255, 255, 0.1);
            }

        <?php endif; ?>

        /* Common navigation styles */
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            margin: 0 0.15rem;
            border-radius: 4px;
        }

        .navbar-dark .navbar-nav .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.15);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background-color: #fff;
            color: #0d6efd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        <?php if ($is_admin): ?>
            .user-avatar {
                background-color: #3498db;
                color: white;
            }

        <?php endif; ?>

        /* ADDED THIS NEW SECTION TO PREVENT CONTENT BLOCKING */
        body {
            padding-top: 70px;
            /* Space for fixed navbar */
        }

        .content-wrapper {
            padding-top: 20px;
        }

        /* Add to your <style> section in header.php */
        .medicine-card {
            transition: all 0.3s ease;
            opacity: 1 !important;
            /* Force opacity */
        }

        .medicine-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        /* Prevent animation flickering */
        @media (prefers-reduced-motion: reduce) {
            .medicine-card {
                transition: none;
            }
        }

        <?php if ($is_doctor): ?>
            /* Doctor dashboard specific spacing */
            .doctor-main {
                margin-left: 150px;
                /* Adjust if you have sidebar */
                padding: 20px;
            }

            @media (max-width: 768px) {
                .doctor-main {
                    margin-left: 0;
                }
            }

        <?php endif; ?>
    </style>
</head>

<!-- Added padding-top to body -->

<body style="padding-top: 70px;">

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <!-- Brand Logo -->
            <a class="navbar-brand fw-bold" href="<?= $is_admin ? 'admin/dashboard.php' : 'index.php' ?>">
                <i class="fas fa-hospital-alt me-2"></i>
                Medicare<?= $is_admin ? ' Admin' : '' ?>
            </a>

            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navbar Content -->
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <?php if ($is_admin): ?>
                        <!-- Admin Navigation -->
                        <li class="nav-item admin-nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? ' active' : '' ?>"
                                href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item admin-nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'pending_doctors.php' ? ' active' : '' ?>"
                                href="pending_doctors.php">
                                <i class="fas fa-user-md me-1"></i> Doctor Approvals
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- Main Site Navigation -->
                        <li class="nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? ' active' : '' ?>"
                                href="index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'appointment.php' ? ' active' : '' ?>"
                                href="appointment.php">Appointments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'epharmacy.php' ? ' active' : '' ?>"
                                href="epharmacy.php">E-Pharmacy</a>
                        </li>
                    <?php endif; ?>

                    <!-- User Section -->
                    <?php if (isset($_SESSION['user']) && !$is_auth): ?>
                        <li class="nav-item dropdown ms-lg-3">
                            <a class="nav-link dropdown-toggle d-flex align-items-center py-2" href="#" id="profileDropdown"
                                role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar me-2">
                                    <?= strtoupper(substr($_SESSION['user']['name'], 0, 1)) ?>
                                </div>
                                <span class="d-none d-lg-inline">
                                    <?= htmlspecialchars($_SESSION['user']['name']) ?>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($_SESSION['user']['role'] === 'admin' && !$is_admin): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php">
                                            <i class="fas fa-cog me-2"></i> Admin Panel
                                        </a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="profile.php">
                                        <i class="fas fa-user me-2"></i> Profile
                                    </a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item text-danger" href="logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                                    </a></li>
                            </ul>
                        </li>
                    <?php elseif (!$is_auth): ?>
                        <li class="nav-item ms-lg-3">
                            <a class="btn btn-outline-light" href="auth.php">
                                <i class="fas fa-sign-in-alt me-1"></i> Login
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Added content wrapper -->
    <div class="content-wrapper <?= $is_doctor ? 'doctor-main' : '' ?>">
        <!-- Your page content will appear here -->