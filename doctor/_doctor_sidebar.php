<?php
// _doctor_sidebar.php

// Determine the current page filename to set the active link
$current_page = basename($_SERVER['PHP_SELF']);

// Default values if variables aren't set in the parent script (optional, but safer)
$doctor_specialty = isset($doctor['specialty']) ? htmlspecialchars($doctor['specialty']) : 'Specialty N/A';
$doctor_name = isset($_SESSION['user']['name']) ? htmlspecialchars($_SESSION['user']['name']) : 'Doctor';
$doctor_initial = isset($_SESSION['user']['name']) && !empty(trim($_SESSION['user']['name'])) ? strtoupper(substr(trim($_SESSION['user']['name']), 0, 1)) : '?';

?>

<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-primary sidebar collapse text-white vh-100">
    <div class="position-sticky pt-4">
        <div class="text-center mb-4 px-3">
            <div class="user-avatar-lg mb-3 mx-auto bg-light d-flex align-items-center justify-content-center rounded-circle"
                style="width: 80px; height: 80px; font-size: 2rem; color: var(--bs-primary);">
                <?php echo $doctor_initial; ?>
            </div>
            <h5 class="text-white mb-1">Dr. <?php echo $doctor_name; ?></h5>
            <span class="badge bg-light text-primary rounded-pill"><?php echo $doctor_specialty; ?></span>
        </div>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white <?php echo ($current_page === 'dashboard.php') ? 'active bg-white bg-opacity-25 rounded' : ''; ?>"
                    href="dashboard.php">
                    <i class="fas fa-tachometer-alt fa-fw me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo ($current_page === 'appointments.php' || $current_page === 'appointment_details.php') ? 'active bg-white bg-opacity-25 rounded' : ''; ?>"
                    href="appointments.php">
                    <i class="fas fa-calendar-check fa-fw me-2"></i> Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo ($current_page === 'patients.php' || $current_page === 'patient_details.php') ? 'active bg-white bg-opacity-25 rounded' : ''; ?>"
                    href="patients.php">
                    <i class="fas fa-users fa-fw me-2"></i> My Patients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo ($current_page === 'schedule.php') ? 'active bg-white bg-opacity-25 rounded' : ''; ?>"
                    href="schedule.php">
                    <i class="fas fa-calendar-alt fa-fw me-2"></i> Schedule
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo ($current_page === 'prescriptions.php') ? 'active bg-white bg-opacity-25 rounded' : ''; ?>"
                    href="prescriptions.php">
                    <i class="fas fa-prescription fa-fw me-2"></i> Prescriptions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo ($current_page === 'profile.php') ? 'active bg-white bg-opacity-25 rounded' : ''; ?>"
                    href="profile.php">
                    <i class="fas fa-user-cog fa-fw me-2"></i> Profile Settings
                </a>
            </li>
        </ul>

        <hr class="text-white-50 mx-3">
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link text-white" href="../logout.php">
                    <i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</nav>

<style>
    .sidebar {
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        z-index: 100;
        /* Behind backdrop on mobile */
        padding: 0;
        box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        background-color: #0d6efd;
        /* Ensure primary background */
        color: #fff;
        /* Default text color */
    }

    .sidebar .nav-link {
        font-weight: 500;
        color: rgba(255, 255, 255, 0.8);
        /* Default link color */
        padding: 0.75rem 1.5rem;
        transition: background-color 0.1s ease-in-out, color 0.1s ease-in-out;
    }

    .sidebar .nav-link:hover {
        color: #fff;
        background-color: rgba(255, 255, 255, 0.1);
    }

    .sidebar .nav-link.active {
        color: #0d6efd;
        /* Primary text color */
        background-color: #fff !important;
        /* White background */
        font-weight: 600;
        border-radius: 0.25rem;
        /* Match rounded class */
    }

    /* Manual style for active background if bg-opacity/rounded isn't enough */
    /*
    .sidebar .nav-link.active.bg-opacity-25 {
        background-color: rgba(255, 255, 255, 0.25) !important;
    }
    */
    .sidebar .nav-link .fa-fw {
        width: 1.2em;
        text-align: center;
    }

    .user-avatar-lg {
        border: 3px solid rgba(255, 255, 255, 0.5);
    }

    /* Adjust main content padding when sidebar is visible */
    /* Place this in your main CSS file loaded in <head> */
    @media (min-width: 768px) {
        .main-content-area {
            /* Add this class to your <main> element */
            padding-left: 250px;
            /* Adjust this value to match sidebar width */
        }
    }
</style>